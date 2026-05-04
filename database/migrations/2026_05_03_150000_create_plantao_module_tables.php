<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantao_equipes', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('plantao_equipe_servidores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipe_id')->constrained('plantao_equipes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('funcao_plantao', 30)->index();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();

            $table->unique(['equipe_id', 'user_id'], 'pes_equipe_user_unique');
        });

        Schema::create('plantao_escalas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipe_id')->nullable()->constrained('plantao_equipes')->nullOnDelete();
            $table->date('data_plantao')->unique();
            $table->time('horario_inicio')->default('07:00:00');
            $table->time('horario_fim')->default('07:00:00');
            $table->string('cqh_geral_type')->nullable()->index();
            $table->unsignedBigInteger('cqh_geral_id')->nullable()->index();
            $table->string('status', 30)->default('prevista')->index();
            $table->text('observacao')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plantao_cqh_servidores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('unidade_operacional')->nullable()->index();
            $table->boolean('apto_cqh')->default(true)->index();
            $table->integer('ordem')->nullable()->index();
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'pcs_user_unique');
        });

        Schema::create('plantao_cqh_externos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('unidade_operacional')->default('DERF_CONFRESA')->index();
            $table->string('telefone')->nullable();
            $table->integer('ordem')->nullable()->index();
            $table->boolean('apto_cqh')->default(true)->index();
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('plantao_permutas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('escala_id')->constrained('plantao_escalas')->cascadeOnDelete();
            $table->string('servidor_original_type')->nullable()->index();
            $table->unsignedBigInteger('servidor_original_id')->index();
            $table->string('servidor_substituto_type')->nullable()->index();
            $table->unsignedBigInteger('servidor_substituto_id')->index();
            $table->string('tipo_funcao', 30)->index();
            $table->date('data_plantao')->index();
            $table->text('motivo')->nullable();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('autorizado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('plantao_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('escala_id')->nullable()->constrained('plantao_escalas')->cascadeOnDelete();
            $table->foreignId('permuta_id')->nullable()->constrained('plantao_permutas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 80);
            $table->text('descricao');
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantao_historicos');
        Schema::dropIfExists('plantao_permutas');
        Schema::dropIfExists('plantao_cqh_externos');
        Schema::dropIfExists('plantao_cqh_servidores');
        Schema::dropIfExists('plantao_escalas');
        Schema::dropIfExists('plantao_equipe_servidores');
        Schema::dropIfExists('plantao_equipes');
    }
};
