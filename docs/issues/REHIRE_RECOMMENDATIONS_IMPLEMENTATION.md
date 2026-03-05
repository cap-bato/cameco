# Rehire Recommendations - Backend Implementation Plan

**Page:** `http://localhost:8000/hr/rehire-recommendations`  
**Status:** 🔴 MOCK DATA — No database tables exist  
**Created:** 2026-03-05  
**Last Updated:** 2026-03-05

## Phase Progress
- [ ] **Phase 1 Task 1.1:** Database Migration — `rehire_recommendations` table
- [ ] **Phase 1 Task 1.2:** Eloquent Model (`RehireRecommendation`)
- [ ] **Phase 1 Task 1.3:** Database Seeder
- [ ] **Phase 2 Task 2.1:** Create `RehireRecommendationService` (auto-generate logic)
- [ ] **Phase 2 Task 2.2:** Update `RehireRecommendationController::index()`
- [ ] **Phase 2 Task 2.3:** Update `RehireRecommendationController::show()`
- [ ] **Phase 2 Task 2.4:** Update `RehireRecommendationController::override()`
- [ ] **Phase 2 Task 2.5:** Implement `bulkApprove()` endpoint
- [ ] **Phase 3 Task 3.1:** Auto-generate recommendations when appraisal cycle closes

---

## 📋 Summary

The Rehire Recommendations page tracks whether a departed (or completing) employee is eligible for rehire. The system **auto-generates** a recommendation based on appraisal score + attendance rate + violation count. HR Managers can **override** the system recommendation with a written justification.

**Controller:** `app/Http/Controllers/HR/Appraisal/RehireRecommendationController.php`  
**Frontend Pages:**
- `resources/js/pages/HR/RehireRecommendations/Index.tsx` — List with filters
- `resources/js/pages/HR/RehireRecommendations/Show.tsx` — Detail with appraisal breakdown + override history

---

## 📊 Current State Analysis

### Auto-Recommendation Business Rules (from existing controller)

| Condition | System Recommendation |
|-----------|----------------------|
| Score ≥ 7.5 AND Attendance ≥ 90% AND Violations = 0 | `eligible` |
| Score < 5.0 OR Violations > 3 | `not_recommended` |
| All other cases | `review_required` |

### Each recommendation record shape (what `Index.tsx` expects)
```ts
{
    id:                  number
    employee_id:         number
    employee_name:       string
    employee_number:     string
    department_id:       number
    department_name:     string
    appraisal_id:        number
    overall_score:       number
    attendance_rate:     number
    violation_count:     number
    recommendation:      'eligible' | 'not_recommended' | 'review_required'
    system_recommendation: string     // original system-generated value
    is_overridden:       boolean
    overridden_by:       string | null
    override_notes:      string | null
    created_at:          string
    updated_at:          string
}
```

### What `Show.tsx` expects (detail view)
```ts
{
    recommendation: { ...above + violations: Array<{ id, type, description, severity, occurred_at }> }
    appraisal: {
        id:            number
        overall_score: number
        scores:        Array<{ criterion, score }>
    }
    employee: { id, employee_number, first_name, last_name, full_name, department_name, email }
    attendanceMetrics: { attendance_rate, lateness_count, violation_count }
    overrideHistory: Array<{ id, action, reason, created_by, created_at }>
}
```

### Problems
- ❌ No `rehire_recommendations` table
- ❌ All data is hardcoded — no DB reads or writes
- ❌ `override()` method does nothing
- ❌ Override history is not tracked
- ❌ Violations come from a mock generator — no real source

---

## 🗄️ Database Schema

