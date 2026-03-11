<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_policy_id')->constrained()->onDelete('restrict');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 5, 1);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            
            // Approval Workflow Fields
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->timestamp('supervisor_approved_at')->nullable();
            $table->text('supervisor_comments')->nullable();
            
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_comments')->nullable();
            
            // HR Processing Fields
            $table->foreignId('hr_processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('hr_processed_at')->nullable();
            $table->text('hr_notes')->nullable();
            
            // System Fields
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('employee_id');
            $table->index('status');
            $table->index('leave_policy_id');
            $table->index(['start_date', 'end_date']);
            $table->index('supervisor_id');
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};