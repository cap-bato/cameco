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
        Schema::create('advance_deductions', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('cash_advance_id')->constrained('employee_cash_advances')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('employee_payroll_calculation_id')->nullable()->constrained('employee_payroll_calculations')->nullOnDelete();
            
            // Deduction Details
            $table->integer('installment_number');
            $table->decimal('deduction_amount', 10, 2);
            $table->decimal('remaining_balance_after', 10, 2);
            
            // Status
            $table->boolean('is_deducted')->default(false);
            $table->timestamp('deducted_at')->nullable();
            $table->text('deduction_notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes and Constraints
            $table->index('cash_advance_id');
            $table->index('payroll_period_id');
            $table->index('is_deducted');
            $table->unique(['cash_advance_id', 'payroll_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_deductions');
    }
};
