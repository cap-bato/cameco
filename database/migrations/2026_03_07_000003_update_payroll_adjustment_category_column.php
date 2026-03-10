<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL: Drop the constraint to allow free-form category strings
        DB::statement("ALTER TABLE payroll_adjustments DROP CONSTRAINT IF EXISTS payroll_adjustments_category_check");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to constraint (not recommended)
        DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_category_check CHECK (category IN ('retroactive_pay', 'correction', 'bonus', 'penalty', 'reimbursement', 'loan_adjustment', 'government_correction', 'rounding', 'other'))");
    }
};
