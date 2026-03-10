# Payroll Mock Pages Wiring Implementation

> **Scope:** 12 payroll pages currently using frontend mock data or experiencing permission errors.
> **Created:** 2025-01-XX
> **Status:** Planning Complete — Ready for Implementation

---

## Executive Summary

This implementation plan addresses 12 payroll pages that are either returning 100% mock/hardcoded data from their controllers or failing with authorization errors. The work is organized into 8 phases progressing from quick permission fixes to full database wiring of government compliance pages.

---

## Page Diagnosis Matrix

| # | Route | Controller | State | Root Cause |
|---|---|---|---|---|
| 1 | `/payroll/loans` | `LoansController` | ✅ DB-wired | **Permission bug**: uses `$this->authorize('viewAny', Employee::class)` → `EmployeePolicy::viewAny()` checks `isHrManager()` or `hr.employees.view` — Payroll Officers have neither |
| 2 | `/payroll/allowances-deductions` | `AllowancesDeductionsController` | ✅ DB-wired | **Same permission bug** as loans |
| 3 | `/payroll/advances` | `AdvancesController` | ❌ 100% mock | Hardcoded array of fake advances (ADV001–ADV005), no model, no DB query |
| 4 | `/payroll/reports/register` | `PayrollRegisterController` | ❌ 100% mock | `getMockPeriods()`, `getMockDepartments()`, `getMockSalaryComponents()`, `getMockEmployeePayrollData()` |
| 5 | `/payroll/reports/government` | `PayrollGovernmentReportsController` | ❌ 100% mock | `getReportsSummary()`, `getSSSReports()`, `getBIRReports()`, etc. all hardcoded |
| 6 | `/payroll/reports/analytics` | `PayrollAnalyticsController` | ❌ 100% mock | `getMonthlyLaborCostTrends()`, `getDepartmentComparisons()`, all use `rand()` |
| 7 | `/payroll/reports/audit` | `PayrollAuditController` | ❌ 100% mock | Generates 50 fake audit logs + 100 fake change history records with `array_rand()` |
| 8 | `/payroll/government/bir` | `BIRController` | ❌ 100% mock | `getMockBIRReports()`, `getMockPayrollPeriods()`, `getMockBIRSummary()` |
| 9 | `/payroll/government/sss` | `SSSController` | ❌ 100% mock | `getMockSSSContributions()`, `getMockSSSPeriods()`, `getMockSSSR3Reports()` |
| 10 | `/payroll/government/philhealth` | `PhilHealthController` | ❌ 100% mock | `getMockPhilHealthContributions()`, `getMockPhilHealthRF1Reports()` |
| 11 | `/payroll/government/pagibig` | `PagIbigController` | ❌ 100% mock | `getMockPagIbigContributions()`, `getMockPagIbigMCRFReports()`, `getMockPagIbigLoanDeductions()` |
| 12 | `/payroll/government/remittances` | `GovernmentRemittancesController` | ❌ 100% mock | `getMockGovernmentRemittances()`, `getMockRemittanceSummary()`, `getMockCalendarEvents()` |

---

## Existing Infrastructure Audit

### Models That Exist ✅
| Model | Table | Status |
|---|---|---|
| `EmployeeLoan` | `employee_loans` | ✅ Has model + migration |
| `LoanDeduction` | `loan_deductions` | ✅ Has model + migration |
| `GovernmentContributionRate` | `government_contribution_rates` | ✅ Has model + migration |
| `PayrollPeriod` | `payroll_periods` | ✅ Has model + migration |
| `EmployeePayrollCalculation` | `employee_payroll_calculations` | ✅ Has model + migration |
| `SalaryComponent` | `salary_components` | ✅ Has model + migration |
| `EmployeeSalaryComponent` | `employee_salary_components` | ✅ Has model + migration |
| `EmployeeAllowance` | `employee_allowances` | ✅ Has model + migration |
| `EmployeeDeduction` | `employee_deductions` | ✅ Has model + migration |
| `EmployeePayrollInfo` | `employee_payroll_info` | ✅ Has model + migration |
| `PayrollAdjustment` | `payroll_adjustments` | ✅ Has model + migration |
| `PayrollApprovalHistory` | `payroll_approval_histories` | ✅ Has model + migration |
| `PayrollCalculationLog` | `payroll_calculation_logs` | ✅ Has model + migration |
| `PayrollConfiguration` | `payroll_configurations` | ✅ Has model + migration |
| `PayrollPayment` | `payroll_payments` | ✅ Has model + migration |
| `PayrollExecutionHistory` | `payroll_execution_histories` | ✅ Has model + migration |
| `PayrollException` | `payroll_exceptions` | ✅ Has model + migration |

### Migrations Exist But Models Missing ⚠️
| Table | Migration | Model Needed |
|---|---|---|
| `employee_government_contributions` | `2026_03_03_100002` | `EmployeeGovernmentContribution` |
| `government_remittances` | `2026_03_03_100003` | `GovernmentRemittance` |
| `government_reports` | `2026_03_03_100004` | `GovernmentReport` |

### Neither Model Nor Migration Exists ❌
| Concept | Notes |
|---|---|
| Cash Advance / Advance | No `CashAdvance` model or `cash_advances` table. **Recommendation:** Use `EmployeeLoan` with `loan_type = 'cash_advance'` since the Advances page shows the same fields (amount, status, dates, employee). The `EmployeeLoan` model already has `loan_type`, `principal_amount`, `status`, `loan_date`, and all needed fields. |

