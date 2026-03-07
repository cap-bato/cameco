# Timekeeping → Payroll: Full Flow Implementation Plan

**Date:** 2026-03-06  
**Scope:** Identify and fix every gap between RFID scan and a populated `employee_payroll_calculations` row.

---

## 1. Full Pipeline Diagram

```
[RFID Reader]
    │ (HTTP POST from FastAPI RFID server)
    ▼
[rfid_ledger] table
    │ append-only, immutable, SHA-256 hash-chained
    │
    ▼  every 1 min via scheduler
ProcessRfidLedgerJob
    │ app/Jobs/Timekeeping/ProcessRfidLedgerJob.php
    │ calls LedgerPollingService::processLedgerEventsComplete()
    │
    ▼
LedgerPollingService
    │ app/Services/Timekeeping/LedgerPollingService.php
    │ 1. pollNewEvents()          — fetch unprocessed rfid_ledger rows
    │ 2. validateHashChain()      — SHA-256 integrity check
    │ 3. deduplicateEvents()      — dedupe within 15-second window
    │ 4. creates AttendanceEvent  — one row per valid scan
    │ 5. marks rfid_ledger.processed = true
    │
    ▼
[attendance_events] table
    │ app/Models/AttendanceEvent.php
    │ fields: employee_id, event_date, event_time, event_type (time_in/time_out/…)
    │
    ▼  daily at 23:59 via scheduler OR manually
GenerateDailySummariesCommand
    │ app/Console/Commands/Timekeeping/GenerateDailySummariesCommand.php
    │ artisan timekeeping:generate-daily-summaries [--date=YYYY-MM-DD] [--force]
    │ calls AttendanceSummaryService for every active employee
    │
    ▼
AttendanceSummaryService::computeDailySummary() → applyBusinessRules() → storeDailySummary()
    │ app/Services/Timekeeping/AttendanceSummaryService.php
    │ aggregates attendance_events per employee per day
    │ applies late/overtime/undertime rules
    │ writes/upserts to daily_attendance_summary
    │ NOTE: is_finalized is NOT set here — defaults to false
    │
    ▼
[daily_attendance_summary] table  (is_finalized = FALSE)
    │
    ▼  *** MISSING STEP — see Section 3 ***
FinalizeAttendanceForPeriodCommand  (TO BE CREATED)
    │ artisan timekeeping:finalize-attendance --from=2026-03-01 --to=2026-03-15
    │ sets is_finalized = true for all rows in date range
    │ sets PayrollPeriod.timekeeping_data_locked = true
    │
    ▼
[daily_attendance_summary] table  (is_finalized = TRUE)
    │
    ▼  triggered from UI → PayrollCalculationController
CalculatePayrollJob  →  CalculateEmployeePayrollJob (×N)  →  FinalizePayrollJob
    │ app/Jobs/Payroll/
    │ PayrollCalculationService::calculateEmployee()
    │ queries: WHERE attendance_date BETWEEN period_start AND period_end
    │          AND is_finalized = true
    │
    ▼
[employee_payroll_calculations] table
    │ calculation_status = 'calculated'
    │
    ▼
PayrollPeriod.status = 'calculated'
```

---

## 2. What Already Exists ✅

| Component | File | Status |
|---|---|---|
| `rfid_ledger` model | `app/Models/RfidLedger.php` | ✅ Built |
| `ProcessRfidLedgerJob` | `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php` | ✅ Built, scheduled every 1 min |
| `LedgerPollingService` | `app/Services/Timekeeping/LedgerPollingService.php` | ✅ Built |
| `AttendanceEvent` model | `app/Models/AttendanceEvent.php` | ✅ Built |
| `AttendanceSummaryService` | `app/Services/Timekeeping/AttendanceSummaryService.php` | ✅ Built |
| `GenerateDailySummariesCommand` | `app/Console/Commands/Timekeeping/GenerateDailySummariesCommand.php` | ✅ Built, runs daily @ 23:59 |
| `DailyAttendanceSummary` model | `app/Models/DailyAttendanceSummary.php` | ✅ Built — has `finalize()`/`unfinalize()` instance methods |
| Scheduler registration | `routes/console.php` | ✅ `ProcessRfidLedgerJob` + `GenerateDailySummariesCommand` scheduled |
| `PayrollCalculationService` | `app/Services/Payroll/PayrollCalculationService.php` | ✅ Queries `is_finalized = true` |
| `CalculatePayrollJob` | `app/Jobs/Payroll/CalculatePayrollJob.php` | ✅ Built + all bugs fixed |
| `CalculateEmployeePayrollJob` | `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` | ✅ Built + all bugs fixed |
| `FinalizePayrollJob` | `app/Jobs/Payroll/FinalizePayrollJob.php` | ✅ Built + all bugs fixed |

