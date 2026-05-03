<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Enums\NivelImpacto;
use App\Models\AfastamentoSolicitacao;

class AfastamentoImpactScoreService
{
    public function calcular(AfastamentoSolicitacao $solicitacao): array
    {
        $conflitos = app(AfastamentoConflictService::class)->detectar($solicitacao);
        $score = min(100, count($conflitos) * 25);

        foreach ($conflitos as $conflito) {
            $score += match ($conflito['nivel'] ?? null) {
                NivelImpacto::CRITICO->value => 35,
                NivelImpacto::ALTO->value => 25,
                NivelImpacto::MODERADO->value => 15,
                default => 5,
            };
        }

        if ($solicitacao->data_inicio && now()->diffInDays($solicitacao->data_inicio, false) <= 15) {
            $score += 10;
        }

        $funcao = $solicitacao->user?->funcao_operacional;
        if ($funcao === FuncaoOperacional::IPC_PLANTAO) {
            $score += app(AfastamentoOperacionalService::class)->coberturaAprovada($solicitacao) ? 20 : 45;
        }

        if ($funcao === FuncaoOperacional::IPC_EXPEDIENTE) {
            $operacional = app(AfastamentoOperacionalService::class);
            $disponiveis = $operacional->disponiveisDaFuncao($funcao, $solicitacao->data_inicio, $solicitacao->data_fim, $solicitacao->id) - 1;
            $score += $disponiveis < $operacional->minimoDisponivel($funcao) ? 45 : 10;
        }

        $score = min(100, max(0, $score));

        return [
            'score' => $score,
            'nivel' => NivelImpacto::fromScore($score),
            'conflitos' => $conflitos,
        ];
    }
}
