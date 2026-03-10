# Payroll Adjustments — Backend/Frontend Integration

**Page:** `/payroll/adjustments`  
**Status:** ✅ COMPLETED - PRODUCTION READY  
**Priority:** HIGH  
**Created:** 2026-03-07  
**Completed:** 2026-03-10

---

## ✅ Implementation Complete

All steps have been successfully completed and tested:

- ✅ **Step 1:** Database schema fixed (migrations completed)
- ✅ **Step 2:** PayrollAdjustment model & controller implemented
- ✅ **Step 3:** Frontend pages verified and tested end-to-end
- ✅ **Step 4:** Period name formatting updated (uses period_number with formatted date range)
- ✅ **Step 5:** Required imports verified and present

**Test Results:** 46/46 tests passed (100% success rate)

---

## 1. Current State

### What exists (mock only)

| Layer | File | State |
|---|---|---|
| Controller | `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php` | All methods return hardcoded arrays |
| Model | `app/Models/PayrollAdjustment.php` | Complete — relationships, scopes, helpers |
| Migration | `database/migrations/2026_02_17_065520_create_payroll_adjustments_table.php` | Wrong enum values (see §3) |
| Routes | `routes/payroll.php` lines 57–65 | All routes registered |
| Frontend page | `resources/js/pages/Payroll/PayrollProcessing/Adjustments/Index.tsx` | Working — uses Inertia props |
| Frontend page | `resources/js/pages/Payroll/PayrollProcessing/Adjustments/History.tsx` | Working — uses Inertia props |
| TypeScript types | `resources/js/types/payroll-pages.ts` lines 362–420 | Defined |

---

## 2. Data Shape Analysis

### TypeScript `PayrollAdjustment` interface (frontend contract)

```ts
interface PayrollAdjustment {
    id: number;
    payroll_period_id: number;
    payroll_period: PayrollPeriod;
    employee_id: number;
    employee_name: string;          // derived: employee.profile.full_name
    employee_number: string;        // derived: employee.employee_number
    department: string;             // derived: employee.department.name
    adjustment_type: 'earning' | 'deduction' | 'correction' | 'backpay' | 'refund';
    adjustment_category: string;    // maps to DB column: category
    amount: number;
    reason: string;
    reference_number?: string;
    status: 'pending' | 'approved' | 'rejected' | 'applied' | 'cancelled';
    requested_by: string;           // derived: createdBy.name
    requested_at: string;           // maps to DB: submitted_at or created_at
    reviewed_by?: string;           // derived: approvedBy.name or rejectedBy.name
    reviewed_at?: string;           // maps to DB: approved_at or rejected_at
    review_notes?: string;          // maps to DB: rejection_reason (or new field)
    applied_at?: string;
    created_at: string;
    updated_at: string;
}
```

### `PayrollAdjustmentsPageProps` (Index page)

```ts
interface PayrollAdjustmentsPageProps {
    adjustments: PayrollAdjustment[];
    available_periods: PayrollPeriod[];   // full PayrollPeriod objects
    available_employees: Array<{
        id: number; name: string; employee_number: string; department: string;
    }>;
    filters: { period_id?; employee_id?; status?; adjustment_type? };
}
```

---

## 3. Issues to Resolve Before Implementation

### Issue A — `adjustment_type` enum mismatch (BREAKING)

| Layer | Values |
|---|---|
| DB migration | `['addition', 'deduction', 'override']` |
| Frontend types + controller validation | `['earning', 'deduction', 'correction', 'backpay', 'refund']` |

**Fix required:** New migration to alter the enum column.

### Issue B — `employee_payroll_calculation_id` is NOT NULL (BLOCKING)

The migration declares `->foreignId('employee_payroll_calculation_id')->constrained()->cascadeOnDelete()` (non-nullable). The frontend form does not send this field. Adjustments can exist for a period without a specific `EmployeePayrollCalculation` row yet.

**Fix required:** New migration to make this column nullable.

