<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\NivelImpacto;
use App\Enums\StatusAfastamento;
use App\Models\AfastamentoPeriodoBloqueado;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;
use App\Models\User;

class AfastamentoConflictService
{
    public function detectar(AfastamentoSolicitacao $solicitacao): array
    {
        $conflitos = [];

        $mesmoServidor = AfastamentoSolicitacao::query()
            ->where('user_id', $solicitacao->user_id)
            ->whereKeyNot($solicitacao->id ?? 0)
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->whereIn('status', [
                StatusAfastamento::SOLICITADO->value,
                StatusAfastamento::EM_ANALISE->value,
                StatusAfastamento::APROVADO->value,
            ])
            ->exists();

        if ($mesmoServidor) {
            $conflitos[] = $this->conflito(
                NivelImpacto::CRITICO,
                'Servidor ja possui afastamento no periodo.',
                'Escolha um periodo sem sobreposicao para o mesmo servidor.',
            );
        }

        $bloqueado = AfastamentoPeriodoBloqueado::query()
            ->ativo()
            ->where(function ($query) use ($solicitacao): void {
                $query
                    ->whereNull('tipo_afastamento')
                    ->orWhere('tipo_afastamento', $solicitacao->tipo_afastamento->value);
            })
            ->whereDate('data_inicio', '<=', $solicitacao->data_fim)
            ->whereDate('data_fim', '>=', $solicitacao->data_inicio)
            ->first();

        if ($bloqueado) {
            $conflitos[] = $this->conflito(
                NivelImpacto::CRITICO,
                'Periodo bloqueado: ' . $bloqueado->motivo,
                'Ajuste a data para fora do periodo bloqueado.',
            );
        }

        $conflitos = array_merge($conflitos, $this->conflitosOperacionais($solicitacao));

        return $conflitos;
    }

    public function possuiCritico(AfastamentoSolicitacao $solicitacao): bool
    {
        return collect($this->detectar($solicitacao))
            ->contains(fn (array $conflito): bool => ($conflito['nivel'] ?? null) === NivelImpacto::CRITICO->value);
    }

    private function conflitosOperacionais(AfastamentoSolicitacao $solicitacao): array
    {
        $user = $solicitacao->user ?: User::query()->find($solicitacao->user_id);
        $funcao = $user?->funcao_operacional;

        if (! $user || ! $funcao || ! $solicitacao->data_inicio || ! $solicitacao->data_fim) {
            return [];
        }

        $operacional = app(AfastamentoOperacionalService::class);
        $conflitos = [];
        $conflitoPrioridade = $this->conflitoPrioridade($solicitacao, $user, $funcao);

        if ($conflitoPrioridade) {
            $conflitos[] = $conflitoPrioridade;
        }

        if ($operacional->servidorEstaEmCobertura($user, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id)) {
            $conflitos[] = $this->conflito(
                NivelImpacto::CRITICO,
                'Servidor esta designado como cobertura de plantao no periodo.',
                'Cancele ou substitua a cobertura antes de aprovar este afastamento.',
                'operacional',
            );
        }

        if (! in_array($funcao, [FuncaoOperacional::IPC_PLANTAO, FuncaoOperacional::EPC_PLANTAO], true)) {
            $disponiveisAntes = $operacional->disponiveisDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id);
            $disponiveisAposAfastamento = $disponiveisAntes - 1;
            $minimo = $operacional->minimoDisponivel($funcao);

            if ($disponiveisAposAfastamento < $minimo) {
                $conflitos[] = $this->conflito(
                    NivelImpacto::CRITICO,
                    "Afastamento deixara {$funcao->label()} com {$disponiveisAposAfastamento} disponivel(is), abaixo do minimo de {$minimo}.",
                    "Hoje existem {$disponiveisAntes} servidor(es) disponivel(is) em {$funcao->label()}. Com este afastamento, restariam {$disponiveisAposAfastamento}.",
                    'operacional',
                );
            } elseif ($disponiveisAposAfastamento === $minimo) {
                $conflitos[] = $this->conflito(
                    NivelImpacto::MODERADO,
                    "{$funcao->label()} ficara no limite minimo operacional.",
                    "Hoje existem {$disponiveisAntes} servidor(es) disponivel(is) em {$funcao->label()}. Com este afastamento, restariam exatamente {$minimo}.",
                    'operacional',
                );
            }
        }

        if ($funcao === FuncaoOperacional::IPC_PLANTAO) {
            $conflitos[] = $this->conflitoCoberturaPlantao(
                $solicitacao,
                $operacional,
                FuncaoOperacional::IPC_PLANTAO,
                FuncaoOperacional::IPC_EXPEDIENTE,
            );
        }

        if ($funcao === FuncaoOperacional::EPC_PLANTAO) {
            $conflitos[] = $this->conflitoCoberturaPlantao(
                $solicitacao,
                $operacional,
                FuncaoOperacional::EPC_PLANTAO,
                FuncaoOperacional::EPC_EXPEDIENTE,
            );
        }

        $regraOperacional = AfastamentoRegraOperacional::query()
            ->ativa()
            ->where('funcao_operacional', $funcao->value)
            ->orderByDesc('id')
            ->first();

