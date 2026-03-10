# Payroll Seeders Implementation Plan

**Date:** 2026-03-06  
**Scope:** Document all existing seeders needed for the payroll calculation flow, identify gaps,  
and define what must be created to enable full end-to-end testing.

---

## 1. Seeder Dependency Chain

```
DatabaseSeeder
    ├── RolesAndPermissionsSeeder         (users, roles, permissions)
    ├── PayrollPermissionsSeeder          (payroll-specific permissions)
    ├── PayrollOfficerAccountSeeder       (payroll@cameco.com user)
    ├── DepartmentSeeder                  (department records)
    ├── PositionSeeder                    (position records)
    ├── EmployeeSeeder                    (active employees + profiles)
    ├── EmployeeAccountSeeder             (links employees to users)
    ├── PaymentMethodsSeeder              (bank transfer, cash, etc.)
    ├── SalaryComponentSeeder             (earnings & deductions config)
    ├── GovernmentContributionRatesSeeder (SSS, PhilHealth, Pag-IBIG tables)
    ├── TaxBracketsSeeder                 (BIR withholding tax table)
    ├── PayrollPeriodsSeeder              (historical periods — status='completed')
    │
    │   ══ PAYROLL CALCULATION TEST PATH ══
    │
    └── PayrollCalculationTestSeeder      (run separately — seeds test period + data)
            ├── EmployeePayrollInfo       (salary config for every active employee)
            ├── PayrollPeriod             ('March 2026 - 1st Half', status='active')
            └── DailyAttendanceSummary    (Mon–Fri attendance, is_finalized=true)
```

---

## 2. Existing Seeders Inventory

### Core prerequisite seeders (run by `DatabaseSeeder`)

| Seeder | File | Provides | Required by payroll? |
|---|---|---|---|
| `EmployeeSeeder` | `database/seeders/EmployeeSeeder.php` | Active employees with profiles | ✅ Required |
| `DepartmentSeeder` | `database/seeders/DepartmentSeeder.php` | Department records | ✅ Required |
| `PositionSeeder` | `database/seeders/PositionSeeder.php` | Position records | ✅ Required |
| `PayrollOfficerAccountSeeder` | `database/seeders/PayrollOfficerAccountSeeder.php` | `payroll@cameco.com` user | ✅ Required |
| `SalaryComponentSeeder` | `database/seeders/SalaryComponentSeeder.php` | Earning/deduction component definitions | ✅ Required |
| `GovernmentContributionRatesSeeder` | `database/seeders/GovernmentContributionRatesSeed er.php` | SSS/PhilHealth/Pag-IBIG brackets | ✅ Required |
| `TaxBracketsSeeder` | `database/seeders/TaxBracketsSeeder.php` | BIR withholding tax table | ✅ Required |
| `PaymentMethodsSeeder` | `database/seeders/PaymentMethodsSeeder.php` | Payment methods (bank transfer, etc.) | ✅ Required |
| `PayrollPeriodsSeeder` | `database/seeders/PayrollPeriodsSeeder.php` | Historical completed periods (no calculations) | Optional |

### Timekeeping seeders

| Seeder | File | Provides | Status |
|---|---|---|---|
| `TimekeepingTestDataSeeder` | `database/seeders/TimekeepingTestDataSeeder.php` | `AttendanceEvent` rows for 20 employees × 7 days | ⚠️ Partial — does NOT generate `DailyAttendanceSummary` |
| `RfidDeviceSeeder` | `database/seeders/RfidDeviceSeeder.php` | RFID device records | Optional for testing |

### Payroll calculation seeders

| Seeder | File | Provides | Status |
|---|---|---|---|
| `PayrollCalculationTestSeeder` | `database/seeders/PayrollCalculationTestSeeder.php` | `EmployeePayrollInfo` + `PayrollPeriod` + `DailyAttendanceSummary` (finalized) | ✅ Working — full test data |
| `PayrollPaymentsSeeder` | `database/seeders/PayrollPaymentsSeeder.php` | Mock payroll payment records | Optional |
| `PayslipsSeeder` | `database/seeders/PayslipsSeeder.php` | Mock payslip records | Optional |

---

## 3. Gap Analysis

### Gap A — `TimekeepingTestDataSeeder` Stops at `AttendanceEvent`

**What it does:** Creates 20 employees and seeds `attendance_events` for 7 days.  
**What it doesn't do:** Call `AttendanceSummaryService::storeDailySummary()` to convert those  
events into `daily_attendance_summary` rows.

**Result:** After running this seeder, `daily_attendance_summary` is empty.  
Payroll calculation finds zero attendance → all employees get `gross_pay = 0`.

