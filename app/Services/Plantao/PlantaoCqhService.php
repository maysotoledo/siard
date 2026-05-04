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
    public function gerarEscalaCqhMensal(int $mes, int $ano): int
    {
        $aptos = $this->pessoasAptas();

        if ($aptos->isEmpty()) {
            return 0;
        }

        $count = 0;
        $i = 0;
        $inicio = Carbon::create($ano, $mes, 1);
        for ($date = $inicio->copy(); $date->lte($inicio->copy()->endOfMonth()); $date->addDay()) {
            $escala = PlantaoEscala::query()->whereDate('data_plantao', $date->toDateString())->first();
            if (! $escala) {
                continue;
            }

            $this->setCqhPessoaForDay($escala, $aptos[$i % $aptos->count()]['key']);
            $i++;
            $count++;
        }

        return $count;
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
        $nome = $pessoa instanceof PlantaoCqhExterno ? $pessoa->nome : $pessoa->name;
        $nome = $curto ? $this->nomeCurto($nome) : mb_strtoupper($nome);
        $derf = method_exists($pessoa, 'isDerf') && $pessoa->isDerf();

        return $nome.($derf ? '(DERF)' : '');
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
            ->orderByRaw('ordem is null, ordem')
            ->get()
            ->map(fn (PlantaoCqhServidor $row): array => [
                'ordem' => $row->ordem,
                'id' => $row->id,
                'key' => 'user:'.$row->user_id,
                'label' => ($row->user?->name ?? 'Servidor').($row->unidade_operacional === 'DERF_CONFRESA' ? '(DERF)' : ''),
            ]);

        $externos = PlantaoCqhExterno::query()
            ->where('ativo', true)
            ->where('apto_cqh', true)
            ->orderByRaw('ordem is null, ordem')
            ->get()
            ->map(fn (PlantaoCqhExterno $row): array => [
                'ordem' => $row->ordem,
                'id' => $row->id,
                'key' => 'externo:'.$row->id,
                'label' => $row->nome.($row->isDerf() ? '(DERF)' : ''),
            ]);

        return $internos
            ->concat($externos)
            ->sortBy([
                fn (array $a, array $b): int => (($a['ordem'] === null) <=> ($b['ordem'] === null)),
                fn (array $a, array $b): int => ($a['ordem'] ?? PHP_INT_MAX) <=> ($b['ordem'] ?? PHP_INT_MAX),
                fn (array $a, array $b): int => strcmp($a['key'], $b['key']),
            ])
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
        $partes = preg_split('/\s+/', trim($nome)) ?: [];

        return mb_strtoupper(implode(' ', array_slice(array_filter($partes), 0, 2)));
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
