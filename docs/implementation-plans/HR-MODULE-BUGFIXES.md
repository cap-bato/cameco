# HR Module Bug Fixes — Implementation Plan

**Date:** 2026-03-19
**Bugs:** 10 confirmed issues across Employee, Department, Position, Leave, Document, and Workforce modules

---

## Summary Table

| # | Module | Description | Severity | Root Cause |
|---|--------|-------------|----------|-----------|
| 1 | Employees | Create employee → 500 error, silent | 🔴 High | `catch (\Exception)` misses `\Error` / `\Throwable` subclasses |
| 2 | Departments | Duplicate name → raw DB error | 🟠 Medium | Missing `unique` rule in `StoreDepartmentRequest` and `UpdateDepartmentRequest` |
| 3 | Departments | Add Child — parent not pre-filled | 🟡 Low | Modal's `useEffect` resets `parent_id: ''` unconditionally in create mode |
| 4 | Positions | Create → raw DB error (level field) | 🔴 High | `level` column is NOT NULL but missing from validation and mapped insert |
| 5 | Positions | Duplicate name → raw DB error | 🟠 Medium | Missing `unique` rule in `StorePositionRequest` and `UpdatePositionRequest` |
| 6 | Leave Requests | Create → 500 + "Undefined array key message" | 🔴 High | `$route['message']` accessed without null-safe check |
| 7 | Leave Balances | Column sort headers don't work | 🟠 Medium | Sort params not passed to backend; client sort only operates on current page |
| 8 | Documents | Summary cards stuck at 0 | 🟠 Medium | Controller never computes or returns `meta` summary stats |
| 9 | Shift Assignments | Department column always blank | 🟠 Medium | `department_id` not sent from form; `getAssignments()` may not eager-load `department` |
| 10 | Shift Assignments | New assignment doesn't appear after save | 🟠 Medium | `store()` has no error handling; `getAssignments()` may apply a date filter that excludes new rows |

---

## Phase 1 — Backend Validation Fixes

**Scope:** Four one-file changes to request classes and one controller method. No frontend changes needed.

---

### Task 1.1 — Bug 2: Add unique constraint to Department requests

**Files:**
- `app/Http/Requests/HR/StoreDepartmentRequest.php`
- `app/Http/Requests/HR/UpdateDepartmentRequest.php`

**Current (broken):**
```php
// StoreDepartmentRequest.php line 17
'name' => ['required', 'string', 'max:150'],

// UpdateDepartmentRequest.php line 17
'name' => ['required', 'string', 'max:150'],
```

**Fix — `StoreDepartmentRequest.php`:**
```php
public function rules(): array
{
    return [
        'name'        => ['required', 'string', 'max:150', 'unique:departments,name'],
        'code'        => ['nullable', 'string', 'max:32', 'unique:departments,code'],
        'description' => ['nullable', 'string', 'max:1000'],
        'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
        'is_active'   => ['boolean'],
    ];
}
```

**Fix — `UpdateDepartmentRequest.php`:**
The update must exclude the current record from the unique check. Add `routeParameter()`:
```php
public function rules(): array
{
    $id = $this->route('department')?->id ?? $this->route('id');

    return [
        'name'        => ['required', 'string', 'max:150', "unique:departments,name,{$id}"],
        'code'        => ['nullable', 'string', 'max:32', "unique:departments,code,{$id}"],
        'description' => ['nullable', 'string', 'max:1000'],
        'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
        'is_active'   => ['boolean'],
    ];
}
```

---

### Task 1.2 — Bug 4: Add `level` field to Position validation and insert

**Problem:** The `positions` table has a `level` column that is `NOT NULL` (or has no default). `PositionController::store()` builds `$mapped` without it, so `Position::create($mapped)` throws a raw DB integrity error.

**Step A — Check migration for `level` column:**
```bash
# Find the positions migration and confirm:
grep -n "level" database/migrations/*positions*
```
If `level` has `->default(null)` or is `nullable()`, the DB error won't occur. If it's plain `->string('level')` or `->enum('level', [...])`, it requires a value.

