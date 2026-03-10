<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_government_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_payroll_calculation_id')->nullable()->constrained()->nullOnDelete();
            
            // Period Information
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_month', 7); // YYYY-MM format
            
            // Compensation Basis
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('gross_compensation', 10, 2); // For PhilHealth/SSS
            $table->decimal('taxable_income', 10, 2); // For BIR
            
            // SSS Contribution
            $table->string('sss_number')->nullable();
            $table->string('sss_bracket', 10)->nullable(); // A, B, C, etc.
            $table->decimal('sss_monthly_salary_credit', 10, 2)->nullable();
            $table->decimal('sss_employee_contribution', 8, 2)->default(0);
            $table->decimal('sss_employer_contribution', 8, 2)->default(0);
            $table->decimal('sss_ec_contribution', 8, 2)->default(0);
            $table->decimal('sss_total_contribution', 8, 2)->default(0);
            $table->boolean('is_sss_exempted')->default(false);
            
            // PhilHealth Contribution
            $table->string('philhealth_number')->nullable();
            $table->decimal('philhealth_premium_base', 10, 2)->nullable();
            $table->decimal('philhealth_employee_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_employer_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_total_contribution', 8, 2)->default(0);
            $table->boolean('is_philhealth_exempted')->default(false);
            
            // Pag-IBIG Contribution
            $table->string('pagibig_number')->nullable();
            $table->decimal('pagibig_compensation_base', 10, 2)->nullable();
            $table->decimal('pagibig_employee_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_employer_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_total_contribution', 8, 2)->default(0);
            $table->boolean('is_pagibig_exempted')->default(false);
            
            // BIR Withholding Tax
            $table->string('tin')->nullable();
            $table->string('tax_status', 10)->nullable();
            $table->decimal('annualized_taxable_income', 12, 2)->nullable();
            $table->decimal('tax_due', 10, 2)->default(0);
            $table->decimal('withholding_tax', 10, 2)->default(0);
            $table->decimal('tax_already_withheld_ytd', 10, 2)->default(0);
            $table->boolean('is_minimum_wage_earner')->default(false);
            $table->boolean('is_substituted_filing')->default(false);
            
            // De minimis and Exemptions
            $table->decimal('deminimis_benefits', 8, 2)->default(0);
            $table->decimal('thirteenth_month_pay', 10, 2)->default(0);
            $table->decimal('other_tax_exempt_compensation', 10, 2)->default(0);
            
            // Totals
            $table->decimal('total_employee_contributions', 10, 2)->default(0);
            $table->decimal('total_employer_contributions', 10, 2)->default(0);
            $table->decimal('total_statutory_deductions', 10, 2)->default(0);
            
            // Processing Status
            $table->enum('status', ['pending', 'calculated', 'processed', 'remitted'])->default('pending');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['period_month', 'status']);
            $table->index('sss_number');
            $table->index('philhealth_number');
            $table->index('pagibig_number');
            $table->index('tin');
            $table->unique(['employee_id', 'payroll_period_id'], 'unique_employee_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_government_contributions');
    }
};
