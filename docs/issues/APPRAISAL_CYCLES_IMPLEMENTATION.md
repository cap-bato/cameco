# Appraisal Cycles - Backend Implementation Plan

**Page:** `http://localhost:8000/hr/appraisals/cycles`  
**Status:** 🔴 MOCK DATA — No database tables exist  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

## Phase Progress
- [ ] **Phase 1 Task 1.1:** Database Migrations
- [ ] **Phase 1 Task 1.2:** Eloquent Models
- [ ] **Phase 1 Task 1.3:** Database Seeder
- [ ] **Phase 2 Task 2.1:** Update `AppraisalCycleController::index()`
- [ ] **Phase 2 Task 2.2:** Update `AppraisalCycleController::show()`
- [ ] **Phase 2 Task 2.3:** Update `AppraisalCycleController::store()`
- [ ] **Phase 2 Task 2.4:** Update `AppraisalCycleController::update()`
- [ ] **Phase 2 Task 2.5:** Update `AppraisalCycleController::destroy()`
- [ ] **Phase 2 Task 2.6:** Implement employee assignment endpoint
- [ ] **Phase 3 Task 3.1:** Add permissions for appraisal module

---

## 📋 Summary

The Appraisal Cycles page manages performance review periods (e.g., "Annual Review 2025"). HR creates cycles, defines scoring criteria per cycle, tracks assignment and completion progress, and closes cycles when done.

**Controller:** `app/Http/Controllers/HR/Appraisal/AppraisalCycleController.php`  
**Frontend Pages:**
- `resources/js/pages/HR/Appraisals/Cycles/Index.tsx` — List of all cycles with stats
- `resources/js/pages/HR/Appraisals/Cycles/Show.tsx` — Cycle detail with assigned employees
- `resources/js/pages/HR/Appraisals/Cycles/Edit.tsx` — Edit cycle form

---

## 📊 Current State Analysis

### What the controller currently passes to `Index.tsx`
```php
[
    'cycles'    => [...],  // array with completion_percentage + criteria[] appended
    'employees' => [...],  // list of all employees for assignment modal
    'stats'     => [
        'total_cycles'         => int,
        'active_cycles'        => int,
        'avg_completion_rate'  => float,
        'pending_appraisals'   => int,
    ],
    'filters'   => ['status' => string, 'year' => string],
]
```

### Each cycle object shape (what frontend expects)
```ts
{
    id:                    number
    name:                  string        // "Annual Review 2025"
    start_date:            string        // "2025-01-01"
    end_date:              string        // "2025-12-31"
    status:                'draft' | 'open' | 'closed'
    total_appraisals:      number        // count of assigned employees
    completed_appraisals:  number        // count where status = completed|acknowledged
    average_score:         number | null // avg overall_score
    completion_percentage: number        // computed: completed/total * 100
    created_by:            string        // user name
    created_at:            string
    updated_at:            string
    criteria:              Array<{ name: string; weight: number }>
}
```

### Problems
- ❌ No `appraisal_cycles` table in database
- ❌ No `appraisal_criteria` table in database
- ❌ `total_appraisals` / `completed_appraisals` are hardcoded
- ❌ `employees` list is hardcoded (not from `employees` table)
- ❌ `store()`, `update()`, `destroy()` do nothing (no DB writes)

---

## 🗄️ Database Schema