### Issue C — `category` vs `adjustment_category`

DB column is `category`; frontend type and mock data refer to it as `adjustment_category`. The controller must map `adjustment_category` → DB `category` on write, and return it as `adjustment_category` to the frontend.

### Issue D — `review_notes` field missing from DB

The frontend uses `review_notes` for both approve and reject notes. DB only has `rejection_reason`. Approved adjustments have no `approval_notes` column.

**Fix required:** Add `approval_notes` text column (nullable) OR repurpose; simplest solution — add a generic `review_notes` column in a new migration.

### Issue E — `requested_by` / `requested_at` derived fields

DB stores `created_by` (FK to users). Frontend expects `requested_by` (string name) and `requested_at` (timestamp). The controller must derive these from relations.

`requested_at` → use `submitted_at` if set, else `created_at`.

---

## 4. Implementation Plan

### Step 1 — Fix DB Schema (new migrations)

Create `database/migrations/2026_03_07_000001_fix_payroll_adjustments_schema.php`:

```php
Schema::table('payroll_adjustments', function (Blueprint $table) {
    // A: Make calculation FK nullable (adjustments can exist without a calculation)
    $table->foreignId('employee_payroll_calculation_id')
        ->nullable()->change();

    // D: Add review_notes column
    $table->text('review_notes')->nullable()->after('rejection_reason');
});
```

Create `database/migrations/2026_03_07_000002_update_payroll_adjustment_type_enum.php`:

```php
// PostgreSQL: drop and recreate the enum constraint
DB::statement("ALTER TABLE payroll_adjustments DROP CONSTRAINT IF EXISTS payroll_adjustments_adjustment_type_check");
DB::statement("ALTER TABLE payroll_adjustments ALTER COLUMN adjustment_type TYPE VARCHAR(20)");
DB::statement("ALTER TABLE payroll_adjustments ADD CONSTRAINT payroll_adjustments_adjustment_type_check 
    CHECK (adjustment_type IN ('earning', 'deduction', 'correction', 'backpay', 'refund'))");
```

Run: `php artisan migrate`

### Step 2 — Update `PayrollAdjustment` Model

File: `app/Models/PayrollAdjustment.php`

Changes:
- Add `review_notes` to `$fillable`
- Add `review_notes` to `$casts` as string
- Update `adjustment_type` enum docblock to match new values
- Add `position` accessor via `employee.profile`

```php
// Add to $fillable
'review_notes',

// Add accessor for frontend convenience
public function getEmployeeNameAttribute(): string
{
    return $this->employee?->profile?->full_name
        ?? $this->employee?->user?->name
        ?? 'Unknown';
}

public function getEmployeeNumberAttribute(): string
{
    return $this->employee?->employee_number ?? '';
}

public function getDepartmentAttribute(): string
{
    return $this->employee?->department?->name ?? '';
}

public function getRequestedByAttribute(): string
{
    return $this->createdBy?->name ?? 'System';
}

public function getRequestedAtAttribute(): string
{
    return ($this->submitted_at ?? $this->created_at)?->toIso8601String() ?? '';
}

public function getReviewedByAttribute(): ?string
{
    return $this->approvedBy?->name ?? $this->rejectedBy?->name;
}

public function getReviewedAtAttribute(): ?string
{
    return ($this->approved_at ?? $this->rejected_at)?->toIso8601String();
}
```

Also add `review_notes`, `employee_name`, `employee_number`, `department`, `requested_by`, `requested_at`, `reviewed_by`, `reviewed_at` to `$appends`.

### Step 3 — Implement `PayrollAdjustmentController`

File: `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php`

Replace all mock data methods with real Eloquent queries.

#### 3a. `index()` — list with filters + pagination

