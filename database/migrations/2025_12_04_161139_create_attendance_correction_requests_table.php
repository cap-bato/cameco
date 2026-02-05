<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates attendance_correction_requests table to track employee-reported attendance issues
     * (missing punches, wrong times) that require HR Staff verification and correction.
     */
    public function up(): void
    {
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade')->comment('Employee reporting the issue');
            $table->date('attendance_date')->comment('Date of attendance issue');
            $table->enum('issue_type', ['missing_punch', 'wrong_time', 'other'])->comment('Type of attendance issue reported');
            $table->time('actual_time_in')->nullable()->comment('Actual time in (employee claims)');
            $table->time('actual_time_out')->nullable()->comment('Actual time out (employee claims)');
            $table->text('reason')->comment('Explanation of the issue and why it occurred');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Request approval status');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null')->comment('HR Staff who reviewed the request');
            $table->timestamp('reviewed_at')->nullable()->comment('Timestamp when request was reviewed');
            $table->text('rejection_reason')->nullable()->comment('Reason for rejection if status is rejected');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('employee_id');
            $table->index('attendance_date');
            $table->index('status');
            $table->index('reviewed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
