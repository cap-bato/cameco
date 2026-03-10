<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1.2: Create attendance_events table with ledger linking columns
     * 
     * This table stores processed attendance events derived from the rfid_ledger.
     * It includes:
     * - ledger_sequence_id: Links to rfid_ledger.sequence_id for traceability
     * - is_deduplicated: Flag indicating duplicate tap handling
     * - ledger_hash_verified: Flag indicating hash chain validation passed
     */
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Employee Reference
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');

            // Event Information
            $table->date('event_date')->comment('Date of attendance event');
            $table->timestamp('event_time')->comment('Exact timestamp of event');
            $table->enum('event_type', [
                'time_in',
                'time_out',
                'break_start',
                'break_end',
                'overtime_start',
                'overtime_end'
            ])->comment('Type of attendance event');

            // Ledger Linkage (Task 5.1.2)
            $table->bigInteger('ledger_sequence_id')->nullable()->unique()->comment('Reference to rfid_ledger.sequence_id for traceability');
            $table->boolean('is_deduplicated')->default(false)->comment('Flag indicating duplicate detection and handling');
            $table->boolean('ledger_hash_verified')->default(false)->comment('Flag indicating SHA-256 hash chain validation passed');

            // Data Source
            $table->enum('source', ['edge_machine', 'manual', 'imported'])->default('manual')->comment('Source of attendance data: RFID scanner, manual entry, or bulk import');
            $table->foreignId('imported_batch_id')->nullable()->constrained('import_batches')->onDelete('set null')->comment('Reference to import batch if imported');

            // Validation & Correction
            $table->boolean('is_corrected')->default(false)->comment('Flag indicating event has been corrected');
            $table->timestamp('original_time')->nullable()->comment('Original timestamp before correction');
            $table->text('correction_reason')->nullable()->comment('Reason for correction');
            $table->foreignId('corrected_by')->nullable()->constrained('users')->onDelete('set null')->comment('User who performed correction');
            $table->timestamp('corrected_at')->nullable()->comment('Timestamp of correction');

            // Location & Device Info
            $table->string('device_id', 255)->nullable()->comment('RFID device/scanner identifier');
            $table->string('location', 255)->nullable()->comment('Physical location of attendance event');
            $table->text('notes')->nullable()->comment('Additional notes about the event');

            // Raw Ledger Payload (for audit trail)
            $table->json('ledger_raw_payload')->nullable()->comment('Copy of raw_payload from rfid_ledger for audit purposes');

            // Audit & Timestamps
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('User who created the record (for manual entries)');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexes for Performance
            $table->index(['employee_id', 'event_date'], 'idx_attendance_events_employee_date');
            $table->index('event_time', 'idx_attendance_events_event_time');
            $table->index('ledger_sequence_id', 'idx_attendance_events_ledger_sequence');
            $table->index('imported_batch_id', 'idx_attendance_events_batch_id');
            $table->index('source', 'idx_attendance_events_source');
            $table->index('is_deduplicated', 'idx_attendance_events_deduplicated');
            $table->index('ledger_hash_verified', 'idx_attendance_events_hash_verified');

            // Unique Constraints
            $table->unique('ledger_sequence_id', 'uq_attendance_events_ledger_sequence');

            // Comments
            $table->comment('Processed attendance events derived from rfid_ledger with ledger traceability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
