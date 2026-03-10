# Payroll Clean-Slate Seeder Rebuild

**Feature:** Replace messy test seeders with properly structured Feb 2026 (1st Half + 2nd Half) payroll seed data  
**Status:** In Progress  
**Priority:** HIGH  
**Created:** 2026-02-20  

---

## 🎯 Objective

The existing `PayrollCalculationTestSeeder` produced dirty data:
- Attendance range spans Feb 16 → Mar 13 (crosses two payroll periods)
- `is_finalized = false` on all attendance rows — calculation produces ₱0 for every employee
- Date mismatch between the period dates and the actual attendance rows
- Cancelling/failed period records leftover in the DB

**Goal:** Delete all confused seeder data and rebuild two clean, properly structured payroll test periods:

| Period | Dates | Period Number | Working Days |
|--------|-------|---------------|--------------|
| Feb 2026 — 1st Half | Feb 2 – Feb 13 | `2026-02-1H` | 10 days (Mon–Fri, Feb 1 is Sunday) |
| Feb 2026 — 2nd Half | Feb 16 – Feb 27 | `2026-02-2H` | 10 days (Mon–Fri, Feb 28 is Saturday) |

Both periods must have `is_finalized = true` on all attendance rows so `PayrollCalculationService::calculateEmployee()` picks them up.

---

## 🔍 Understanding the Calculation Pipeline

### Full Flow (studied from source code)

```
[UI: /payroll/calculations]
        │
        ▼
PayrollCalculationController::store()           ← sets period status = 'calculating'
        │
        ▼
CalculatePayrollJob (ShouldQueue, Batchable)     ← runs on 'payroll' queue
    │ fetches Employee::where('status','active')
    │ filters: only employees WITH ->payrollInfo
    │ creates Bus::batch($employeeJobs)
        │
        ▼
CalculateEmployeePayrollJob × N (Batchable)     ← one per active employee
    │ calls PayrollCalculationService::calculateEmployee()
        │
        ▼
PayrollCalculationService::calculateEmployee()  ← the real work
    ├── Step 1:  getActivePayrollInfo($employee)
    │            → throws ValidationException if none found
    │            → requires is_active=true AND end_date=null
    │
    ├── Step 2:  DailyAttendanceSummary
    │            ->whereBetween('attendance_date', [period_start, period_end])
    │            ->where('is_finalized', TRUE)   ⚠️  CRITICAL — without this, 0 days worked
    │
    ├── Step 3:  Count present days, sum hours, late_minutes, overtime_hours
    ├── Step 4:  calculateBasicPay(daysWorked, payrollInfo)
    ├── Step 5:  calculateOvertimePay(overtimeHours, payrollInfo)
    ├── Step 6:  getEmployeeComponents (salary components)
    ├── Step 7:  getActiveAllowances
    ├── Step 8:  grossPay = basic + OT + components + allowances
    ├── Step 9:  SSS, PhilHealth, Pag-IBIG contributions
    ├── Step 10: withholdingTax (on taxable income)
    ├── Step 11: getActiveDeductions
    ├── Step 12: processLoanDeduction
    ├── Step 13: late/undertime deductions
    ├── Step 14: allDeductions total
    ├── Step 15: netPay = grossPay - allDeductions
    ├── Step 16: forceDelete() any existing calculation (safe recalc)
    └── Step 17: EmployeePayrollCalculation::create([calculation_status => 'calculated'])
        │
        ▼
FinalizePayrollJob                              ← runs AFTER batch via ->then()
    │ sums all calculations
    └── PayrollPeriod::update([status => 'calculated', total_net_pay => ...])
```

### Critical Pre-conditions for Calculation to Succeed

