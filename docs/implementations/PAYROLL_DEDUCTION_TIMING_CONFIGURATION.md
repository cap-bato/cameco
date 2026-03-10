# Implementation Plan: Configurable Deduction Timing (Per Cutoff vs Monthly)

**Created:** 2026-03-10  
**Priority:** HIGH — Critical for compliance with Philippine payroll regulations  
**Risk:** MEDIUM — Modifies core calculation logic; requires careful testing

---

## Summary

Make government deductions (SSS, PhilHealth, Pag-IBIG, Tax) and loan deductions configurable for when they are applied:
- **Per Cutoff** — Deduct every period (1st and 2nd cutoff)
- **Monthly Only** — Deduct once per month (2nd cutoff only, current behavior)
- **Split Monthly** — Deduct half each cutoff (total monthly amount split across both periods)

This addresses Philippine payroll requirements where:
- SSS contributions can be split or full monthly
- PhilHealth is typically monthly
- Pag-IBIG is typically monthly  
- Withholding tax is typically monthly
- Loan deductions are typically monthly

---

## Current Behavior (Problem Statement)

### How It Works Now

The `PayrollCalculationService::calculateEmployee()` method currently has **hardcoded logic** at lines 145-166:

```php
$periodHalf = $this->getPeriodHalf($period);

if ($periodHalf === 2) {
    // 2nd half: Apply all government deductions
    $sssContribution        = $this->calculateSSSContribution($payrollInfo);
    $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
    $pagibigContribution    = $this->calculatePagIBIGContribution($payrollInfo);
    
    $monthlyGross   = ($basicPay * 2) + $totalAllowances;
    $monthlyTaxable = $monthlyGross - $sssContribution - $philhealthContribution - $pagibigContribution;
    $withholdingTax = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
} else {
    // 1st half: No government deductions
    $sssContribution        = 0.0;
    $philhealthContribution = 0.0;
    $pagibigContribution    = 0.0;
    $withholdingTax         = 0.0;
}
```

**Loan deductions** also have the same hardcoded logic (lines 176-178):
```php
$loanDeductions = $periodHalf === 2
    ? $this->loanManagementService->processLoanDeduction($employee, $period)
    : 0.0;
```

### The Problem

1. **Not configurable** — Timing is hardcoded, cannot be changed per organization policy
2. **No flexibility** — Cannot handle split deductions (half per cutoff)
3. **No per-deduction control** — All deductions use the same timing (2nd cutoff only)
4. **Cannot support per-cutoff** — Some orgs deduct government contributions every cutoff

### Business Requirements

Different organizations have different deduction policies:

| Deduction Type | Policy A (Current) | Policy B (Common) | Policy C (Alternative) |
|---|---|---|---|
| SSS | 2nd cutoff only | Split (half each) | Every cutoff |
| PhilHealth | 2nd cutoff only | 2nd cutoff only | Every cutoff |
| Pag-IBIG | 2nd cutoff only | 2nd cutoff only | Every cutoff |
| Withholding Tax | 2nd cutoff only | Split (half each) | Every cutoff |
| Loan Deductions | 2nd cutoff only | 2nd cutoff only | Every cutoff |

---

## Proposed Solution

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  PayrollConfiguration (New Table)                               │
│  ─────────────────────────────────                              │
│  • Global deduction timing rules                                │
│  • JSON config for each deduction type                          │
│  • Effective date ranges                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  PayrollCalculationService (Modified)                           │
│  ─────────────────────────────────                              │
│  • Read configuration before calculating deductions             │
│  • Apply timing rules per deduction type                        │
│  • Support per_cutoff / monthly_only / split_monthly            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  EmployeePayrollCalculation (Unchanged)                         │
│  ─────────────────────────────────                              │
│  • Stores final calculated amounts                              │
│  • No schema changes needed                                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

### Phase 1: Create PayrollConfiguration Model and Table

**New Migration:** `database/migrations/2026_03_10_000000_create_payroll_configurations_table.php`

```php
Schema::create('payroll_configurations', function (Blueprint $table) {
    $table->id();
    $table->string('config_key')->unique(); // 'deduction_timing'
    $table->json('config_value'); // Flexible JSON structure
    $table->string('description')->nullable();
    $table->date('effective_from')->nullable();
    $table->date('effective_to')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('config_key');
    $table->index(['is_active', 'effective_from', 'effective_to']);
});
```

