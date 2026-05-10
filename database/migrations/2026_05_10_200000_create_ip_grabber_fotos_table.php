<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_grabber_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pixel_track_id')->constrained('pixel_tracks')->cascadeOnDelete();
            $table->string('access_uuid', 36)->nullable()->index();
            $table->string('path', 255);
            $table->timestamps();

            $table->index('pixel_track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_grabber_fotos');
    }
};
