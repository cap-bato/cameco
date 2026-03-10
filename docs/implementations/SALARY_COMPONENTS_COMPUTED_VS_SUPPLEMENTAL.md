# Salary Components: Computed vs Supplemental Fix

**Created:** 2026-03-07  
**Priority:** MEDIUM — prevents future double-counting; no current breakage (tables are empty)  
**Risk:** LOW — additive changes only; no modification to `PayrollCalculationService`

---

## 1. Why This Fix Is Needed

### The Problem: Two Systems That Weren't Reconciled

The `salary_components` catalog was seeded with entries like `BASIC`, `OT_REG`, `SSS`, `PHILHEALTH`, `PAGIBIG`, `TAX`, and `LOAN_DEDUCTION`. These look like they should drive payroll — and they contain fields like `ot_multiplier`, `calculation_method`, and `affects_sss` that suggest a rule-driven engine.

But the **actual calculation engine** (`PayrollCalculationService`) never reads those fields. It independently calculates everything from `EmployeePayrollInfo` + `daily_attendance_summary`:

```php
// Step 4 — basic pay from PayrollInfo.daily_rate × daysWorked
$basicPay = $this->calculateBasicPay($daysWorked, $payrollInfo);

// Step 5 — OT from PayrollInfo.hourly_rate × OT hours (multipliers hardcoded in the method)
$overtimePay = $this->calculateOvertimePay($overtimeHours, $payrollInfo);

// Step 6 — reads employee_salary_components pivot and sums amounts
$components = $this->componentService->getEmployeeComponents($employee, true);
$componentAmounts = $components->sum('pivot.amount');   // ← BLINDLY SUMS

// Step 8
$grossPay = $basicPay + $overtimePay + $componentAmounts + $totalAllowances;
```

Step 6 **blindly sums** whatever is in the pivot. It doesn't check what kind of component was assigned. If HR assigns `BASIC` with `pivot.amount = 28000` to an employee whose `daily_rate` already yields `basicPay = 28000`, the result is:

```
grossPay = 28,000 (engine)
         + 28,000 (pivot) ← DOUBLE-COUNTED
         = 56,000 ← WRONG
```

**This is not currently a problem** because `employee_salary_components` is empty. But now that `SalaryComponentSeeder` has been run and the catalog is visible in the UI, HR staff can assign components freely — including the dangerous computed ones.

---

## 2. The Correct Split

The catalog entries must be divided into two roles:

### COMPUTED — Engine Owns These
The calculation engine produces these values independently every pay period. They **cannot be static pivot amounts** because they change with attendance, salary brackets, and BIR rules.

| Code | Name | Why Engine Must Own It |
|---|---|---|
| `BASIC` | Basic Salary | = `daily_rate × daysWorked` — changes with absences |
| `OT_REG` | Overtime Regular (1.25×) | = `hourly_rate × 1.25 × OT hours` — dynamic |
| `OT_HOLIDAY` | Overtime Holiday (1.30×) | Same — dynamic |
| `OT_DOUBLE` | Overtime Double (2.00×) | Same — dynamic |
| `OT_TRIPLE` | Overtime Triple (2.60×) | Same — dynamic |
| `HOLIDAY_REG` | Regular Holiday Pay | Dynamic — depends on calendar |
| `HOLIDAY_DOUBLE` | Double Holiday Pay | Dynamic |
| `HOLIDAY_SPECIAL_WORK` | Special Holiday (If Worked) | Dynamic |
| `PREMIUM_NIGHT` | Night Shift Premium | Dynamic — % of this period's basic |
| `SSS` | SSS Contribution | Bracket lookup from PayrollInfo |
| `PHILHEALTH` | PhilHealth Contribution | % of gross — dynamic |
| `PAGIBIG` | Pag-IBIG Contribution | % of basic — dynamic |
| `TAX` | Withholding Tax | Depends on monthly taxable income |
| `LOAN_DEDUCTION` | Loan Deduction | Set per loan schedule — managed by LoanManagementService |

**Rule:** `is_engine_computed = true` → pivot assignment is **blocked**.

---

### SUPPLEMENTAL — Not Auto-Computed, But Further Split

Of the 7 non-engine-computed components, only **2 are unique to the pivot**. The other 5 are fully redundant with `employee_allowances` and should be blocked from pivot assignment to prevent double-counting and confusion.

