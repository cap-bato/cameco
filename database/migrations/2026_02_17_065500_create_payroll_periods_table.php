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
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            
            // Period Information
            $table->string('period_number')->unique(); // e.g., "2026-01-A" (A=1-15, B=16-31)
            $table->string('period_name'); // e.g., "January 2026 - Period 1"
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('period_month', 7); // YYYY-MM format
            $table->integer('period_year');
            
            // Period Type
            $table->enum('period_type', [
                'regular',          // Normal semi-monthly
                'adjustment',       // Correction period
                '13th_month',       // 13th month pay
                'final_pay',        // Separated employees
                'mid_year_bonus'    // Mid-year bonus
            ])->default('regular');
            
            // Cutoff Dates
            $table->date('timekeeping_cutoff_date'); // Data freeze for timekeeping
            $table->date('leave_cutoff_date'); // Data freeze for leave
            $table->date('adjustment_deadline'); // Last day for manual adjustments
            
            // Employee Coverage
            $table->integer('total_employees')->default(0);
            $table->integer('active_employees')->default(0);
            $table->integer('excluded_employees')->default(0);
            $table->json('employee_filter')->nullable(); // Department, position filters
            
            // Financial Totals
            $table->decimal('total_gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net_pay', 12, 2)->default(0);
            $table->decimal('total_government_contributions', 12, 2)->default(0);
            $table->decimal('total_loan_deductions', 10, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            
            // Processing Status
            $table->enum('status', [
                'draft',            // Period created, not yet processing
                'active',           // Ready for calculations
                'calculating',      // Calculation in progress
                'calculated',       // Calculations complete
                'under_review',     // Payroll Officer reviewing
                'pending_approval', // Submitted to HR Manager
                'approved',         // HR Manager approved, awaiting Office Admin
                'finalized',        // Office Admin approved, locked
                'processing_payment', // Sent to Payments module
                'completed',        // Payment distributed
                'cancelled'         // Period cancelled
            ])->default('draft')->index();
            
            // Processing Timestamps
            $table->timestamp('calculation_started_at')->nullable();
            $table->timestamp('calculation_completed_at')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            
            // Calculation Metadata
            $table->json('calculation_config')->nullable(); // Settings used for calculation
            $table->integer('calculation_retries')->default(0);
            $table->text('calculation_errors')->nullable();
            $table->integer('exceptions_count')->default(0);
            $table->integer('adjustments_count')->default(0);
            
            // Data Sources
            $table->json('timekeeping_summary')->nullable(); // Snapshot of attendance data
            $table->json('leave_summary')->nullable(); // Snapshot of leave data
            $table->boolean('timekeeping_data_locked')->default(false);
            $table->boolean('leave_data_locked')->default(false);
            
            // Approval Chain
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // HR Manager
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Office Admin
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['period_start', 'period_end']);
            $table->index(['period_month', 'period_type']);
            $table->index(['payment_date', 'status']);
            $table->index('period_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
