<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'data_ingresso_servico_publico')) {
                $table->date('data_ingresso_servico_publico')->nullable()->after('data_ingresso')->index();
            }
            if (! Schema::hasColumn('users', 'data_ingresso_unidade')) {
                $table->date('data_ingresso_unidade')->nullable()->after('data_ingresso_servico_publico')->index();
            }
            if (! Schema::hasColumn('users', 'data_ingresso_carreira')) {
                $table->date('data_ingresso_carreira')->nullable()->after('data_ingresso_unidade')->index();
            }
        });

        Schema::create('afastamento_prioridade_regras', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('tipo_afastamento', 40)->nullable()->index();
            $table->string('funcao_operacional', 60)->nullable()->index();
            $table->unsignedBigInteger('unidade_id')->nullable()->index();
            $table->boolean('usar_antiguidade_servico_publico')->default(true);
            $table->boolean('usar_antiguidade_carreira')->default(true);
            $table->boolean('usar_antiguidade_unidade')->default(true);
            $table->integer('peso_antiguidade_servico_publico')->default(2);
            $table->integer('peso_antiguidade_carreira')->default(3);
            $table->integer('peso_antiguidade_unidade')->default(1);
            $table->integer('peso_periodo_aquisitivo_mais_antigo')->default(5);
            $table->integer('peso_tempo_sem_gozo')->default(5);
            $table->integer('peso_saldo_vencido_ou_antigo')->default(8);
            $table->integer('peso_impacto_operacional')->default(-10);
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('afastamento_solicitacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('afastamento_solicitacoes', 'prioridade_score')) {
                $table->integer('prioridade_score')->nullable()->after('nivel_impacto');
            }
            if (! Schema::hasColumn('afastamento_solicitacoes', 'prioridade_nivel')) {
                $table->string('prioridade_nivel', 40)->nullable()->after('prioridade_score')->index();
            }
            if (! Schema::hasColumn('afastamento_solicitacoes', 'prioridade_posicao')) {
                $table->integer('prioridade_posicao')->nullable()->after('prioridade_nivel');
            }
            if (! Schema::hasColumn('afastamento_solicitacoes', 'prioridade_motivo')) {
                $table->text('prioridade_motivo')->nullable()->after('prioridade_posicao');
            }
            if (! Schema::hasColumn('afastamento_solicitacoes', 'prioridade_calculada_em')) {
                $table->timestamp('prioridade_calculada_em')->nullable()->after('prioridade_motivo');
            }
        });

        DB::table('afastamento_prioridade_regras')->updateOrInsert(
            ['nome' => 'Regra Geral de Prioridade'],
            [
                'usar_antiguidade_servico_publico' => true,
                'usar_antiguidade_carreira' => true,
                'usar_antiguidade_unidade' => true,
                'peso_antiguidade_servico_publico' => 2,
                'peso_antiguidade_carreira' => 3,
                'peso_antiguidade_unidade' => 1,
                'peso_periodo_aquisitivo_mais_antigo' => 5,
                'peso_tempo_sem_gozo' => 5,
                'peso_saldo_vencido_ou_antigo' => 8,
                'peso_impacto_operacional' => -10,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('afastamento_prioridade_regras');
    }
};
