<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_accesses', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 255)->default('/');
            $table->string('ip', 45)->nullable();
            $table->string('referer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();

            $table->index(['path', 'accessed_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_accesses');
    }
};