**New Model:** `app/Models/PayrollConfiguration.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'config_value' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now();
        
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }

    // Helper Methods
    public static function get(string $key, $default = null)
    {
        $config = self::active()
            ->effectiveOn()
            ->where('config_key', $key)
            ->first();

        return $config?->config_value ?? $default;
    }

    public static function set(string $key, $value, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $value,
                'description' => $description,
                'effective_from' => now(),
                'is_active' => true,
                'updated_by' => auth()->id(),
            ]
        );
    }

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

---

### Phase 2: Create Deduction Timing Seeder ✅ COMPLETE

**New Seeder:** `database/seeders/PayrollConfigurationSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PayrollConfiguration;
use Carbon\Carbon;

class PayrollConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // Deduction Timing Configuration
        PayrollConfiguration::updateOrCreate(
            ['config_key' => 'deduction_timing'],
            [
                'config_value' => [
                    'sss' => [
                        'timing' => 'monthly_only',  // per_cutoff | monthly_only | split_monthly
                        'apply_on_period' => 2, // 1 = 1st cutoff, 2 = 2nd cutoff, null = both
                        'description' => 'SSS contribution - once per month on 2nd cutoff',
                    ],
                    'philhealth' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'PhilHealth premium - once per month on 2nd cutoff',
                    ],
                    'pagibig' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Pag-IBIG contribution - once per month on 2nd cutoff',
                    ],
                    'withholding_tax' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Withholding tax - once per month on 2nd cutoff',
                    ],
                    'loans' => [
                        'timing' => 'monthly_only',
                        'apply_on_period' => 2,
                        'description' => 'Loan installments - once per month on 2nd cutoff',
                    ],
                ],
                'description' => 'Deduction timing configuration for semi-monthly payroll',
                'effective_from' => Carbon::now(),
                'is_active' => true,
            ]
        );

        // Alternative configuration examples (commented out)
        
        // Example 1: All deductions per cutoff
        /*
        PayrollConfiguration::create([
            'config_key' => 'deduction_timing_per_cutoff',
            'config_value' => [
                'sss' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'philhealth' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'pagibig' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'withholding_tax' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
                'loans' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
            ],
            'description' => 'All deductions applied every cutoff (for testing)',
            'effective_from' => Carbon::now(),
            'is_active' => false,
        ]);
        */

        // Example 2: Split monthly deductions
        /*
        PayrollConfiguration::create([
            'config_key' => 'deduction_timing_split',
            'config_value' => [
                'sss' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'philhealth' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'pagibig' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'withholding_tax' => ['timing' => 'split_monthly', 'apply_on_period' => null],
                'loans' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            ],
            'description' => 'Government contributions split 50/50 across cutoffs',
            'effective_from' => Carbon::now(),
            'is_active' => false,
        ]);
        */

        $this->command->info('Payroll configuration seeded successfully!');
        $this->command->info('- Deduction timing: monthly_only (2nd cutoff)');
    }
}
```

---

### Phase 3: Modify PayrollCalculationService ✅ COMPLETE (3a, 3b, 3c, 3d Done)

**File:** `app/Services/Payroll/PayrollCalculationService.php`

#### 3a. Add configuration helper method ✅ COMPLETE

```php
/**
 * Get deduction timing configuration
 *
 * @return array
 */
private function getDeductionTimingConfig(): array
{
    $config = PayrollConfiguration::get('deduction_timing', []);
    
    // Default fallback if no config exists
    return array_merge([
        'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'philhealth' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'pagibig' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'loans' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
    ], $config);
}

/**
 * Determine if a deduction should be applied for the given period
 *
 * @param array $deductionConfig  Config for specific deduction type
 * @param int $periodHalf         1 or 2 (which half of month)
 * @return bool
 */
private function shouldApplyDeduction(array $deductionConfig, int $periodHalf): bool
{
    $timing = $deductionConfig['timing'] ?? 'monthly_only';
    $applyOnPeriod = $deductionConfig['apply_on_period'] ?? 2;

    return match ($timing) {
        'per_cutoff' => true, // Apply every period
        'monthly_only' => $periodHalf === $applyOnPeriod, // Apply only on specified period
        'split_monthly' => true, // Apply every period but split amount
        default => $periodHalf === 2, // Fallback to 2nd period only
    };
}

