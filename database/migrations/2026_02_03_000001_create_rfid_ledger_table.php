<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1.1: Create rfid_ledger table (PostgreSQL append-only ledger)
     * 
     * This table stores all RFID scan events in an append-only format with:
     * - Sequential ordering via sequence_id
     * - Cryptographic hash chains for tamper-evidence
     * - Optional device signatures for authenticity
     * - Processed flag for tracking ledger polling status
     */
    public function up(): void
    {
        Schema::create('rfid_ledger', function (Blueprint $table) {
            // Primary Key
            $table->bigIncrements('id');

            // Sequence & Ordering
            $table->bigInteger('sequence_id')->unique()->comment('Unique sequence number for ordering and replay');

            // Employee & Device Information
            $table->string('employee_rfid', 255)->comment('RFID card unique identifier');
            $table->string('device_id', 255)->comment('RFID scanner/edge device identifier');

            // Event Information
            $table->timestamp('scan_timestamp')->comment('Exact timestamp of RFID scan');
            $table->string('event_type', 50)->comment('Event type: time_in, time_out, break_start, break_end');

            // Raw Payload (JSON)
            $table->json('raw_payload')->comment('Complete event payload from RFID device');

            // Cryptographic Hash Chain
            $table->string('hash_chain', 255)->comment('SHA-256 hash of (prev_hash || payload) for tamper-evidence');
            $table->string('hash_previous', 255)->nullable()->comment('Hash of previous ledger entry (if any)');

            // Optional Device Signature
            $table->text('device_signature')->nullable()->comment('Optional Ed25519 device signature for origin verification');

            // Processing Status
            $table->boolean('processed')->default(false)->comment('Flag indicating ledger entry has been processed');
            $table->timestamp('processed_at')->nullable()->comment('Timestamp when this entry was processed');

            // Audit & Timestamps
            $table->timestamp('created_at')->useCurrent()->comment('Server creation timestamp');

            // Indexes for Performance
            $table->index('sequence_id', 'idx_rfid_ledger_sequence')->comment('Index for sequential ordering');
            $table->index('processed', 'idx_rfid_ledger_processed')->comment('Index for polling unprocessed events');
            $table->index('employee_rfid', 'idx_rfid_ledger_employee')->comment('Index for employee event lookups');
            $table->index('device_id', 'idx_rfid_ledger_device')->comment('Index for device event lookups');
            $table->index('scan_timestamp', 'idx_rfid_ledger_scan_timestamp')->comment('Index for timestamp range queries');
            $table->index(['employee_rfid', 'scan_timestamp'], 'idx_rfid_ledger_employee_timestamp')->comment('Combined index for employee timeline queries');

            // Comments
            $table->comment('Append-only RFID ledger: tamper-resistant event log populated by FastAPI RFID server');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfid_ledger');
    }
};
