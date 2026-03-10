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
            // Allow summaries to be stored even when no work schedule is found.
            // Previously NOT NULL — employee with events but missing dept schedule
            // would be silently treated as absent because the service bailed early.
            $table->foreignId('work_schedule_id')
                ->nullable()
                ->change();

            // Flag so HR can review records that had no schedule at time of computation.
            // is_present may still be true if the employee actually tapped in.
            $table->boolean('needs_schedule_review')
                ->default(false)
                ->after('is_overtime')
                ->comment('True when no work schedule was found for the employee\'s department; hours/late status may be incomplete.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            $table->dropColumn('needs_schedule_review');
            $table->foreignId('work_schedule_id')->nullable(false)->change();
        });
    }
};