| # | Requirement | Column / Constraint |
|---|-------------|---------------------|
| 1 | Employee must be `status = 'active'` | `employees.status` |
| 2 | Must have active payroll info | `employee_payroll_info.is_active = true` AND `end_date IS NULL` |
| 3 | Attendance rows in period date range | `daily_attendance_summary.attendance_date BETWEEN period_start AND period_end` |
| 4 | **Attendance MUST be finalized** | `daily_attendance_summary.is_finalized = TRUE` |
| 5 | Period must exist with `status = 'active'` | `payroll_periods.status = 'active'` |
| 6 | `job_batches` table must exist | `php artisan migrate` (Laravel batch infrastructure) |
| 7 | Queue worker running on `payroll` queue | `php artisan queue:work --queue=payroll,default` |

### Why `is_finalized = true` Matters

```php
// PayrollCalculationService.php line 122
$attendanceSummaries = DailyAttendanceSummary::query()
    ->where('employee_id', $employee->id)
    ->whereBetween('attendance_date', [$period->period_start, $period->period_end])
    ->where('is_finalized', true)   // ← only finalized rows count
    ->get();

$daysWorked = $attendanceSummaries->where('is_present', true)->count();
// If is_finalized = false → attendanceSummaries is empty → daysWorked = 0 → basicPay = 0
```

---

## 📋 Data to Delete (Reset Script)

Before running clean seeders, these tables need purging of test data:

```sql
-- 1. Remove stale attendance summaries (test period range)
DELETE FROM daily_attendance_summary
WHERE attendance_date BETWEEN '2026-02-01' AND '2026-03-31';

-- 2. Remove payroll calculations for test periods
DELETE FROM employee_payroll_calculations
WHERE payroll_period_id IN (
    SELECT id FROM payroll_periods
    WHERE period_number IN ('2026-02-1H', '2026-02-2H')
);

-- 3. Remove the test payroll periods themselves
DELETE FROM payroll_periods
WHERE period_number IN ('2026-02-1H', '2026-02-2H');

-- 4. Optionally: soft-delete employee_payroll_info and re-seed
-- (only needed if salary data is wrong — skip if it's fine)
```

> **Note:** The reset is baked directly into the seeder via `forceDelete()` calls so it is idempotent and safe to re-run.

---

## 📐 Phased Implementation Plan

---

### Phase 1 — Study & Verify Pre-conditions

**Goal:** Confirm the DB state before touching any code.

#### Task 1.1 — Check employee count and statuses
```bash
php artisan tinker --execute="echo App\Models\Employee::count() . ' total, ' . App\Models\Employee::where('status','active')->count() . ' active';"
```
✅ Expected: 42 total, ~42 active

**Status:** ✅ **COMPLETE**  
**Result:**
```
Total employees: 42
Active employees: 40
Other statuses: 2 (terminated)
```

**Action Taken:** Ran `php artisan db:seed --class=BulkEmployeeSeeder` to add 31 employees to the existing 11 from EmployeeSeeder, bringing total to 42. Of these, 40 are active and 2 are terminated.

#### Task 1.2 — Verify employee_payroll_info coverage
```bash
php artisan tinker --execute="echo App\Models\EmployeePayrollInfo::where('is_active',true)->whereNull('end_date')->count() . ' employees have active payroll info';"
```
✅ Expected: 42 (all employees must have payroll info)

**Status:** ✅ **COMPLETE**  
**Result:**
```
Active employees with payroll info: 40 / 40
```

**Action Taken:** Ran `php artisan db:seed --class=EmployeePayrollInfoSeeder` which created 29 new payroll info records (skipped 11 that already existed). All 40 active employees now have:
- `is_active = true`
- `end_date = null`
- Realistic salary presets (cycling through 5 salary tiers)
- Valid government registration numbers (SSS, PhilHealth, Pag-IBIG, TIN)
- Bank transfer payment method configured

#### Task 1.3 — Check for leftover stale periods
```bash
php artisan tinker --execute="App\Models\PayrollPeriod::get()->each(fn(\$p) => print(\$p->period_number . ' | ' . \$p->status . PHP_EOL));"
```
Note any periods in `calculating`, `cancelled`, or `calculation_failed` status — these must be reset.

**Status:** ✅ **COMPLETE**  
**Result:**
```
PayrollPeriod table is completely empty
No stale, stuck, or cancelled periods exist
```

