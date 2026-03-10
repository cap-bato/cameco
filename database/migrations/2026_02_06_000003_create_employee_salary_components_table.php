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
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('salary_component_id')->constrained('salary_components')->onDelete('cascade');
            
            // Component Settings
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('units', 8, 2)->nullable(); // For per-unit calculations
            
            // Frequency and Timing
            $table->enum('frequency', ['per_payroll', 'monthly', 'quarterly', 'semi_annual', 'annually', 'one_time'])->default('per_payroll');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            
            // Conditions
            $table->boolean('is_prorated')->default(false); // Prorate if not full period
            $table->boolean('requires_attendance')->default(true); // Deduct if absent
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index(['salary_component_id', 'is_active']);
            $table->index('effective_date');
            $table->unique(['employee_id', 'salary_component_id', 'effective_date'], 'unique_employee_component_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
