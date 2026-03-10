# Implementation Plan: Bi-Weekly Payroll Period Configuration

**Created:** 2026-03-10  
**Priority:** MEDIUM — Required for organizations using every-2-weeks pay cycles  
**Risk:** MEDIUM — Touches core calculation logic; must not break existing semi-monthly behavior  
**Depends on:** `PAYROLL_DEDUCTION_TIMING_CONFIGURATION.md` (already complete)

---

## Summary

Extend the payroll system to fully support **bi-weekly** (every 2 weeks = 26 periods/year) payroll periods.
Currently, `bi_weekly` is accepted as a frontend value but the backend calculation logic treats all periods as semi-monthly.  
This plan fixes the detection, basic-pay formula, deduction timing logic, and UI for bi-weekly periods.

**Core Problem:** Bi-weekly periods need two configurable deduction behaviors:
- **Every Period** — Deduct every bi-weekly cutoff (26x per year)
- **End of Month** — Deduct only on the last bi-weekly period that falls in a given month (12x per year)

---

## Bi-Weekly vs Semi-Monthly: Key Differences

| Property | Semi-Monthly | Bi-Weekly |
|---|---|---|
| Periods per year | 24 (exactly) | 26 (exactly) |
| Period length | 15 or 16 days | Always 14 days |
| Periods per month | Always 2 | Usually 2, sometimes 3 |
| "Position" concept | 1st half / 2nd half | Not last / Last in month |
| Monthly salary divisor | ÷ 2 | × 12 ÷ 26 |
| `split_monthly` meaning | 0.5 per period | Complex (avoid) |

---

## Bugs Found in Current Codebase

### Bug 1: `inferFrequency()` wrong threshold for bi-weekly

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`  
**Method:** `inferFrequency()` (line ~344)

```php
// CURRENT (WRONG):
return match(true) {
    $days <= 7  => 'weekly',
    $days <= 10 => 'bi_weekly',   // ← BUG: bi-weekly is 14 days (diff = 13)
    $days <= 17 => 'semi_monthly',
    default     => 'monthly',
};
```

A true bi-weekly period spans 14 calendar days. `Carbon::diffInDays()` returns 13 for a 14-day span (Jan 1 → Jan 14 = 13 days difference). The current `<= 10` threshold means any real bi-weekly period (diff = 13) is misclassified as `semi_monthly`.

**Fix:**
```php
// CORRECT:
return match(true) {
    $days <= 7  => 'weekly',
    $days <= 13 => 'bi_weekly',   // 14-day period → diff = 13
    $days <= 17 => 'semi_monthly',
    default     => 'monthly',
};
```

### Bug 2: `calculateBasicPay()` divides by 2 for all period types

**File:** `app/Services/Payroll/PayrollCalculationService.php`  
**Method:** `calculateBasicPay()` (line ~392)

```php
// CURRENT (WRONG):
return match ($payrollInfo->salary_type) {
    'monthly' => $payrollInfo->basic_salary / 2,  // assumes semi-monthly
    ...
};
```

A bi-weekly employee earning ₱20,000/month should receive ₱9,230.77 per bi-weekly period (`20,000 × 12 ÷ 26`), NOT ₱10,000 (÷ 2).

---

## Architecture

```
┌─────────────────────────────┐     ┌──────────────────────────────────┐
│  PayrollPeriod               │     │  PayrollCalculationService        │
│  ─────────────────────────  │     │  ────────────────────────────── │
│  period_start: 2026-01-01   │────▶│  inferPeriodFrequency()          │
│  period_end:   2026-01-14   │     │    → 'bi_weekly'                 │
│  (14-day period)            │     │                                  │
└─────────────────────────────┘     │  getPeriodPosition()             │
                                    │    → { frequency: 'bi_weekly',  │
                                    │        half: 1,                  │
                                    │        is_last_in_month: false } │
                                    │                                  │
                                    │  shouldApplyDeduction()          │
                                    │    (monthly_only + bi_weekly)    │
                                    │    → checks is_last_in_month     │
                                    └──────────────────────────────────┘
