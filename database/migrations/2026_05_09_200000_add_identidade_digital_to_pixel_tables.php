<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pixel_tracks (IpGrabber)
        Schema::table('pixel_tracks', function (Blueprint $table) {
            if (! Schema::hasColumn('pixel_tracks', 'identidade_nome')) {
                $table->string('identidade_nome', 120)->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('pixel_tracks', 'identidade_email')) {
                $table->string('identidade_email', 180)->nullable()->after('identidade_nome');
            }
            if (! Schema::hasColumn('pixel_tracks', 'identidade_telefone')) {
                $table->string('identidade_telefone', 40)->nullable()->after('identidade_email');
            }
            if (! Schema::hasColumn('pixel_tracks', 'identidade_redes')) {
                $table->json('identidade_redes')->nullable()->after('identidade_telefone');
            }
        });

        // pixel_track_accesses (IpGrabberAccess)
        Schema::table('pixel_track_accesses', function (Blueprint $table) {
            if (! Schema::hasColumn('pixel_track_accesses', 'identidade_nome')) {
                $table->string('identidade_nome', 120)->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('pixel_track_accesses', 'identidade_email')) {
                $table->string('identidade_email', 180)->nullable()->after('identidade_nome');
            }
            if (! Schema::hasColumn('pixel_track_accesses', 'identidade_telefone')) {
                $table->string('identidade_telefone', 40)->nullable()->after('identidade_email');
            }
            if (! Schema::hasColumn('pixel_track_accesses', 'identidade_redes')) {
                $table->json('identidade_redes')->nullable()->after('identidade_telefone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pixel_tracks', function (Blueprint $table) {
            $table->dropColumn(['identidade_nome', 'identidade_email', 'identidade_telefone', 'identidade_redes']);
        });

        Schema::table('pixel_track_accesses', function (Blueprint $table) {
            $table->dropColumn(['identidade_nome', 'identidade_email', 'identidade_telefone', 'identidade_redes']);
        });
    }
};
