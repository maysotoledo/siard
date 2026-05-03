<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'data_ingresso')
            && ! Schema::hasColumn('users', 'data_posse')
            && ! Schema::hasColumn('users', 'data_exercicio')
            && ! Schema::hasColumn('users', 'admitted_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->date('data_ingresso')->nullable()->after('email')->index();
            });
        }

        if (Schema::hasTable('afastamento_periodos_aquisitivos')
            && ! Schema::hasColumn('afastamento_periodos_aquisitivos', 'gerado_automaticamente')) {
            Schema::table('afastamento_periodos_aquisitivos', function (Blueprint $table): void {
                $table->boolean('gerado_automaticamente')->default(true)->after('status')->index();
            });
        }

        if (Schema::hasTable('afastamento_periodos_aquisitivos')) {
            DB::table('afastamento_periodos_aquisitivos')
                ->whereIn('status', ['rascunho', 'aprovado'])
                ->update(['status' => 'adquirido']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('afastamento_periodos_aquisitivos')
            && Schema::hasColumn('afastamento_periodos_aquisitivos', 'gerado_automaticamente')) {
            Schema::table('afastamento_periodos_aquisitivos', function (Blueprint $table): void {
                $table->dropColumn('gerado_automaticamente');
            });
        }
    }
};