/**
 * Get the multiplier for split monthly deductions
 *
 * @param array $deductionConfig  Config for specific deduction type
 * @return float  1.0 for full amount, 0.5 for half amount
 */
private function getDeductionMultiplier(array $deductionConfig): float
{
    $timing = $deductionConfig['timing'] ?? 'monthly_only';

    return match ($timing) {
        'split_monthly' => 0.5, // Half the monthly amount
        default => 1.0, // Full amount
    };
}
```

#### 3b. Replace hardcoded deduction logic (around lines 145-166) ✅ COMPLETE

**OLD CODE:**
```php
$periodHalf = $this->getPeriodHalf($period);

if ($periodHalf === 2) {
    $sssContribution        = $this->calculateSSSContribution($payrollInfo);
    $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
    $pagibigContribution    = $this->calculatePagIBIGContribution($payrollInfo);
    
    $monthlyGross   = ($basicPay * 2) + $totalAllowances;
    $monthlyTaxable = $monthlyGross - $sssContribution - $philhealthContribution - $pagibigContribution;
    $withholdingTax = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
} else {
    $sssContribution        = 0.0;
    $philhealthContribution = 0.0;
    $pagibigContribution    = 0.0;
    $withholdingTax         = 0.0;
}
```

**NEW CODE:**
```php
// Step 9 & 10: Government contributions and tax — configurable timing
$periodHalf = $this->getPeriodHalf($period);
$deductionConfig = $this->getDeductionTimingConfig();

// SSS Contribution
if ($this->shouldApplyDeduction($deductionConfig['sss'], $periodHalf)) {
    $sssContribution = $this->calculateSSSContribution($payrollInfo);
    $sssContribution *= $this->getDeductionMultiplier($deductionConfig['sss']);
} else {
    $sssContribution = 0.0;
}

// PhilHealth Contribution
if ($this->shouldApplyDeduction($deductionConfig['philhealth'], $periodHalf)) {
    $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
    $philhealthContribution *= $this->getDeductionMultiplier($deductionConfig['philhealth']);
} else {
    $philhealthContribution = 0.0;
}

// Pag-IBIG Contribution
if ($this->shouldApplyDeduction($deductionConfig['pagibig'], $periodHalf)) {
    $pagibigContribution = $this->calculatePagIBIGContribution($payrollInfo);
    $pagibigContribution *= $this->getDeductionMultiplier($deductionConfig['pagibig']);
} else {
    $pagibigContribution = 0.0;
}

// Withholding Tax
if ($this->shouldApplyDeduction($deductionConfig['withholding_tax'], $periodHalf)) {
    // Calculate tax based on monthly gross projection
    $monthlyGross   = ($basicPay * 2) + $totalAllowances;
    $monthlyTaxable = $monthlyGross - ($sssContribution / $this->getDeductionMultiplier($deductionConfig['sss']))
                                   - ($philhealthContribution / $this->getDeductionMultiplier($deductionConfig['philhealth']))
                                   - ($pagibigContribution / $this->getDeductionMultiplier($deductionConfig['pagibig']));
    $withholdingTax = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
    $withholdingTax *= $this->getDeductionMultiplier($deductionConfig['withholding_tax']);
} else {
    $withholdingTax = 0.0;
}
```

#### 3c. Replace hardcoded loan deduction logic (around lines 176-178) ✅ COMPLETE

**OLD CODE:**
```php
$loanDeductions = $periodHalf === 2
    ? $this->loanManagementService->processLoanDeduction($employee, $period)
    : 0.0;