### Services That Exist ✅
| Service | Path |
|---|---|
| `PayrollCalculationService` | `app/Services/Payroll/PayrollCalculationService.php` |
| `EmployeePayrollInfoService` | `app/Services/Payroll/EmployeePayrollInfoService.php` |
| `SalaryComponentService` | `app/Services/Payroll/SalaryComponentService.php` |

### Middleware Configuration
- **Route middleware:** `['auth', 'verified', EnsurePayrollOfficer::class]` — all payroll routes
- **EnsurePayrollOfficer:** Checks for `Payroll Officer` or `Superadmin` role — working correctly
- **No per-route `permission:` middleware** on any of the 12 affected routes (only dashboard has it)

### Permission Issue Root Cause
```
LoansController::index() → $this->authorize('viewAny', Employee::class)
                          → EmployeePolicy::viewAny()
                          → return $this->isHrManager($user) || $user->can('hr.employees.view')
                          → Payroll Officer has NEITHER → 403 Unauthorized
```
**Same issue** in `AllowancesDeductionsController::index()`.

---

## Phase 1: Fix Permission Issues (Loans & Allowances/Deductions)

**Pages:** `/payroll/loans`, `/payroll/allowances-deductions`
**Effort:** Low — controllers are already DB-wired, just need authorization fix

### Task 1.1: Add Payroll-Specific Permissions to Seeder/Config

**✅ COMPLETED**

Added these permissions to the permissions seeder:
- ✅ `payroll.loans.view`
- ✅ `payroll.loans.create`
- ✅ `payroll.loans.update`
- ✅ `payroll.loans.delete`
- ✅ `payroll.allowances-deductions.view`
- ✅ `payroll.allowances-deductions.manage`

All permissions have been assigned to the `Payroll Officer` role.

**Files modified:**
- ✅ `database/seeders/PayrollPermissionsSeeder.php` — Added 6 new permissions and ran seeder successfully

### Task 1.2: Replace Authorization in LoansController

**File:** `app/Http/Controllers/Payroll/LoansController.php`

Replace:
```php
$this->authorize('viewAny', Employee::class);
```

With:
```php
if (!auth()->user()->can('payroll.loans.view') && !auth()->user()->hasRole(['Payroll Officer', 'Superadmin'])) {
    abort(403);
}
```

Or better — use middleware on the routes:
```php
// In routes/payroll.php
Route::get('/loans', [LoansController::class, 'index'])
    ->middleware('permission:payroll.loans.view');
```

### Task 1.3: Replace Authorization in AllowancesDeductionsController

**✅ COMPLETED**

**File:** `app/Http/Controllers/Payroll/EmployeePayroll/AllowancesDeductionsController.php`

Removed all `$this->authorize()` calls and moved authorization to route middleware:
- ✅ Removed `$this->authorize('viewAny', Employee::class)` from `index()`
- ✅ Removed `$this->authorize('create', Employee::class)` from `bulkAssignPage()`
- ✅ Removed `$this->authorize('create', Employee::class)` from `store()`
- ✅ Removed `$this->authorize('update', Employee::class)` from `update()`
- ✅ Removed `$this->authorize('delete', Employee::class)` from `destroy()`
- ✅ Removed `$this->authorize('viewAny', Employee::class)` from `history()`
- ✅ Removed `$this->authorize('create', Employee::class)` from `bulkAssign()`

**Routes Updated:** All Allowances & Deductions routes now use permission middleware:
- ✅ `GET /allowances-deductions` → `middleware('permission:payroll.allowances-deductions.view')`
- ✅ `POST /allowances-deductions` → `middleware('permission:payroll.allowances-deductions.manage')`
- ✅ `GET /allowances-deductions/bulk-assign` → `middleware('permission:payroll.allowances-deductions.manage')`
- ✅ `POST /allowances-deductions/bulk-assign` → `middleware('permission:payroll.allowances-deductions.manage')`
- ✅ `GET /allowances-deductions/{id}` → `middleware('permission:payroll.allowances-deductions.view')`
- ✅ `PUT /allowances-deductions/{id}` → `middleware('permission:payroll.allowances-deductions.manage')`
- ✅ `DELETE /allowances-deductions/{id}` → `middleware('permission:payroll.allowances-deductions.manage')`
- ✅ `GET /allowances-deductions/{employeeId}/history` → `middleware('permission:payroll.allowances-deductions.view')`

**Permissions Verified:**
- ✅ `payroll.allowances-deductions.view` assigned to Payroll Officer role
- ✅ `payroll.allowances-deductions.manage` assigned to Payroll Officer role

### Task 1.4: Verify Frontend Receives Data Correctly

**✅ COMPLETED**

**Verification performed:**

1. **User `full_name` Accessor** ✅
   - Confirmed `User::getFullNameAttribute()` works correctly
   - Returns profile first/last name or falls back to user.name or username
   - All three users tested return expected full names

2. **LoansController Data Structure** ✅
   - Verified proper array mapping with correct field names
   - Confirmed employee name resolution via `$loan->employee->user->full_name`
   - Department relationships load correctly: `$loan->employee->department->name`
   - All required fields present: employee_name, employee_number, department_name, loan_type, principal_amount, status, etc.
   - Data structure matches frontend `EmployeeLoan` interface expectations

3. **AllowancesDeductionsController Data Structure** ✅
   - Verified employee mapping with all required fields
   - Confirmed relationships loaded: allowances, deductions, department, position
   - Employee first_name and last_name properly returned
   - Component data (allowances/deductions) correctly mapped with salary_component relationships
   - Summary data (total_allowances, total_deductions) calculated correctly

