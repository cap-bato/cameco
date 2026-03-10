# Mock Pages Wiring — Implementation Plan

**Pages covered:**
1. `/hr/documents/requests` — Document Requests (HR Document Hub)
2. `/hr/timekeeping/import` — Import Management (Attendance Bulk Import)
3. `/hr/reports/employees` — Employee Reports
4. `/hr/reports/leave` — Leave Reports

**Status:** In Progress  
**Date:** 2026-03-10

---

## § 1 — Current State Overview

| Page | Controller | State | Frontend |
|---|---|---|---|
| `/hr/documents/requests` | `DocumentRequestController::index()` | ❌ 100% MOCK — hardcoded array of 6 fake requests | ✅ Props-ready, has `fetchRequests()` JSON fallback |
| | `DocumentRequestController::process()` | ❌ MOCK — never updates DB | ✅ `ProcessRequestModal` wired |
| `/hr/timekeeping/import` | `ImportController::index()` | ❌ 100% MOCK — `getMockImportBatches()` | ✅ Props-ready; upload + retry are `console.log` only |
| | `ImportController::upload()` | ❌ PARTIAL — validates file but never creates `ImportBatch` row | |
| | `ImportController::errors()` | ❌ MOCK — `getMockImportErrors()` | |
| `/hr/reports/employees` | `ReportController::employees()` | ❌ 100% MOCK — hardcoded numbers; `recent_hires = []` | ✅ Defensive rendering |
| `/hr/reports/leave` | `ReportController::leave()` | ❌ 100% MOCK — hardcoded numbers | ✅ Defensive rendering |

### Database Tables Confirmed
| Table | Exists | Notes |
|---|---|---|
| `document_requests` | ✅ | No `priority` column; `status` enum only has `pending\|processed\|rejected` |
| `import_batches` | ✅ | No `warnings` column; no `imported_by_name` virtual column |
| `employees` | ✅ | Has `status`, `department_id`, `date_hired` |
| `departments` | ✅ | Has `name` |
| `employee_profiles` | ✅ | Has `first_name`, `last_name` |
| `leave_requests` | ✅ | Has `status`, `leave_policy_id`, `days_requested` |
| `leave_policies` | ✅ | Has `name` (the leave type name) |

### Schema Gaps (need migrations)
| Gap | Affects | Fix |
|---|---|---|
| `document_requests` has no `priority` column | Documents Request page shows priority badge | Add `priority` enum migration |
| `document_requests.status` enum: `pending\|processed\|rejected` | Frontend expects `pending\|processing\|completed\|rejected` | Widen enum via migration |
| `import_batches` has no `warnings` column | `ImportBatch` TypeScript type has `warnings` | Add column via migration |

---

## § 2 — Page 1: `/hr/documents/requests`

### Root Cause Analysis

`DocumentRequestController::index()` at
`app/Http/Controllers/HR/Documents/DocumentRequestController.php`
returns a hardcoded `collect([...])` with 6 fake arrays instead of querying `DocumentRequest::query()`.

`process()` validates the action but logs an audit and returns a response without ever calling
`DocumentRequest::find($id)->update(...)`.

**Additional schema issues:**

1. The frontend type `DocumentRequest.priority` (`urgent | high | normal`) has no corresponding DB column in `document_requests`.
2. The frontend expects `status: 'processing' | 'completed'` but the migration enum only has `pending | processed | rejected`.
3. `employee_name`, `employee_number`, `department` are not stored columns — they must be joined from `employees` → `employee_profiles` + `departments`.
4. The frontend's `fetchRequests()` call at line ~185 sends `Accept: application/json` — the controller must detect this and return JSON instead of Inertia.

---

### Phase 1-A — Migration: Add `priority` + fix `status` enum on `document_requests`

**New migration:** `database/migrations/2026_03_10_add_priority_to_document_requests.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add priority column
        Schema::table('document_requests', function (Blueprint $table) {
            $table->enum('priority', ['urgent', 'high', 'normal'])
                ->default('normal')
                ->after('purpose');
        });

        // Widen status: add 'processing' and 'completed' values
        // PostgreSQL: use ALTER TABLE ... ALTER COLUMN ... TYPE
        // MySQL: re-define the enum
        DB::statement("ALTER TABLE document_requests MODIFY COLUMN status ENUM('pending','processing','processed','completed','rejected') DEFAULT 'pending'");
        // NOTE for PostgreSQL: use DB::statement("ALTER TABLE document_requests ADD CONSTRAINT ... CHECK (status IN (...))")
        // Or use a string column if DB is PostgreSQL and doesn't support MODIFY COLUMN for enums.
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
        DB::statement("ALTER TABLE document_requests MODIFY COLUMN status ENUM('pending','processed','rejected') DEFAULT 'pending'");
    }
};
```