```

---

## Implementation Phases

---

### Phase 1: Fix `inferFrequency()` in Controller

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`

Locate the `inferFrequency()` method and change the match threshold:

```php
private function inferFrequency($start, $end): string
{
    if (!$start || !$end) {
        return 'semi_monthly';
    }

    $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));

    return match(true) {
        $days <= 7  => 'weekly',
        $days <= 13 => 'bi_weekly',   // true 14-day bi-weekly period: diff = 13
        $days <= 17 => 'semi_monthly',
        default     => 'monthly',
    };
}
```

**Why `<= 13`:** `Carbon::parse('2026-01-01')->diffInDays('2026-01-14')` returns `13`.  
Semi-monthly 1st half (Jan 1–15) returns `14`, so `<= 13` correctly separates them.

---

### Phase 2: Add `inferPeriodFrequency()` to `PayrollCalculationService`

The DB `period_type` column is always stored as `'regular'` (see controller `store()`).  
The calculation service needs to infer the frequency from the date range, same logic as the controller.

**File:** `app/Services/Payroll/PayrollCalculationService.php`

Add this new private method after `getPeriodHalf()`:

```php
/**
 * Infer the pay frequency from the payroll period date range.
 * Mirrors the logic in PayrollPeriodController::inferFrequency().
 *
 * @param PayrollPeriod $period
 * @return string  'weekly' | 'bi_weekly' | 'semi_monthly' | 'monthly'
 */
private function inferPeriodFrequency(PayrollPeriod $period): string
{
    $days = Carbon::parse($period->period_start)->diffInDays(Carbon::parse($period->period_end));

    return match (true) {
        $days <= 7  => 'weekly',
        $days <= 13 => 'bi_weekly',
        $days <= 17 => 'semi_monthly',
        default     => 'monthly',
    };
}
```

---

### Phase 3: Add `isLastBiWeeklyPeriodInMonth()` to `PayrollCalculationService`

For bi-weekly, "monthly only" means the **last period whose start date falls in a given month**.

**Logic:** The day after `period_end` is where the next period would start. If that next-period-start is in a *different month* from `period_start`, then this is the last period for that month.

**Examples:**

| period_start | period_end | Next start | Same month? | Is last? |
|---|---|---|---|---|
| Jan 1 | Jan 14 | Jan 15 | Yes | No |
| Jan 15 | Jan 28 | Jan 29 | Yes | No |
| Jan 29 | Feb 11 | Feb 12 | No (start=Jan, next=Feb) | **Yes** |
| Feb 12 | Feb 25 | Feb 26 | Yes | No |
| Feb 26 | Mar 11 | Mar 12 | No (start=Feb, next=Mar) | **Yes** |

**File:** `app/Services/Payroll/PayrollCalculationService.php`

Add after `inferPeriodFrequency()`:

```php
/**
 * Determine if this bi-weekly period is the last one in its starting month.
 * "Last" means: the day after period_end falls in a different month than period_start.
 *
 * @param PayrollPeriod $period
 * @return bool
 */
private function isLastBiWeeklyPeriodInMonth(PayrollPeriod $period): bool
{
    $nextPeriodStart  = Carbon::parse($period->period_end)->addDay();
    $currentStartMonth = Carbon::parse($period->period_start)->month;

    return $nextPeriodStart->month !== $currentStartMonth;
}
```

---

### Phase 4: Add `getPeriodPosition()` to `PayrollCalculationService`

Replace the single `$periodHalf` concept with a richer position struct that works for any frequency.

**File:** `app/Services/Payroll/PayrollCalculationService.php`