```

**NEW CODE:**
```php
// Step 12: Calculate loan deductions — configurable timing
if ($this->shouldApplyDeduction($deductionConfig['loans'], $periodHalf)) {
    $loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);
    $loanDeductions *= $this->getDeductionMultiplier($deductionConfig['loans']);
} else {
    $loanDeductions = 0.0;
}
```

#### 3d. Add import at top of file ✅ COMPLETE

```php
use App\Models\PayrollConfiguration;
```

---

### Phase 4: Testing Strategy ✅ COMPLETE

> **Status:** Implemented and all 10 tests passing  
> **File:** `tests/Unit/Services/Payroll/PayrollCalculationServiceDeductionTimingTest.php`  
> **Coverage:** monthly_only, per_cutoff, split_monthly, default fallback, mixed config, loan timing  
> **Note:** Also fixed SQLite-incompatible `ALTER TABLE DROP CONSTRAINT` in `2026_03_09_000001_fix_payroll_calculation_versioning.php` to be PostgreSQL-only (guarded by `DB::getDriverName() === 'pgsql'`)

#### Unit Tests

**File:** `tests/Unit/Services/Payroll/PayrollCalculationServiceDeductionTimingTest.php`

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollConfiguration;
use App\Models\EmployeePayrollInfo;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollCalculationServiceDeductionTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_only_deductions_apply_on_2nd_cutoff()
    {
        // Setup: Configure monthly_only timing
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'pagibig' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        ]);

        $employee = Employee::factory()->create();
        $period1st = PayrollPeriod::factory()->create([
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
        ]);
        $period2nd = PayrollPeriod::factory()->create([
            'period_start' => '2026-03-16',
            'period_end' => '2026-03-31',
        ]);

        $service = app(PayrollCalculationService::class);

        // Test 1st cutoff: No deductions
        $calc1st = $service->calculateEmployee($employee, $period1st);
        $this->assertEquals(0, $calc1st->sss_contribution);
        $this->assertEquals(0, $calc1st->philhealth_contribution);
        $this->assertEquals(0, $calc1st->pagibig_contribution);
        $this->assertEquals(0, $calc1st->withholding_tax);

        // Test 2nd cutoff: Full deductions
        $calc2nd = $service->calculateEmployee($employee, $period2nd);
        $this->assertGreaterThan(0, $calc2nd->sss_contribution);
        $this->assertGreaterThan(0, $calc2nd->philhealth_contribution);
        $this->assertGreaterThan(0, $calc2nd->pagibig_contribution);
        $this->assertGreaterThan(0, $calc2nd->withholding_tax);
    }

    public function test_per_cutoff_deductions_apply_every_period()
    {
        // Setup: Configure per_cutoff timing
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
            'philhealth' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
            'pagibig' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
            'withholding_tax' => ['timing' => 'per_cutoff', 'apply_on_period' => null],
        ]);

        $employee = Employee::factory()->create();
        $period1st = PayrollPeriod::factory()->create([
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
        ]);

        $service = app(PayrollCalculationService::class);

        // Test 1st cutoff: Should have deductions
        $calc1st = $service->calculateEmployee($employee, $period1st);
        $this->assertGreaterThan(0, $calc1st->sss_contribution);
        $this->assertGreaterThan(0, $calc1st->philhealth_contribution);
    }

    public function test_split_monthly_deductions_half_each_cutoff()
    {
        // Setup: Configure split_monthly timing
        PayrollConfiguration::set('deduction_timing', [
            'sss' => ['timing' => 'split_monthly', 'apply_on_period' => null],
            'philhealth' => ['timing' => 'split_monthly', 'apply_on_period' => null],
            'pagibig' => ['timing' => 'split_monthly', 'apply_on_period' => null],
            'withholding_tax' => ['timing' => 'split_monthly', 'apply_on_period' => null],
        ]);

        $employee = Employee::factory()->create();
        $period1st = PayrollPeriod::factory()->create([
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-15',
        ]);
        $period2nd = PayrollPeriod::factory()->create([
            'period_start' => '2026-03-16',
            'period_end' => '2026-03-31',
        ]);

        $service = app(PayrollCalculationService::class);

        // Calculate both cutoffs
        $calc1st = $service->calculateEmployee($employee, $period1st);
        $calc2nd = $service->calculateEmployee($employee, $period2nd);

        // Both should have deductions
        $this->assertGreaterThan(0, $calc1st->sss_contribution);
        $this->assertGreaterThan(0, $calc2nd->sss_contribution);

        // Total should equal full monthly (approximately, accounting for rounding)
        $totalSSS = $calc1st->sss_contribution + $calc2nd->sss_contribution;
        // Add assertion comparing to expected monthly amount
    }
}
```

#### Integration Test Scenarios

1. **Scenario 1: Default Configuration (Current Behavior)**
   - Config: monthly_only on 2nd cutoff
   - Expected: 1st cutoff = ₱0 deductions, 2nd cutoff = full monthly deductions

2. **Scenario 2: Per Cutoff**
   - Config: per_cutoff for all deductions
   - Expected: Both cutoffs have full deductions (total = 2× monthly rate)
   - **WARNING**: This doubles total deductions per month

