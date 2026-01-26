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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            // Template information
            $table->string('name'); // e.g., "Employment Contract", "Certificate of Employment"
            $table->text('description')->nullable();
            $table->enum('template_type', [
                'contract',
                'offer_letter',
                'coe',
                'memo',
                'warning',
                'clearance',
                'resignation',
                'termination',
                'other'
            ])->index();
            
            // Template file and content
            $table->string('file_path'); // storage/app/templates/{id}/{filename}
            $table->json('variables')->nullable(); // e.g., ["{{employee_name}}", "{{position}}", "{{start_date}}"]
            
            // Version control
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_locked')->default(false); // Lock after approval
            $table->boolean('is_active')->default(true)->index();
            
            // Status
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'archived'
            ])->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['template_type', 'is_active']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
