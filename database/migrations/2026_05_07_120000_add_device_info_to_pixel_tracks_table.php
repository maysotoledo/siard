<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->string('ip_local', 45)->nullable()->after('ip');     // IP privado via WebRTC
            $table->string('idioma', 20)->nullable()->after('gmt');      // navigator.language
            $table->string('plataforma')->nullable()->after('idioma');   // Windows, Android, iPhone...
            $table->string('resolucao', 20)->nullable()->after('plataforma'); // ex: 1920x1080
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn(['ip_local', 'idioma', 'plataforma', 'resolucao']);
        });
    }
};