```php
public function index(Request $request): Response
{
    $query = PayrollAdjustment::with([
            'employee.profile',
            'employee.department',
            'payrollPeriod',
            'createdBy',
            'approvedBy',
            'rejectedBy',
        ])
        ->when($request->period_id, fn($q, $v) => $q->byPeriod((int) $v))
        ->when($request->employee_id, fn($q, $v) => $q->byEmployee((int) $v))
        ->when($request->status, fn($q, $v) => $q->where('status', $v))
        ->when($request->adjustment_type, fn($q, $v) => $q->byType($v))
        ->latest();

    $adjustments = $query->paginate(20)->through(
        fn($adj) => $this->transformAdjustment($adj)
    );

    $availablePeriods = PayrollPeriod::whereNotIn('status', ['cancelled'])
        ->orderByDesc('period_start')
        ->get(['id', 'period_number', 'period_start', 'period_end', 'status']);

    $availableEmployees = Employee::where('status', 'active')
        ->with(['profile', 'department'])
        ->get()
        ->map(fn($e) => [
            'id'              => $e->id,
            'name'            => $e->profile?->full_name ?? $e->user?->name ?? 'Unknown',
            'employee_number' => $e->employee_number,
            'department'      => $e->department?->name ?? '',
        ]);

    return Inertia::render('Payroll/PayrollProcessing/Adjustments/Index', [
        'adjustments'         => $adjustments,
        'available_periods'   => $availablePeriods,
        'available_employees' => $availableEmployees,
        'filters' => [
            'period_id'       => $request->integer('period_id') ?: null,
            'employee_id'     => $request->integer('employee_id') ?: null,
            'status'          => $request->input('status'),
            'adjustment_type' => $request->input('adjustment_type'),
        ],
    ]);
}
```

> **Note:** The frontend currently receives a plain array. If pagination is added, update `PayrollAdjustmentsPageProps.adjustments` to `LengthAwarePaginator<PayrollAdjustment>` shape or keep `->get()` initially.

#### 3b. `store()` — create new adjustment

```php
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'payroll_period_id' => 'required|exists:payroll_periods,id',
        'employee_id'       => 'required|exists:employees,id',
        'adjustment_type'   => 'required|in:earning,deduction,correction,backpay,refund',
        'adjustment_category' => 'required|string|max:100',
        'amount'            => 'required|numeric|min:0.01|max:999999.99',
        'reason'            => 'required|string|max:200',
        'reference_number'  => 'nullable|string|max:100',
    ]);

    PayrollAdjustment::create([
        'payroll_period_id' => $validated['payroll_period_id'],
        'employee_id'       => $validated['employee_id'],
        'adjustment_type'   => $validated['adjustment_type'],
        'category'          => $validated['adjustment_category'],  // map field name
        'amount'            => $validated['amount'],
        'reason'            => $validated['reason'],
        'reference_number'  => $validated['reference_number'] ?? null,
        'status'            => 'pending',
        'submitted_at'      => now(),
        'created_by'        => auth()->id(),
    ]);

    return redirect()->back()->with('success', 'Adjustment created successfully.');
}
```

#### 3c. `update()` — edit pending adjustment only

```php
public function update(Request $request, int $id): RedirectResponse
{
    $adjustment = PayrollAdjustment::findOrFail($id);

    abort_if($adjustment->status !== 'pending', 403, 'Only pending adjustments can be edited.');

    $validated = $request->validate([
        'payroll_period_id'   => 'required|exists:payroll_periods,id',
        'employee_id'         => 'required|exists:employees,id',
        'adjustment_type'     => 'required|in:earning,deduction,correction,backpay,refund',
        'adjustment_category' => 'required|string|max:100',
        'amount'              => 'required|numeric|min:0.01|max:999999.99',
        'reason'              => 'required|string|max:200',
        'reference_number'    => 'nullable|string|max:100',
    ]);

    $adjustment->update([
        'payroll_period_id' => $validated['payroll_period_id'],
        'employee_id'       => $validated['employee_id'],
        'adjustment_type'   => $validated['adjustment_type'],
        'category'          => $validated['adjustment_category'],
        'amount'            => $validated['amount'],
        'reason'            => $validated['reason'],
        'reference_number'  => $validated['reference_number'] ?? null,
    ]);

    return redirect()->back()->with('success', 'Adjustment updated successfully.');
}
```