4. **Relationships Properly Loaded** ✅
   - Employee → User → Profile relationship chain works
   - Department relationships fully loaded
   - Salary component relationships resolved for allowances/deductions
   - All eager-loaded relations prevent N+1 queries

5. **Filter Dropdowns Population** ✅
   - Employees dropdown: 11 active employees with proper name resolution
   - Departments dropdown: 10 departments available
   - Loan types: 3 types (SSS, Pag-IBIG, Company)
   - Loan statuses: 4 statuses (Active, Completed, Cancelled, Restructured)

6. **Permission Middleware & Authorization** ✅
   - All 4 loan permission middleware entries configured properly
   - All 2 allowances-deductions permission middleware entries configured
   - No `$this->authorize()` calls remain in either controller
   - Authorization enforced at route level, not controller level

**Frontend Data Flow Verified:**
- ✅ Both `/payroll/loans` and `/payroll/allowances-deductions` controllers return properly structured data
- ✅ All required relationships are eager-loaded to prevent N+1 queries
- ✅ Data shape matches frontend component interface expectations
- ✅ Permission middleware ensures only authorized users access the data
- ✅ No authorization errors (403) will be thrown for Payroll Officers with correct permissions

### Acceptance Criteria

**Task 1.1 (Permissions Setup):** ✅ COMPLETE
- [x] Permissions created: `payroll.loans.view`, `payroll.loans.create`, `payroll.loans.update`, `payroll.loans.delete`, `payroll.allowances-deductions.view`, `payroll.allowances-deductions.manage`
- [x] All permissions assigned to Payroll Officer role
- [x] Permission seeder executed successfully

**Task 1.2 (LoansController Fix):** ✅ COMPLETE
- [x] All `$this->authorize()` calls removed from LoansController
- [x] All Loans routes updated with `middleware('permission:payroll.loans.*')`
- [x] Payroll Officer has required loan permissions
- [x] LoansController data structure verified and returns properly formatted data

**Task 1.3 (AllowancesDeductionsController Fix):** ✅ COMPLETE
- [x] All `$this->authorize()` calls removed from AllowancesDeductionsController (7 total)
- [x] All Allowances & Deductions routes updated with `middleware('permission:payroll.allowances-deductions.*')`
- [x] Payroll Officer has required allowances-deductions permissions
- [x] AllowancesDeductionsController data structure verified and returns properly formatted data

**Task 1.4 (Frontend Data Verification):** ✅ COMPLETE
- [x] User `full_name` accessor verified and working correctly
- [x] LoansController returns properly structured data with all required fields
- [x] AllowancesDeductionsController returns properly structured data with all required fields
- [x] All relationships (Employee → User → Profile, Department) properly eager-loaded
- [x] Filter dropdowns populate correctly with all required data
- [x] Frontend pages receive data without 403 permission errors
- [x] Payroll Officer can access both endpoints with proper authorization
- [x] Data matches frontend TypeScript interface expectations

---

## Phase 2: Create Missing Eloquent Models

**Prerequisite:** Migrations already exist. Just need models.

### Task 2.1: Create EmployeeGovernmentContribution Model

**File:** `app/Models/EmployeeGovernmentContribution.php`

Based on migration `2026_03_03_100002_create_employee_government_contributions_table.php`:

```php
class EmployeeGovernmentContribution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id', 'payroll_period_id', 'employee_payroll_calculation_id',
        'period_start', 'period_end', 'period_month',
        'basic_salary', 'gross_compensation', 'taxable_income',
        // SSS
        'sss_number', 'sss_bracket', 'sss_monthly_salary_credit',
        'sss_employee_contribution', 'sss_employer_contribution', 'sss_ec_contribution', 'sss_total_contribution',
        'is_sss_exempted',
        // PhilHealth
        'philhealth_number', 'philhealth_premium_base',
        'philhealth_employee_contribution', 'philhealth_employer_contribution', 'philhealth_total_contribution',
        'is_philhealth_exempted',
        // PagIBIG
        'pagibig_number', 'pagibig_compensation_base',
        'pagibig_employee_contribution', 'pagibig_employer_contribution', 'pagibig_total_contribution',
        'is_pagibig_exempted',
        // BIR
        'tin', 'tax_status', 'annualized_taxable_income', 'tax_due', 'withholding_tax',
        'tax_already_withheld_ytd', 'is_minimum_wage_earner', 'is_substituted_filing',
        // De minimis
        'deminimis_benefits', 'thirteenth_month_pay', 'other_tax_exempt_compensation',
        // Totals
        'total_employee_contributions', 'total_employer_contributions', 'total_statutory_deductions',
        // Status
        'status', 'calculated_at', 'calculated_by', 'processed_at', 'processed_by',
    ];
```

**Relationships:**
- `belongsTo(Employee::class)`
- `belongsTo(PayrollPeriod::class)`
- `belongsTo(EmployeePayrollCalculation::class)`
- `belongsTo(User::class, 'calculated_by')`
- `belongsTo(User::class, 'processed_by')`

### Task 2.2: Create GovernmentRemittance Model

**File:** `app/Models/GovernmentRemittance.php`

Based on migration `2026_03_03_100003_create_government_remittances_table.php`:

**Relationships:**
- `belongsTo(PayrollPeriod::class)`
- `hasMany(GovernmentReport::class)`
- `belongsTo(User::class, 'prepared_by')`
- `belongsTo(User::class, 'submitted_by')`
- `belongsTo(User::class, 'paid_by')`

