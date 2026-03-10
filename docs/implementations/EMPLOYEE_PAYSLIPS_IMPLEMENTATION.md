# Employee Payslips — Backend/Frontend Integration

**Page:** `http://localhost:8000/employee/payslips`  
**Status:** Mock data only — needs full DB integration  
**Priority:** HIGH  
**Created:** 2026-03-10

---

## 1. Current State

### What exists

| Layer | File | State |
|---|---|---|
| Controller | `app/Http/Controllers/Employee/PayslipController.php` | All 4 public methods call private mock helpers |
| Model | `app/Models/Payslip.php` | Complete — relationships, scopes, helpers (`getEarningsBreakdown()`, `getDeductionsBreakdown()`, `getYtdSummary()`) |
| Migration | `database/migrations/2026_02_17_065600_create_payslips_table.php` | Table exists, columns correct |
| Routes | `routes/employee.php` lines 63–82 | 4 routes registered; ordering bug (see §3-F) |
| Frontend page | `resources/js/pages/Employee/Payslips/Index.tsx` | Fully structured — uses Inertia props |
| Employee model | `app/Models/Employee.php` | **Missing** `payslips()` HasMany relationship |
| Permissions | `database/seeders/EmployeeRoleSeeder.php` | `employee.payslips.view` and `employee.payslips.download` defined |

---

## 2. Data Shape Analysis

### TypeScript `PayslipRecord` interface (frontend contract)

```ts
interface SalaryComponent {
    name: string;
    amount: number;
}

interface PayslipRecord {
    id: number;
    pay_period_start: string;       // period_start formatted 'Y-m-d'
    pay_period_end: string;         // period_end formatted 'Y-m-d'
    pay_date: string;               // payment_date formatted 'Y-m-d'
    status: 'released' | 'pending' | 'processing' | 'failed';
    basic_salary: number;           // earnings_data['basic_monthly_salary'] or ['basic_salary']
    allowances: SalaryComponent[];  // earnings_data allowances array
    gross_pay: number;              // total_earnings column
    deductions: SalaryComponent[];  // deductions_data as named array
    net_pay: number;                // net_pay column
    year_to_date_gross: number;     // ytd_gross column
    year_to_date_net: number;       // ytd_net column
    pdf_url?: string;               // Storage::temporaryUrl(file_path), null if no file
}

interface PayslipsIndexProps {
    employee: EmployeeInfo;
    payslips: PayslipRecord[];
    availableYears: number[];
    filters: { year: number };
    annualSummary?: {
        year: number;
        total_gross: number;
        total_deductions: number;
        total_net: number;
        thirteenth_month_pay?: number;
        bonuses_received?: number;
        tax_withheld?: number;
    };
    error?: string;
}
```

### DB → Frontend field mapping

| Frontend field | DB column / source | Notes |
|---|---|---|
| `id` | `payslips.id` | |
| `pay_period_start` | `payslips.period_start` | Format as `'Y-m-d'` |
| `pay_period_end` | `payslips.period_end` | Format as `'Y-m-d'` |
| `pay_date` | `payslips.payment_date` | Format as `'Y-m-d'` |
| `status` | `payslips.status` | **Must map** (see status table below) |
| `basic_salary` | `earnings_data['basic_monthly_salary']` | Fall back to `earnings_data['basic_salary']` |
| `allowances` | `earnings_data['other_allowances']` or individual allowance keys | Build `SalaryComponent[]` |
| `gross_pay` | `payslips.total_earnings` | Decimal column |
| `deductions` | `deductions_data` object keys | Build `SalaryComponent[]` |
| `net_pay` | `payslips.net_pay` | Decimal column |
| `year_to_date_gross` | `payslips.ytd_gross` | Nullable decimal |
| `year_to_date_net` | `payslips.ytd_net` | Nullable decimal |
| `pdf_url` | `payslips.file_path` via `Storage::temporaryUrl()` | Null if `file_path` is empty |

### Status enum mapping (DB → Frontend)

```php
match ($payslip->status) {
    'draft'        => 'pending',
    'generated'    => 'processing',
    'distributed'  => 'released',
    'acknowledged' => 'released',
    default        => 'pending',
}
```

### `earnings_data` JSON keys (from `PayrollCalculationService`)

```php
// Keys written by PayrollCalculationService:
'basic_monthly_salary'   // → basic_salary frontend field
'daily_rate'
'hourly_rate'
'basic_pay'
'regular_overtime_pay'
'total_overtime_pay'
'other_allowances'       // → total allowances amount
'total_allowances'
'gross_pay'
// Allowances array may be under 'allowances' key

// For SalaryComponent[] allowances array, build from:
// - basic_monthly_salary → { name: 'Basic Salary', amount: X }
// - Each key from earnings_data where key contains 'allowance' → { name, amount }
```

