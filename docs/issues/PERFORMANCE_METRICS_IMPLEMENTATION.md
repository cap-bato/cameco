# Performance Metrics - Backend Implementation Plan

**Page:** `http://localhost:8000/hr/performance-metrics`  
**Status:** 🔴 MOCK DATA — No database tables exist  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

## Phase Progress
- [ ] **Phase 1 Task 1.1:** Database Migration — `performance_metrics` table
- [ ] **Phase 1 Task 1.2:** Eloquent Model (`PerformanceMetric`)
- [ ] **Phase 1 Task 1.3:** Database Seeder
- [ ] **Phase 2 Task 2.1:** Update `PerformanceMetricsController::index()`
- [ ] **Phase 2 Task 2.2:** Update `PerformanceMetricsController::show()`
- [ ] **Phase 2 Task 2.3:** Implement `departmentComparison()` with real data
- [ ] **Phase 2 Task 2.4:** Create `PerformanceMetricService` (compute + store metrics)
- [ ] **Phase 3 Task 3.1:** Console command / scheduler to recompute metrics

---

## 📋 Summary

The Performance Metrics page provides HR analytics dashboards showing employee performance scores per appraisal cycle, performance categories (high/medium/low), trends, and department comparisons. Metrics are **derived/computed** from `appraisals` + optional timekeeping data, then stored as a snapshot.

**Controller:** `app/Http/Controllers/HR/Appraisal/PerformanceMetricsController.php`  
**Frontend Pages:**
- `resources/js/pages/HR/PerformanceMetrics/Index.tsx` — Dashboard with filters + employee list
- `resources/js/pages/HR/PerformanceMetrics/Show.tsx` — Individual employee history + charts

---

## 📊 Current State Analysis

### What the controller currently passes to `Index.tsx`
```php
[
    'metrics'     => [...],  // array of per-employee metric records
    'departments' => [...],  // for filter dropdown
    'summary'     => [
        'average_score'   => float,
        'high_performers' => int,
        'low_performers'  => int,
        'completion_rate' => float,
    ],
    'filters' => ['department_id', 'performance_category', 'date_from', 'date_to'],
]
```

### Each metric record shape (what `Index.tsx` expects)
```ts
{
    employee_id:          number
    employee_name:        string
    employee_number:      string
    department_id:        number
    department_name:      string
    overall_score:        number        // 1.0–10.0
    attendance_rate:      number        // 0–100
    behavior_score:       number
    productivity_score:   number
    performance_category: 'excellent' | 'high' | 'medium' | 'low' | 'poor'
    trend:                'improving' | 'stable' | 'declining'
}
```

### What `Show.tsx` expects (employee history page)
```ts
{
    employee:          { id, employee_number, first_name, last_name, full_name, department_name, email }
    currentMetric:     { ...above fields }
    historicalMetrics: Array<{ cycle_name, overall_score, cycle_date }>
    departmentAverage: number
    trend:             string
    attendanceCorrelation: {
        months:           string[]
        appraisalScores:  number[]
        attendanceRates:  number[]
    }
}
```

### Problems
- ❌ No `performance_metrics` table — data is entirely hardcoded
- ❌ Historical data per employee is hardcoded
- ❌ Department comparison is hardcoded
- ❌ No mechanism to recompute/refresh metrics when appraisals are updated

---

## 🗄️ Database Schema

### Table: `performance_metrics`

This is a **materialized snapshot** table. It is computed from `appraisals` + `appraisal_scores` after an appraisal cycle is completed, and stored here for fast querying.

```sql
CREATE TABLE performance_metrics (
    id                   BIGSERIAL PRIMARY KEY,
    employee_id          BIGINT NOT NULL REFERENCES employees(id),
    appraisal_cycle_id   BIGINT NOT NULL REFERENCES appraisal_cycles(id),

    -- Scores (derived from appraisal + appraisal_scores)
    overall_score        DECIMAL(4,2) NULL,
    behavior_score       DECIMAL(4,2) NULL,    -- score for "Behavior & Conduct" criterion
    productivity_score   DECIMAL(4,2) NULL,    -- score for "Productivity" criterion

    -- Attendance snapshot (from timekeeping for the cycle date range)
    attendance_rate      DECIMAL(5,2) NULL,    -- percentage 0–100
    lateness_count       INT NOT NULL DEFAULT 0,
    absence_count        INT NOT NULL DEFAULT 0,

    -- Category and trend
    performance_category VARCHAR(20) NULL,     -- excellent|high|medium|low|poor
    trend                VARCHAR(20) NULL,     -- improving|stable|declining

    -- Metadata
    computed_at          TIMESTAMP NULL,       -- when this row was last recomputed
    created_at           TIMESTAMP,
    updated_at           TIMESTAMP,

    UNIQUE (employee_id, appraisal_cycle_id)   -- one metric per employee per cycle
);
```