### Table: `rehire_recommendations`
```sql
CREATE TABLE rehire_recommendations (
    id                     BIGSERIAL PRIMARY KEY,
    employee_id            BIGINT NOT NULL REFERENCES employees(id),
    appraisal_cycle_id     BIGINT NOT NULL REFERENCES appraisal_cycles(id),
    appraisal_id           BIGINT NOT NULL REFERENCES appraisals(id),

    -- Scores snapshot at time of generation
    overall_score          DECIMAL(4,2) NULL,
    attendance_rate        DECIMAL(5,2) NULL,
    violation_count        INT NOT NULL DEFAULT 0,

    -- Recommendations
    system_recommendation  VARCHAR(30) NOT NULL,   -- eligible|not_recommended|review_required
    recommendation         VARCHAR(30) NOT NULL,   -- current effective recommendation (after any override)

    -- Override tracking
    is_overridden          BOOLEAN NOT NULL DEFAULT FALSE,
    overridden_by          BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    override_notes         TEXT NULL,
    overridden_at          TIMESTAMP NULL,

    created_at             TIMESTAMP,
    updated_at             TIMESTAMP,

    UNIQUE (employee_id, appraisal_cycle_id)       -- one recommendation per employee per cycle
);
```

### Table: `rehire_recommendation_overrides` (audit trail)
```sql
CREATE TABLE rehire_recommendation_overrides (
    id                        BIGSERIAL PRIMARY KEY,
    rehire_recommendation_id  BIGINT NOT NULL REFERENCES rehire_recommendations(id) ON DELETE CASCADE,
    previous_recommendation   VARCHAR(30) NOT NULL,
    new_recommendation        VARCHAR(30) NOT NULL,
    reason                    TEXT NOT NULL,
    overridden_by             BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at                TIMESTAMP
);
```

> **Note on violations:** The current mock uses inline violation types. Real violations should come from a disciplinary records table (e.g., `employee_remarks` or a dedicated `disciplinary_records` table). For now, `violation_count` is a snapshot integer stored on the recommendation. Full violation detail is a future integration.

---

## 🏗️ Phase 1: Database Setup

### Task 1.1: Create Migration

**File:** `database/migrations/2026_03_06_100500_create_rehire_recommendations_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehire_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles');
            $table->foreignId('appraisal_id')->constrained('appraisals');
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->decimal('attendance_rate', 5, 2)->nullable();
            $table->unsignedInteger('violation_count')->default(0);
            $table->string('system_recommendation', 30);
            $table->string('recommendation', 30);
            $table->boolean('is_overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_notes')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'appraisal_cycle_id']);
            $table->index('recommendation');
        });

        Schema::create('rehire_recommendation_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehire_recommendation_id')
                ->constrained('rehire_recommendations')
                ->cascadeOnDelete();
            $table->string('previous_recommendation', 30);
            $table->string('new_recommendation', 30);
            $table->text('reason');
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehire_recommendation_overrides');
        Schema::dropIfExists('rehire_recommendations');
    }
};
```

Run: `php artisan migrate --path=database/migrations/2026_03_06_100500_create_rehire_recommendations_table.php`

---

### Task 1.2: Create Eloquent Models

**File:** `app/Models/RehireRecommendation.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RehireRecommendation extends Model
{
    protected $fillable = [
        'employee_id', 'appraisal_cycle_id', 'appraisal_id',
        'overall_score', 'attendance_rate', 'violation_count',
        'system_recommendation', 'recommendation',
        'is_overridden', 'overridden_by', 'override_notes', 'overridden_at',
    ];

    protected $casts = [
        'overall_score'   => 'decimal:2',
        'attendance_rate' => 'decimal:2',
        'is_overridden'   => 'boolean',
        'overridden_at'   => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function overriddenByUser()
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function overrideHistory()
    {
        return $this->hasMany(RehireRecommendationOverride::class)->orderByDesc('created_at');
    }
}
```

**File:** `app/Models/RehireRecommendationOverride.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RehireRecommendationOverride extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rehire_recommendation_id', 'previous_recommendation',
        'new_recommendation', 'reason', 'overridden_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function recommendation()
    {
        return $this->belongsTo(RehireRecommendation::class, 'rehire_recommendation_id');
    }

    public function overriddenByUser()
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
```

---

### Task 1.3: Database Seeder

**File:** `database/seeders/RehireRecommendationSeeder.php`

```php
<?php
namespace Database\Seeders;

use App\Models\Appraisal;
use App\Services\HR\RehireRecommendationService;
use Illuminate\Database\Seeder;

class RehireRecommendationSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(RehireRecommendationService::class);

        // Generate recommendations for all completed appraisals
        Appraisal::whereIn('status', ['completed', 'acknowledged'])->each(function ($appraisal) use ($service) {
            $service->generateForAppraisal($appraisal);
        });
    }
}
```

