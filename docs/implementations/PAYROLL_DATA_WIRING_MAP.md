# Payroll Data Wiring Map

**Purpose:** Explains how every employee-related table plugs into the payroll calculation engine.  
**Audience:** Developers extending or debugging `PayrollCalculationService`.

---

## Overview: How Payroll Calculates One Employee

```
PayrollCalculationService::calculateEmployee()
│
├── [INPUT 1]  EmployeePayrollInfo          ← salary/rate/govt IDs (set by HR on onboarding)
├── [INPUT 2]  DailyAttendanceSummary       ← days present, OT hours, late/undertime (from timekeeping)
│
├── Step 4   calculateBasicPay()            ← basicPay
├── Step 5   calculateOvertimePay()         ← overtimePay
│
├── [INPUT 3]  employee_salary_components   ← pivot: supplemental one-off amounts (via SalaryComponentService)
├── [INPUT 4]  employee_allowances          ← recurring monthly allowances (via AllowanceDeductionService)
│
├── Step 8   grossPay = basic + OT + components + allowances
│
├── Step 9   calculateSSSContribution()     ← reads government_contribution_rates table
├── Step 9   calculatePhilHealthContribution() ← reads government_contribution_rates table
├── Step 9   calculatePagIBIGContribution() ← reads government_contribution_rates table
├── Step 10  calculateWithholdingTax()      ← reads tax_brackets table
│
├── [INPUT 5]  employee_deductions          ← recurring non-govt deductions (via AllowanceDeductionService)
├── [INPUT 6]  employee_loans              ← installment deductions (via LoanManagementService)
├── Step 13  calculateLateDeduction()       ← from DailyAttendanceSummary.late_minutes
├── Step 13  calculateUndertimeDeduction()  ← from DailyAttendanceSummary.undertime_minutes
│
├── Step 15  netPay = grossPay - allDeductions
│
└── [OUTPUT]  EmployeePayrollCalculation    ← saved row per employee per period
              employee_government_contributions ← saved separately for remittance tracking
```

---

## Table-by-Table Wiring Guide

---

### 1. `employee_payroll_info` ✅ Already Wired

