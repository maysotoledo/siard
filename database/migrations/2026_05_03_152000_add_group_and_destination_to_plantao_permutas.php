<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantao_permutas', function (Blueprint $table): void {
            $table->uuid('grupo_permuta')->nullable()->after('id')->index();
            $table->foreignId('escala_destino_id')->nullable()->after('escala_id')->constrained('plantao_escalas')->nullOnDelete();
            $table->string('lado', 20)->nullable()->after('escala_destino_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('plantao_permutas', function (Blueprint $table): void {
            $table->dropForeign(['escala_destino_id']);
            $table->dropColumn(['grupo_permuta', 'escala_destino_id', 'lado']);
        });
    }
};