3. **Scenario 3: Split Monthly**
   - Config: split_monthly for all deductions
   - Expected: Both cutoffs have half deductions (total = monthly rate)

4. **Scenario 4: Mixed Configuration**
   - Config: SSS split_monthly, PhilHealth monthly_only, Tax per_cutoff
   - Expected: Different timing per deduction type

---

### Phase 5a: Admin UI for Global Configuration Management (Optional)

**Controller:** `app/Http/Controllers/Admin/PayrollConfigurationController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollConfigurationController extends Controller
{
    public function deductionTiming()
    {
        $config = PayrollConfiguration::where('config_key', 'deduction_timing')
            ->active()
            ->effectiveOn()
            ->first();

        return Inertia::render('Admin/PayrollConfiguration/DeductionTiming', [
            'config' => $config?->config_value ?? [],
        ]);
    }

    public function updateDeductionTiming(Request $request)
    {
        $validated = $request->validate([
            'sss.timing' => 'required|in:per_cutoff,monthly_only,split_monthly',
            'sss.apply_on_period' => 'nullable|in:1,2',
            'philhealth.timing' => 'required|in:per_cutoff,monthly_only,split_monthly',
            'philhealth.apply_on_period' => 'nullable|in:1,2',
            'pagibig.timing' => 'required|in:per_cutoff,monthly_only,split_monthly',
            'pagibig.apply_on_period' => 'nullable|in:1,2',
            'withholding_tax.timing' => 'required|in:per_cutoff,monthly_only,split_monthly',
            'withholding_tax.apply_on_period' => 'nullable|in:1,2',
            'loans.timing' => 'required|in:per_cutoff,monthly_only,split_monthly',
            'loans.apply_on_period' => 'nullable|in:1,2',
        ]);

        PayrollConfiguration::set(
            'deduction_timing',
            $validated,
            'Deduction timing configuration for semi-monthly payroll'
        );

        return redirect()->back()
            ->with('success', 'Deduction timing configuration updated successfully.');
    }
}
```

**Frontend:** `resources/js/pages/Admin/PayrollConfiguration/DeductionTiming.tsx`

```typescript
// Simple form with radio buttons for each deduction type:
// - Per Cutoff (every period)
// - Monthly Only (select 1st or 2nd cutoff)
// - Split Monthly (half each cutoff)
```

**Routes:** Add to `routes/admin.php`

```php
Route::prefix('payroll-configuration')->name('payroll-configuration.')->group(function () {
    Route::get('deduction-timing', [PayrollConfigurationController::class, 'deductionTiming'])
        ->name('deduction-timing');
    Route::post('deduction-timing', [PayrollConfigurationController::class, 'updateDeductionTiming'])
        ->name('deduction-timing.update');
});
```

---

### Phase 5b: Officer-Facing Deduction Timing UI (Period Creation Page)

**Purpose:** Allow payroll officers to override deduction timing per payroll period at creation/edit time, without needing admin access. Overrides are stored in the existing `PayrollPeriod.calculation_config` JSON field and take precedence over the global admin defaults.

#### Data Flow

```
Period creation form
  → officer sets per-deduction timing overrides (or leaves as "System Default")
  → submitted with period create/update request
  → stored in PayrollPeriod.calculation_config["deduction_timing"]
  → PayrollCalculationService checks period.calculation_config first
  → falls back to PayrollConfiguration global defaults
```

#### Backend Changes

**1. `PayrollPeriodController::store()` and `update()`** — Accept optional `deduction_timing` overrides and merge into `calculation_config`:

```php
// Add to validation rules in store() and update():
'deduction_timing' => 'nullable|array',
'deduction_timing.sss.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
'deduction_timing.sss.apply_on_period' => 'nullable|in:1,2',
'deduction_timing.philhealth.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
'deduction_timing.philhealth.apply_on_period' => 'nullable|in:1,2',
'deduction_timing.pagibig.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
'deduction_timing.pagibig.apply_on_period' => 'nullable|in:1,2',
'deduction_timing.withholding_tax.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
'deduction_timing.withholding_tax.apply_on_period' => 'nullable|in:1,2',
'deduction_timing.loans.timing' => 'nullable|in:per_cutoff,monthly_only,split_monthly',
'deduction_timing.loans.apply_on_period' => 'nullable|in:1,2',

// Then when creating/updating period, merge into calculation_config:
$calculationConfig = [];
if (!empty($validated['deduction_timing'])) {
    // Only store overrides where timing is not null
    $overrides = array_filter($validated['deduction_timing'], fn($v) => !empty($v['timing']));
    if (!empty($overrides)) {
        $calculationConfig['deduction_timing'] = $overrides;
    }
}
$period->calculation_config = $calculationConfig ?: null;
```

