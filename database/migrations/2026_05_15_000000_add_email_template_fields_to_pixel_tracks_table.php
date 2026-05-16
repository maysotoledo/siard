<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->string('email_tipo', 30)->default('marketing')->after('target_email');
            $table->string('recovery_email')->nullable()->after('email_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table): void {
            $table->dropColumn(['email_tipo', 'recovery_email']);
        });
    }
};
