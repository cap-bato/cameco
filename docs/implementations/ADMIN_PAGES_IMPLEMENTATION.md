# ADMIN PAGES — IMPLEMENTATION PLAN

**Pages covered:**
- `GET /admin/company`
- `GET /admin/business-rules`
- `GET /admin/payroll-rules`
- `GET /admin/system-config`
- `GET /admin/approval-workflows` ← has a runtime error

**Status:** In Progress  
**Priority:** High  
**Date:** 2025-07

---

## § 1 — Current State

> **Key Finding:** None of the 4 "mock?" pages use mock data. They all query `SystemSetting` from
> the database. They appear empty because `system_settings` has no pre-seeded records for their
> key prefixes. The approval-workflows page **renders OK** but **crashes on save** due to a route
> ordering bug in `routes/admin.php`.

| Page | Controller | Frontend Page | Backend State | Frontend State |
|---|---|---|---|---|
| `/admin/company` | `Admin/CompanyController` | `Admin/Company/Index.tsx` | ✅ DB-wired, no mock | ✅ No TS errors |
| `/admin/business-rules` | `Admin/BusinessRulesController` | `Admin/BusinessRules/Index.tsx` | ✅ DB-wired, no mock | ✅ No TS errors |
| `/admin/payroll-rules` | `Admin/PayrollRulesController` | `Admin/PayrollRules/Index.tsx` | ⚠️ DB-wired, missing route | ⚠️ Missing TS field |
| `/admin/system-config` | `Admin/SystemConfigController` | `Admin/SystemConfig/Index.tsx` | ⚠️ Missing optional props | ✅ No TS errors |
| `/admin/approval-workflows` | `Admin/ApprovalWorkflowController` | `Admin/ApprovalWorkflows/Index.tsx` | ❌ Route conflict on save | ✅ No TS errors |

### Data flow all 5 pages share

```
Request → EnsureOfficeAdmin middleware
        → permission:admin.*.view middleware
        → Controller::index()
        → SystemSetting::where('key', 'LIKE', '<prefix>.%')->get()  (or by category)
        → Inertia::render('Admin/<Page>/Index', [...props])
        → React page renders with prop data
```

No hardcoded/mock arrays are returned. Pages show defaults only when `system_settings` has no
matching rows (category/key prefix).

---

## § 2 — Data Shape Analysis

### `/admin/company`

**Controller returns:**
```php
[
  'company' => [
    'company_name', 'company_tagline', 'company_address', 'company_city',
    'company_state', 'company_postal_code', 'company_country',
    'company_phone', 'company_email', 'company_website',
    'company_tax_id', 'company_registration', 'company_industry',
    'company_founding_year', 'company_size', 'company_logo',
  ]
]
```

**Frontend `CompanyIndexProps`:** `{ company: CompanyData }` ✅ Match.

---

### `/admin/business-rules`

**Controller returns:**
```php
['businessRules' => [
  // 24 keys across working_hours, overtime, attendance, holidays, holiday_multipliers groups
]]
```

**Frontend `BusinessRulesIndexProps`:** `{ businessRules: BusinessRulesData }` ✅ Match.

---

### `/admin/payroll-rules`

**Controller `index()` returns:**
```php
[
  'payrollRules' => [
    // from category 'payroll': standard_deductions, salary_structure, allowances, cutoff_dates, etc.
    // from category 'government_rates': sss, philhealth, pagibig, withholding_tax
    // from category 'payment_methods': bank_transfer, gcash, cash, check
  ]
]
```

**Frontend `PayrollRulesIndexProps`:**
```ts
interface PayrollRulesIndexProps {
  payrollRules: PayrollRulesData;   // ← does NOT include standard_deductions
}
```

**⚠️ Mismatch:** Controller sends `payrollRules.standard_deductions` (nested object from category
`payroll`), but the TypeScript interface for `PayrollRulesData` has no `standard_deductions` key.
The `StandardDeductionsForm` component receives `undefined` initial values.

---

### `/admin/system-config`

**Controller `index()` returns:**
```php
['systemConfig' => $config]  // only this one prop
```

**Frontend `SystemConfigIndexProps`:**
```ts
interface SystemConfigIndexProps {
  systemConfig: SystemConfigData;
  auditLogs?: PaginatedActivityLog;    // optional — not passed → undefined
  availableUsers?: UserOption[];       // optional — not passed → undefined
  filters?: AuditFilters;             // optional — not passed → undefined
}
```

