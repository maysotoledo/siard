<?php

namespace App\Services\Plantao;

use App\Models\AfastamentoSolicitacao;
use App\Models\PlantaoEscala;
use App\Models\PlantaoPermuta;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Propaga as coberturas aprovadas de um afastamento para a escala de plantão,
 * criando/removendo PlantaoPermuta automáticas nas escalas afetadas.
 *
 * Cada permuta auto-gerada é marcada no campo "motivo" com o prefixo
 * "[AFASTAMENTO #<id>]" para permitir localização/idempotência sem alterar
 * o schema existente da tabela plantao_permutas.
 */
class PlantaoSubstituicaoService
{
    /**
     * Cria as permutas automáticas de plantão para o afastamento, com base nas
     * coberturas aprovadas. Ignora afastamentos sem cobertura aprovada (a
     * propagação acontece somente quando há um substituto formalmente indicado).
     *
     * Idempotente: pode ser chamada múltiplas vezes sem criar duplicatas.
     */
    public function aplicar(AfastamentoSolicitacao $solicitacao): int
    {
        $solicitacao->loadMissing('user');

        $coberturas = $solicitacao->coberturasPlantao()
            ->where('status', 'aprovada')
            ->with('servidorCobertura')
            ->get();

        if ($coberturas->isEmpty()) {
            return 0;
        }

        $tipoFuncao = $this->tipoFuncaoPlantao($solicitacao);

        if ($tipoFuncao === null) {
            // Servidor não é de plantão — nada a propagar.
            return 0;
        }

        $afastadoId = (int) $solicitacao->user_id;
        $criadas = 0;

        return DB::transaction(function () use ($solicitacao, $coberturas, $tipoFuncao, $afastadoId, &$criadas): int {
            $escalas = $this->escalasAfetadas($solicitacao, $afastadoId, $tipoFuncao);

            foreach ($escalas as $escala) {
                // Em casos com múltiplas coberturas aprovadas, usamos a mais recente.
                $cobertura = $coberturas->sortByDesc('aprovado_em')->first();
                $substituto = $cobertura?->servidorCobertura;

                if (! $substituto instanceof User) {
                    continue;
                }

                $marcador = $this->marcador($solicitacao);

                $existente = PlantaoPermuta::query()
                    ->where('escala_id', $escala->id)
                    ->where('tipo_funcao', $tipoFuncao)
                    ->where('servidor_original_type', User::class)
                    ->where('servidor_original_id', $afastadoId)
                    ->where('motivo', 'like', $marcador.'%')
                    ->first();

                if ($existente) {
                    // Atualiza substituto/datas caso a cobertura tenha mudado.
                    $existente->forceFill([
                        'servidor_substituto_type' => User::class,
                        'servidor_substituto_id' => $substituto->id,
                        'data_plantao' => $escala->data_plantao,
                        'autorizado_por' => $cobertura?->aprovado_por ?? Auth::id(),
                        'autorizado_em' => $cobertura?->aprovado_em ?? now(),
                        'motivo' => $marcador.' Cobertura aprovada por afastamento.',
                    ])->save();

                    continue;
                }

                PlantaoPermuta::query()->create([
                    'escala_id' => $escala->id,
                    'escala_destino_id' => null,
                    'lado' => null,
                    'grupo_permuta' => null,
                    'servidor_original_type' => User::class,
                    'servidor_original_id' => $afastadoId,
                    'servidor_substituto_type' => User::class,
                    'servidor_substituto_id' => $substituto->id,
                    'tipo_funcao' => $tipoFuncao,
                    'data_plantao' => $escala->data_plantao,
                    'motivo' => $marcador.' Cobertura aprovada por afastamento.',
                    'autorizado_por' => $cobertura?->aprovado_por ?? Auth::id(),
                    'autorizado_em' => $cobertura?->aprovado_em ?? now(),
                ]);

                $escala->forceFill(['status' => 'alterada'])->save();

                app(PlantaoHistoricoService::class)->registrar(
                    $escala,
                    'substituicao_afastamento',
                    'Substituição automática gerada por afastamento aprovado.',
                    [
                        'afastamento_solicitacao_id' => $solicitacao->id,
                        'servidor_original_id' => $afastadoId,
                        'servidor_substituto_id' => $substituto->id,
                        'tipo' => $tipoFuncao,
                    ],
                );

                $criadas++;
            }

            return $criadas;
        });
    }

