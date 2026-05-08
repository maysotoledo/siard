<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->string('target_email')->nullable()->after('label');
            $table->timestamp('sent_at')->nullable()->after('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->dropColumn(['target_email', 'sent_at']);
        });
    }
};