        $simultaneos = $operacional->afastadosDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id);

        if ($regraOperacional && $simultaneos >= (int) $regraOperacional->maximo_afastados_simultaneos) {
            $conflitos[] = $this->conflito(
                NivelImpacto::CRITICO,
                'Excesso de afastados simultaneos para ' . $funcao->label() . '.',
                'Escolha periodo com menor concentracao ou ajuste a regra operacional com justificativa.',
                'operacional',
            );
        }

        return array_values(array_filter($conflitos));
    }

    private function conflitoPrioridade(AfastamentoSolicitacao $solicitacao, User $user, FuncaoOperacional $funcao): ?array
    {
        $analise = app(AfastamentoPrioridadeService::class)->analisarConflitosPorPrioridade($solicitacao);
        $ranking = $analise['ranking'] ?? [];

        if (count($ranking) <= 1) {
            return null;
        }

        $preferido = $analise['preferido']['servidor'] ?? 'nao definido';
        $total = count($ranking);

        return $this->conflito(
            NivelImpacto::MODERADO,
            "Ha {$total} servidores/solicitacoes conflitantes na mesma funcao operacional. Prioridade recomendada: {$preferido}.",
            'Verifique a fila de prioridade: ingresso na carreira, ingresso na unidade e ordem da solicitacao. A decisao continua subordinada ao interesse do servico, efetivo minimo e cobertura operacional.',
            'prioridade',
        );
    }

    private function conflitoCoberturaPlantao(
        AfastamentoSolicitacao $solicitacao,
        AfastamentoOperacionalService $operacional,
        FuncaoOperacional $funcaoPlantao,
        FuncaoOperacional $funcaoExpediente,
    ): array {
        $disponiveisCobertura = $operacional->disponiveisDaFuncao($funcaoExpediente, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id);
        $minimoCobertura = $operacional->minimoDisponivel($funcaoExpediente);
        $restariam = max(0, $disponiveisCobertura - 1);
        $haCandidatos = $operacional->servidoresDisponiveisParaCobertura($solicitacao) !== [];
        $coberturaAprovada = $operacional->coberturaAprovada($solicitacao);
        $ficaraAbaixoDoMinimo = $restariam < $minimoCobertura;
        $ficaraNoMinimo = $restariam === $minimoCobertura;

        if ($coberturaAprovada && $ficaraAbaixoDoMinimo) {
            return $this->conflito(
                NivelImpacto::CRITICO,
                "{$funcaoPlantao->label()} coberto, mas {$funcaoExpediente->label()} ficara abaixo do minimo.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Com 1 deslocamento aprovado para cobertura, restariam {$restariam}.",
                'operacional',
            );
        }

        if ($coberturaAprovada && $ficaraNoMinimo) {
            return $this->conflito(
                NivelImpacto::MODERADO,
                "{$funcaoPlantao->label()} ja possui cobertura aprovada e deixara {$funcaoExpediente->label()} no limite minimo.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Com a cobertura aprovada, restariam exatamente {$restariam}.",
                'operacional',
            );
        }

        if ($coberturaAprovada) {
            return $this->conflito(
                NivelImpacto::BAIXO,
                "{$funcaoPlantao->label()} ja possui cobertura aprovada.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Com a cobertura aprovada, restariam {$restariam}.",
                'operacional',
            );
        }

        if (! $haCandidatos) {
            return $this->conflito(
                NivelImpacto::CRITICO,
                "{$funcaoPlantao->label()} esta sem cobertura disponivel.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Nenhum servidor pode ser deslocado para cobrir o plantao.",
                'operacional',
            );
        }

        if ($ficaraAbaixoDoMinimo) {
            return $this->conflito(
                NivelImpacto::ALTO,
                "{$funcaoPlantao->label()} depende de cobertura, mas {$funcaoExpediente->label()} ficaria abaixo do minimo.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Se 1 servidor for deslocado para cobertura, restariam {$restariam}.",
                'operacional',
            );
        }

        if ($ficaraNoMinimo) {
            return $this->conflito(
                NivelImpacto::MODERADO,
                "{$funcaoPlantao->label()} depende de cobertura com {$funcaoExpediente->label()} no limite minimo.",
                "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Se 1 servidor for deslocado para cobertura, restariam exatamente {$restariam}.",
                'operacional',
            );
        }

        return $this->conflito(
            NivelImpacto::BAIXO,
            "{$funcaoPlantao->label()} depende apenas da aprovacao da cobertura.",
            "{$funcaoExpediente->label()} tem {$disponiveisCobertura} servidor(es) disponivel(is), com minimo operacional de {$minimoCobertura}. Se 1 servidor for deslocado para cobertura, restariam {$restariam}.",
            'operacional',
        );
    }

    private function conflito(NivelImpacto $nivel, string $mensagem, string $sugestao, string $origem = 'geral'): array
    {
        return [
            'nivel' => $nivel->value,
            'mensagem' => $mensagem,
            'sugestao' => $sugestao,
            'origem' => $origem,
        ];
    }
}