---

## 🔧 Phase 2: Service + Controller Updates

### Task 2.1: Create `RehireRecommendationService`

**File:** `app/Services/HR/RehireRecommendationService.php`

```php
<?php
namespace App\Services\HR;

use App\Models\Appraisal;
use App\Models\RehireRecommendation;
use App\Models\RehireRecommendationOverride;
use Illuminate\Support\Facades\DB;

class RehireRecommendationService
{
    /**
     * Auto-generate (or update) a rehire recommendation from a completed appraisal.
     */
    public function generateForAppraisal(Appraisal $appraisal): RehireRecommendation
    {
        $score          = (float) $appraisal->overall_score;
        $attendanceRate = (float) ($appraisal->employee->attendanceRate ?? 0); // TODO: real timekeeping
        $violationCount = 0; // TODO: real disciplinary records

        $systemRec = $this->computeRecommendation($score, $attendanceRate, $violationCount);

        return RehireRecommendation::updateOrCreate(
            [
                'employee_id'        => $appraisal->employee_id,
                'appraisal_cycle_id' => $appraisal->appraisal_cycle_id,
            ],
            [
                'appraisal_id'          => $appraisal->id,
                'overall_score'         => $score,
                'attendance_rate'       => $attendanceRate,
                'violation_count'       => $violationCount,
                'system_recommendation' => $systemRec,
                'recommendation'        => $systemRec, // no override yet
                'is_overridden'         => false,
            ]
        );
    }

    /**
     * Apply the business rules to compute a recommendation.
     */
    public function computeRecommendation(float $score, float $attendanceRate, int $violationCount): string
    {
        if ($score >= 7.5 && $attendanceRate >= 90 && $violationCount === 0) {
            return 'eligible';
        }

        if ($score < 5.0 || $violationCount > 3) {
            return 'not_recommended';
        }

        return 'review_required';
    }

    /**
     * Apply an HR override to an existing recommendation.
     */
    public function override(RehireRecommendation $rec, string $newRecommendation, string $notes, int $userId): void
    {
        DB::transaction(function () use ($rec, $newRecommendation, $notes, $userId) {
            // Log to override history
            RehireRecommendationOverride::create([
                'rehire_recommendation_id' => $rec->id,
                'previous_recommendation'  => $rec->recommendation,
                'new_recommendation'       => $newRecommendation,
                'reason'                   => $notes,
                'overridden_by'            => $userId,
            ]);

            // Update the recommendation
            $rec->update([
                'recommendation' => $newRecommendation,
                'is_overridden'  => true,
                'overridden_by'  => $userId,
                'override_notes' => $notes,
                'overridden_at'  => now(),
            ]);
        });
    }
}
```

---

### Task 2.2: Update `index()` Method

```php
use App\Models\RehireRecommendation;
use App\Models\Department;

public function index(Request $request)
{
    $recommendation = $request->input('recommendation', '');
    $departmentId   = $request->input('department_id', '');
    $search         = $request->input('search', '');

    $query = RehireRecommendation::with([
        'employee.profile:id,first_name,last_name',
        'employee.department:id,name',
        'cycle:id,name',
        'overriddenByUser:id,name',
    ]);

    if ($recommendation) $query->where('recommendation', $recommendation);
    if ($departmentId)   $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
    if ($search) {
        $query->whereHas('employee', function ($q) use ($search) {
            $q->where('employee_number', 'ILIKE', "%{$search}%")
              ->orWhereHas('profile', fn($pq) => $pq->whereRaw(
                  "CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]
              ));
        });
    }

    $recommendations = $query->latest()->get()->map(function ($r) {
        return [
            'id'                    => $r->id,
            'employee_id'           => $r->employee_id,
            'employee_name'         => $r->employee?->profile?->first_name . ' ' . $r->employee?->profile?->last_name,
            'employee_number'       => $r->employee?->employee_number,
            'department_id'         => $r->employee?->department_id,
            'department_name'       => $r->employee?->department?->name,
            'appraisal_id'          => $r->appraisal_id,
            'overall_score'         => (float) $r->overall_score,
            'attendance_rate'       => (float) ($r->attendance_rate ?? 0),
            'violation_count'       => $r->violation_count,
            'recommendation'        => $r->recommendation,
            'system_recommendation' => $r->system_recommendation,
            'is_overridden'         => $r->is_overridden,
            'overridden_by'         => $r->overriddenByUser?->name,
            'override_notes'        => $r->override_notes,
            'created_at'            => $r->created_at?->toDateTimeString(),
            'updated_at'            => $r->updated_at?->toDateTimeString(),
        ];
    });

    $departments = Department::orderBy('name')->get(['id', 'name'])
        ->map(fn($d) => ['id' => $d->id, 'name' => $d->name]);

    return Inertia::render('HR/RehireRecommendations/Index', [
        'recommendations' => $recommendations,
        'departments'     => $departments,
        'filters'         => compact('recommendation', 'departmentId', 'search'),
    ]);
}
```