**Analysis:** The database has a clean slate with no leftover period records from previous test runs. No reset/cleanup needed for this task.

#### Task 1.4 — Verify job_batches table exists
```bash
php artisan migrate:status | grep job_batches
```
If missing: `php artisan migrate`

**Status:** ✅ **COMPLETE**  
**Result:**
```
job_batches table exists and is ready
Current row count: 0
```

**Analysis:** The Laravel batch infrastructure table `job_batches` is properly created and available. This table is required by `Bus::batch()` for the payroll calculation batch dispatch to work correctly. No migration needed.

---

### Phase 1 Summary — ✅ **COMPLETE**

All pre-condition checks have been successfully verified. The database is ready for Phase 2 (data deletion) and Phase 3 (clean seeder creation).

| Task | Status | Finding |
|------|--------|---------|
| 1.1 — Employee count | ✅ | 42 total, 40 active, 2 terminated |
| 1.2 — Payroll info coverage | ✅ | 40/40 active employees have payroll info |
| 1.3 — Stale periods | ✅ | No stale periods exist (clean slate) |
| 1.4 — job_batches table | ✅ | Table exists and is ready |

**Database State Confirmed:**
- ✅ 40 active employees with complete payroll configuration
- ✅ Empty PayrollPeriod table (no conflicts to clean up)
- ✅ Laravel batch infrastructure in place (job_batches table ready)
- ✅ All prerequisites met to proceed with new seeder creation

---

### Phase 2 — Delete Old Test Data

**Goal:** Wipe all confused attendance + period data from the Feb 2026 range.

**Status:** ✅ **COMPLETE**

#### Task 2.1 — Run reset via Artisan Tinker
```bash
php artisan tinker
```

```php
// Inside tinker:
use App\Models\DailyAttendanceSummary;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

// Delete attendance in Feb–Mar 2026 range
$deletedAttendance = DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-01', '2026-03-31'])->forceDelete();
echo "Deleted attendance rows: $deletedAttendance\n";

// Delete calculations tied to Feb 2026 periods
$periodIds = PayrollPeriod::whereIn('period_number', ['2026-02-1H', '2026-02-2H'])->pluck('id');
$deletedCalcs = EmployeePayrollCalculation::whereIn('payroll_period_id', $periodIds)->forceDelete();
echo "Deleted calculations: $deletedCalcs\n";

// Delete the periods
$deletedPeriods = PayrollPeriod::whereIn('period_number', ['2026-02-1H', '2026-02-2H'])->forceDelete();
echo "Deleted periods: $deletedPeriods\n";
```

**Result:**
```
Deleted attendance rows: 0
Deleted calculations: 0
Deleted periods: 0
```

**Analysis:** The database is already in a clean state with no leftover test data from previous runs. No deletions were necessary.

#### Task 2.2 — Verify clean state
```bash
php artisan tinker --execute="echo 'Attendance: ' . App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-01','2026-03-31'])->count();"
```

**Result:**
```
Final State After Phase 2:
- Attendance rows in Feb-Mar range: 0 ✅
- Payroll calculations total: 0 ✅
- Payroll periods total: 0 ✅
```

**Conclusion:** Database is completely clean and ready for new seeder creation in Phase 3.

---

### Phase 3 — Create New Clean Seeders

**Goal:** Build two new seeder classes — one for each February half — that are fully self-contained, idempotent, and production-safe.

#### Architecture Decision

Split into **3 seeder classes** for clarity and reusability:

| Seeder | Responsibility |
|--------|---------------|
| `EmployeePayrollInfoSeeder` | Ensures all employees have active payroll info. Idempotent. |
| `FebruaryFirstHalfPayrollSeeder` | Feb 1–15 period + finalized attendance for all active employees |
| `FebruarySecondHalfPayrollSeeder` | Feb 16–28 period + finalized attendance for all active employees |

All three are called from a coordinator: `FullPayrollTestDataSeeder`.

---

#### Task 3.1 — Implement `EmployeePayrollInfoSeeder`

**File:** `database/seeders/EmployeePayrollInfoSeeder.php`

