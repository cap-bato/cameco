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
        if (Schema::hasTable('rfid_ledger')) {
            Schema::table('rfid_ledger', function (Blueprint $table) {
            // Composite index for date range queries with device filtering
            if (!Schema::hasIndex('rfid_ledger', 'idx_rfid_ledger_scan_timestamp_device')) {
                $table->index(['scan_timestamp', 'device_id'], 'idx_rfid_ledger_scan_timestamp_device');
            }
            
            // Index for employee lookups
            if (!Schema::hasIndex('rfid_ledger', 'idx_rfid_ledger_employee_rfid')) {
                $table->index('employee_rfid', 'idx_rfid_ledger_employee_rfid');
            }
            
            // Index for unprocessed events (polling optimization)
            if (!Schema::hasIndex('rfid_ledger', 'idx_rfid_ledger_processed_sequence')) {
                $table->index(['processed', 'sequence_id'], 'idx_rfid_ledger_processed_sequence');
            }
            
            // Index for event type filtering
            if (!Schema::hasIndex('rfid_ledger', 'idx_rfid_ledger_event_type')) {
                $table->index('event_type', 'idx_rfid_ledger_event_type');
            }
        });
        }

        // Indexes for attendance_events table
        if (Schema::hasTable('attendance_events')) {
            Schema::table('attendance_events', function (Blueprint $table) {
            // Composite index for employee date range queries
            if (!Schema::hasIndex('attendance_events', 'idx_attendance_events_employee_date')) {
                $table->index(['employee_id', 'event_date'], 'idx_attendance_events_employee_date');
            }
            
            // Index for source filtering
            if (!Schema::hasIndex('attendance_events', 'idx_attendance_events_source')) {
                $table->index('source', 'idx_attendance_events_source');
            }
            
            // Index for corrected events
            if (!Schema::hasIndex('attendance_events', 'idx_attendance_events_corrected')) {
                $table->index('is_corrected', 'idx_attendance_events_corrected');
            }
            
            // Index for ledger traceability
            if (!Schema::hasIndex('attendance_events', 'idx_attendance_events_ledger_seq')) {
                $table->index('ledger_sequence_id', 'idx_attendance_events_ledger_seq');
            }
        });
        }

        // Indexes for daily_attendance_summary table  
        if (Schema::hasTable('daily_attendance_summary')) {
            Schema::table('daily_attendance_summary', function (Blueprint $table) {
            // Composite index for employee date range queries (most common)
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_employee_date')) {
                $table->index(['employee_id', 'attendance_date'], 'idx_daily_summary_employee_date');
            }
            
            // Index for date range queries
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_attendance_date')) {
                $table->index('attendance_date', 'idx_daily_summary_attendance_date');
            }
            
            // Index for status filtering
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_status')) {
                $table->index(['is_present', 'is_late'], 'idx_daily_summary_status');
            }
            
            // Index for finalized records (payroll queries)
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_finalized_date')) {
                $table->index(['is_finalized', 'attendance_date'], 'idx_daily_summary_finalized_date');
            }
            
            // Index for ledger verification status
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_ledger_verified')) {
                $table->index('ledger_verified', 'idx_daily_summary_ledger_verified');
            }
            
            // Index for leave tracking
            if (!Schema::hasIndex('daily_attendance_summary', 'idx_daily_summary_leave_request')) {
                $table->index('leave_request_id', 'idx_daily_summary_leave_request');
            }
        });
        }

        // Indexes for overtime_requests table
        if (Schema::hasTable('overtime_requests')) {
            Schema::table('overtime_requests', function (Blueprint $table) {
            // Composite index for employee date range queries
            if (!Schema::hasIndex('overtime_requests', 'idx_overtime_employee_date')) {
                $table->index(['employee_id', 'overtime_date'], 'idx_overtime_employee_date');
            }
            
            // Index for status filtering
            if (!Schema::hasIndex('overtime_requests', 'idx_overtime_status')) {
                $table->index('status', 'idx_overtime_status');
            }
            
            // Index for approval queries
            if (!Schema::hasIndex('overtime_requests', 'idx_overtime_status_date')) {
                $table->index(['status', 'overtime_date'], 'idx_overtime_status_date');
            }
        });
        }

        // Indexes for import_batches table
        if (Schema::hasTable('import_batches')) {
            Schema::table('import_batches', function (Blueprint $table) {
            // Index for status filtering
            if (!Schema::hasIndex('import_batches', 'idx_import_batches_status')) {
                $table->index('status', 'idx_import_batches_status');
            }
            
            // Index for date range queries (use created_at instead of imported_at)
            if (!Schema::hasIndex('import_batches', 'idx_import_batches_created_at')) {
                $table->index('created_at', 'idx_import_batches_created_at');
            }
        });
        }

        // Indexes for rfid_devices table
        if (Schema::hasTable('rfid_devices')) {
            Schema::table('rfid_devices', function (Blueprint $table) {
            // Index for status filtering
            if (!Schema::hasIndex('rfid_devices', 'idx_rfid_devices_status')) {
                $table->index('status', 'idx_rfid_devices_status');
            }
            
            // Index for location queries
            if (!Schema::hasIndex('rfid_devices', 'idx_rfid_devices_location')) {
                $table->index('location', 'idx_rfid_devices_location');
            }
        });
        }
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
            $table->dropIndex('idx_import_batches_created_at');
        });

        // Drop rfid_devices indexes
        Schema::table('rfid_devices', function (Blueprint $table) {
            $table->dropIndex('idx_rfid_devices_status');
            $table->dropIndex('idx_rfid_devices_location');
        });
    }
};