---

### Task 2.3: Update `show()` Method

```php
public function show($id)
{
    $rec = RehireRecommendation::with([
        'employee.profile',
        'employee.department:id,name',
        'appraisal.scores.criteria:id,name,weight',
        'overriddenByUser:id,name',
        'overrideHistory.overriddenByUser:id,name',
    ])->findOrFail($id);

    return Inertia::render('HR/RehireRecommendations/Show', [
        'recommendation' => [
            'id'                    => $rec->id,
            'employee_id'           => $rec->employee_id,
            'employee_name'         => $rec->employee?->profile?->first_name . ' ' . $rec->employee?->profile?->last_name,
            'employee_number'       => $rec->employee?->employee_number,
            'department_id'         => $rec->employee?->department_id,
            'department_name'       => $rec->employee?->department?->name,
            'overall_score'         => (float) $rec->overall_score,
            'attendance_rate'       => (float) ($rec->attendance_rate ?? 0),
            'violation_count'       => $rec->violation_count,
            'recommendation'        => $rec->recommendation,
            'system_recommendation' => $rec->system_recommendation,
            'is_overridden'         => $rec->is_overridden,
            'overridden_by'         => $rec->overriddenByUser?->name,
            'override_notes'        => $rec->override_notes,
            'violations'            => [], // TODO: integrate disciplinary records
            'created_at'            => $rec->created_at?->toDateTimeString(),
            'updated_at'            => $rec->updated_at?->toDateTimeString(),
        ],
        'appraisal' => [
            'id'            => $rec->appraisal?->id,
            'overall_score' => (float) $rec->appraisal?->overall_score,
            'scores'        => $rec->appraisal?->scores->map(fn($s) => [
                'criterion' => $s->criteria?->name,
                'score'     => (float) $s->score,
            ])->values() ?? [],
        ],
        'employee' => [
            'id'              => $rec->employee->id,
            'employee_number' => $rec->employee->employee_number,
            'first_name'      => $rec->employee->profile?->first_name,
            'last_name'       => $rec->employee->profile?->last_name,
            'full_name'       => $rec->employee->profile?->first_name . ' ' . $rec->employee->profile?->last_name,
            'department_id'   => $rec->employee->department_id,
            'department_name' => $rec->employee->department?->name,
            'email'           => null,
        ],
        'attendanceMetrics' => [
            'attendance_rate' => (float) ($rec->attendance_rate ?? 0),
            'lateness_count'  => 0,   // TODO: timekeeping
            'violation_count' => $rec->violation_count,
        ],
        'overrideHistory' => $rec->overrideHistory->map(fn($o) => [
            'id'         => $o->id,
            'action'     => "Changed from '{$o->previous_recommendation}' to '{$o->new_recommendation}'",
            'reason'     => $o->reason,
            'created_by' => $o->overriddenByUser?->name,
            'created_at' => $o->created_at?->toDateTimeString(),
        ])->values(),
    ]);
}
```

---

### Task 2.4: Update `override()` Method

