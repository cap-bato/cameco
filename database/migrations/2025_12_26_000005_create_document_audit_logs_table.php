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
        Schema::create('document_audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('document_id')
                ->constrained('employee_documents')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict');
            
            // Audit information
            $table->enum('action', [
                'uploaded',
                'downloaded',
                'approved',
                'rejected',
                'deleted',
                'bulk_uploaded',
                'reminder_sent',
                'viewed',
                'restored'
            ])->index();
            
            // Request details
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context (e.g., reason for action)
            
            // Timestamp
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['document_id', 'action']);
            $table->index(['user_id', 'action']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_audit_logs');
    }
};
