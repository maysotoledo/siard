<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->string('og_titulo')->nullable()->after('mensagem');
            $table->string('og_descricao')->nullable()->after('og_titulo');
            $table->string('og_imagem')->nullable()->after('og_descricao'); // URL pública da imagem
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn(['og_titulo', 'og_descricao', 'og_imagem']);
        });
    }
};