```php
use App\Services\HR\RehireRecommendationService;

public function override(Request $request, $id)
{
    $rec = RehireRecommendation::findOrFail($id);

    $validated = $request->validate([
        'recommendation' => 'required|in:eligible,not_recommended,review_required',
        'notes'          => 'required|string|min:10|max:1000',
    ]);

    app(RehireRecommendationService::class)->override(
        $rec,
        $validated['recommendation'],
        $validated['notes'],
        auth()->id()
    );

    return back()->with('success', 'Rehire recommendation updated successfully.');
}
```

---

### Task 2.5: Implement `bulkApprove()` Method

The frontend may send bulk actions. Add this method:

```php
public function bulkUpdate(Request $request)
{
    $validated = $request->validate([
        'ids'            => 'required|array|min:1',
        'ids.*'          => 'required|integer|exists:rehire_recommendations,id',
        'recommendation' => 'required|in:eligible,not_recommended,review_required',
        'notes'          => 'required|string|min:10|max:500',
    ]);

    $service = app(RehireRecommendationService::class);
    $recs    = RehireRecommendation::whereIn('id', $validated['ids'])->get();

    foreach ($recs as $rec) {
        $service->override($rec, $validated['recommendation'], $validated['notes'], auth()->id());
    }

    return back()->with('success', count($recs) . ' recommendations updated.');
}
```

**Add route in `hr.php`:**
```php
Route::post('/rehire-recommendations/bulk-update', [RehireRecommendationController::class, 'bulkUpdate'])
    ->middleware('permission:hr.appraisals.manage')
    ->name('rehire-recommendations.bulk-update');
```

---

## ⏰ Phase 3: Auto-Generate on Cycle Close

### Task 3.1: Hook into Appraisal Completion

When an appraisal is marked `completed`, auto-generate the rehire recommendation:

**In `AppraisalController::updateStatus()`** (add after status update):
```php
if ($validated['status'] === 'completed' && $appraisal->overall_score !== null) {
    app(\App\Services\HR\RehireRecommendationService::class)
        ->generateForAppraisal($appraisal->fresh());
}
```

Or via the **AppraisalObserver** (if you created one for PerformanceMetrics):
```php
public function updated(Appraisal $appraisal): void
{
    if ($appraisal->isDirty('status') && $appraisal->status === 'completed') {
        app(PerformanceMetricService::class)->computeAndStore($appraisal);
        app(RehireRecommendationService::class)->generateForAppraisal($appraisal);
    }
}
```

---

## 📝 Notes on Violation Data

The current mock generates inline violation descriptions. Real violation data should come from a disciplinary records table (check if `employee_remarks` contains disciplinary entries or if a separate table exists).

For now:
- Store `violation_count` as a simple integer snapshot on the recommendation
- Pass `violations: []` to the Show page frontend
- When disciplinary records are implemented, query them and pass back a real violations array

---

## ✅ Acceptance Criteria

- [ ] `rehire_recommendations` table exists and is populated from completed appraisals
- [ ] List page shows real recommendations from database
- [ ] Filters (recommendation type, department, search) work against real data
- [ ] Show page displays correct appraisal score breakdown
- [ ] Override updates the `recommendation` field and logs to `rehire_recommendation_overrides`
- [ ] Override history is shown on the Show page
- [ ] Completing an appraisal automatically generates a rehire recommendation
- [ ] System recommendation is preserved separately from the (potentially overridden) current recommendation

---

## 📁 Files to Create/Modify

| Action | File |
|--------|------|
| 🆕 Create | `database/migrations/2026_03_06_100500_create_rehire_recommendations_table.php` |
| 🆕 Create | `app/Models/RehireRecommendation.php` |
| 🆕 Create | `app/Models/RehireRecommendationOverride.php` |
| 🆕 Create | `app/Services/HR/RehireRecommendationService.php` |
| 🆕 Create | `database/seeders/RehireRecommendationSeeder.php` |
| ✏️ Modify | `app/Http/Controllers/HR/Appraisal/RehireRecommendationController.php` |
| ✏️ Modify | `routes/hr.php` (add bulk-update route) |
| ✏️ Modify (optional) | `app/Http/Controllers/HR/Appraisal/AppraisalController.php` (trigger generation) |
