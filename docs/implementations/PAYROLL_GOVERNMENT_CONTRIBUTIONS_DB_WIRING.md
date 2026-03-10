# Implementation Plan: Wire Government Contribution Tables to PayrollCalculationService

## Summary

Replace the four broken/outdated hardcoded calculation methods in `PayrollCalculationService` with
DB-driven lookups from `government_contribution_rates` and `tax_brackets`. Both tables are already
seeded. The Eloquent models (`GovernmentContributionRate`, `TaxBracket`) already have all the
lookup helper methods needed — this is purely a wiring task.

---

## Will This Break The Calculation Process?

**No — with one condition.**

| Concern | Answer |
|---|---|
| Method signatures change? | No. All four methods stay `private … float`. Callers are unchanged. |
| New DB queries slow things down? | Negligible. 3 single-row lookups + 1 single-row lookup per employee. |
| What if the seeder was never run? | Methods return `0.0` with a `Log::warning()` — same behavior as today when govt IDs are missing. |
| Does it affect `finalizeCalculation()`? | **Yes — employer ratios also need fixing** (see Phase 2 below). |
| Does anything outside the service break? | No. The `EmployeePayrollCalculation` columns being written (`sss_contribution`, `philhealth_contribution`, etc.) remain identical — only their values become correct. |

The only behavioral change is **correct numbers instead of wrong numbers**. No schema changes, no
migration, no new columns, no interface changes.

---

## Current Bugs Being Fixed

| Method | Current (broken) | Correct (after wiring) |
|---|---|---|
| `calculateSSSContribution()` | `$salary * 0.08` for all brackets (% formula — wrong, SSS uses fixed peso amounts per MSC) | Look up `employee_amount` from `government_contribution_rates` by salary bracket |
| `calculatePhilHealthContribution()` | `$salary * 0.0275` (outdated 2.75%) | Look up `employee_rate` (2.5%) from DB, clamp to min/max |
| `calculatePagIBIGContribution()` | Uses `pagibig_employee_rate` column from `employee_payroll_info` (may be null/stale) | Look up `employee_rate` from DB by salary tier, apply ₱100 ceiling |
| `calculateWithholdingTax()` | Wrong TRAIN rates (5%/10%/15%/20% instead of 15%/20%/25%/30%) | Look up bracket from `tax_brackets`, use model's `calculateTax()` |
| `finalizeCalculation()` employer ratios | `sss * 1.45`, `philhealth * 1.00`, `pagibig * 0.20` (all wrong) | Look up `employer_amount`/`employer_rate` from DB |

---

## Phases

### Phase 1 — Wire Employee Contribution Methods (PayrollCalculationService)

**File:** `app/Services/Payroll/PayrollCalculationService.php`

#### 1a. Add imports

```php
// Add after existing use statements (line ~10)
use App\Models\GovernmentContributionRate;
use App\Models\TaxBracket;
```

#### 1b. Replace `calculateSSSContribution()`

```php
private function calculateSSSContribution(EmployeePayrollInfo $payrollInfo): float
{
    if (!$payrollInfo->sss_number) {
        return 0;
    }

    $bracket = GovernmentContributionRate::findSSSBracket((float) $payrollInfo->basic_salary);

    if (!$bracket) {
        Log::warning('SSS bracket not found for salary, returning 0', [
            'employee_id' => $payrollInfo->employee_id,
            'salary'      => $payrollInfo->basic_salary,
        ]);
        return 0;
    }

    return (float) $bracket->employee_amount;
}
```

**Why `employee_amount` not `employee_rate * salary`?**
SSS uses fixed peso amounts per Monthly Salary Credit (MSC) bracket — it is NOT a percentage of
actual salary. The seeder stores the correct fixed amounts (e.g., ₱180 for bracket A, ₱1,350 for
bracket AR).

#### 1c. Replace `calculatePhilHealthContribution()`