#### Tier 1 — Unique to the Pivot (`is_pivot_assignable = true`)

| Code | Name | Why Only Pivot Covers This |
|---|---|---|
| `ALLOWANCE_DIFF_RATE` | Rate Difference | No matching type in `employee_allowances` — pivot is the only clean home |
| `13TH_MONTH` | 13th Month Pay | Not in `employee_allowances`; requires separate per-employee tracking |

**Rule:** `is_engine_computed = false, is_pivot_assignable = true` → pivot assignment **allowed and recommended**.

#### Tier 2 — Redundant with `employee_allowances` (`is_pivot_assignable = false`)

| Code | Name | Already Covered By |
|---|---|---|
| `ALLOWANCE_OTHER` | Other Allowance | `employee_allowances.allowance_type = 'other'` |
| `RICE` | Rice Subsidy | `employee_allowances.allowance_type = 'rice'` |
| `CLOTHING` | Clothing/Uniform Allowance | `employee_allowances.allowance_type = 'clothing'` |
| `LAUNDRY` | Laundry Allowance | `employee_allowances.allowance_type = 'laundry'` |
| `MEDICAL` | Medical/Health Allowance | `employee_allowances.allowance_type = 'medical'` |

**Rule:** `is_pivot_assignable = false` → pivot assignment **blocked with redirect**. The service throws a `ValidationException` telling HR to use Allowances & Deductions instead.

> **Why block rather than warn?** Both Step 6 (components pivot) and Step 7 (allowances) feed into gross pay independently. If HR uses both for `RICE`, the employee gets double rice subsidy silently. Since `AllowanceDeductionService` already handles all five cleanly with its own UI, there is no benefit to the pivot path for these types. One system, one source of truth.

---

## 3. Implementation Phases

---

### Phase 1 — Add `is_engine_computed` Column to `salary_components`

**Goal:** Add the flag to the DB so the system can enforce the split.

**Files to change:**
- New migration: `database/migrations/YYYY_MM_DD_add_is_engine_computed_to_salary_components.php`
- `app/Models/SalaryComponent.php` — add to `$fillable` and `$casts`

**Tasks:**

- [ ] **1.1** Create migration with two new columns:
  ```php
  $table->boolean('is_engine_computed')->default(false)->after('is_system_component');
  $table->boolean('is_pivot_assignable')->default(false)->after('is_engine_computed');
  ```
  Both default to `false` — no existing rows are affected.

- [ ] **1.2** Add to `SalaryComponent` model `$fillable` and `$casts`:
  ```php
  // $fillable
  'is_engine_computed',
  'is_pivot_assignable',

  // $casts
  'is_engine_computed'   => 'boolean',
  'is_pivot_assignable'  => 'boolean',
  ```

- [ ] **1.3** Add scope to model for the assign dropdown:
  ```php
  // Returns only components that HR is allowed to assign via the pivot
  public function scopeAssignable($query)
  {
      return $query->where('is_pivot_assignable', true)->where('is_active', true);
  }
  ```

**Zero risk:** both columns default to `false`; no calculation code is touched.

---

### Phase 2 — Update `SalaryComponentSeeder` to Set the Flag

**Goal:** Mark all COMPUTED catalog entries with `is_engine_computed = true`.

**Files to change:**
- `database/seeders/SalaryComponentSeeder.php`

**Tasks:**

- [ ] **2.1** Add `'is_engine_computed' => true, 'is_pivot_assignable' => false` to COMPUTED entries:
  - `BASIC`, `OT_REG`, `OT_HOLIDAY`, `OT_DOUBLE`, `OT_TRIPLE`
  - `HOLIDAY_REG`, `HOLIDAY_DOUBLE`, `HOLIDAY_SPECIAL_WORK`, `PREMIUM_NIGHT`
  - `SSS`, `PHILHEALTH`, `PAGIBIG`, `TAX`, `LOAN_DEDUCTION`

- [ ] **2.2** Add `'is_engine_computed' => false, 'is_pivot_assignable' => true` to **Tier 1 unique** entries only:
  - `ALLOWANCE_DIFF_RATE`, `13TH_MONTH`

- [ ] **2.3** Add `'is_engine_computed' => false, 'is_pivot_assignable' => false` to **Tier 2 redundant** entries:
  - `ALLOWANCE_OTHER`, `RICE`, `CLOTHING`, `LAUNDRY`, `MEDICAL`

