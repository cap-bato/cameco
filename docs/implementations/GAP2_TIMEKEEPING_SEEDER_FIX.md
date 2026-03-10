# Gap 2 — TimekeepingTestDataSeeder Missing Summary Generation

**Status:** ✅ Phase 1 Task 1.1 COMPLETE — DailyAttendanceSummary generation implemented (2026-03-06)  
✅ Phase 1 Task 1.2 COMPLETE — fixed payroll-period date range implemented (2026-03-06)  
✅ Phase 2 Task 2.1 COMPLETE — standalone DailyAttendanceSummarySeeder created (2026-03-06)  
✅ Phase 3 Task 3.1 COMPLETE — standalone EmployeePayrollInfoSeeder created (2026-03-06)  
✅ Phase 4 Task 4.1 COMPLETE — env-gated PayrollCalculationTestSeeder registration added (2026-03-06)  
**Priority:** 🟠 Medium — testing gap, not a production blocker  

---

## The Problem

`TimekeepingTestDataSeeder` creates:
- ✅ 20 test employees (via `Employee::factory()`)
- ✅ `AttendanceEvent` rows for last 7 days (time_in/time_out per employee per day)

`TimekeepingTestDataSeeder` does **NOT** create:
- ❌ `DailyAttendanceSummary` rows (the table payroll reads)
- ❌ `EmployeePayrollInfo` rows (salary config for payroll calculation)
- ❌ A test `PayrollPeriod`

This means running `TimekeepingTestDataSeeder` and then trying to "Calculate" a payroll  
period will produce zero-pay results because `daily_attendance_summary` is empty.

---

## Phase 1 — Extend `TimekeepingTestDataSeeder`

### Task 1.1 — Generate `DailyAttendanceSummary` After Events

**File:** `database/seeders/TimekeepingTestDataSeeder.php`

After the attendance events loop completes, add a step that calls  
`AttendanceSummaryService::computeDailySummary()` + `storeDailySummary()` for each  
employee × day combination, then finalizes the rows.

Add at end of `run()` method:

```php
// Step 2: Generate daily summaries from attendance events
$this->command->info('Generating daily attendance summaries from events...');
$summaryService = new \App\Services\Timekeeping\AttendanceSummaryService();

$summaryCreated = 0;
$summaryErrors  = 0;

for ($day = 6; $day >= 0; $day--) {
    $date = now()->subDays($day);
    if ($date->isWeekend()) continue;
    
    foreach ($employees as $employee) {
        try {
            // Check if attendance event exists for this employee/day
            $hasEvent = \App\Models\AttendanceEvent::where('employee_id', $employee->id)
                ->whereDate('event_date', $date)
                ->exists();

            if (!$hasEvent) continue;

            $summary = $summaryService->computeDailySummary($employee->id, $date);
            $summary = $summaryService->applyBusinessRules($summary, $date);
            $summaryService->storeDailySummary($summary);
            $summaryCreated++;
        } catch (\Exception $e) {
            $summaryErrors++;
        }
    }
}

$this->command->info("  → Summaries created: {$summaryCreated}, errors: {$summaryErrors}");

// Step 3: Finalize all summaries (mark is_finalized = true so payroll can pick them up)
$finalized = \App\Models\DailyAttendanceSummary::whereBetween('attendance_date', [
        now()->subDays(6)->toDateString(),
        now()->toDateString(),
    ])
    ->where('is_finalized', false)
    ->update(['is_finalized' => true]);

$this->command->info("  → Finalized {$finalized} attendance rows for payroll.");
```

---

### Task 1.2 — Extend Date Range to Cover a Full Payroll Period ✅ COMPLETE (2026-03-06)

**Problem:** Current seeder only creates 7 days of data (last week).  
A payroll period is typically 15 days (semi-monthly) or 30 days (monthly).  
Testing the March 2026 period (`2026-03-01` to `2026-03-15`) requires seeding those dates,  
not "last 7 days."

**Fix:** Add a `SEED_DATE_FROM` / `SEED_DATE_TO` constant in the seeder:

```php
// Date range for seeded attendance data (align with PayrollCalculationTestSeeder)
private const SEED_DATE_FROM = '2026-03-01';
private const SEED_DATE_TO   = '2026-03-15';
```

And replace `now()->subDays($day)` with a loop over working days in this range.