**What it is:** The employee's payroll profile. Set by HR during onboarding.  
**Contains:** `basic_salary`, `daily_rate`, `hourly_rate`, `salary_type`, `tax_status`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin`.

**How it's used:**
- `calculateBasicPay()` reads `salary_type`, `basic_salary`, `daily_rate`, `hourly_rate`
- `calculateSSSContribution()` checks `sss_number` (skip if null)
- `calculatePhilHealthContribution()` checks `philhealth_number`
- `calculatePagIBIGContribution()` checks `pagibig_number`
- `calculateWithholdingTax()` reads `tax_status` (S, ME, S1, Z, etc.)

**Service:** `EmployeePayrollInfoService::getActivePayrollInfo()`

    ---

### 2. `government_contribution_rates` ⚠️ Seeded, NOT YET Wired

**What it is:** The official SSS bracket table, PhilHealth premium rate, Pag-IBIG rate — loaded from actual government circulars.  
**Contains:** bracket amounts, rates, min/max ceilings, effective dates.

**How it SHOULD be used:**
- `calculateSSSContribution()` → `GovernmentContributionRate::findSSSBracket($salary)` → returns `employee_amount` (fixed peso, NOT a percentage)
- `calculatePhilHealthContribution()` → `GovernmentContributionRate::getPhilHealthRate()` → returns `employee_rate` (2.5%), apply min ₱250 / max ₱2,500 per month
- `calculatePagIBIGContribution()` → `GovernmentContributionRate::getPagIbigRate($salary)` → returns `employee_rate` (2%), apply ₱100 ceiling

**Current bug:** All three methods use wrong hardcoded formulas instead of reading this table.  
**Fix plan:** See `PAYROLL_GOVERNMENT_CONTRIBUTIONS_DB_WIRING.md` Phase 1.

---

### 3. `tax_brackets` ⚠️ Seeded, NOT YET Wired

**What it is:** BIR TRAIN Law (RA 10963) annualized withholding tax brackets per tax status.  
**Contains:** `income_from`, `income_to`, `base_tax`, `tax_rate`, `excess_over`, `tax_status`.

**How it SHOULD be used:**
- `calculateWithholdingTax()` → annualize monthly taxable income (`× 12`)
- → `TaxBracket::findBracket($annualIncome, $taxStatus)` → returns the matching bracket
- → `$bracket->calculateTax($annualIncome)` → returns annual tax
- → divide by 12 for monthly withholding

**Current bug:** Hardcoded rates that are wrong (5%/10%/15% instead of 15%/20%/25%).  
**Fix plan:** See `PAYROLL_GOVERNMENT_CONTRIBUTIONS_DB_WIRING.md` Phase 1e.

---

### 4. `employee_dependents` — Tax Status Only (No Direct Wiring Needed)

**What it is:** List of the employee's dependents (children, spouse, etc.) with `relationship` and `date_of_birth`.

**Does it affect payroll calculation?**  
**No — not directly.** Under TRAIN Law (effective 2018), personal exemptions were abolished. The number of dependents no longer changes the withholding tax calculation — everyone uses the same ₱250,000 zero-bracket regardless of dependents.

**Then what is `employee_dependents` for?**

| Purpose | Details |
|---|---|
| 13th Month Pay computation | 13th month is based on basic salary only — not affected by dependents |
| Philhealth / SSS dependent records | SSS allows filing for dependent benefits; stored for reference |
| HR recordkeeping | Required for DOLE and BIR audit compliance (13th month exemption, COLA) |
| `tax_status` on `employee_payroll_info` | HR sets this manually (S, ME, ME1, S2, etc.) based on BIR Form 2305. The dependent count in `employee_dependents` should MATCH the tax status code — but the calculation engine reads `tax_status` directly, NOT the dependent count from this table. |

**Recommendation:** Do NOT wire `employee_dependents` directly into the calculation engine. Instead:
- HR sets `employee_payroll_info.tax_status` = `ME2` (for example) when an employee files BIR 2305 with 2 dependents
- Optionally add a validation rule: warn HR if the number of rows in `employee_dependents` with `relationship = 'child'` doesn't match the numeric suffix in `tax_status`

**No code change needed in `PayrollCalculationService`.**

---

### 5. `employee_allowances` ✅ Already Wired

**What it is:** Recurring monthly allowances per employee. Set by HR.  
**Types:** `rice`, `cola`, `transportation`, `meal`, `housing`, `communication`, `laundry`, `clothing`, `other`.

**How it's used:**
```php
// Step 7 in calculateEmployee()
$allowances = $this->allowanceDeductionService->getActiveAllowances($employee);
$totalAllowances = $allowances->sum('amount');
// ↓ Added into grossPay
$grossPay = $basicPay + $overtimePay + $componentAmounts + $totalAllowances;
```

**Key points:**
- `getActiveAllowances()` filters by `is_active = true` and `end_date >= today` (or null)
- Amount is added to GROSS pay → affects taxable income → affects withholding tax
- De minimis benefits (`is_deminimis = true`) are still summed here — the engine doesn't split them out yet; full de minimis tax exemption handling is a future improvement

**No change needed.** Already wired correctly.

---

### 6. `employee_deductions` ✅ Already Wired

**What it is:** Recurring non-government deductions per employee.  
**Types:** insurance premiums, union dues, canteen balance, cooperative, etc.

**How it's used:**
```php
// Step 11 in calculateEmployee()
$deductions = $this->allowanceDeductionService->getActiveDeductions($employee);
$totalDeductions = $deductions->sum('amount');
// ↓ Added into allDeductions (reduces net pay, does NOT reduce taxable income)
$allDeductions = $sss + $philhealth + $pagibig + $tax + $totalDeductions + $loans + $late + $undertime;
```

**Key points:**
- These deductions come AFTER tax calculation — they reduce net pay but do NOT reduce BIR taxable income
- If you have a deduction that IS pre-tax (e.g., mandatory union dues sometimes qualify), the service would need to subtract it before `calculateWithholdingTax()` — that's a future refinement

**No change needed.** Already wired correctly.

---

### 7. `employee_loans` ✅ Already Wired (via LoanManagementService)

**What it is:** Loan records with installment schedules. Types: `sss_loan`, `pagibig_loan`, `company_loan`, `emergency_loan`, `housing_loan`.

**How it's used:**
```php
// Step 12 in calculateEmployee()
$loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);
```

`processLoanDeduction()` does:
1. Gets all active loans for the employee
2. Finds the next `pending` `LoanDeduction` row (scheduled installment)
3. Marks it `processed`, deducts from loan balance
4. Returns the total deducted this period

**Key points:**
- Loan deductions come AFTER tax — they are post-tax deductions
- SSS loans and Pag-IBIG loans are deducted here as loan repayments (distinct from SSS/PhilHealth/PagIBIG contribution deductions)
- The `LoanDeduction` table holds the pre-scheduled installment rows; `processLoanDeduction()` picks the next pending one each period

**No change needed.** Already wired correctly.

---

### 8. `employee_government_contributions` ⚠️ Table Exists, NOT Populated by Engine

**What it is:** A detailed per-period record of each employee's government contributions — SSS, PhilHealth, Pag-IBIG, and BIR withholding tax. Designed for **remittance tracking** (paying the contributions to SSS/PhilHealth/HDMF/BIR).

**Current state:** The `PayrollCalculationService` calculates government contributions and writes them to the aggregate `employee_payroll_calculations` row only (`sss_contribution`, `philhealth_contribution`, etc.). It does NOT write to `employee_government_contributions`.

**What this table is for:**
| Column group | Purpose |
|---|---|
| `sss_bracket`, `sss_monthly_salary_credit` | Audit trail — which bracket was used |
| `sss_employer_contribution`, `sss_ec_contribution` | For SSS R-3 remittance report |
| `philhealth_premium_base` | For PhilHealth RF-1 form |
| `pagibig_compensation_base` | For Pag-IBIG RF-1 form |
| `annualized_taxable_income`, `tax_already_withheld_ytd` | For BIR Alpha list / 2316 |
| `status` (pending → calculated → processed → remitted) | Tracks whether government has been paid |
| `deminimis_benefits`, `thirteenth_month_pay` | For BIR annual alphalist |

**How it SHOULD be wired:**

After `calculateEmployee()` computes all government contribution amounts, a new write step should create the `employee_government_contributions` row:

```php
// After Step 9 calculations, add a Step 9b:
EmployeeGovernmentContribution::updateOrCreate(
    [
        'employee_id'           => $employee->id,
        'payroll_period_id'     => $period->id,
    ],
    [
        'period_start'                  => $period->period_start,
        'period_end'                    => $period->period_end,
        'period_month'                  => Carbon::parse($period->period_start)->format('Y-m'),
        'basic_salary'                  => $payrollInfo->basic_salary,
        'gross_compensation'            => $grossPay,
        'taxable_income'                => $taxableIncome,
        'sss_number'                    => $payrollInfo->sss_number,
        'sss_bracket'                   => $sssBracket?->bracket_code,
        'sss_monthly_salary_credit'     => $sssBracket?->monthly_salary_credit,
        'sss_employee_contribution'     => $sssContribution,
        'sss_employer_contribution'     => $sssBracket?->employer_amount ?? 0,
        'sss_ec_contribution'           => $sssBracket?->ec_amount ?? 0,
        'sss_total_contribution'        => ($sssContribution + ($sssBracket?->employer_amount ?? 0) + ($sssBracket?->ec_amount ?? 0)),
        'philhealth_number'             => $payrollInfo->philhealth_number,
        'philhealth_premium_base'       => $payrollInfo->basic_salary,
        'philhealth_employee_contribution' => $philhealthContribution,
        'philhealth_employer_contribution' => $philhealthContribution, // 1:1 split
        'philhealth_total_contribution' => $philhealthContribution * 2,
        'pagibig_number'                => $payrollInfo->pagibig_number,
        'pagibig_compensation_base'     => $payrollInfo->basic_salary,
        'pagibig_employee_contribution' => $pagibigContribution,
        'pagibig_employer_contribution' => $pagibigContribution, // 1:1 split for REGULAR bracket
        'pagibig_total_contribution'    => $pagibigContribution * 2,
        'tin'                           => $payrollInfo->tin,
        'tax_status'                    => $payrollInfo->tax_status,
        'annualized_taxable_income'     => $taxableIncome * 12,
        'withholding_tax'               => $withholdingTax,
        'total_employee_contributions'  => $sssContribution + $philhealthContribution + $pagibigContribution + $withholdingTax,
        'total_employer_contributions'  => ($sssBracket?->employer_amount ?? 0) + ($sssBracket?->ec_amount ?? 0) + $philhealthContribution + $pagibigContribution,
        'status'                        => 'calculated',
        'calculated_at'                 => Carbon::now(),
        'employee_payroll_calculation_id' => null, // filled in after Step 17
    ]
);
```

**Why this matters:** Without this table being populated, there is no per-employee audit trail for government remittances. The SSS R-3, PhilHealth RF-1, and Pag-IBIG RF-1 forms cannot be generated from the system. Currently, the aggregate numbers exist on `employee_payroll_calculations` but the breakdown details (bracket used, employer share, EC amount) are lost.

**Implementation priority:** HIGH once the Phase 1 DB wiring fix is done (government contributions DB wiring). The bracket detail is only available at calculation time — it cannot be reconstructed later.

---

## Complete Wiring Status Summary

| Table | Role in Payroll | Current Status | Action Needed |
|---|---|---|---|
| `employee_payroll_info` | Salary/rate/govt IDs — the master HR record | ✅ Wired | None |
| `daily_attendance_summaries` | Days worked, OT/late/undertime | ✅ Wired | None |
| `employee_salary_components` (pivot) | Supplemental one-off amounts (13th month, rate diff) | ✅ Wired (but guard needed — see SALARY_COMPONENTS plan) | Add computed/assignable guards |
| `employee_allowances` | Recurring monthly allowances | ✅ Wired | None |
| `employee_deductions` | Recurring non-govt deductions | ✅ Wired | None |
| `employee_loans` | Installment loan repayments | ✅ Wired | None |
| `government_contribution_rates` | SSS/PhilHealth/PagIBIG official rates from DB | ⚠️ Seeded, NOT wired | Fix 4 methods in PayrollCalculationService (see DB wiring plan) |
| `tax_brackets` | BIR TRAIN Law withholding tax brackets | ⚠️ Seeded, NOT wired | Fix `calculateWithholdingTax()` |
| `employee_government_contributions` | Per-employee per-period govt contribution detail for remittance | ⚠️ Table exists, never written | Add write step after govt contributions are calculated |
| `employee_dependents` | Dependent records for HR/BIR compliance | ✅ No wiring needed | HR manually sets `tax_status` on payroll info from BIR 2305 |

---

## Dependency Tree: What Must Happen First

```
Phase 1: Wire government_contribution_rates and tax_brackets into PayrollCalculationService
         (PAYROLL_GOVERNMENT_CONTRIBUTIONS_DB_WIRING.md)
    │
    ├─→ Phase 2: Now that SSS bracket is resolved, write to employee_government_contributions
    │            (employer amounts only known after bracket lookup)
    │
    └─→ Phase 3: Fix finalizeCalculation() employer ratios
                 (currently uses wrong multipliers — will be correct once Phase 1 is done)
