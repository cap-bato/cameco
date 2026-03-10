<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the overly-restrictive one-per-period unique constraint and add
     * 'superseded' to the calculation_status enum to enable versioned audit trails.
     *
     * The original migration already defined:
     *   unique_employee_period_version (employee_id, payroll_period_id, version)
     * which correctly allows multiple rows per employee per period as long as
     * version numbers differ. The later constraint (unique_payroll_period_employee)
     * contradicted this by enforcing exactly one row total — forcing forceDelete()
     * and destroying audit history on every recalculation.
     *
     * After this migration:
     * - Old (superseded) calculation rows are soft-deleted with status='superseded'
     * - New recalculations get version++ and previous_version_id pointing to the old row
     * - Normal queries (without withTrashed()) see only the latest version
     * - Audit queries use withTrashed() to see the full version chain
     *
     * Related: PAYROLL_CALCULATION_VERSIONING.md Phase 1
     */
    public function up(): void
    {
        // Drop the overly-restrictive one-per-period constraint.
        // The version-based constraint unique_employee_period_version (employee_id, period_id, version)
        // is the correct one and stays. Soft-deleted old versions satisfy it because version differs.
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            $table->dropUnique('unique_payroll_period_employee');
        });

        // Add 'superseded' to the calculation_status check constraint (PostgreSQL only).
        // Laravel creates enum columns as varchar + CHECK constraint on PostgreSQL.
        // SQLite does not support ALTER TABLE DROP/ADD CONSTRAINT — skip on SQLite.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employee_payroll_calculations DROP CONSTRAINT employee_payroll_calculations_calculation_status_check');
            DB::statement("ALTER TABLE employee_payroll_calculations ADD CONSTRAINT employee_payroll_calculations_calculation_status_check CHECK (calculation_status IN ('pending','calculating','calculated','exception','adjusted','approved','locked','superseded'))");
        }
    }

    /**
     * Reverse the migration.
     * ⚠️ Only safe to run down() if no versioned (superseded) rows exist in the table.
     */
    public function down(): void
    {
        // Revert check constraint — remove 'superseded' value (PostgreSQL only).
        // WARNING: any rows with calculation_status='superseded' must be updated first.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employee_payroll_calculations DROP CONSTRAINT employee_payroll_calculations_calculation_status_check');
            DB::statement("ALTER TABLE employee_payroll_calculations ADD CONSTRAINT employee_payroll_calculations_calculation_status_check CHECK (calculation_status IN ('pending','calculating','calculated','exception','adjusted','approved','locked'))");
        }

        // Re-add the strict one-per-period constraint.
        Schema::table('employee_payroll_calculations', function (Blueprint $table) {
            $table->unique(['payroll_period_id', 'employee_id'], 'unique_payroll_period_employee');
        });
    }
};