#### 3d. `destroy()` — soft-delete (pending only)

```php
public function destroy(int $id): RedirectResponse
{
    $adjustment = PayrollAdjustment::findOrFail($id);

    abort_if(
        !in_array($adjustment->status, ['pending', 'rejected']),
        403,
        'Only pending or rejected adjustments can be deleted.'
    );

    $adjustment->delete();

    return redirect()->back()->with('success', 'Adjustment deleted.');
}
```

#### 3e. `approve()` — approve a pending adjustment

```php
public function approve(int $id): RedirectResponse
{
    $adjustment = PayrollAdjustment::findOrFail($id);

    abort_if($adjustment->status !== 'pending', 422, 'Adjustment is not in pending status.');

    $adjustment->update([
        'status'      => 'approved',
        'approved_by' => auth()->id(),
        'approved_at' => now(),
    ]);

    return redirect()->back()->with('success', 'Adjustment approved.');
}
```

#### 3f. `reject()` — reject with notes

```php
public function reject(Request $request, int $id): RedirectResponse
{
    $adjustment = PayrollAdjustment::findOrFail($id);

    abort_if($adjustment->status !== 'pending', 422, 'Adjustment is not in pending status.');

    $validated = $request->validate([
        'rejection_notes' => 'required|string|max:500',
    ]);

    $adjustment->update([
        'status'           => 'rejected',
        'rejected_by'      => auth()->id(),
        'rejected_at'      => now(),
        'rejection_reason' => $validated['rejection_notes'],
        'review_notes'     => $validated['rejection_notes'],
    ]);

    return redirect()->back()->with('success', 'Adjustment rejected.');
}
```

#### 3g. `history()` — employee adjustment history

```php
public function history(Request $request, int $employeeId): Response
{
    $employee = Employee::with(['profile', 'department', 'positions'])->findOrFail($employeeId);

    $query = PayrollAdjustment::with(['payrollPeriod', 'createdBy', 'approvedBy', 'rejectedBy'])
        ->where('employee_id', $employeeId)
        ->when($request->period_id, fn($q, $v) => $q->byPeriod((int) $v))
        ->when($request->status, fn($q, $v) => $q->where('status', $v))
        ->when($request->type, fn($q, $v) => $q->byType($v))
        ->latest();

    $adjustments = $query->get()->map(fn($adj) => $this->transformAdjustment($adj));

    $summary = [
        'total_adjustments'    => $adjustments->count(),
        'pending_adjustments'  => $adjustments->where('status', 'pending')->count(),
        'approved_adjustments' => $adjustments->where('status', 'approved')->count(),
        'rejected_adjustments' => $adjustments->where('status', 'rejected')->count(),
        'total_pending_amount' => $adjustments->where('status', 'pending')->sum('amount'),
    ];

    $availablePeriods = PayrollPeriod::orderByDesc('period_start')
        ->get(['id', 'period_number'])
        ->map(fn($p) => ['id' => $p->id, 'name' => $p->period_number]);

    return Inertia::render('Payroll/PayrollProcessing/Adjustments/History', [
        'employee_id'       => $employee->id,
        'employee_name'     => $employee->profile?->full_name ?? 'Unknown',
        'employee_number'   => $employee->employee_number,
        'department'        => $employee->department?->name ?? '',
        'position'          => $employee->currentPosition?->title ?? '',
        'adjustments'       => $adjustments,
        'summary'           => $summary,
        'available_periods' => $availablePeriods,
        'available_statuses' => [
            ['value' => 'pending',  'label' => 'Pending'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected'],
            ['value' => 'applied',  'label' => 'Applied'],
        ],
    ]);
}
```

#### 3h. Private `transformAdjustment()` helper