**Scopes:**
- `scopeByAgency($query, $agency)`
- `scopePending($query)`
- `scopeOverdue($query)`

### Task 2.3: Create GovernmentReport Model

**File:** `app/Models/GovernmentReport.php`

Based on migration `2026_03_03_100004_create_government_reports_table.php`:

**Relationships:**
- `belongsTo(PayrollPeriod::class)`
- `belongsTo(GovernmentRemittance::class)`
- `belongsTo(User::class, 'generated_by')`
- `belongsTo(User::class, 'submitted_by')`

**Scopes:**
- `scopeByAgency($query, $agency)`
- `scopeByReportType($query, $type)`
- `scopeDraft($query)` / `scopeSubmitted($query)`

### Acceptance Criteria
- [x] `EmployeeGovernmentContribution` model created with all fillable, casts, and relationships ✅ 2026-03-10
- [ ] `GovernmentRemittance` model created with all fillable, casts, and relationships
- [ ] `GovernmentReport` model created with all fillable, casts, and relationships
- [ ] All 3 models can be instantiated without errors
- [ ] Tables exist in database (run `php artisan migrate` if needed)

---

## Phase 3: Wire Advances Page

**Page:** `/payroll/advances`
**Current state:** AdvancesController returns hardcoded array with string IDs (ADV001–ADV005)
**Approach:** Use `EmployeeLoan` model with `loan_type = 'cash_advance'` since no CashAdvance model exists and the data shape matches.

### Task 3.1: Wire AdvancesController::index()

**File:** `app/Http/Controllers/Payroll/AdvancesController.php`

Replace all mock data methods with real queries:

```php
public function index(Request $request)
{
    $query = EmployeeLoan::with(['employee.user', 'employee.department', 'createdBy'])
        ->where('loan_type', 'cash_advance');

    // Apply filters
    if ($request->filled('search')) {
        $search = $request->search;
        $query->whereHas('employee.user', function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");
        });
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('department_id')) {
        $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->department_id));
    }

    $advances = $query->latest()->paginate(15);

    // Map to frontend shape
    $advances->getCollection()->transform(function ($advance) {
        return [
            'id' => $advance->id,
            'employee_id' => $advance->employee_id,
            'employee_name' => $advance->employee?->user?->full_name ?? 'N/A',
            'department' => $advance->employee?->department?->name ?? 'N/A',
            'amount' => (float) $advance->principal_amount,
            'remaining_balance' => (float) $advance->remaining_balance,
            'status' => $advance->status,
            'loan_date' => $advance->loan_date?->format('Y-m-d'),
            'notes' => $advance->notes,
            'created_at' => $advance->created_at?->format('Y-m-d H:i:s'),
        ];
    });

    $employees = Employee::with('user')
        ->whereHas('user')
        ->get()
        ->map(fn ($e) => ['id' => $e->id, 'name' => $e->user->full_name]);

    $departments = Department::select('id', 'name')->get();

    return Inertia::render('Payroll/Advances/Index', [
        'advances' => $advances,
        'employees' => $employees,
        'departments' => $departments,
        'filters' => $request->only(['search', 'status', 'department_id']),
        'statuses' => ['pending', 'approved', 'active', 'completed', 'cancelled'],
    ]);
}
```

### Task 3.2: Wire AdvancesController::store()

Wire the `store()` method to create an `EmployeeLoan` with `loan_type = 'cash_advance'`.

### Task 3.3: Wire AdvancesController::approve() and reject()

Wire approve/reject methods to update `EmployeeLoan` status.

### Task 3.4: Update Frontend to Accept Paginated Data

**File:** `resources/js/pages/Payroll/Advances/Index.tsx`

Update the frontend component to:
- Accept paginated data shape instead of flat array
- Use integer IDs instead of string IDs (ADV001)
- Connect filters to backend query params via Inertia router

### Acceptance Criteria
- [ ] Advances page loads from `employee_loans` table where `loan_type = 'cash_advance'`
- [ ] Search, filter by status, filter by department work
- [ ] Create new advance creates an `EmployeeLoan` record
- [ ] Approve/reject actions update record status
- [ ] Pagination works
- [ ] Empty state displays correctly when no advances exist

---

## Phase 4: Wire Payroll Register Report

**Page:** `/payroll/reports/register`
**Current state:** `PayrollRegisterController` uses `getMockPeriods()`, `getMockDepartments()`, `getMockSalaryComponents()`, `getMockEmployeePayrollData()` — all hardcoded.

### Task 4.1: Wire PayrollRegisterController::index()

**File:** `app/Http/Controllers/Payroll/Reports/PayrollRegisterController.php`

Replace mock methods with real queries:

```php
public function index(Request $request)
{
    $periodId = $request->query('period_id');

    // Get periods from DB
    $periods = PayrollPeriod::orderByDesc('start_date')
        ->limit(12)
        ->get()
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'start_date' => $p->start_date->format('Y-m-d'),
            'end_date' => $p->end_date->format('Y-m-d'),
            'status' => $p->status,
        ]);

    // Get currently selected period
    $selectedPeriod = $periodId
        ? PayrollPeriod::find($periodId)
        : PayrollPeriod::orderByDesc('start_date')->first();

    // Departments from DB
    $departments = Department::select('id', 'name')->get();

    // Salary components from DB
    $salaryComponents = SalaryComponent::where('is_active', true)
        ->select('id', 'name', 'code', 'type')
        ->get();

    // Employee payroll data for selected period
    $employeePayrollData = [];
    if ($selectedPeriod) {
        $calculations = EmployeePayrollCalculation::with(['employee.user', 'employee.department'])
            ->where('payroll_period_id', $selectedPeriod->id)
            ->get();

        $employeePayrollData = $calculations->map(function ($calc) {
            return [
                'employee_id' => $calc->employee_id,
                'employee_name' => $calc->employee?->user?->full_name ?? 'N/A',
                'department' => $calc->employee?->department?->name ?? 'N/A',
                'basic_salary' => (float) $calc->basic_salary,
                'gross_pay' => (float) $calc->gross_pay,
                'total_deductions' => (float) $calc->total_deductions,
                'net_pay' => (float) $calc->net_pay,
                // ... map all salary component amounts
            ];
        });
    }

    // Build summary
    $summary = [
        'total_employees' => $employeePayrollData->count(),
        'total_gross_pay' => $employeePayrollData->sum('gross_pay'),
        'total_deductions' => $employeePayrollData->sum('total_deductions'),
        'total_net_pay' => $employeePayrollData->sum('net_pay'),
    ];

    return Inertia::render('Payroll/Reports/Register/Index', [
        'register_data' => $employeePayrollData,
        'summary' => $summary,
        'periods' => $periods,
        'departments' => $departments,
        'salary_components' => $salaryComponents,
        'selected_period' => $selectedPeriod,
        'filters' => $request->only(['period_id', 'department_id', 'status']),
    ]);
}
```

### Task 4.2: Remove All getMock*() Methods

Delete `getMockPeriods()`, `getMockDepartments()`, `getMockSalaryComponents()`, `getMockEmployeePayrollData()` from the controller.

### Task 4.3: Update Frontend for Real Data Shape

**File:** `resources/js/pages/Payroll/Reports/Register/Index.tsx`

- Ensure data shape matches the new backend response
- Wire period selector to reload via Inertia with `period_id` query param
- Department filter via Inertia query param

### Acceptance Criteria
- [ ] Register page loads periods from `payroll_periods` table
- [ ] Employee payroll data comes from `employee_payroll_calculations` table
- [ ] Department filter works against real data
- [ ] Summary totals compute from real calculations
- [ ] Salary components display from `salary_components` table
- [ ] Period selector triggers data reload

---

## Phase 5: Wire Government Agency Pages (BIR, SSS, PhilHealth, Pag-IBIG)

**Pages:** 4 individual government agency pages
**Prerequisite:** Phase 2 (models created)

### Task 5.1: Create GovernmentContributionService

**File:** `app/Services/Payroll/GovernmentContributionService.php`

Shared service used by all 4 agency controllers. Key methods:

```php
class GovernmentContributionService
{
    // Get contributions for a specific agency and period
    public function getContributions(string $agency, ?int $periodId = null): Collection

    // Get summary stats for an agency
    public function getSummary(string $agency, int $periodId): array

    // Get remittance history for an agency
    public function getRemittances(string $agency, int $limit = 6): Collection

    // Get reports for an agency
    public function getReports(string $agency, ?int $periodId = null): Collection

    // Generate a report file (R3, RF1, MCRF, 1601C, etc.)
    public function generateReport(string $agency, string $reportType, int $periodId): GovernmentReport

    // Get available periods
    public function getPeriods(): Collection
}
```

### Task 5.2: Wire BIRController to Real Data

**File:** `app/Http/Controllers/Payroll/Government/BIRController.php`

Replace all `getMock*()` methods:
- `index()`: Query `EmployeeGovernmentContribution` for BIR fields (withholding tax, TIN, etc.) + `GovernmentReport` where `agency = 'bir'`
- `generate1601C()`: Generate real 1601C from employee contribution data
- `generate2316()`: Generate real 2316 certificates from annual data
- `generateAlphalist()`: Generate real Alphalist in DAT format
- Remove `getMockBIRReports()`, `getMockPayrollPeriods()`, `getMockBIRSummary()`, `getMockGeneratedReports()`

**Data sources:**
- `employee_government_contributions` → `tin`, `tax_status`, `withholding_tax`, `taxable_income`, `annualized_taxable_income`
- `government_reports` WHERE `agency = 'bir'`
- `government_remittances` WHERE `agency = 'bir'`

### Task 5.3: Wire SSSController to Real Data

**File:** `app/Http/Controllers/Payroll/Government/SSSController.php`

Replace all `getMock*()` methods:
- `index()`: Query `EmployeeGovernmentContribution` for SSS fields + `GovernmentReport` where `agency = 'sss'`
- `generateR3()`: Generate real R3 report from contribution data
- Remove `getMockSSSContributions()`, `getMockSSSPeriods()`, `getMockSSSSummary()`, `getMockSSSRemittances()`, `getMockSSSR3Reports()`

**Data sources:**
- `employee_government_contributions` → `sss_number`, `sss_bracket`, `sss_employee_contribution`, `sss_employer_contribution`, `sss_ec_contribution`, `sss_total_contribution`
- `government_contribution_rates` WHERE `agency = 'sss'` → for bracket lookups
- `government_reports` WHERE `agency = 'sss'` AND `report_type = 'r3'`

### Task 5.4: Wire PhilHealthController to Real Data

**File:** `app/Http/Controllers/Payroll/Government/PhilHealthController.php`

Replace all `getMock*()` methods:
- `index()`: Query `EmployeeGovernmentContribution` for PhilHealth fields + `GovernmentReport` where `agency = 'philhealth'`
- `generateRF1()`: Generate real RF1 report
- Remove `getMockPhilHealthContributions()`, `getMockPhilHealthPeriods()`, `getMockPhilHealthSummary()`, `getMockPhilHealthRemittances()`, `getMockPhilHealthRF1Reports()`

