<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL-compatible syntax — SQLite does not support ALTER COLUMN
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_adjustments ALTER COLUMN employee_payroll_calculation_id DROP NOT NULL');
        } else {
            // SQLite: use Schema to change the column nullable (Laravel handles recreating the table)
            Schema::table('payroll_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('employee_payroll_calculation_id')->nullable()->change();
            });
        }

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            // Add review_notes column for both approval and rejection notes
            if (!Schema::hasColumn('payroll_adjustments', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('rejection_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            // Drop review_notes column
            $table->dropColumn('review_notes');
        });

        // Restore non-nullable constraint (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payroll_adjustments ALTER COLUMN employee_payroll_calculation_id SET NOT NULL');
        }
    }
};
