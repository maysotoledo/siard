<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AfastamentoRegraSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('afastamento_regras')->updateOrInsert(
            ['tipo_afastamento' => 'ferias', 'nome' => 'Férias estatutárias'],
            [
                'dias_por_periodo' => 30,
                'meses_para_aquisicao' => 12,
                'permite_parcelamento' => true,
                'quantidade_maxima_parcelas' => 3,
                'dias_minimos_por_parcela' => 10,
                'exige_aprovacao_chefia' => true,
                'afeta_efetivo_minimo' => true,
                'permite_interrupcao' => true,
                'permite_cancelamento_apos_inicio' => false,
                'devolve_saldo_ao_interromper' => true,
                'ativo' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('afastamento_regras')->updateOrInsert(
            ['tipo_afastamento' => 'licenca_premio', 'nome' => 'Licença-prêmio parametrizável'],
            [
                'dias_por_periodo' => 90,
                'meses_para_aquisicao' => 60,
                'permite_parcelamento' => true,
                'quantidade_maxima_parcelas' => 3,
                'dias_minimos_por_parcela' => 30,
                'exige_aprovacao_chefia' => true,
                'afeta_efetivo_minimo' => true,
                'permite_interrupcao' => false,
                'permite_cancelamento_apos_inicio' => false,
                'devolve_saldo_ao_interromper' => false,
                'ativo' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        foreach ($this->regrasOperacionaisPadrao() as $regra) {
            DB::table('afastamento_regras_operacionais')->updateOrInsert(
                ['funcao_operacional' => $regra['funcao_operacional']],
                [
                    ...$regra,
                    'minimo_por_dia' => $regra['minimo_disponivel'] ?? 1,
                    'maximo_afastados_simultaneos' => 1,
                    'ativo' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function regrasOperacionaisPadrao(): array
    {
        return [
            ['funcao_operacional' => 'IPC_EXPEDIENTE', 'grupo_operacional' => 'expediente', 'minimo_disponivel' => 2, 'prioridade_operacional' => false, 'permite_cobertura_por_funcao' => null],
            ['funcao_operacional' => 'IPC_PLANTAO', 'grupo_operacional' => 'plantao', 'minimo_disponivel' => 1, 'prioridade_operacional' => true, 'permite_cobertura_por_funcao' => json_encode(['IPC_EXPEDIENTE'])],
            ['funcao_operacional' => 'EPC_EXPEDIENTE', 'grupo_operacional' => 'expediente', 'minimo_disponivel' => 1, 'prioridade_operacional' => false, 'permite_cobertura_por_funcao' => null],
            ['funcao_operacional' => 'EPC_PLANTAO', 'grupo_operacional' => 'plantao', 'minimo_disponivel' => 1, 'prioridade_operacional' => true, 'permite_cobertura_por_funcao' => null],
        ];
    }
}
