# Gap 1 — FinalizeAttendanceForPeriodCommand

**Status:** ✅ ALL PHASES COMPLETE (2026-03-06)
- ✅ Phase 1 COMPLETE — all 4 tasks implemented
- ✅ Phase 2 Task 2.1 COMPLETE — `--auto-finalize` flag implemented  
- ✅ Phase 3 Task 3.1 COMPLETE — permission created and assigned to HR Manager & Payroll Officer
**Priority:** 🔴 Must implement before any production payroll run  

---

## The Problem

`AttendanceSummaryService::storeDailySummary()` writes `is_finalized = false` by default.  
`PayrollCalculationService` queries:

```php
DailyAttendanceSummary::query()
    ->where('employee_id', $employee->id)
    ->whereBetween('attendance_date', [$period->period_start, $period->period_end])
    ->where('is_finalized', true)   // ← nothing passes this filter
    ->get();
```

**Result:** All employees get `daysWorked = 0`, `basicPay = 0`, `grossPay = 0`.  
Payroll will complete "successfully" but generate zero pay for everyone.

The `DailyAttendanceSummary` model already has `markFinalized()` and `markUnfinalized()`  
instance methods, and `scopeFinalized()` / `scopePending()` scopes — but nothing calls  
`markFinalized()` from the timekeeping pipeline.

---

## Phase 1 — Create the Artisan Command

### Task 1.1 — Create `FinalizeAttendanceForPeriodCommand`

**File:** `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php`

```php
<?php

namespace App\Console\Commands\Timekeeping;

use App\Models\DailyAttendanceSummary;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizeAttendanceForPeriodCommand extends Command
{
    protected $signature = 'timekeeping:finalize-attendance
                            {--from=      : Start date YYYY-MM-DD (required unless --period is given)}
                            {--to=        : End date YYYY-MM-DD (required unless --period is given)}
                            {--period=    : PayrollPeriod ID — uses period_start / timekeeping_cutoff_date automatically}
                            {--dry-run    : Show what would be finalized without making changes}
                            {--force      : Re-finalize rows that are already finalized}';

    protected $description = 'Mark daily_attendance_summary rows as is_finalized=true for a date range, locking them for payroll processing';

    public function handle(): int
    {
        // Resolve date range
        [$from, $to, $period] = $this->resolveDateRange();

        if (!$from || !$to) {
            $this->error('Provide --from and --to, or --period=<id>.');
            return Command::FAILURE;
        }

        $this->info("Finalizing attendance from {$from} to {$to}...");

        // Build query
        $query = DailyAttendanceSummary::whereBetween('attendance_date', [$from, $to]);
        if (!$this->option('force')) {
            $query->where('is_finalized', false);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->warn('No unfinalized rows found in range. Use --force to re-finalize.');
            return Command::SUCCESS;
        }

        $this->line("Found {$count} row(s) to finalize.");

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] No changes made.');
            return Command::SUCCESS;
        }

        // Confirm for large sets
        if ($count > 500 && !$this->confirm("Finalize {$count} rows?")) {
            $this->warn('Aborted.');
            return Command::FAILURE;
        }

        DB::beginTransaction();
        try {
            $updated = (clone $query)->update(['is_finalized' => true]);

            // If --period provided, lock timekeeping on the period
            if ($period) {
                $period->update(['timekeeping_data_locked' => true]);
                $this->info("🔒 Locked timekeeping for period [{$period->id}] {$period->period_name}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed: ' . $e->getMessage());
            Log::error('[FinalizeAttendanceForPeriodCommand] Failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $this->info("✅ Finalized {$updated} attendance row(s).");

        Log::info('[FinalizeAttendanceForPeriodCommand] Attendance finalized', [
            'from'       => $from,
            'to'         => $to,
            'period_id'  => $period?->id,
            'rows'       => $updated,
        ]);

        return Command::SUCCESS;
    }

    /** Resolve --from/--to from options or from --period */
    private function resolveDateRange(): array
    {
        if ($periodId = $this->option('period')) {
            $period = PayrollPeriod::findOrFail($periodId);
            $from   = $period->period_start->toDateString();
            // Use timekeeping_cutoff_date if set, otherwise period_end
            $to     = $period->timekeeping_cutoff_date
                ? $period->timekeeping_cutoff_date->toDateString()
                : $period->period_end->toDateString();
            return [$from, $to, $period];
        }

        return [
            $this->option('from'),
            $this->option('to'),
            null,
        ];
    }
}
```

---

### Task 1.2 — Register Command in `Console/Kernel.php` ✅ DONE

**File:** `app/Console/Kernel.php`  