```php
/**
 * Get the full position context for a payroll period.
 *
 * Returns:
 *  - frequency:       'weekly' | 'bi_weekly' | 'semi_monthly' | 'monthly'
 *  - half:            1 or 2 (semi-monthly half; for bi-weekly this is inferred from day ≤ 15)
 *  - is_last_in_month: true if this is the last period of the month
 *                     (for semi-monthly: periodHalf === 2; for bi-weekly: isLastBiWeeklyPeriodInMonth)
 *
 * @param PayrollPeriod $period
 * @return array{frequency: string, half: int, is_last_in_month: bool}
 */
private function getPeriodPosition(PayrollPeriod $period): array
{
    $frequency = $this->inferPeriodFrequency($period);
    $half      = $this->getPeriodHalf($period); // still used for semi-monthly

    $isLastInMonth = match ($frequency) {
        'bi_weekly' => $this->isLastBiWeeklyPeriodInMonth($period),
        'monthly'   => true,                     // monthly is always "last" (only period)
        default     => $half === 2,              // semi-monthly: 2nd half is "last"
    };

    return [
        'frequency'        => $frequency,
        'half'             => $half,
        'is_last_in_month' => $isLastInMonth,
    ];
}
```

---

### Phase 5: Update `shouldApplyDeduction()` signature

Add `string $frequency` parameter so bi-weekly and semi-monthly can have different `monthly_only` behavior.

**File:** `app/Services/Payroll/PayrollCalculationService.php`

```php
/**
 * Determine if a deduction should be applied for the given period.
 *
 * Timing modes:
 *  - per_cutoff:    Always deduct (every period regardless of type).
 *  - monthly_only:  Semi-monthly → apply only on the specified half (1 or 2).
 *                   Bi-weekly   → apply only on the last period of the month.
 *  - split_monthly: Always deduct (multiplier handles the amount split).
 *
 * @param array  $deductionConfig  Config entry for the specific deduction type
 * @param int    $periodHalf       1 or 2 (which semi-monthly half)
 * @param bool   $isLastInMonth    True if this is the last period in the month
 * @param string $frequency        'bi_weekly' | 'semi_monthly' | 'weekly' | 'monthly'
 * @return bool
 */
private function shouldApplyDeduction(
    array  $deductionConfig,
    int    $periodHalf,
    bool   $isLastInMonth   = false,
    string $frequency       = 'semi_monthly'
): bool {
    $timing        = $deductionConfig['timing']          ?? 'monthly_only';
    $applyOnPeriod = $deductionConfig['apply_on_period'] ?? 2;

    return match ($timing) {
        'per_cutoff'   => true,
        'split_monthly' => true,
        'monthly_only'  => $frequency === 'bi_weekly'
            ? $isLastInMonth
            : ($periodHalf === (int) $applyOnPeriod),
        default         => $frequency === 'bi_weekly'
            ? $isLastInMonth
            : ($periodHalf === 2),
    };
}
```

---

### Phase 6: Update `getDeductionMultiplier()` for bi-weekly

`split_monthly` for bi-weekly periods is **not recommended** because the number of bi-weekly periods per month varies (2 or 3). Document this clearly, but provide a safe fallback.

**File:** `app/Services/Payroll/PayrollCalculationService.php`

```php
/**
 * Get the amount multiplier for a deduction in the current period.
 *
 * - per_cutoff / monthly_only:  Always 1.0 (full amount)
 * - split_monthly (semi-monthly): 0.5 (half each cutoff)
 * - split_monthly (bi-weekly):    0.5 (NOTE: months with 3 bi-weekly periods will
 *   result in 1.5× monthly total for that month — avoid using split_monthly on
 *   bi-weekly cycles; use per_cutoff instead and accept minor over-deduction)
 *
 * @param array  $deductionConfig
 * @param string $frequency
 * @return float
 */
private function getDeductionMultiplier(array $deductionConfig, string $frequency = 'semi_monthly'): float
{
    $timing = $deductionConfig['timing'] ?? 'monthly_only';

    if ($timing !== 'split_monthly') {
        return 1.0;
    }

    // For both semi-monthly and bi-weekly, 0.5 per period.
    // For bi-weekly months with 3 periods, this results in 1.5× the monthly amount.
    // Use monthly_only or per_cutoff for bi-weekly instead of split_monthly.
    return 0.5;
}
```

