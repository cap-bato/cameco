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
        if (Schema::hasTable('payroll_adjustments')) {
            return;
        }
        
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            
            // Adjustment Type
            $table->enum('adjustment_type', [
                'addition',     // Add to net pay
                'deduction',    // Subtract from net pay
                'override'      // Replace calculated amount
            ]);
            
            // Adjustment Category
            $table->enum('category', [
                'retroactive_pay',      // Salary increase backpay
                'correction',           // Fix calculation error
                'bonus',               // One-time bonus
                'penalty',             // Disciplinary deduction
                'reimbursement',       // Expense reimbursement
                'loan_adjustment',     // Loan payment correction
                'government_correction', // Gov't deduction fix
                'rounding',            // Rounding adjustment
                'other'
            ]);
            
            // Component Being Adjusted
            $table->string('component')->nullable(); // e.g., 'basic_pay', 'overtime_pay', 'sss_contribution'
            
            // Amount
            $table->decimal('amount', 8, 2);
            $table->decimal('original_amount', 8, 2)->nullable(); // For overrides
            $table->decimal('adjusted_amount', 8, 2)->nullable(); // For overrides
            
            // Reason & Justification
            $table->string('reason', 200);
            $table->text('justification')->nullable();
            $table->string('reference_number')->nullable(); // Document reference
            
            // Supporting Documents
            $table->json('supporting_documents')->nullable(); // File paths
            
            // Approval Status
            $table->enum('status', [
                'pending',      // Awaiting approval
                'approved',     // Approved by HR Manager
                'rejected',     // Rejected
                'applied'       // Applied to calculation
            ])->default('pending');
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            
            // Impact
            $table->decimal('impact_on_net_pay', 8, 2)->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_payroll_calculation_id', 'status']);
            $table->index(['payroll_period_id', 'category']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