**Step B — Add `level` to `StorePositionRequest`:**
```php
// app/Http/Requests/HR/StorePositionRequest.php
public function rules(): array
{
    return [
        'title'       => ['required', 'string', 'max:150', 'unique:positions,title'],
        'code'        => ['nullable', 'string', 'max:32'],
        'description' => ['nullable', 'string', 'max:1000'],
        'level'       => ['nullable', 'string', 'max:50'],   // ← add this
        'department_id' => ['required', 'integer', 'exists:departments,id'],
        'reports_to'  => ['nullable', 'integer', 'exists:positions,id'],
        'salary_min'  => ['nullable', 'integer', 'min:0'],
        'salary_max'  => ['nullable', 'integer', 'gte:salary_min'],
        'is_active'   => ['boolean'],
    ];
}
```

**Step C — Map `level` in `PositionController::store()`:**
```php
// app/Http/Controllers/HR/Employee/PositionController.php — store() method
$mapped = [
    'title'         => $data['title'],
    'code'          => $data['code'] ?? null,
    'description'   => $data['description'] ?? null,
    'level'         => $data['level'] ?? null,          // ← add this line
    'department_id' => $data['department_id'],
    'reports_to'    => $data['reports_to'] ?? null,
    'min_salary'    => $data['salary_min'] ?? null,
    'max_salary'    => $data['salary_max'] ?? null,
    'is_active'     => $data['is_active'] ?? true,
];
```

**Step D — Also map `level` in `PositionController::update()`:**
```php
// Same file — update() method, $mapped array
'level' => $data['level'] ?? $position->level,
```

If the `level` column has no default and is required, also add it to the position creation form in `resources/js/pages/HR/Positions/` (see Task 3.1).

---

### Task 1.3 — Bug 5: Add unique constraint to Position requests

**File:** `app/Http/Requests/HR/StorePositionRequest.php`

Already included in Task 1.2 Step B: `'title' => ['required', 'string', 'max:150', 'unique:positions,title']`.

**Also fix `UpdatePositionRequest.php`:**
```php
// app/Http/Requests/HR/UpdatePositionRequest.php
public function rules(): array
{
    $id = $this->route('position')?->id ?? $this->route('id');

    return [
        'title'       => ['required', 'string', 'max:150', "unique:positions,title,{$id}"],
        'code'        => ['nullable', 'string', 'max:32'],
        'description' => ['nullable', 'string', 'max:1000'],
        'level'       => ['nullable', 'string', 'max:50'],
        'department_id' => ['required', 'integer', 'exists:departments,id'],
        'reports_to'  => ['nullable', 'integer', 'exists:positions,id'],
        'salary_min'  => ['nullable', 'integer', 'min:0'],
        'salary_max'  => ['nullable', 'integer', 'gte:salary_min'],
        'is_active'   => ['boolean'],
    ];
}
```

---

### Task 1.4 — Bug 6: Fix "Undefined array key message" in LeaveRequestController

**File:** `app/Http/Controllers/HR/Leave/LeaveRequestController.php:391`

**Current (broken):**
```php
return redirect()->route('hr.leave.requests')
    ->with('success', "...has been submitted successfully. {$route['message']}");
```

`$this->approvalService->determineApprovalRoute()` does not always return a `'message'` key.

**Fix:**
```php
// Line 390-392
$routeMessage = $route['message'] ?? 'Your request has been submitted for approval.';

return redirect()->route('hr.leave.requests')
    ->with('success', "Leave request for {$employee->profile->first_name} {$employee->profile->last_name} has been submitted successfully. {$routeMessage}");
```

---

## Phase 2 — Employee Creation 500 Error

### Task 2.1 — Bug 1: Widen the catch block in `EmployeeService`

**File:** `app/Services/HR/EmployeeService.php:185`

**Current (broken):**
```php
} catch (\Exception $e) {
    DB::rollBack();
    ...
    return ['success' => false, 'message' => 'Employee creation failed: ' . $e->getMessage()];
}
```

`\Exception` does not catch PHP `\Error` subtypes (e.g., `TypeError`, `ValueError`, `ArithmeticError`). If any code inside the `try` block triggers a type error — such as passing a `string` to a parameter expecting `int`, or calling a method on `null` — the error escapes the catch block, the transaction is never rolled back, and PHP throws a 500 with no user-facing message.

**Fix:**
```php
} catch (\Throwable $e) {    // ← was \Exception
    DB::rollBack();

    Log::error('Employee creation failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);

    return [
        'success' => false,
        'message' => 'Employee creation failed: ' . $e->getMessage(),
    ];
}
```

This catches both `\Exception` and `\Error` (and all their subclasses), ensuring the transaction is always rolled back and the controller always receives a structured result instead of a thrown error.

---

## Phase 3 — Frontend Form Fixes

