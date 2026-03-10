# Appraisals (Employee Reviews) - Backend Implementation Plan

**Page:** `http://localhost:8000/hr/appraisals`  
**Status:** 🔴 MOCK DATA — No database tables exist  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

## Phase Progress
- [ ] **Phase 1 Task 1.1:** Database Migration — `appraisals` table
- [ ] **Phase 1 Task 1.2:** Database Migration — `appraisal_scores` table
- [ ] **Phase 1 Task 1.3:** Eloquent Models (`Appraisal`, `AppraisalScore`)
- [ ] **Phase 1 Task 1.4:** Database Seeder
- [ ] **Phase 2 Task 2.1:** Update `AppraisalController::index()`
- [ ] **Phase 2 Task 2.2:** Update `AppraisalController::show()`
- [ ] **Phase 2 Task 2.3:** Update `AppraisalController::store()`
- [ ] **Phase 2 Task 2.4:** Update `AppraisalController::updateScores()`
- [ ] **Phase 2 Task 2.5:** Update `AppraisalController::updateStatus()`
- [ ] **Phase 2 Task 2.6:** Update `AppraisalController::submitFeedback()`

---

## 📋 Summary

The Appraisals page manages individual employee performance reviews within a cycle. HR creates an appraisal per employee per cycle, enters scores for each criterion, adds feedback, and tracks the review through a workflow: `draft → in_progress → completed → acknowledged`.

**Controller:** `app/Http/Controllers/HR/Appraisal/AppraisalController.php`  
**Frontend Pages:**
- `resources/js/pages/HR/Appraisals/Index.tsx` — List with filters (cycle, status, department, search)
- `resources/js/pages/HR/Appraisals/Show.tsx` — Detail view with scores per criterion

---

## 📊 Current State Analysis

### What the controller currently passes to `Index.tsx`
```php
[
    'appraisals'  => [...],  // filtered array of appraisal records
    'cycles'      => [...],  // for filter dropdown
    'departments' => [...],  // for filter dropdown
    'filters'     => ['cycle_id', 'status', 'department_id', 'search'],
]
```

### Each appraisal record shape (what `Index.tsx` expects)
```ts
{
    id:               number
    employee_id:      number
    employee_name:    string
    employee_number:  string
    department_id:    number
    department_name:  string
    cycle_id:         number
    cycle_name:       string
    status:           'draft' | 'in_progress' | 'completed' | 'acknowledged'
    status_label:     string         // e.g. "In Progress"
    status_color:     string         // e.g. "bg-blue-100 text-blue-800"
    overall_score:    number | null
    attendance_rate:  number | null  // from timekeeping (or stored snapshot)
    lateness_count:   number
    violation_count:  number
    created_at:       string
    updated_at:       string
}
```

### What `Show.tsx` expects (detail view)
```ts
{
    appraisal: {
        ...above fields +
        feedback:        string | null
        scores: Array<{
            id:         number
            criterion:  string
            score:      number
            weight:     number
            notes:      string | null
        }>
    }
    employee: {
        id, employee_number, first_name, last_name, full_name,
        department_name, position_name, email, phone, date_employed, status
    }
    cycle: { id, name, start_date, end_date }
}
```

### Problems
- ❌ No `appraisals` table in database
- ❌ No `appraisal_scores` table in database
- ❌ Attendance data (`attendance_rate`, `lateness_count`) is hardcoded — needs to come from timekeeping tables
- ❌ All write methods (`store`, `updateScores`, `updateStatus`, `submitFeedback`) do nothing

---

## 🗄️ Database Schema

### Table: `appraisals`
```sql
CREATE TABLE appraisals (
    id                  BIGSERIAL PRIMARY KEY,
    appraisal_cycle_id  BIGINT NOT NULL REFERENCES appraisal_cycles(id) ON DELETE CASCADE,
    employee_id         BIGINT NOT NULL REFERENCES employees(id),
    appraiser_id        BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',  -- draft|in_progress|completed|acknowledged
    overall_score       DECIMAL(4,2) NULL,                     -- 1.00–10.00
    feedback            TEXT NULL,
    notes               TEXT NULL,
    submitted_at        TIMESTAMP NULL,
    acknowledged_at     TIMESTAMP NULL,
    created_by          BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by          BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP,
    UNIQUE (appraisal_cycle_id, employee_id)                   -- one appraisal per employee per cycle
);
```

