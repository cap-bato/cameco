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
        if (Schema::hasTable('employee_payroll_calculations')) {
            return;
        }
        
        Schema::create('employee_payroll_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Employee Snapshot (at calculation time)
            $table->string('employee_number', 20);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->enum('employment_status', ['regular', 'probationary', 'contractual', 'project_based'])->nullable();
            $table->date('hire_date')->nullable();
            
            // Salary Configuration (Snapshot)
            $table->decimal('basic_monthly_salary', 10, 2);
            $table->decimal('daily_rate', 8, 2);
            $table->decimal('hourly_rate', 8, 2);
            $table->integer('working_days_per_month')->default(22);
            $table->decimal('working_hours_per_day', 4, 2)->default(8);
            
            // TIMEKEEPING DATA
            $table->integer('expected_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('excused_absences')->default(0);
            $table->integer('unexcused_absences')->default(0);
            $table->decimal('late_hours', 6, 2)->default(0);
            $table->decimal('undertime_hours', 6, 2)->default(0);
            
            // Overtime Hours
            $table->decimal('regular_overtime_hours', 6, 2)->default(0);
            $table->decimal('rest_day_overtime_hours', 6, 2)->default(0);
            $table->decimal('holiday_overtime_hours', 6, 2)->default(0);
            $table->decimal('night_differential_hours', 6, 2)->default(0);
            $table->decimal('total_overtime_hours', 6, 2)->default(0);
            
            // LEAVE DATA
            $table->integer('paid_leave_days')->default(0);
            $table->integer('unpaid_leave_days')->default(0);
            $table->decimal('leave_deduction_amount', 8, 2)->default(0);
            $table->json('leave_breakdown')->nullable();
            
            // EARNINGS CALCULATION
            $table->decimal('basic_pay', 10, 2)->default(0);
            
            // Overtime Pay
            $table->decimal('regular_overtime_pay', 8, 2)->default(0);
            $table->decimal('rest_day_overtime_pay', 8, 2)->default(0);
            $table->decimal('holiday_overtime_pay', 8, 2)->default(0);
            $table->decimal('night_differential_pay', 8, 2)->default(0);
            $table->decimal('total_overtime_pay', 8, 2)->default(0);
            
            // Allowances
            $table->decimal('transportation_allowance', 8, 2)->default(0);
            $table->decimal('meal_allowance', 8, 2)->default(0);
            $table->decimal('housing_allowance', 8, 2)->default(0);
            $table->decimal('communication_allowance', 8, 2)->default(0);
            $table->decimal('other_allowances', 8, 2)->default(0);
            $table->decimal('total_allowances', 8, 2)->default(0);
            
            // Bonuses & Incentives
            $table->decimal('performance_bonus', 8, 2)->default(0);
            $table->decimal('attendance_bonus', 8, 2)->default(0);
            $table->decimal('productivity_bonus', 8, 2)->default(0);
            $table->decimal('other_income', 8, 2)->default(0);
            $table->decimal('total_bonuses', 8, 2)->default(0);
            
            // Gross Pay
            $table->decimal('gross_pay', 10, 2)->default(0);
            
            // DEDUCTIONS CALCULATION
            // Government Contributions
            $table->decimal('sss_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_contribution', 8, 2)->default(0);
            $table->decimal('withholding_tax', 8, 2)->default(0);
            $table->decimal('total_government_deductions', 8, 2)->default(0);
            
            // Loan Deductions
            $table->decimal('sss_loan_deduction', 8, 2)->default(0);
            $table->decimal('pagibig_loan_deduction', 8, 2)->default(0);
            $table->decimal('company_loan_deduction', 8, 2)->default(0);
            $table->decimal('total_loan_deductions', 8, 2)->default(0);
            
            // Advance Deductions
            $table->decimal('cash_advance_deduction', 8, 2)->default(0);
            $table->decimal('salary_advance_deduction', 8, 2)->default(0);
            $table->decimal('total_advance_deductions', 8, 2)->default(0);
            
            // Other Deductions
            $table->decimal('tardiness_deduction', 8, 2)->default(0);
            $table->decimal('absence_deduction', 8, 2)->default(0);
            $table->decimal('uniform_deduction', 8, 2)->default(0);
            $table->decimal('tool_deduction', 8, 2)->default(0);
            $table->decimal('miscellaneous_deductions', 8, 2)->default(0);
            
            // Total Deductions
            $table->decimal('total_deductions', 10, 2)->default(0);
            
            // NET PAY
            $table->decimal('net_pay', 10, 2)->default(0);
            
            // ADJUSTMENTS
            $table->decimal('adjustments_total', 8, 2)->default(0);
            $table->decimal('final_net_pay', 10, 2)->default(0);
            
            // CALCULATION METADATA
            $table->enum('calculation_status', [
                'pending',
                'calculating',
                'calculated',
                'exception',
                'adjusted',
                'approved',
                'locked'
            ])->default('pending')->index();
            
            $table->boolean('has_exceptions')->default(false);
            $table->integer('exceptions_count')->default(0);
            $table->json('exception_flags')->nullable();
            
            $table->boolean('has_adjustments')->default(false);
            $table->integer('adjustments_count')->default(0);
            
            $table->json('calculation_breakdown')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            
            // Version Control
            $table->integer('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()->constrained('employee_payroll_calculations')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['payroll_period_id', 'calculation_status']);
            $table->index('has_exceptions');
            $table->index('has_adjustments');
            $table->unique(['employee_id', 'payroll_period_id', 'version'], 'unique_employee_period_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_calculations');
    }
};