### `deductions_data` JSON keys

```php
// Keys written by PayrollCalculationService:
'sss_contribution'
'philhealth_contribution'
'pagibig_contribution'
'withholding_tax'
'total_loan_deductions'
'tardiness_deduction'
'total_deductions'
// Build SalaryComponent[] excluding total keys
```

### `annualSummary` shape (from `annualSummary()` response)

```php
[
    'year'                 => $year,
    'total_gross'          => Payslip::where()->sum('total_earnings'),
    'total_deductions'     => Payslip::where()->sum('total_deductions'),
    'total_net'            => Payslip::where()->sum('net_pay'),
    'thirteenth_month_pay' => derived from is_thirteenth_month payslips,
    'bonuses_received'     => sum of bonus-type payslips,
    'tax_withheld'         => Payslip::where()->sum JSON extract of withholding_tax,
]
```

---

## 3. Issues to Resolve

| # | Issue | Severity | Fix |
|---|---|---|---|
| A | `Employee.php` missing `payslips()` HasMany relationship | BLOCKING | Add `public function payslips(): HasMany { return $this->hasMany(Payslip::class)->orderBy('period_start', 'desc'); }` |
| B | `PayslipController::index()` uses `getMockPayslips()` | BLOCKING | Replace with real Eloquent query on `Payslip` model filtered by `employee_id` and `year` |
| C | `PayslipController::show()` uses `getMockPayslipDetail()` | BLOCKING | Replace with `Payslip::where('employee_id', $employee->id)->findOrFail($id)` |
| D | `PayslipController::download()` uses `generateMockPayslipPDF()` returning fake PDF bytes | BLOCKING | Use `Storage::download(file_path)` for existing PDFs; fallback to dompdf for on-demand generation |
| E | `PayslipController::annualSummary()` uses `getMockAnnualSummary()` | BLOCKING | Aggregate real payslip data for the year |
| F | Route ordering bug: `annual-summary/{year}` is registered after `/{id}` — Laravel matches `'annual-summary'` as an ID | BUG | Move `annual-summary/{year}` registration BEFORE `/{id}` in `routes/employee.php` |
| G | Status enum mismatch: DB uses `draft/generated/distributed/acknowledged`, frontend expects `released/pending/processing/failed` | BREAKING | Add `mapPayslipStatus()` private helper in controller |
| H | `BIR 2316` download: frontend calls `GET /employee/payslips/bir-2316/download?year=YYYY` but route does not exist | MISSING | Add route + `downloadBIR2316()` controller method |
| I | `availableYears` is hardcoded as last 3 years in mock; should be years with actual payslip records | LOW | Query `YEAR(period_start)` distinct from real payslips |

---

## 4. Phased Implementation

### Phase 1 — Employee model + index() real data

**Goal:** `GET /employee/payslips` returns real DB records.

#### Task 1.1 — Add `payslips()` relationship to `Employee.php`

File: `app/Models/Employee.php`

Add after existing `payrollHistory()` relationship:

```php
/**
 * Get all payslips for this employee.
 */
public function payslips(): HasMany
{
    return $this->hasMany(Payslip::class)->orderBy('period_start', 'desc');
}
```

Also add at top of file (if not already imported):
```php
use App\Models\Payslip;
```

#### Task 1.2 — Add private helpers to `PayslipController`

File: `app/Http/Controllers/Employee/PayslipController.php`

Add three private helpers **before** the mock methods section:

**a) `mapPayslipStatus()`**
```php
private function mapPayslipStatus(string $dbStatus): string
{
    return match ($dbStatus) {
        'draft'        => 'pending',
        'generated'    => 'processing',
        'distributed'  => 'released',
        'acknowledged' => 'released',
        default        => 'pending',
    };
}
```

**b) `buildAllowancesArray()`**
```php
private function buildAllowancesArray(array $earningsData): array
{
    $allowances = [];

    if (!empty($earningsData['other_allowances']) && $earningsData['other_allowances'] > 0) {
        $allowances[] = ['name' => 'Other Allowances', 'amount' => (float) $earningsData['other_allowances']];
    }

    // If earnings_data has an 'allowances' sub-array, use that instead
    if (!empty($earningsData['allowances']) && is_array($earningsData['allowances'])) {
        return array_map(fn($a) => [
            'name'   => $a['name'] ?? 'Allowance',
            'amount' => (float) ($a['amount'] ?? 0),
        ], $earningsData['allowances']);
    }

    return $allowances;
}
```

