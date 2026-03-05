<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add advance_deduction column to employee_payroll_calculations table
     * to track cash advance deductions processed during payroll calculation.
     * 
     * Integration: Phase 3, Task 3.1, Subtask 3.1.1
     * Feature: Cash Advances & Salary Advances Management
     * 
     * The advance_deduction column stores the amount deducted from
     * employee's net pay for cash advances in current payroll period.
     */
    public function up(): void
    {
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            // Add advance_deduction column after other_deductions
            // This tracks the amount deducted for active cash advances
            $table->decimal('advance_deduction', 10, 2)
                ->default(0)
                ->after('other_deductions')
                ->comment('Cash advance deduction for payroll period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            $table->dropColumn('advance_deduction');
        });
    }
};
