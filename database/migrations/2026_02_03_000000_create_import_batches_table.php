<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1: Create import_batches table (required for attendance_events foreign key)
     * 
     * This table tracks bulk file imports of attendance data (CSV/Excel files).
     */
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // File Information
            $table->string('file_name', 255)->comment('Name of imported file');
            $table->string('file_path', 500)->comment('Path where file is stored');
            $table->unsignedInteger('file_size')->comment('Size of file in bytes');

            // Import Details
            $table->enum('import_type', ['attendance', 'schedule', 'correction'])->comment('Type of import: attendance, schedule, or correction');
            $table->unsignedInteger('total_records')->comment('Total number of records in file');
            $table->unsignedInteger('processed_records')->default(0)->comment('Number of records processed');
            $table->unsignedInteger('successful_records')->default(0)->comment('Number of records successfully imported');
            $table->unsignedInteger('failed_records')->default(0)->comment('Number of records that failed');

            // Processing Status
            $table->enum('status', ['uploaded', 'processing', 'completed', 'failed'])->default('uploaded')->comment('Current processing status of import');
            $table->timestamp('started_at')->nullable()->comment('When processing started');
            $table->timestamp('completed_at')->nullable()->comment('When processing completed');
            $table->text('error_log')->nullable()->comment('Log of errors encountered during processing');

            // Audit & Timestamps
            $table->foreignId('imported_by')->constrained('users')->comment('User who initiated the import');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexes for Performance
            $table->index('status', 'idx_import_batches_status');
            $table->index('imported_by', 'idx_import_batches_imported_by');

            // Comments
            $table->comment('Tracks bulk imports of attendance data from files');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
