<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a unique constraint on (payroll_period_id, employee_id) to prevent
     * duplicate calculation records within the same period. This acts as a safety
     * net to ensure that exactly one calculation record exists per employee per period.
     *
     * Note: Table uses SoftDeletes. This unique constraint applies to all rows
     * including soft-deleted ones. The application logic relies on forceDelete()
     * to remove old records before creating new ones, ensuring no constraint violations.
     *
     * Related: Gap 6 Phase 3 Task 3.1
     * Issue: CalculateEmployeePayrollJob could create duplicate records
     * Solution: Unique constraint prevents duplicates at the database level
     */
    public function up(): void
    {
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            // Add unique constraint on period + employee combination
            // This ensures only one calculation (latest version) per employee per period
            $table->unique(
                ['payroll_period_id', 'employee_id'],
                'unique_payroll_period_employee'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            $table->dropUnique('unique_payroll_period_employee');
        });
    }
};