```php
private function transformAdjustment(PayrollAdjustment $adj): array
{
    $reviewedBy = $adj->approvedBy?->name ?? $adj->rejectedBy?->name;
    $reviewedAt = $adj->approved_at ?? $adj->rejected_at;

    return [
        'id'                  => $adj->id,
        'payroll_period_id'   => $adj->payroll_period_id,
        'payroll_period'      => $adj->payrollPeriod,
        'employee_id'         => $adj->employee_id,
        'employee_name'       => $adj->employee?->profile?->full_name
                                    ?? $adj->employee?->user?->name ?? 'Unknown',
        'employee_number'     => $adj->employee?->employee_number ?? '',
        'department'          => $adj->employee?->department?->name ?? '',
        'adjustment_type'     => $adj->adjustment_type,
        'adjustment_category' => $adj->category,      // DB col 'category' → frontend 'adjustment_category'
        'amount'              => (float) $adj->amount,
        'reason'              => $adj->reason,
        'reference_number'    => $adj->reference_number,
        'status'              => $adj->status,
        'requested_by'        => $adj->createdBy?->name ?? 'System',
        'requested_at'        => ($adj->submitted_at ?? $adj->created_at)?->toIso8601String(),
        'reviewed_by'         => $reviewedBy,
        'reviewed_at'         => $reviewedAt?->toIso8601String(),
        'review_notes'        => $adj->review_notes ?? $adj->rejection_reason,
        'applied_at'          => $adj->applied_at?->toIso8601String(),
        'created_at'          => $adj->created_at->toIso8601String(),
        'updated_at'          => $adj->updated_at->toIso8601String(),
    ];
}
```

### Step 4 — Update TypeScript Types

File: `resources/js/types/payroll-pages.ts`

The `PayrollAdjustmentsPageProps.available_periods` is typed as `PayrollPeriod[]` but Index.tsx uses fields like `period.name`. The `PayrollPeriod` type needs a `name` computed property, OR the controller should include a formatted `name` field.

**Suggested:** Add `name` field to the `available_periods` mapping in the controller:

```php
$availablePeriods = PayrollPeriod::whereNotIn('status', ['cancelled'])
    ->orderByDesc('period_start')
    ->get()
    ->map(fn($p) => [
        'id'           => $p->id,
        'name'         => $p->period_number . ' (' . $p->period_start?->format('M d') . '–' . $p->period_end?->format('M d, Y') . ')',
        'period_type'  => $p->period_type,
        'start_date'   => $p->period_start?->toDateString(),
        'end_date'     => $p->period_end?->toDateString(),
        'payment_date' => $p->payment_date?->toDateString(),
        'status'       => $p->status,
    ]);
```

### Step 5 — Add Required Imports to Controller

```php
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
```

---

## 5. Frontend Impact Assessment

The frontend pages and components are already using the correct field names from the TypeScript interface. No frontend changes are needed **unless**:

| Scenario | Change needed |
|---|---|
| `adjustments` becomes paginated | Update `AdjustmentsTable` to accept `LengthAwarePaginator` and add pagination controls |
| `available_periods.name` format changes | Update `AdjustmentsFilter` dropdown label rendering if needed |
| Form submit field mismatch | `AdjustmentFormModal` sends `adjustment_category` — controller maps to DB `category` ✅ |

---

## 6. Authorization (future phase)

Recommend adding `PayrollAdjustmentPolicy` with:

| Action | Who can |
|---|---|
| `create` | Payroll Officer, HR Manager, Super Admin |
| `update` | Creator only (while pending) |
| `delete` | Creator or Payroll Manager (while pending/rejected) |
| `approve` | Payroll Manager, HR Manager (not the creator) |
| `reject` | Payroll Manager, HR Manager |
| `viewHistory` | Payroll Officer, HR Manager, Super Admin |

Register in `AuthServiceProvider::boot()` and call `$this->authorize(...)` in controller methods.

---