**Data sources:**
- `employee_government_contributions` → `philhealth_number`, `philhealth_employee_contribution`, `philhealth_employer_contribution`, `philhealth_total_contribution`
- `government_contribution_rates` WHERE `agency = 'philhealth'`
- `government_reports` WHERE `agency = 'philhealth'` AND `report_type = 'rf1'`

### Task 5.5: Wire PagIbigController to Real Data

**File:** `app/Http/Controllers/Payroll/Government/PagIbigController.php`

Replace all `getMock*()` methods:
- `index()`: Query `EmployeeGovernmentContribution` for Pag-IBIG fields + `GovernmentReport` where `agency = 'pagibig'`
- `generateMCRF()`: Generate real MCRF report
- Remove `getMockPagIbigContributions()`, `getMockPagIbigPeriods()`, `getMockPagIbigSummary()`, `getMockPagIbigRemittances()`, `getMockPagIbigMCRFReports()`, `getMockPagIbigLoanDeductions()`

**Data sources:**
- `employee_government_contributions` → `pagibig_number`, `pagibig_employee_contribution`, `pagibig_employer_contribution`, `pagibig_total_contribution`
- `government_contribution_rates` WHERE `agency = 'pagibig'`
- `government_reports` WHERE `agency = 'pagibig'` AND `report_type = 'mcrf'`
- `employee_loans` WHERE `loan_type` IN Pag-IBIG loan types for loan deductions

### Task 5.6: Update All 4 Frontend Pages

Update each frontend page to ensure the data shape from the wired controller matches what the component expects. Key changes:
- Integer IDs instead of hardcoded sequential IDs
- Nullable fields for periods with no data yet
- Paginated contribution lists if employee count is large
- Summary computed from real totals

**Files:**
- `resources/js/pages/Payroll/Government/BIR/Index.tsx`
- `resources/js/pages/Payroll/Government/SSS/Index.tsx`
- `resources/js/pages/Payroll/Government/PhilHealth/Index.tsx`
- `resources/js/pages/Payroll/Government/PagIbig/Index.tsx`

### Acceptance Criteria
- [ ] `GovernmentContributionService` created and shared across all 4 controllers
- [ ] BIR page queries `employee_government_contributions` + `government_reports`
- [ ] SSS page shows real contributions per employee with proper bracket lookup
- [ ] PhilHealth page shows real premiums (5% rate, 2.5% EE + 2.5% ER)
- [ ] Pag-IBIG page shows real contributions + loan deductions from `employee_loans`
- [ ] Report generation creates records in `government_reports` table
- [ ] Report download serves real file content
- [ ] All 4 frontend pages display real data without errors
- [ ] Empty state when no contributions exist for a period

---

## Phase 6: Wire Government Reports & Remittances Summary Pages

**Pages:** `/payroll/reports/government`, `/payroll/government/remittances`
**Prerequisite:** Phase 2 (models) + Phase 5 (agency controllers wired)

### Task 6.1: Wire PayrollGovernmentReportsController

**File:** `app/Http/Controllers/Payroll/Reports/PayrollGovernmentReportsController.php`

Replace all hardcoded methods with queries to `government_reports` and `government_remittances`:

```php
public function index(Request $request)
{
    // Summary from government_reports table
    $reportsSummary = [
        'total_reports_generated' => GovernmentReport::count(),
        'total_reports_submitted' => GovernmentReport::where('status', 'submitted')->count(),
        'reports_pending_submission' => GovernmentReport::whereIn('status', ['draft', 'ready'])->count(),
        'total_contributions' => GovernmentRemittance::sum('total_amount'),
        'next_deadline' => GovernmentRemittance::where('status', 'pending')->orderBy('due_date')->value('due_date'),
        'overdue_reports' => GovernmentRemittance::where('status', 'overdue')->count(),
    ];

    // Reports grouped by agency
    $sssReports = GovernmentReport::byAgency('sss')->with('payrollPeriod')->latest()->limit(3)->get();
    $philhealthReports = GovernmentReport::byAgency('philhealth')->with('payrollPeriod')->latest()->limit(3)->get();
    $pagibigReports = GovernmentReport::byAgency('pagibig')->with('payrollPeriod')->latest()->limit(3)->get();
    $birReports = GovernmentReport::byAgency('bir')->with('payrollPeriod')->latest()->limit(3)->get();

    // Upcoming deadlines from remittances
    $upcomingDeadlines = GovernmentRemittance::where('due_date', '>=', now())
        ->orderBy('due_date')
        ->limit(5)
        ->get();

    // Compliance status per agency
    $complianceStatus = [...]; // Computed from remittance status per agency

    return Inertia::render('Payroll/Reports/Government/Index', [...]);
}
```

Remove all `getReportsSummary()`, `getSSSReports()`, `getPhilHealthReports()`, `getPagIbigReports()`, `getBIRReports()`, `getUpcomingDeadlines()`, `getComplianceStatus()` mock methods.

### Task 6.2: Wire GovernmentRemittancesController

**File:** `app/Http/Controllers/Payroll/Government/GovernmentRemittancesController.php`

Replace all mock methods:
- `index()`: Query `government_remittances` table grouped by period, with real due dates and payment status
- `recordPayment()`: Actually update `GovernmentRemittance` record with payment info
- `sendReminder()`: Create notification/email for pending remittances

Remove `getMockRemittancePeriods()`, `getMockGovernmentRemittances()`, `getMockRemittanceSummary()`, `getMockCalendarEvents()`.