**Implemented:**
- Added constants in `database/seeders/TimekeepingTestDataSeeder.php`:
```php
private const SEED_DATE_FROM = '2026-03-01';
private const SEED_DATE_TO   = '2026-03-15';
```
- Added `getWorkingDays(string $startDate, string $endDate): array` helper to build Mon-Fri dates.
- Replaced attendance-event generation loop from rolling 7-day logic to `foreach ($workDays as $date)`.
- Replaced summary-generation loop from rolling 7-day logic to `foreach ($workDays as $date)`.
- Updated finalization range to use constants:
```php
->whereBetween('attendance_date', [self::SEED_DATE_FROM, self::SEED_DATE_TO])
```

**Verification:**
- ✅ `php -l database/seeders/TimekeepingTestDataSeeder.php` (no syntax errors)
- ✅ `php artisan db:seed --class=TimekeepingTestDataSeeder --force` now runs with the fixed range and reports:
    - `Generating attendance events for 10 working days...`

---

### Task 1.3 — Add `EmployeePayrollInfo` Seeding

**Problem:** After generating attendance data, employees still need payroll info for  
`CalculatePayrollJob` to dispach them (the job skips employees where `payrollInfo` is null).

**Fix:** At the start of `run()`, call:

```php
$this->call(EmployeePayrollInfoSeeder::class);
```

Or inline the payroll info seeding at the end (same logic as `PayrollCalculationTestSeeder` step 1).

---

## Phase 2 — Create `DailyAttendanceSummarySeeder` (standalone)

### Task 2.1 — Create New Seeder ✅ COMPLETE (2026-03-06)

**File:** `database/seeders/DailyAttendanceSummarySeeder.php`

This is a standalone seeder that directly inserts finalized attendance rows  
**without** needing RFID data or the timekeeping pipeline. Used by:
- `PayrollCalculationTestSeeder` (currently inline — should call this)
- Manual seeding after `migrate:fresh --seed`

**Implemented:**
- Created file: `database/seeders/DailyAttendanceSummarySeeder.php`
- Added fixed test window constants:
```php
private const PERIOD_START = '2026-03-01';
private const PERIOD_END   = '2026-03-15';
```
- Seeds finalized (`is_finalized = true`) Mon-Fri attendance rows for all active employees.
- Uses existing `WorkSchedule` id from DB instead of hardcoding schedule id.
- Idempotent behavior: skips employee/date rows that already exist.
- Includes `getWorkingDays()` helper for deterministic date generation.

**Verification:**
- ✅ `php -l database/seeders/DailyAttendanceSummarySeeder.php` (no syntax errors)
- ✅ `php artisan db:seed --class=DailyAttendanceSummarySeeder --force` executed successfully
- ✅ Runtime output confirms working-day coverage and safe re-runs:
    - `Work days in range: 10`
    - `Created: 0, Skipped: 310` (expected due to existing seeded records)

```php
<?php

namespace Database\Seeders;

use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DailyAttendanceSummarySeeder extends Seeder
{
    // Align these with PayrollCalculationTestSeeder
    private const PERIOD_START = '2026-03-01';
    private const PERIOD_END   = '2026-03-15';

    public function run(): void
    {
        $this->command->info('Seeding DailyAttendanceSummary (Mon–Fri, is_finalized=true)...');

        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->error('No active employees. Run EmployeeSeeder first.');
            return;
        }

        $workDays = $this->getWorkingDays(self::PERIOD_START, self::PERIOD_END);
        $this->command->info('Work days in range: ' . count($workDays));

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                $exists = DailyAttendanceSummary::where('employee_id', $employee->id)
                    ->where('attendance_date', $date)
                    ->exists();

                if ($exists) { $skipped++; continue; }

                $rand        = mt_rand(1, 100);
                $isAbsent    = $rand <= 5;
                $isLate      = !$isAbsent && $rand <= 25;
                $isOvertime  = !$isAbsent && $rand >= 85;

                $lateMinutes     = $isLate     ? mt_rand(5, 45)  : 0;
                $overtimeHours   = $isOvertime ? mt_rand(1, 3)   : 0.0;
                $regularHours    = $isAbsent   ? 0.0             : 8.0;
                $totalHours      = $isAbsent   ? 0.0             : ($regularHours + $overtimeHours - $lateMinutes / 60);

                $timeIn  = $isAbsent ? null : Carbon::parse($date . ' 08:00:00')->addMinutes($lateMinutes);
                $timeOut = $isAbsent ? null : Carbon::parse($date . ' 17:00:00')->addHours($overtimeHours);

                DailyAttendanceSummary::create([
                    'employee_id'        => $employee->id,
                    'attendance_date'    => $date,
                    'work_schedule_id'   => 1,
                    'time_in'            => $timeIn?->toDateTimeString(),
                    'time_out'           => $timeOut?->toDateTimeString(),
                    'break_start'        => $isAbsent ? null : Carbon::parse($date . ' 12:00:00')->toDateTimeString(),
                    'break_end'          => $isAbsent ? null : Carbon::parse($date . ' 13:00:00')->toDateTimeString(),
                    'break_duration'     => $isAbsent ? 0 : 60,
                    'total_hours_worked' => round(max(0, $totalHours), 2),
                    'regular_hours'      => round($regularHours, 2),
                    'overtime_hours'     => round($overtimeHours, 2),
                    'is_present'         => !$isAbsent,
                    'is_late'            => $isLate,
                    'is_undertime'       => false,
                    'is_overtime'        => $isOvertime,
                    'late_minutes'       => $lateMinutes,
                    'undertime_minutes'  => 0,
                    'is_on_leave'        => false,
                    'ledger_verified'    => true,
                    'is_finalized'       => true,   // ← CRITICAL for payroll
                    'calculated_at'      => now(),
                ]);
                $created++;
            }
        }

        $this->command->info("  → Created: {$created}, Skipped: {$skipped}");
    }

    private function getWorkingDays(string $start, string $end): array
    {
        $days   = [];
        $cursor = Carbon::parse($start);
        $last   = Carbon::parse($end);
        while ($cursor->lte($last)) {
            if (!$cursor->isWeekend()) $days[] = $cursor->toDateString();
            $cursor->addDay();
        }
        return $days;
    }
}
```