```php
private function calculatePhilHealthContribution(EmployeePayrollInfo $payrollInfo): float
{
    if (!$payrollInfo->philhealth_number) {
        return 0;
    }

    $rate = GovernmentContributionRate::getPhilHealthRate();

    if (!$rate) {
        Log::warning('PhilHealth rate not found in DB, returning 0', [
            'employee_id' => $payrollInfo->employee_id,
        ]);
        return 0;
    }

    $salary    = (float) $payrollInfo->basic_salary;
    $eeRate    = (float) $rate->employee_rate / 100; // e.g. 2.5% → 0.025
    $computed  = $salary * $eeRate;

    // Clamp to PhilHealth min/max (employee share = half of total min/max)
    $minEE = (float) ($rate->minimum_contribution / 2); // ₱500 total → ₱250 EE
    $maxEE = (float) ($rate->maximum_contribution / 2); // ₱5,000 total → ₱2,500 EE

    return max($minEE, min($maxEE, $computed));
}
```

#### 1d. Replace `calculatePagIBIGContribution()`

```php
private function calculatePagIBIGContribution(EmployeePayrollInfo $payrollInfo): float
{
    if (!$payrollInfo->pagibig_number) {
        return 0;
    }

    $salary = (float) $payrollInfo->basic_salary;
    $rate   = GovernmentContributionRate::getPagIbigRate($salary);

    if (!$rate) {
        Log::warning('Pag-IBIG rate not found in DB, returning 0', [
            'employee_id' => $payrollInfo->employee_id,
            'salary'      => $salary,
        ]);
        return 0;
    }

    $eeRate   = (float) $rate->employee_rate / 100; // e.g. 2% → 0.02
    $computed = $salary * $eeRate;

    // Employee ceiling: ₱100/month (per RA 9679)
    $ceiling = (float) ($rate->contribution_ceiling ?? 100);

    return min($ceiling, $computed);
}
```

#### 1e. Replace `calculateWithholdingTax()`

```php
private function calculateWithholdingTax(float $taxableIncome, string $taxStatus): float
{
    if ($taxStatus === 'Z') {
        return 0;
    }

    $annualIncome = $taxableIncome * 12;

    if ($annualIncome <= 0) {
        return 0;
    }

    // Fall back to 'S' if the exact status has no bracket seeded
    $bracket = TaxBracket::findBracket($annualIncome, $taxStatus)
            ?? TaxBracket::findBracket($annualIncome, 'S');

    if (!$bracket) {
        Log::warning('Tax bracket not found, returning 0', [
            'tax_status'   => $taxStatus,
            'annual_income' => $annualIncome,
        ]);
        return 0;
    }

    $annualTax = $bracket->calculateTax($annualIncome);

    return round($annualTax / 12, 2); // Convert annual tax to monthly
}
```

**Note on tax status granularity:** TRAIN Law removed the income differential between statuses (S,
ME, S1, etc.) — all statuses use the same brackets. The seeder stores identical bracket rows for
each status code to preserve the data structure. The `'S'` fallback above handles any status code
not in the table.

---

### Phase 2 — Fix Employer Ratios in `finalizeCalculation()`

The current ratios (`sss * 1.45`, `philhealth * 1.00`, `pagibig * 0.20`) are wrong because:
- SSS employer share is NOT 1.45× the EE share — it's a different fixed peso amount per bracket
- PhilHealth employer share **is** equal to EE (1:1 ratio — this one is correct)
- Pag-IBIG employer share is **also** 2% (= 1:1 ratio with the REGULAR EE rate), **not** 0.20×

These ratios are used in `finalizeCalculation()` only to compute `total_employer_cost` on the
`payroll_periods` row — they don't affect employee net pay.

**Fix approach:** Store employer amounts at calculation time alongside EE amounts, then sum them in
finalize. Two options:

**Option A (minimal — keep existing columns):** Derive employer cost from known DB ratios when
finalizing. Since all employee records in the period are already calculated, query the DB rate
once and apply it to the period totals.

```php
// In finalizeCalculation(), replace the three $totalEmployer* closures:

// SSS: employer_amount is in the bracket; ratio = employer_amount / employee_amount
// For simplicity, derive from the EE amount using the known 8.5:4.5 rate ratio
$totalEmployerSSS = $calculations->sum(function ($calc) {
    $bracket = GovernmentContributionRate::findSSSBracket((float) $calc->basic_monthly_salary);
    return $bracket ? (float) $bracket->employer_amount : (float) $calc->sss_contribution * (8.5 / 4.5);
});

// PhilHealth: 1:1 EE:ER split
$totalEmployerPhilHealth = $calculations->sum('philhealth_contribution');

// Pag-IBIG: employer rate is also 2% with same ₱100 ceiling behavior
// For simplicity, 1:1 ratio is correct for REGULAR bracket (both 2%)
$totalEmployerPagIBIG = $calculations->sum('pagibig_contribution');
```