**Fix:** Either:
- (A) Add a `DailyAttendanceSummaryTestSeeder` that directly inserts finalized rows  
- (B) Extend `TimekeepingTestDataSeeder` to also call `AttendanceSummaryService` after inserting events  
- (C) Use `PayrollCalculationTestSeeder` which already inserts summaries directly (current workaround, already works)

**Recommendation:** Option A — create a standalone seeder that inserts finalized summaries  
linked to the same date range used by `PayrollCalculationTestSeeder`.

---

### Gap B — No `DailyAttendanceSummarySeeder`

No seeder exists specifically for `daily_attendance_summary`. The only source is:
1. `PayrollCalculationTestSeeder` (inserts directly, works for payroll testing)
2. `timekeeping:generate-daily-summaries` artisan command (generates from events, but `is_finalized=false`)

**Fix:** Create `database/seeders/DailyAttendanceSummarySeeder.php` that:
- Accepts a date range (hardcoded to current test period)
- Generates realistic Mon–Fri attendance rows `is_finalized = true`
- Used as a dependency from `PayrollCalculationTestSeeder` (or standalone)

---

### Gap C — `PayrollCalculationTestSeeder` Not Registered in `DatabaseSeeder`

The `PayrollCalculationTestSeeder` is **NOT** called from `DatabaseSeeder.php`.  
It must be run manually: `php artisan db:seed --class=PayrollCalculationTestSeeder`.

This is intentional (avoids running it on every seed), but it should be documented clearly  
and optionally gated behind an env flag for convenience.

**Fix:** Add an env-gated call in `DatabaseSeeder.php`:
```php
if (app()->environment('local', 'testing') && env('SEED_PAYROLL_TEST_DATA', false)) {
    $this->call(PayrollCalculationTestSeeder::class);
}
```

---

### Gap D — No `EmployeePayrollInfoSeeder` (standalone)

`PayrollCalculationTestSeeder` creates `EmployeePayrollInfo` inline. There's no standalone  
seeder for this. If the seeder is run after payroll periods already exist, but no payroll info  
was seeded, the calculation will fail with "Employee X has no active payroll information".

---

## 4. Files to Create

### 4.1 `database/seeders/DailyAttendanceSummarySeeder.php` (NEW)

**Purpose:** Standalone seeder for `daily_attendance_summary`. Inserts finalized attendance  
rows for all active employees for a given period. Usable without running the full timekeeping  
pipeline.

**Logic:**
- Queries all active employees
- For each working day (Mon–Fri) between `PERIOD_START` and `PERIOD_END`:
  - 5% absent
  - 20% late (5–45 min late)
  - 10% overtime (1–3 extra hours)
  - Rest: standard 8-hour day
- Sets `is_finalized = true` on all rows
- Idempotent: skips rows that already exist

**Key columns:**
```php
DailyAttendanceSummary::create([
    'employee_id'        => $employee->id,
    'attendance_date'    => $date,
    'work_schedule_id'   => 1,
    'time_in'            => '2026-03-XX 08:00:00',
    'time_out'           => '2026-03-XX 17:00:00',
    'break_start'        => '2026-03-XX 12:00:00',
    'break_end'          => '2026-03-XX 13:00:00',
    'break_duration'     => 60,
    'total_hours_worked' => 8.0,
    'regular_hours'      => 8.0,
    'overtime_hours'     => 0.0,
    'is_present'         => true,
    'is_late'            => false,
    'is_undertime'       => false,
    'is_overtime'        => false,
    'late_minutes'       => 0,
    'undertime_minutes'  => 0,
    'is_on_leave'        => false,
    'ledger_verified'    => true,
    'is_finalized'       => true,   // ← critical for payroll
    'calculated_at'      => now(),
]);
```

---

### 4.2 `database/seeders/EmployeePayrollInfoSeeder.php` (NEW)

**Purpose:** Standalone seeder that creates `employee_payroll_info` records for all active  
employees. Should be idempotent (skip employees that already have active payroll info).

**Logic:**
- Queries all active employees
- Skips each employee that already has `is_active = true` and `end_date = null` payroll info
- Assigns salary from a rotating set of presets (same as `PayrollCalculationTestSeeder`)
- Assigns government numbers (SSS, PhilHealth, Pag-IBIG, TIN)
- Sets `is_active = true`, `effective_date = 2026-01-01`, `end_date = null`

---

### 4.3 Update `database/seeders/PayrollCalculationTestSeeder.php` (MODIFY)

**Current state:** Fully working. Creates `EmployeePayrollInfo`, `PayrollPeriod`, and  
`DailyAttendanceSummary` in one seeder.