**2. `PayrollCalculationService::getDeductionTimingConfig()`** — Accept period parameter and check period-level overrides first:

```php
private function getDeductionTimingConfig(?PayrollPeriod $period = null): array
{
    // Start with global defaults from DB
    $globalConfig = PayrollConfiguration::get('deduction_timing', []);
    $defaults = array_merge([
        'sss'             => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'philhealth'      => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'pagibig'         => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        'loans'           => ['timing' => 'monthly_only', 'apply_on_period' => 2],
    ], $globalConfig);

    // Period-level overrides take precedence
    $periodOverrides = $period?->calculation_config['deduction_timing'] ?? [];
    if (!empty($periodOverrides)) {
        foreach ($periodOverrides as $key => $override) {
            if (isset($defaults[$key]) && !empty($override['timing'])) {
                $defaults[$key] = array_merge($defaults[$key], $override);
            }
        }
    }

    return $defaults;
}
```

> **Note:** Also update the `calculateEmployee()` call to pass `$period` into `getDeductionTimingConfig($period)` so period-level overrides are applied.

#### Frontend Changes

**Modified:** `resources/js/pages/Payroll/PayrollProcessing/Periods/Index.tsx` (or the `PeriodFormModal` component) — Add a collapsible "Deduction Timing" section at the bottom of the create/edit form.

**File:** `resources/js/pages/Payroll/PayrollProcessing/Periods/components/PeriodFormModal.tsx` (modify existing)

```typescript
// Add to PeriodFormData type:
deduction_timing?: {
  sss?: { timing: DeductionTiming; apply_on_period?: 1 | 2 } | null;
  philhealth?: { timing: DeductionTiming; apply_on_period?: 1 | 2 } | null;
  pagibig?: { timing: DeductionTiming; apply_on_period?: 1 | 2 } | null;
  withholding_tax?: { timing: DeductionTiming; apply_on_period?: 1 | 2 } | null;
  loans?: { timing: DeductionTiming; apply_on_period?: 1 | 2 } | null;
};

type DeductionTiming = 'per_cutoff' | 'monthly_only' | 'split_monthly';
```

**UI Design in `PeriodFormModal`:**

```tsx
{/* Deduction Timing Overrides — collapsible, at bottom of form */}
<Collapsible defaultOpen={false}>
  <CollapsibleTrigger>
    <ChevronDown className="h-4 w-4" />
    Deduction Timing Overrides
    <Badge variant="outline">Uses system defaults</Badge>
  </CollapsibleTrigger>
  <CollapsibleContent>
    <p className="text-sm text-muted-foreground mb-4">
      Override deduction timing for this period only. Leave blank to use system-wide defaults.
    </p>
    <div className="space-y-3">
      {/* One row per deduction type */}
      {DEDUCTION_TYPES.map((type) => (
        <div key={type.key} className="flex items-center gap-4">
          <Label className="w-36">{type.label}</Label>
          <Select
            value={form.deduction_timing?.[type.key]?.timing ?? ''}
            onValueChange={(val) => setDeductionOverride(type.key, 'timing', val)}
          >
            <SelectTrigger className="w-44">
              <SelectValue placeholder="System Default" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">System Default</SelectItem>
              <SelectItem value="per_cutoff">Every Cutoff</SelectItem>
              <SelectItem value="monthly_only">Monthly Only</SelectItem>
              <SelectItem value="split_monthly">Split Monthly</SelectItem>
            </SelectContent>
          </Select>
          {/* Show period selector only when monthly_only is chosen */}
          {form.deduction_timing?.[type.key]?.timing === 'monthly_only' && (
            <Select
              value={String(form.deduction_timing?.[type.key]?.apply_on_period ?? 2)}
              onValueChange={(val) => setDeductionOverride(type.key, 'apply_on_period', Number(val))}
            >
              <SelectTrigger className="w-36">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="1">1st Cutoff</SelectItem>
                <SelectItem value="2">2nd Cutoff</SelectItem>
              </SelectContent>
            </Select>
          )}
        </div>
      ))}
    </div>
  </CollapsibleContent>
</Collapsible>
```

