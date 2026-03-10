<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1.4: Create ledger_health_logs table
     * 
     * This table tracks integrity checks, health metrics, and replay operations
     * on the RFID ledger. Used for monitoring ledger health and detecting tampering.
     */
    public function up(): void
    {
        Schema::create('ledger_health_logs', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Check Metadata
            $table->timestamp('check_timestamp')->comment('When this health check was performed');
            $table->bigInteger('last_sequence_id')->comment('Highest sequence_id in ledger at check time');

            // Gap Detection
            $table->boolean('gaps_detected')->default(false)->comment('Whether sequence gaps were found');
            $table->json('gap_details')->nullable()->comment('Details of detected gaps: {missing_ranges: [[start, end], ...]}');

            // Hash Verification
            $table->boolean('hash_failures')->default(false)->comment('Whether hash chain validation failed');
            $table->json('hash_failure_details')->nullable()->comment('Details of failed hashes: {failed_sequences: [id, ...], reasons: [...]}');

            // Processing Status
            $table->boolean('replay_triggered')->default(false)->comment('Whether automatic replay was triggered');
            $table->bigInteger('total_unprocessed')->default(0)->comment('Count of unprocessed ledger entries');
            $table->bigInteger('processing_queue_size')->default(0)->comment('Current size of processing queue');
            $table->decimal('processing_lag_seconds', 8, 2)->nullable()->comment('Lag between latest ledger entry and now');

            // Health Status
            $table->enum('status', ['healthy', 'warning', 'critical'])->comment('Overall ledger health status');

            // Thresholds Used for Status Determination
            $table->unsignedInteger('gap_count')->default(0)->comment('Number of gaps detected');
            $table->unsignedInteger('hash_failure_count')->default(0)->comment('Number of hash failures');
            $table->unsignedInteger('duplicate_count')->default(0)->comment('Number of duplicate events detected');

            // Optional Notes
            $table->text('notes')->nullable()->comment('Human-readable summary of health check results');
            $table->text('recommendations')->nullable()->comment('Recommended actions if health is degraded');

            // Audit & Timestamps
            $table->timestamp('created_at')->useCurrent()->comment('When this log entry was created');

            // Indexes for Performance
            $table->index('check_timestamp', 'idx_ledger_health_check_timestamp');
            $table->index('status', 'idx_ledger_health_status');
            $table->index('last_sequence_id', 'idx_ledger_health_sequence');
            $table->index(['check_timestamp', 'status'], 'idx_ledger_health_timestamp_status');
            $table->index('gaps_detected', 'idx_ledger_health_gaps');
            $table->index('hash_failures', 'idx_ledger_health_failures');
            $table->index('replay_triggered', 'idx_ledger_health_replay');

            // Comments
            $table->comment('Ledger integrity health checks: tracks gaps, hash failures, and replay status (Task 5.1.4)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_health_logs');
    }
};
