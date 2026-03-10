<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1.3: Create daily_attendance_summary table with ledger integrity tracking
     * 
     * This table aggregates daily attendance data with ledger sequence tracking
     * for integrity verification and reconciliation.
     */
    public function up(): void
    {
        Schema::create('daily_attendance_summary', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Employee & Date Reference
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('attendance_date')->comment('Date of attendance record');
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->comment('Applied work schedule for this date');

            // Time Tracking
            $table->timestamp('time_in')->nullable()->comment('Employee check-in time');
            $table->timestamp('time_out')->nullable()->comment('Employee check-out time');
            $table->timestamp('break_start')->nullable()->comment('Break period start');
            $table->timestamp('break_end')->nullable()->comment('Break period end');

            // Calculated Fields
            $table->decimal('total_hours_worked', 5, 2)->nullable()->comment('Total hours worked today');
            $table->decimal('regular_hours', 5, 2)->nullable()->comment('Regular (non-overtime) hours');
            $table->decimal('overtime_hours', 5, 2)->nullable()->comment('Overtime hours beyond schedule');
            $table->unsignedInteger('break_duration')->nullable()->comment('Total break duration in minutes');

            // Status Flags
            $table->boolean('is_present')->default(false)->comment('Employee was present today');
            $table->boolean('is_late')->default(false)->comment('Employee clocked in late');
            $table->boolean('is_undertime')->default(false)->comment('Employee worked fewer hours than scheduled');
            $table->boolean('is_overtime')->default(false)->comment('Employee worked overtime');
            $table->unsignedInteger('late_minutes')->nullable()->comment('Minutes late from scheduled start');
            $table->unsignedInteger('undertime_minutes')->nullable()->comment('Minutes short from scheduled duration');

            // Leave Integration
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->onDelete('set null')->comment('Approved leave for this date (if any)');
            $table->boolean('is_on_leave')->default(false)->comment('Employee is on approved leave');

            // Ledger Integrity Tracking (Task 5.1.3)
            $table->bigInteger('ledger_sequence_start')->nullable()->comment('First rfid_ledger sequence_id for this date');
            $table->bigInteger('ledger_sequence_end')->nullable()->comment('Last rfid_ledger sequence_id for this date');
            $table->boolean('ledger_verified')->default(true)->comment('All events verified against hash chain');

            // Processing Status
            $table->timestamp('calculated_at')->nullable()->comment('When summary was last calculated');
            $table->boolean('is_finalized')->default(false)->comment('Summary is locked and approved for payroll');

            // Audit & Timestamps
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // Unique Constraints
            $table->unique(['employee_id', 'attendance_date'], 'uq_daily_attendance_employee_date');

            // Indexes for Performance
            $table->index('attendance_date', 'idx_daily_attendance_date');
            $table->index('work_schedule_id', 'idx_daily_attendance_schedule_id');
            $table->index(['employee_id', 'attendance_date'], 'idx_daily_attendance_employee_date');
            $table->index('is_finalized', 'idx_daily_attendance_finalized');
            $table->index('is_on_leave', 'idx_daily_attendance_on_leave');
            $table->index('ledger_sequence_start', 'idx_daily_attendance_ledger_start');
            $table->index('ledger_sequence_end', 'idx_daily_attendance_ledger_end');
            $table->index('ledger_verified', 'idx_daily_attendance_ledger_verified');

            // Comments
            $table->comment('Daily attendance summary with ledger integrity tracking (Task 5.1.3)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_summary');
    }
};
