# Payroll Calculation Integration Plan

**Date:** 2026-03-05  
**Scope:** Wire the `/payroll/calculations` page (and related pages) to real DB data via the existing job/event/service pipeline.

---

## Current State

### What exists (already built)
| Component | File | Status |
|---|---|---|
| DB tables | `payroll_periods`, `employee_payroll_calculations`, `payroll_calculation_logs`, `payroll_adjustments` | ✅ Migrated, **empty** |
| Queue | `QUEUE_CONNECTION=database`, `jobs` table migrated | ✅ Ready |
| Jobs | `CalculatePayrollJob`, `CalculateEmployeePayrollJob`, `FinalizePayrollJob` | ✅ Built |
| Service | `PayrollCalculationService` (10-step calc flow) | ✅ Built (uses old column names) |
| Events | `PayrollCalculationStarted/Completed/Failed`, `EmployeePayrollCalculated` | ✅ Built |
| Listeners | `LogPayrollCalculation`, `UpdatePayrollProgress`, `NotifyPayrollOfficer` | ✅ Built (bugs — see Phase 1) |
| EventServiceProvider | All events wired to listeners | ✅ Registered |
| Controller | `PayrollCalculationController` | ✅ Reads real DB (no more mocks) |
| Frontend | `Calculations/Index.tsx`, `calculations-table.tsx`, `calculation-progress-modal.tsx` | ✅ Built |

