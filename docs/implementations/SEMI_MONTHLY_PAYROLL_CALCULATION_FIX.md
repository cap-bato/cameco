# Semi-Monthly Payroll Calculation Fix

**Created:** 2026-03-10  
**Priority:** HIGH — employees are getting wrong net pay on both halves  
**Risk:** MEDIUM — changes core calculation logic, needs QA with test data  
**Status:** 🟡 In Progress — Phases 1–3 complete

---

## 1. What You're Seeing and Why

### The ₱1,200 SSS number

The ₱1,200 comes from the old broken formula: `₱15,000 × 0.08 = ₱1,200`.

The DB wiring fix from the previous session corrected this to use bracket lookup (correct amount for
a ₱15,000 salary is **₱675**, bracket W). However, **existing payroll run records in the database
still hold the old ₱1,200 value** — they were calculated before the fix. Recalculating those
periods will now produce ₱675.

### The deeper problem

**Even ₱675 is wrong for semi-monthly payroll.**

SSS, PhilHealth, and Pag-IBIG are **monthly government obligations** — an employee owes them once
per calendar month, not once per payroll period. Your system runs semi-monthly (1st half and 2nd
half). The current engine does not know this: it applies the full monthly contribution amount on
**every period it runs**, so an employee paying ₱675 SSS per month ends up with:

| Period | SSS Deducted | Should Be |
|--------|-------------|-----------|
| March 1st half  | ₱675 | ₱0 |
| March 2nd half  | ₱675 | ₱675 |
| **Month total** | **₱1,350** | **₱675** |

The employee is being **double-deducted** on every government contribution, every month.

The same double-deduction applies to PhilHealth and Pag-IBIG.

For withholding tax, the Bracket-3 tax on ₱600k/yr annual income is ₱5,208.33/month. Currently
the engine deducts ₱5,208.33 from **each half**, giving ₱10,416.66/month instead of ₱5,208.33.

---

## 2. What the System IS (confirmed)

From inspecting the actual `payroll_periods` table:

```
1st half  2026-03-01 → 2026-03-15   (days 1–15)
2nd half  2026-03-16 → 2026-03-31   (days 16–end)
```

`period_type = 'regular'` means "normal semi-monthly" per the enum comment in the migration. There
is no monthly frequency — the system is semi-monthly by design.

---

## 3. Two Additional Bugs Found During Analysis

### Bug 1 — Monthly basic salary not halved

`calculateBasicPay()` for `salary_type = 'monthly'` returns:

```php
'monthly' => $payrollInfo->basic_salary,
```

For an employee with `basic_salary = 30,000`, BOTH the 1st half and 2nd half payslip show ₱30,000
gross instead of ₱15,000.

### Bug 2 — Withholding tax computed on half-month income but applied twice

The tax engine receives half the monthly gross as `$taxableIncome`, annualizes it, then deducts
the result in each period. This means:
- The annualization is done at ×12 on a half-period income → equivalent to ×24, overstating the
  annual projection
- It is then deducted again from the next period (another ×24 projection)
- Net effect: BIR withholding tax is ~4× overstated per year

---

## 4. Standard Philippine Semi-Monthly Payroll Practice

| Item | 1st Half (Days 1–15) | 2nd Half (Days 16–end) |
|------|---------------------|------------------------|
| Basic Pay | `basic_salary ÷ 2` | `basic_salary ÷ 2` |
| Overtime / Late / Undertime | Actual for those days | Actual for those days |
| Allowances | Typically full (or split) | Typically zero (or split) |
| SSS | ₱0 | Full monthly bracket amount |
| PhilHealth | ₱0 | Full monthly amount (with clamp) |
| Pag-IBIG | ₱0 | Full monthly amount (with ceiling) |
| Withholding Tax | ₱0 | Full monthly tax, computed on projected monthly gross |
| Loan deductions | ₱0 (or employer-defined) | Full installment amount |

> **Authority:** DOLE Labor Advisories on semi-monthly pay, BIR RR 11-2018 (TRAIN), SSS Circular
> 2019-002. Government contributions are monthly obligations; the payroll system must consolidate
> both halves of the month before remitting.

---

## 5. Implementation Plan

### How to detect which half

No new DB column needed. Derive from `period_start`:

```php
private function getPeriodHalf(PayrollPeriod $period): int
{
    return Carbon::parse($period->period_start)->day <= 15 ? 1 : 2;
}
```

All existing periods follow this pattern (confirmed from DB).

---

### Phase 1 — Fix Basic Pay for Monthly Salary Type

**File:** `app/Services/Payroll/PayrollCalculationService.php`

**Change `calculateBasicPay()`** — add `PayrollPeriod $period` parameter, halve monthly salary:

```php
private function calculateBasicPay(int $daysWorked, EmployeePayrollInfo $payrollInfo, PayrollPeriod $period): float
{
    return match ($payrollInfo->salary_type) {
        'monthly' => $payrollInfo->basic_salary / 2,   // semi-monthly: always half
        'daily'   => $daysWorked * ($payrollInfo->daily_rate ?? 0),
        'hourly'  => $daysWorked * 8 * ($payrollInfo->hourly_rate ?? 0),
        default   => 0,
    };
}
```