> **PostgreSQL note:** If using PostgreSQL, drop and recreate the check constraint instead of `MODIFY COLUMN`.
> Run `php artisan migrate` after creating the file.

---

### Phase 1-B — Wire `DocumentRequestController::index()` to real DB

**File:** `app/Http/Controllers/HR/Documents/DocumentRequestController.php`

Replace the fake `collect([...])` in `index()` with real queries:

```php
use App\Models\DocumentRequest;

public function index(Request $request)
{
    $this->authorize('viewAny', \App\Models\Employee::class);

    // Build query with employee + department joins
    $query = DocumentRequest::with([
        'employee.profile:id,first_name,last_name',
        'employee.department:id,name',
        'employee:id,employee_number,department_id',
        'processedBy.profile:id,first_name,last_name',
    ])
    ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
    ->when($request->filled('document_type'), fn($q) => $q->where('document_type', $request->document_type))
    ->when($request->filled('priority'), fn($q) => $q->where('priority', $request->priority))
    ->when($request->filled('date_from'), fn($q) => $q->where('requested_at', '>=', $request->date_from))
    ->when($request->filled('date_to'), fn($q) => $q->where('requested_at', '<=', $request->date_to . ' 23:59:59'))
    ->when($request->filled('search'), function ($q) use ($request) {
        $search = $request->search;
        $q->whereHas('employee', function ($eq) use ($search) {
            $eq->where('employee_number', 'like', "%{$search}%")
               ->orWhereHas('profile', fn($pq) => $pq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"));
        })
        ->orWhere('document_type', 'like', "%{$search}%");
    })
    ->orderByDesc('requested_at');

    $requests = $query->get()->map(function (DocumentRequest $req) {
        $profile = $req->employee?->profile;
        $processedByProfile = $req->processedBy?->profile;

        return [
            'id'                      => $req->id,
            'employee_id'             => $req->employee_id,
            'employee_name'           => $profile
                                         ? trim("{$profile->first_name} {$profile->last_name}")
                                         : 'Unknown',
            'employee_number'         => $req->employee?->employee_number ?? '—',
            'department'              => $req->employee?->department?->name ?? '—',
            'document_type'           => $req->document_type,
            'purpose'                 => $req->purpose,
            'priority'                => $req->priority ?? 'normal',
            'status'                  => $req->status,
            'requested_at'            => $req->requested_at?->format('Y-m-d H:i:s'),
            'processed_by'            => $processedByProfile
                                         ? trim("{$processedByProfile->first_name} {$processedByProfile->last_name}")
                                         : null,
            'processed_at'            => $req->processed_at?->format('Y-m-d H:i:s'),
            'generated_document_path' => $req->file_path,
            'rejection_reason'        => $req->rejection_reason,
        ];
    });

    $statistics = [
        'pending'    => DocumentRequest::where('status', 'pending')->count(),
        'processing' => DocumentRequest::where('status', 'processing')->count(),
        'completed'  => DocumentRequest::whereIn('status', ['processed', 'completed'])->count(),
        'rejected'   => DocumentRequest::where('status', 'rejected')->count(),
    ];

    $this->logAudit(
        'document_requests.view',
        'info',
        ['filters' => $request->only(['status', 'document_type', 'date_from', 'date_to', 'search'])]
    );

    // JSON response for frontend fetchRequests() AJAX call
    if ($request->expectsJson()) {
        return response()->json([
            'requests'   => $requests,
            'statistics' => $statistics,
        ]);
    }

    return Inertia::render('HR/Documents/Requests/Index', [
        'requests'   => $requests,
        'statistics' => $statistics,
        'filters'    => $request->only(['status', 'document_type', 'priority', 'date_from', 'date_to', 'search']),
    ]);
}
```

---

### Phase 1-C — Wire `DocumentRequestController::process()` to real DB

Replace the mock section in `process()`:

