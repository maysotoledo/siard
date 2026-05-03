<?php

namespace App\Services\Afastamentos;

use App\Enums\FuncaoOperacional;
use App\Models\AfastamentoSolicitacao;
use Carbon\Carbon;

class AfastamentoSuggestionService
{
    public function sugerir(AfastamentoSolicitacao $solicitacao, int $quantidade = 3): array
    {
        if ($solicitacao->user?->funcao_operacional === FuncaoOperacional::IPC_PLANTAO) {
            $coberturas = app(AfastamentoOperacionalService::class)->servidoresDisponiveisParaCobertura($solicitacao);

            if ($coberturas !== []) {
                return collect($coberturas)
                    ->take($quantidade)
                    ->map(fn (string $nome, int $id): array => [
                        'tipo' => 'cobertura_plantao',
                        'servidor_cobertura_id' => $id,
                        'label' => 'Cobertura sugerida: ' . $nome,
                        'data_inicio' => $solicitacao->data_inicio?->toDateString(),
                        'data_fim' => $solicitacao->data_fim?->toDateString(),
                    ])
                    ->values()
                    ->all();
            }
        }

        $dias = max(1, (int) $solicitacao->dias_solicitados);
        $inicio = $solicitacao->data_inicio?->copy() ?: now()->addWeek()->startOfDay();
        $sugestoes = [];

        for ($cursor = $inicio->copy()->addWeek(), $tentativas = 0; count($sugestoes) < $quantidade && $tentativas < 12; $cursor->addWeek(), $tentativas++) {
            $fim = $cursor->copy()->addDays($dias - 1);
            $clone = $solicitacao->replicate();
            $clone->data_inicio = $cursor->toDateString();
            $clone->data_fim = $fim->toDateString();
            $clone->setRelation('user', $solicitacao->user);

            if (! app(AfastamentoConflictService::class)->possuiCritico($clone)) {
                $sugestoes[] = [
                    'data_inicio' => $cursor->toDateString(),
                    'data_fim' => $fim->toDateString(),
                    'label' => Carbon::parse($cursor)->format('d/m/Y') . ' a ' . Carbon::parse($fim)->format('d/m/Y'),
                ];
            }
        }

        return array_slice($sugestoes, 0, $quantidade);
    }
}
