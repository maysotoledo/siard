<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->string('tracking_domain', 120)->nullable()->after('preview_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->dropColumn('tracking_domain');
        });
    }
};
