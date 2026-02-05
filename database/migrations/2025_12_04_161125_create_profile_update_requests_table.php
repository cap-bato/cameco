<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates profile_update_requests table to track employee-initiated contact info updates
     * that require HR Staff approval before applying.
     */
    public function up(): void
    {
        Schema::create('profile_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade')->comment('Employee who requested the update');
            $table->string('field_name', 100)->comment('Field being updated (e.g., contact_number, email, address)');
            $table->text('old_value')->nullable()->comment('Previous field value');
            $table->text('new_value')->comment('New requested field value');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Request approval status');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null')->comment('HR Staff who reviewed the request');
            $table->timestamp('reviewed_at')->nullable()->comment('Timestamp when request was reviewed');
            $table->text('rejection_reason')->nullable()->comment('Reason for rejection if status is rejected');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('employee_id');
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
        Schema::dropIfExists('profile_update_requests');
    }
};