### Task 6.3: Update Frontend Pages

**Files:**
- `resources/js/pages/Payroll/Reports/Government/Index.tsx`
- `resources/js/pages/Payroll/Government/Remittances/Index.tsx`

### Acceptance Criteria
- [ ] Government Reports page aggregates real data from `government_reports` table
- [ ] Reports per agency (SSS, PhilHealth, PagIBIG, BIR) come from DB
- [ ] Upcoming deadlines computed from `government_remittances.due_date`
- [ ] Remittances page lists all remittance records from DB
- [ ] Record payment action persists to DB
- [ ] Calendar events derived from real due dates
- [ ] Compliance status computed from real submission statuses

---

## Phase 7: Wire Analytics Report

**Page:** `/payroll/reports/analytics`
**Current state:** All analytics computed with `rand()` — no real data at all.

### Task 7.1: Wire PayrollAnalyticsController::index()

**File:** `app/Http/Controllers/Payroll/Reports/PayrollAnalyticsController.php`

Replace all mock methods with real aggregation queries:

- `getMonthlyLaborCostTrends()`: Query `EmployeePayrollCalculation` grouped by `PayrollPeriod` month, SUM gross_pay, basic_salary, allowances, overtime, etc.
- `getDepartmentComparisons()`: Query calculations grouped by department
- `getComponentBreakdown()`: Query `SalaryComponent` amounts from calculations
- `getYearOverYearComparisons()`: Compare current vs previous year same months
- `getEmployeeCostAnalysis()`: Per-employee cost breakdowns
- `getBudgetVarianceData()`: Compare actuals vs budgets (if budget data exists)
- `getForecastProjections()`: Simple linear projection based on historical data

Remove all private `getMonthlyLaborCostTrends()`, `getDepartmentComparisons()`, `getComponentBreakdown()`, `getYearOverYearComparisons()`, `getEmployeeCostAnalysis()`, `getBudgetVarianceData()`, `getForecastProjections()`, `getAnalyticsSummary()` methods.

Replace `available_periods` hardcoded array with:
```php
$availablePeriods = PayrollPeriod::orderByDesc('start_date')
    ->limit(12)
    ->get()
    ->map(fn ($p) => $p->start_date->format('F Y'));
```

Replace `available_departments` hardcoded array with:
```php
$availableDepartments = Department::select('id', 'name')->get();
```

### Task 7.2: Update Frontend

**File:** `resources/js/pages/Payroll/Reports/Analytics.tsx`

- Ensure data shape matches
- Handle empty data gracefully (no payroll calculations yet = empty charts)

### Acceptance Criteria
- [ ] Labor cost trends computed from `employee_payroll_calculations`
- [ ] Department comparisons use real department data
- [ ] Component breakdown comes from real salary component amounts
- [ ] Period selector comes from `payroll_periods` table
- [ ] Department selector comes from `departments` table
- [ ] Charts handle empty data without errors

---

## Phase 8: Wire Audit Report

**Page:** `/payroll/reports/audit`
**Current state:** Generates 50 fake audit logs and 100 fake change history records.

### Task 8.1: Wire PayrollAuditController::index()

**File:** `app/Http/Controllers/Payroll/Reports/PayrollAuditController.php`

Replace mock methods with real data from:
- **Option A:** Laravel `activity_log` table (if using `spatie/laravel-activitylog`) — filter for payroll-related models
- **Option B:** `PayrollCalculationLog` model — already exists for payroll calculation events
- **Option C:** `PayrollApprovalHistory` model — for approval/rejection events

```php
public function index(Request $request)
{
    // Audit logs from activity_log filtered to payroll models
    $auditLogs = Activity::whereIn('subject_type', [
        PayrollPeriod::class,
        EmployeePayrollCalculation::class,
        PayrollAdjustment::class,
        SalaryComponent::class,
        EmployeePayrollInfo::class,
    ])
    ->with('causer')
    ->latest()
    ->paginate(50);

    // Change history from payroll_calculation_logs
    $changeHistory = PayrollCalculationLog::with(['payrollPeriod', 'user'])
        ->latest()
        ->paginate(100);

    return Inertia::render('Payroll/Reports/Audit', [
        'auditLogs' => $auditLogs,
        'changeHistory' => $changeHistory,
        'filters' => $request->only(['action', 'entity_type', 'user_id', 'date_range', 'search']),
    ]);
}
```

### Task 8.2: Add Filters

Support filtering by:
- `action` (created, updated, deleted, approved, rejected)
- `entity_type` (PayrollPeriod, PayrollCalculation, etc.)
- `user_id` (who performed the action)
- `date_range` (from/to dates)
- `search` (text search across descriptions)

### Task 8.3: Update Frontend

**File:** `resources/js/pages/Payroll/Reports/Audit.tsx`

- Accept paginated data
- Wire filter controls to Inertia query params
- Remove any frontend mock data fallbacks

### Acceptance Criteria
- [ ] Audit logs come from `activity_log` or equivalent real source
- [ ] Change history comes from `payroll_calculation_logs`
- [ ] Filters work (action, entity type, user, date range, search)
- [ ] Pagination works
- [ ] Real user names and emails displayed
- [ ] Real timestamps and IP addresses (from activity log)
- [ ] Old/new values from activity log `properties` column

---

## Implementation Order & Dependencies

