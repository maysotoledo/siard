<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analise_run_ips', function (Blueprint $table): void {
            $table->index(['analise_run_id', 'enriched'], 'run_ips_run_enriched_idx');
        });

        Schema::table('bilhetagens', function (Blueprint $table): void {
            $table->index(['analise_run_id', 'recipient', 'timestamp_utc', 'id'], 'bilhetagens_run_recipient_latest_idx');
            $table->index(['analise_run_id', 'recipient', 'message_id', 'timestamp_utc'], 'bilhetagens_run_recipient_message_ts_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bilhetagens', function (Blueprint $table): void {
            $table->dropIndex('bilhetagens_run_recipient_latest_idx');
            $table->dropIndex('bilhetagens_run_recipient_message_ts_idx');
        });

        Schema::table('analise_run_ips', function (Blueprint $table): void {
            $table->dropIndex('run_ips_run_enriched_idx');
        });
    }
};
