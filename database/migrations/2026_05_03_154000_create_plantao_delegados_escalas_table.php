<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantao_delegados_escalas', function (Blueprint $table): void {
            $table->id();
            $table->date('data_plantao')->unique();
            $table->string('nome_delegado');
            $table->string('unidade_delegado')->nullable();
            $table->string('contato')->nullable();
            $table->text('horario')->nullable();
            $table->boolean('regionalizado')->default(false);
            $table->string('origem_pdf')->nullable();
            $table->json('dados_extraidos')->nullable();
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasColumn('plantao_escalas', 'dpc_nome')) {
            DB::table('plantao_escalas')
                ->whereNotNull('dpc_nome')
                ->where('dpc_nome', '<>', '')
                ->orderBy('id')
                ->chunkById(100, function ($escalas): void {
                    foreach ($escalas as $escala) {
                        DB::table('plantao_delegados_escalas')->updateOrInsert(
                            ['data_plantao' => $escala->data_plantao],
                            [
                                'nome_delegado' => $escala->dpc_nome,
                                'contato' => $escala->dpc_contato ?? null,
                                'origem_pdf' => 'legacy:plantao_escalas',
                                'dados_extraidos' => json_encode(['origem' => 'plantao_escalas.dpc_nome']),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plantao_delegados_escalas');
    }
};
