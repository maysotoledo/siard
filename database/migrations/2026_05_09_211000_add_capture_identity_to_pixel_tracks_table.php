<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            if (! Schema::hasColumn('pixel_tracks', 'capture_identity')) {
                $table->boolean('capture_identity')->default(false)->after('capture_gps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            if (Schema::hasColumn('pixel_tracks', 'capture_identity')) {
                $table->dropColumn('capture_identity');
            }
        });
    }
};
