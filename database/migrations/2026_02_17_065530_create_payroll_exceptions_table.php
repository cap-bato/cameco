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
        if (Schema::hasTable('payroll_exceptions')) {
            return;
        }
        
        Schema::create('payroll_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            
            // Exception Type
            $table->enum('exception_type', [
                'high_variance',           // >20% deviation from previous period
                'low_net_pay',            // Net pay < ₱1,000
                'high_net_pay',           // Net pay > ₱500,000
                'negative_net_pay',       // Net pay < 0
                'missing_timekeeping',    // No attendance data
                'missing_government_id',  // Missing SSS/PhilHealth/etc
                'excessive_deduction',    // Deductions > 50% gross pay
                'missing_leave_data',     // Unpaid leave with no leave record
                'calculation_error',      // General calculation error
                'data_inconsistency'      // Conflicting data sources
            ]);
            
            // Severity
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            
            // Details
            $table->string('title');
            $table->text('description');
            $table->json('details')->nullable(); // Structured details
            
            // Values
            $table->decimal('current_value', 10, 2)->nullable();
            $table->decimal('expected_value', 10, 2)->nullable();
            $table->decimal('variance', 10, 2)->nullable();
            $table->decimal('variance_percentage', 5, 2)->nullable();
            
            // Resolution
            $table->enum('status', [
                'open',         // Needs review
                'acknowledged', // Reviewed, intentional
                'resolved',     // Fixed via adjustment
                'ignored'       // Accepted as-is
            ])->default('open')->index();
            
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Auto-generated flag
            $table->boolean('is_auto_generated')->default(true);
            $table->string('detection_rule')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_payroll_calculation_id', 'status']);
            $table->index(['payroll_period_id', 'exception_type']);
            $table->index(['severity', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_exceptions');
    }
};
