<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ferias')) {
            return;
        }

        DB::table('ferias')
            ->orderBy('id')
            ->get()
            ->each(function (object $ferias): void {
                $inicio = \Carbon\Carbon::parse($ferias->inicio)->startOfDay();
                $fim = \Carbon\Carbon::parse($ferias->fim)->startOfDay();
                $dias = $inicio->diffInDays($fim) + 1;
                $now = now();

                DB::table('afastamento_solicitacoes')->updateOrInsert(
                    [
                        'user_id' => $ferias->user_id,
                        'tipo_afastamento' => 'ferias',
                        'data_inicio' => $inicio->toDateString(),
                        'data_fim' => $fim->toDateString(),
                    ],
                    [
                        'dias_solicitados' => $dias,
                        'dias_aprovados' => $dias,
                        'status' => 'aprovado',
                        'impacto_score' => null,
                        'nivel_impacto' => null,
                        'observacao' => 'Importado automaticamente do módulo legado de férias.',
                        'aprovado_em' => $ferias->created_at ?? $now,
                        'created_at' => $ferias->created_at ?? $now,
                        'updated_at' => $ferias->updated_at ?? $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        DB::table('afastamento_solicitacoes')
            ->where('tipo_afastamento', 'ferias')
            ->where('observacao', 'Importado automaticamente do módulo legado de férias.')
            ->delete();
    }
};