**Status:** Command is already registered in line 18 of `$commands` array:

```php
\App\Console\Commands\Timekeeping\FinalizeAttendanceForPeriodCommand::class,
```

**Verification:**
- ✅ `php artisan timekeeping:finalize-attendance --help` → fully discoverable
- ✅ All options display correctly: `--from`, `--to`, `--period`, `--dry-run`, `--force`
- ✅ Command appears in `php artisan list timekeeping` namespace

> **Note:** Laravel 12 supports auto-discovery, but this app uses explicit Kernel.php  
> registration (line 18). The command is production-ready.

---

### Task 1.3 — Add Route for UI-Triggered Finalization ✅ DONE

**File:** `routes/hr.php`  

Routes have been added to the timekeeping group:

```php
// Attendance Finalization API Routes (JSON Responses)
// Lock/unlock attendance for a period
// ====================================================
Route::prefix('api/attendance/finalize')->name('api.attendance.finalize.')->group(function () {
    // Finalize (lock) attendance for period
    Route::post('/', [AttendanceFinalizeController::class, 'store'])
        ->middleware('permission:hr.timekeeping.attendance.finalize')
        ->name('store');
    
    // Unfinalize (unlock) attendance for period
    Route::delete('/', [AttendanceFinalizeController::class, 'destroy'])
        ->middleware('permission:hr.timekeeping.attendance.finalize')
        ->name('destroy');
});
```

**Verification:**
- ✅ POST `hr/timekeeping/api/attendance/finalize` → finalize attendance
- ✅ DELETE `hr/timekeeping/api/attendance/finalize` → unfinalize attendance
- ✅ Permission middleware `hr.timekeeping.attendance.finalize` applied
- ✅ Routes appear in `php artisan route:list --name=finalize`

---

### Task 1.4 — Create `AttendanceFinalizeController` ✅ DONE

**File:** `app/Http/Controllers/HR/Timekeeping/AttendanceFinalizeController.php`

**Implementation:** Two public JSON API methods:

#### `store()` — POST Finalize Attendance

