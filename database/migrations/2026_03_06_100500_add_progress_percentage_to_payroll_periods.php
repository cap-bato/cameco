<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Gap 4 Task 1.1: Add progress_percentage column to payroll_periods table
     * This column tracks the percentage of employees calculated during the current payroll run.
     * Allows frontend to display real-time progress during payroll calculation.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->decimal('progress_percentage', 5, 2)
                  ->default(0.00)
                  ->after('status')
                  ->comment('0.00–100.00: percentage of employees calculated during current run');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('progress_percentage');
        });
    }
};
