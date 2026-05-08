<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_track_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pixel_track_id')->constrained('pixel_tracks')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('endpoint', 20)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('porta', 10)->nullable();
            $table->string('gmt', 60)->nullable();
            $table->string('cidade')->nullable();
            $table->string('regiao')->nullable();
            $table->string('pais')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('isp')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_local', 45)->nullable();
            $table->string('idioma', 20)->nullable();
            $table->string('plataforma', 100)->nullable();
            $table->string('resolucao', 20)->nullable();
            $table->string('referer')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();

            $table->index(['pixel_track_id', 'accessed_at']);
            $table->index('ip');
        });

        DB::table('pixel_tracks')
            ->whereNotNull('clicked_at')
            ->orderBy('id')
            ->chunkById(100, function ($pixels): void {
                foreach ($pixels as $pixel) {
                    DB::table('pixel_track_accesses')->insert([
                        'pixel_track_id' => $pixel->id,
                        'uuid' => (string) Str::uuid(),
                        'endpoint' => 'historico',
                        'ip' => $pixel->ip,
                        'porta' => $pixel->porta,
                        'gmt' => $pixel->gmt,
                        'cidade' => $pixel->cidade,
                        'regiao' => $pixel->regiao,
                        'pais' => $pixel->pais,
                        'latitude' => $pixel->latitude,
                        'longitude' => $pixel->longitude,
                        'isp' => $pixel->isp,
                        'user_agent' => $pixel->user_agent,
                        'ip_local' => $pixel->ip_local,
                        'idioma' => $pixel->idioma,
                        'plataforma' => $pixel->plataforma,
                        'resolucao' => $pixel->resolucao,
                        'accessed_at' => $pixel->clicked_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_track_accesses');
    }
};
