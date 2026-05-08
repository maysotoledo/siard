<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->string('tracking_channel', 20)->default('link')->after('tracking_domain');
        });

        DB::table('pixel_tracks')->update([
            'tracking_channel' => 'link',
        ]);
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->dropColumn('tracking_channel');
        });
    }
};