---

### Phase 7: Update `calculateBasicPay()` for bi-weekly salary

**File:** `app/Services/Payroll/PayrollCalculationService.php`

```php
/**
 * Calculate basic pay for employee based on period frequency.
 *
 * Salary type 'monthly':
 *  - semi_monthly: monthly_salary ÷ 2          (₱20,000 → ₱10,000 per period)
 *  - bi_weekly:    monthly_salary × 12 ÷ 26    (₱20,000 → ₱9,230.77 per period)
 *  - monthly:      full monthly_salary          (₱20,000 → ₱20,000 per period)
 *  - weekly:       monthly_salary × 12 ÷ 52    (₱20,000 → ₱4,615.38 per period)
 *
 * @param int $daysWorked
 * @param EmployeePayrollInfo $payrollInfo
 * @param PayrollPeriod $period
 * @return float
 */
private function calculateBasicPay(int $daysWorked, EmployeePayrollInfo $payrollInfo, PayrollPeriod $period): float
{
    if ($payrollInfo->salary_type === 'monthly') {
        $frequency = $this->inferPeriodFrequency($period);

        return match ($frequency) {
            'bi_weekly'   => round(((float) $payrollInfo->basic_salary * 12) / 26, 4),
            'monthly'     => (float) $payrollInfo->basic_salary,
            'weekly'      => round(((float) $payrollInfo->basic_salary * 12) / 52, 4),
            default       => (float) $payrollInfo->basic_salary / 2, // semi_monthly
        };
    }

    return match ($payrollInfo->salary_type) {
        'daily'  => $daysWorked * ((float) ($payrollInfo->daily_rate    ?? 0)),
        'hourly' => $daysWorked * 8 * ((float) ($payrollInfo->hourly_rate ?? 0)),
        default  => 0.0,
    };
}
```

---

### Phase 8: Update `calculateEmployee()` to use `getPeriodPosition()`

**File:** `app/Services/Payroll/PayrollCalculationService.php`

In `calculateEmployee()`, locate the block that calculates the period position and replace:

```php
// REMOVE these two lines:
$periodHalf = $this->getPeriodHalf($period);
$deductionConfig = $this->getDeductionTimingConfig($period);

// REPLACE WITH:
$position        = $this->getPeriodPosition($period);
$periodHalf      = $position['half'];
$isLastInMonth   = $position['is_last_in_month'];
$periodFrequency = $position['frequency'];
$deductionConfig = $this->getDeductionTimingConfig($period);
```

Then update every `shouldApplyDeduction()` call in the method to pass the new arguments:

```php
// SSS Contribution
if ($this->shouldApplyDeduction($deductionConfig['sss'], $periodHalf, $isLastInMonth, $periodFrequency)) {
    $sssContribution = $this->calculateSSSContribution($payrollInfo);
    $sssContribution *= $this->getDeductionMultiplier($deductionConfig['sss'], $periodFrequency);
} else {
    $sssContribution = 0.0;
}

// PhilHealth Contribution
if ($this->shouldApplyDeduction($deductionConfig['philhealth'], $periodHalf, $isLastInMonth, $periodFrequency)) {
    $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
    $philhealthContribution *= $this->getDeductionMultiplier($deductionConfig['philhealth'], $periodFrequency);
} else {
    $philhealthContribution = 0.0;
}

// Pag-IBIG Contribution
if ($this->shouldApplyDeduction($deductionConfig['pagibig'], $periodHalf, $isLastInMonth, $periodFrequency)) {
    $pagibigContribution = $this->calculatePagIBIGContribution($payrollInfo);
    $pagibigContribution *= $this->getDeductionMultiplier($deductionConfig['pagibig'], $periodFrequency);
} else {
    $pagibigContribution = 0.0;
}

// Withholding Tax
if ($this->shouldApplyDeduction($deductionConfig['withholding_tax'], $periodHalf, $isLastInMonth, $periodFrequency)) {
    // For bi-weekly monthly gross projection: multiply period pay by 26/12
    $grossMultiplier = $periodFrequency === 'bi_weekly' ? (26 / 12) : 2;
    $monthlyGross    = ($basicPay * $grossMultiplier) + $totalAllowances;
    $sssFull         = $sssContribution > 0
        ? $sssContribution / $this->getDeductionMultiplier($deductionConfig['sss'], $periodFrequency)
        : $this->calculateSSSContribution($payrollInfo);
    $phFull          = $philhealthContribution > 0
        ? $philhealthContribution / $this->getDeductionMultiplier($deductionConfig['philhealth'], $periodFrequency)
        : $this->calculatePhilHealthContribution($payrollInfo);
    $pagFull         = $pagibigContribution > 0
        ? $pagibigContribution / $this->getDeductionMultiplier($deductionConfig['pagibig'], $periodFrequency)
        : $this->calculatePagIBIGContribution($payrollInfo);
    $monthlyTaxable  = $monthlyGross - $sssFull - $phFull - $pagFull;
    $withholdingTax  = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
    $withholdingTax *= $this->getDeductionMultiplier($deductionConfig['withholding_tax'], $periodFrequency);
} else {
    $withholdingTax = 0.0;
}

// Loan deductions
if ($this->shouldApplyDeduction($deductionConfig['loans'], $periodHalf, $isLastInMonth, $periodFrequency)) {
    $loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);
    $loanDeductions *= $this->getDeductionMultiplier($deductionConfig['loans'], $periodFrequency);
} else {
    $loanDeductions = 0.0;
}
```

---

### Phase 9: Frontend — Deduction Timing UI for Bi-Weekly

**File:** `resources/js/components/payroll/period-form-modal.tsx`

When the selected `period_type` is `bi_weekly`, the deduction timing options should:
1. Rename "Monthly Only" → "End of Month (Last Period)"
2. Hide the "1st Cutoff / 2nd Cutoff" apply_on_period selector (not applicable for bi-weekly)
3. Add a note that `split_monthly` is not recommended for bi-weekly

#### Change 1: Pass `periodType` into the deduction section

In the deduction timing map, replace the static options with period-type-aware ones:

```tsx
// Add this helper in the component (after DEDUCTION_TYPES const):
function getTimingOptions(periodType: string) {
    if (periodType === 'bi_weekly') {
        return [
            { value: 'system_default', label: 'System Default' },
            { value: 'per_cutoff',     label: 'Every Period (26×/year)' },
            { value: 'monthly_only',   label: 'End of Month (Last Period)' },
        ];
    }
    return [
        { value: 'system_default', label: 'System Default' },
        { value: 'per_cutoff',     label: 'Every Cutoff' },
        { value: 'monthly_only',   label: 'Monthly Only' },
        { value: 'split_monthly',  label: 'Split Monthly' },
    ];
}
```

#### Change 2: Use `getTimingOptions` in the deduction dropdown map

Replace the static `<SelectContent>` block inside the deduction timing map:

```tsx
{DEDUCTION_TYPES.map((deductionType) => {
    const currentOverride = formData.deduction_timing?.[deductionType.key as keyof typeof formData.deduction_timing];
    const currentTiming   = currentOverride?.timing ?? 'system_default';
    const currentPeriod   = currentOverride?.apply_on_period ?? 2;
    const timingOptions   = getTimingOptions(formData.period_type);

    return (
        <div key={deductionType.key} className="flex items-center gap-3">
            <Label className="w-36 mb-0">{deductionType.label}</Label>
            <Select
                value={currentTiming}
                onValueChange={(val) =>
                    handleSetDeductionOverride(deductionType.key, 'timing', val)
                }
                disabled={isLoading}
            >
                <SelectTrigger className="w-48">
                    <SelectValue placeholder="System Default" />
                </SelectTrigger>
                <SelectContent>
                    {timingOptions.map(opt => (
                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Only show apply_on_period for non-bi-weekly monthly_only */}
            {currentTiming === 'monthly_only' && formData.period_type !== 'bi_weekly' && (
                <Select
                    value={String(currentPeriod)}
                    onValueChange={(val) =>
                        handleSetDeductionOverride(deductionType.key, 'apply_on_period', parseInt(val))
                    }
                    disabled={isLoading}
                >
                    <SelectTrigger className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">1st Cutoff</SelectItem>
                        <SelectItem value="2">2nd Cutoff</SelectItem>
                    </SelectContent>
                </Select>
            )}
        </div>
    );
})}
```

