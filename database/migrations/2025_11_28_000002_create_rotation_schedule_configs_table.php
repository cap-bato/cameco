<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates the rotation_schedule_configs table which explicitly links
     * a rotation assignment to a work schedule for a specific date range.
     *
     * Phase 2 Implementation: Configuration Layer
     * This table answers the question: "Which schedule applies to which rotation, and when?"
     *
     * Example:
     *   - Employee John has rotation_assignment_id 1 (4x2 rotation)
     *   - For Nov 27, 2025 to Jan 27, 2026, he uses work_schedule_id 3 (Mon-Fri 9-5)
     *   - After Jan 27, he uses work_schedule_id 5 (Mon-Fri 10-6)
     */
    public function up(): void
    {
        Schema::create('rotation_schedule_configs', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('rotation_assignment_id')
                ->constrained('rotation_assignments')
                ->onDelete('cascade');
            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules')
                ->onDelete('restrict');

            // Date Range: When this configuration is active
            $table->date('effective_date');
            $table->date('end_date')->nullable()->comment('NULL means indefinite/ongoing');

            // Status
            $table->boolean('is_active')->default(true)->index();

            // Metadata
            $table->timestamps();

            // Indexes for common queries
            $table->index(['rotation_assignment_id', 'effective_date'], 'idx_rotation_effective_date');
            $table->index('work_schedule_id', 'idx_work_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotation_schedule_configs');
    }
};
