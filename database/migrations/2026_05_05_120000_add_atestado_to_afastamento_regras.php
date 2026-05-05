<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('afastamento_regras')->updateOrInsert(
            ['tipo_afastamento' => 'atestado'],
            [
                'nome' => 'Atestado médico',
                // Atestado não tem período aquisitivo: campos de acúmulo ficam zerados.
                'dias_por_periodo' => 0,
                'meses_para_aquisicao' => 0,
                // Não há parcelas — cada atestado é uma entrada independente.
                'permite_parcelamento' => false,
                'quantidade_maxima_parcelas' => 1,
                'dias_minimos_por_parcela' => 1,
                // Atestado pode ser registrado retroativamente e não exige aprovação prévia.
                'exige_aprovacao_chefia' => false,
                'afeta_efetivo_minimo' => true,
                'permite_interrupcao' => false,
                'permite_cancelamento_apos_inicio' => true,
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
            ->where('tipo_afastamento', 'atestado')
            ->delete();
    }
};