---

## 3. What Is Missing ❌

### Gap 1 — No "Finalize Attendance" Artisan Command (CRITICAL)

**Problem:** `AttendanceSummaryService::storeDailySummary()` never sets `is_finalized = true`.
Generated summaries have `is_finalized = false` (default). `PayrollCalculationService` filters 
`WHERE is_finalized = true`, so it finds zero rows → all employees get `daysWorked = 0`, `grossPay = 0`.

**Proof:**  
- `storeDailySummary()` (line ~510): no reference to `is_finalized`
- `DailyAttendanceSummary::$fillable` has `is_finalized`
- Model has `finalize()` / `unfinalize()` methods but nothing calls them from the pipeline

**Fix Required:**  
Create `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php`:
```
artisan timekeeping:finalize-attendance
    --from=YYYY-MM-DD    Start date (required)
    --to=YYYY-MM-DD      End date (required)
    --period=ID          PayrollPeriod ID (optional, locks timekeeping_data_locked)
    --force              Finalize even if already finalized
```

This command:
1. Queries `daily_attendance_summary` `WHERE attendance_date BETWEEN --from AND --to`
2. Bulk-updates `is_finalized = true`
3. If `--period` given: sets `PayrollPeriod.timekeeping_data_locked = true`

---

### Gap 2 — `TimekeepingTestDataSeeder` Does Not Generate Summaries

**Problem:** `TimekeepingTestDataSeeder` seeds `AttendanceEvent` rows only. It does NOT call  
`GenerateDailySummariesCommand` or `AttendanceSummaryService::storeDailySummary()`.

Result: running `TimekeepingTestDataSeeder` + `PayrollCalculationTestSeeder` leaves  
`daily_attendance_summary` populated only by the payroll seeder (which directly inserts rows  
with `is_finalized = true` — this is the current workaround that works).

**For the full realistic flow** (timekeeping events → summaries → payroll):
```bash
php artisan db:seed --class=TimekeepingTestDataSeeder
php artisan timekeeping:generate-daily-summaries --date=2026-03-01
php artisan timekeeping:generate-daily-summaries --date=2026-03-02
# ... (or loop)
php artisan timekeeping:finalize-attendance --from=2026-03-01 --to=2026-03-15
```

---

### Gap 3 — `timekeeping_data_locked` on `PayrollPeriod` Is Unused

**Problem:** `PayrollPeriod` has `timekeeping_data_locked boolean` in `$fillable` and a cast,
but no controller, command, or job ever sets it to `true`.

It's meant to prevent attendance corrections after payroll calculation starts.
`PayrollCalculationService` does not check this flag — it's purely informational right now.

**Fix Required (optional but recommended):**  
The `FinalizeAttendanceForPeriodCommand` (Gap 1) should set this when `--period` is specified.

---

### Gap 4 — `CalculatePayrollJob` Silently Skips Employees Without `EmployeePayrollInfo`

**Problem:**
```php
$employees = Employee::where('status', 'active')->with('payrollInfo')->get();
foreach ($employees as $employee) {
    if ($employee->payrollInfo) {  // ← silently skips if no payroll info
        CalculateEmployeePayrollJob::dispatch(...);
    }
}
```

Employees without an active `EmployeePayrollInfo` record are silently skipped.
`total_employees` on the period is set to `$dispatchedCount` (only those with payroll info),
but `active_employees` in the DB might differ.

