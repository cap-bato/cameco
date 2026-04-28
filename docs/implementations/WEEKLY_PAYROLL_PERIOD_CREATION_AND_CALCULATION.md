# Weekly Payroll Period Creation and Calculation Implementation

Created: 2026-03-17  
Priority: High  
Risk: Medium (core payroll math, deduction timing, and period creation flow)

---

## 1. Objective

Implement weekly payroll period creation that is fully calculable end-to-end, with these required rules:

1. Monthly deductions are spread across exactly 4 weeks.
2. Government deductions are computed from the employee's gross pay for that specific week.
3. Weekly payroll periods can be created from UI and processed by existing calculation jobs.

---

## 2. Business Rules

### 2.1 Weekly basic pay for monthly salaried employees

For salary type = monthly and period frequency = weekly:

$$
weekly\_basic\_pay = \frac{monthly\_salary \times 12}{52}
$$

### 2.2 Monthly deduction spread over 4 weeks

For deduction timing mode `spread_monthly_4`:

$$
weekly\_deduction = \frac{monthly\_amount}{4}
$$

Apply only when weekly position in month is 1 to 4.  
If week position is 5, monthly-split deduction is 0 for that period.

### 2.3 Government deductions based on weekly gross pay

Government deduction basis must use weekly gross pay (calculated for the current payroll period), not fixed monthly basic salary.

- SSS basis input: weekly gross pay (or equivalent weekly compensation basis)
- PhilHealth basis input: weekly gross pay
- Pag-IBIG basis input: weekly gross pay
- Withholding tax projection: derived from weekly taxable pay, annualized consistently

---

## 3. Current Gaps (Codebase)

1. Payroll period `period_type` is validated in controller request, but persisted as `regular` in database record creation, so frequency is inferred later instead of stored.
2. `inferFrequency()` currently uses a `<= 10` threshold for bi-weekly, which can misclassify true 14-day periods.
3. `calculateBasicPay()` currently assumes monthly salary is always divided by 2 (semi-monthly behavior).
4. Deduction timing modes only include `per_cutoff`, `monthly_only`, `split_monthly`; no weekly-specific 4-way spread mode.
5. Government contribution calculation functions currently use payroll info salary fields, not computed weekly gross.
6. Frontend period form timing options do not expose a weekly 4-way spread choice.

---

## 4. Implementation Phases and Tasks

## Phase 1: Weekly Period Creation Contract (Backend + Frontend)

Goal: Ensure weekly period creation payload is valid, explicit, and can carry weekly deduction timing mode.

Tasks:
- [ ] Update payroll period validation to allow weekly spread timing mode in create/update endpoints.
- [ ] Ensure period creation/update preserves period-level deduction timing overrides in `calculation_config`.
- [ ] Ensure weekly frequency remains inferable from date range in all read paths.
- [ ] Keep existing period lifecycle (`draft -> calculating -> calculated`) unchanged.

Files to touch:
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`
- `resources/js/pages/Payroll/PayrollProcessing/Periods/Index.tsx`
- `resources/js/components/payroll/period-form-modal.tsx`
- `resources/js/types/payroll-pages.ts`

Deliverable:
- Weekly period can be created from UI with deduction timing config suitable for weekly processing.

---

## Phase 2: Frequency and Position Helpers in Calculation Service

Goal: Add period-position intelligence needed for weekly 4-way deduction spread.

Tasks:
✅ Add `inferPeriodFrequency(PayrollPeriod $period)` helper in calculation service.
✅ Add `getWeeklyPositionInMonth(PayrollPeriod $period)` helper (returns 1..5 using period start date).
✅ Add `getPeriodPosition(PayrollPeriod $period)` helper returning:
  - frequency
  - periodHalf (for semi-monthly compatibility)
  - isLastInMonth
  - weeklyPositionInMonth
✅ Fix frequency threshold mapping so bi-weekly 14-day spans are classified correctly.

Files touched:
* `app/Services/Payroll/PayrollCalculationService.php`

Deliverable:
✅ Calculation flow can determine weekly period position and support month-boundary behavior.

---
Implementation Notes (2026-03-18):
* Added helpers for period frequency, weekly position, and period position info.
* Updated basic pay calculation to use correct formula for weekly periods.
* Frequency mapping now correctly distinguishes weekly, bi-weekly, semi-monthly, monthly.
* Ready for deduction timing logic in Phase 3.

---

## Phase 3: Weekly Salary and Deduction Timing Engine

Goal: Make deduction scheduling work for weekly periods, including monthly spread to 4 weeks.

Tasks:
- [ ] Add new timing mode: `spread_monthly_4`.
- [ ] Extend `shouldApplyDeduction(...)` to accept frequency and weekly position context.
- [ ] For `spread_monthly_4`, apply only when frequency is weekly and weekly position is <= 4.
- [ ] Extend `getDeductionMultiplier(...)`:
  - `spread_monthly_4` => 0.25
  - existing modes unchanged.
- [ ] Update `getDeductionTimingConfig(...)` defaults and merge logic to support the new mode.
- [ ] Update `calculateBasicPay(...)` for monthly salary + weekly frequency using x12/52 formula.

Files to touch:
- `app/Services/Payroll/PayrollCalculationService.php`
- `resources/js/types/payroll-pages.ts`
- `resources/js/components/payroll/period-form-modal.tsx`

Deliverable:
- Monthly deductions are split exactly across 4 weekly payrolls, never over-deducted on week 5.

---

## Phase 4: Government Deductions from Weekly Gross Pay

Goal: Base government deductions on this period's gross pay values.

Tasks:
- [ ] Refactor government contribution methods to accept payroll basis amount (weekly gross or weekly taxable base).
- [ ] Compute SSS using weekly gross-based basis.
- [ ] Compute PhilHealth using weekly gross-based basis with existing min/max rules applied consistently.
- [ ] Compute Pag-IBIG from weekly gross-based basis with configured caps.
- [ ] Compute withholding tax from weekly taxable income projected to annual basis consistently.
- [ ] Ensure deduction sequence in `calculateEmployee(...)` computes gross first, then uses gross as basis for government contributions.

Files to touch:
- `app/Services/Payroll/PayrollCalculationService.php`
- `app/Services/Payroll/GovernmentContributionService.php` (if contribution logic extraction is preferred)
- `app/Models/GovernmentContributionRate.php` (only if additional helper methods are needed)

Deliverable:
- Weekly government deductions are mathematically tied to each employee's actual weekly gross pay.

---

## Phase 5: Frontend UX for Weekly Deduction Timing

Goal: Expose correct options in period create/edit UI to avoid invalid configurations.

Tasks:
- [ ] Add `spread_monthly_4` option in deduction timing selector UI.
- [ ] Show helper text when period type is weekly:
  - monthly deductions split to weeks 1-4
  - week 5 does not apply monthly spread deductions
- [ ] Keep `apply_on_period` selector hidden/disabled for timing modes that do not use it.
- [ ] Ensure form payload sends correct timing enum values.

Files to touch:
- `resources/js/components/payroll/period-form-modal.tsx`
- `resources/js/types/payroll-pages.ts`
- `resources/js/pages/Payroll/PayrollProcessing/Periods/Index.tsx`

Deliverable:
- Payroll officer can configure weekly periods correctly without manual backend intervention.

---

## Phase 6: Routes, Job Triggering, and Calculation Flow Validation

Goal: Confirm weekly periods can be created, calculated, and reviewed through existing flow.

Tasks:
- [ ] Verify existing routes already support create + calculate actions for weekly periods.
- [ ] Validate `PayrollPeriodController::calculate()` starts job for weekly periods exactly like other period types.
- [ ] Verify calculation job and employee job do not assume semi-monthly-only behavior.

Files to verify/touch if needed:
- `routes/payroll.php`
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`
- `app/Jobs/Payroll/CalculatePayrollJob.php`
- `app/Jobs/Payroll/CalculateEmployeePayrollJob.php`
- `app/Jobs/Payroll/FinalizePayrollJob.php`