```php
public function process(Request $request, $id)
{
    $validated = $request->validate([
        'action'           => 'required|in:approve,reject',
        'template_id'      => 'required_if:action,approve|nullable|integer',
        'notes'            => 'nullable|string|max:500',
        'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        'send_email'       => 'boolean',
    ]);

    $docRequest = DocumentRequest::findOrFail($id);

    if ($validated['action'] === 'approve') {
        $docRequest->update([
            'status'       => 'processing',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        $this->logAudit('document_requests.approve', 'info', [
            'request_id'  => $id,
            'template_id' => $validated['template_id'] ?? null,
            'send_email'  => $validated['send_email'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request approved and processing started.',
            'status'  => 'processing',
        ]);
    }

    // Reject
    $docRequest->update([
        'status'           => 'rejected',
        'processed_by'     => auth()->id(),
        'processed_at'     => now(),
        'rejection_reason' => $validated['rejection_reason'],
        'notes'            => $validated['notes'] ?? null,
    ]);

    $this->logAudit('document_requests.reject', 'info', [
        'request_id'       => $id,
        'rejection_reason' => $validated['rejection_reason'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Request rejected.',
        'status'  => 'rejected',
    ]);
}
```

---

### Phase 1-D — Verify `DocumentRequest` model relations

In `app/Models/DocumentRequest.php`, confirm these exist (add if missing):

```php
// Relationship: Employee (with nested profile)
public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class);
}

// Relationship: Processed by User
public function processedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'processed_by');
}
```

The `Employee` model must have:
- `profile()` → `HasOne(EmployeeProfile::class)`
- `department()` → `BelongsTo(Department::class)`

These already exist in the codebase — no changes needed.

---

### Phase 1 Test Checklist

- [ ] Run `php artisan migrate` — confirm `priority` column added to `document_requests`
- [ ] Navigate to `/hr/documents/requests` — confirm page loads (no Inertia error)
- [ ] Verify stat cards show DB-sourced counts (0 if table is empty)
- [ ] Insert a test record via tinker:  
  `\App\Models\DocumentRequest::create(['employee_id' => 1, 'document_type' => 'COE', 'status' => 'pending', 'priority' => 'urgent'])`
- [ ] Confirm the request appears in the table with correct employee name, department
- [ ] Click "Process" on a pending request → confirm DB status updated to `processing`
- [ ] Click "Reject" → confirm DB status updated to `rejected` with `rejection_reason`
- [ ] Use the search field — confirm it filters by name/number
- [ ] Use the `Accept: application/json` fetch in browser dev tools — confirm JSON response

---

## § 3 — Page 2: `/hr/timekeeping/import`

### Root Cause Analysis

`ImportController` at `app/Http/Controllers/HR/Timekeeping/ImportController.php`:

- `index()` returns `$this->getMockImportBatches()` — a private method that returns a hardcoded PHP array
- `upload()` validates the file and saves it, but never calls `ImportBatch::create(...)`, returns mock ID
- `process()` returns a hardcoded result array
- `errors()` returns mock error data via `getMockImportErrors()`

**Frontend issues:**
- `handleUploadFile()` does `console.log('Upload file clicked')` — no file picker, no API call
- `handleRetryImport()` does `console.log('Retry import for batch:', batch.id)` — no API call
- `handleViewBatch()` calls `setBatchErrors([])` — never fetches real errors from API

**Type/schema gaps:**
- `ImportBatch` TypeScript type has `warnings: number` — this column does not exist in `import_batches` table → add migration OR add a computed value in the controller
- `ImportBatch` TypeScript type has `imported_by_name: string` — needs join with `users` table
- `ImportBatch` TypeScript type has `processing_time: string | null` — can be computed from `started_at`/`completed_at`

---

### Phase 2-A — Migration: Add `warnings` to `import_batches`

**New migration:** `database/migrations/2026_03_10_add_warnings_to_import_batches.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('warnings')->default(0)->after('failed_records');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('warnings');
        });
    }
};
```

---

### Phase 2-B — Wire `ImportController::index()` to real DB

Replace `$this->getMockImportBatches()` call:

```php
use App\Models\ImportBatch;

public function index(Request $request): Response
{
    $query = ImportBatch::with('importedByUser.profile:id,first_name,last_name')
        ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at');

    $batches = $query->get()->map(function (ImportBatch $batch) {
        $importedByUser = $batch->importedByUser;
        $profile        = $importedByUser?->profile;

        $processingTime = null;
        if ($batch->started_at && $batch->completed_at) {
            $secs           = $batch->started_at->diffInSeconds($batch->completed_at);
            $processingTime = $secs . ' seconds';
        }

        return [
            'id'                 => $batch->id,
            'file_name'          => $batch->file_name,
            'file_path'          => $batch->file_path,
            'file_size'          => $batch->file_size,
            'import_type'        => $batch->import_type,
            'total_records'      => $batch->total_records,
            'processed_records'  => $batch->processed_records,
            'successful_records' => $batch->successful_records,
            'failed_records'     => $batch->failed_records,
            'warnings'           => $batch->warnings ?? 0,
            'status'             => $batch->status,
            'started_at'         => $batch->started_at?->toISOString(),
            'completed_at'       => $batch->completed_at?->toISOString(),
            'processing_time'    => $processingTime,
            'error_log'          => $batch->error_log,
            'imported_by'        => $batch->imported_by,
            'imported_by_name'   => $profile
                                    ? trim("{$profile->first_name} {$profile->last_name}")
                                    : $importedByUser?->name ?? 'Unknown',
            'created_at'         => $batch->created_at?->format('Y-m-d H:i:s'),
            'updated_at'         => $batch->updated_at?->format('Y-m-d H:i:s'),
        ];
    });

    $summary = [
        'total_imports'   => ImportBatch::count(),
        'successful'      => ImportBatch::where('status', 'completed')->where('failed_records', 0)->count(),
        'failed'          => ImportBatch::where('status', 'failed')->count(),
        'pending'         => ImportBatch::whereIn('status', ['uploaded', 'processing'])->count(),
        'records_imported'=> (int) ImportBatch::where('status', 'completed')->sum('successful_records'),
    ];

    return Inertia::render('HR/Timekeeping/Import/Index', [
        'batches' => $batches,
        'summary' => $summary,
        'filters' => $request->only(['status', 'date_from', 'date_to']),
    ]);
}
```

---

### Phase 2-C — Wire `ImportController::upload()` to create real `ImportBatch`

Replace mock upload handler with real DB record creation:

```php
public function upload(Request $request): JsonResponse
{
    $validated = $request->validate([
        'file'        => 'required|file|mimes:csv,xlsx,xls|max:10240',
        'import_type' => 'required|in:attendance,overtime,schedule',
    ]);

    $file = $request->file('file');

    // Store file in private storage (not publicly accessible)
    $storedPath = $file->store('imports/timekeeping', 'local');

    // Create ImportBatch record
    $batch = ImportBatch::create([
        'file_name'          => $file->getClientOriginalName(),
        'file_path'          => $storedPath,
        'file_size'          => $file->getSize(),
        'import_type'        => $validated['import_type'],
        'total_records'      => 0,     // Updated when processing starts
        'processed_records'  => 0,
        'successful_records' => 0,
        'failed_records'     => 0,
        'warnings'           => 0,
        'status'             => 'uploaded',
        'imported_by'        => auth()->id(),
    ]);

    // Optionally dispatch a job to process the import asynchronously:
    // ProcessAttendanceImportJob::dispatch($batch->id);

    return response()->json([
        'success' => true,
        'message' => 'File uploaded successfully. Processing will begin shortly.',
        'data'    => [
            'batch_id'    => $batch->id,
            'file_name'   => $batch->file_name,
            'file_size'   => $batch->file_size,
            'import_type' => $batch->import_type,
            'status'      => $batch->status,
            'uploaded_at' => $batch->created_at->format('Y-m-d H:i:s'),
        ],
    ]);
}
```

---

### Phase 2-D — Wire `ImportController::errors()` to real data

The `import_batches` table has an `error_log` (text) column, not a normalized table of errors.
The `ImportError` TypeScript type expects a table of per-row errors, which doesn't exist yet.

**Two options:**

#### Option A — Parse `error_log` JSON (simpler, no migration)

Store errors as JSON string in `error_log`. Frontend can unpack rows from it.

