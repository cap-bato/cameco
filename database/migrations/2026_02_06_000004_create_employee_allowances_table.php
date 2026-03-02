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
        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Allowance Type
            $table->enum('allowance_type', [
                'rice',
                'cola',
                'transportation',
                'meal',
                'housing',
                'communication',
                'utilities',
                'laundry',
                'uniform',
                'medical',
                'educational',
                'special_project',
                'other'
            ]);
            
            // Allowance Details
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 10, 2);
            
            // Frequency and Timing
            $table->enum('frequency', ['per_payroll', 'monthly', 'quarterly', 'semi_annual', 'annually', 'one_time'])->default('monthly');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            
            // Proration and Conditions
            $table->boolean('is_prorated')->default(false); // Prorate for partial periods
            $table->boolean('is_taxable')->default(false); // Most allowances are non-taxable
            $table->decimal('deminimis_limit_monthly', 10, 2)->nullable(); // Monthly de minimis limit
            $table->decimal('deminimis_limit_annual', 10, 2)->nullable(); // Annual de minimis limit
            
            // Approval and Status
            $table->enum('status', ['pending', 'approved', 'active', 'completed', 'cancelled'])->default('pending');
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
            $table->index(['allowance_type', 'is_active']);
            $table->index('effective_date');
            $table->index('status');
            $table->unique(['employee_id', 'allowance_type', 'effective_date'], 'unique_employee_allowance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_allowances');
    }
};
