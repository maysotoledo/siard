<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->string('mensagem')->default('Este documento não está mais disponível.')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn('mensagem');
        });
    }
};
