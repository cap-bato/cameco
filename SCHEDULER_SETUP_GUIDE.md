# Quick Start Guide: RFID Ledger Processing Scheduler

## Phase 6, Task 6.1 Implementation Complete

All subtasks (6.1.1, 6.1.2, 6.1.3) have been successfully implemented.

---

## 📋 What Was Implemented

### 1. ProcessRfidLedgerJob (Task 6.1.1, 6.1.3)
**File:** `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php`

**Features:**
- ✅ Calls `LedgerPollingService` to process RFID events
- ✅ Validates hash chain integrity
- ✅ Deduplicates events (15-second window)
- ✅ Creates attendance records
- ✅ Retry logic: 3 attempts with exponential backoff (1m, 2m, 5m)
- ✅ Failure notifications to HR Managers
- ✅ Hash chain validation alerts (critical)
- ✅ Sequence gap detection (warning)
- ✅ Processing metrics logging

### 2. Scheduler Configuration (Task 6.1.2)
**File:** `routes/console.php`

**Scheduled Jobs:**
```php
// Main RFID ledger processing - Every 1 minute
Schedule::job(new ProcessRfidLedgerJob())
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Supporting jobs
Schedule::command('timekeeping:cleanup-deduplication-cache')->everyFiveMinutes();
Schedule::command('timekeeping:generate-daily-summaries')->dailyAt('23:59');
Schedule::command('timekeeping:check-device-health')->everyTwoMinutes();
```

### 3. Failure Notifications (Task 6.1.3)
**File:** `app/Notifications/LedgerProcessingFailedNotification.php`

**Notification Channels:**
- Database notifications (all errors/warnings)
- Email notifications (critical failures only)
- Sent to all users with "HR Manager" role

### 4. Supporting Commands
- `CleanupDeduplicationCacheCommand.php` - Removes expired cache entries
- `GenerateDailySummariesCommand.php` - Daily attendance summaries
- `CheckDeviceHealthCommand.php` - Monitor device online/offline status

---

## 🚀 How to Run

### Option 1: Development (Local Testing)

```bash
# Start Laravel scheduler (keeps running)
php artisan schedule:work

# In another terminal, start queue worker
php artisan queue:work

# Test job manually
php artisan tinker
>>> dispatch(new \App\Jobs\Timekeeping\ProcessRfidLedgerJob());
```

### Option 2: Production Setup

**Step 1:** Add to crontab

```bash
crontab -e
```

Add this line:
```
* * * * * cd /path/to/cameco && php artisan schedule:run >> /dev/null 2>&1
```

**Step 2:** Start queue worker (using supervisor)

Create `/etc/supervisor/conf.d/cameco-worker.conf`:

```ini
[program:cameco-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/cameco/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/cameco/storage/logs/worker.log
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cameco-worker:*
```

---

## 🧪 Testing

### Run Unit Tests

```bash
# Test ProcessRfidLedgerJob
php artisan test --filter ProcessRfidLedgerJobTest

# Test LedgerPollingService (already passing)
php artisan test --filter LedgerPollingServiceTest
```

### Manual Testing

```bash
# 1. Verify scheduler recognizes the job
php artisan schedule:list

# Expected output:
# 0 * * * *  process-rfid-ledger ............... Next Due: 1 minute from now

# 2. Run job manually (dry run)
php artisan queue:work --once

# 3. Check logs
tail -f storage/logs/laravel.log | grep ProcessRfidLedgerJob

# 4. Test device health check
php artisan timekeeping:check-device-health

# 5. Test daily summaries
php artisan timekeeping:generate-daily-summaries --date=2026-02-04
```

### Simulate Failure

```bash
# In tinker, mock a failure scenario
php artisan tinker

>>> use App\Jobs\Timekeeping\ProcessRfidLedgerJob;
>>> $job = new ProcessRfidLedgerJob();
>>> $job->failed(new \Exception("Test failure"));

# Check if notification was sent to HR Managers
>>> \App\Models\User::role('HR Manager')->first()->notifications;
```

---

## 📊 Monitoring

### View Job Logs

```bash
# Real-time monitoring
tail -f storage/logs/laravel.log | grep "\[ProcessRfidLedgerJob\]"

# Search for failures
grep "ProcessRfidLedgerJob.*failed" storage/logs/laravel.log
```

### Check Job Metrics

Logs include:
- `events_polled`: Number of unprocessed events fetched
- `events_deduplicated`: Duplicates filtered out
- `events_created`: New attendance records created
- `processing_time_ms`: Job execution time
- `hash_chain_valid`: Integrity check status
- `sequence_gaps`: Missing sequence IDs

### Database Notifications

```sql
-- View recent notifications for HR Managers
SELECT u.name, n.type, n.data->>'message' as message, n.created_at
FROM users u
JOIN notifications n ON n.notifiable_id = u.id
WHERE n.type = 'timekeeping.ledger.failure'
ORDER BY n.created_at DESC
LIMIT 10;
```