### Root cause of empty page
1. `PayrollPeriodController::calculate()` only sets `status='calculating'` but **never dispatches** `CalculatePayrollJob`
2. `PayrollCalculationService` uses **old column names** (`period->start_date`, `period->name`) instead of DB columns (`period_start`, `period_name`)
3. `UpdatePayrollProgress` listener references `$period->employees()` and `$period->calculations()->where('status','success')` — neither relation/scope exists
4. `CalculatePayrollJob` references `$period->name` (doesn't exist — should be `period_name`)
5. `FinalizePayrollJob` references `$calculation->status` (column is `calculation_status`)
6. No `payroll` queue worker is running

---

## Phase 1 — Fix Column Name Bugs in Jobs & Service
**Goal:** Make jobs/service use correct DB column names so they don't crash at runtime.

### Tasks

- [x] **1.1** Fix `CalculatePayrollJob` — replace `$this->payrollPeriod->name` → `$this->payrollPeriod->period_name`
  - File: `app/Jobs/Payroll/CalculatePayrollJob.php`

- [x] **1.2** Fix `CalculateEmployeePayrollJob` — replace `$this->payrollPeriod->name` → `$this->payrollPeriod->period_name`
  - File: `app/Jobs/Payroll/CalculateEmployeePayrollJob.php`

- [x] **1.3** Fix `FinalizePayrollJob` — replace `->where('status', 'calculated')` → `->where('calculation_status', 'calculated')`
  - File: `app/Jobs/Payroll/FinalizePayrollJob.php`

- [x] **1.4** Fix `PayrollCalculationService::startCalculation()` — replace `'processed_at'` → `'calculation_started_at'`; replace period column references (`$period->start_date` → `$period->period_start`, `$period->end_date` → `$period->period_end`)
  - File: `app/Services/Payroll/PayrollCalculationService.php`

- [x] **1.5** Fix `PayrollCalculationService::calculateEmployee()` — `DailyAttendanceSummary` query uses `$period->start_date`/`$period->end_date` → fix to `$period->period_start`/`$period->period_end`

- [x] **1.6** Fix `UpdatePayrollProgress` listener — `$event->payrollPeriod->employees()` does not exist; replace with count from `EmployeePayrollCalculation`; `->where('status','success')` → `->where('calculation_status','calculated')`
  - File: `app/Listeners/Payroll/UpdatePayrollProgress.php`

---

## Phase 2 — Wire the Dispatch in PayrollPeriodController
**Goal:** Clicking "Start Calculation" in the UI actually queues the job.

### Tasks

- [x] **2.1** In `PayrollPeriodController::calculate()`, after setting `status='calculating'`, dispatch `CalculatePayrollJob::dispatch($period, auth()->id())`
  - File: `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`
  - Also log a `PayrollCalculationLog` entry with `log_type='calculation_started'`

- [x] **2.2** In `PayrollCalculationController::store()`, dispatch `CalculatePayrollJob::dispatch($period, auth()->id())`
  - File: `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`
  - This is the "Start Calculation" button on the `/payroll/calculations` page

- [x] **2.3** In `PayrollCalculationController::recalculate()`, dispatch `CalculatePayrollJob::dispatch($period, auth()->id())` after resetting status

---

## Phase 3 — Ensure PayrollPeriod Model Has `approved_at` Column  
**Goal:** `PayrollPeriod::update(['approved_at' => now()])` must not throw.

### Tasks

- [x] **3.1** Verify migration `2026_02_17_065500_create_payroll_periods_table` has `approved_at` timestamp  
  - Confirmed: column exists in live DB

- [x] **3.2** Verify `calculation_started_at` column exists in `payroll_periods` migration  
  - Confirmed: column exists in live DB

---

## Phase 4 — Queue Worker Setup  
**Goal:** Jobs actually process (not just queue).

### Tasks

- [x] **4.1** Run queue worker during development:
  ```bash
  php artisan queue:work --queue=payroll,default --tries=3 --timeout=120
  ```
  - Started as background process (PID 19560)

- [x] **4.2** Add a `payroll` queue worker entry for production (supervisor/Caddy/scheduler). Document in `SCHEDULER_SETUP_GUIDE.md`
  - Added "💰 Payroll Queue Worker Setup" section to `SCHEDULER_SETUP_GUIDE.md`
  - Covers: dev command, supervisor config (`cameco-payroll-worker.conf`), failed job commands

- [x] **4.3** Verify `QUEUE_CONNECTION=database` is set in `.env`  
  - ✅ Confirmed at `.env` line 39: `QUEUE_CONNECTION=database`

---

## Phase 5 — Add `PayrollCalculationLog` Entries  
**Goal:** The `payroll_calculation_logs` table gets populated so logs are visible.

### Tasks

- [x] **5.1** In `CalculatePayrollJob::handle()` — call `PayrollCalculationLog::logCalculationStarted()` before dispatching employee jobs
  - Called after fetching active employees (uses `$employees->count()` for the employee count)
  - Import added: `use App\Models\PayrollCalculationLog`

- [x] **5.2** In `FinalizePayrollJob::handle()` — call `PayrollCalculationLog::logCalculationCompleted()` after aggregating totals
  - `$startTime = microtime(true)` added at start of `handle()` for accurate processing time
  - Called inside the DB transaction right after `PayrollCalculationCompleted::dispatch()`
  - Import added: `use App\Models\PayrollCalculationLog`

- [x] **5.3** In `CalculateEmployeePayrollJob::failed()` — call `PayrollCalculationLog::logCalculationFailed()` on job failure
  - Added after the existing `EmployeePayrollCalculation::create()` block in `failed()`
  - Logs employee ID, name, and exception message; wrapped in try/catch so a logging failure never masks the original error
  - Import added: `use App\Models\PayrollCalculationLog`

- [x] **5.4** The `LogPayrollCalculation` listener already writes to Laravel log — also writes to `PayrollCalculationLog` from the listener for DB-level auditability
  - Added `PayrollCalculationLog::logCalculationFailed()` inside `handleFailed()` (batch-level `PayrollCalculationFailed` event)
  - `handleStarted` / `handleCompleted` left without DB duplicates — already covered by 5.1/5.2
  - Import added: `use App\Models\PayrollCalculationLog`

---

## Phase 6 — Calculations/Show Page  
**Goal:** `/payroll/calculations/{id}` renders real per-employee data.

### Tasks

- [ ] **6.1** `PayrollCalculationController::show()` already queries `employee_payroll_calculations` — verify it works once data exists (no code change needed)

- [ ] **6.2** Create `resources/js/pages/Payroll/PayrollProcessing/Calculations/Show.tsx` — a page for per-employee breakdown  
  - Use existing component `employee-calculation-details.tsx`
  - Show table: employee name, basic pay, deductions breakdown, net pay, status

- [ ] **6.3** Add route for show page (already exists in `routes/payroll.php`):  
  ```php
  Route::get('/calculations/{id}', [PayrollCalculationController::class, 'show'])->name('calculations.show');
  ```

---

## Phase 7 — Payroll Review Page Integration  
**Goal:** `/payroll/review` shows real data from calculated periods.

### Tasks

- [ ] **7.1** Read `PayrollReviewController.php` — determine if it uses mock data
- [ ] **7.2** If mock: replace `index()` to query `payroll_periods` with status in `['calculated','under_review','pending_approval']` + join `employee_payroll_calculations` aggregates

---

## Phase 8 — Seeder for Testing  
**Goal:** Populate sample periods + calculations for UI testing without running real jobs.

### Tasks

- [ ] **8.1** Create `database/seeders/PayrollPeriodSeeder.php` — inserts 3–5 payroll periods with mixed statuses (`draft`, `calculating`, `calculated`, `approved`)

- [ ] **8.2** Create `database/seeders/EmployeePayrollCalculationSeeder.php` — for each seeded period, insert mock `employee_payroll_calculations` rows using real employee IDs from the DB

- [ ] **8.3** Register seeders in `DatabaseSeeder.php` behind a dev-only flag

---

## Phase 9 — End-to-End Test  
**Goal:** Full flow works from UI → queue → DB → UI refresh.

### Manual Test Steps

1. Go to `/payroll/periods` → create a payroll period
2. Click "Calculate" on the period
3. Verify period status changes to `calculating` in DB
4. Start queue worker: `php artisan queue:work --queue=payroll`
5. Watch `employee_payroll_calculations` get populated
6. Go to `/payroll/calculations` → period appears with real progress %
7. After job completes → status shows `completed`
8. Click into period → `/payroll/calculations/{id}` → employee breakdown visible
9. Click "Approve" → status becomes `approved`
10. Check `payroll_calculation_logs` → entries present

---

## Column Name Reference (DB → Service/Job mapping)

| DB Column (`payroll_periods`) | Old (wrong) reference | Correct reference |
|---|---|---|
| `period_name` | `$period->name` | `$period->period_name` |
| `period_start` | `$period->start_date` | `$period->period_start` |
| `period_end` | `$period->end_date` | `$period->period_end` |
| `timekeeping_cutoff_date` | `$period->cutoff_date` | `$period->timekeeping_cutoff_date` |
| `payment_date` | `$period->pay_date` | `$period->payment_date` |
| `calculation_started_at` | `$period->processed_at` | `$period->calculation_started_at` |

| DB Column (`employee_payroll_calculations`) | Old (wrong) reference | Correct reference |
|---|---|---|
| `calculation_status` | `$calc->status` | `$calc->calculation_status` |
| `final_net_pay` | `$calc->net_pay` (in finalize) | check `$calc->final_net_pay ?? $calc->net_pay` |

---

## Files to Modify (Summary)

| File | Phase | Change |
|---|---|---|
| `app/Jobs/Payroll/CalculatePayrollJob.php` | 1.1 | Fix `->name` → `->period_name` |
| `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` | 1.2 | Fix `->name` → `->period_name` |
| `app/Jobs/Payroll/FinalizePayrollJob.php` | 1.3 | Fix `->status` → `->calculation_status` |
| `app/Services/Payroll/PayrollCalculationService.php` | 1.4, 1.5 | Fix all period column name references |
| `app/Listeners/Payroll/UpdatePayrollProgress.php` | 1.6 | Fix broken relation + status filter |
| `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php` | 2.1 | Dispatch `CalculatePayrollJob` |
| `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php` | 2.2, 2.3 | Dispatch job from `store()` + `recalculate()` |
| `app/Jobs/Payroll/CalculatePayrollJob.php` | 5.1 | Add `PayrollCalculationLog::logCalculationStarted()` |
| `app/Jobs/Payroll/FinalizePayrollJob.php` | 5.2 | Add `PayrollCalculationLog::logCalculationCompleted()` |
| `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` | 5.3 | Add log in `failed()` |

## Files to Create

| File | Phase |
|---|---|
| `resources/js/pages/Payroll/PayrollProcessing/Calculations/Show.tsx` | 6.2 |
| `database/seeders/PayrollPeriodSeeder.php` | 8.1 |
| `database/seeders/EmployeePayrollCalculationSeeder.php` | 8.2 |
| `database/migrations/..._add_approved_at_to_payroll_periods.php` (if needed) | 3.1 |

---

## Progress Tracking

### Phase 1 — Fix Column Name Bugs
- [ ] 1.1 CalculatePayrollJob column names
- [ ] 1.2 CalculateEmployeePayrollJob column names
- [ ] 1.3 FinalizePayrollJob calculation_status
- [ ] 1.4 PayrollCalculationService::startCalculation columns
- [ ] 1.5 PayrollCalculationService::calculateEmployee period columns
- [ ] 1.6 UpdatePayrollProgress listener

### Phase 2 — Wire Dispatch
- [ ] 2.1 PayrollPeriodController::calculate() dispatches job
- [ ] 2.2 PayrollCalculationController::store() dispatches job
- [x] 2.3 PayrollCalculationController::recalculate() dispatches job

### Phase 3 — Column Verification
- [x] 3.1 Verify approved_at in payroll_periods (confirmed in live DB)
- [x] 3.2 Verify calculation_started_at in payroll_periods (confirmed in live DB)

### Phase 4 — Queue Worker
- [ ] 4.1 Run worker locally
- [ ] 4.2 Document production setup

### Phase 5 — Calculation Logs
- [ ] 5.1 Log on job start
- [ ] 5.2 Log on finalization
- [ ] 5.3 Log on job failure

### Phase 6 — Show Page
- [ ] 6.1 Verify show() controller works
- [ ] 6.2 Create Show.tsx
- [ ] 6.3 Verify route

### Phase 7 — Review Page
- [ ] 7.1 Audit PayrollReviewController
- [ ] 7.2 Replace mock if needed

### Phase 8 — Seeders
- [ ] 8.1 PayrollPeriodSeeder
- [ ] 8.2 EmployeePayrollCalculationSeeder
- [ ] 8.3 Register in DatabaseSeeder

### Phase 9 — E2E Test
- [ ] Full manual flow pass
