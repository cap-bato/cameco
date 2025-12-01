<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Phase 1: Add rotation tracking to shift_assignments table
     * This allows us to track which shifts were generated from rotation assignments
     * and store the source information for auditing and debugging.
     */
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            // Add rotation assignment reference (nullable - for backward compatibility with schedule-only shifts)
            $table->foreignId('rotation_assignment_id')
                ->nullable()
                ->after('schedule_id')
                ->constrained('rotation_assignments')
                ->onDelete('cascade');

            // Add assignment source tracking
            // Values: 'schedule' (manual), 'rotation' (from rotation pattern), 'manual' (manually created)
            $table->enum('assignment_source', ['schedule', 'rotation', 'manual'])
                ->default('schedule')
                ->after('rotation_assignment_id');

            // Add JSON details about the source (rotation name, schedule name, etc.)
            $table->json('source_details')
                ->nullable()
                ->after('assignment_source');

            // Add indexes for performance when querying by rotation or date/employee
            $table->index('rotation_assignment_id');
            $table->index(['employee_id', 'rotation_assignment_id']);
            $table->index(['date', 'employee_id']);
            $table->index(['assignment_source', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('shift_assignments_rotation_assignment_id_index');
            $table->dropIndex('shift_assignments_employee_id_rotation_assignment_id_index');
            $table->dropIndex('shift_assignments_date_employee_id_index');
            $table->dropIndex('shift_assignments_assignment_source_employee_id_index');

            // Drop columns
            $table->dropForeign(['rotation_assignment_id']);
            $table->dropColumn('rotation_assignment_id');
            $table->dropColumn('assignment_source');
            $table->dropColumn('source_details');
        });
    }
};