**Status:** ✅ **COMPLETE** (Already implemented and verified working)

**Implementation Details:**

✅ **Logic implemented correctly:**
1. Fetches all active employees (`Employee::where('status', 'active')->get()`)
2. For each employee: skips if already has `is_active=true AND end_date IS NULL`
3. Creates fresh `EmployeePayrollInfo` with realistic salary presets (cycling 5 tiers)

✅ **Salary Presets (5 tiers, cycling):**
```php
['basic_salary' => 35000, 'daily_rate' => 1346.15, 'hourly_rate' => 168.27],
['basic_salary' => 28000, 'daily_rate' => 1076.92, 'hourly_rate' => 134.62],
['basic_salary' => 22000, 'daily_rate' => 846.15,  'hourly_rate' => 105.77],
['basic_salary' => 18000, 'daily_rate' => 692.31,  'hourly_rate' => 86.54],
['basic_salary' => 15000, 'daily_rate' => 576.92,  'hourly_rate' => 72.12],
```

✅ **All key fields correctly configured:**
- `is_active = true`, `end_date = null`
- `salary_type = 'monthly'`
- `tax_status = 'S'` (Single)
- `effective_date = '2026-01-01'`
- Valid SSS, PhilHealth, Pag-IBIG, TIN numbers (format-valid, procedurally generated)
- `payment_method = 'bank_transfer'`
- `rdo_code = '055'`
- Bank details: BDO Unibank with valid account numbers
- Benefits: Rice ✅, Uniform ❌, Laundry ❌, Medical ✅

✅ **Idempotency verified:**
- Safe to run multiple times
- Checks for existing active payroll info before creating
- Skips employees that already have records

**Verification Result:**
```
Active employees: 40
Employees with active payroll info: 40
Coverage: 100% ✅
```

**Already executed:** This seeder was run during Phase 1, Task 1.2, creating 29 new records and skipping 11 existing ones. All 40 active employees now have complete payroll configuration.

---

#### Task 3.2 — Implement `FebruaryFirstHalfPayrollSeeder`

**File:** `database/seeders/FebruaryFirstHalfPayrollSeeder.php`

**Status:** ✅ **COMPLETE** (March 6, 2026)

**Period Data:**
```php
const PERIOD_NUMBER   = '2026-02-1H';
const PERIOD_NAME     = 'February 2026 - 1st Half';
const PERIOD_START    = '2026-02-02';   // Feb 1 is Sunday — first workday is Feb 2
const PERIOD_END      = '2026-02-13';   // Last workday before Feb 16
const PAYMENT_DATE    = '2026-02-20';
const CUTOFF_DATE     = '2026-02-13';
```

**Working Days (10 days):**
```
Feb 2 (Mon), Feb 3, Feb 4, Feb 5, Feb 6 (Fri)
Feb 9 (Mon), Feb 10, Feb 11, Feb 12, Feb 13 (Fri)
```

**Implementation Complete:**
1. ✅ **Reset Function:** `forceDelete()` any existing period with `period_number = '2026-02-1H'` and its calculations + attendance
2. ✅ **PayrollPeriod Created:** Record created with `status = 'active'`, `timekeeping_data_locked = false`, `period_type = 'regular'`
3. ✅ **Attendance Seeded:** For each active employee, created 10 `DailyAttendanceSummary` rows
   - ✅ **`is_finalized = TRUE`** on all 400 records (mandatory for calculation)
   - ✅ Randomization working: 93% present, 7% absent, 21% late, 15% overtime
   - ✅ `is_present`, `time_in`, `time_out` set correctly based on randomization
   - ✅ `work_schedule_id` properly assigned

**Verification Results:**
```
Period Number: 2026-02-1H ✓
Period Name: February 2026 - 1st Half ✓
Period Start: 2026-02-02 ✓
Period End: 2026-02-13 ✓
Status: active ✓
Working Days: 10 (Mon–Fri) ✓

Total Attendance Records: 400 (40 employees × 10 days) ✓
  - Present: 373 (93.2%) ✓
  - Absent: 27 (6.8%) ✓
  - Late Arrivals: 85 (21.3%) ✓
  - Overtime: 60 (15.0%) ✓
  - ALL FINALIZED: 400 (100%) ✓

Status: READY FOR PAYROLL CALCULATION ✓
```

