<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investigation_context_id')
                ->nullable()
                ->index()
                ->constrained('investigation_contexts')
                ->nullOnDelete();

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

            $table->string('tipo')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->longText('prompt');
            $table->longText('resposta')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('erro')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
    }
};
