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
        Schema::create('loan_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->onDelete('cascade');
            $table->foreignId('payroll_calculation_id')->nullable()->constrained('payroll_calculations')->onDelete('set null');
            
            // Installment Information
            $table->integer('installment_number'); // Which installment (1st, 2nd, etc.)
            $table->date('due_date'); // When payment is due
            $table->date('paid_date')->nullable(); // When actually paid
            
            // Deduction Amounts
            $table->decimal('principal_deduction', 10, 2); // Principal portion of installment
            $table->decimal('interest_deduction', 10, 2); // Interest portion of installment
            $table->decimal('total_deduction', 10, 2); // Total deduction (principal + interest)
            $table->decimal('penalty_amount', 10, 2)->default(0); // For late payment penalties
            
            // Payment Details
            $table->decimal('amount_deducted', 10, 2)->default(0); // Amount actually deducted in payroll
            $table->decimal('amount_paid', 10, 2)->default(0); // Amount actually paid by employee
            $table->decimal('balance_after_payment', 10, 2)->nullable(); // Remaining loan balance
            
            // Status
            $table->enum('status', ['pending', 'deducted', 'paid', 'overdue', 'partial_paid', 'waived', 'cancelled'])->default('pending');
            $table->timestamp('deducted_at')->nullable(); // When deducted from payroll
            
            // Reference and Notes
            $table->string('reference_number', 100)->nullable(); // Check number, bank reference, etc.
            $table->text('remarks')->nullable();
            
            // System Fields
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_loan_id', 'status']);
            $table->index('due_date');
            $table->index('paid_date');
            $table->index(['employee_loan_id', 'installment_number']);
            $table->index('payroll_calculation_id');
            $table->unique(['employee_loan_id', 'installment_number'], 'unique_loan_installment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_deductions');
    }
};