---

## 🔧 Configuration

### Environment Variables

```env
# Queue Configuration
QUEUE_CONNECTION=database  # or redis for production

# Scheduler Settings (optional)
SCHEDULER_CACHE_DRIVER=database
SCHEDULER_TIMEZONE=Asia/Manila

# Mail Settings (for critical notifications)
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@cathay-metal.com
MAIL_FROM_NAME="Cathay Metal HRIS"
```

### Job Retry Configuration

Edit `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php`:

```php
public $tries = 3;              // Number of retry attempts
public $backoff = [60, 120, 300]; // Backoff delays (seconds)
public $maxExceptions = 3;      // Max exceptions before failure
```

---

## ⚠️ Troubleshooting

### Job Not Running?

**Problem:** Cron not executing
```bash
# Check cron service
sudo systemctl status cron

# Check cron logs
grep CRON /var/log/syslog

# Verify crontab entry
crontab -l
```

**Problem:** Queue not processing
```bash
# Check queue worker status
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### High Failure Rate?

1. **Check database connection:**
   ```bash
   php artisan tinker
   >>> \DB::connection()->getPdo();
   ```

2. **Verify rfid_ledger table:**
   ```sql
   SELECT COUNT(*) FROM rfid_ledger WHERE processed = FALSE;
   ```

3. **Check LedgerPollingService:**
   ```bash
   php artisan tinker
   >>> $service = new \App\Services\Timekeeping\LedgerPollingService();
   >>> $events = $service->pollNewEvents(10);
   >>> $events->count();
   ```

### Hash Chain Validation Failures?

**🔴 CRITICAL ALERT**

This indicates possible tampering or data corruption!

**Immediate Actions:**
1. View Ledger Health Dashboard: `/hr/timekeeping/ledger/health`
2. Review failed sequence IDs in logs
3. Do NOT approve payroll until resolved
4. Contact system administrator
5. Run integrity audit:
   ```bash
   php artisan tinekeeping:audit-ledger-integrity
   ```

---

## � Payroll Queue Worker Setup

The payroll calculation pipeline dispatches jobs to the `payroll` queue. These must be processed by a dedicated queue worker.

### Queue

| Queue name | Used by |
|---|---|
| `payroll` | `CalculatePayrollJob`, `CalculateEmployeePayrollJob`, `FinalizePayrollJob` |
| `default` | Everything else |

### Development

Start in a second terminal alongside `php artisan serve`:

```bash
php artisan queue:work --queue=payroll,default --tries=3 --timeout=120
```

Options explained:
- `--queue=payroll,default` — processes payroll jobs first, then falls back to default
- `--tries=3` — retries failed jobs up to 3 times before marking as failed
- `--timeout=120` — kills a job that runs longer than 120 seconds (prevents stalls)

Verify the worker sees queued jobs:

```bash
# Check how many jobs are pending
php artisan queue:monitor payroll
```

### Production (Supervisor)

Create `/etc/supervisor/conf.d/cameco-payroll-worker.conf`:

```ini
[program:cameco-payroll-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cameco/artisan queue:work --queue=payroll,default --tries=3 --timeout=120 --sleep=3 --max-jobs=500
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/cameco/storage/logs/payroll-worker.log
stopwaitsecs=130
```

Then run:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cameco-payroll-worker:*
sudo supervisorctl status
```

### Handling Failed Jobs

```bash
# View failed payroll jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Retry a specific job by ID
php artisan queue:retry <uuid>

# Clear all failed jobs
php artisan queue:flush
```

### Environment Variable

```env
QUEUE_CONNECTION=database   # must be set in .env (already confirmed)
```

---

## �📚 Related Documentation

- [Timekeeping RFID Integration Implementation](../../docs/issues/TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md)
- [FastAPI RFID Server Implementation](../../docs/issues/FASTAPI_RFID_SERVER_IMPLEMENTATION.md)
- [LedgerPollingService README](../Services/Timekeeping/README.md)
- [Jobs Directory README](./README.md)

---

## ✅ Acceptance Criteria Met

- [x] **6.1.1:** `handle()` method calls `LedgerPollingService` ✅
- [x] **6.1.2:** Configured to run every 1 minute via Laravel Scheduler ✅
- [x] **6.1.3:** Retry logic and failure notifications implemented ✅
- [x] Job runs automatically every minute ✅
- [x] Failures trigger alerts to HR Managers ✅
- [x] Processing metrics logged ✅
- [x] Hash chain validation enforced ✅
- [x] Device health monitoring active ✅

---

**Status:** 🟢 READY FOR DEPLOYMENT  
**Last Updated:** February 4, 2026  
**Implemented By:** AI Assistant
