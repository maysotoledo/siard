<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afastamento_regras_operacionais', function (Blueprint $table): void {
            if (! Schema::hasColumn('afastamento_regras_operacionais', 'funcao_operacional')) {
                $table->string('funcao_operacional', 60)->nullable()->after('unidade_id')->index();
            }

            if (! Schema::hasColumn('afastamento_regras_operacionais', 'grupo_operacional')) {
                $table->string('grupo_operacional', 30)->nullable()->after('funcao_operacional')->index();
            }

            if (! Schema::hasColumn('afastamento_regras_operacionais', 'minimo_disponivel')) {
                $table->unsignedSmallInteger('minimo_disponivel')->nullable()->after('grupo_operacional');
            }

            if (! Schema::hasColumn('afastamento_regras_operacionais', 'prioridade_operacional')) {
                $table->boolean('prioridade_operacional')->default(false)->after('minimo_disponivel')->index();
            }

            if (! Schema::hasColumn('afastamento_regras_operacionais', 'permite_cobertura_por_funcao')) {
                $table->json('permite_cobertura_por_funcao')->nullable()->after('prioridade_operacional');
            }
        });

        $now = now();
        foreach ($this->regrasPadrao() as $regra) {
            DB::table('afastamento_regras_operacionais')->updateOrInsert(
                ['funcao_operacional' => $regra['funcao_operacional']],
                [
                    ...$regra,
                    'minimo_por_dia' => $regra['minimo_disponivel'] ?? 1,
                    'maximo_afastados_simultaneos' => 1,
                    'ativo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('afastamento_regras_operacionais', function (Blueprint $table): void {
            foreach (['permite_cobertura_por_funcao', 'prioridade_operacional', 'minimo_disponivel', 'grupo_operacional', 'funcao_operacional'] as $column) {
                if (Schema::hasColumn('afastamento_regras_operacionais', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function regrasPadrao(): array
    {
        return [
            ['funcao_operacional' => 'IPC_EXPEDIENTE', 'grupo_operacional' => 'expediente', 'minimo_disponivel' => 2, 'prioridade_operacional' => false, 'permite_cobertura_por_funcao' => null],
            ['funcao_operacional' => 'IPC_PLANTAO', 'grupo_operacional' => 'plantao', 'minimo_disponivel' => 1, 'prioridade_operacional' => true, 'permite_cobertura_por_funcao' => json_encode(['IPC_EXPEDIENTE'])],
            ['funcao_operacional' => 'EPC_EXPEDIENTE', 'grupo_operacional' => 'expediente', 'minimo_disponivel' => 1, 'prioridade_operacional' => false, 'permite_cobertura_por_funcao' => null],
            ['funcao_operacional' => 'EPC_PLANTAO', 'grupo_operacional' => 'plantao', 'minimo_disponivel' => 1, 'prioridade_operacional' => true, 'permite_cobertura_por_funcao' => null],
        ];
    }
};
