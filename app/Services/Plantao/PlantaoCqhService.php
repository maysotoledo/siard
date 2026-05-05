<?php

namespace App\Services\Plantao;

use App\Models\AfastamentoSolicitacao;
use App\Models\PlantaoCqhExterno;
use App\Models\PlantaoCqhServidor;
use App\Models\PlantaoEscala;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlantaoCqhService
{
    /**
     * Gera a escala CQH mensal com algoritmo inteligente de rotação.
     *
     * Regra de descanso: um servidor não pode fazer CQH se tiver plantão
     * no dia anterior, no mesmo dia ou no dia seguinte (intervalo mínimo de 24h).
     * O algoritmo percorre a lista de aptos em rotação e pula quem estiver bloqueado,
     * garantindo distribuição justa mesmo com restrições de descanso.
     */
    public function gerarEscalaCqhMensal(int $mes, int $ano): int
    {
        $aptos = $this->pessoasAptas();

        if ($aptos->isEmpty()) {
            return 0;
        }

        $inicio = Carbon::create($ano, $mes, 1);
        $fim = $inicio->copy()->endOfMonth();

        // Pré-carrega mapa de plantões: data_string => [user_id, ...]
        // Inclui ±1 dia do mês para cobrir bordas do intervalo de 24h
        $plantoesPorData = $this->carregarPlantoesPorData(
            $inicio->copy()->subDay(),
            $fim->copy()->addDay()
        );

        $count = 0;
        $total = $aptos->count();
        $rotacao = 0; // índice atual na lista de rotação

        for ($date = $inicio->copy(); $date->lte($fim); $date->addDay()) {
            $escala = PlantaoEscala::query()
                ->whereDate('data_plantao', $date->toDateString())
                ->first();

            if (! $escala) {
                continue;
            }

            // Monta set de user_ids bloqueados por conflito de 24h (dia -1, dia, dia +1)
            $bloqueados = array_unique(array_merge(
                $plantoesPorData[$date->copy()->subDay()->toDateString()] ?? [],
                $plantoesPorData[$date->toDateString()] ?? [],
                $plantoesPorData[$date->copy()->addDay()->toDateString()] ?? [],
            ));

            // Algoritmo de rotação inteligente: tenta cada candidato a partir do índice atual
            $atribuido = false;
            for ($tentativa = 0; $tentativa < $total; $tentativa++) {
                $idx = ($rotacao + $tentativa) % $total;
                $candidato = $aptos[$idx];

                if ($this->candidatoEstaBloqueado($candidato, $bloqueados, $date)) {
                    continue;
                }

                $this->setCqhPessoaForDay($escala, $candidato['key']);
                $rotacao = ($idx + 1) % $total; // avança rotação para além do candidato escolhido
                $count++;
                $atribuido = true;
                break;
            }

            // Fallback: todos bloqueados — atribui o próximo da rotação sem restrição
            if (! $atribuido) {
                $this->setCqhPessoaForDay($escala, $aptos[$rotacao % $total]['key']);
                $rotacao = ($rotacao + 1) % $total;
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retorna mapa [data_string => [user_id, ...]] de servidores escalados para plantão
     * dentro do período informado (inclusive).
     */
    private function carregarPlantoesPorData(Carbon $inicio, Carbon $fim): array
    {
        $escalas = PlantaoEscala::query()
            ->whereBetween('data_plantao', [$inicio->toDateString(), $fim->toDateString()])
            ->whereNotNull('equipe_id')
            ->with(['equipe.servidores' => fn ($q) => $q->where('ativo', true)])
            ->get();

        $mapa = [];
        foreach ($escalas as $escala) {
            $dataStr = $escala->data_plantao->toDateString();
            $mapa[$dataStr] ??= [];
            foreach ($escala->equipe?->servidores ?? [] as $membro) {
                $mapa[$dataStr][] = $membro->user_id;
            }
        }

        return $mapa;
    }

    /**
     * Verifica se um candidato está bloqueado para CQH em determinada data.
     * Servidores externos não têm plantão no sistema e nunca são bloqueados por esta regra.
     */
    private function candidatoEstaBloqueado(array $candidato, array $bloqueados, Carbon $date): bool
    {
        if (str_starts_with($candidato['key'], 'externo:')) {
            return false;
        }

        [, $userId] = explode(':', $candidato['key'], 2);
        $userId = (int) $userId;

        if (in_array($userId, $bloqueados, true)) {
            return true;
        }

        if ($this->estaAfastado($userId, $date)) {
            return true;
        }

        return false;
    }

    public function setCqhForDay(PlantaoEscala $escala, int $userId): PlantaoEscala
    {
        return $this->setCqhPessoaForDay($escala, 'user:'.$userId);
    }

    public function setCqhPessoaForDay(PlantaoEscala $escala, string|int $pessoaKey): PlantaoEscala
    {
        [$type, $id] = $this->parsePessoaKey($pessoaKey);
        $pessoa = $type::query()->findOrFail($id);

        if (! $this->pessoaEstaApta($pessoa)) {
            throw ValidationException::withMessages(['cqh_geral_id' => 'Servidor não está apto ao CQH Geral.']);
        }

        if ($pessoa instanceof User && $this->estaAfastado($pessoa->id, $escala->data_plantao)) {
            throw ValidationException::withMessages(['cqh_geral_id' => 'Servidor está afastado nesta data.']);
        }

        $escala->forceFill([
            'cqh_geral_type' => $type,
            'cqh_geral_id' => $id,
            'status' => 'alterada',
        ])->save();

        app(PlantaoHistoricoService::class)->registrar($escala, 'cqh_alterado', 'CQH Geral alterado.', [
            'cqh_geral_type' => $type,
            'cqh_geral_id' => $id,
        ]);

        return $escala->refresh();
    }

    public function cqhOptions(): array
    {
        return $this->pessoasAptas()
            ->mapWithKeys(fn (array $row): array => [$row['key'] => $row['label']])
            ->all();
    }

    public function nomePessoa(Model $pessoa, bool $curto = false): string
    {
        $derf = method_exists($pessoa, 'isDerf') && $pessoa->isDerf();

        if ($curto) {
            // Prioridade 1: nome_calendario configurado manualmente no cadastro
            $nomeCalendario = $this->nomeCalendarioConfigurado($pessoa);
            if ($nomeCalendario !== null) {
                return mb_strtoupper($nomeCalendario) . ($derf ? '(DERF)' : '');
            }

            // Prioridade 2: abreviação automática via IA/fallback
            $nomeBase = $pessoa instanceof PlantaoCqhExterno ? $pessoa->nome : $pessoa->name;
            return $this->nomeCurto($nomeBase) . ($derf ? '(DERF)' : '');
        }

        $nome = mb_strtoupper($pessoa instanceof PlantaoCqhExterno ? $pessoa->nome : $pessoa->name);
        return $nome . ($derf ? '(DERF)' : '');
    }

    /**
     * Retorna o nome_calendario configurado no cadastro, se existir.
     * Para User: busca via plantaoCqh (já eager-loaded no calendário).
     * Para PlantaoCqhExterno: lê diretamente do model.
     */
    private function nomeCalendarioConfigurado(Model $pessoa): ?string
    {
        if ($pessoa instanceof PlantaoCqhExterno) {
            return filled($pessoa->nome_calendario) ? trim($pessoa->nome_calendario) : null;
        }

        // User: tenta via relacionamento plantaoCqh (carregado pelo calendário)
        if ($pessoa instanceof User) {
            $cqh = $pessoa->relationLoaded('plantaoCqh')
                ? $pessoa->plantaoCqh
                : $pessoa->plantaoCqh()->first();

            return ($cqh && filled($cqh->nome_calendario)) ? trim($cqh->nome_calendario) : null;
        }

        return null;
    }

    public function keyFor(Model $pessoa): string
    {
        return $pessoa instanceof PlantaoCqhExterno ? 'externo:'.$pessoa->id : 'user:'.$pessoa->id;
    }

    /**
     * @return array{0: class-string<Model>, 1: int}
     */
    public function parsePessoaKey(string|int $key): array
    {
        if (is_int($key) || ctype_digit((string) $key)) {
            return [User::class, (int) $key];
        }

        [$tipo, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

        return [
            match ($tipo) {
                'user' => User::class,
                'externo' => PlantaoCqhExterno::class,
                default => throw ValidationException::withMessages(['cqh_geral_id' => 'Tipo de servidor CQH inválido.']),
            },
            (int) $id,
        ];
    }

    private function pessoasAptas(): Collection
    {
        $internos = PlantaoCqhServidor::query()
            ->with('user')
            ->where('ativo', true)
            ->where('apto_cqh', true)
            ->get()
            ->map(fn (PlantaoCqhServidor $row): array => [
                'id' => $row->id,
                'key' => 'user:'.$row->user_id,
                'label' => ($row->user?->name ?? 'Servidor').($row->unidade_operacional === 'DERF_CONFRESA' ? '(DERF)' : ''),
            ]);

        $externos = PlantaoCqhExterno::query()
            ->where('ativo', true)
            ->where('apto_cqh', true)
            ->get()
            ->map(fn (PlantaoCqhExterno $row): array => [
                'id' => $row->id,
                'key' => 'externo:'.$row->id,
                'label' => $row->nome.($row->isDerf() ? '(DERF)' : ''),
            ]);

        // Ordena alfabeticamente pelo nome para rotação consistente e imparcial
        return $internos
            ->concat($externos)
            ->sortBy('label')
            ->values();
    }

    private function pessoaEstaApta(Model $pessoa): bool
    {
        if ($pessoa instanceof PlantaoCqhExterno) {
            return $pessoa->ativo && $pessoa->apto_cqh;
        }

        return $pessoa instanceof User && PlantaoCqhServidor::query()
            ->where('user_id', $pessoa->id)
            ->where('ativo', true)
            ->where('apto_cqh', true)
            ->exists();
    }

    private function nomeCurto(string $nome): string
    {
        return app(PlantaoNomeService::class)->abreviar($nome);
    }

    private function estaAfastado(int $userId, Carbon $date): bool
    {
        return class_exists(AfastamentoSolicitacao::class)
            && AfastamentoSolicitacao::query()
                ->where('user_id', $userId)
                ->whereIn('status', ['aprovado', 'em_analise'])
                ->whereDate('data_inicio', '<=', $date->toDateString())
                ->whereDate('data_fim', '>=', $date->toDateString())
                ->exists();
    }
}
