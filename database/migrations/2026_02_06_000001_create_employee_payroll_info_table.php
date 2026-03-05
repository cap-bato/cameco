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
        Schema::create('employee_payroll_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Salary Information
            $table->enum('salary_type', ['monthly', 'daily', 'hourly', 'contractual', 'project_based']);
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->decimal('daily_rate', 8, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            
            // Payment Method
            $table->enum('payment_method', ['bank_transfer', 'cash', 'check']);
            
            // Tax Information
            $table->enum('tax_status', ['Z', 'S', 'ME', 'S1', 'ME1', 'S2', 'ME2', 'S3', 'ME3', 'S4', 'ME4']);
            $table->string('rdo_code', 10)->nullable();
            $table->decimal('withholding_tax_exemption', 8, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('is_substituted_filing')->default(false);
            
            // Government Numbers
            $table->string('sss_number', 20)->nullable();
            $table->string('philhealth_number', 20)->nullable();
            $table->string('pagibig_number', 20)->nullable();
            $table->string('tin_number', 20)->nullable();
            
            // Government Contribution Settings
            $table->string('sss_bracket', 20)->nullable();
            $table->boolean('is_sss_voluntary')->default(false);
            $table->boolean('philhealth_is_indigent')->default(false);
            $table->decimal('pagibig_employee_rate', 4, 2)->default(1.00);
            
            // Bank Information
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            
            // De Minimis Benefits Entitlements
            $table->boolean('is_entitled_to_rice')->default(true);
            $table->boolean('is_entitled_to_uniform')->default(true);
            $table->boolean('is_entitled_to_laundry')->default(false);
            $table->boolean('is_entitled_to_medical')->default(true);
            
            // Effective Dates
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index('salary_type');
            $table->index('effective_date');
            $table->unique(['employee_id', 'is_active'], 'unique_employee_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_info');
    }
};
