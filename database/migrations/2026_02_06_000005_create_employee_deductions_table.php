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
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Deduction Type
            $table->enum('deduction_type', [
                'insurance',
                'hmo',
                'union_dues',
                'canteen',
                'utilities',
                'loan_deduction',
                'professional_fee',
                'contribution',
                'tax_adjustment',
                'court_order',
                'other'
            ]);
            
            // Deduction Details
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 10, 2);
            
            // Frequency and Timing
            $table->enum('frequency', ['per_payroll', 'monthly', 'quarterly', 'semi_annual', 'annually', 'one_time'])->default('monthly');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            
            // Deduction Settings
            $table->boolean('is_prorated')->default(false); // Prorate for partial periods
            $table->boolean('is_required')->default(false); // Mandatory deduction
            $table->decimal('max_deduction_amount', 10, 2)->nullable(); // Maximum deduction per period
            
            // Related Loan (if applicable) - Will be set via separate migration after employee_loans is created
            $table->unsignedBigInteger('employee_loan_id')->nullable();
            
            // Approval and Status
            $table->enum('status', ['pending', 'approved', 'active', 'completed', 'suspended', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index(['deduction_type', 'is_active']);
            $table->index('effective_date');
            $table->index('status');
            $table->unique(['employee_id', 'deduction_type', 'effective_date'], 'unique_employee_deduction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};
