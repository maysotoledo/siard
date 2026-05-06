<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\NivelImpacto;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;

class AfastamentoImpactScoreService
{
    public function calcular(AfastamentoSolicitacao $solicitacao): array
    {
        $conflitos = app(AfastamentoConflictService::class)->detectar($solicitacao);
        $score = 0;

        foreach ($conflitos as $conflito) {
            $score += match ($conflito['nivel'] ?? null) {
                NivelImpacto::CRITICO->value => 40,
                NivelImpacto::ALTO->value => 24,
                NivelImpacto::MODERADO->value => 12,
                default => 4,
            };
        }

        if ($solicitacao->data_inicio && now()->diffInDays($solicitacao->data_inicio, false) <= 15) {
            $score += 5;
        }

        $funcao = $solicitacao->user?->funcao_operacional;
        $regraOperacional = $funcao
            ? AfastamentoRegraOperacional::query()
                ->ativa()
                ->where('funcao_operacional', $funcao->value)
                ->orderByDesc('id')
                ->first()
            : null;

        if (collect($conflitos)->contains(fn (array $conflito): bool => ($conflito['origem'] ?? null) === 'operacional')) {
            $score += 5;
        }

        if ($regraOperacional?->prioridade_operacional) {
            $score += 5;
        }

        if ($funcao === FuncaoOperacional::IPC_PLANTAO) {
            $score += $this->ajusteCoberturaPlantao($solicitacao, FuncaoOperacional::IPC_EXPEDIENTE);
        }

        if ($funcao === FuncaoOperacional::EPC_PLANTAO) {
            $score += $this->ajusteCoberturaPlantao($solicitacao, FuncaoOperacional::EPC_EXPEDIENTE);
        }

        if (in_array($funcao, [FuncaoOperacional::IPC_EXPEDIENTE, FuncaoOperacional::EPC_EXPEDIENTE, FuncaoOperacional::CARTORIO_CENTRAL, FuncaoOperacional::DPC], true)) {
            $operacional = app(AfastamentoOperacionalService::class);
            $disponiveis = $operacional->disponiveisDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id) - 1;
            $score += $disponiveis < $operacional->minimoDisponivel($funcao) ? 20 : 4;
        }

        $score = min(100, max(0, $score));

        return [
            'score' => $score,
            'nivel' => NivelImpacto::fromScore($score),
            'conflitos' => $conflitos,
        ];
    }

    private function ajusteCoberturaPlantao(AfastamentoSolicitacao $solicitacao, FuncaoOperacional $funcaoExpediente): int
    {
        $operacional = app(AfastamentoOperacionalService::class);
        $disponiveisCobertura = $operacional->disponiveisDaFuncao($funcaoExpediente, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id);
        $minimoCobertura = $operacional->minimoDisponivel($funcaoExpediente);
        $restariam = $disponiveisCobertura - 1;
        $haCandidatos = $operacional->servidoresDisponiveisParaCobertura($solicitacao) !== [];
        $coberturaAprovada = $operacional->coberturaAprovada($solicitacao);

        if (! $haCandidatos) {
            return 30;
        }

        if ($restariam < $minimoCobertura) {
            return $coberturaAprovada ? 22 : 18;
        }

        if ($restariam === $minimoCobertura) {
            return $coberturaAprovada ? 10 : 8;
        }

        return $coberturaAprovada ? 2 : 3;
    }
}
