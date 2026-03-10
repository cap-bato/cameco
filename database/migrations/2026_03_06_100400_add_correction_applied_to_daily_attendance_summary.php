<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            $table->boolean('correction_applied')
                  ->default(false)
                  ->after('is_finalized')
                  ->comment('True when an approved AttendanceCorrection has been applied to this row');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            $table->dropColumn('correction_applied');
        });
    }
};
