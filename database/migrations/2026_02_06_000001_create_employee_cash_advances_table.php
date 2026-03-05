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
        Schema::create('employee_cash_advances', function (Blueprint $table) {
            $table->id();
            
            // Advance Number (unique identifier)
            $table->string('advance_number', 20)->unique();
            
            // Employee Info
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            
            // Advance Details
            $table->enum('advance_type', ['cash_advance', 'medical_advance', 'travel_advance', 'equipment_advance'])->default('cash_advance');
            $table->decimal('amount_requested', 10, 2);
            $table->decimal('amount_approved', 10, 2)->nullable();
            $table->text('purpose');
            $table->enum('priority_level', ['normal', 'urgent'])->default('normal');
            $table->json('supporting_documents')->nullable();
            $table->date('requested_date');
            
            // Approval Workflow
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Deduction Schedule
            $table->enum('deduction_status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->enum('deduction_schedule', ['single_period', 'installments'])->default('installments');
            $table->integer('number_of_installments')->default(1);
            $table->integer('installments_completed')->default(0);
            $table->decimal('deduction_amount_per_period', 10, 2)->nullable();
            
            // Balance Tracking
            $table->decimal('total_deducted', 10, 2)->default(0);
            $table->decimal('remaining_balance', 10, 2)->nullable();
            
            // Completion
            $table->timestamp('completed_at')->nullable();
            $table->enum('completion_reason', ['fully_paid', 'employee_resignation', 'cancelled', 'written_off'])->nullable();
            
            // Audit
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('employee_id');
            $table->index('approval_status');
            $table->index('deduction_status');
            $table->index('requested_date');
            $table->index('advance_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_cash_advances');
    }
};