Deliverable:
- Weekly periods are fully calculable using existing queued payroll processing pipeline.

---

## Phase 7: Tests (Unit + Integration + Manual)

Goal: Prevent regressions and guarantee weekly requirements.

Tasks:
- [ ] Unit test: frequency inference (weekly, bi-weekly, semi-monthly, monthly).
- [ ] Unit test: weekly position in month (1..5) classification.
- [ ] Unit test: `spread_monthly_4` applies in weeks 1-4 only.
- [ ] Unit test: monthly salary weekly conversion formula x12/52.
- [ ] Unit test: government deductions are based on computed weekly gross pay input.
- [ ] Integration test: weekly period calculation totals for 4-week month.
- [ ] Integration test: weekly period calculation totals for 5-week month (no extra month-spread deduction in week 5).
- [ ] Regression tests: semi-monthly and bi-weekly behavior unchanged.

Likely test files:
- `tests/Unit/Services/Payroll/PayrollCalculationServiceTest.php`
- `tests/Feature/Payroll/PayrollPeriodControllerTest.php`
- `tests/Feature/Payroll/WeeklyPayrollCalculationTest.php`

Deliverable:
- Weekly payroll rules are covered and verified, with no regressions to existing periods.

---

## 5. File Touch Map (Frontend to Backend)

### Backend (required)
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`
- `app/Services/Payroll/PayrollCalculationService.php`
- `app/Services/Payroll/GovernmentContributionService.php` (if extraction/centralization is used)
- `app/Jobs/Payroll/CalculatePayrollJob.php` (verification; modify only if needed)
- `app/Jobs/Payroll/CalculateEmployeePayrollJob.php` (verification; modify only if needed)
- `routes/payroll.php` (verification; modify only if needed)

### Frontend (required)
- `resources/js/pages/Payroll/PayrollProcessing/Periods/Index.tsx`
- `resources/js/components/payroll/period-form-modal.tsx`
- `resources/js/types/payroll-pages.ts`

### Tests (required)
- `tests/Unit/Services/Payroll/PayrollCalculationServiceTest.php`
- `tests/Feature/Payroll/PayrollPeriodControllerTest.php`
- `tests/Feature/Payroll/WeeklyPayrollCalculationTest.php`

---

## 6. Acceptance Criteria

1. Weekly periods can be created in Payroll Periods UI and stored with valid deduction timing overrides.
2. Weekly periods can be calculated through existing calculate action and job pipeline.
3. Monthly deductions configured with `spread_monthly_4` are charged exactly 25% in weeks 1-4 and 0 in week 5.
4. Government deductions are computed from each period's weekly gross pay basis.
5. Existing semi-monthly/bi-weekly/monthly computations remain functionally correct.
6. Automated tests pass for weekly logic and regression paths.

---

## 7. Rollout Notes

1. Enable in staging first and run one full month simulation for a weekly payroll group.
2. Compare expected monthly totals against sum of weekly runs per employee.
3. Verify government contribution exports and remittance reports still match expected totals.
4. Roll out to production after payroll officer sign-off.
