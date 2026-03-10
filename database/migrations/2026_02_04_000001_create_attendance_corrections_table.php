<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 4.4.5: Creates attendance_corrections table for manual correction workflow
     * Tracks corrections to attendance records with full audit trail.
     * Corrections are stored separately from attendance_events to preserve ledger integrity.
     */
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            
            // Reference to the attendance event being corrected
            $table->foreignId('attendance_event_id')
                ->constrained('attendance_events')
                ->onDelete('cascade')
                ->comment('Attendance event being corrected');
            
            // User tracking
            $table->foreignId('requested_by_user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('User who requested the correction');
            
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who approved the correction');
            
            // Original times (for audit trail)
            $table->time('original_time_in')->nullable()->comment('Original time in before correction');
            $table->time('original_time_out')->nullable()->comment('Original time out before correction');
            $table->time('original_break_start')->nullable()->comment('Original break start before correction');
            $table->time('original_break_end')->nullable()->comment('Original break end before correction');
            
            // Corrected times
            $table->time('corrected_time_in')->nullable()->comment('Corrected time in');
            $table->time('corrected_time_out')->nullable()->comment('Corrected time out');
            $table->time('corrected_break_start')->nullable()->comment('Corrected break start');
            $table->time('corrected_break_end')->nullable()->comment('Corrected break end');
            
            // Hours calculation
            $table->decimal('hours_difference', 5, 2)
                ->comment('Difference in hours (corrected - original)');
            
            // Correction details
            $table->string('correction_reason')
                ->comment('Reason code: wrong_entry, machine_error, employee_reported, manual_adjustment, other');
            
            $table->text('justification')
                ->comment('Detailed justification for the correction (minimum 10 characters)');
            
            $table->text('rejection_reason')
                ->nullable()
                ->comment('Reason for rejection if status is rejected');
            
            // Status tracking
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->comment('Correction approval status');
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent()->comment('When correction was requested');
            $table->timestamp('processed_at')->nullable()->comment('When correction was approved/rejected');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('attendance_event_id');
            $table->index('status');
            $table->index('requested_by_user_id');
            $table->index('approved_by_user_id');
            $table->index('requested_at');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