**c) `buildDeductionsArray()`**
```php
private function buildDeductionsArray(array $deductionsData): array
{
    $labelMap = [
        'sss_contribution'       => 'SSS',
        'philhealth_contribution' => 'PhilHealth',
        'pagibig_contribution'   => 'Pag-IBIG',
        'withholding_tax'        => 'Withholding Tax',
        'total_loan_deductions'  => 'Loan Deductions',
        'tardiness_deduction'    => 'Tardiness',
    ];

    $deductions = [];
    foreach ($labelMap as $key => $label) {
        if (!empty($deductionsData[$key]) && $deductionsData[$key] > 0) {
            $deductions[] = ['name' => $label, 'amount' => (float) $deductionsData[$key]];
        }
    }

    return $deductions;
}
```

**d) `transformPayslip()`**
```php
private function transformPayslip(\App\Models\Payslip $payslip): array
{
    $earningsData   = $payslip->earnings_data ?? [];
    $deductionsData = $payslip->deductions_data ?? [];

    $pdfUrl = null;
    if (!empty($payslip->file_path) && \Illuminate\Support\Facades\Storage::exists($payslip->file_path)) {
        try {
            $pdfUrl = \Illuminate\Support\Facades\Storage::temporaryUrl(
                $payslip->file_path,
                now()->addMinutes(30)
            );
        } catch (\Exception $e) {
            // Local storage does not support temporary URLs; fall back to null
            $pdfUrl = \Illuminate\Support\Facades\Storage::url($payslip->file_path);
        }
    }

    return [
        'id'                 => $payslip->id,
        'pay_period_start'   => $payslip->period_start?->format('Y-m-d'),
        'pay_period_end'     => $payslip->period_end?->format('Y-m-d'),
        'pay_date'           => $payslip->payment_date?->format('Y-m-d'),
        'status'             => $this->mapPayslipStatus($payslip->status),
        'basic_salary'       => (float) ($earningsData['basic_monthly_salary'] ?? $earningsData['basic_salary'] ?? 0),
        'allowances'         => $this->buildAllowancesArray($earningsData),
        'gross_pay'          => (float) $payslip->total_earnings,
        'deductions'         => $this->buildDeductionsArray($deductionsData),
        'net_pay'            => (float) $payslip->net_pay,
        'year_to_date_gross' => (float) ($payslip->ytd_gross ?? 0),
        'year_to_date_net'   => (float) ($payslip->ytd_net ?? 0),
        'pdf_url'            => $pdfUrl,
    ];
}
```

#### Task 1.3 — Rewrite `index()` method

Replace the try block contents in `index()`:

```php
$year = (int) $request->input('year', now()->year);

// Available years from actual payslip records
$availableYears = \App\Models\Payslip::where('employee_id', $employee->id)
    ->selectRaw('YEAR(period_start) as year')
    ->distinct()
    ->orderByDesc('year')
    ->pluck('year')
    ->toArray();

if (empty($availableYears)) {
    $availableYears = [now()->year];
}

$payslips = \App\Models\Payslip::where('employee_id', $employee->id)
    ->whereYear('period_start', $year)
    ->orderBy('period_start', 'desc')
    ->get()
    ->map(fn($p) => $this->transformPayslip($p))
    ->toArray();

return Inertia::render('Employee/Payslips/Index', [
    'employee' => [
        'id'              => $employee->id,
        'employee_number' => $employee->employee_number,
        'full_name'       => $employee->profile->full_name ?? $user->name,
        'department'      => $employee->department->name ?? 'N/A',
        'position'        => $employee->position->title ?? 'N/A',
    ],
    'payslips'       => $payslips,
    'availableYears' => $availableYears,
    'filters'        => ['year' => $year],
]);
```

---

### Phase 2 — show() and download()

#### Task 2.1 — Rewrite `show()` method

```php
public function show(Request $request, int $id)
{
    $user     = $request->user();
    $employee = $user->employee;

    if (!$employee) {
        abort(403, 'No employee record found.');
    }

    $payslip = \App\Models\Payslip::where('employee_id', $employee->id)
        ->findOrFail($id);

    // Mark as viewed if distributed
    if ($payslip->status === 'distributed') {
        $payslip->markAsViewed();
    }

    return response()->json($this->transformPayslip($payslip));
}
```

#### Task 2.2 — Rewrite `download()` method