```php
public function errors(int $id): JsonResponse
{
    $batch = ImportBatch::findOrFail($id);

    $errors = [];
    if ($batch->error_log) {
        $decoded = json_decode($batch->error_log, true);
        if (is_array($decoded)) {
            $errors = $decoded;
        }
    }

    return response()->json([
        'success' => true,
        'data'    => [
            'batch_id'   => $id,
            'errors'     => $errors,
            'total'      => count($errors),
        ],
    ]);
}
```

#### Option B — Create `import_batch_errors` table (recommended for large imports)

**Migration:** `database/migrations/2026_03_10_create_import_batch_errors_table.php`

```php
Schema::create('import_batch_errors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
    $table->unsignedInteger('row_number');
    $table->string('employee_identifier')->nullable();
    $table->enum('error_type', ['invalid_employee','invalid_time','duplicate_entry','validation_error']);
    $table->string('error_message');
    $table->json('raw_data')->nullable();
    $table->string('suggested_fix')->nullable();
    $table->timestamps();
    $table->index('import_batch_id');
});
```

Create `ImportBatchError` model and fill it during the actual import job.

> **Recommendation:** Start with Option A. Add Option B when a real import processing job is implemented.

---

### Phase 2-E — Fix frontend `handleUploadFile()`

**File:** `resources/js/pages/HR/Timekeeping/Import/Index.tsx`

Replace:
```ts
const handleUploadFile = () => {
    console.log('Upload file clicked');
    // File upload dialog would open here
};
```

With a real file input + `router.post`:
```ts
import { router } from '@inertiajs/react';
import { useRef } from 'react';

// Add at component level:
const fileInputRef = useRef<HTMLInputElement>(null);

const handleUploadFile = () => {
    fileInputRef.current?.click();
};

const handleFileSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('import_type', 'attendance'); // or from a select dropdown

    router.post('/hr/timekeeping/import/upload', formData, {
        forceFormData: true,
        onSuccess: () => {
            // Inertia will reload page with updated batches
        },
        onError: (errors) => {
            console.error('Upload failed', errors);
        },
    });

    // Reset file input
    if (fileInputRef.current) fileInputRef.current.value = '';
};

// Add to JSX (hidden input + visible button):
// <input ref={fileInputRef} type="file" accept=".csv,.xlsx,.xls" className="hidden" onChange={handleFileSelected} />
```

---

### Phase 2-F — Fix frontend `handleViewBatch()` to fetch real errors

**File:** `resources/js/pages/HR/Timekeeping/Import/Index.tsx`

Replace:
```ts
const handleViewBatch = (batch: ImportBatch) => {
    setSelectedBatch(batch);
    // In a real app, fetch errors for this batch
    setBatchErrors([]);
    setIsDetailModalOpen(true);
};
```

With real fetch:
```ts
const handleViewBatch = async (batch: ImportBatch) => {
    setSelectedBatch(batch);
    setIsDetailModalOpen(true);

    try {
        const res = await fetch(`/hr/timekeeping/import/${batch.id}/errors`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        setBatchErrors(json.data?.errors ?? []);
    } catch {
        setBatchErrors([]);
    }
};
```

Add `GET /hr/timekeeping/import/{id}/errors` route to `routes/hr.php`:
```php
Route::get('/import/{id}/errors', [ImportController::class, 'errors'])->name('import.errors');
```

---

### Phase 2-G — Wire `ImportController::process()` to create a dispatch job

This should be a queued job (`ProcessAttendanceImportJob`). For now, the route exists for manual trigger:

```php
public function process(int $id): JsonResponse
{
    $batch = ImportBatch::findOrFail($id);

    if (!in_array($batch->status, ['uploaded', 'failed'])) {
        return response()->json(['success' => false, 'message' => 'Batch cannot be reprocessed in status: ' . $batch->status], 422);
    }

    $batch->update(['status' => 'processing', 'started_at' => now()]);

    // Dispatch the job
    // ProcessAttendanceImportJob::dispatch($batch->id);
    // For now, just mark as pending and return
    return response()->json([
        'success' => true,
        'message' => 'Import queued for processing.',
        'data'    => ['batch_id' => $id, 'status' => 'processing'],
    ]);
}
```

---

### Phase 2 Test Checklist

