<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enhances leave balance tracking system by:
     * - Adding missing fields to leave_balances (forfeited, last_accrued_at)
     * - Creating leave_accruals table for audit trail
     * - Creating leave_carry_forward_rules table for policy configuration
     */
    public function up(): void
    {
        // Add missing columns to existing leave_balances table
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->decimal('forfeited', 5, 2)->default(0)->after('carried_forward');
            $table->timestamp('last_accrued_at')->nullable()->after('forfeited');
            
            // Add additional indexes for better query performance
            $table->index(['employee_id', 'year']);
            $table->index('leave_policy_id');
        });

        // Create leave_accruals table for audit trail
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->date('accrual_date');
            $table->decimal('amount', 5, 2);
            $table->enum('accrual_type', ['monthly', 'annual', 'manual', 'adjustment', 'carried_forward']);
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['leave_balance_id', 'accrual_date']);
            $table->index('accrual_date');
        });

        // Create leave_carry_forward_rules table
        Schema::create('leave_carry_forward_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
            $table->decimal('max_carry_forward_days', 5, 2)->default(5);
            $table->integer('expiry_months')->default(3)->comment('Months until carried leave expires');
            $table->boolean('allow_partial')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['leave_policy_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables first
        Schema::dropIfExists('leave_carry_forward_rules');
        Schema::dropIfExists('leave_accruals');
        
        // Remove added columns from leave_balances
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn(['forfeited', 'last_accrued_at']);
            $table->dropIndex(['employee_id', 'year']);
            $table->dropIndex(['leave_policy_id']);
        });
    }
};