### Table: `appraisal_cycles`
```sql
CREATE TABLE appraisal_cycles (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,              -- "Annual Review 2025"
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    status      VARCHAR(10) NOT NULL DEFAULT 'draft', -- draft | open | closed
    description TEXT NULL,
    created_by  BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

### Table: `appraisal_criteria`
```sql
CREATE TABLE appraisal_criteria (
    id                 BIGSERIAL PRIMARY KEY,
    appraisal_cycle_id BIGINT NOT NULL REFERENCES appraisal_cycles(id) ON DELETE CASCADE,
    name               VARCHAR(100) NOT NULL,       -- "Quality of Work"
    description        TEXT NULL,
    weight             SMALLINT NOT NULL DEFAULT 20, -- percentage (must sum to 100 per cycle)
    max_score          SMALLINT NOT NULL DEFAULT 10,
    sort_order         SMALLINT NOT NULL DEFAULT 0,
    created_at         TIMESTAMP,
    updated_at         TIMESTAMP
);
```

> **Note:** Migration files already created at:
> - `database/migrations/2026_03_06_100000_create_appraisal_cycles_table.php`
> - `database/migrations/2026_03_06_100100_create_appraisal_criteria_table.php`

---

## 🏗️ Phase 1: Database Setup

### Task 1.1: Run Migrations
```bash
php artisan migrate --path=database/migrations/2026_03_06_100000_create_appraisal_cycles_table.php
php artisan migrate --path=database/migrations/2026_03_06_100100_create_appraisal_criteria_table.php
```

Verify tables exist:
```bash
php artisan tinker
>>> \DB::select("SELECT table_name FROM information_schema.tables WHERE table_name IN ('appraisal_cycles','appraisal_criteria')");
```

---

### Task 1.2: Create Eloquent Models

#### File: `app/Models/AppraisalCycle.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalCycle extends Model
{
    protected $fillable = [
        'name', 'start_date', 'end_date', 'status', 'description', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // Relationships
    public function criteria()
    {
        return $this->hasMany(AppraisalCriteria::class)->orderBy('sort_order');
    }

    public function appraisals()
    {
        return $this->hasMany(Appraisal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Computed accessors
    public function getTotalAppraisalsAttribute(): int
    {
        return $this->appraisals()->count();
    }

    public function getCompletedAppraisalsAttribute(): int
    {
        return $this->appraisals()
            ->whereIn('status', ['completed', 'acknowledged'])
            ->count();
    }

    public function getAverageScoreAttribute(): ?float
    {
        $avg = $this->appraisals()
            ->whereNotNull('overall_score')
            ->avg('overall_score');
        return $avg ? round($avg, 2) : null;
    }

    public function getCompletionPercentageAttribute(): int
    {
        $total = $this->total_appraisals;
        if ($total === 0) return 0;
        return (int) round(($this->completed_appraisals / $total) * 100);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeByYear($query, $year)
    {
        return $query->whereYear('start_date', $year);
    }
}
```

#### File: `app/Models/AppraisalCriteria.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalCriteria extends Model
{
    protected $fillable = [
        'appraisal_cycle_id', 'name', 'description', 'weight', 'max_score', 'sort_order',
    ];

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function scores()
    {
        return $this->hasMany(AppraisalScore::class);
    }
}
```

---

### Task 1.3: Database Seeder

#### File: `database/seeders/AppraisalCycleSeeder.php`
```php
<?php
namespace Database\Seeders;

use App\Models\AppraisalCycle;
use App\Models\AppraisalCriteria;
use Illuminate\Database\Seeder;

class AppraisalCycleSeeder extends Seeder
{
    public function run(): void
    {
        $defaultCriteria = [
            ['name' => 'Quality of Work',         'weight' => 20, 'sort_order' => 1],
            ['name' => 'Attendance & Punctuality', 'weight' => 20, 'sort_order' => 2],
            ['name' => 'Behavior & Conduct',       'weight' => 20, 'sort_order' => 3],
            ['name' => 'Productivity',             'weight' => 20, 'sort_order' => 4],
            ['name' => 'Teamwork',                 'weight' => 20, 'sort_order' => 5],
        ];

        $cycles = [
            ['name' => 'Annual Review 2025',   'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'open'],
            ['name' => 'Mid-Year Review 2025', 'start_date' => '2025-06-01', 'end_date' => '2025-06-30', 'status' => 'open'],
            ['name' => 'Annual Review 2024',   'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'status' => 'closed'],
            ['name' => 'Mid-Year Review 2024', 'start_date' => '2024-06-01', 'end_date' => '2024-06-30', 'status' => 'closed'],
        ];

        foreach ($cycles as $cycleData) {
            $cycle = AppraisalCycle::create($cycleData);
            foreach ($defaultCriteria as $c) {
                $cycle->criteria()->create($c);
            }
        }
    }
}
```

Run: `php artisan db:seed --class=AppraisalCycleSeeder`

---

## 🔧 Phase 2: Controller Updates

### Task 2.1: Update `index()` Method

Replace the entire `index()` method in `AppraisalCycleController`:

```php
public function index(Request $request)
{
    $status = $request->input('status', 'all');
    $year   = $request->input('year', date('Y'));

    // Build query
    $query = AppraisalCycle::withCount([
        'appraisals as total_appraisals',
        'appraisals as completed_appraisals' => fn($q) => $q->whereIn('status', ['completed', 'acknowledged']),
    ])->with('criteria:id,appraisal_cycle_id,name,weight')
      ->with('creator:id,name');

    if ($status !== 'all') {
        $query->where('status', $status);
    }

    $query->whereYear('start_date', $year);

    $cycles = $query->latest()->get()->map(function ($cycle) {
        $total = $cycle->total_appraisals;
        return [
            'id'                    => $cycle->id,
            'name'                  => $cycle->name,
            'start_date'            => $cycle->start_date->format('Y-m-d'),
            'end_date'              => $cycle->end_date->format('Y-m-d'),
            'status'                => $cycle->status,
            'total_appraisals'      => $total,
            'completed_appraisals'  => $cycle->completed_appraisals,
            'average_score'         => $cycle->average_score,
            'completion_percentage' => $total > 0
                ? (int) round(($cycle->completed_appraisals / $total) * 100)
                : 0,
            'created_by'  => $cycle->creator?->name ?? '—',
            'created_at'  => $cycle->created_at?->toDateTimeString(),
            'updated_at'  => $cycle->updated_at?->toDateTimeString(),
            'criteria'    => $cycle->criteria->map(fn($c) => [
                'name'   => $c->name,
                'weight' => $c->weight,
            ])->values(),
        ];
    });

    // Stats across ALL cycles (unfiltered)
    $allCycles = AppraisalCycle::withCount([
        'appraisals as total_appraisals',
        'appraisals as completed_appraisals' => fn($q) => $q->whereIn('status', ['completed', 'acknowledged']),
    ])->get();

    $totalCycles       = $allCycles->count();
    $activeCycles      = $allCycles->where('status', 'open')->count();
    $pendingAppraisals = $allCycles->sum(fn($c) => $c->total_appraisals - $c->completed_appraisals);
    $avgCompletion     = $totalCycles > 0
        ? round($allCycles->sum(fn($c) => $c->total_appraisals > 0
            ? ($c->completed_appraisals / $c->total_appraisals * 100) : 0) / $totalCycles, 1)
        : 0;

    // Real employees for assignment modal
    $employees = Employee::with('profile:id,first_name,last_name')
        ->with('department:id,name')
        ->with('position:id,title')
        ->where('status', 'active')
        ->orderBy('employee_number')
        ->get()
        ->map(fn($e) => [
            'id'              => $e->id,
            'name'            => $e->profile?->first_name . ' ' . $e->profile?->last_name,
            'employee_number' => $e->employee_number,
            'department'      => $e->department?->name,
            'position'        => $e->position?->title,
        ]);

    return Inertia::render('HR/Appraisals/Cycles/Index', [
        'cycles'    => $cycles,
        'employees' => $employees,
        'stats'     => [
            'total_cycles'         => $totalCycles,
            'active_cycles'        => $activeCycles,
            'avg_completion_rate'  => $avgCompletion,
            'pending_appraisals'   => $pendingAppraisals,
        ],
        'filters' => [
            'status' => $status,
            'year'   => $year,
        ],
    ]);
}
```

**Required imports to add at top of controller:**
```php
use App\Models\AppraisalCycle;
use App\Models\AppraisalCriteria;
use App\Models\Employee;
```

---

### Task 2.2: Update `show()` Method

```php
public function show($id)
{
    $cycle = AppraisalCycle::with([
        'criteria:id,appraisal_cycle_id,name,description,weight,max_score,sort_order',
        'creator:id,name',
        'appraisals.employee.profile:id,first_name,last_name',
        'appraisals.employee.department:id,name',
    ])->findOrFail($id);

    $total     = $cycle->appraisals->count();
    $completed = $cycle->appraisals->whereIn('status', ['completed', 'acknowledged'])->count();

    return Inertia::render('HR/Appraisals/Cycles/Show', [
        'cycle' => [
            'id'                    => $cycle->id,
            'name'                  => $cycle->name,
            'start_date'            => $cycle->start_date->format('Y-m-d'),
            'end_date'              => $cycle->end_date->format('Y-m-d'),
            'status'                => $cycle->status,
            'description'           => $cycle->description,
            'total_appraisals'      => $total,
            'completed_appraisals'  => $completed,
            'average_score'         => $cycle->average_score,
            'completion_percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'created_by'            => $cycle->creator?->name,
            'created_at'            => $cycle->created_at?->toDateTimeString(),
            'updated_at'            => $cycle->updated_at?->toDateTimeString(),
            'criteria'              => $cycle->criteria->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'weight'      => $c->weight,
                'max_score'   => $c->max_score,
                'sort_order'  => $c->sort_order,
            ])->values(),
        ],
    ]);
}
```

---

### Task 2.3: Update `store()` Method (Create Cycle)

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name'        => 'required|string|max:150|unique:appraisal_cycles,name',
        'start_date'  => 'required|date',
        'end_date'    => 'required|date|after:start_date',
        'status'      => 'required|in:draft,open',
        'description' => 'nullable|string|max:1000',
        'criteria'    => 'required|array|min:1',
        'criteria.*.name'   => 'required|string|max:100',
        'criteria.*.weight' => 'required|integer|min:1|max:100',
    ]);

    // Validate weights sum to 100
    $totalWeight = array_sum(array_column($validated['criteria'], 'weight'));
    if ($totalWeight !== 100) {
        return back()->withErrors(['criteria' => 'Criteria weights must sum to 100%.']);
    }

    DB::transaction(function () use ($validated) {
        $cycle = AppraisalCycle::create([
            'name'        => $validated['name'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'status'      => $validated['status'],
            'description' => $validated['description'],
            'created_by'  => auth()->id(),
        ]);

        foreach ($validated['criteria'] as $index => $criteria) {
            $cycle->criteria()->create([
                'name'       => $criteria['name'],
                'weight'     => $criteria['weight'],
                'sort_order' => $index + 1,
            ]);
        }
    });

    return redirect()->route('hr.appraisals.cycles.index')
        ->with('success', 'Appraisal cycle created successfully.');
}
```

**Add import:** `use Illuminate\Support\Facades\DB;`

---

### Task 2.4: Update `update()` Method

```php
public function update(Request $request, $id)
{
    $cycle = AppraisalCycle::findOrFail($id);

    $validated = $request->validate([
        'name'        => 'required|string|max:150|unique:appraisal_cycles,name,' . $cycle->id,
        'start_date'  => 'required|date',
        'end_date'    => 'required|date|after:start_date',
        'status'      => 'required|in:draft,open,closed',
        'description' => 'nullable|string|max:1000',
        'criteria'    => 'required|array|min:1',
        'criteria.*.name'   => 'required|string|max:100',
        'criteria.*.weight' => 'required|integer|min:1|max:100',
    ]);

    $totalWeight = array_sum(array_column($validated['criteria'], 'weight'));
    if ($totalWeight !== 100) {
        return back()->withErrors(['criteria' => 'Criteria weights must sum to 100%.']);
    }

    DB::transaction(function () use ($cycle, $validated) {
        $cycle->update([
            'name'        => $validated['name'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'status'      => $validated['status'],
            'description' => $validated['description'],
        ]);

        // Replace criteria
        $cycle->criteria()->delete();
        foreach ($validated['criteria'] as $index => $c) {
            $cycle->criteria()->create([
                'name'       => $c['name'],
                'weight'     => $c['weight'],
                'sort_order' => $index + 1,
            ]);
        }
    });

    return back()->with('success', 'Appraisal cycle updated successfully.');
}
```

---

### Task 2.5: Update `destroy()` Method

```php
public function destroy($id)
{
    $cycle = AppraisalCycle::findOrFail($id);

    // Prevent deletion if appraisals exist
    if ($cycle->appraisals()->count() > 0) {
        return back()->withErrors(['error' => 'Cannot delete a cycle that has appraisals assigned.']);
    }

    $cycle->delete(); // cascades to appraisal_criteria

    return redirect()->route('hr.appraisals.cycles.index')
        ->with('success', 'Appraisal cycle deleted.');
}
```

---

### Task 2.6: Employee Assignment Endpoint

The frontend's `EmployeeAssignmentModal` sends a POST to assign employees to a cycle. Add this to `hr.php` routes and add a method to the controller:

**Route to add in `hr.php`:**
```php
Route::post('/appraisals/cycles/{id}/assign', [AppraisalCycleController::class, 'assignEmployees'])
    ->middleware('permission:hr.appraisals.manage')
    ->name('appraisals.cycles.assign');
```

**Controller method:**
```php
public function assignEmployees(Request $request, $id)
{
    $cycle = AppraisalCycle::findOrFail($id);

    $validated = $request->validate([
        'employee_ids'   => 'required|array|min:1',
        'employee_ids.*' => 'required|integer|exists:employees,id',
    ]);

    $created = 0;
    foreach ($validated['employee_ids'] as $employeeId) {
        // Skip if already assigned
        $exists = Appraisal::where('appraisal_cycle_id', $cycle->id)
            ->where('employee_id', $employeeId)
            ->exists();

        if (!$exists) {
            Appraisal::create([
                'appraisal_cycle_id' => $cycle->id,
                'employee_id'        => $employeeId,
                'status'             => 'draft',
                'created_by'         => auth()->id(),
            ]);
            $created++;
        }
    }

    return back()->with('success', "{$created} employee(s) assigned to cycle.");
}
```

**Add import:** `use App\Models\Appraisal;`

---

## 🔐 Phase 3: Permissions

### Task 3.1: Ensure Permissions Exist

Check that these permissions are seeded in your permissions seeder:
```
hr.appraisals.view
hr.appraisals.manage
```

If not, add them to whatever seeder handles HR permissions (likely `RolesAndPermissionsSeeder`):
```php
Permission::firstOrCreate(['name' => 'hr.appraisals.view',   'guard_name' => 'web']);
Permission::firstOrCreate(['name' => 'hr.appraisals.manage', 'guard_name' => 'web']);
```

Then assign to appropriate roles (HR Manager, HR Staff as read-only).

---

## ✅ Acceptance Criteria

- [ ] Cycles list loads from database (not hardcoded)
- [ ] Stats (total_cycles, active_cycles, etc.) are computed from real data
- [ ] Creating a cycle writes to `appraisal_cycles` + `appraisal_criteria` tables
- [ ] Editing a cycle updates the tables
- [ ] Deleting a cycle is blocked if appraisals exist
- [ ] Assigning employees creates `appraisals` rows in draft status
- [ ] Employee list in assignment modal shows real employees from DB
- [ ] Filters (status, year) work against real data

---

## 📁 Files to Create/Modify

| Action | File |
|--------|------|
| ✅ Already created | `database/migrations/2026_03_06_100000_create_appraisal_cycles_table.php` |
| ✅ Already created | `database/migrations/2026_03_06_100100_create_appraisal_criteria_table.php` |
| 🆕 Create | `app/Models/AppraisalCycle.php` |
| 🆕 Create | `app/Models/AppraisalCriteria.php` |
| 🆕 Create | `database/seeders/AppraisalCycleSeeder.php` |
| ✏️ Modify | `app/Http/Controllers/HR/Appraisal/AppraisalCycleController.php` |
| ✏️ Modify | `routes/hr.php` (add assign endpoint) |
