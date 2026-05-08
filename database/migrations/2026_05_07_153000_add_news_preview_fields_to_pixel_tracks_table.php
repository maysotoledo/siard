<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->string('preview_tipo', 20)->default('mensagem')->after('label');
            $table->string('noticia_url')->nullable()->after('mensagem');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn(['preview_tipo', 'noticia_url']);
        });
    }
};
