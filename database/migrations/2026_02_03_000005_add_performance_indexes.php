<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 5.1.5: Add indexes for performance optimization
     * 
     * This migration creates performance-critical indexes across all
     * timekeeping tables to optimize query performance for common operations.
     */
    public function up(): void
    {
        // Additional indexes for rfid_ledger (beyond those in initial migration)
        Schema::table('rfid_ledger', function (Blueprint $table) {
            // Already has indexes from 2026_02_03_000001, but add composite index for common queries
            $table->index(['processed', 'created_at'], 'idx_rfid_ledger_processed_created');
            $table->index(['device_id', 'scan_timestamp'], 'idx_rfid_ledger_device_timestamp');
            $table->index('event_type', 'idx_rfid_ledger_event_type');
        });

        // Additional indexes for attendance_events (beyond those in initial migration)
        Schema::table('attendance_events', function (Blueprint $table) {
            // Already has indexes from 2026_02_03_000002, but add more for filtering
            $table->index(['employee_id', 'source'], 'idx_attendance_events_employee_source');
            $table->index(['is_corrected', 'event_date'], 'idx_attendance_events_corrected_date');
            $table->index(['source', 'created_at'], 'idx_attendance_events_source_created');
            $table->index('device_id', 'idx_attendance_events_device');
        });

        // Additional indexes for daily_attendance_summary (already created with many indexes)
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            // Composite index for common queries
            $table->index(['employee_id', 'is_finalized', 'attendance_date'], 'idx_daily_attendance_employee_finalized_date');
            $table->index('leave_request_id', 'idx_daily_attendance_leave_request');
        });

        // Indexes for import_batches (beyond those in initial migration)
        Schema::table('import_batches', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_import_batches_status_created');
            $table->index('import_type', 'idx_import_batches_type');
        });

        // Indexes for ledger_health_logs (mostly handled in creation, but add composite)
        Schema::table('ledger_health_logs', function (Blueprint $table) {
            $table->index(['status', 'check_timestamp'], 'idx_ledger_health_status_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfid_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_rfid_ledger_processed_created');
            $table->dropIndex('idx_rfid_ledger_device_timestamp');
            $table->dropIndex('idx_rfid_ledger_event_type');
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_events_employee_source');
            $table->dropIndex('idx_attendance_events_corrected_date');
            $table->dropIndex('idx_attendance_events_source_created');
            $table->dropIndex('idx_attendance_events_device');
        });

        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            $table->dropIndex('idx_daily_attendance_employee_finalized_date');
            $table->dropIndex('idx_daily_attendance_leave_request');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex('idx_import_batches_status_created');
            $table->dropIndex('idx_import_batches_type');
        });

        Schema::table('ledger_health_logs', function (Blueprint $table) {
            $table->dropIndex('idx_ledger_health_status_timestamp');
        });
    }
};