> **Note:** Daily and hourly types are already correct — they multiply by actual days worked in the
> period, so they naturally produce a half-month figure.

Update the call in `calculateEmployee()` Step 4:

```php
$basicPay = $this->calculateBasicPay($daysWorked, $payrollInfo, $period);
```

**Tasks:**
- [x] **1.1** Update `calculateBasicPay()` signature + body
- [x] **1.2** Update the call site at Step 4

---

### Phase 2 — Apply Government Contributions Only on 2nd Half

**File:** `app/Services/Payroll/PayrollCalculationService.php`

Replace Step 9 and Step 10 in `calculateEmployee()`:

```php
// Step 9 & 10: Government contributions and tax — monthly obligations, 2nd half only.
$periodHalf = $this->getPeriodHalf($period);

if ($periodHalf === 2) {
    // Compute contributions on the MONTHLY projection (both halves combined).
    // basicPay here is already half the monthly salary, so project back to monthly.
    $monthlyBasic = $payrollInfo->basic_salary;

    // Use a temporary full-month PayrollInfo proxy for the bracket lookups.
    // (bracket lookups use basic_salary, so we pass $payrollInfo directly —
    //  basic_salary already stores the full monthly figure, not the half-period figure)
    $sssContribution        = $this->calculateSSSContribution($payrollInfo);
    $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
    $pagibigContribution    = $this->calculatePagIBIGContribution($payrollInfo);

    // Withholding tax: annualise the MONTHLY gross (not the half-period gross).
    $monthlyGross   = ($basicPay * 2) + $totalAllowances;   // project to monthly
    $monthlyTaxable = $monthlyGross - $sssContribution - $philhealthContribution - $pagibigContribution;
    $withholdingTax = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
} else {
    // 1st half: no government deductions — paid as salary advance only.
    $sssContribution        = 0.0;
    $philhealthContribution = 0.0;
    $pagibigContribution    = 0.0;
    $withholdingTax         = 0.0;
}
```

**Tasks:**
- [x] **2.1** Add `getPeriodHalf()` helper method to the service
- [x] **2.2** Replace Step 9 and Step 10 blocks with the conditional logic above
- [x] **2.3** Verify loan deduction behavior (see note below)

> **Loans — verified & fixed:** `scheduleLoanDeductions()` creates one `LoanDeduction` record per
> calendar month. `processLoanDeduction()` ignores the `$payrollPeriod` parameter and processes
> the oldest pending installment on every call. Calling it for both halves would consume two
> installments per physical month. Applied the same 2nd-half gate used for government contributions:
> `$loanDeductions = $periodHalf === 2 ? $this->loanManagementService->processLoanDeduction(...) : 0.0;`

---

### Phase 3 — Add `getPeriodHalf()` Helper ✅ Done

> **✅ Implemented as Task 2.1.** Method lives at `app/Services/Payroll/PayrollCalculationService.php` (inside the `// Helper methods` section, after `calculateBasicPay()`).

```php
/**
 * Determine whether a period is the 1st or 2nd half of the month.
 * Convention: period_start day <= 15 → 1st half, day > 15 → 2nd half.
 */
private function getPeriodHalf(PayrollPeriod $period): int
{
    return Carbon::parse($period->period_start)->day <= 15 ? 1 : 2;
}
```

---

### Phase 4 — Verification

After implementing:

| Test Case | Expected 1st Half (₱30k/mo salary) | Expected 2nd Half |
|-----------|-----------------------------------|-------------------|
| Basic Pay | ₱15,000 | ₱15,000 |
| SSS (bracket for ₱30k) | ₱0 | ₱1,350 |
| PhilHealth (2.5%, min ₱250) | ₱0 | ₱750 |
| Pag-IBIG (2%, ceil ₱100) | ₱0 | ₱100 |
| Withholding Tax (monthly gross ₱30k+allow) | ₱0 | (computed on ₱30k base) |
| Monthly total SSS | — | ₱1,350 ✅ |

---

## 6. Summary of All Bugs in Scope

| # | Bug | Impact | Fix Phase |
|---|-----|--------|-----------|
| 1 | `calculateBasicPay()` returns full monthly salary per period | Employees receive 2× gross per month | Phase 1 |
| 2 | SSS/PhilHealth/PagIBIG deducted on every period | Double-deductions every month | Phase 2 |
| 3 | Withholding tax computed on half-month income ×12, deducted twice | ~4× over-withholding | Phase 2 |
| 4 | Old hardcoded formulas still in existing DB records | Historical payslips show wrong amounts | Recalculate affected periods |

---

## 7. Files to Change

| File | Change |
|------|--------|
| `app/Services/Payroll/PayrollCalculationService.php` | `calculateBasicPay()` + Step 9/10 block + add `getPeriodHalf()` |
| *(no migration, no model changes)* | All changes are in the calculation engine only |

---

## 8. What Does NOT Change

- `Government_contribution_rates` and `tax_brackets` DB lookups — these remain correct  
- `calculateSSSContribution()`, `calculatePhilHealthContribution()`, etc. — bracket/rate methods unchanged
- Net calculation logic (Step 14, 15) — unchanged
- Payroll versioning — unchanged
- Finalize/aggregate logic — unchanged (it already sums from individual calculation records)