### Category Thresholds (apply when computing)
| Score        | Category    |
|-------------|-------------|
| 9.0 – 10.0  | excellent   |
| 7.5 – 8.9   | high        |
| 6.0 – 7.4   | medium      |
| 4.0 – 5.9   | low         |
| < 4.0       | poor        |

### Trend Logic (compare current vs previous cycle)
- `improving` — current overall_score > previous by > 0.4
- `declining` — current overall_score < previous by > 0.4
- `stable`    — otherwise

---

## 🏗️ Phase 1: Database Setup

### Task 1.1: Create Migration

**File:** `database/migrations/2026_03_06_100400_create_performance_metrics_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->decimal('behavior_score', 4, 2)->nullable();
            $table->decimal('productivity_score', 4, 2)->nullable();
            $table->decimal('attendance_rate', 5, 2)->nullable();
            $table->unsignedInteger('lateness_count')->default(0);
            $table->unsignedInteger('absence_count')->default(0);
            $table->string('performance_category', 20)->nullable();
            $table->string('trend', 20)->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'appraisal_cycle_id']);
            $table->index('performance_category');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
```

Run: `php artisan migrate --path=database/migrations/2026_03_06_100400_create_performance_metrics_table.php`

---

### Task 1.2: Create Eloquent Model

**File:** `app/Models/PerformanceMetric.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $fillable = [
        'employee_id', 'appraisal_cycle_id',
        'overall_score', 'behavior_score', 'productivity_score',
        'attendance_rate', 'lateness_count', 'absence_count',
        'performance_category', 'trend', 'computed_at',
    ];

    protected $casts = [
        'overall_score'      => 'decimal:2',
        'behavior_score'     => 'decimal:2',
        'productivity_score' => 'decimal:2',
        'attendance_rate'    => 'decimal:2',
        'computed_at'        => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }
}
```

---

### Task 1.3: Database Seeder

**File:** `database/seeders/PerformanceMetricSeeder.php`

```php
<?php
namespace Database\Seeders;

use App\Models\Appraisal;
use App\Models\PerformanceMetric;
use App\Services\HR\PerformanceMetricService;
use Illuminate\Database\Seeder;

class PerformanceMetricSeeder extends Seeder
{
    public function run(): void
    {
        // Recompute from all completed appraisals
        $service = app(PerformanceMetricService::class);

        Appraisal::with('cycle')
            ->whereIn('status', ['completed', 'acknowledged'])
            ->get()
            ->groupBy('appraisal_cycle_id')
            ->each(function ($appraisals, $cycleId) use ($service) {
                foreach ($appraisals as $appraisal) {
                    $service->computeAndStore($appraisal);
                }
            });
    }
}
```

---

## 🔧 Phase 2: Controller Updates

### Task 2.4: Create `PerformanceMetricService` First

**File:** `app/Services/HR/PerformanceMetricService.php`

```php
<?php
namespace App\Services\HR;

use App\Models\Appraisal;
use App\Models\AppraisalScore;
use App\Models\PerformanceMetric;

class PerformanceMetricService
{
    /**
     * Compute metrics from a completed appraisal and store/update the snapshot.
     */
    public function computeAndStore(Appraisal $appraisal): PerformanceMetric
    {
        $appraisal->load('scores.criteria', 'cycle');

        // Find behavior and productivity scores by criterion name
        $behaviorScore    = $appraisal->scores
            ->first(fn($s) => str_contains(strtolower($s->criteria?->name ?? ''), 'behavior'));
        $productivityScore = $appraisal->scores
            ->first(fn($s) => str_contains(strtolower($s->criteria?->name ?? ''), 'productivity'));

        // Determine category
        $category = $this->categorize($appraisal->overall_score);

        // Determine trend (compare with previous cycle)
        $trend = $this->computeTrend($appraisal->employee_id, $appraisal->appraisal_cycle_id, $appraisal->overall_score);

        return PerformanceMetric::updateOrCreate(
            [
                'employee_id'        => $appraisal->employee_id,
                'appraisal_cycle_id' => $appraisal->appraisal_cycle_id,
            ],
            [
                'overall_score'      => $appraisal->overall_score,
                'behavior_score'     => $behaviorScore?->score,
                'productivity_score' => $productivityScore?->score,
                'attendance_rate'    => null,  // TODO: integrate timekeeping
                'performance_category' => $category,
                'trend'              => $trend,
                'computed_at'        => now(),
            ]
        );
    }

    /**
     * Assign a performance category based on the overall score.
     */
    public function categorize(?float $score): ?string
    {
        if ($score === null) return null;
        return match(true) {
            $score >= 9.0 => 'excellent',
            $score >= 7.5 => 'high',
            $score >= 6.0 => 'medium',
            $score >= 4.0 => 'low',
            default       => 'poor',
        };
    }

    /**
     * Compare current score against the employee's most recent previous cycle metric.
     */
    private function computeTrend(int $employeeId, int $currentCycleId, ?float $currentScore): string
    {
        if ($currentScore === null) return 'stable';

        $previous = PerformanceMetric::where('employee_id', $employeeId)
            ->where('appraisal_cycle_id', '!=', $currentCycleId)
            ->whereNotNull('overall_score')
            ->orderByDesc('computed_at')
            ->value('overall_score');

        if ($previous === null) return 'stable';

        $diff = $currentScore - $previous;
        if ($diff > 0.4)  return 'improving';
        if ($diff < -0.4) return 'declining';
        return 'stable';
    }
}
```