---

### Task 2.2 — Refactor `PayrollCalculationTestSeeder` to Use the Standalone Seeder

**File:** `database/seeders/PayrollCalculationTestSeeder.php`

Replace the inline attendance seeding block (Step 3, lines ~155–245) with:

```php
// Step 3: DailyAttendanceSummary
$this->command->info('Step 3: Seeding DailyAttendanceSummary...');
$this->call(DailyAttendanceSummarySeeder::class);
```

This keeps the seeder DRY and ensures both seeders stay in sync on date ranges.

---

## Phase 3 — Create `EmployeePayrollInfoSeeder` (standalone)

### Task 3.1 — Create New Seeder ✅ COMPLETE (2026-03-06)

**File:** `database/seeders/EmployeePayrollInfoSeeder.php`

Extracted from `PayrollCalculationTestSeeder` step 1 into a standalone seeder:

**Implemented:**
- Created file: `database/seeders/EmployeePayrollInfoSeeder.php`
- Added reusable salary presets (`SALARY_PRESETS`) from existing payroll test logic.
- Implemented creator resolution order:
    - `payroll@cameco.com`
    - `superadmin@cameco.com`
    - first available user
- Added idempotent create logic:
    - skips employee if active payroll info (`is_active=true` and `end_date IS NULL`) already exists
- Added `sss(float $salary): string` bracket helper for `sss_bracket` population.

**Verification:**
- ✅ `php -l database/seeders/EmployeePayrollInfoSeeder.php` (no syntax errors)
- ✅ `php artisan db:seed --class=EmployeePayrollInfoSeeder --force` executed successfully
- ✅ Runtime result confirms idempotent behavior:
    - `Created: 0, Skipped: 31` (expected because active records already exist)