#### Change 3: Add a help note for bi-weekly in the collapsible description

Below the existing `<p className="text-sm text-muted-foreground">` line, add:

```tsx
{formData.period_type === 'bi_weekly' && (
    <p className="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950 rounded px-3 py-2">
        <strong>Bi-weekly note:</strong> "End of Month" applies deductions on the last
        bi-weekly period each month. "Every Period" deducts 26× per year.
        Split Monthly is not recommended for bi-weekly periods.
    </p>
)}
```

---

### Phase 10: Unit Tests

**File:** `tests/Unit/Services/Payroll/PayrollCalculationServiceBiWeeklyTest.php`

```php
<?php

namespace Tests\Unit\Services\Payroll;

use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollCalculationService;
use Tests\TestCase;
use Carbon\Carbon;
use Mockery;
use ReflectionClass;

class PayrollCalculationServiceBiWeeklyTest extends TestCase
{
    private PayrollCalculationService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PayrollCalculationService(
            Mockery::mock(\App\Services\Payroll\EmployeePayrollInfoService::class),
            Mockery::mock(\App\Services\Payroll\SalaryComponentService::class),
            Mockery::mock(\App\Services\Payroll\AllowanceDeductionService::class),
            Mockery::mock(\App\Services\Payroll\LoanManagementService::class),
        );

        $this->reflection = new ReflectionClass($this->service);
    }

    private function callMethod(string $method, array $args = mixed): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->service, $args);
    }

    private function makePeriod(string $start, string $end): PayrollPeriod
    {
        $period = Mockery::mock(PayrollPeriod::class)->makePartial();
        $period->period_start = Carbon::parse($start);
        $period->period_end   = Carbon::parse($end);
        $period->calculation_config = null;
        return $period;
    }

    // -----------------------------------------------------------------------
    // inferPeriodFrequency
    // -----------------------------------------------------------------------

    /** @test */
    public function it_correctly_identifies_bi_weekly_period(): void
    {
        $period = $this->makePeriod('2026-01-01', '2026-01-14'); // 13-day diff
        $freq   = $this->callMethod('inferPeriodFrequency', [$period]);
        $this->assertSame('bi_weekly', $freq);
    }

    /** @test */
    public function it_correctly_identifies_semi_monthly_period(): void
    {
        $period = $this->makePeriod('2026-01-01', '2026-01-15'); // 14-day diff
        $freq   = $this->callMethod('inferPeriodFrequency', [$period]);
        $this->assertSame('semi_monthly', $freq);
    }

    /** @test */
    public function it_correctly_identifies_second_semi_monthly_half(): void
    {
        $period = $this->makePeriod('2026-01-16', '2026-01-31'); // 15-day diff
        $freq   = $this->callMethod('inferPeriodFrequency', [$period]);
        $this->assertSame('semi_monthly', $freq);
    }

    // -----------------------------------------------------------------------
    // isLastBiWeeklyPeriodInMonth
    // -----------------------------------------------------------------------

    /** @test */
    public function first_bi_weekly_is_not_last_in_month(): void
    {
        $period = $this->makePeriod('2026-01-01', '2026-01-14');
        // Next start: Jan 15 — same month → NOT last
        $result = $this->callMethod('isLastBiWeeklyPeriodInMonth', [$period]);
        $this->assertFalse($result);
    }

    /** @test */
    public function second_bi_weekly_is_not_last_when_third_is_in_same_month(): void
    {
        $period = $this->makePeriod('2026-01-15', '2026-01-28');
        // Next start: Jan 29 — same month → NOT last
        $result = $this->callMethod('isLastBiWeeklyPeriodInMonth', [$period]);
        $this->assertFalse($result);
    }

    /** @test */
    public function third_bi_weekly_is_last_when_next_is_in_feb(): void
    {
        $period = $this->makePeriod('2026-01-29', '2026-02-11');
        // Next start: Feb 12 — different month from period_start (Jan) → IS last
        $result = $this->callMethod('isLastBiWeeklyPeriodInMonth', [$period]);
        $this->assertTrue($result);
    }

    /** @test */
    public function last_february_bi_weekly_is_detected_correctly(): void
    {
        $period = $this->makePeriod('2026-02-12', '2026-02-25');
        // Next start: Feb 26 — same month → NOT last
        $result = $this->callMethod('isLastBiWeeklyPeriodInMonth', [$period]);
        $this->assertFalse($result);
    }

    /** @test */
    public function february_period_that_ends_in_march_is_last(): void
    {
        $period = $this->makePeriod('2026-02-26', '2026-03-11');
        // Next start: Mar 12 — different month from period_start (Feb) → IS last
        $result = $this->callMethod('isLastBiWeeklyPeriodInMonth', [$period]);
        $this->assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // shouldApplyDeduction — bi-weekly monthly_only
    // -----------------------------------------------------------------------

    /** @test */
    public function monthly_only_does_not_apply_on_non_last_bi_weekly_period(): void
    {
        $config = ['timing' => 'monthly_only', 'apply_on_period' => 2];
        $result = $this->callMethod('shouldApplyDeduction', [$config, 1, false, 'bi_weekly']);
        $this->assertFalse($result);
    }

    /** @test */
    public function monthly_only_applies_on_last_bi_weekly_period(): void
    {
        $config = ['timing' => 'monthly_only', 'apply_on_period' => 2];
        $result = $this->callMethod('shouldApplyDeduction', [$config, 2, true, 'bi_weekly']);
        $this->assertTrue($result);
    }

    /** @test */
    public function per_cutoff_always_applies_for_bi_weekly(): void
    {
        $config = ['timing' => 'per_cutoff'];

        $this->assertTrue($this->callMethod('shouldApplyDeduction', [$config, 1, false, 'bi_weekly']));
        $this->assertTrue($this->callMethod('shouldApplyDeduction', [$config, 1, true,  'bi_weekly']));
    }

    // -----------------------------------------------------------------------
    // shouldApplyDeduction — semi-monthly backward compatibility
    // -----------------------------------------------------------------------

    /** @test */
    public function semi_monthly_monthly_only_on_second_half_still_works(): void
    {
        $config = ['timing' => 'monthly_only', 'apply_on_period' => 2];

        $this->assertFalse($this->callMethod('shouldApplyDeduction', [$config, 1, false, 'semi_monthly']));
        $this->assertTrue ($this->callMethod('shouldApplyDeduction', [$config, 2, true,  'semi_monthly']));
    }

    /** @test */
    public function semi_monthly_monthly_only_on_first_half_works(): void
    {
        $config = ['timing' => 'monthly_only', 'apply_on_period' => 1];

        $this->assertTrue ($this->callMethod('shouldApplyDeduction', [$config, 1, false, 'semi_monthly']));
        $this->assertFalse($this->callMethod('shouldApplyDeduction', [$config, 2, true,  'semi_monthly']));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

---

## Controller Validation Changes (Phase 11)

No new timing modes need to be added to validators — the existing `per_cutoff` and `monthly_only` values work for bi-weekly with the updated backend logic.

However, document that `apply_on_period` is **ignored** for bi-weekly periods:

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`