- [ ] Run `php artisan migrate` — `warnings` column added to `import_batches`
- [ ] Navigate to `/hr/timekeeping/import` — confirms page loads, summary cards show real DB stats (0 if empty)
- [ ] Click "Upload File" button — file picker opens
- [ ] Upload a CSV file — page reloads, batch appears in table with `status = uploaded`
- [ ] Confirm `import_batches` row exists in DB with correct `file_name`, `imported_by`
- [ ] Click "Details" on a batch — modal opens (with empty errors for `uploaded` status)
- [ ] Retry API: `POST /hr/timekeeping/import/{id}/process` — batch status changes to `processing`

---

## § 4 — Page 3: `/hr/reports/employees`

### Root Cause Analysis

`ReportController::employees()` at `app/Http/Controllers/HR/Reports/ReportController.php`:

- Returns hardcoded `$summary` array with values `total_employees: 45`, `active_employees: 38`, etc.
- Returns hardcoded `$byDepartment` array with 5 fake departments
- Returns `$recentHires = []` — always empty
- Has a comment: `"Placeholder data for frontend development (will be replaced with real data in ISSUE-9 Phase 4)"`

**Frontend** renders correctly when data is zero/empty — it has defensive rendering throughout.

---

### Phase 3-A — Wire `ReportController::employees()` to real `Employee` queries

Replace the hardcoded data with real DB queries:

```php
use App\Models\Employee;
use App\Models\Department;

public function employees(Request $request): Response
{
    $this->authorize('viewAny', Employee::class);

    // ── Summary stats ──────────────────────────────────────────────────────
    $statusCounts = Employee::selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->pluck('count', 'status')
        ->toArray();

    $total      = array_sum($statusCounts);
    $active     = (int) ($statusCounts['active'] ?? 0);
    $inactive   = (int) ($statusCounts['inactive'] ?? 0);
    $terminated = (int) ($statusCounts['terminated'] ?? 0);
    $archived   = (int) ($statusCounts['archived'] ?? 0);

    // On leave: count employees with an active approved leave request today
    $onLeave = \App\Models\LeaveRequest::where('status', 'approved')
        ->where('start_date', '<=', today())
        ->where('end_date', '>=', today())
        ->distinct('employee_id')
        ->count('employee_id');

    // Average tenure: only for active employees with a date_hired
    $avgTenure = Employee::where('status', 'active')
        ->whereNotNull('date_hired')
        ->get()
        ->avg(fn($e) => $e->date_hired->diffInYears(now()));

    $summary = [
        'total_employees'      => $total,
        'active_employees'     => $active,
        'inactive_employees'   => $inactive + $archived,
        'terminated_employees' => $terminated,
        'on_leave_employees'   => $onLeave,
        'average_tenure_years' => round((float) $avgTenure, 2),
    ];

    // ── By department ──────────────────────────────────────────────────────
    $deptCounts = Employee::where('status', 'active')
        ->whereNotNull('department_id')
        ->selectRaw('department_id, COUNT(*) as employee_count')
        ->groupBy('department_id')
        ->with('department:id,name')
        ->get();

    $activeTotal = $deptCounts->sum('employee_count') ?: 1; // avoid divide-by-zero

    $byDepartment = $deptCounts->map(fn($row) => [
        'department_id'   => $row->department_id,
        'department_name' => $row->department?->name ?? 'Unknown',
        'employee_count'  => (int) $row->employee_count,
        'percentage'      => round(($row->employee_count / $activeTotal) * 100, 2),
    ])->sortByDesc('employee_count')->values()->toArray();

    // ── Status distribution ────────────────────────────────────────────────
    $byStatus = collect($statusCounts)->map(fn($count, $status) => [
        'status'     => $status,
        'count'      => (int) $count,
        'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
    ])->values()->toArray();

    // ── Recent hires (last 30 days) ────────────────────────────────────────
    $recentHires = Employee::with([
        'profile:id,first_name,last_name',
        'department:id,name',
        'position:id,title',
    ])
    ->where('date_hired', '>=', now()->subDays(30))
    ->orderByDesc('date_hired')
    ->limit(10)
    ->get();

    return Inertia::render('HR/Reports/Employees', [
        'summary'        => $summary,
        'by_department'  => $byDepartment,
        'by_status'      => $byStatus,
        'recent_hires'   => $recentHires,
        'headcount_trend'=> [], // Future: monthly trend from audit log or snapshot
        'can_export'     => auth()->user()->can('hr.employees.export'),
    ]);
}
```

---

### Phase 3 Test Checklist