**⚠️ Gap:** The "Audit Logs" tab renders `<AuditLogsTable auditLogs={auditLogs} ... />` with
`undefined`. The tab shows an empty state rather than crashing (props are optional), but audit
functionality is completely non-functional.

---

### `/admin/approval-workflows`

**Controller `index()` returns:**
```php
[
  'approvalRules' => [...],   // 15 keys matching LeaveApprovalRules interface ✅
  'leaveTypes' => [...],      // code, name, is_paid ✅
]
```

**Frontend `ApprovalWorkflowsIndexProps`:**
```ts
interface ApprovalWorkflowsIndexProps {
  approvalRules: LeaveApprovalRules;
  leaveTypes: LeaveType[];
}
```

✅ Props match. Page renders correctly.

**`handleSaveAll()` submits to:**
```ts
router.put('/admin/leave-policies/approval-rules', formData);
```

**❌ Runtime error:** `PUT /admin/leave-policies/approval-rules` is caught by the parameterized
route `PUT /admin/leave-policies/{leavePolicy}` (declared first at line 174 of `routes/admin.php`)
before reaching the fixed `PUT /admin/leave-policies/approval-rules` route (line 188).
Laravel's route model binding tries to find `LeavePolicy` with key `'approval-rules'` → fails
with `ModelNotFoundException` → 404/500 response.

---

## § 3 — Issues to Resolve

| # | Page | File | Issue | Severity |
|---|---|---|---|---|
| A | `approval-workflows` | `routes/admin.php:161-190` | `PUT /{leavePolicy}` declared before `PUT /approval-rules` in leave-policies group → route conflict traps save action | **BREAKING** |
| B | `payroll-rules` | `routes/admin.php:~230` | Route `PUT /admin/payroll-rules/deductions` not registered; `updateDeductions()` method exists in controller but is unreachable | **BREAKING** |
| C | `payroll-rules` | `Admin/PayrollRules/Index.tsx` | `PayrollRulesIndexProps` TypeScript interface missing `standard_deductions` field; `StandardDeductionsForm` gets `undefined` | **TYPE ERROR** |
| D | `system-config` | `Admin/SystemConfigController.php` | `index()` doesn't pass `auditLogs`, `availableUsers`, `filters` props; Audit Logs tab non-functional | **MISSING FEATURE** |
| E | All 5 | `database/seeders/` | No seeder for `company.*`, `business_rules.*`, `payroll.*`, `government_rates.*`, `payment_methods.*`, `system_config.*` key prefixes → all pages show empty defaults | **MISSING DATA** |
| F | All 5 | `database/seeders/OfficeAdminSeeder.php` | If `OfficeAdminSeeder` hasn't been run, all pages return 403 permission denied | **PERMISSION** |

---

## § 4 — Phased Implementation

---

### Phase 1 — Fix approval-workflows save (route ordering bug)
**Files:** `routes/admin.php`  
**Status:** [ ] Not started

**Problem:** In the `leave-policies` route group, `PUT /{leavePolicy}` is declared at line 174,
before `PUT /approval-rules` at line 188. Given Laravel evaluates routes in declaration order,
`PUT /admin/leave-policies/approval-rules` incorrectly binds as `$leavePolicy = 'approval-rules'`.

**Fix:** Move the fixed-path routes (`/approval-rules`) **before** the parameterized routes
(`/{leavePolicy}`) within the `leave-policies` group.

```php
// In routes/admin.php — leave-policies group
Route::prefix('leave-policies')->name('leave-policies.')->group(function () {

    // ── Fixed routes FIRST ─────────────────────────────────────────────
    Route::get('/', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'index'])
        ->middleware('permission:admin.leave-policies.view')
        ->name('index');

    Route::post('/', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'store'])
        ->middleware('permission:admin.leave-policies.create')
        ->name('store');

    // Approval rules — must come BEFORE /{leavePolicy} parameterized routes
    Route::get('/approval-rules', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'configureApprovalRules'])
        ->middleware('permission:admin.leave-policies.view')
        ->name('approval-rules');

    Route::put('/approval-rules', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'updateApprovalRules'])
        ->middleware('permission:admin.leave-policies.edit')
        ->name('approval-rules.update');

    // ── Parameterized routes AFTER ─────────────────────────────────────
    Route::put('/{leavePolicy}', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'update'])
        ->middleware('permission:admin.leave-policies.edit')
        ->name('update');

    Route::delete('/{leavePolicy}', [\App\Http\Controllers\Admin\LeavePolicyController::class, 'destroy'])
        ->middleware('permission:admin.leave-policies.delete')
        ->name('destroy');
});
```