### Table: `appraisal_scores`
```sql
CREATE TABLE appraisal_scores (
    id                   BIGSERIAL PRIMARY KEY,
    appraisal_id         BIGINT NOT NULL REFERENCES appraisals(id) ON DELETE CASCADE,
    appraisal_criteria_id BIGINT NOT NULL REFERENCES appraisal_criteria(id) ON DELETE CASCADE,
    score                DECIMAL(4,2) NOT NULL,   -- 1.00–10.00
    comments             TEXT NULL,
    created_at           TIMESTAMP,
    updated_at           TIMESTAMP,
    UNIQUE (appraisal_id, appraisal_criteria_id)
);
```

> **Note:** Migration files already created at:
> - `database/migrations/2026_03_06_100200_create_appraisals_table.php`
> - `database/migrations/2026_03_06_100300_create_appraisal_scores_table.php`

### Note on Attendance Data
`attendance_rate`, `lateness_count`, and `violation_count` are **not stored on the appraisal record**. They are read-only snapshots from the timekeeping module when displaying an appraisal. Query from `daily_attendance_summaries` and `attendance_events` tables at display time, scoped to the cycle's date range.

If timekeeping data is not yet connected, store these as nullable columns on `appraisals` and populate them when the appraisal is submitted.

---

## 🏗️ Phase 1: Database Setup

### Task 1.1–1.2: Run Migrations
```bash
php artisan migrate --path=database/migrations/2026_03_06_100200_create_appraisals_table.php
php artisan migrate --path=database/migrations/2026_03_06_100300_create_appraisal_scores_table.php
```

---

### Task 1.3: Create Eloquent Models

#### File: `app/Models/Appraisal.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appraisal extends Model
{
    protected $fillable = [
        'appraisal_cycle_id', 'employee_id', 'appraiser_id',
        'status', 'overall_score', 'feedback', 'notes',
        'submitted_at', 'acknowledged_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'overall_score'   => 'decimal:2',
        'submitted_at'    => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    // Relationships
    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function appraiser()
    {
        return $this->belongsTo(User::class, 'appraiser_id');
    }

    public function scores()
    {
        return $this->hasMany(AppraisalScore::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Status label accessor
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'        => 'Draft',
            'in_progress'  => 'In Progress',
            'completed'    => 'Completed',
            'acknowledged' => 'Acknowledged',
            default        => ucfirst($this->status),
        };
    }

    // Status color accessor (Tailwind classes)
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'        => 'bg-gray-100 text-gray-800',
            'in_progress'  => 'bg-blue-100 text-blue-800',
            'completed'    => 'bg-green-100 text-green-800',
            'acknowledged' => 'bg-purple-100 text-purple-800',
            default        => 'bg-gray-100 text-gray-800',
        };
    }
}
```

#### File: `app/Models/AppraisalScore.php`
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalScore extends Model
{
    protected $fillable = [
        'appraisal_id', 'appraisal_criteria_id', 'score', 'comments',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function criteria()
    {
        return $this->belongsTo(AppraisalCriteria::class, 'appraisal_criteria_id');
    }
}
```

---

### Task 1.4: Database Seeder

#### File: `database/seeders/AppraisalSeeder.php`
```php
<?php
namespace Database\Seeders;

use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\AppraisalScore;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class AppraisalSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = AppraisalCycle::where('name', 'Annual Review 2025')->first();
        if (!$cycle) return;

        $employees = Employee::where('status', 'active')->take(10)->get();
        $criteria  = $cycle->criteria;

        foreach ($employees as $employee) {
            $appraisal = Appraisal::create([
                'appraisal_cycle_id' => $cycle->id,
                'employee_id'        => $employee->id,
                'status'             => collect(['draft', 'in_progress', 'completed'])->random(),
                'created_by'         => 1,
            ]);

            // Add scores if completed
            if (in_array($appraisal->status, ['completed', 'acknowledged'])) {
                $totalScore = 0;
                foreach ($criteria as $criterion) {
                    $score = round(rand(60, 95) / 10, 1); // 6.0–9.5
                    AppraisalScore::create([
                        'appraisal_id'          => $appraisal->id,
                        'appraisal_criteria_id' => $criterion->id,
                        'score'                 => $score,
                        'comments'              => null,
                    ]);
                    $totalScore += $score;
                }
                $appraisal->update([
                    'overall_score' => round($totalScore / $criteria->count(), 2),
                    'submitted_at'  => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }
}
```

