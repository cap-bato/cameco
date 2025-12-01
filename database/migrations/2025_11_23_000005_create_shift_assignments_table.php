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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('work_schedules')->onDelete('restrict');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');

            // Assignment Details
            $table->date('date');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->enum('shift_type', ['morning', 'afternoon', 'night', 'split', 'custom'])->nullable();

            // Location
            $table->string('location', 191)->nullable();

            // Overtime Tracking
            $table->boolean('is_overtime')->default(false);
            $table->decimal('overtime_hours', 5, 2)->default(0.00);

            // Status Tracking
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled');

            // Conflict Detection
            $table->boolean('has_conflict')->default(false);
            $table->text('conflict_reason')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_id');
            $table->index('schedule_id');
            $table->index('date');
            $table->index('department_id');
            $table->index('status');
            $table->index('is_overtime');
            $table->index('has_conflict');

            // Unique Constraint - prevent double-booking same employee
            $table->unique(['employee_id', 'date', 'shift_start'], 'unique_employee_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
