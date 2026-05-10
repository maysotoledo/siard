<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            if (! Schema::hasColumn('pixel_tracks', 'gps_status')) {
                $table->string('gps_status', 30)->nullable()->after('gps_accuracy');
            }
            if (! Schema::hasColumn('pixel_tracks', 'gps_error')) {
                $table->string('gps_error', 120)->nullable()->after('gps_status');
            }
        });

        Schema::table('pixel_track_accesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('pixel_track_accesses', 'gps_status')) {
                $table->string('gps_status', 30)->nullable()->after('gps_accuracy');
            }
            if (! Schema::hasColumn('pixel_track_accesses', 'gps_error')) {
                $table->string('gps_error', 120)->nullable()->after('gps_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pixel_track_accesses', function (Blueprint $table): void {
            $table->dropColumn(['gps_status', 'gps_error']);
        });

        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->dropColumn(['gps_status', 'gps_error']);
        });
    }
};