- [ ] **2.4** Re-run seeder to update the existing rows:
  ```bash
  php artisan db:seed --class=SalaryComponentSeeder
  ```
  Safe — uses `updateOrCreate` on `code`.

---

### Phase 3 — Block Assignment of Computed Components in `SalaryComponentService`

**Goal:** Make it impossible to assign a computed component via `assignComponentToEmployee()`.

**Files to change:**
- `app/Services/Payroll/SalaryComponentService.php`

**Tasks:**

- [ ] **3.1** Add two guards at the top of `assignComponentToEmployee()`:
  ```php
  public function assignComponentToEmployee(
      Employee $employee,
      SalaryComponent $component,
      array $data,
      User $creator
  ): EmployeeSalaryComponent {
      // Guard 1: engine-computed components are owned by the payroll engine
      if ($component->is_engine_computed) {
          throw ValidationException::withMessages([
              'component' => "'{$component->name}' ({$component->code}) is calculated automatically "
                           . "by the payroll engine and cannot be manually assigned. "
                           . "Basic pay, overtime, government contributions and tax are computed "
                           . "from EmployeePayrollInfo and attendance data.",
          ]);
      }

      // Guard 2: redundant components — already handled by AllowanceDeductionService
      if (!$component->is_pivot_assignable) {
          throw ValidationException::withMessages([
              'component' => "'{$component->name}' ({$component->code}) is managed through "
                           . "Allowances & Deductions, not the component pivot. "
                           . "Go to Employee Payroll → Allowances & Deductions to assign this benefit.",
          ]);
      }

      // ... rest of existing code unchanged
  ```

**Zero risk to payroll calculation:** `PayrollCalculationService` doesn't call `assignComponentToEmployee()` — it only reads via `getEmployeeComponents()`. Adding guards on the write path has no effect on any running calculation.

---

### Phase 4 — Filter the Assignment Dropdown in the Frontend

**Goal:** The component picker in the employee assignment form must only show SUPPLEMENTAL components. HR should never see `BASIC`, `SSS`, etc. as assignable options.

**Files to change:**
- `app/Http/Controllers/Payroll/EmployeePayroll/SalaryComponentController.php`
- `resources/js/components/payroll/component-form-modal.tsx` (or wherever the assign UI lives)
- `resources/js/pages/Payroll/EmployeePayroll/Components/Index.tsx`

**Tasks:**

- [ ] **4.1** In `SalaryComponentController::index()`, pass `assignable_components` using the `scopeAssignable()` scope (only `is_pivot_assignable = true`):
  ```php
  $assignableComponents = SalaryComponent::assignable()
      ->orderBy('display_order')
      ->get(['id', 'name', 'code', 'component_type', 'category']);
  // Result: only ALLOWANCE_DIFF_RATE and 13TH_MONTH

  return Inertia::render('Payroll/EmployeePayroll/Components/Index', [
      'components'            => $components,           // full catalog (for reference view)
      'assignable_components' => $assignableComponents, // 2 items only (for assign dropdown)
      // ... existing props
  ]);
  ```

- [ ] **4.2** In the catalog list UI (`Components/Index.tsx`), add visual badges on COMPUTED rows:
  - Show `Engine Computed` badge in amber/orange — tooltip: *"Calculated automatically from attendance and EmployeePayrollInfo"*
  - Show `Use Allowances & Deductions` badge in blue on Tier 2 redundant rows — tooltip: *"Managed via Employee Payroll → Allowances & Deductions"*
  - Disable the "Assign" action button on both badge types

- [ ] **4.3** In the assign form dropdown, use `assignable_components` (the 2-item list), not the full catalog.

---

### Phase 5 — (Absorbed into Phase 3)

**This phase is no longer separate.** The original plan was to add a soft warning for de minimis overlap. Based on the architectural conclusion that `RICE`, `CLOTHING`, `LAUNDRY`, `MEDICAL`, and `ALLOWANCE_OTHER` are fully redundant with `employee_allowances`, the correct approach is a **hard block** (Guard 2 in Phase 3), not a warning.

**Why the change:** A warning still allows the assignment to go through, meaning payroll will silently sum amounts from both Step 6 (pivot) and Step 7 (allowances) — double-counting the benefit. Since `AllowanceDeductionService` already has a complete, working UI for all five of these types, there is no valid use case for the pivot path. Blocking it enforces one clear system and eliminates the double-count risk entirely.