Accepts either:
- `period_id`: int (uses period's `period_start` to `timekeeping_cutoff_date` or `period_end`)
- `from` + `to`: YYYY-MM-DD date strings

Response:
```json
{
  "success": true,
  "message": "Finalized 110 attendance row(s)",
  "finalized": 110
}
```

Logic:
1. Query `DailyAttendanceSummary` where `is_finalized = false` in date range
2. Update `is_finalized = true` for all matching rows
3. If `period_id` provided, also set `PayrollPeriod.timekeeping_data_locked = true`
4. Log the action with user ID, dates, and count updated

#### `destroy()` — DELETE Unfinalize Attendance

Accepts:
- `period_id`: int (only method for unlocking)

Response:
```json
{
  "success": true,
  "message": "Unfinalized 110 attendance row(s)",
  "unfinalized": 110
}
```

Logic:
1. Query `DailyAttendanceSummary` where `is_finalized = true` in period range
2. Update `is_finalized = false` for all matching rows
3. Set `PayrollPeriod.timekeeping_data_locked = false`
4. Log the action

**Verification:**
- ✅ POST `hr/timekeeping/api/attendance/finalize` fully wired
- ✅ DELETE `hr/timekeeping/api/attendance/finalize` fully wired
- ✅ Permission middleware `hr.timekeeping.attendance.finalize` guards both routes
- ✅ Input validation: `period_id` OR (`from` + `to`) required
- ✅ Zero PHP errors / no compilation errors
- ✅ Integrates with routes/hr.php successfully
- ✅ Controller imported and registered in routes

## Phase 2 — Hook into `GenerateDailySummariesCommand` (Optional Enhancement)

**Problem:** `GenerateDailySummariesCommand` generates summaries but never finalizes them.  
For the natural pipeline, finalization is a separate step (HR Manager confirms data is correct).  
But for automated overnight runs, an `--auto-finalize` flag is useful for non-contested periods.

### Task 2.1 — Add `--auto-finalize` flag to `GenerateDailySummariesCommand` ✅

**Status:** ✅ COMPLETE (2026-03-06)

**File:** `app/Console/Commands/Timekeeping/GenerateDailySummariesCommand.php`

**Implementation Details:**

1. **Signature Update:** Added `{--auto-finalize : Automatically finalize summaries if no errors}` flag to command signature
2. **Import Added:** Added `use App\Models\DailyAttendanceSummary;` to imports
3. **Logic Added:** After progress bar completes, checks both conditions:
   - `$this->option('auto-finalize')` — flag was provided
   - `$errorCount === 0` — no errors during generation
4. **Auto-finalize Query:** Sets `is_finalized = true` for all unfinalized summaries on the target date
5. **Error Handling:** Wrapped in try-catch to handle DB errors gracefully
6. **User Feedback:** Displays count of finalized records via `$this->info()`

**Verification:**
- ✅ Command help shows `--auto-finalize` flag: `php artisan timekeeping:generate-daily-summaries --help`
- ✅ No PHP compilation errors
- ✅ Command executes correctly with the flag
- ✅ Auto-finalize only runs when no errors occurred (design prevents double-finalization of bad data)

**Implementation:**

After all summaries are generated, added:

```php
// Auto-finalize if requested and no errors occurred
if ($this->option('auto-finalize') && $errorCount === 0) {
    try {
        $finalized = DailyAttendanceSummary::whereDate('attendance_date', $targetDate)
            ->where('is_finalized', false)
            ->update(['is_finalized' => true]);
        
        $this->info("✓ Auto-finalized {$finalized} attendance summaries for {$targetDate->toDateString()}");
    } catch (\Exception $e) {
        $this->warn("Auto-finalization failed: {$e->getMessage()}");
    }
}
```

Updated signature:
```php
protected $signature = 'timekeeping:generate-daily-summaries
                        {--date= : Specific date to generate summaries for (YYYY-MM-DD)}
                        {--force : Force regeneration even if summaries exist}
                        {--auto-finalize : Automatically finalize attendance after successful generation}';
```

---

## Phase 3 — Add Permission ✅ COMPLETE

### Task 3.1 — Add `hr.timekeeping.attendance.finalize` Permission ✅ COMPLETE (2026-03-06)

**Status:** ✅ COMPLETE

**File:** `database/seeders/TimekeepingPermissionsSeeder.php`

**Implementation:** 

The permission `hr.timekeeping.attendance.finalize` has been added to the seeder with the following updates:

1. **Permission Created:** Added to permissions array at line 26
   ```php
   'hr.timekeeping.attendance.finalize',
   ```

2. **HR Manager Assignment:** All permissions including finalize are assigned to HR Manager role (line 70)
   ```php
   $hrManagerRole->givePermissionTo($permissions);
   ```

3. **Payroll Officer Assignment:** Payroll Officer role now has the finalize permission (lines 72-78)
   ```php
   $payrollOfficerRole = \Spatie\Permission\Models\Role::firstOrCreate(
       ['name' => 'Payroll Officer'],
       ['guard_name' => 'web']
   );
   $payrollOfficerRole->givePermissionTo('hr.timekeeping.attendance.finalize');
   ```

**Verification:**
- ✅ Seeder executed successfully: `php artisan db:seed --class=TimekeepingPermissionsSeeder`
- ✅ Permission cache cleared: `php artisan permission:cache-reset`
- ✅ Routes properly protected with middleware:
  - POST `hr/timekeeping/api/attendance/finalize` — middleware: `permission:hr.timekeeping.attendance.finalize`
  - DELETE `hr/timekeeping/api/attendance/finalize` — middleware: `permission:hr.timekeeping.attendance.finalize`
- ✅ Both HR Manager and Payroll Officer roles have the permission

**Roles Assigned:**
- ✅ HR Manager — all timekeeping permissions including finalize
- ✅ Payroll Officer — finalize permission specifically

---

## Usage Examples

```bash
# Finalize attendance using period ID (recommended — auto-resolves dates)
php artisan timekeeping:finalize-attendance --period=5

# Finalize manually specified date range
php artisan timekeeping:finalize-attendance --from=2026-03-01 --to=2026-03-15

# Dry run — preview what would be finalized
php artisan timekeeping:finalize-attendance --period=5 --dry-run

# Re-finalize (e.g., after corrections)
php artisan timekeeping:finalize-attendance --period=5 --force

# Unfinalize (allow corrections)
# Use AttendanceFinalizeController::destroy() from UI, or:
php artisan tinker --execute="
    App\Models\DailyAttendanceSummary
        ::whereBetween('attendance_date', ['2026-03-01','2026-03-15'])
        ->update(['is_finalized' => false]);
    echo 'Unfinalized.';
"
```

---

## Files to Create / Modify

| File | Action |
|---|---|
| `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php` | **CREATE** |
| `app/Http/Controllers/HR/Timekeeping/AttendanceFinalizeController.php` | **CREATE** (optional) |
| `app/Console/Commands/Timekeeping/GenerateDailySummariesCommand.php` | **MODIFY** — add `--auto-finalize` flag |
| `routes/hr.php` | **MODIFY** — add 2 finalize routes |
| `database/seeders/TimekeepingPermissionsSeeder.php` | **MODIFY** — add finalize permission |