    /**
     * Remove permutas automáticas geradas para o afastamento.
     *
     * Se $somenteAposData for informado, mantém as permutas de escalas com
     * data_plantao <= $somenteAposData (preservando o histórico real). Útil ao
     * interromper o afastamento: passamos a data da interrupção para preservar
     * os plantões já cobertos antes dela.
     */
    public function reverter(AfastamentoSolicitacao $solicitacao, ?\Carbon\CarbonInterface $somenteAposData = null): int
    {
        $marcador = $this->marcador($solicitacao);

        $permutas = PlantaoPermuta::query()
            ->where('motivo', 'like', $marcador.'%')
            ->where('servidor_original_id', $solicitacao->user_id)
            ->when($somenteAposData, fn ($query) => $query->whereDate('data_plantao', '>', $somenteAposData->toDateString()))
            ->get();

        if ($permutas->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($permutas, $solicitacao): int {
            $removidas = 0;

            foreach ($permutas as $permuta) {
                $escala = $permuta->escala;
                $permuta->delete();
                $removidas++;

                if ($escala instanceof PlantaoEscala) {
                    app(PlantaoHistoricoService::class)->registrar(
                        $escala,
                        'substituicao_afastamento_revertida',
                        'Substituição automática por afastamento removida.',
                        [
                            'afastamento_solicitacao_id' => $solicitacao->id,
                        ],
                    );
                }
            }

            return $removidas;
        });
    }

    /**
     * Reconcilia: remove permutas anteriores e aplica novamente. Útil ao
     * interromper o afastamento (a data_fim mudou) ou quando a cobertura é
     * trocada após a aprovação.
     */
    public function reconciliar(AfastamentoSolicitacao $solicitacao): int
    {
        $this->reverter($solicitacao);

        return $this->aplicar($solicitacao);
    }

    /**
     * Marcador único usado no campo "motivo" da PlantaoPermuta.
     */
    private function marcador(AfastamentoSolicitacao $solicitacao): string
    {
        return '[AFASTAMENTO #'.$solicitacao->id.']';
    }

    /**
     * Devolve o tipo_funcao usado em PlantaoPermuta (ipc_plantao/epc_plantao)
     * com base na função operacional do servidor afastado, ou null se não for
     * de plantão.
     */
    private function tipoFuncaoPlantao(AfastamentoSolicitacao $solicitacao): ?string
    {
        $funcao = $solicitacao->user?->funcao_operacional;

        return match ($funcao?->value) {
            'IPC_PLANTAO' => 'ipc_plantao',
            'EPC_PLANTAO' => 'epc_plantao',
            default => null,
        };
    }

    /**
     * Localiza as escalas em que o servidor afastado consta na equipe na
     * função correspondente, dentro do período do afastamento.
     *
     * @return \Illuminate\Support\Collection<int, PlantaoEscala>
     */
    private function escalasAfetadas(AfastamentoSolicitacao $solicitacao, int $afastadoId, string $tipoFuncao): \Illuminate\Support\Collection
    {
        return PlantaoEscala::query()
            ->whereDate('data_plantao', '>=', $solicitacao->data_inicio)
            ->whereDate('data_plantao', '<=', $solicitacao->data_fim)
            ->whereHas('equipe.servidores', function ($query) use ($afastadoId, $tipoFuncao): void {
                $query->where('user_id', $afastadoId)
                    ->where('funcao_plantao', $tipoFuncao)
                    ->where('ativo', true);
            })
            ->get();
    }
}