- [ ] Navigate to `/hr/reports/employees` — confirm page loads
- [ ] Stat cards show real DB counts (not the hardcoded `45`, `38`, etc.)
- [ ] "Employees by Department" section lists real departments
- [ ] "Recent Hires" section shows employees hired in the last 30 days
- [ ] "Employment Duration Analysis" shows real average tenure (or 0 if all have no `date_hired`)
- [ ] "Employee Status Distribution" shows real status breakdown
- [ ] If zero employees, all sections show "No data available" gracefully

---

## § 5 — Page 4: `/hr/reports/leave`

### Root Cause Analysis

`ReportController::leave()`:

- Returns hardcoded `$summary` with values `total_pending_requests: 12`, etc.
- Returns hardcoded `$byType` with fictional leave type counts + percentages
- Returns hardcoded `$byStatus` with fictional status breakdown
- Has a comment: `"Leave statistics (placeholder - will be replaced with actual leave data from ISSUE-5)"`

`LeaveRequest` model has:
- `status` column: `pending | approved | rejected | cancelled`
- `days_requested` column
- `leave_policy_id` → `LeavePolicy.name` (the leave type name)
- `start_date`, `end_date` (casted as `date`)

---

### Phase 4-A — Wire `ReportController::leave()` to real `LeaveRequest` queries

```php
use App\Models\LeaveRequest;
use App\Models\LeavePolicy;

public function leave(Request $request): Response
{
    $this->authorize('viewAny', Employee::class);

    $currentYear = now()->year;

    // ── Summary stats ──────────────────────────────────────────────────────
    $yearQuery = LeaveRequest::whereYear('start_date', $currentYear);

    $pending   = (clone $yearQuery)->where('status', 'pending')->count();
    $approved  = (clone $yearQuery)->where('status', 'approved')->count();
    $rejected  = (clone $yearQuery)->where('status', 'rejected')->count();

    // Days used this year: sum of days_requested for approved requests
    $daysUsed = (clone $yearQuery)
        ->where('status', 'approved')
        ->sum('days_requested');

    // Employees currently on leave today
    $onLeaveToday = LeaveRequest::where('status', 'approved')
        ->where('start_date', '<=', today())
        ->where('end_date', '>=', today())
        ->distinct('employee_id')
        ->count('employee_id');

    // Average remaining: from leave_balances if available; otherwise 0
    $averageRemaining = 0;
    if (\Schema::hasTable('leave_balances')) {
        $averageRemaining = (float) \App\Models\LeaveBalance::whereYear('year', $currentYear)
            ->where('remaining_days', '>', 0)
            ->avg('remaining_days') ?? 0;
    }

    $summary = [
        'total_pending_requests'       => $pending,
        'total_approved_requests'      => $approved,
        'total_rejected_requests'      => $rejected,
        'employees_on_leave'           => $onLeaveToday,
        'leave_days_used_this_year'    => (float) $daysUsed,
        'leave_days_remaining_average' => round($averageRemaining, 1),
    ];

    // ── By type (join via leave_policies) ─────────────────────────────────
    $totalRequests = $pending + $approved + $rejected;

    $byTypeRaw = LeaveRequest::whereYear('start_date', $currentYear)
        ->whereNotNull('leave_policy_id')
        ->selectRaw('leave_policy_id, COUNT(*) as count')
        ->groupBy('leave_policy_id')
        ->with('leavePolicy:id,name')
        ->get();

    $byType = $byTypeRaw->map(fn($row) => [
        'leave_type' => $row->leavePolicy?->name ?? 'Unknown',
        'count'      => (int) $row->count,
        'percentage' => $totalRequests > 0 ? round(($row->count / $totalRequests) * 100, 2) : 0,
    ])->sortByDesc('count')->values()->toArray();

    // ── By status ──────────────────────────────────────────────────────────
    $statusCounts = LeaveRequest::whereYear('start_date', $currentYear)
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->pluck('count', 'status')
        ->toArray();

    $byStatus = collect($statusCounts)->map(fn($count, $status) => [
        'status'     => ucfirst($status),
        'count'      => (int) $count,
        'percentage' => $totalRequests > 0 ? round(($count / $totalRequests) * 100, 2) : 0,
    ])->values()->toArray();

    return Inertia::render('HR/Reports/Leave', [
        'summary'     => $summary,
        'by_type'     => $byType,
        'by_status'   => $byStatus,
        'by_month'    => [], // Future: monthly breakdown
        'top_users'   => [], // Future: employees with most leave usage
        'can_export'  => auth()->user()->can('hr.leave.export'),
    ]);
}
```

