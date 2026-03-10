<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to map old values to new values
        DB::table('payroll_adjustments')->where('adjustment_type', 'addition')->update(['adjustment_type' => 'earning']);
        // 'deduction' stays the same
        DB::table('payroll_adjustments')->where('adjustment_type', 'override')->update(['adjustment_type' => 'correction']);
        
        // PostgreSQL: Drop the old constraint and add new one with updated values
        DB::statement("ALTER TABLE payroll_adjustments DROP CONSTRAINT IF EXISTS payroll_adjustments_adjustment_type_check");
        DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_adjustment_type_check CHECK (adjustment_type IN ('earning', 'deduction', 'correction', 'backpay', 'refund'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Map new values back to old values
        DB::table('payroll_adjustments')->where('adjustment_type', 'earning')->update(['adjustment_type' => 'addition']);
        DB::table('payroll_adjustments')->where('adjustment_type', 'correction')->update(['adjustment_type' => 'override']);
        DB::table('payroll_adjustments')->whereIn('adjustment_type', ['backpay', 'refund'])->update(['adjustment_type' => 'deduction']);
        
        // Revert to old constraint values
        DB::statement("ALTER TABLE payroll_adjustments DROP CONSTRAINT IF EXISTS payroll_adjustments_adjustment_type_check");
        DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_adjustment_type_check CHECK (adjustment_type IN ('addition', 'deduction', 'override'))");
    }
};
