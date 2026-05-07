<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('label');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Dados capturados no momento do acesso
            $table->string('ip', 45)->nullable();
            $table->string('porta', 10)->nullable();
            $table->string('gmt', 40)->nullable();
            $table->string('cidade')->nullable();
            $table->string('regiao')->nullable();
            $table->string('pais')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('isp')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('total_acessos')->default(0);
            $table->timestamp('clicked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_tracks');
    }
};