```

---

## FAQ

**Q: Why doesn't the number of dependents in `employee_dependents` affect withholding tax?**  
A: TRAIN Law abolished per-dependent exemptions. The `tax_status` field on `employee_payroll_info` (e.g., `ME2`) is the BIR-recognized status code that HR sets when the employee files Form 2305. It already encodes the dependent count. The engine reads `tax_status` → looks up the matching bracket → that row has the same rates as all other statuses (TRAIN removed the differential). The `employee_dependents` table is for recordkeeping and benefit eligibility, not for tax calculation.

**Q: Should `employee_allowances` amounts be taxable or non-taxable?**  
A: Currently the engine adds ALL allowances to gross pay (all taxable treatment). The `is_taxable` and `is_deminimis` flags on `EmployeeAllowance` exist but are not yet read by the engine. A future improvement would: sum taxable allowances into gross (affecting BIR taxable income) but exclude de minimis benefits (up to their statutory limits) from the taxable base. This requires splitting Step 7 into `taxableAllowances` and `deminimisAllowances` before calling `calculateWithholdingTax()`.

**Q: Does `employee_deductions` include SSS/PhilHealth/PagIBIG amounts?**  
A: No — those are handled separately in Steps 9–10 via the government contribution rate tables. `employee_deductions` is only for non-statutory deductions like insurance, cooperative shares, or canteen balances.

**Q: What is `LoanDeduction` vs `employee_loans`?**  
A: `employee_loans` is the loan header (total amount, interest, number of installments). `LoanDeduction` is the installment schedule — one row per month, pre-generated when the loan is created. `processLoanDeduction()` picks the next `pending` installment row each payroll period and marks it `processed`. This is already fully working.
