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
        Schema::create('offboarding_cases', function (Blueprint $table) {
            $table->id();

            // Employee Info
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number', 50)->unique();

            // Separation Details
            $table->enum('separation_type', [
                'resignation',
                'termination',
                'retirement',
                'end_of_contract',
                'death',
                'abscondment'
            ]);
            $table->text('separation_reason')->nullable();
            $table->date('last_working_day');
            $table->integer('notice_period_days')->nullable();

            // Status Tracking
            $table->enum('status', [
                'pending',
                'in_progress',
                'clearance_pending',
                'completed',
                'cancelled'
            ])->default('pending');

            // Workflow Stages
            $table->timestamp('resignation_submitted_at')->nullable();
            $table->timestamp('clearance_started_at')->nullable();
            $table->timestamp('exit_interview_completed_at')->nullable();
            $table->timestamp('all_clearances_approved_at')->nullable();
            $table->timestamp('final_documents_generated_at')->nullable();
            $table->timestamp('account_deactivated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // HR Actions
            $table->foreignId('hr_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('rehire_eligible')->nullable();
            $table->text('rehire_eligibility_reason')->nullable();
            $table->boolean('final_pay_computed')->default(false);
            $table->boolean('final_documents_issued')->default(false);

            // Notes
            $table->longText('internal_notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('status');
            $table->index('separation_type');
            $table->index('last_working_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offboarding_cases');
    }
};