**Suggested change:** Extract the `DailyAttendanceSummary` block into `DailyAttendanceSummarySeeder`  
and call it from here, to keep logic DRY.

Before:
```php
public function run(): void
{
    $this->seedPayrollInfo();
    $this->seedPeriod();
    $this->seedAttendance();  // inline
}
```

After:
```php
public function run(): void
{
    $this->call(EmployeePayrollInfoSeeder::class);
    $this->seedPeriod();
    $this->call(DailyAttendanceSummarySeeder::class);
}
```

---

### 4.4 Update `database/seeders/DatabaseSeeder.php` (MODIFY)

Add env-gated call for payroll test data:

```php
// At the end of run(), after PayrollPeriodsSeeder:

// ── Payroll Calculation Test Data (dev/local only) ──────────────────
// Set SEED_PAYROLL_TEST_DATA=true in .env to include this.
if (app()->environment('local', 'testing') && env('SEED_PAYROLL_TEST_DATA', false)) {
    if (class_exists(\Database\Seeders\PayrollCalculationTestSeeder::class)) {
        $this->call(\Database\Seeders\PayrollCalculationTestSeeder::class);
    }
}
```

---

## 5. Seeder Run Order (Complete)

For a fully functional payroll flow from a fresh database:

```bash
# Full fresh seed (runs DatabaseSeeder — does NOT include payroll calculation test data)
php artisan migrate:fresh --seed

# Then run payroll-specific test data
php artisan db:seed --class=PayrollCalculationTestSeeder

# ↑ This seeder creates:
#   1. EmployeePayrollInfo for all active employees
#   2. PayrollPeriod: "March 2026 - 1st Half" (period_number=2026-03-1H, status='active')
#   3. DailyAttendanceSummary: Mon–Fri rows for Mar 1–15, is_finalized=true

# Verify data
php artisan tinker --execute="
    echo 'Employees with payroll info: ' . App\Models\EmployeePayrollInfo::where('is_active',true)->count() . PHP_EOL;
    echo 'PayrollPeriod: ' . App\Models\PayrollPeriod::where('period_number','2026-03-1H')->count() . PHP_EOL;
    echo 'Finalized attendance rows: ' . App\Models\DailyAttendanceSummary::where('is_finalized',true)->count() . PHP_EOL;
"

# Start queue worker
php artisan queue:work --queue=payroll,default --tries=3 --timeout=120

# Trigger calculation (from UI or tinker)
php artisan tinker --execute="
    \$period = App\Models\PayrollPeriod::where('period_number','2026-03-1H')->first();
    App\Jobs\Payroll\CalculatePayrollJob::dispatch(\$period, 1);
    echo 'Dispatched for period: ' . \$period->period_name;
"

# Watch queue worker output then verify results
php artisan tinker --execute="
    echo 'Calculations: ' . App\Models\EmployeePayrollCalculation::count() . PHP_EOL;
    echo 'Logs: '         . App\Models\PayrollCalculationLog::count() . PHP_EOL;
    echo 'Period status: '. App\Models\PayrollPeriod::where('period_number','2026-03-1H')->value('status') . PHP_EOL;
"
```

---

## 6. Implementation Tasks

| Task | File | Action | Priority |
|---|---|---|---|
| S1 | `database/seeders/DailyAttendanceSummarySeeder.php` | **CREATE** | 🟠 Medium |
| S2 | `database/seeders/EmployeePayrollInfoSeeder.php` | **CREATE** | 🟠 Medium |
| S3 | `database/seeders/PayrollCalculationTestSeeder.php` | Refactor to call S1+S2 | 🟡 Low |
| S4 | `database/seeders/DatabaseSeeder.php` | Add env-gated `PayrollCalculationTestSeeder` call | 🟡 Low |

**Note:** `PayrollCalculationTestSeeder` already works end-to-end. Tasks S1–S4 improve  
code organization and make individual seeders reusable, but are not blocking.

---

## 7. What `PayrollCalculationTestSeeder` Already Seeds Correctly

For reference, this seeder (which already exists and works) covers:

✅ `EmployeePayrollInfo` — `basic_salary`, `daily_rate`, `hourly_rate`, `tax_status`, government numbers  
✅ `PayrollPeriod` — `period_name`, `period_start`, `period_end`, `payment_date`, `timekeeping_cutoff_date`  
✅ `DailyAttendanceSummary` — Mon–Fri rows, with realistic late/overtime/absent scenarios, `is_finalized = true`  
✅ Idempotent — uses `firstOrCreate` for period; skips employees and dates that already exist  
✅ Progress output — informs how many rows created vs skipped  

The only improvement needed is making it callable from `DatabaseSeeder` with an env guard.