**Deduction type labels constant:**

```typescript
const DEDUCTION_TYPES = [
  { key: 'sss',             label: 'SSS' },
  { key: 'philhealth',      label: 'PhilHealth' },
  { key: 'pagibig',         label: 'Pag-IBIG' },
  { key: 'withholding_tax', label: 'Withholding Tax' },
  { key: 'loans',           label: 'Loan Deductions' },
] as const;
```

#### UX Rules

| Rule | Detail |
|---|---|
| Collapsed by default | Officers who don't need overrides are unaffected |
| "System Default" option | Selecting blank = no override, global config applies |
| Badge shows active overrides | If any overrides are set, badge shows count (e.g. "2 overrides") |
| Only non-null overrides submitted | Empty selections are stripped before POST |
| Edit mode pre-populates | Existing overrides from `calculation_config` are loaded into form |
| Locked periods are read-only | Disable dropdowns when period status is not `draft` |

#### No New Routes Needed

Overrides are submitted through the existing period create/update endpoints:
- `POST /payroll/periods` — includes `deduction_timing` in body
- `PUT /payroll/periods/{id}` — includes `deduction_timing` in body

---

## Migration Path

### Step 1: Run Migration and Seeder
```bash
php artisan migrate
php artisan db:seed --class=PayrollConfigurationSeeder
```

### Step 2: Verify Default Configuration
```bash
php artisan tinker
>>> PayrollConfiguration::get('deduction_timing')
```

Expected output:
```php
[
  "sss" => [
    "timing" => "monthly_only",
    "apply_on_period" => 2,
  ],
  "philhealth" => [
    "timing" => "monthly_only",
    "apply_on_period" => 2,
  ],
  // ...
]
```

### Step 3: Test with Real Payroll Period
1. Create two test periods (1st half: Mar 1-15, 2nd half: Mar 16-31)
2. Run calculation for one test employee on both periods
3. Verify 1st period has ₱0 government deductions
4. Verify 2nd period has full government deductions
5. Check `employee_payroll_calculations` table for both records

### Step 4: Deploy to Production
1. **Backup database** — changes modify core calculation logic
2. Run migration
3. Run seeder (preserves current behavior: monthly_only on 2nd cutoff)
4. Monitor first live payroll run
5. Verify deduction amounts match previous manual calculations

---

## Rollback Plan

If issues arise, the service degrades gracefully:

1. **Configuration table missing**: Service uses hardcoded fallback (current behavior)
2. **Configuration corrupted**: Service catches exceptions and falls back to 2nd-cutoff-only
3. **Wrong deduction amounts**: Update configuration via admin UI or direct DB update
4. **Full rollback**: Revert code changes, keep configuration table for future use

**Fallback code in service:**
```php
try {
    $deductionConfig = $this->getDeductionTimingConfig();
} catch (\Exception $e) {
    Log::warning('Failed to load deduction timing config, using fallback', [
        'error' => $e->getMessage()
    ]);
    $deductionConfig = [
        'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        // ... other defaults
    ];
}
```

---

## Implementation Progress

### Phase 1: ✅ COMPLETE (2026-03-10)
- Created `PayrollConfiguration` model with scopes and helper methods
- Created migration for `payroll_configurations` table with JSON config storage
- Migration successfully applied to database
- No errors, ready for Phase 2 (Seeder)

### Phase 2: ✅ COMPLETE (2026-03-10)
- Created `PayrollConfigurationSeeder` with default `monthly_only` on 2nd cutoff for all 5 deduction types

### Phase 3: ✅ COMPLETE (2026-03-10)
- 3a: Added `use App\Models\PayrollConfiguration;` import
- 3b: Added `getDeductionTimingConfig()`, `shouldApplyDeduction()`, `getDeductionMultiplier()` helper methods
- 3c: Replaced hardcoded government deduction logic (SSS, PhilHealth, Pag-IBIG, Withholding Tax)
- 3d: Replaced hardcoded loan deduction ternary (done during 3c)