Run:
```bash
php artisan db:seed --class=AppraisalCycleSeeder  # must run first
php artisan db:seed --class=AppraisalSeeder
```

---

## 🔧 Phase 2: Controller Updates

### Task 2.1: Update `index()` Method

```php
use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\Department;

public function index(Request $request)
{
    $cycleId      = $request->input('cycle_id', '');
    $status       = $request->input('status', '');
    $departmentId = $request->input('department_id', '');
    $search       = $request->input('search', '');

    $query = Appraisal::with([
        'employee.profile:id,first_name,last_name',
        'employee.department:id,name',
        'cycle:id,name',
    ]);

    if ($cycleId)      $query->where('appraisal_cycle_id', $cycleId);
    if ($status)       $query->where('status', $status);
    if ($departmentId) $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
    if ($search) {
        $query->whereHas('employee', function ($q) use ($search) {
            $q->where('employee_number', 'ILIKE', "%{$search}%")
              ->orWhereHas('profile', fn($pq) => $pq->where(
                  \DB::raw("CONCAT(first_name, ' ', last_name)"), 'ILIKE', "%{$search}%"
              ));
        });
    }

    $appraisals = $query->latest()->get()->map(function ($a) {
        return [
            'id'              => $a->id,
            'employee_id'     => $a->employee_id,
            'employee_name'   => $a->employee?->profile?->first_name . ' ' . $a->employee?->profile?->last_name,
            'employee_number' => $a->employee?->employee_number,
            'department_id'   => $a->employee?->department_id,
            'department_name' => $a->employee?->department?->name,
            'cycle_id'        => $a->appraisal_cycle_id,
            'cycle_name'      => $a->cycle?->name,
            'status'          => $a->status,
            'status_label'    => $a->status_label,
            'status_color'    => $a->status_color,
            'overall_score'   => $a->overall_score,
            'attendance_rate' => null,  // TODO: pull from timekeeping when integrated
            'lateness_count'  => 0,
            'violation_count' => 0,
            'created_at'      => $a->created_at?->toDateTimeString(),
            'updated_at'      => $a->updated_at?->toDateTimeString(),
        ];
    });

    // Cycles for filter dropdown
    $cycles = AppraisalCycle::orderByDesc('start_date')
        ->get(['id', 'name'])
        ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

    // Departments for filter dropdown
    $departments = Department::orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($d) => ['id' => $d->id, 'name' => $d->name]);

    return Inertia::render('HR/Appraisals/Index', [
        'appraisals'  => $appraisals,
        'cycles'      => $cycles,
        'departments' => $departments,
        'filters'     => compact('cycleId', 'status', 'departmentId', 'search'),
    ]);
}
```

---

### Task 2.2: Update `show()` Method

