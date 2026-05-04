<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plantao_cqh_externos')) {
            Schema::create('plantao_cqh_externos', function (Blueprint $table): void {
                $table->id();
                $table->string('nome');
                $table->string('unidade_operacional')->default('DERF_CONFRESA')->index();
                $table->string('telefone')->nullable();
                $table->integer('ordem')->nullable()->index();
                $table->boolean('apto_cqh')->default(true)->index();
                $table->boolean('ativo')->default(true)->index();
                $table->text('observacao')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('plantao_escalas') && ! Schema::hasColumn('plantao_escalas', 'cqh_geral_type')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('plantao_escalas', function (Blueprint $table): void {
                    $table->dropForeign(['cqh_geral_id']);
                });
            }

            Schema::table('plantao_escalas', function (Blueprint $table): void {
                $table->string('cqh_geral_type')->nullable()->after('horario_fim')->index();
            });

            DB::table('plantao_escalas')
                ->whereNotNull('cqh_geral_id')
                ->update(['cqh_geral_type' => User::class]);
        }

        if (Schema::hasTable('plantao_permutas') && ! Schema::hasColumn('plantao_permutas', 'servidor_original_type')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('plantao_permutas', function (Blueprint $table): void {
                    $table->dropForeign(['servidor_original_id']);
                    $table->dropForeign(['servidor_substituto_id']);
                });
            }

            Schema::table('plantao_permutas', function (Blueprint $table): void {
                $table->string('servidor_original_type')->nullable()->after('escala_id')->index();
                $table->string('servidor_substituto_type')->nullable()->after('servidor_original_id')->index();
            });

            DB::table('plantao_permutas')->update([
                'servidor_original_type' => User::class,
                'servidor_substituto_type' => User::class,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plantao_permutas') && Schema::hasColumn('plantao_permutas', 'servidor_original_type')) {
            Schema::table('plantao_permutas', function (Blueprint $table): void {
                $table->dropColumn(['servidor_original_type', 'servidor_substituto_type']);
            });
        }

        if (Schema::hasTable('plantao_escalas') && Schema::hasColumn('plantao_escalas', 'cqh_geral_type')) {
            Schema::table('plantao_escalas', function (Blueprint $table): void {
                $table->dropColumn('cqh_geral_type');
            });
        }

        Schema::dropIfExists('plantao_cqh_externos');
    }
};
