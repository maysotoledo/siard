<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'data_ingresso',
                'data_ingresso_servico_publico',
                'data_ingresso_unidade',
                'data_ingresso_carreira',
            ];

            $toDrop = array_filter(
                $columns,
                fn (string $col) => Schema::hasColumn('users', $col),
            );

            if ($toDrop) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('data_ingresso')->nullable();
            $table->date('data_ingresso_servico_publico')->nullable();
            $table->date('data_ingresso_unidade')->nullable();
            $table->date('data_ingresso_carreira')->nullable();
        });
    }
};
