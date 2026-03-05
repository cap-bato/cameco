<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create employee_payroll_calculations table to store individual
     * employee payroll calculation records for each payroll period.
     * 
     * This table tracks:
     * - Daily attendance and hours worked
     * - Salary components and amounts
     * - Deductions (government, loans, advances, etc.)
     * - Final net pay amounts
     * - Calculation status and metadata
     */
    public function up(): void
    {
        Schema::create('employee_payroll_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();

            // Attendance Data
            $table->integer('days_worked')->default(0)->comment('Number of working days');
            $table->decimal('total_hours', 8, 2)->default(0)->comment('Total hours worked');
            $table->decimal('regular_hours', 8, 2)->default(0)->comment('Regular hours worked');
            $table->decimal('overtime_hours', 8, 2)->default(0)->comment('Overtime hours worked');
            $table->integer('late_minutes')->default(0)->comment('Total late minutes');
            $table->integer('undertime_minutes')->default(0)->comment('Total undertime minutes');

            // Earnings
            $table->decimal('basic_pay', 12, 2)->default(0)->comment('Basic salary for period');
            $table->decimal('overtime_pay', 12, 2)->default(0)->comment('Overtime pay');
            $table->decimal('component_amount', 12, 2)->default(0)->comment('Salary components total');
            $table->decimal('allowance_amount', 12, 2)->default(0)->comment('Allowances total');
            $table->decimal('gross_pay', 12, 2)->default(0)->comment('Total gross pay');

            // Government Contributions (Employee Share)
            $table->decimal('sss_contribution', 10, 2)->default(0)->comment('SSS contribution');
            $table->decimal('philhealth_contribution', 10, 2)->default(0)->comment('PhilHealth contribution');
            $table->decimal('pagibig_contribution', 10, 2)->default(0)->comment('Pag-IBIG contribution');
            $table->decimal('withholding_tax', 10, 2)->default(0)->comment('BIR withholding tax');

            // Deductions
            $table->decimal('deduction_amount', 10, 2)->default(0)->comment('Regular deductions');
            $table->decimal('loan_deduction', 10, 2)->default(0)->comment('Loan deductions');
            $table->decimal('late_deduction', 10, 2)->default(0)->comment('Late deduction');
            $table->decimal('undertime_deduction', 10, 2)->default(0)->comment('Undertime deduction');
            $table->decimal('other_deductions', 10, 2)->default(0)->comment('Other deductions');

            // Final Calculations
            $table->decimal('total_deductions', 12, 2)->default(0)->comment('Total deductions');
            $table->decimal('net_pay', 12, 2)->default(0)->comment('Net pay after all deductions');

            // Status and Timestamps
            $table->enum('status', ['draft', 'calculated', 'finalized', 'approved', 'paid'])->default('draft');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('payroll_period_id');
            $table->index('employee_id');
            $table->index('status');
            $table->unique(['payroll_period_id', 'employee_id']);
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