```php
public function download(Request $request, int $id)
{
    $user     = $request->user();
    $employee = $user->employee;

    if (!$employee) {
        abort(403, 'No employee record found.');
    }

    $payslip = \App\Models\Payslip::where('employee_id', $employee->id)
        ->findOrFail($id);

    // Serve stored PDF if it exists
    if (!empty($payslip->file_path) && \Illuminate\Support\Facades\Storage::exists($payslip->file_path)) {
        return \Illuminate\Support\Facades\Storage::download(
            $payslip->file_path,
            "payslip-{$payslip->period_start?->format('Y-m')}.pdf"
        );
    }

    // Fallback: Generate PDF on-demand via dompdf
    $data = $this->transformPayslip($payslip);
    $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadView('payslips.pdf', [
        'payslip'  => $data,
        'employee' => [
            'full_name'       => $employee->profile->full_name ?? $user->name,
            'employee_number' => $employee->employee_number,
            'department'      => $employee->department->name ?? 'N/A',
            'position'        => $employee->position->title ?? 'N/A',
        ],
    ]);

    return $pdf->download("payslip-{$payslip->period_start?->format('Y-m')}.pdf");
}
```

---

### Phase 3 — annualSummary(), downloadBIR2316(), and route fixes

#### Task 3.1 — Fix route ordering in `routes/employee.php`

Current (buggy) order:
```php
Route::get('/{id}', [..., 'show'])...           // line ~66
Route::get('/annual-summary/{year}', [...])...  // line ~72  ← shadowed!
```

Correct order — static paths BEFORE dynamic `{id}`:
```php
Route::get('/annual-summary/{year}', [..., 'annualSummary'])...
Route::get('/bir-2316/download', [..., 'downloadBIR2316'])...
Route::get('/{id}', [..., 'show'])...
Route::get('/{id}/download', [..., 'download'])...
```

#### Task 3.2 — Rewrite `annualSummary()` method

```php
public function annualSummary(Request $request, int $year)
{
    $user     = $request->user();
    $employee = $user->employee;

    if (!$employee) {
        abort(403, 'No employee record found.');
    }

    $payslips = \App\Models\Payslip::where('employee_id', $employee->id)
        ->whereYear('period_start', $year)
        ->whereIn('status', ['distributed', 'acknowledged'])
        ->get();

    if ($payslips->isEmpty()) {
        return response()->json(null);
    }

    $totalGross      = $payslips->sum('total_earnings');
    $totalDeductions = $payslips->sum('total_deductions');
    $totalNet        = $payslips->sum('net_pay');

    // Tax withheld: sum of withholding_tax from deductions_data JSON
    $taxWithheld = $payslips->sum(function ($p) {
        return (float) (($p->deductions_data ?? [])['withholding_tax'] ?? 0);
    });

    // 13th month: payslips with is_thirteenth_month flag
    $thirteenthMonthPay = $payslips->where('is_thirteenth_month', true)->sum('net_pay');

    return response()->json([
        'year'                 => $year,
        'total_gross'          => (float) $totalGross,
        'total_deductions'     => (float) $totalDeductions,
        'total_net'            => (float) $totalNet,
        'thirteenth_month_pay' => $thirteenthMonthPay > 0 ? (float) $thirteenthMonthPay : null,
        'bonuses_received'     => null, // TODO: derive from bonus payslip type if applicable
        'tax_withheld'         => (float) $taxWithheld,
    ]);
}
```

#### Task 3.3 — Add `downloadBIR2316()` method

```php
public function downloadBIR2316(Request $request)
{
    $user     = $request->user();
    $employee = $user->employee;

    if (!$employee) {
        abort(403, 'No employee record found.');
    }

    $year     = (int) $request->input('year', now()->year);
    $payslips = \App\Models\Payslip::where('employee_id', $employee->id)
        ->whereYear('period_start', $year)
        ->whereIn('status', ['distributed', 'acknowledged'])
        ->get();

    $totalGross  = $payslips->sum('total_earnings');
    $totalNet    = $payslips->sum('net_pay');
    $taxWithheld = $payslips->sum(function ($p) {
        return (float) (($p->deductions_data ?? [])['withholding_tax'] ?? 0);
    });

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payslips.bir-2316', [
        'employee'    => [
            'full_name'       => $employee->profile->full_name ?? $user->name,
            'employee_number' => $employee->employee_number,
            'department'      => $employee->department->name ?? 'N/A',
            'tin'             => $employee->profile->tin ?? 'N/A',
        ],
        'year'        => $year,
        'total_gross' => $totalGross,
        'total_net'   => $totalNet,
        'tax_withheld'=> $taxWithheld,
    ]);

    return $pdf->download("BIR-2316-{$year}.pdf");
}
```

#### Task 3.4 — Register BIR 2316 route in `routes/employee.php`