**Usage:**
```bash
php artisan db:seed --class=FebruaryFirstHalfPayrollSeeder
```

---

#### Task 3.3 — Implement `FebruarySecondHalfPayrollSeeder`

**File:** `database/seeders/FebruarySecondHalfPayrollSeeder.php`

**Period Data:**
```php
const PERIOD_NUMBER   = '2026-02-2H';
const PERIOD_NAME     = 'February 2026 - 2nd Half';
const PERIOD_START    = '2026-02-16';   // First workday of 2nd half
const PERIOD_END      = '2026-02-27';   // Last workday (Feb 28 is Saturday)
const PAYMENT_DATE    = '2026-03-05';
const CUTOFF_DATE     = '2026-02-27';
```

**Working Days (10 days):**
```
Feb 16 (Mon), Feb 17, Feb 18, Feb 19, Feb 20 (Fri)
Feb 23 (Mon), Feb 24, Feb 25, Feb 26, Feb 27 (Fri)
```

**Status:** ✅ **COMPLETE** (March 6, 2026)

**Implementation Complete:**
1. ✅ **Reset Function:** `forceDelete()` any existing period with `period_number = '2026-02-2H'` and its calculations + attendance
2. ✅ **PayrollPeriod Created:** Record created with `status = 'active'`, `timekeeping_data_locked = false`, `period_type = 'regular'`
3. ✅ **Attendance Seeded:** For each active employee, created 10 `DailyAttendanceSummary` rows
     - ✅ **`is_finalized = TRUE`** on all 400 records (mandatory for calculation)
     - ✅ Randomization working: 93.5% present, 6.5% absent, 16.3% late, 19.0% overtime
     - ✅ `is_present`, `time_in`, `time_out` set correctly based on randomization
     - ✅ `work_schedule_id` properly assigned

**Verification Results:**
```
Period Number: 2026-02-2H ✓
Period Name: February 2026 - 2nd Half ✓
Period Start: 2026-02-16 ✓
Period End: 2026-02-27 ✓
Status: active ✓
Working Days: 10 (Mon–Fri) ✓

Total Attendance Records: 400 (40 employees × 10 days) ✓
    - Present: 374 (93.5%) ✓
    - Absent: 26 (6.5%) ✓
    - Late Arrivals: 65 (16.3%) ✓
    - Overtime: 76 (19.0%) ✓
    - ALL FINALIZED: 400 (100%) ✓

Status: READY FOR PAYROLL CALCULATION ✓
```

**Usage:**
```bash
php artisan db:seed --class=FebruarySecondHalfPayrollSeeder
```

---

#### Task 3.4 — Implement `FullPayrollTestDataSeeder` (coordinator)

**File:** `database/seeders/FullPayrollTestDataSeeder.php`

**Purpose:** Single entry point: calls the three seeders in order.

```php
public function run(): void
{
    $this->call([
        EmployeePayrollInfoSeeder::class,
        FebruaryFirstHalfPayrollSeeder::class,
        FebruarySecondHalfPayrollSeeder::class,
    ]);
}
```

**Usage:**
```bash
php artisan db:seed --class=FullPayrollTestDataSeeder
```

---

### Phase 4 — Validate Seeded Data

**Goal:** Confirm the data is correct before triggering calculation.

#### Task 4.1 — Check period records
```bash
php artisan tinker --execute="
App\Models\PayrollPeriod::whereIn('period_number', ['2026-02-1H','2026-02-2H'])
    ->get(['period_number','period_start','period_end','status'])
    ->each(fn(\$p) => print(\$p->period_number . ' | ' . \$p->period_start . ' to ' . \$p->period_end . ' | ' . \$p->status . PHP_EOL));
"
```
✅ Expected:
```
2026-02-1H | 2026-02-02 to 2026-02-13 | active
2026-02-2H | 2026-02-16 to 2026-02-27 | active
```

