<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantao_cqh_servidores', function (Blueprint $table): void {
            $table->string('nome_calendario', 60)->nullable()->after('unidade_operacional')
                ->comment('Nome social/curto para exibição no calendário. Sobrepõe a abreviação automática.');
        });

        Schema::table('plantao_cqh_externos', function (Blueprint $table): void {
            $table->string('nome_calendario', 60)->nullable()->after('unidade_operacional')
                ->comment('Nome social/curto para exibição no calendário. Sobrepõe a abreviação automática.');
        });
    }

    public function down(): void
    {
        Schema::table('plantao_cqh_servidores', function (Blueprint $table): void {
            $table->dropColumn('nome_calendario');
        });

        Schema::table('plantao_cqh_externos', function (Blueprint $table): void {
            $table->dropColumn('nome_calendario');
        });
    }
};
