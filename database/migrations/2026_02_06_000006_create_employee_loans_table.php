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
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Loan Information
            $table->enum('loan_type', ['sss_loan', 'pagibig_loan', 'company_loan', 'personal_loan', 'emergency_loan']);
            $table->string('loan_number', 50)->unique()->nullable(); // Unique loan identifier
            $table->text('description')->nullable();
            
            // Loan Amount Details
            $table->decimal('principal_amount', 10, 2); // Original loan amount
            $table->decimal('interest_rate', 5, 2)->default(0); // Annual interest rate in percentage
            $table->decimal('interest_amount', 10, 2)->default(0); // Total interest to be charged
            $table->decimal('total_loan_amount', 10, 2); // Principal + Interest
            
            // Repayment Details
            $table->integer('installment_count'); // Number of installment payments
            $table->decimal('installment_amount', 10, 2); // Fixed monthly installment
            $table->integer('installments_paid')->default(0); // Number of payments made
            $table->decimal('balance_amount', 10, 2); // Remaining balance
            
            // Dates
            $table->date('disbursement_date'); // When loan was given
            $table->date('start_deduction_date'); // When deductions begin
            $table->date('maturity_date'); // Expected loan maturity date
            $table->date('actual_completion_date')->nullable(); // When loan was fully paid
            
            // Early Payment Support
            $table->boolean('allows_early_payment')->default(true);
            $table->decimal('penalty_rate_early_payment', 5, 2)->nullable(); // Penalty percentage for early payment
            
            // Loan Status
            $table->enum('status', ['pending', 'approved', 'disbursed', 'active', 'completed', 'defaulted', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            
            // Approval and References
            $table->string('reference_number', 100)->nullable(); // Bank reference, memo number, etc.
            $table->text('remarks')->nullable();
            
            // System Fields
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index(['loan_type', 'is_active']);
            $table->index('status');
            $table->index('disbursement_date');
            $table->index(['employee_id', 'loan_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
