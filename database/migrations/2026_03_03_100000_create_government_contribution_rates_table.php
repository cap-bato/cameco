<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_contribution_rates', function (Blueprint $table) {
            $table->id();

            // Rate Type
            $table->enum('agency', ['sss', 'philhealth', 'pagibig'])->index();
            $table->string('rate_type', 50); // 'bracket', 'premium_rate', 'contribution_rate'

            // SSS Bracket Information
            $table->string('bracket_code', 10)->nullable(); // 'A', 'B', 'C', etc.
            $table->decimal('compensation_min', 10, 2)->nullable();
            $table->decimal('compensation_max', 10, 2)->nullable();
            $table->decimal('monthly_salary_credit', 10, 2)->nullable(); // MSC

            // Contribution Rates (as percentages)
            $table->decimal('employee_rate', 5, 2)->nullable(); // e.g., 4.50 for 4.5%
            $table->decimal('employer_rate', 5, 2)->nullable(); // e.g., 8.50 for 8.5%
            $table->decimal('total_rate', 5, 2)->nullable();

            // Fixed Amounts
            $table->decimal('employee_amount', 8, 2)->nullable();
            $table->decimal('employer_amount', 8, 2)->nullable();
            $table->decimal('ec_amount', 8, 2)->nullable(); // SSS EC contribution
            $table->decimal('total_amount', 8, 2)->nullable();

            // PhilHealth/Pag-IBIG Limits
            $table->decimal('minimum_contribution', 8, 2)->nullable();
            $table->decimal('maximum_contribution', 8, 2)->nullable();
            $table->decimal('premium_ceiling', 10, 2)->nullable(); // PhilHealth ₱100k
            $table->decimal('contribution_ceiling', 8, 2)->nullable(); // Pag-IBIG ₱100

            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['agency', 'is_active', 'effective_from']);
            $table->index(['bracket_code', 'agency']);
            $table->index(['compensation_min', 'compensation_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_contribution_rates');
    }
};
