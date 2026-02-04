<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Task 7.2.1: Add performance indexes to optimize database queries
     */
    public function up(): void
    {
        // Indexes for rfid_ledger table
        Schema::table('rfid_ledger', function (Blueprint $table) {
            // Composite index for date range queries with device filtering
            $table->index(['scan_timestamp', 'device_id'], 'idx_rfid_ledger_scan_timestamp_device');
            
            // Index for employee lookups
            $table->index('employee_rfid', 'idx_rfid_ledger_employee_rfid');
            
            // Index for unprocessed events (polling optimization)
            $table->index(['processed', 'sequence_id'], 'idx_rfid_ledger_processed_sequence');
            
            // Index for event type filtering
            $table->index('event_type', 'idx_rfid_ledger_event_type');
        });

        // Indexes for attendance_events table
        Schema::table('attendance_events', function (Blueprint $table) {
            // Composite index for employee date range queries
            $table->index(['employee_id', 'event_date'], 'idx_attendance_events_employee_date');
            
            // Index for source filtering
            $table->index('source', 'idx_attendance_events_source');
            
            // Index for corrected events
            $table->index('is_corrected', 'idx_attendance_events_corrected');
            
            // Index for ledger traceability
            $table->index('ledger_sequence_id', 'idx_attendance_events_ledger_seq');
        });

        // Indexes for daily_attendance_summary table  
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            // Composite index for employee date range queries (most common)
            $table->index(['employee_id', 'attendance_date'], 'idx_daily_summary_employee_date');
            
            // Index for date range queries
            $table->index('attendance_date', 'idx_daily_summary_attendance_date');
            
            // Index for status filtering
            $table->index(['is_present', 'is_late'], 'idx_daily_summary_status');
            
            // Index for finalized records (payroll queries)
            $table->index(['is_finalized', 'attendance_date'], 'idx_daily_summary_finalized_date');
            
            // Index for ledger verification status
            $table->index('ledger_verified', 'idx_daily_summary_ledger_verified');
            
            // Index for leave tracking
            $table->index('leave_request_id', 'idx_daily_summary_leave_request');
        });

        // Indexes for overtime_requests table
        Schema::table('overtime_requests', function (Blueprint $table) {
            // Composite index for employee date range queries
            $table->index(['employee_id', 'overtime_date'], 'idx_overtime_employee_date');
            
            // Index for status filtering
            $table->index('status', 'idx_overtime_status');
            
            // Index for approval queries
            $table->index(['status', 'overtime_date'], 'idx_overtime_status_date');
        });

        // Indexes for import_batches table
        Schema::table('import_batches', function (Blueprint $table) {
            // Index for status filtering
            $table->index('status', 'idx_import_batches_status');
            
            // Index for date range queries
            $table->index('imported_at', 'idx_import_batches_imported_at');
        });

        // Indexes for rfid_devices table
        Schema::table('rfid_devices', function (Blueprint $table) {
            // Index for status filtering
            $table->index('status', 'idx_rfid_devices_status');
            
            // Index for location queries
            $table->index('location', 'idx_rfid_devices_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop rfid_ledger indexes
        Schema::table('rfid_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_rfid_ledger_scan_timestamp_device');
            $table->dropIndex('idx_rfid_ledger_employee_rfid');
            $table->dropIndex('idx_rfid_ledger_processed_sequence');
            $table->dropIndex('idx_rfid_ledger_event_type');
        });

        // Drop attendance_events indexes
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_events_employee_date');
            $table->dropIndex('idx_attendance_events_source');
            $table->dropIndex('idx_attendance_events_corrected');
            $table->dropIndex('idx_attendance_events_ledger_seq');
        });

        // Drop daily_attendance_summary indexes
        Schema::table('daily_attendance_summary', function (Blueprint $table) {
            $table->dropIndex('idx_daily_summary_employee_date');
            $table->dropIndex('idx_daily_summary_attendance_date');
            $table->dropIndex('idx_daily_summary_status');
            $table->dropIndex('idx_daily_summary_finalized_date');
            $table->dropIndex('idx_daily_summary_ledger_verified');
            $table->dropIndex('idx_daily_summary_leave_request');
        });

        // Drop overtime_requests indexes
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex('idx_overtime_employee_date');
            $table->dropIndex('idx_overtime_status');
            $table->dropIndex('idx_overtime_status_date');
        });

        // Drop import_batches indexes
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex('idx_import_batches_status');
            $table->dropIndex('idx_import_batches_imported_at');
        });

        // Drop rfid_devices indexes
        Schema::table('rfid_devices', function (Blueprint $table) {
            $table->dropIndex('idx_rfid_devices_status');
            $table->dropIndex('idx_rfid_devices_location');
        });
    }
};