---

### Task 2.1: Update `index()` Method

```php
use App\Models\PerformanceMetric;
use App\Models\AppraisalCycle;
use App\Models\Department;

public function index(Request $request)
{
    $departmentId        = $request->input('department_id', '');
    $performanceCategory = $request->input('performance_category', '');
    $cycleId             = $request->input('cycle_id', '');

    // Default to latest open cycle
    if (!$cycleId) {
        $cycleId = AppraisalCycle::where('status', 'open')
            ->orderByDesc('start_date')
            ->value('id') ?? '';
    }

    $query = PerformanceMetric::with([
        'employee.profile:id,first_name,last_name',
        'employee.department:id,name',
    ]);

    if ($cycleId)             $query->where('appraisal_cycle_id', $cycleId);
    if ($departmentId)        $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
    if ($performanceCategory) $query->where('performance_category', $performanceCategory);

    $metrics = $query->get()->map(function ($m) {
        return [
            'employee_id'          => $m->employee_id,
            'employee_name'        => $m->employee?->profile?->first_name . ' ' . $m->employee?->profile?->last_name,
            'employee_number'      => $m->employee?->employee_number,
            'department_id'        => $m->employee?->department_id,
            'department_name'      => $m->employee?->department?->name,
            'overall_score'        => (float) $m->overall_score,
            'attendance_rate'      => (float) ($m->attendance_rate ?? 0),
            'behavior_score'       => (float) ($m->behavior_score ?? 0),
            'productivity_score'   => (float) ($m->productivity_score ?? 0),
            'performance_category' => $m->performance_category,
            'trend'                => $m->trend,
        ];
    });

    // Summary stats
    $count       = $metrics->count();
    $summary = [
        'average_score'   => $count > 0 ? round($metrics->avg('overall_score'), 2) : 0,
        'high_performers' => $metrics->whereIn('performance_category', ['excellent', 'high'])->count(),
        'low_performers'  => $metrics->whereIn('performance_category', ['low', 'poor'])->count(),
        'completion_rate' => 100, // all records in this table are from completed appraisals
    ];

    $departments = Department::orderBy('name')->get(['id', 'name'])
        ->map(fn($d) => ['id' => $d->id, 'name' => $d->name]);

    $cycles = AppraisalCycle::orderByDesc('start_date')->get(['id', 'name'])
        ->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

    return Inertia::render('HR/PerformanceMetrics/Index', [
        'metrics'     => $metrics->values(),
        'departments' => $departments,
        'cycles'      => $cycles,
        'summary'     => $summary,
        'filters'     => compact('departmentId', 'performanceCategory', 'cycleId'),
    ]);
}
```

---

### Task 2.2: Update `show()` Method

```php
public function show($employeeId)
{
    $employee = Employee::with([
        'profile:id,first_name,last_name',
        'department:id,name',
    ])->findOrFail($employeeId);

    // All metrics for this employee, newest first
    $allMetrics = PerformanceMetric::where('employee_id', $employeeId)
        ->with('cycle:id,name,end_date')
        ->orderByDesc('computed_at')
        ->get();

    $currentMetric = $allMetrics->first();

    $historicalMetrics = $allMetrics->skip(1)->take(5)->map(fn($m) => [
        'cycle_name'    => $m->cycle?->name,
        'overall_score' => (float) $m->overall_score,
        'cycle_date'    => $m->cycle?->end_date?->format('Y-m-d'),
    ])->values();

    // Department average from current cycle
    $departmentAverage = 0;
    if ($currentMetric) {
        $departmentAverage = PerformanceMetric::where('appraisal_cycle_id', $currentMetric->appraisal_cycle_id)
            ->whereHas('employee', fn($q) => $q->where('department_id', $employee->department_id))
            ->avg('overall_score') ?? 0;
        $departmentAverage = round($departmentAverage, 2);
    }

    return Inertia::render('HR/PerformanceMetrics/Show', [
        'employee' => [
            'id'              => $employee->id,
            'employee_number' => $employee->employee_number,
            'first_name'      => $employee->profile?->first_name,
            'last_name'       => $employee->profile?->last_name,
            'full_name'       => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
            'department_name' => $employee->department?->name,
            'email'           => null, // from profile/user if needed
        ],
        'currentMetric' => $currentMetric ? [
            'employee_id'          => $currentMetric->employee_id,
            'overall_score'        => (float) $currentMetric->overall_score,
            'attendance_rate'      => (float) ($currentMetric->attendance_rate ?? 0),
            'behavior_score'       => (float) ($currentMetric->behavior_score ?? 0),
            'productivity_score'   => (float) ($currentMetric->productivity_score ?? 0),
            'performance_category' => $currentMetric->performance_category,
            'trend'                => $currentMetric->trend,
        ] : null,
        'historicalMetrics'      => $historicalMetrics,
        'departmentAverage'      => $departmentAverage,
        'trend'                  => $currentMetric?->trend ?? 'stable',
        'attendanceCorrelation'  => [], // TODO: integrate timekeeping data
    ]);
}
```

