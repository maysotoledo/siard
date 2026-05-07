<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            // Caminho do arquivo salvo no storage (alternativa ao og_imagem URL)
            $table->string('og_imagem_upload')->nullable()->after('og_imagem');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn('og_imagem_upload');
        });
    }
};
