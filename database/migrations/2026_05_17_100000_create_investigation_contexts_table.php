<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_contexts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('analise_run_id')
                ->nullable()
                ->index()
                ->constrained('analise_runs')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('titulo')->nullable();
            $table->string('numero_bo')->nullable();
            $table->string('numero_procedimento')->nullable();
            $table->string('natureza')->nullable();
            $table->json('vitimas')->nullable();
            $table->json('suspeitos')->nullable();
            $table->string('unidade_policial')->nullable();

            $table->string('arquivo_original')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_mime')->nullable();

            $table->longText('texto_extraido')->nullable();
            $table->longText('resumo_contexto')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_contexts');
    }
};