**This is acceptable behavior** but should be logged explicitly so HR knows which employees  
were excluded. Currently only `dispatched_count` is logged — the skipped count is not.

---

### Gap 5 — `FinalizePayrollJob` Uses a Fixed 30-Second Delay (Fragile)

**Problem:** `FinalizePayrollJob::dispatch(...)->delay(now()->addSeconds(30))` assumes all  
`CalculateEmployeePayrollJob` instances finish within 30 seconds. With many employees or  
slow DB, this races and `FinalizePayrollJob` runs before all employees are done.

**Proper fix:** Use `Bus::batch()` to chain `FinalizePayrollJob` after the employee batch:
```php
Bus::batch(
    $employees->map(fn($e) => new CalculateEmployeePayrollJob($e, $period, $userId))->all()
)->then(function (Batch $batch) use ($period, $userId) {
    FinalizePayrollJob::dispatch($period, $userId);
})->onQueue('payroll')->dispatch();
```
This requires the `job_batches` table migration (already available in Laravel).

**Priority:** Medium — for small employee sets (<50) the 30-second delay works.  
Required before production with large employee counts.

---

## 4. Implementation Tasks

### Task T1 — Create `FinalizeAttendanceForPeriodCommand` (CRITICAL)

**File:** `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php`

```php
<?php
namespace App\Console\Commands\Timekeeping;

use App\Models\DailyAttendanceSummary;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FinalizeAttendanceForPeriodCommand extends Command
{
    protected $signature = 'timekeeping:finalize-attendance
                            {--from= : Start date YYYY-MM-DD (required)}
                            {--to= : End date YYYY-MM-DD (required)}
                            {--period= : PayrollPeriod ID to lock timekeeping_data_locked}
                            {--force : Re-finalize already-finalized rows}';

    protected $description = 'Finalize attendance summaries for a date range, locking them for payroll processing';

    public function handle(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');

        if (!$from || !$to) {
            $this->error('--from and --to are required.');
            return Command::FAILURE;
        }

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate   = Carbon::parse($to)->endOfDay();

        $query = DailyAttendanceSummary::whereBetween('attendance_date', [
            $fromDate->toDateString(),
            $toDate->toDateString(),
        ]);

        if (!$this->option('force')) {
            $query->where('is_finalized', false);
        }

        $count = $query->count();
        $this->info("Found {$count} attendance summary rows to finalize...");

        if ($count === 0) {
            $this->warn('Nothing to finalize. Use --force to re-finalize.');
            return Command::SUCCESS;
        }

        // Bulk update
        $updated = $query->update([
            'is_finalized' => true,
        ]);

        $this->info("✅ Finalized {$updated} rows.");

        // Lock the period if --period given
        if ($periodId = $this->option('period')) {
            $period = PayrollPeriod::find($periodId);
            if ($period) {
                $period->update(['timekeeping_data_locked' => true]);
                $this->info("🔒 Locked timekeeping data for period: [{$period->id}] {$period->period_name}");
            } else {
                $this->warn("Period ID {$periodId} not found — skipping lock.");
            }
        }

        return Command::SUCCESS;
    }
}
```

**Register in scheduler** (`routes/console.php`) — no automatic schedule; this is run manually  
before each payroll run, or from a UI action.

---

### Task T2 — Log Skipped Employees in `CalculatePayrollJob` (LOW PRIORITY)

**File:** `app/Jobs/Payroll/CalculatePayrollJob.php`

After the foreach loop, add:
```php
$skippedCount = $employees->count() - $dispatchedCount;
if ($skippedCount > 0) {
    Log::warning('Some employees skipped (no EmployeePayrollInfo)', [
        'period_id'     => $this->payrollPeriod->id,
        'skipped_count' => $skippedCount,
        'skipped_ids'   => $employees->filter(fn($e) => !$e->payrollInfo)->pluck('id')->toArray(),
    ]);
}
```

---

### Task T3 — Replace 30-Second Delay With `Bus::batch()` (MEDIUM PRIORITY)

**File:** `app/Jobs/Payroll/CalculatePayrollJob.php`