### Task 3.1 — Bug 3: Pre-fill parent department when adding a child

**File:** `resources/js/components/hr/department-form-modal.tsx:78–86`

**Current (broken):**
```tsx
// useEffect lines 78-87
} else {
    // Reset form for create mode
    setFormData({
        name: '',
        code: '',
        description: '',
        parent_id: '',         // ← always resets to empty, ignoring passed department.parent_id
        is_active: true,
    });
}
```

When `handleAddChildClick` is called in `Departments/Index.tsx`, it sets:
```tsx
setSelectedDepartment({
    ...parentDept,
    id: 0,                    // fake id signals "new"
    parent_id: parentDept.id, // the parent we want pre-filled
});
setModalMode('create');
```

But the modal's `useEffect` is in create mode, so it runs the `else` branch and resets `parent_id` to `''`.

**Fix:**
```tsx
} else {
    setFormData({
        name: '',
        code: '',
        description: '',
        // Pre-fill parent_id if passed (e.g. from "Add Child" action)
        parent_id: department?.parent_id ? String(department.parent_id) : '',
        is_active: true,
    });
}
```

No backend change needed — the parent_id is already included in the form submission.

---

### Task 3.2 — Bug 4 (frontend part): Add `level` field to Position creation form

**File:** `resources/js/pages/HR/Positions/` — Create and Edit form components

If the `level` column is required in the DB, the frontend form must include the field. Add a text input or select for `level` (e.g., `junior`, `mid`, `senior`, `lead`, `manager`) to the position form so users can provide a value.

> Coordinate with the actual enum values used in the database migration.

---

## Phase 4 — Leave Balances Sorting

### Task 4.1 — Bug 7: Implement server-side sorting for leave balances

**Problem:** The balance list is server-paginated (groups of 25 employees per page). Client-side sorting only reorders the current page — clicking "Name" while on page 2 sorts page 2's names, not all employees. The sort needs to be passed to the server.

**Step A — `LeaveBalanceController::index()` — accept and apply sort params:**

```php
// app/Http/Controllers/HR/Leave/LeaveBalanceController.php
public function index(Request $request): Response
{
    $selectedYear  = $request->input('year', now()->year);
    $employeeId    = $request->input('employee_id');
    $perPage       = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100])
                     ? (int) $request->input('per_page', 25) : 25;
    $page          = (int) $request->input('page', 1);
    $sortBy        = $request->input('sort_by', 'name');        // ← new
    $sortDirection = $request->input('sort_direction', 'asc');  // ← new

    // ... (existing query unchanged) ...

    // Sort $groupedBalances before pagination
    $groupedBalances = $groupedBalances->sortBy(function ($item) use ($sortBy) {
        return match ($sortBy) {
            'name'       => $item['name'],
            'department' => $item['department'],
            'number'     => $item['employee_number'],
            default      => $item['name'],
        };
    }, SORT_REGULAR, $sortDirection === 'desc');    // ← sort before ->forPage()

    $paginated = new LengthAwarePaginator(
        $groupedBalances->forPage($page, $perPage)->values(),
        $groupedBalances->count(),
        $perPage,
        $page,
        ['path' => route('hr.leave.balances'), 'query' => $request->query()]
    );

    return Inertia::render('HR/Leave/Balances', [
        // ... existing props ...
        'sortBy'        => $sortBy,           // ← pass back to frontend
        'sortDirection' => $sortDirection,    // ← pass back to frontend
    ]);
}
```

**Step B — `resources/js/pages/HR/Leave/Balances.tsx` — make column headers trigger server sort:**

Replace the client-side sort logic with a server-driven approach. When a column header is clicked, append `sort_by` and `sort_direction` to the current URL using Inertia's router:

```tsx
// Add to props interface:
sortBy: string;
sortDirection: 'asc' | 'desc';

// Replace local sort state with a handler that calls the server:
const handleSort = (column: string) => {
    const newDirection =
        sortBy === column && sortDirection === 'asc' ? 'desc' : 'asc';

    router.get(
        route('hr.leave.balances'),
        { ...filters, sort_by: column, sort_direction: newDirection, page: 1 },
        { preserveState: true, replace: true }
    );
};
```

Use `handleSort('name')`, `handleSort('department')` etc. on each `<TableHead onClick>`.

---

## Phase 5 — Document Summary Cards

### Task 5.1 — Bug 8: Compute and return summary stats in EmployeeDocumentController

