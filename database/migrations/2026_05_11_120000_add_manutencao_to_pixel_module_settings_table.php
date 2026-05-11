<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pixel_module_settings', function (Blueprint $table): void {
            $table->boolean('manutencao_ativa')->default(false)->after('payment_enabled');
            $table->dateTime('manutencao_prevista')->nullable()->after('manutencao_ativa');
        });
    }

    public function down(): void
    {
        Schema::table('pixel_module_settings', function (Blueprint $table): void {
            $table->dropColumn(['manutencao_ativa', 'manutencao_prevista']);
        });
    }
};