**Validation after fix:**
- Navigate to `/admin/approval-workflows`
- Change any rule value, click "Save All Rules"
- Should return success toast (not a 404/500 error)

---

### Phase 2 — Fix payroll-rules deductions (missing route + TS interface)
**Files:** `routes/admin.php`, `Admin/PayrollRules/Index.tsx`  
**Status:** [ ] Not started

#### 2a — Add missing route

In `routes/admin.php`, inside the `payroll-rules` prefix group (around line 230–252), add:

```php
// Deductions update (was missing)
Route::put('/deductions', [\App\Http\Controllers\Admin\PayrollRulesController::class, 'updateDeductions'])
    ->middleware('permission:admin.payroll-rules.edit')
    ->name('deductions.update');
```

The `PayrollRulesController::updateDeductions()` method already exists (line ~206) and stores
`payroll.deductions.*` keys in `system_settings`.

#### 2b — Add `standard_deductions` to TypeScript interface

In `Admin/PayrollRules/Index.tsx`, find the `PayrollRulesData` (or `PayrollRulesIndexProps`)
interface and add the missing `standard_deductions` property.

The controller returns from category `payroll` which includes:
- `payroll.deductions.sss_employee` (boolean)
- `payroll.deductions.philhealth_employee` (boolean)
- `payroll.deductions.pagibig_employee` (boolean)
- `payroll.deductions.income_tax` (boolean)
- … and other deduction keys

```ts
// Add to PayrollRulesData interface (or the parent interface):
standard_deductions?: {
    sss_employee?: boolean;
    philhealth_employee?: boolean;
    pagibig_employee?: boolean;
    income_tax?: boolean;
    loan_deductions?: boolean;
    cash_advance?: boolean;
};
```

**Validation after fix:**
- Navigate to `/admin/payroll-rules` → Standard Deductions tab
- Toggle a deduction checkbox and save
- Should succeed (200), not 404

---

### Phase 3 — Add Audit Logs data to SystemConfig
**Files:** `app/Http/Controllers/Admin/SystemConfigController.php`  
**Status:** [ ] Not started

In `SystemConfigController::index()`, add queries for audit logs, available users, and filters:

```php
use Spatie\Activitylog\Models\Activity;

public function index(Request $request): Response
{
    $config = SystemSetting::where('category', 'system_config')
        ->get()
        ->pluck('value', 'key')
        ->toArray();

    $systemConfig = $this->buildSystemConfig($config);

    // Audit logs
    $logsQuery = Activity::with('causer:id,name,email')
        ->latest();

    // Apply filters
    if ($request->filled('user_id')) {
        $logsQuery->where('causer_id', $request->input('user_id'));
    }
    if ($request->filled('log_name')) {
        $logsQuery->where('log_name', $request->input('log_name'));
    }
    if ($request->filled('from')) {
        $logsQuery->whereDate('created_at', '>=', $request->input('from'));
    }
    if ($request->filled('to')) {
        $logsQuery->whereDate('created_at', '<=', $request->input('to'));
    }

    $auditLogs = $logsQuery->paginate(20)->withQueryString();

    $availableUsers = \App\Models\User::select('id', 'name', 'email')
        ->orderBy('name')
        ->get();

    return Inertia::render('Admin/SystemConfig/Index', [
        'systemConfig' => $systemConfig,
        'auditLogs' => $auditLogs,
        'availableUsers' => $availableUsers,
        'filters' => $request->only(['user_id', 'log_name', 'from', 'to']),
    ]);
}
```

**Validation after fix:**
- Navigate to `/admin/system-config` → Audit Logs tab
- Should show paginated activity log entries
- Filter by user or date should work

---

### Phase 4 — Create SystemSettingsSeeder
**Files:** `database/seeders/SystemSettingsSeeder.php`  
**Status:** [ ] Not started

Create a seeder that pre-populates `system_settings` with sensible Philippine-business defaults
for all key prefixes used by the 5 admin pages.

**Key groups to seed:**