**File:** `app/Http/Controllers/HR/Documents/EmployeeDocumentController.php`

**Current:** The `index()` method returns `documents`, `filters`, `employees`, `categories` but no `meta` object. The frontend reads `meta.total`, `meta.pending_approvals`, `meta.expiring_soon`, `meta.recently_uploaded` which are all `undefined`.

**Fix — add `meta` calculation before the `return Inertia::render(...)` call:**

```php
// Add inside index(), after the $documents query, before the return statement:

// Compute summary stats from the full (unfiltered) table for the summary cards.
// These counts are global totals, not filtered-page counts.
$meta = [
    'total'             => \App\Models\EmployeeDocument::count(),
    'pending_approvals' => \App\Models\EmployeeDocument::where('status', 'pending')->count(),
    'expiring_soon'     => \App\Models\EmployeeDocument::whereNotNull('expires_at')
                               ->where('expires_at', '>', now())
                               ->where('expires_at', '<=', now()->addDays(30))
                               ->count(),
    'recently_uploaded' => \App\Models\EmployeeDocument::where('uploaded_at', '>=', now()->subDays(7))
                               ->count(),
];

return Inertia::render('HR/Documents/Index', [
    'documents'  => $documents,
    'filters'    => $filters,
    'employees'  => $employees,
    'categories' => $categories,
    'meta'       => $meta,           // ← add this
]);
```

> **Note:** The `ilike` operator used in the search filter (lines 47–52) is PostgreSQL-specific and will fail on SQLite/MySQL. Replace with `LOWER() LIKE` or `->where('column', 'like', ...)`:
> ```php
> // Replace ilike with cross-DB compatible:
> $q->whereRaw('LOWER(document_type) LIKE ?', ['%' . strtolower($search) . '%'])
>   ->orWhereRaw('LOWER(file_name) LIKE ?', ['%' . strtolower($search) . '%'])
> ```

---

## Phase 6 — Shift Assignment Fixes

### Task 6.1 — Bug 9: Show department in the assignments table

**Problem A — `department_id` is not sent from the creation form.**
`CreateEditAssignmentModal.tsx` initialises `formData` without `department_id`. When an employee is selected in the modal, `department_id` should be automatically populated from that employee's department.

**Fix — `CreateEditAssignmentModal.tsx` — when employee changes, sync department:**
```tsx
// Inside the employee select onChange handler, after setting employee_id:
const handleEmployeeChange = (employeeId: string) => {
    const emp = employees.find(e => String(e.id) === employeeId);
    setFormData(prev => ({
        ...prev,
        employee_id: emp?.id,
        department_id: emp?.department_id,   // ← auto-fill from employee
    }));
};
```

Also add `department_id` to the initial `formData` state so it's included in the submitted payload:
```tsx
const [formData, setFormData] = useState<...>({
    employee_id:   undefined,
    schedule_id:   undefined,
    department_id: undefined,    // ← add this
    date:          new Date().toISOString().split('T')[0],
    shift_start:   '06:00:00',
    shift_end:     '14:00:00',
    location:      '',
    is_overtime:   false,
    notes:         '',
});
```

**Problem B — `getAssignments()` may not eager-load the `department` relationship.**
The `AssignmentController::index()` transforms `$assignment->department?->name` (line 62). If `ShiftAssignmentService::getAssignments()` does not eager-load the `department` relationship, this either causes N+1 queries or returns `null`.

**Fix — `ShiftAssignmentService::getAssignments()`:**
```php
public function getAssignments(): Collection
{
    return ShiftAssignment::with([
        'employee.profile',
        'employee.department',
        'schedule',
        'department',          // ← ensure this is eager-loaded
    ])
    ->whereNull('deleted_at')
    ->latest('date')
    ->get();
}
```

---

### Task 6.2 — Bug 10: New assignment doesn't appear after save

**Problem 1 — `AssignmentController::store()` has no error handling.**
If `createAssignment()` throws (e.g., a required `shift_type` column is missing from the payload), the exception is unhandled, causes a 500, but the redirect appears to work from the frontend's perspective (Inertia shows the index page from the previous load). No row is written.