Use `Illuminate\Bus\Batch` to guarantee `FinalizePayrollJob` runs only after all  
`CalculateEmployeePayrollJob` instances complete.

```php
use Illuminate\Support\Facades\Bus;

$jobs = $employees
    ->filter(fn($e) => $e->payrollInfo)
    ->map(fn($e) => new CalculateEmployeePayrollJob($e, $this->payrollPeriod, $this->userId))
    ->values()
    ->all();

Bus::batch($jobs)
    ->then(function (\Illuminate\Bus\Batch $batch) {
        FinalizePayrollJob::dispatch($this->payrollPeriod, $this->userId);
    })
    ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) {
        Log::error('Payroll batch failed', ['error' => $e->getMessage()]);
    })
    ->onQueue('payroll')
    ->dispatch();
```

Requires running `php artisan queue:batches-table && php artisan migrate` once.

---

## 5. Correct Manual Test Flow (Full Pipeline)

```bash
# Step 0: Ensure prerequisites are seeded
php artisan db:seed --class=PayrollCalculationTestSeeder
# Creates: EmployeePayrollInfo, PayrollPeriod (Mar 1-15), DailyAttendanceSummary (is_finalized=true)
# ^ This seeder already handles the full chain for testing — see PAYROLL_SEEDER_PLAN.md

# ----- OR for the REAL pipeline: -----

# Step 1: Seed attendance events (as if RFID fired)
php artisan db:seed --class=TimekeepingTestDataSeeder

# Step 2: Convert attendance_events → daily_attendance_summary
php artisan timekeeping:generate-daily-summaries --date=2026-03-01 --force
# Repeat for each day, or loop:
# for ($d = 1; $d <= 15; $d++) artisan timekeeping:generate-daily-summaries --date="2026-03-$d"

# Step 3: Finalize the attendance for the period (MISSING STEP — requires Task T1)
php artisan timekeeping:finalize-attendance --from=2026-03-01 --to=2026-03-15 --period=<ID>

# Step 4: Start queue worker
php artisan queue:work --queue=payroll,default --tries=3

# Step 5: Trigger calculation from UI (or tinker)
php artisan tinker --execute="
    \$p = App\Models\PayrollPeriod::where('period_number','2026-03-1H')->first();
    App\Jobs\Payroll\CalculatePayrollJob::dispatch(\$p, 1);
"

# Step 6: Monitor
php artisan queue:monitor payroll
php artisan tinker --execute="
    echo 'Calcs: ' . App\Models\EmployeePayrollCalculation::count();
    echo 'Logs: '  . App\Models\PayrollCalculationLog::count();
"
```

---

## 6. File Touch Summary

| File | Action | Priority |
|---|---|---|
| `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php` | **CREATE** | 🔴 Critical |
| `routes/console.php` | Register new command (no schedule needed) | 🔴 Critical |
| `app/Jobs/Payroll/CalculatePayrollJob.php` | Add skipped-employee warning log | 🟡 Low |
| `app/Jobs/Payroll/CalculatePayrollJob.php` | Replace 30s delay with `Bus::batch()` | 🟠 Medium |
| `database/migrations/YYYY_MM_DD_create_job_batches_table.php` | Run if using `Bus::batch()` | 🟠 Medium |

---

## 7. Status of Pipeline Steps

| Step | Component | Status |
|---|---|---|
| 1. RFID → rfid_ledger | FastAPI RFID server (external) | ✅ (external) |
| 2. rfid_ledger → attendance_events | `ProcessRfidLedgerJob` | ✅ |
| 3. attendance_events → daily summaries | `GenerateDailySummariesCommand` | ✅ (runs daily 23:59) |
| 4. **Finalize summaries (is_finalized = true)** | **FinalizeAttendanceForPeriodCommand** | ❌ **MISSING** |
| 5. Trigger payroll calculation | `CalculatePayrollJob` via UI/controller | ✅ |
| 6. Per-employee calculation | `CalculateEmployeePayrollJob` | ✅ |
| 7. Finalize period | `FinalizePayrollJob` | ✅ (30s delay, fragile) |
| 8. Done → status = 'calculated' | `PayrollPeriod.status` | ✅ |