#### `company.*` (CompanyController)
```php
['key' => 'company.name',              'value' => 'CAMECO Corporation',         'type' => 'string',  'category' => 'company'],
['key' => 'company.tagline',           'value' => 'Excellence in Every Step',   'type' => 'string',  'category' => 'company'],
['key' => 'company.address',           'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.city',              'value' => 'Manila',                     'type' => 'string',  'category' => 'company'],
['key' => 'company.state',             'value' => 'Metro Manila',               'type' => 'string',  'category' => 'company'],
['key' => 'company.postal_code',       'value' => '1000',                       'type' => 'string',  'category' => 'company'],
['key' => 'company.country',           'value' => 'Philippines',                'type' => 'string',  'category' => 'company'],
['key' => 'company.phone',             'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.email',             'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.website',           'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.tax_id',            'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.registration',      'value' => '',                           'type' => 'string',  'category' => 'company'],
['key' => 'company.industry',          'value' => 'Manufacturing',              'type' => 'string',  'category' => 'company'],
['key' => 'company.founding_year',     'value' => '2000',                       'type' => 'integer', 'category' => 'company'],
['key' => 'company.size',              'value' => '100-500',                    'type' => 'string',  'category' => 'company'],
['key' => 'company.logo',              'value' => '',                           'type' => 'string',  'category' => 'company'],
```

#### `business_rules.*` (BusinessRulesController)
```php
// Working hours
['key' => 'business_rules.working_hours.work_start',     'value' => '08:00', 'type' => 'string',  'category' => 'business_rules'],
['key' => 'business_rules.working_hours.work_end',       'value' => '17:00', 'type' => 'string',  'category' => 'business_rules'],
['key' => 'business_rules.working_hours.break_duration', 'value' => '60',    'type' => 'integer', 'category' => 'business_rules'],
['key' => 'business_rules.working_hours.work_days',      'value' => '["Monday","Tuesday","Wednesday","Thursday","Friday"]', 'type' => 'json', 'category' => 'business_rules'],
['key' => 'business_rules.working_hours.hours_per_day',  'value' => '8',     'type' => 'integer', 'category' => 'business_rules'],

// Overtime rules  
['key' => 'business_rules.overtime.enabled',             'value' => '1',     'type' => 'boolean', 'category' => 'business_rules'],
['key' => 'business_rules.overtime.min_hours',           'value' => '0.5',   'type' => 'float',   'category' => 'business_rules'],
['key' => 'business_rules.overtime.max_daily_overtime',  'value' => '4',     'type' => 'float',   'category' => 'business_rules'],
['key' => 'business_rules.overtime.requires_approval',   'value' => '1',     'type' => 'boolean', 'category' => 'business_rules'],
['key' => 'business_rules.overtime.night_differential_start', 'value' => '22:00', 'type' => 'string', 'category' => 'business_rules'],
['key' => 'business_rules.overtime.night_differential_end',   'value' => '06:00', 'type' => 'string', 'category' => 'business_rules'],

// Attendance rules
['key' => 'business_rules.attendance.grace_period_minutes', 'value' => '15', 'type' => 'integer', 'category' => 'business_rules'],
['key' => 'business_rules.attendance.late_deduction_enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'business_rules'],
['key' => 'business_rules.attendance.absent_deduction_enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'business_rules'],
['key' => 'business_rules.attendance.half_day_cutoff_hours', 'value' => '4', 'type' => 'float', 'category' => 'business_rules'],

// Holiday multipliers (DOLE-mandated rates)
['key' => 'business_rules.holiday.regular_holiday_multiplier',  'value' => '2.0', 'type' => 'float', 'category' => 'business_rules'],
['key' => 'business_rules.holiday.special_holiday_multiplier',  'value' => '1.3', 'type' => 'float', 'category' => 'business_rules'],
['key' => 'business_rules.holiday.double_holiday_multiplier',   'value' => '3.0', 'type' => 'float', 'category' => 'business_rules'],
['key' => 'business_rules.holiday.rest_day_multiplier',         'value' => '1.3', 'type' => 'float', 'category' => 'business_rules'],
['key' => 'business_rules.holiday.holiday_ot_multiplier',       'value' => '2.6', 'type' => 'float', 'category' => 'business_rules'],
```