```php
public function show($id)
{
    $appraisal = Appraisal::with([
        'employee.profile',
        'employee.department:id,name',
        'employee.position:id,title',
        'cycle:id,name,start_date,end_date',
        'scores.criteria:id,name,weight,max_score',
    ])->findOrFail($id);

    return Inertia::render('HR/Appraisals/Show', [
        'appraisal' => [
            'id'              => $appraisal->id,
            'employee_id'     => $appraisal->employee_id,
            'employee_name'   => $appraisal->employee?->profile?->first_name . ' ' . $appraisal->employee?->profile?->last_name,
            'employee_number' => $appraisal->employee?->employee_number,
            'department_name' => $appraisal->employee?->department?->name,
            'cycle_name'      => $appraisal->cycle?->name,
            'status'          => $appraisal->status,
            'status_label'    => $appraisal->status_label,
            'status_color'    => $appraisal->status_color,
            'overall_score'   => $appraisal->overall_score,
            'feedback'        => $appraisal->feedback,
            'attendance_rate' => null,  // TODO: timekeeping integration
            'lateness_count'  => 0,
            'violation_count' => 0,
            'created_at'      => $appraisal->created_at?->toDateTimeString(),
            'updated_at'      => $appraisal->updated_at?->toDateTimeString(),
            'scores'          => $appraisal->scores->map(fn($s) => [
                'id'        => $s->id,
                'criterion' => $s->criteria?->name,
                'score'     => (float) $s->score,
                'weight'    => $s->criteria?->weight,
                'notes'     => $s->comments,
            ])->values(),
        ],
        'employee' => [
            'id'              => $appraisal->employee->id,
            'employee_number' => $appraisal->employee->employee_number,
            'first_name'      => $appraisal->employee->profile?->first_name,
            'last_name'       => $appraisal->employee->profile?->last_name,
            'full_name'       => $appraisal->employee->profile?->first_name . ' ' . $appraisal->employee->profile?->last_name,
            'department_name' => $appraisal->employee->department?->name,
            'position_name'   => $appraisal->employee->position?->title,
            'date_employed'   => $appraisal->employee->date_hired?->format('Y-m-d'),
            'status'          => $appraisal->employee->status,
        ],
        'cycle' => [
            'id'         => $appraisal->cycle->id,
            'name'       => $appraisal->cycle->name,
            'start_date' => $appraisal->cycle->start_date->format('Y-m-d'),
            'end_date'   => $appraisal->cycle->end_date->format('Y-m-d'),
        ],
    ]);
}
```

---