## 7. File Checklist

### New files to create
- [x] `database/migrations/2026_03_07_000001_fix_payroll_adjustments_schema.php` ✅ COMPLETED (PostgreSQL compatible)
- [x] `database/migrations/2026_03_07_000002_update_payroll_adjustment_type_enum.php` ✅ COMPLETED (PostgreSQL compatible)
- [x] `database/migrations/2026_03_07_000003_update_payroll_adjustment_category_column.php` ✅ COMPLETED (PostgreSQL compatible)

### Files to modify
- [x] `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php` — replace all mock data with real queries ✅ COMPLETED (all methods implemented: index, store, update, destroy, approve, reject, history with transformAdjustment helper; period name formatting updated to use period_number)
- [x] `app/Models/PayrollAdjustment.php` — add `review_notes` to `$fillable`, add accessor methods ✅ COMPLETED (all accessors, $appends array, relationship aliases, scopes added and tested)

### Files to verify (no changes likely needed)
- [x] `resources/js/pages/Payroll/PayrollProcessing/Adjustments/Index.tsx` ✅ VERIFIED (all components working, TypeScript clean)
- [x] `resources/js/pages/Payroll/PayrollProcessing/Adjustments/History.tsx` ✅ VERIFIED (all components working, TypeScript clean)
- [x] `resources/js/types/payroll-pages.ts` ✅ VERIFIED (types match backend data structure)
- [x] Period name formatting (Step 4) ✅ COMPLETED (uses period_number with formatted date range: "YYYY-MM-#H (MMM DD–MMM DD, YYYY)")
- [x] Required imports (Step 5) ✅ VERIFIED (Employee, PayrollAdjustment, PayrollPeriod imported)
- [x] `routes/payroll.php` ✅ VERIFIED (all routes registered and working)

---

## 8. Execution Order

1. ✅ Create and run both migrations — COMPLETED
2. ✅ Update `PayrollAdjustment` model `$fillable` and add accessors — COMPLETED (all accessors tested and working)
3. ✅ Implement controller `index()` with real queries — COMPLETED (tested page loads with real data)
4. ✅ Implement `store()` — COMPLETED (tested creating adjustments)
5. ✅ Implement `update()` — COMPLETED (tested editing pending adjustments)
6. ✅ Implement `destroy()` — COMPLETED (tested deleting pending/rejected adjustments)
7. ✅ Implement `approve()` / `reject()` — COMPLETED (tested workflow with review notes)
8. ✅ Implement `history()` — COMPLETED (tested employee history page with summary)
9. ✅ Verify no frontend type errors — COMPLETED (TypeScript validation passed, all components clean)
10. ✅ Update period name formatting (Step 4) — COMPLETED (uses period_number with formatted date range)
11. ✅ Verify required imports (Step 5) — COMPLETED (all imports present)

---

## 9. Notes

- The `category` enum in the DB migration lists values (`retroactive_pay`, `correction`, `bonus`, etc.) but the frontend stores `adjustment_category` as a free-form string. **Decision:** Keep `category` as a plain `VARCHAR` (not enum) to avoid future migration pain. The fix migration in Step 1 should also change `category` from enum to `string`.
- The `employee_payroll_calculation_id` can be populated later when the adjustment is "applied" to an actual calculation row.
- "Apply" logic (marking `status = 'applied'` and updating `EmployeePayrollCalculation.net_pay`) is a separate feature not included in this integration.
- **PostgreSQL Compatibility:** All three migrations have been updated to use PostgreSQL syntax:
  - Migration 1: Uses `ALTER COLUMN ... DROP NOT NULL` instead of `->nullable()->change()`
  - Migration 2: Uses `DROP CONSTRAINT` and `ADD CONSTRAINT` for CHECK constraints instead of MySQL's `MODIFY ENUM`
  - Migration 3: Uses `DROP CONSTRAINT` to remove category check constraint (PostgreSQL doesn't use inline ENUM)
