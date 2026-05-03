<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('afastamento_coberturas_plantao')) {
            return;
        }

        Schema::create('afastamento_coberturas_plantao', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('afastamento_solicitacao_id');
            $table->unsignedBigInteger('servidor_plantao_afastado_id');
            $table->unsignedBigInteger('servidor_cobertura_id');
            $table->string('funcao_origem', 60)->index();
            $table->string('funcao_destino', 60)->index();
            $table->date('data_inicio')->index();
            $table->date('data_fim')->index();
            $table->string('status', 30)->default('sugerida')->index();
            $table->unsignedBigInteger('aprovado_por')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['servidor_cobertura_id', 'data_inicio', 'data_fim', 'status'], 'acp_cobertura_periodo_status_idx');
            $table->foreign('afastamento_solicitacao_id', 'acp_solicitacao_fk')->references('id')->on('afastamento_solicitacoes')->cascadeOnDelete();
            $table->foreign('servidor_plantao_afastado_id', 'acp_plantao_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('servidor_cobertura_id', 'acp_cobertura_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('aprovado_por', 'acp_aprovado_por_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afastamento_coberturas_plantao');
    }
};
