<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('afastamento_regras')
            ->whereIn('tipo_afastamento', ['ferias', 'licenca_premio'])
            ->delete();
    }
};
