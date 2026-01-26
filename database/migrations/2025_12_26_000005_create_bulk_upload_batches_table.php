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
        Schema::create('bulk_upload_batches', function (Blueprint $table) {
            $table->id();
            
            // Foreign key
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict');
            
            // Batch information
            $table->enum('status', [
                'processing',
                'completed',
                'failed',
                'partially_completed'
            ])->default('processing')->index();
            
            // Processing statistics
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            
            // File information
            $table->string('csv_file_path')->nullable(); // Path to uploaded CSV file
            $table->json('error_log')->nullable(); // Detailed error information for each failed row
            
            // Batch details
            $table->text('notes')->nullable(); // HR notes about the bulk upload
            
            // Timing
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index(['uploaded_by', 'status']);
            $table->index('started_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_batches');
    }
};