**Net result:** Phase 5 is removed. Guard 2 in Phase 3 (`!$component->is_pivot_assignable`) covers all five redundant components with a clear redirect message.

---

## 4. What Does NOT Change

| Item | Status |
|---|---|
| `PayrollCalculationService` | **Untouched** — not a single line changes |
| `getEmployeeComponents()` in `SalaryComponentService` | **Untouched** — reads are unaffected |
| `CalculatePayrollJob` / `CalculateEmployeePayrollJob` | **Untouched** |
| `employee_payroll_calculations` records | **Untouched** |
| Existing payroll periods / calculations | **Unaffected** |
| `EmployeePayrollInfo` | **Untouched** |
| `employee_salary_components` pivot rows | **None exist yet** — no migration needed |

The fix is entirely on the **write path** (you can't assign computed components anymore) and the **UI** (catalog shows which are computed). The read path that runs calculation is never touched.

---

## 5. Design Decisions Record

### Why the hardcoded engine is correct
The engine hardcodes OT multipliers (1.25×, 1.30×, 2.00×, 2.60×), PhilHealth rate, PagIBIG rate, and BIR withholding tax schedules. This is **intentional and correct** for Philippine statutory payroll:

- These rates are set by **DOLE, SSS, PhilHealth, HDMF, and BIR** — not by the company
- Making them DB-configurable creates audit risk: "who changed the OT multiplier and when?"
- When the law changes, the code must be reviewed and updated deliberately, which is safer than a silent DB field change
- Rule-driven engines add complexity only justified when different employee groups have materially different contractual terms — not the case here

### Why only 2 components are truly pivot-assignable
Of the original 17 seeded components:
- 14 are COMPUTED — the engine already produces these values from `EmployeePayrollInfo` + attendance
- 3 of the remaining 7 supplemental ones (`RICE`, `CLOTHING`, `LAUNDRY`, `MEDICAL`, `ALLOWANCE_OTHER`) are fully duplicated by `employee_allowances` which is already wired into Step 7 of the engine
- Only `ALLOWANCE_DIFF_RATE` and `13TH_MONTH` have no clean equivalent in `employee_allowances`

The pivot's `sum(pivot.amount)` at Step 6 is correct behavior — but only when the 2 truly unique components are the only things assigned.

---

## 6. Implementation Order & Safe Rollout

```
Phase 1 → Migration — add is_engine_computed + is_pivot_assignable
   ↓
Phase 2 → Re-seed catalog — set both flags on all 17 entries
   ↓
Phase 3 → Service guards — Guard 1 (computed) + Guard 2 (redundant redirect)
   ↓
Phase 4 → Frontend — badge display + restrict assign dropdown to 2 components
```

Phases 1–3 are backend-only with zero impact on the existing payroll flow. Phase 4 is a UI polish layer. Phase 5 (overlap warning) has been removed — absorbed into Guard 2 of Phase 3 as a hard block.

---

## 7. Verification Checklist

After implementation:

- [ ] Migration runs without error: two new boolean columns added to `salary_components`
- [ ] `php artisan db:seed --class=SalaryComponentSeeder` runs without error
- [ ] `salary_components` table:
  - 14 rows: `is_engine_computed = true, is_pivot_assignable = false` (COMPUTED)
  - 5 rows: `is_engine_computed = false, is_pivot_assignable = false` (REDUNDANT: RICE/CLOTHING/LAUNDRY/MEDICAL/ALLOWANCE_OTHER)
  - 2 rows: `is_engine_computed = false, is_pivot_assignable = true` (UNIQUE: ALLOWANCE_DIFF_RATE/13TH_MONTH)
- [ ] Attempting to assign `BASIC` via API → `422` with engine-computed message
- [ ] Attempting to assign `RICE` via API → `422` with "use Allowances & Deductions" redirect message
- [ ] Assigning `ALLOWANCE_DIFF_RATE` or `13TH_MONTH` via API → succeeds normally
- [ ] `/payroll/salary-components` catalog shows correct badges on all 3 tiers
- [ ] Assign dropdown only lists **2 components**: `ALLOWANCE_DIFF_RATE` and `13TH_MONTH`
- [ ] Full payroll calculation still runs and produces correct `net_pay` (no regression)
- [ ] `RICE`/`CLOTHING`/`LAUNDRY`/`MEDICAL`/`other` allowances still assignable via Allowances & Deductions UI
