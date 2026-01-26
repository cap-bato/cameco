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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            // Request information
            $table->string('document_type'); // e.g., "Certificate of Employment", "Payslip"
            $table->text('purpose')->nullable(); // Why the employee is requesting the document
            $table->enum('request_source', [
                'employee_portal',
                'manual',
                'email'
            ])->default('employee_portal')->index();
            
            // Request status
            $table->enum('status', [
                'pending',
                'processed',
                'rejected'
            ])->default('pending')->index();
            
            // Processing information
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->string('file_path')->nullable(); // Path to generated/uploaded document
            $table->text('notes')->nullable(); // HR notes about the request
            $table->text('rejection_reason')->nullable(); // Why request was rejected
            
            // Employee notification
            $table->timestamp('employee_notified_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['employee_id', 'status']);
            $table->index(['document_type', 'status']);
            $table->index('requested_at');
            $table->index('processed_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