```php
<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeePayrollInfoSeeder extends Seeder
{
    private const SALARY_PRESETS = [
        ['basic_salary' => 35000, 'daily_rate' => 1346.15, 'hourly_rate' => 168.27],
        ['basic_salary' => 28000, 'daily_rate' => 1076.92, 'hourly_rate' => 134.62],
        ['basic_salary' => 22000, 'daily_rate' => 846.15,  'hourly_rate' => 105.77],
        ['basic_salary' => 18000, 'daily_rate' => 692.31,  'hourly_rate' => 86.54],
        ['basic_salary' => 15000, 'daily_rate' => 576.92,  'hourly_rate' => 72.12],
    ];

    public function run(): void
    {
        $this->command->info('Seeding EmployeePayrollInfo...');

        $creator = User::where('email', 'payroll@cameco.com')->first()
            ?? User::where('email', 'superadmin@cameco.com')->first()
            ?? User::first();

        if (!$creator) {
            $this->command->error('No user found — run RolesAndPermissionsSeeder first.');
            return;
        }

        $employees = Employee::where('status', 'active')->get();
        $created = 0; $skipped = 0;

        foreach ($employees as $index => $employee) {
            $exists = EmployeePayrollInfo::where('employee_id', $employee->id)
                ->where('is_active', true)->whereNull('end_date')->exists();

            if ($exists) { $skipped++; continue; }

            $preset = self::SALARY_PRESETS[$index % count(self::SALARY_PRESETS)];

            EmployeePayrollInfo::create([
                'employee_id'                => $employee->id,
                'salary_type'                => 'monthly',
                'basic_salary'               => $preset['basic_salary'],
                'daily_rate'                 => $preset['daily_rate'],
                'hourly_rate'                => $preset['hourly_rate'],
                'payment_method'             => 'bank_transfer',
                'tax_status'                 => 'S',
                'rdo_code'                   => '055',
                'withholding_tax_exemption'  => 0,
                'is_tax_exempt'              => false,
                'is_substituted_filing'      => false,
                'sss_number'                 => sprintf('33-%07d-%d', $employee->id * 1000 + $index, mt_rand(0, 9)),
                'philhealth_number'          => sprintf('%012d', $employee->id * 100000 + $index),
                'pagibig_number'             => sprintf('%012d', $employee->id * 200000 + $index),
                'tin_number'                 => sprintf('%09d-%03d', $employee->id * 1000000, mt_rand(0, 999)),
                'sss_bracket'                => $this->sss($preset['basic_salary']),
                'is_sss_voluntary'           => false,
                'philhealth_is_indigent'     => false,
                'pagibig_employee_rate'      => 2.00,
                'bank_name'                  => 'BDO Unibank',
                'bank_code'                  => 'BDO',
                'bank_account_number'        => sprintf('%010d', $employee->id * 1000000 + 1000000000),
                'bank_account_name'          => $employee->profile?->full_name ?? 'Employee',
                'is_entitled_to_rice'        => true,
                'is_entitled_to_uniform'     => false,
                'is_entitled_to_laundry'     => false,
                'is_entitled_to_medical'     => true,
                'effective_date'             => '2026-01-01',
                'end_date'                   => null,
                'is_active'                  => true,
                'created_by'                 => $creator->id,
            ]);
            $created++;
        }

        $this->command->info("  → Created: {$created}, Skipped: {$skipped}");
    }

    private function sss(float $salary): string
    {
        // Simplified bracket: returns bracket 1–35 based on salary
        $brackets = [3250,3750,4250,4750,5250,5750,6250,6750,7250,7750,8250,8750,
                     9250,9750,10250,10750,11250,11750,12250,12750,13250,13750,14250,
                     14750,15250,15750,16250,16750,17250,17750,18250,18750,19250,19750];
        foreach ($brackets as $i => $cap) {
            if ($salary <= $cap) return (string)($i + 1);
        }
        return '35';
    }
}
```

---

## Phase 4 — Register Seeders in `DatabaseSeeder`

### Task 4.1 — Add Env-Gated Payroll Test Data Call ✅ COMPLETE (2026-03-06)

**File:** `database/seeders/DatabaseSeeder.php`

After the `PayrollPeriodsSeeder` call block, add:

**Implemented:**
- Updated `database/seeders/DatabaseSeeder.php` immediately after the `PayrollPeriodsSeeder` block.
- Added environment-gated invocation of `PayrollCalculationTestSeeder`:
    - runs only in `local` or `testing`
    - requires `SEED_PAYROLL_TEST_DATA=true`
- Keeps production/default seeding behavior unchanged unless explicitly enabled.

**Verification:**
- ✅ `php -l database/seeders/DatabaseSeeder.php` (no syntax errors)

**Applied code:**

```php
// ── Payroll Calculation Test Data (dev/local only) ────────────────────────
// Enable with: SEED_PAYROLL_TEST_DATA=true in .env
if (app()->environment('local', 'testing') && env('SEED_PAYROLL_TEST_DATA', false)) {
    if (class_exists(\Database\Seeders\PayrollCalculationTestSeeder::class)) {
        $this->call(\Database\Seeders\PayrollCalculationTestSeeder::class);
    }
}
```

---

## Summary of Files to Create / Modify

| File | Action |
|---|---|
| `database/seeders/DailyAttendanceSummarySeeder.php` | **CREATE** |
| `database/seeders/EmployeePayrollInfoSeeder.php` | **CREATE** |
| `database/seeders/TimekeepingTestDataSeeder.php` | **MODIFY** — add summary + finalize steps |
| `database/seeders/PayrollCalculationTestSeeder.php` | **MODIFY** — call DailyAttendanceSummarySeeder |
| `database/seeders/DatabaseSeeder.php` | **MODIFY** — add env-gated PayrollCalculationTestSeeder |
