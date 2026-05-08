<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->decimal('gps_latitude', 10, 7)->nullable()->after('longitude');
            $table->decimal('gps_longitude', 10, 7)->nullable()->after('gps_latitude');
            $table->decimal('gps_accuracy', 10, 2)->nullable()->after('gps_longitude');
        });

        Schema::table('pixel_track_accesses', function (Blueprint $table) {
            $table->decimal('gps_latitude', 10, 7)->nullable()->after('longitude');
            $table->decimal('gps_longitude', 10, 7)->nullable()->after('gps_latitude');
            $table->decimal('gps_accuracy', 10, 2)->nullable()->after('gps_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_track_accesses', function (Blueprint $table) {
            $table->dropColumn(['gps_latitude', 'gps_longitude', 'gps_accuracy']);
        });

        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn(['gps_latitude', 'gps_longitude', 'gps_accuracy']);
        });
    }
};