In both `store()` and `update()`, add a comment above the deduction timing validation block:

```php
// Deduction timing overrides (optional)
// Note: for bi_weekly periods, 'apply_on_period' is ignored; 'monthly_only'
// automatically applies to the last period of the month.
'deduction_timing' => 'nullable|array',
// ... (existing rules unchanged)
```

---

## Migration Checklist

No database migrations are required for this feature. All changes are in:
1. PHP service logic
2. PHP controller logic (1 line)  
3. TypeScript/TSX component (minor UI changes)
4. New unit test file

---

## Progress Tracker

- [ ] **Phase 1** — Fix `inferFrequency()` in `PayrollPeriodController` (1 line change)
- [ ] **Phase 2** — Add `inferPeriodFrequency()` method to `PayrollCalculationService`
- [ ] **Phase 3** — Add `isLastBiWeeklyPeriodInMonth()` method to `PayrollCalculationService`
- [ ] **Phase 4** — Add `getPeriodPosition()` method to `PayrollCalculationService`
- [ ] **Phase 5** — Update `shouldApplyDeduction()` signature to accept `isLastInMonth` and `frequency`
- [ ] **Phase 6** — Update `getDeductionMultiplier()` to accept `frequency`
- [ ] **Phase 7** — Update `calculateBasicPay()` to handle bi-weekly salary divisor  
- [ ] **Phase 8** — Update `calculateEmployee()` to use `getPeriodPosition()` and pass new args
- [ ] **Phase 9** — Update `period-form-modal.tsx` with bi-weekly-aware UI
- [ ] **Phase 10** — Write and run unit tests (`PayrollCalculationServiceBiWeeklyTest.php`)
- [ ] **Phase 11** — Add comment to controller validation (documentation only)