### Task 2.3: Update `store()` Method

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'employee_id' => 'required|integer|exists:employees,id',
        'cycle_id'    => 'required|integer|exists:appraisal_cycles,id',
    ]);

    // Prevent duplicate
    $exists = Appraisal::where('appraisal_cycle_id', $validated['cycle_id'])
        ->where('employee_id', $validated['employee_id'])
        ->exists();

    if ($exists) {
        return back()->withErrors(['employee_id' => 'Appraisal already exists for this employee in the selected cycle.']);
    }

    Appraisal::create([
        'appraisal_cycle_id' => $validated['cycle_id'],
        'employee_id'        => $validated['employee_id'],
        'status'             => 'draft',
        'created_by'         => auth()->id(),
    ]);

    return redirect()->route('hr.appraisals.index')
        ->with('success', 'Appraisal created successfully.');
}
```

---

### Task 2.4: Update `updateScores()` Method

```php
public function updateScores(Request $request, $id)
{
    $appraisal = Appraisal::findOrFail($id);

    $validated = $request->validate([
        'scores'                    => 'required|array|min:1',
        'scores.*.appraisal_criteria_id' => 'required|integer|exists:appraisal_criteria,id',
        'scores.*.score'            => 'required|numeric|min:0|max:10',
        'scores.*.comments'         => 'nullable|string|max:500',
    ]);

    DB::transaction(function () use ($appraisal, $validated) {
        foreach ($validated['scores'] as $scoreData) {
            AppraisalScore::updateOrCreate(
                [
                    'appraisal_id'          => $appraisal->id,
                    'appraisal_criteria_id' => $scoreData['appraisal_criteria_id'],
                ],
                [
                    'score'    => $scoreData['score'],
                    'comments' => $scoreData['comments'] ?? null,
                ]
            );
        }

        // Recompute overall score as weighted average
        $scores    = $appraisal->scores()->with('criteria:id,weight')->get();
        $totalWeight = $scores->sum('criteria.weight');
        $weightedSum = $scores->sum(fn($s) => $s->score * ($s->criteria?->weight ?? 0));
        $overall = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;

        $appraisal->update([
            'overall_score' => $overall,
            'updated_by'    => auth()->id(),
        ]);
    });

    return back()->with('success', 'Scores updated successfully.');
}
```

**Add import:** `use App\Models\AppraisalScore; use Illuminate\Support\Facades\DB;`

---

### Task 2.5: Update `updateStatus()` Method

```php
public function updateStatus(Request $request, $id)
{
    $appraisal = Appraisal::findOrFail($id);

    $validated = $request->validate([
        'status' => 'required|in:draft,in_progress,completed,acknowledged',
        'notes'  => 'nullable|string|max:500',
    ]);

    $updates = [
        'status'     => $validated['status'],
        'updated_by' => auth()->id(),
    ];

    if ($validated['status'] === 'completed') {
        $updates['submitted_at'] = now();
    }
    if ($validated['status'] === 'acknowledged') {
        $updates['acknowledged_at'] = now();
    }

    $appraisal->update($updates);

    return back()->with('success', 'Appraisal status updated.');
}
```

---

### Task 2.6: Update `submitFeedback()` Method

```php
public function submitFeedback(Request $request, $id)
{
    $appraisal = Appraisal::findOrFail($id);

    $validated = $request->validate([
        'overall_score'  => 'required|numeric|min:0|max:10',
        'feedback'       => 'required|string|min:10|max:1000',
        'scores'         => 'required|array|min:1',
        'scores.*.appraisal_criteria_id' => 'required|integer|exists:appraisal_criteria,id',
        'scores.*.score' => 'required|numeric|min:0|max:10',
        'scores.*.notes' => 'nullable|string|max:500',
    ]);

    DB::transaction(function () use ($appraisal, $validated) {
        foreach ($validated['scores'] as $scoreData) {
            AppraisalScore::updateOrCreate(
                [
                    'appraisal_id'          => $appraisal->id,
                    'appraisal_criteria_id' => $scoreData['appraisal_criteria_id'],
                ],
                [
                    'score'    => $scoreData['score'],
                    'comments' => $scoreData['notes'] ?? null,
                ]
            );
        }

        $appraisal->update([
            'overall_score' => $validated['overall_score'],
            'feedback'      => $validated['feedback'],
            'status'        => 'completed',
            'submitted_at'  => now(),
            'updated_by'    => auth()->id(),
        ]);
    });

    return back()->with('success', 'Appraisal feedback submitted successfully.');
}
```

---

## 📝 Note on Attendance Data

The frontend displays `attendance_rate`, `lateness_count`, and `violation_count` per appraisal. These should come from timekeeping tables:

**Suggested query for `attendance_rate`** (within cycle date range):
```php
// In DailyAttendanceSummary model or timekeeping table
$attendanceRate = DailyAttendanceSummary::where('employee_id', $employeeId)
    ->whereBetween('date', [$cycle->start_date, $cycle->end_date])
    ->selectRaw('ROUND(100.0 * SUM(CASE WHEN status = \'present\' THEN 1 ELSE 0 END) / COUNT(*), 1) as rate')
    ->value('rate');
```

This integration can be deferred — pass `null` initially and implement when timekeeping data is stable.

---

## ✅ Acceptance Criteria

- [ ] Appraisals list loads from database
- [ ] Filter by cycle, status, department, and search all work
- [ ] Appraisal detail shows correct scores from `appraisal_scores` table
- [ ] Creating an appraisal writes to `appraisals` table
- [ ] Scores are saved via `updateScores()` with weighted average recalculation
- [ ] Status transitions work (draft → in_progress → completed → acknowledged)
- [ ] `submitFeedback()` saves scores + overall_score + feedback in one transaction

---

## 📁 Files to Create/Modify

| Action | File |
|--------|------|
| ✅ Already created | `database/migrations/2026_03_06_100200_create_appraisals_table.php` |
| ✅ Already created | `database/migrations/2026_03_06_100300_create_appraisal_scores_table.php` |
| 🆕 Create | `app/Models/Appraisal.php` |
| 🆕 Create | `app/Models/AppraisalScore.php` |
| 🆕 Create | `database/seeders/AppraisalSeeder.php` |
| ✏️ Modify | `app/Http/Controllers/HR/Appraisal/AppraisalController.php` |