### Phase 4: ✅ COMPLETE (2026-03-10)
- Created `tests/Unit/Services/Payroll/PayrollCalculationServiceDeductionTimingTest.php`
- 10 tests, 28 assertions, all passing
- Covers: monthly_only, per_cutoff, split_monthly, default fallback, mixed config, loan timing
- Fixed SQLite-incompatible `ALTER TABLE DROP CONSTRAINT` in versioning migration (now PostgreSQL-only)
- ✅ **Phase 5b COMPLETE (2026-03-10)** — Implemented officer-facing deduction timing UI with period-level overrides
  - Updated PayrollPeriodController store() and update() to accept deduction_timing overrides
  - Updated PayrollCalculationService::getDeductionTimingConfig() to accept optional PayrollPeriod parameter
  - Added period-level override handling (takes precedence over global defaults)
  - Created PeriodFormModal collapsible section with 5 deduction type selectors
  - Added conditional period selector (1st/2nd cutoff) for monthly_only timing
  - Created comprehensive period override test suite (4 new tests, all passing)

---

## File Checklist

### New Files
- [x] `database/migrations/2026_03_10_000000_create_payroll_configurations_table.php` ✅
- [x] `app/Models/PayrollConfiguration.php` ✅
- [x] `database/seeders/PayrollConfigurationSeeder.php` ✅
- [x] `tests/Unit/Services/Payroll/PayrollCalculationServiceDeductionTimingTest.php` ✅
- [x] `tests/Unit/Services/Payroll/PayrollCalculationServicePeriodOverridesTest.php` ✅ (Phase 5b)
- [ ] `app/Http/Controllers/Admin/PayrollConfigurationController.php` (Phase 5a)
- [ ] `resources/js/pages/Admin/PayrollConfiguration/DeductionTiming.tsx` (Phase 5a)

### Modified Files
- [x] `app/Services/Payroll/PayrollCalculationService.php` ✅
  - [x] Add `use App\Models\PayrollConfiguration;` ✅
  - [x] Add `getDeductionTimingConfig()` method ✅
  - [x] Add `shouldApplyDeduction()` method ✅
  - [x] Add `getDeductionMultiplier()` method ✅
  - [x] Replace hardcoded deduction logic ✅
  - [x] Replace hardcoded loan deduction logic ✅
  - [x] Update `getDeductionTimingConfig()` to accept `?PayrollPeriod $period` ✅ (Phase 5b)
  - [x] Pass `$period` into `getDeductionTimingConfig($period)` in `calculateEmployee()` ✅ (Phase 5b)
- [x] `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php` ✅ (Phase 5b — accept & store deduction_timing overrides)
- [x] `resources/js/pages/Payroll/PayrollProcessing/Periods/components/PeriodFormModal.tsx` ✅ (Phase 5b — add collapsible timing section)
- [x] `resources/js/types/payroll-pages.ts` ✅ (Phase 5b — added DeductionTimingOverride and DeductionTimingType type definitions)
- [ ] `routes/admin.php` (Phase 5a — add configuration routes)

### Documentation Files
- [ ] This file: `docs/implementations/PAYROLL_DEDUCTION_TIMING_CONFIGURATION.md`
- [ ] Update: `docs/PAYROLL_DATA_WIRING_MAP.md` (add configuration table)

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Wrong deduction amounts | Medium | High | Extensive unit tests, manual verification on test data |
| Double-charging employees | Low | Critical | Default config preserves current behavior (monthly_only) |
| Configuration corruption | Low | Medium | Fallback to hardcoded defaults in catch blocks |
| Performance degradation | Low | Low | Config cached per calculation run (not per employee) |
| Regulatory non-compliance | Low | Critical | Document Philippine labor law requirements in config descriptions |

---

## Success Criteria

- [x] Configuration can be changed without code deployment
- [x] Default behavior matches current production (monthly_only, 2nd cutoff)
- [x] Supports all three timing modes: per_cutoff, monthly_only, split_monthly
- [x] Test employee calculations match expected amounts for all modes
- [x] No regression in existing payroll calculations
- [x] Payroll officer can override timing per period at creation time (Phase 5b) ✅
- [ ] Admin can change global defaults via web UI (Phase 5a)
- [ ] Audit trail: WHO changed the configuration and WHEN
- [ ] Documentation updated with configuration examples

---

## Next Steps After Implementation

1. **Add employer contribution timing** — Currently only employee deductions are configurable
2. **Per-employee overrides** — Some employees may have special deduction schedules
3. **Remittance calendar integration** — Auto-adjust timing based on government remittance deadlines
4. **Analytics dashboard** — Show total deductions per period for budget forecasting
5. **Notification system** — Alert payroll staff when configuration changes