**Fix — wrap store() in try/catch:**
```php
// app/Http/Controllers/HR/Workforce/AssignmentController.php
public function store(StoreShiftAssignmentRequest $request)
{
    try {
        $this->shiftAssignmentService->createAssignment(
            $request->validated(),
            auth()->user()
        );
    } catch (\Throwable $e) {
        \Log::error('Shift assignment creation failed', [
            'data'  => $request->validated(),
            'error' => $e->getMessage(),
        ]);

        return back()
            ->withInput()
            ->withErrors(['error' => 'Failed to create assignment: ' . $e->getMessage()]);
    }

    return redirect()->route('hr.workforce.assignments.index')
        ->with('success', 'Shift assignment created successfully.');
}
```

**Problem 2 — `getAssignments()` may apply a date filter that hides new rows.**
Inspect `ShiftAssignmentService::getAssignments()`. If it filters by current week/month (e.g., `->where('date', '>=', now()->startOfWeek())`), assignments created for future dates won't appear.

**Fix — remove restrictive date filter from `getAssignments()` or accept date range params:**
```php
public function getAssignments(?string $dateFrom = null, ?string $dateTo = null): Collection
{
    $query = ShiftAssignment::with(['employee.profile', 'employee.department', 'schedule', 'department'])
        ->whereNull('deleted_at');

    // Only filter by date if explicitly requested
    if ($dateFrom) {
        $query->where('date', '>=', $dateFrom);
    }
    if ($dateTo) {
        $query->where('date', '<=', $dateTo);
    }

    return $query->latest('date')->get();
}
```

Update the `AssignmentController::index()` call accordingly:
```php
$assignments = $this->shiftAssignmentService->getAssignments(
    $request->input('date_from'),
    $request->input('date_to')
);
```

**Problem 3 — `handleSaveAssignment` in `Index.tsx` closes modal before request completes.**
```tsx
// Current (Index.tsx lines 167-174):
router.post('/hr/workforce/assignments', data as never);
handleCloseModal();  // ← called immediately, not in onSuccess
```

This doesn't affect whether the page refreshes (Inertia handles that via the redirect), but it means the modal closes before errors can be shown. Move close to the `onSuccess` callback:

```tsx
const handleSaveAssignment = (data: Record<string, unknown>) => {
    if (editingAssignment?.id) {
        router.put(`/hr/workforce/assignments/${editingAssignment.id}`, data as never, {
            onSuccess: handleCloseModal,
        });
    } else {
        router.post('/hr/workforce/assignments', data as never, {
            onSuccess: handleCloseModal,
        });
    }
};
```

---

## Acceptance Criteria

### Phase 1
- [x] **Task 1.1 — DONE** Creating a department with a duplicate name shows "The name has already been taken." inline — no raw DB error
- [x] **Task 1.1 — DONE** Editing a department to a duplicate name shows the same validation message
- [x] **Task 1.2 & 1.3 — DONE** Creating a position with a duplicate title shows "The title has already been taken."
- [x] **Task 1.2 — DONE** Position `level` field now required and validated (fixes null constraint violation)
- [x] **Task 1.4 — DONE** Creating a leave request redirects to the list with a success message — no 500, no "Undefined array key" error

### Phase 2
- [x] **Task 2.1 — DONE** Creating an employee with valid data succeeds — no 500 error in the console
- [ ] If employee creation fails (e.g., duplicate email), a user-friendly error appears on the form
- [ ] The transaction is always rolled back on failure (no orphaned profile rows)

### Phase 3
- [x] **Task 3.1 — DONE** Clicking "Add Child" next to any department opens the modal with the Parent Department field pre-selected to that department
- [x] **Task 3.2 — DONE** Added `level` field to Position creation/edit form — now required and visible to users

### Phase 4
- [x] **Task 4.1 — DONE** Clicking any sortable column header on the Leave Balances page sorts all pages, not just the current one
- [x] **Task 4.1 — DONE** Clicking the same header again reverses the sort direction
- [x] **Task 4.1 — DONE** Sort state persists across page navigation

### Phase 5
- [x] **Task 5.1 — DONE** After uploading a document, the "Total Documents" card shows accurate count on next page load
- [x] **Task 5.1 — DONE** "Pending Approvals", "Expiring Soon", and "Recently Uploaded" cards show accurate live counts

### Phase 6
- [x] **Task 6.1 — DONE** The Department column in the Shift Assignments table is populated for every row
- [x] **Task 6.1 — DONE** Creating a new shift assignment via the modal automatically syncs department_id from the selected employee
- [x] **Task 6.2 — DONE** Creating a new shift assignment via the modal causes it to appear in the list after saving
- [x] **Task 6.2 — DONE** If creation fails, an error message is shown — no silent failure
- [ ] `php artisan test` passes
