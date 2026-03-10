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
        // PostgreSQL-compatible syntax
        DB::statement('ALTER TABLE payroll_adjustments ALTER COLUMN employee_payroll_calculation_id DROP NOT NULL');
        
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            // Add review_notes column for both approval and rejection notes
            $table->text('review_notes')->nullable()->after('rejection_reason');
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
        
        // Restore non-nullable constraint
        DB::statement('ALTER TABLE payroll_adjustments ALTER COLUMN employee_payroll_calculation_id SET NOT NULL');
    }
};