---

## Test Plan

### Unit Tests
- `PayrollCalculationServiceBiWeeklyTest` — 11 test methods (see Phase 10)
- Regression: run `PayrollCalculationServiceDeductionTimingTest` and `PayrollCalculationServicePeriodOverridesTest` to confirm no regressions

### Manual Tests
1. Create a bi-weekly period (Jan 1–14, 2026) with SSS = "End of Month (Last Period)"
2. Trigger calculation — SSS should be ₱0 (not last period)
3. Create a bi-weekly period (Jan 29 – Feb 11, 2026 for January last period)
4. Trigger calculation — SSS should apply (is last period in January)
5. Create a bi-weekly period with SSS = "Every Period"
6. Trigger calculation — SSS should apply for all periods

### Regression Verification
1. Create a semi-monthly period (Jan 1–15) with `monthly_only` SSS — first half should have ₱0 SSS ✓
2. Create a semi-monthly period (Jan 16–31) — second half should have SSS applied ✓

---

## Known Limitations / Out of Scope

1. **`split_monthly` for bi-weekly** — Not supported. Months with 3 bi-weekly periods create 1.5× overdeduction. Recommend using `per_cutoff` or `monthly_only` for bi-weekly.
2. **Global config** (`PayrollConfiguration` table) — Currently stores semi-monthly defaults. A future improvement could support per-frequency defaults (separate config entries per frequency type).
3. **Period auto-generation** — No auto-generation of bi-weekly schedule. Periods must still be manually created at this time.
4. **Payslip display** — Payslip templates may need to be aware of bi-weekly to display the correct "period N of 26" label.

---

## Related Files

| File | Change Required |
|---|---|
| `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php` | Fix `inferFrequency()` threshold |
| `app/Services/Payroll/PayrollCalculationService.php` | Add 4 methods, update 3 methods, update `calculateEmployee()` |
| `resources/js/components/payroll/period-form-modal.tsx` | Add `getTimingOptions()`, update deduction timing map, add bi-weekly note |
| `tests/Unit/Services/Payroll/PayrollCalculationServiceBiWeeklyTest.php` | **New file** — 11 unit tests |

---

## Related Plans

- [PAYROLL_DEDUCTION_TIMING_CONFIGURATION.md](./PAYROLL_DEDUCTION_TIMING_CONFIGURATION.md) — Prerequisite (completed)