```
Phase 1 ──── Fix Permissions (Loans, AllowancesDeductions)
    │         No dependencies. Quick fix.
    │
Phase 2 ──── Create Missing Models
    │         No dependencies. Required by Phases 5, 6.
    │
Phase 3 ──── Wire Advances
    │         Uses existing EmployeeLoan model.
    │
Phase 4 ──── Wire Payroll Register
    │         Uses existing PayrollPeriod, EmployeePayrollCalculation, SalaryComponent.
    │
Phase 5 ──── Wire Government Agency Pages (BIR, SSS, PhilHealth, PagIBIG)
    │         Depends on Phase 2 (models).
    │
Phase 6 ──── Wire Government Reports & Remittances Summary
    │         Depends on Phase 2 + Phase 5.
    │
Phase 7 ──── Wire Analytics Report
    │         Uses existing PayrollPeriod, EmployeePayrollCalculation.
    │
Phase 8 ──── Wire Audit Report
              Uses existing activity_log, PayrollCalculationLog.
```

**Phases 1, 2, 3, 4, 7, 8** can be worked in parallel (no interdependencies).
**Phase 5** requires Phase 2 completion.
**Phase 6** requires Phase 2 + 5 completion.

---

## Test Plan

### Unit Tests
- [ ] `EmployeeGovernmentContribution` model relationships and scopes
- [ ] `GovernmentRemittance` model relationships and scopes
- [ ] `GovernmentReport` model relationships and scopes
- [ ] `GovernmentContributionService` methods return correct data
- [ ] Permission checks: Payroll Officer CAN access loans/allowances, regular Employee CANNOT

### Feature Tests
- [ ] `GET /payroll/loans` returns 200 for Payroll Officer (was 403)
- [ ] `GET /payroll/allowances-deductions` returns 200 for Payroll Officer (was 403)
- [ ] `GET /payroll/advances` returns real data from `employee_loans`
- [ ] `POST /payroll/advances` creates EmployeeLoan with `loan_type = 'cash_advance'`
- [ ] `GET /payroll/reports/register` returns real PayrollPeriod + calculation data
- [ ] `GET /payroll/government/bir` returns data from `employee_government_contributions`
- [ ] `GET /payroll/government/sss` returns real SSS contribution data
- [ ] `GET /payroll/government/philhealth` returns real PhilHealth data
- [ ] `GET /payroll/government/pagibig` returns real Pag-IBIG data
- [ ] `GET /payroll/government/remittances` returns data from `government_remittances`
- [ ] `GET /payroll/reports/government` aggregates from `government_reports`
- [ ] `GET /payroll/reports/analytics` computes from real `employee_payroll_calculations`
- [ ] `GET /payroll/reports/audit` returns paginated activity log data

### Manual Testing
- [ ] Verify all 12 pages load without errors
- [ ] Verify filter/search functionality on each page
- [ ] Verify empty states display correctly (new database with no data)
- [ ] Verify pagination on all list pages
- [ ] Verify report generation creates files and DB records
- [ ] Verify report download serves correct file content

---

## Files Modified Summary

### Controllers to Modify (10)
1. `app/Http/Controllers/Payroll/LoansController.php` — remove `$this->authorize('viewAny', Employee::class)`
2. `app/Http/Controllers/Payroll/AllowancesDeductionsController.php` — remove same authorization
3. `app/Http/Controllers/Payroll/AdvancesController.php` — full rewrite from mock to EmployeeLoan queries
4. `app/Http/Controllers/Payroll/Reports/PayrollRegisterController.php` — full rewrite from mock to real queries
5. `app/Http/Controllers/Payroll/Reports/PayrollGovernmentReportsController.php` — rewrite from mock
6. `app/Http/Controllers/Payroll/Reports/PayrollAnalyticsController.php` — rewrite from mock
7. `app/Http/Controllers/Payroll/Reports/PayrollAuditController.php` — rewrite from mock
8. `app/Http/Controllers/Payroll/Government/BIRController.php` — rewrite from mock
9. `app/Http/Controllers/Payroll/Government/SSSController.php` — rewrite from mock
10. `app/Http/Controllers/Payroll/Government/PhilHealthController.php` — rewrite from mock
11. `app/Http/Controllers/Payroll/Government/PagIbigController.php` — rewrite from mock
12. `app/Http/Controllers/Payroll/Government/GovernmentRemittancesController.php` — rewrite from mock

### New Models (3)
1. `app/Models/EmployeeGovernmentContribution.php`
2. `app/Models/GovernmentRemittance.php`
3. `app/Models/GovernmentReport.php`

### New Service (1)
1. `app/Services/Payroll/GovernmentContributionService.php`

### Seeders (1)
1. `database/seeders/RolesAndPermissionsSeeder.php` — add payroll permissions

### Frontend Pages to Update (12)
1. `resources/js/pages/Payroll/EmployeePayroll/Loans/Index.tsx`
2. `resources/js/pages/Payroll/EmployeePayroll/AllowancesDeductions/Index.tsx`
3. `resources/js/pages/Payroll/Advances/Index.tsx`
4. `resources/js/pages/Payroll/Reports/Register/Index.tsx`
5. `resources/js/pages/Payroll/Reports/Government/Index.tsx`
6. `resources/js/pages/Payroll/Reports/Analytics.tsx`
7. `resources/js/pages/Payroll/Reports/Audit.tsx`
8. `resources/js/pages/Payroll/Government/BIR/Index.tsx`
9. `resources/js/pages/Payroll/Government/SSS/Index.tsx`
10. `resources/js/pages/Payroll/Government/PhilHealth/Index.tsx`
11. `resources/js/pages/Payroll/Government/PagIbig/Index.tsx`
12. `resources/js/pages/Payroll/Government/Remittances/Index.tsx`
