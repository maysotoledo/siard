<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afastamento_regras', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_afastamento', 40)->index();
            $table->string('nome');
            $table->unsignedSmallInteger('dias_por_periodo')->default(30);
            $table->unsignedSmallInteger('meses_para_aquisicao')->default(12);
            $table->boolean('permite_parcelamento')->default(true);
            $table->unsignedSmallInteger('quantidade_maxima_parcelas')->default(3);
            $table->unsignedSmallInteger('dias_minimos_por_parcela')->default(10);
            $table->boolean('exige_aprovacao_chefia')->default(true);
            $table->boolean('afeta_efetivo_minimo')->default(true);
            $table->boolean('permite_interrupcao')->default(true);
            $table->boolean('permite_cancelamento_apos_inicio')->default(false);
            $table->boolean('devolve_saldo_ao_interromper')->default(true);
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('afastamento_periodos_aquisitivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo_afastamento', 40)->index();
            $table->date('data_inicio')->index();
            $table->date('data_fim')->index();
            $table->date('data_aquisicao')->index();
            $table->unsignedSmallInteger('dias_direito')->default(0);
            $table->unsignedSmallInteger('dias_usufruidos')->default(0);
            $table->unsignedSmallInteger('dias_disponiveis')->default(0);
            $table->string('status', 40)->default('rascunho')->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tipo_afastamento', 'status'], 'apa_user_tipo_status_idx');
        });

        Schema::create('afastamento_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('unidade_id')->nullable()->index();
            $table->foreignId('periodo_aquisitivo_id')
                ->nullable()
                ->constrained('afastamento_periodos_aquisitivos')
                ->nullOnDelete();
            $table->string('tipo_afastamento', 40)->index();
            $table->date('data_inicio')->index();
            $table->date('data_fim')->index();
            $table->unsignedSmallInteger('dias_solicitados')->default(0);
            $table->unsignedSmallInteger('dias_aprovados')->nullable();
            $table->string('status', 40)->default('rascunho')->index();
            $table->unsignedTinyInteger('impacto_score')->nullable();
            $table->string('nivel_impacto', 40)->nullable()->index();
            $table->text('justificativa_servidor')->nullable();
            $table->text('justificativa_chefia')->nullable();
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('indeferido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indeferido_em')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tipo_afastamento', 'status'], 'as_user_tipo_status_idx');
            $table->index(['data_inicio', 'data_fim', 'status'], 'as_periodo_status_idx');
        });

        Schema::create('afastamento_regras_operacionais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id')->nullable()->index();
            $table->string('cargo')->nullable()->index();
            $table->string('funcao')->nullable()->index();
            $table->string('setor')->nullable()->index();
            $table->unsignedSmallInteger('minimo_por_dia')->default(1);
            $table->unsignedSmallInteger('maximo_afastados_simultaneos')->default(1);
            $table->json('dias_criticos')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('afastamento_periodos_bloqueados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unidade_id')->nullable()->index();
            $table->string('tipo_afastamento', 40)->nullable()->index();
            $table->date('data_inicio')->index();
            $table->date('data_fim')->index();
            $table->string('motivo');
            $table->boolean('bloqueio_total')->default(true);
            $table->json('funcoes_afetadas')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();

            $table->index(['data_inicio', 'data_fim', 'ativo'], 'apb_periodo_ativo_idx');
        });

        Schema::create('afastamento_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afastamento_solicitacao_id')
                ->constrained('afastamento_solicitacoes')
                ->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 80);
            $table->string('status_anterior', 40)->nullable();
            $table->string('status_novo', 40)->nullable();
            $table->text('descricao');
            $table->json('dados')->nullable();
            $table->timestamps();
        });

        Schema::create('afastamento_interrupcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afastamento_solicitacao_id')
                ->constrained('afastamento_solicitacoes')
                ->cascadeOnDelete();
            $table->foreignId('interrompido_por')->constrained('users')->cascadeOnDelete();
            $table->date('data_interrupcao')->index();
            $table->text('motivo');
            $table->unsignedSmallInteger('dias_restantes')->default(0);
            $table->boolean('saldo_devolvido')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afastamento_interrupcoes');
        Schema::dropIfExists('afastamento_historicos');
        Schema::dropIfExists('afastamento_periodos_bloqueados');
        Schema::dropIfExists('afastamento_regras_operacionais');
        Schema::dropIfExists('afastamento_solicitacoes');
        Schema::dropIfExists('afastamento_periodos_aquisitivos');
        Schema::dropIfExists('afastamento_regras');
    }
};
