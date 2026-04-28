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
            $table->string('loan_type'); // e.g. cash_advance, sss_loan, pagibig_loan, company_loan, etc.
            $table->string('loan_type_label')->nullable();
            $table->string('loan_number', 100)->unique()->nullable();

            // Loan Amount Details
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('interest_amount', 12, 2)->default(0);
            $table->decimal('total_loan_amount', 12, 2);

            // Repayment Details
            $table->integer('number_of_installments')->nullable();
            $table->decimal('installment_amount', 12, 2)->nullable();
            $table->integer('installments_paid')->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2)->nullable();

            // Dates
            $table->date('loan_date')->nullable();
            $table->date('first_deduction_date')->nullable();
            $table->date('last_deduction_date')->nullable();
            $table->date('completion_date')->nullable();

            // Status
            $table->string('status')->default('pending');
            $table->string('completion_reason')->nullable();

            // Reference / Notes
            $table->string('external_loan_number')->nullable();
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['employee_id', 'loan_type']);
            $table->index(['employee_id', 'status']);
            $table->index('loan_type');
            $table->index('status');
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