#### Task 4.2 — Count finalized attendance rows
```bash
php artisan tinker --execute="
echo '1H finalized: ' . App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02','2026-02-13'])->where('is_finalized', true)->count() . PHP_EOL;
echo '2H finalized: ' . App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-16','2026-02-27'])->where('is_finalized', true)->count() . PHP_EOL;
"
```
✅ Expected:  
`1H finalized: ~420` (42 employees × 10 days; some absent rows still exist but is_finalized=true)  
`2H finalized: ~420`

#### Task 4.3 — Verify all active employees have payroll info
```bash
php artisan tinker --execute="
\$active = App\Models\Employee::where('status','active')->count();
\$withInfo = App\Models\Employee::where('status','active')
    ->whereHas('payrollInfo', fn(\$q) => \$q->where('is_active',true)->whereNull('end_date'))
    ->count();
echo \"Active: \$active, With payroll info: \$withInfo\";
"
```
✅ Expected: `Active: 42, With payroll info: 42`  
(If counts differ, run `EmployeePayrollInfoSeeder` again)

#### Task 4.4 — Spot-check one employee's attendance
```bash
php artisan tinker --execute="
\$emp = App\Models\Employee::first();
echo 'Employee: ' . \$emp->id . PHP_EOL;
App\Models\DailyAttendanceSummary::where('employee_id', \$emp->id)
    ->whereBetween('attendance_date', ['2026-02-02','2026-02-13'])
    ->get(['attendance_date','is_present','is_finalized','total_hours_worked'])
    ->each(fn(\$r) => print(\$r->attendance_date . ' | present=' . (int)\$r->is_present . ' | finalized=' . (int)\$r->is_finalized . PHP_EOL));
"
```
✅ Expected: 10 rows, all `is_finalized=1`

---

### Phase 5 — Trigger Calculation & Verify Results

**Goal:** Run payroll calculation for both periods end-to-end via the UI.

#### Task 5.1 — Start queue worker
```bash
php artisan queue:work --queue=payroll,default --tries=3
```
Keep this terminal open. Watch for success/error logs.

#### Task 5.2 — Trigger 1st Half calculation from UI
1. Navigate to `/payroll/calculations`
2. Find **"February 2026 - 1st Half"** → Status: `Active`
3. Click **"Start Calculation"** → select type: `regular`
4. Period status changes to `Calculating`
5. Queue worker processes ~42 jobs
6. `FinalizePayrollJob` runs → status changes to `Calculated`

#### Task 5.3 — Verify 1st Half results
- Click on the period → view employee calculations
- All ~42 employees should have `calculation_status = 'calculated'`
- `basic_pay > 0` for employees present ≥ 1 day
- `net_pay` = gross − deductions (should be reasonable positive values)
- No `exception` status rows (unless employee truly has no payroll info)

#### Task 5.4 — Trigger 2nd Half calculation
Repeat Task 5.2 for **"February 2026 - 2nd Half"**.

#### Task 5.5 — Final validation checklist

| Check | Expected |
|-------|----------|
| Period 1H status | `calculated` |
| Period 2H status | `calculated` |
| Employee calc count per period | ~42 |
| `calculation_status` on each | `calculated` |
| `basic_pay` range | ₱5,769 – ₱13,462 (10/26 of monthly) |
| `net_pay` | Positive, after deductions |
| Queue worker errors | None |
| `employee_payroll_calculations` rows total | ~84 (42 × 2 periods) |

---

## 🔧 Implementation Notes

### File Structure After Implementation

```
database/seeders/
  ├── EmployeePayrollInfoSeeder.php           ← NEW: idempotent payroll info seeder
  ├── FebruaryFirstHalfPayrollSeeder.php      ← NEW: Feb 1–15 period + attendance
  ├── FebruarySecondHalfPayrollSeeder.php     ← NEW: Feb 16–28 period + attendance
  ├── FullPayrollTestDataSeeder.php           ← NEW: coordinator
  └── PayrollCalculationTestSeeder.php        ← OLD: can be deleted after new ones verified
```