---

### Task 2.3: Update `departmentComparison()`

```php
public function departmentComparison(Request $request)
{
    $cycleId = $request->input('cycle_id', AppraisalCycle::where('status', 'open')->value('id'));

    $comparison = PerformanceMetric::where('appraisal_cycle_id', $cycleId)
        ->with('employee.department:id,name')
        ->get()
        ->groupBy(fn($m) => $m->employee?->department?->name)
        ->map(function ($group, $deptName) {
            $count = $group->count();
            return [
                'name'               => $deptName ?? 'Unknown',
                'average_score'      => $count > 0 ? round($group->avg('overall_score'), 2) : 0,
                'total_employees'    => $count,
                'appraised_employees'=> $count,
                'high_performers'    => $group->whereIn('performance_category', ['excellent', 'high'])->count(),
                'medium_performers'  => $group->where('performance_category', 'medium')->count(),
                'low_performers'     => $group->whereIn('performance_category', ['low', 'poor'])->count(),
            ];
        })
        ->values();

    return Inertia::render('HR/PerformanceMetrics/DepartmentComparison', [
        'comparison' => $comparison,
    ]);
}
```

---

## ⏰ Phase 3: Auto-Recompute on Appraisal Completion

### Task 3.1: Hook into Appraisal Status Change

The most important trigger is: **when an appraisal is marked `completed`**, recompute the metric.

**Option A — In the controller** (simplest, do this first):

In `AppraisalController::updateStatus()`, after updating status to `completed`:
```php
if ($validated['status'] === 'completed') {
    app(\App\Services\HR\PerformanceMetricService::class)->computeAndStore($appraisal->fresh());
}
```

**Option B — Observer** (cleaner long-term):

Create `app/Observers/AppraisalObserver.php`:
```php
public function updated(Appraisal $appraisal): void
{
    if ($appraisal->isDirty('status') && $appraisal->status === 'completed') {
        app(PerformanceMetricService::class)->computeAndStore($appraisal);
    }
}
```

Register in `AppServiceProvider::boot()`:
```php
Appraisal::observe(AppraisalObserver::class);
```

### Task 3.2: Bulk Recompute Command (optional)

Useful to rebuild all metrics from scratch:

```bash
php artisan make:command RecomputePerformanceMetrics
```

```php
// In handle():
Appraisal::whereIn('status', ['completed', 'acknowledged'])->each(function ($appraisal) {
    app(PerformanceMetricService::class)->computeAndStore($appraisal);
});
$this->info('Performance metrics recomputed.');
```

---

## ✅ Acceptance Criteria

- [ ] `performance_metrics` table exists and is populated from completed appraisals
- [ ] Index page loads real employee metrics (not hardcoded)
- [ ] Filters by department and performance category work
- [ ] Summary stats (avg score, high/low counts) are computed from DB
- [ ] Show page displays an employee's history across multiple cycles
- [ ] Department comparison uses real aggregated data
- [ ] Completing an appraisal automatically creates/updates a `performance_metrics` record

---

## 📁 Files to Create/Modify

| Action | File |
|--------|------|
| 🆕 Create | `database/migrations/2026_03_06_100400_create_performance_metrics_table.php` |
| 🆕 Create | `app/Models/PerformanceMetric.php` |
| 🆕 Create | `app/Services/HR/PerformanceMetricService.php` |
| 🆕 Create | `database/seeders/PerformanceMetricSeeder.php` |
| 🆕 Create (optional) | `app/Observers/AppraisalObserver.php` |
| ✏️ Modify | `app/Http/Controllers/HR/Appraisal/PerformanceMetricsController.php` |
| ✏️ Modify (optional) | `app/Http/Controllers/HR/Appraisal/AppraisalController.php` (trigger recompute on complete) |
