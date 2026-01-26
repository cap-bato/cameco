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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            // Document information
            $table->enum('document_category', [
                'personal',
                'educational',
                'employment',
                'medical',
                'contracts',
                'benefits',
                'performance',
                'separation',
                'government',
                'special'
            ])->index();
            $table->string('document_type'); // e.g., "Birth Certificate", "NBI Clearance"
            $table->string('file_name');
            $table->string('file_path'); // storage/app/employee-documents/{id}/{category}/{year}/{filename}
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('mime_type'); // e.g., "application/pdf", "image/jpeg"
            
            // Document status
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'auto_approved'
            ])->default('pending')->index();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_critical')->default(false); // Critical docs need HR Manager approval
            
            // Approval workflow
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            
            // Expiry tracking
            $table->date('expires_at')->nullable()->index();
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Bulk upload tracking
            $table->foreignId('bulk_upload_batch_id')
                ->nullable()
                ->constrained('bulk_upload_batches')
                ->onDelete('set null');
            $table->enum('source', [
                'manual',
                'bulk',
                'employee_portal'
            ])->default('manual');
            
            // Timestamps
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('retention_expires_at')->nullable(); // 5 years after employee separation
            
            // Indexes
            $table->index(['employee_id', 'document_category']);
            $table->index(['document_type', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('bulk_upload_batch_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