### Idempotency Rules

Each seeder **must** be safe to run multiple times:
- Use `updateOrCreate` or check-then-skip for `EmployeePayrollInfo`
- Use `forceDelete()` for existing test periods/attendance before re-creating
- `DailyAttendanceSummary`: delete by `(employee_id, attendance_date)` or by date range before inserting

### The `is_finalized` Contract

```php
// ALWAYS set this to true in test seeders:
'is_finalized' => true,

// Setting it to false means:
// → PayrollCalculationService skips the row
// → daysWorked = 0
// → basicPay = ₱0.00
// → netPay = ₱0.00 (or negative due to deductions)
```

### Employee Status Must be `active`

```php
// CalculatePayrollJob line 68:
$employees = Employee::where('status', 'active')->with('payrollInfo')->get();
// Only 'active' employees are batch-processed.
// If an employee's status is 'inactive' or 'terminated', they are skipped entirely.
```

### Period Status Flow

```
'active'       → trigger calculation → 'calculating'
'calculating'  → all jobs done → FinalizePayrollJob → 'calculated'
'calculated'   → review/approve actions available
```

If a period is stuck in `calculating` or `cancelled`, reset it:
```bash
php artisan tinker --execute="
App\Models\PayrollPeriod::where('period_number','2026-02-1H')
    ->update(['status' => 'active', 'calculation_started_at' => null]);
"
```

---

## ✅ Acceptance Criteria

- [ ] All old Feb 2026 test data deleted from DB
- [ ] `EmployeePayrollInfoSeeder` — all 42 employees have active payroll info
- [ ] `FebruaryFirstHalfPayrollSeeder` — period `2026-02-1H` created, 10 working days × 42 employees = 420 finalized attendance rows
- [ ] `FebruarySecondHalfPayrollSeeder` — period `2026-02-2H` created, 10 working days × 42 employees = 420 finalized attendance rows
- [ ] `FullPayrollTestDataSeeder` — single `php artisan db:seed --class=FullPayrollTestDataSeeder` produces all of the above
- [ ] Payroll calculation triggered for 1st Half → all employees show `calculated` status
- [ ] Payroll calculation triggered for 2nd Half → all employees show `calculated` status
- [ ] `net_pay > 0` for every employee with at least 1 present day
- [ ] No queue worker errors during batch processing

---

## 🚨 Common Pitfalls

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| `is_finalized = false` | All employees show ₱0 in calculation | Set `is_finalized = true` in seeder |
| Employee not `status = 'active'` | Employee silently skipped by job | Update `employees.status = 'active'` |
| No active payroll info | `ValidationException` in logs, `exception` calc status | Re-run `EmployeePayrollInfoSeeder` |
| Period not `status = 'active'` | "Start Calculation" button disabled or fails | Update period status to `active` |
| `job_batches` missing | `Bus::batch()` throws missing table error | Run `php artisan migrate` |
| Queue not running | Period stays `calculating` forever | Run `php artisan queue:work --queue=payroll,default` |
| `forceDelete` not used | Unique constraint violation on recalculation | Always `forceDelete()` before re-inserting |

---

## 📁 Related Files

| File | Purpose |
|------|---------|
| `app/Services/Payroll/PayrollCalculationService.php` | Core calculation logic, 17-step pipeline |
| `app/Jobs/Payroll/CalculatePayrollJob.php` | Batch orchestrator (fetches active employees) |
| `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` | Per-employee calculation job |
| `app/Jobs/Payroll/FinalizePayrollJob.php` | Runs after batch, sets period → calculated |
| `app/Models/PayrollPeriod.php` | Period model, status transitions |
| `app/Models/DailyAttendanceSummary.php` | Attendance model, `is_finalized` flag |
| `app/Models/EmployeePayrollInfo.php` | Salary config per employee |
| `app/Models/EmployeePayrollCalculation.php` | Result record per employee per period |
| `database/seeders/PayrollCalculationTestSeeder.php` | OLD seeder (to be replaced) |
