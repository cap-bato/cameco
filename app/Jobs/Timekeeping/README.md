# RFID Ledger Processing - Scheduled Jobs

This directory contains scheduled jobs for processing RFID attendance events.

## Main Job: ProcessRfidLedgerJob

**Schedule:** Every 1 minute  
**Purpose:** Polls the RFID ledger for new events and processes them into attendance records

### What It Does

1. Polls unprocessed events from `rfid_ledger` table
2. Validates hash chain integrity
3. Deduplicates events (15-second window)
4. Creates `attendance_events` records
5. Marks ledger entries as processed
6. Logs processing metrics
7. Sends alerts on failures

### Retry Logic

- **Max Attempts:** 3
- **Backoff:** Exponential (1 min, 2 min, 5 min)
- **Failure Handling:** Notifies HR Managers via database + email

### Monitoring

The job logs detailed metrics on each run:
```
events_polled: 125
events_deduplicated: 3
events_created: 122
processing_time_ms: 245
hash_chain_valid: true
sequence_gaps: []
```

## Running the Scheduler

### Development

```bash
# Run scheduler locally (keeps running, checks every minute)
php artisan schedule:work

# Or run specific job manually
php artisan queue:work --once
```

### Production

Add to crontab (runs Laravel scheduler every minute):

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Testing Individual Commands

```bash
# Test ledger processing manually
php artisan queue:work --once

# Check device health
php artisan timekeeping:check-device-health

# Generate daily summaries
php artisan timekeeping:generate-daily-summaries

# Cleanup deduplication cache
php artisan timekeeping:cleanup-deduplication-cache

# Generate summaries for specific date
php artisan timekeeping:generate-daily-summaries --date=2026-02-03
```

## Queue Configuration

Ensure your `.env` has proper queue configuration:

```env
QUEUE_CONNECTION=database  # or redis, sync for testing

# For database queue (recommended)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cameco
```

Run queue worker in production:

```bash
# Start queue worker
php artisan queue:work --tries=3 --timeout=90

# Or use supervisor/systemd for production
```

## Notifications

Failures trigger notifications to users with "HR Manager" role:

- **Database Notifications:** All failures/warnings
- **Email Notifications:** Critical failures only

View notifications at: `/notifications` (if notification panel is enabled)

## Troubleshooting

### Job not running?

1. Check cron is configured: `crontab -l`
2. Check queue connection: `php artisan queue:work --once`
3. Check logs: `tail -f storage/logs/laravel.log | grep ProcessRfidLedgerJob`

### High failure rate?

1. Check database connection to PostgreSQL
2. Verify `rfid_ledger` table exists and has data
3. Check `LedgerPollingService` is working: `php artisan tinker`
   ```php
   $service = new App\Services\Timekeeping\LedgerPollingService();
   $events = $service->pollNewEvents(10);
   dd($events->count());
   ```

### Hash chain validation failures?

**Critical:** This indicates possible tampering or data corruption.

1. Check ledger integrity: View [Ledger Health Dashboard](/hr/timekeeping/ledger/health)
2. Review hash chain validation logs
3. Do NOT approve payroll until resolved
4. Contact system administrator

## Supporting Scheduled Jobs

### Cleanup Deduplication Cache
- **Schedule:** Every 5 minutes
- **Purpose:** Remove expired deduplication entries (>1 hour old)

### Generate Daily Summaries
- **Schedule:** Daily at 11:59 PM (Asia/Manila)
- **Purpose:** Compute `daily_attendance_summary` for all active employees

### Check Device Health
- **Schedule:** Every 2 minutes
- **Purpose:** Detect offline RFID devices (>10 min threshold)
- **Alert:** Notifies HR Managers if devices offline >30 minutes

## Integration with FastAPI RFID Server

The job processes events written by the FastAPI RFID server:

```
FastAPI Server → rfid_ledger (PostgreSQL)
       ↓
ProcessRfidLedgerJob (every 1 min)
       ↓
attendance_events → daily_attendance_summary
       ↓
Payroll / Appraisal Modules
```

## Performance Targets

- **Processing Lag:** < 2 minutes (95th percentile)
- **Job Execution Time:** < 500ms for 1000 events
- **Failure Rate:** < 0.1%
- **Hash Chain Validation:** 100% pass rate

## Related Documentation

- [Timekeeping RFID Integration Implementation](../../docs/issues/TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md)
- [LedgerPollingService](../Services/Timekeeping/LedgerPollingService.php)
- [FastAPI RFID Server Implementation](../../docs/issues/FASTAPI_RFID_SERVER_IMPLEMENTATION.md)
