<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Phase 1, Task 1.1: Create overtime_requests table
     * For tracking employee overtime requests with approval workflow
     */
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            // Primary Key
            $table->id();
            
            // Employee Reference
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade')
                ->comment('Reference to employee requesting overtime');
            
            // Request Information
            $table->date('request_date')->comment('Date of overtime request');
            $table->timestamp('planned_start_time')->comment('Planned overtime start time');
            $table->timestamp('planned_end_time')->comment('Planned overtime end time');
            $table->decimal('planned_hours', 5, 2)->comment('Planned overtime hours');
            $table->text('reason')->comment('Reason for overtime request');
            
            // Approval Workflow
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])
                ->default('pending')
                ->comment('Request status');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who approved/rejected');
            $table->timestamp('approved_at')->nullable()->comment('Approval timestamp');
            $table->text('rejection_reason')->nullable()->comment('Reason for rejection');
            
            // Actual Time Tracking
            $table->timestamp('actual_start_time')->nullable()->comment('Actual overtime start');
            $table->timestamp('actual_end_time')->nullable()->comment('Actual overtime end');
            $table->decimal('actual_hours', 5, 2)->nullable()->comment('Actual overtime hours');
            
            // Audit Fields
            $table->foreignId('created_by')
                ->constrained('users')
                ->comment('User who created the request');
            $table->timestamps();
            
            // Indexes for Performance
            $table->index('employee_id', 'idx_overtime_requests_employee_id');
            $table->index('status', 'idx_overtime_requests_status');
            $table->index('request_date', 'idx_overtime_requests_request_date');
            $table->index(['employee_id', 'request_date'], 'idx_overtime_employee_date');
            
            // Table Comment
            $table->comment('Overtime requests with approval workflow and time tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
