<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantao_escalas', function (Blueprint $table): void {
            $table->string('dpc_nome')->nullable()->after('cqh_geral_id');
            $table->string('dpc_contato')->nullable()->after('dpc_nome');
        });
    }

    public function down(): void
    {
        Schema::table('plantao_escalas', function (Blueprint $table): void {
            $table->dropColumn(['dpc_nome', 'dpc_contato']);
        });
    }
};