Also confirm `LeaveRequest` model has a `leavePolicy()` relation — check `app/Models/LeaveRequest.php`:
```php
public function leavePolicy(): BelongsTo
{
    return $this->belongsTo(LeavePolicy::class);
}
```

---

### Phase 4 Test Checklist

- [ ] Navigate to `/hr/reports/leave` — confirm page loads
- [ ] Stat cards show real DB counts from `leave_requests` table
- [ ] "Leave Requests by Type" lists real leave policy names (or "No data available" if empty)
- [ ] "Request Status Overview" shows real pending/approved/rejected counts
- [ ] "Status Breakdown Details" lists all statuses from DB
- [ ] All values are for current year only (not all-time)

---

## § 6 — Full Progress Checklist

### Page 1: Document Requests
- [ ] P1-A: Create migration: add `priority` column + expand `status` enum on `document_requests`
- [ ] P1-B: Wire `DocumentRequestController::index()` — remove mock, query real `DocumentRequest` records
- [ ] P1-C: Wire `DocumentRequestController::process()` — update DB status + audit log
- [ ] P1-D: Verify `DocumentRequest` model has all necessary relations

### Page 2: Import Management
- [ ] P2-A: Create migration: add `warnings` column to `import_batches`
- [ ] P2-B: Wire `ImportController::index()` — remove `getMockImportBatches()`, query real records
- [ ] P2-C: Wire `ImportController::upload()` — create real `ImportBatch` row in DB
- [ ] P2-D: Wire `ImportController::errors()` — return `error_log` JSON or normalized table (Option A first)
- [ ] P2-E: Fix `Import/Index.tsx::handleUploadFile()` — add real file input and `router.post`
- [ ] P2-F: Fix `Import/Index.tsx::handleViewBatch()` — fetch real errors via API
- [ ] P2-G: Wire `ImportController::process()` — update status to `processing`, dispatch job (or stub)
- [ ] P2-H: Add missing route `GET /hr/timekeeping/import/{id}/errors` in `routes/hr.php`

### Page 3: Employee Reports
- [ ] P3-A: Replace hardcoded data in `ReportController::employees()` with real `Employee` queries
- [ ] P3-B: Populate `recent_hires` with real employees hired in last 30 days

### Page 4: Leave Reports
- [ ] P4-A: Replace hardcoded data in `ReportController::leave()` with real `LeaveRequest` queries
- [ ] P4-B: Verify `LeaveRequest::leavePolicy()` relation exists
- [ ] P4-C: Verify `LeaveBalance` model exists for `leave_days_remaining_average` fallback

---

## § 7 — Related Files

| File | Role | Action |
|---|---|---|
| `app/Http/Controllers/HR/Documents/DocumentRequestController.php` | Document requests list + process | Replace mock with DB queries |
| `app/Http/Controllers/HR/Timekeeping/ImportController.php` | Import management | Replace mock, wire upload/errors |
| `app/Http/Controllers/HR/Reports/ReportController.php` | Employee + Leave reports | Replace 2 methods with real queries |
| `app/Models/DocumentRequest.php` | Document request model | Verify relations |
| `app/Models/ImportBatch.php` | Import batch model | Add `warnings` to `$fillable` after migration |
| `app/Models/LeaveRequest.php` | Leave request model | Verify `leavePolicy()` relation |
| `resources/js/pages/HR/Documents/Requests/Index.tsx` | Document requests UI | No changes needed — already props-ready |
| `resources/js/pages/HR/Timekeeping/Import/Index.tsx` | Import management UI | Fix `handleUploadFile`, `handleViewBatch` |
| `resources/js/pages/HR/Reports/Employees.tsx` | Employee reports UI | No changes needed — defensive rendering ready |
| `resources/js/pages/HR/Reports/Leave.tsx` | Leave reports UI | No changes needed — defensive rendering ready |
| `routes/hr.php` | HR routes | Add `GET /import/{id}/errors` route |
| `database/migrations/2026_03_10_add_priority_to_document_requests.php` | New migration | To be created |
| `database/migrations/2026_03_10_add_warnings_to_import_batches.php` | New migration | To be created |