Add inside the payslips group, BEFORE `/{id}`:
```php
Route::get('/bir-2316/download', [\App\Http\Controllers\Employee\PayslipController::class, 'downloadBIR2316'])
    ->middleware('permission:employee.payslips.download')
    ->name('bir-2316');
```

---

### Phase 4 — Cleanup

#### Task 4.1 — Remove all mock private methods

Delete these four private methods from `PayslipController`:
- `getMockPayslips(int $employeeId, int $year): array`
- `getMockPayslipDetail(int $employeeId, int $payslipId): ?array`
- `getMockAnnualSummary(int $employeeId, int $year): array`
- `generateMockPayslipPDF(array $payslip): string`

---

## 5. New Files Needed

| File | Purpose |
|---|---|
| `resources/views/payslips/pdf.blade.php` | Blade template for on-demand payslip PDF (used by dompdf fallback in `download()`) |
| `resources/views/payslips/bir-2316.blade.php` | Blade template for BIR 2316 certificate PDF |

### Minimal `pdf.blade.php` structure
```html
<!DOCTYPE html>
<html>
<head><style>body{font-family:Arial;font-size:12px;}</style></head>
<body>
  <h2>Payslip — {{ $employee['full_name'] }}</h2>
  <p>Period: {{ $payslip['pay_period_start'] }} to {{ $payslip['pay_period_end'] }}</p>
  <p>Gross Pay: {{ number_format($payslip['gross_pay'], 2) }}</p>
  <p>Net Pay: {{ number_format($payslip['net_pay'], 2) }}</p>
</body>
</html>
```

---

## 6. Permission Checklist

| Permission | Route middleware | Seeder status |
|---|---|---|
| `employee.payslips.view` | `index`, `show`, `annualSummary` | ✅ Defined in `EmployeeRoleSeeder` |
| `employee.payslips.download` | `download`, `downloadBIR2316` | ✅ Defined in `EmployeeRoleSeeder` |

**If permissions are missing at runtime:**
```bash
php artisan db:seed --class=EmployeeRoleSeeder
```

---

## 7. Test Plan

### Unit tests
| Test | Expected |
|---|---|
| `mapPayslipStatus('draft')` → `'pending'` | ✅ |
| `mapPayslipStatus('distributed')` → `'released'` | ✅ |
| `transformPayslip()` returns correct field mapping | ✅ |
| `buildAllowancesArray(['other_allowances' => 500])` → 1 item | ✅ |
| `buildDeductionsArray(['sss_contribution' => 0])` → 0 items (zero values excluded) | ✅ |

### Integration tests (Pest / PHPUnit)
| Test | Expected |
|---|---|
| `GET /employee/payslips` with no payslips → empty array, no error | 200 |
| `GET /employee/payslips` returns only own employee's records | Self-only |
| `GET /employee/payslips/{id}` with another employee's payslip ID → 404 | 404 |
| `GET /employee/payslips/annual-summary/2025` resolves correctly (not matched as `/{id}`) | 200 |
| `GET /employee/payslips/bir-2316/download?year=2025` resolves correctly | 200 |
| `GET /employee/payslips/{id}/download` returns PDF response | 200 |

### Manual tests
- [ ] Log in as Employee user, visit `/employee/payslips` — should load with no error
- [ ] Verify year filter changes payslip list
- [ ] Click on a payslip — detail view opens
- [ ] Download button triggers PDF response
- [ ] Annual summary section shows correct totals
- [ ] BIR 2316 download generates PDF

---

## 8. Related Files

| File | Relation |
|---|---|
| `app/Models/Payslip.php` | Core model; helpers `getEarningsBreakdown()`, `markAsViewed()` available |
| `app/Models/Employee.php` | Needs `payslips()` relationship added |
| `app/Services/Payroll/PayrollCalculationService.php` | Defines `earnings_data` / `deductions_data` JSON key structure |
| `config/dompdf.php` | dompdf config; already configured |
| `app/Models/PayrollPeriod.php` | Referenced via `payslips.payroll_period_id` |
| `database/migrations/2026_02_17_065600_create_payslips_table.php` | Full schema reference |
| `database/seeders/EmployeeRoleSeeder.php` | Defines and assigns `employee.payslips.*` permissions |
| `resources/js/pages/Employee/Payslips/Index.tsx` | Frontend; `handleDownloadBIR2316()` calls `/employee/payslips/bir-2316/download` |

---

## 9. Progress

- [ ] Phase 1: `Employee::payslips()` + real `index()` + helpers
- [ ] Phase 2: Real `show()` and `download()`
- [ ] Phase 3: Real `annualSummary()` + `downloadBIR2316()` + route fixes
- [ ] Phase 4: Remove all mock methods
