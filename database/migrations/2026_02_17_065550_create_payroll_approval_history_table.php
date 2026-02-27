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
        if (Schema::hasTable('payroll_approval_history')) {
            return;
        }
        
        Schema::create('payroll_approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Approval Step
            $table->enum('approval_step', [
                'payroll_officer_submit',  // Payroll Officer submits for review
                'hr_manager_review',       // HR Manager reviews
                'hr_manager_approve',      // HR Manager approves
                'hr_manager_reject',       // HR Manager rejects
                'office_admin_review',     // Office Admin reviews
                'office_admin_approve',    // Office Admin approves (final)
                'office_admin_reject',     // Office Admin rejects
                'locked',                  // Period locked
                'unlocked'                 // Period unlocked (rare)
            ]);
            
            // Action
            $table->enum('action', ['submit', 'approve', 'reject', 'lock', 'unlock']);
            
            // Status Change
            $table->string('status_from');
            $table->string('status_to');
            
            // Actor
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('user_name');
            $table->string('user_role');
            
            // Notes
            $table->text('comments')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Snapshot (optional)
            $table->json('period_snapshot')->nullable(); // State at approval time
            
            // Timestamps
            $table->timestamp('created_at');
            
            // Indexes
            $table->index(['payroll_period_id', 'approval_step']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_approval_history');
    }
};