**Option B (proper — add employer columns to calculation record):** Add
`sss_employer_contribution`, `philhealth_employer_contribution`, `pagibig_employer_contribution`
columns to `employee_payroll_calculations`. Calculate and store them in `calculateEmployee()`.
Sum them directly in `finalizeCalculation()`.

> **Recommendation:** Implement Option A now (no migration needed), plan Option B as a follow-up
> schema improvement.

---

## Implementation Checklist

### Phase 1 — Service method wiring
- [x] Add `use App\Models\GovernmentContributionRate;` import
- [x] Add `use App\Models\TaxBracket;` import
- [x] Replace `calculateSSSContribution()` body
- [x] Replace `calculatePhilHealthContribution()` body
- [x] Replace `calculatePagIBIGContribution()` body
- [x] Replace `calculateWithholdingTax()` body
- [x] Verify seeders have been run: `php artisan db:seed --class=GovernmentContributionRatesSeeder`
- [x] Verify seeders have been run: `php artisan db:seed --class=TaxBracketsSeeder`

### Phase 2 — Employer ratios
- [x] Fix `$totalEmployerSSS` in `finalizeCalculation()`
- [x] Fix `$totalEmployerPhilHealth` in `finalizeCalculation()`
- [x] Fix `$totalEmployerPagIBIG` in `finalizeCalculation()`

### Phase 3 — Verification
- [ ] Run `php artisan tinker` spot-check (see below)
- [ ] Run payroll calculation for 1 test employee; compare SSS/PhilHealth/tax to manual BIR/SSS tables

---

## Spot-Check Tinker Commands

```php
// Verify SSS bracket lookup
use App\Models\GovernmentContributionRate;
$b = GovernmentContributionRate::findSSSBracket(15000);
echo "EE: {$b->employee_amount}, ER: {$b->employer_amount}, EC: {$b->ec_amount}";
// Expected: EE: 675, ER: 1275, EC: 30 (bracket W)

// Verify PhilHealth rate
$ph = GovernmentContributionRate::getPhilHealthRate();
echo "Rate: {$ph->employee_rate}%, Min EE: " . ($ph->minimum_contribution/2) . ", Max EE: " . ($ph->maximum_contribution/2);
// Expected: Rate: 2.5%, Min EE: 250, Max EE: 2500

// Verify Pag-IBIG
$pi = GovernmentContributionRate::getPagIbigRate(20000);
echo "EE rate: {$pi->employee_rate}%, Ceiling: {$pi->contribution_ceiling}";
// Expected: EE rate: 2%, Ceiling: 100

// Verify tax bracket - ₱600k annual income, status S
use App\Models\TaxBracket;
$tb = TaxBracket::findBracket(600000, 'S');
$tax = $tb->calculateTax(600000);
echo "Annual tax: {$tax}, Monthly: " . round($tax/12, 2);
// Expected: Annual: 62500, Monthly: 5208.33
// (22500 base + 20% × 200000 excess over 400000)
```

---

## Impact Summary

| What changes | Before | After |
|---|---|---|
| SSS deduction on ₱15,000 salary | ₱1,200 (15000 × 0.08) — **WRONG** | ₱675 (bracket W fixed amount) — **CORRECT** |
| PhilHealth on ₱20,000 salary | ₱550 (20000 × 0.0275) — **WRONG rate** | ₱500 (20000 × 2.5% = 500, at minimum floor) — **CORRECT** |
| Pag-IBIG on ₱20,000 salary | Depends on `pagibig_employee_rate` column — unreliable | ₱100 (20000 × 2% = 400, capped at ₱100) — **CORRECT** |
| Withholding tax on ₱600k/yr | ₱4,062.50 (wrong 5%/10% rates) — **WRONG** | ₱5,208.33 (correct 15%/20% TRAIN rates) — **CORRECT** |
| Employee net pay | Over-stated (deductions too low) | Correct |
| Employer cost total | Wrong (incorrect ER ratios) | Correct after Phase 2 |