#### `payroll.*` + `government_rates.*` + `payment_methods.*` (PayrollRulesController)
```php
// Payroll category
['key' => 'payroll.cutoff.first_cutoff_start',  'value' => '1',  'type' => 'integer', 'category' => 'payroll'],
['key' => 'payroll.cutoff.first_cutoff_end',    'value' => '15', 'type' => 'integer', 'category' => 'payroll'],
['key' => 'payroll.cutoff.second_cutoff_start', 'value' => '16', 'type' => 'integer', 'category' => 'payroll'],
['key' => 'payroll.cutoff.second_cutoff_end',   'value' => '31', 'type' => 'integer', 'category' => 'payroll'],
['key' => 'payroll.deductions.sss_employee',    'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
['key' => 'payroll.deductions.philhealth_employee', 'value' => '1', 'type' => 'boolean', 'category' => 'payroll'],
['key' => 'payroll.deductions.pagibig_employee', 'value' => '1', 'type' => 'boolean', 'category' => 'payroll'],
['key' => 'payroll.deductions.income_tax',      'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
['key' => 'payroll.deductions.loan_deductions', 'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],
['key' => 'payroll.deductions.cash_advance',    'value' => '1',  'type' => 'boolean', 'category' => 'payroll'],

// Government rates (2024 TRAIN law / RA 11199)
['key' => 'government_rates.sss.employee_rate',  'value' => '0.045', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.sss.employer_rate',  'value' => '0.095', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.sss.max_msc',        'value' => '30000', 'type' => 'integer', 'category' => 'government_rates'],
['key' => 'government_rates.philhealth.employee_rate', 'value' => '0.025', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.philhealth.employer_rate', 'value' => '0.025', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.philhealth.max_salary', 'value' => '100000', 'type' => 'integer', 'category' => 'government_rates'],
['key' => 'government_rates.pagibig.employee_rate', 'value' => '0.02', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.pagibig.employer_rate', 'value' => '0.02', 'type' => 'float', 'category' => 'government_rates'],
['key' => 'government_rates.pagibig.max_contribution', 'value' => '100', 'type' => 'integer', 'category' => 'government_rates'],

// Payment methods
['key' => 'payment_methods.bank_transfer.enabled', 'value' => '1', 'type' => 'boolean', 'category' => 'payment_methods'],
['key' => 'payment_methods.gcash.enabled',          'value' => '0', 'type' => 'boolean', 'category' => 'payment_methods'],
['key' => 'payment_methods.cash.enabled',           'value' => '1', 'type' => 'boolean', 'category' => 'payment_methods'],
['key' => 'payment_methods.check.enabled',          'value' => '0', 'type' => 'boolean', 'category' => 'payment_methods'],
```

#### `system_config.*` (SystemConfigController)
```php
['key' => 'system_config.timezone',            'value' => 'Asia/Manila',    'type' => 'string',  'category' => 'system_config'],
['key' => 'system_config.date_format',         'value' => 'Y-m-d',          'type' => 'string',  'category' => 'system_config'],
['key' => 'system_config.time_format',         'value' => 'H:i',            'type' => 'string',  'category' => 'system_config'],
['key' => 'system_config.currency',            'value' => 'PHP',            'type' => 'string',  'category' => 'system_config'],
['key' => 'system_config.locale',              'value' => 'en_PH',          'type' => 'string',  'category' => 'system_config'],
['key' => 'system_config.maintenance_mode',    'value' => '0',              'type' => 'boolean', 'category' => 'system_config'],
['key' => 'system_config.allow_registration',  'value' => '0',              'type' => 'boolean', 'category' => 'system_config'],
['key' => 'system_config.session_lifetime',    'value' => '120',            'type' => 'integer', 'category' => 'system_config'],
['key' => 'system_config.max_login_attempts',  'value' => '5',              'type' => 'integer', 'category' => 'system_config'],
```

**Seeder class skeleton:**

```php
// database/seeders/SystemSettingsSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // company.*
            // business_rules.*
            // payroll.*
            // government_rates.*
            // payment_methods.*
            // system_config.*
            // ... (all rows from the tables above)
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
```

Register in `DatabaseSeeder`:
```php
$this->call([
    // ... existing seeders ...
    SystemSettingsSeeder::class,
]);
```

Run with:
```bash
php artisan db:seed --class=SystemSettingsSeeder
```

---

## § 5 — Permission Checklist

All 5 pages are behind `EnsureOfficeAdmin` middleware and Spatie `permission:admin.*` guards.

