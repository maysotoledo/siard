<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('attendance_lunch_start', 5)
                ->nullable()
                ->after('attendance_slot_duration_minutes');
            $table->string('attendance_lunch_end', 5)
                ->nullable()
                ->after('attendance_lunch_start');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_lunch_start',
                'attendance_lunch_end',
            ]);
        });
    }
};
