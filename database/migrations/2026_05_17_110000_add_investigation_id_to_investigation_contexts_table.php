<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigation_contexts', function (Blueprint $table) {
            $table->foreignId('analise_investigation_id')
                ->nullable()
                ->index()
                ->after('analise_run_id')
                ->constrained('analise_investigations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investigation_contexts', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\AnaliseInvestigation::class);
            $table->dropColumn('analise_investigation_id');
        });
    }
};