```bash
# Must run before any admin page works:
php artisan db:seed --class=OfficeAdminSeeder

# Test user created by that seeder:
# Email: admin@cameco.com
# Password: password
# Role: Office Admin
```

**Permission-to-route mapping:**

| Page | Permission required |
|---|---|
| `/admin/company` | `admin.company.view` |
| `/admin/business-rules` | `admin.business-rules.view` |
| `/admin/payroll-rules` | `admin.payroll-rules.view` |
| `/admin/system-config` | `admin.system-config.view` |
| `/admin/approval-workflows` | `admin.approval-workflows.view` |

---

## § 6 — Test Plan

### Phase 1 — approval-workflows save fix

- [ ] Navigate to `/admin/approval-workflows` while logged in as `admin@cameco.com`
- [ ] Confirm page renders without console errors
- [ ] Change "Duration Threshold" value, click "Save All Rules"
- [ ] Expect: success toast; no 404/500 in network tab
- [ ] Reload page and confirm saved values persist

### Phase 2 — payroll-rules deductions

- [ ] Navigate to `/admin/payroll-rules` → "Standard Deductions" tab
- [ ] Toggle SSS Employee deduction off, click save
- [ ] Expect: success (no 404); verify in DB `system_settings` where `key = 'payroll.deductions.sss_employee'`
- [ ] Confirm no TypeScript console errors about `undefined` `standard_deductions`

### Phase 3 — system-config audit logs

- [ ] Navigate to `/admin/system-config` → "Audit Logs" tab
- [ ] Expect: table with paginated rows from `activity_log` table
- [ ] Filter by user → list should narrow
- [ ] Filter by date range → list should narrow

### Phase 4 — All pages with seeder

- [ ] Run `php artisan db:seed --class=SystemSettingsSeeder`
- [ ] Navigate to `/admin/company` — company name field should show "CAMECO Corporation"
- [ ] Navigate to `/admin/business-rules` — work start shows "08:00", OT enabled
- [ ] Navigate to `/admin/payroll-rules` — government rates populated
- [ ] Navigate to `/admin/system-config` — timezone shows "Asia/Manila"
- [ ] Verify update + reload persists for each page (end-to-end round-trip)

---

## § 7 — Related Files

| File | Role |
|---|---|
| `app/Http/Controllers/Admin/CompanyController.php` | Reads/writes `company.*` settings |
| `app/Http/Controllers/Admin/BusinessRulesController.php` | Reads/writes `business_rules.*` settings |
| `app/Http/Controllers/Admin/PayrollRulesController.php` | Reads/writes `payroll.*`, `government_rates.*`, `payment_methods.*` — `updateDeductions()` at ~line 206 |
| `app/Http/Controllers/Admin/SystemConfigController.php` | Reads/writes `system_config.*` settings — needs audit log props |
| `app/Http/Controllers/Admin/ApprovalWorkflowController.php` | `index()` passes `approvalRules` + `leaveTypes` |
| `app/Http/Controllers/Admin/LeavePolicyController.php` | `updateApprovalRules()` is the real save handler for approval-workflows form |
| `app/Models/SystemSetting.php` | `system_settings` table ORM; `getValue(key, default)` static helper |
| `routes/admin.php` | All admin routes — **leave-policies group** has the route ordering bug |
| `database/seeders/OfficeAdminSeeder.php` | Creates `Office Admin` role + `admin.*` permissions + test user |
| `resources/js/pages/Admin/ApprovalWorkflows/Index.tsx` | `handleSaveAll()` → `PUT /admin/leave-policies/approval-rules` |
| `resources/js/pages/Admin/PayrollRules/Index.tsx` | `handleDeductionsSubmit()` → `PUT /admin/payroll-rules/deductions` |
| `resources/js/components/admin/workflow-tester.tsx` | Fully frontend simulation; only needs `approvalRules` + `leaveTypes` |

---

## § 8 — Progress Checklist

- [ ] Phase 1: Fix route ordering in `leave-policies` group (approval-workflows save)
- [ ] Phase 2a: Add `PUT /payroll-rules/deductions` route
- [ ] Phase 2b: Add `standard_deductions` to `PayrollRulesIndexProps` TS interface
- [ ] Phase 3: Add audit log queries to `SystemConfigController::index()`
- [ ] Phase 4: Create and run `SystemSettingsSeeder`
