# ATS Module — Bug Fix Implementation Plan

**Module:** Applicant Tracking System (HR/ATS)
**Date:** 2026-03-19
**Severity Legend:** 🔴 Critical · 🟠 High · 🟡 Medium

---

## Executive Summary

A full audit of the ATS module identified **30 confirmed bugs** across controllers, models, migrations, seeders, routes, and TypeScript types. They are organized below into four phases in order of blast-radius. Fixing Phase 1 alone will unblock the most broken user-facing flows.

---

## Phase 1 — Critical Runtime Errors

These cause HTTP 500s, silent data loss, or complete feature breakage.

---

### BUG-01 🔴 `ApplicationController::move()` uses a CLI helper instead of returning a response

**File:** `app/Http/Controllers/HR/ATS/ApplicationController.php:252`

**Problem:**
```php
use function Laravel\Prompts\alert;
// ...
alert('Success', 'Application moved successfully.', 'success'); // line 252
// function ends with no return statement
```
`alert()` is a Laravel Prompts helper for CLI output. In an HTTP context it does nothing and the function returns `null`, so the Kanban drag-and-drop gets no response and fails silently.

**Fix:**
Remove the `use function Laravel\Prompts\alert;` import (line 16) and add a proper return at the end of `move()`:

```php
// Remove line 16:
// use function Laravel\Prompts\alert;

public function move(Request $request, Application $application)
{
    $validated = $request->validate([
        'status' => 'required|in:submitted,shortlisted,interviewed,offered,hired,rejected,withdrawn',
        'notes'  => 'nullable|string|max:1000',
    ]);

    $application->status = $validated['status'];
    $application->save();

    ApplicationStatusHistory::create([
        'application_id' => $application->id,
        'status'         => $validated['status'],
        'changed_by'     => Auth::id(),
        'notes'          => $validated['notes'] ?? null,
    ]);

    return response()->json(['success' => true, 'status' => $validated['status']]);
}
```

---

### BUG-02 🔴 Routes reference `InterviewController` methods that don't exist

**File:** `routes/hr.php:458–466`

**Problem:**
```php
Route::post('/interviews/{id}/feedback', [InterviewController::class, 'addFeedback']);   // line 458
Route::post('/interviews/{id}/complete', [InterviewController::class, 'markCompleted']); // line 464
```
Neither `addFeedback` nor `markCompleted` exist in `InterviewController`. The existing method is named `updateFeedback` (line 191), and there is no `markCompleted` at all. Both routes throw HTTP 500 on every request.

**Fix:**
Rename the route to match the existing method, and implement `markCompleted`:

```php
// routes/hr.php — change line 458:
Route::post('/interviews/{id}/feedback', [InterviewController::class, 'updateFeedback'])
    ->middleware('permission:hr.ats.interviews.schedule')
    ->name('interviews.feedback');

// Add to InterviewController:
public function markCompleted(Request $request, Interview $interview)
{
    $validated = $request->validate([
        'score'          => 'nullable|numeric|min:0|max:100',
        'recommendation' => 'nullable|in:hire,no_hire,hold',
        'feedback'       => 'nullable|string',
    ]);

    $interview->update(array_merge($validated, ['status' => 'completed']));

    return response()->json(['success' => true, 'interview' => $interview]);
}
```

---

### BUG-03 🔴 `Candidate::$fillable` doesn't include its own table columns

**File:** `app/Models/Candidate.php:12–18`

**Problem:**
```php
protected $fillable = [
    'profile_id',   // FK that doesn't exist in migration
    'source',
    'status',
    'applied_at',
    'notes',        // column doesn't exist in migration either
];
```
The `candidates` migration (`2025_11_27_113507`) defines `first_name`, `last_name`, `middle_name`, `email`, `phone`, `address`, `birthdate`, `gender`, `department_id`, `position_id`, `resume_path`, `source`, `status`, `applied_at`. The model's `$fillable` is misaligned: `profile_id` and `notes` don't exist as columns, and all real columns except `source`/`status`/`applied_at` are missing. `Candidate::create()` and `Candidate::firstOrCreate()` silently drop all unguarded data.

**Fix:**
```php
protected $fillable = [
    'first_name',
    'last_name',
    'middle_name',
    'email',
    'phone',
    'address',
    'birthdate',
    'gender',
    'department_id',
    'position_id',
    'resume_path',
    'source',
    'status',
    'applied_at',
];
```
Also remove the `profile()` and `position()` relationships if they have no backing foreign keys in the actual schema, or add the FK columns via migration. The `statusHistory()` relationship on `Candidate` (line 41) should point to `CandidateStatusHistory`, not `ApplicationStatusHistory`.

---

### BUG-04 🔴 Interview status enum — `'canceled'` vs `'cancelled'` mismatch

**Files:**
- `database/migrations/2025_11_28_114048_create_interviews_table.php:19` — DB enum: `'canceled'`
- `app/Http/Controllers/HR/ATS/InterviewController.php:67` — counts `'canceled'`
- `app/Http/Controllers/HR/ATS/InterviewController.php:147` — saves `'cancelled'` ← different spelling
- `resources/js/types/ats-pages.ts:37` — TypeScript: `'cancelled'`

**Problem:**
`InterviewController::cancel()` writes `'cancelled'` to a column whose enum only allows `'canceled'`. MySQL will throw a data truncation error; SQLite may silently accept it but the value won't match future queries that filter on `'canceled'`.

**Fix — Option A (recommended): Change the migration enum to match the rest of the codebase.**
Create a new migration:
```php
// New migration: xxxx_fix_interviews_status_enum.php
Schema::table('interviews', function (Blueprint $table) {
    $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])
          ->default('scheduled')
          ->change();
});
```
Also add `no_show` which the controller already queries (line 68) but the enum doesn't include.

**Fix — Option B: standardise on `'canceled'` everywhere and update the TypeScript type.**

---

### BUG-05 🔴 Interview `location_type` form/DB enum mismatch

**Files:**
- `database/migrations/2025_11_28_114048_create_interviews_table.php:18` — DB enum: `['office', 'virtual']`
- `resources/js/types/ats-pages.ts:60` — TypeScript: `'office' | 'video_call' | 'phone'`
- `app/Http/Controllers/HR/ATS/ApplicationController.php:195` — backend validate: `'office,virtual'`
- `app/Http/Controllers/HR/ATS/InterviewController.php:97` — backend validate: `'office,virtual'`

**Problem:**
Frontend sends `'video_call'` or `'phone'`; backend validation rejects both with a 422, and even if it didn't, the DB enum would reject them.

**Fix — Standardise on three values everywhere:**
```php
// New migration:
$table->enum('location_type', ['office', 'video_call', 'phone'])->change();

// Both controllers — update validation rule:
'location_type' => 'required|in:office,video_call,phone',
```

---

### BUG-06 🔴 Interview `recommendation` enum mismatch

**Files:**
- `database/migrations/2025_11_28_114048_create_interviews_table.php:22` — DB enum: `['hire', 'no_hire', 'hold']`
- `resources/js/types/ats-pages.ts:55` — TypeScript: `'hire' | 'pending' | 'reject'`
- `app/Http/Controllers/HR/ATS/InterviewController.php:128` — validation: `'hire,pending,reject'`

**Problem:**
All three layers use different values. Saving `'pending'` or `'reject'` via the form hits a DB constraint error. Saving `'no_hire'` or `'hold'` from a direct query never renders correctly on the frontend.

**Fix — Standardise on the DB enum as source of truth:**
```php
// Migration change:
$table->enum('recommendation', ['hire', 'no_hire', 'hold'])->nullable()->change();

// InterviewController::update() validation:
'recommendation' => 'nullable|in:hire,no_hire,hold',
```
```ts
// ats-pages.ts line 55:
export type InterviewRecommendation = 'hire' | 'no_hire' | 'hold';
```

---

## Phase 2 — Broken Data Flows & Missing Routes

These cause 404s, missing data, or features that appear to work but persist nothing.

---

### BUG-07 🟠 Missing routes: `applications.move`, `schedule-interview`, `generate-offer`, `applications.notes.store`

**File:** `routes/hr.php`

**Problem:**
`ApplicationController` has methods `move()`, `scheduleInterview()`, `generateOffer()` but none have routes. Frontend components POST to these endpoints and get 404.

**Fix — add to the applications route group in `hr.php`:**
```php
Route::post('/applications/{id}/move', [ApplicationController::class, 'move'])
    ->middleware('permission:hr.ats.applications.update')
    ->name('applications.move');

Route::post('/applications/{id}/schedule-interview', [ApplicationController::class, 'scheduleInterview'])
    ->middleware('permission:hr.ats.applications.update')
    ->name('applications.schedule-interview');

Route::post('/applications/{id}/generate-offer', [ApplicationController::class, 'generateOffer'])
    ->middleware('permission:hr.ats.applications.update')
    ->name('applications.generate-offer');

Route::post('/applications/{id}/notes', [ApplicationController::class, 'addNote'])
    ->middleware('permission:hr.ats.applications.update')
    ->name('applications.notes.store');
```
Then implement `addNote()` in `ApplicationController`:
```php
public function addNote(Request $request, Application $application)
{
    $validated = $request->validate([
        'note'       => 'required|string',
        'is_private' => 'boolean',
    ]);

    $application->notes()->create([
        'note'       => $validated['note'],
        'is_private' => $validated['is_private'] ?? false,
        'user_id'    => Auth::id(),
    ]);

    return back()->with('success', 'Note added.');
}
```

---

### BUG-08 🟠 `ApplicationController::scheduleInterview()` location_type validation is `'office,virtual'` but store validates `'office,video_call,phone'`

**File:** `app/Http/Controllers/HR/ATS/ApplicationController.php:195`

Already captured under BUG-05. The fix there also covers this controller.

---

### BUG-09 🟠 `ApplicationShowProps` type expects separate `candidate` and `job` props, but controller embeds them inside `application`

**File:** `resources/js/types/ats-pages.ts:423–430`

**Problem:**
```ts
// Type declares:
export interface ApplicationShowProps {
    application: Application;
    candidate: Candidate;   // ← expects separate prop
    job: JobPosting;        // ← expects separate prop
    ...
}
```
But `ApplicationController::show()` returns:
```php
return Inertia::render('HR/ATS/Applications/Show', [
    'application' => [ /* candidate_name, candidate_email embedded */ ],
    // no separate 'candidate' or 'job' keys
]);
```
The Show page will get `undefined` for `candidate` and `job`.

**Fix — Two options:**
A) Update the controller to pass `candidate` and `job` as separate props:
```php
return Inertia::render('HR/ATS/Applications/Show', [
    'application'    => $application,
    'candidate'      => $application->candidate,
    'job'            => $application->jobPosting,
    'interviews'     => $application->interviews,
    'status_history' => $application->statusHistory,
    'notes'          => $application->notes,
    'can_schedule_interview' => $canScheduleInterview,
    'can_generate_offer'     => $canGenerateOffer,
]);
```
B) Update the TypeScript interface to remove separate `candidate`/`job` and read from embedded `application`. Option A is recommended as it matches the declared type.

---

### BUG-10 🟠 `ApplicationController::show()` remaps statuses that no longer exist in the DB

**File:** `app/Http/Controllers/HR/ATS/ApplicationController.php:105–109`

**Problem:**
```php
'status' => match($application->status) {
    'new' => 'submitted',
    'in_process' => 'shortlisted',
    default => $application->status,
},
```
The applications table default is `'submitted'` and no migration sets `'new'` or `'in_process'` as valid values. This dead code confuses the status history timeline displayed to users.

**Fix:**
Remove the `match()` remapping block. Pass `$application->status` directly.

---

### BUG-11 🟠 `CandidateSummary` type is missing `offered` and `rejected` fields

**File:** `resources/js/types/ats-pages.ts:305–311`

**Problem:**
```ts
export interface CandidateSummary {
    total_candidates: number;
    new_candidates: number;
    in_process: number;
    interviewed: number;
    hired: number;
    // ← missing: offered, rejected
}
```
`CandidateController::index()` returns `offered` and `rejected` counts in `statistics`. The frontend will silently get `undefined` for these values when rendering stats cards.

**Fix:**
```ts
export interface CandidateSummary {
    total_candidates: number;
    new_candidates: number;
    in_process: number;
    interviewed: number;
    offered: number;
    hired: number;
    rejected: number;
}
```

---

### BUG-12 🟠 `InterviewSummary` type doesn't match what the controller returns

**File:** `resources/js/types/ats-pages.ts:329–336`

**Problem:**
```ts
export interface InterviewSummary {
    total_interviews: number;
    scheduled: number;
    completed: number;
    today: number;           // ← controller doesn't return this
    this_week: number;       // ← controller returns 'upcoming_this_week'
    cancelled: number;
    // ← missing: no_show
}
```
`InterviewController::index()` returns `no_show` and `upcoming_this_week` — not `today` or `this_week`.

**Fix — align the type to controller output:**
```ts
export interface InterviewSummary {
    total_interviews: number;
    scheduled: number;
    completed: number;
    cancelled: number;
    no_show: number;
    upcoming_this_week: number;
}
```

---

### BUG-13 🟠 `ApplicationFilters` type uses `score_min`/`score_max` but controller reads `min_score`/`max_score`

**File:** `resources/js/types/ats-pages.ts:256–264`

**Problem:**
```ts
export interface ApplicationFilters extends CommonFilters {
    score_min?: number;   // ← frontend key
    score_max?: number;   // ← frontend key
}
```
`ApplicationController::index()` reads:
```php
$minScore = $request->input('min_score');  // ← different key
$maxScore = $request->input('max_score');
```
Score filters never apply.

**Fix — standardise on `min_score`/`max_score`:**
```ts
export interface ApplicationFilters extends CommonFilters {
    status?: ApplicationStatus | '';
    job_id?: number | '';
    source?: CandidateSource | '';
    min_score?: number;
    max_score?: number;
    date_from?: string;
    date_to?: string;
}
```

---

### BUG-14 🟠 `ApplicationsIndexProps` expects `PaginatedData<Application>` but controller returns a plain array

**File:** `resources/js/types/ats-pages.ts:413–418`

**Problem:**
```ts
export interface ApplicationsIndexProps {
    applications: PaginatedData<Application>; // ← expects pagination wrapper
```
`ApplicationController::index()` calls `.get()` (not `.paginate()`), returning a plain array. The frontend will crash trying to read `.data`, `.links`, `.meta` from a flat array.

**Fix — either paginate in the controller:**
```php
->paginate(20)
```
Or update the type to `Application[]` if pagination is not needed yet.

---

### BUG-15 🟠 `CandidatesIndexProps` expects `PaginatedData<Candidate>` but controller returns a plain array

**File:** `resources/js/types/ats-pages.ts:389–393`

Same issue as BUG-14. `CandidateController::index()` uses `.get()`, not `.paginate()`.

**Fix — same as BUG-14.**

---

### BUG-16 🟠 `InterviewController::index()` uses `ilike` — PostgreSQL-only operator

**File:** `app/Http/Controllers/HR/ATS/InterviewController.php:40–41`

**Problem:**
```php
$q->where('first_name', 'ilike', "%{$search}%")
  ->orWhere('last_name', 'ilike', "%{$search}%");
```
`ilike` is a PostgreSQL-specific case-insensitive LIKE. On SQLite (dev) and MySQL (prod) this throws an error.

**Fix:**
```php
$q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($search) . '%'])
  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($search) . '%']);
```

---

### BUG-17 🟠 `InterviewController::index()` unsafe null chain — crashes when application or candidate is deleted

**File:** `app/Http/Controllers/HR/ATS/InterviewController.php:48`

**Problem:**
```php
'candidate_name' => $interview->application->candidate->first_name . ' ' . $interview->application->candidate->last_name,
```
If `application` or `candidate` is soft-deleted or missing, this throws a fatal `Call to member function on null`.

**Fix:**
```php
'candidate_name' => optional(optional($interview->application)->candidate)->first_name
    ? trim($interview->application->candidate->first_name . ' ' . $interview->application->candidate->last_name)
    : 'Unknown',
```

---

### BUG-18 🟠 `InterviewController` — `no_show` status not in DB enum

**File:** `database/migrations/2025_11_28_114048_create_interviews_table.php:19`

The DB enum is `['scheduled', 'completed', 'canceled']`. The controller queries `'no_show'` (line 68) and the TypeScript type includes `'no_show'`. No row will ever match this query because the value can never be written.

**Fix — covered by the enum migration in BUG-04:**
Add `no_show` to the enum: `['scheduled', 'completed', 'cancelled', 'no_show']`.

---

### BUG-19 🟠 Interviews table missing `candidate_id` column but controller accepts and stores it

**File:** `database/migrations/2025_11_28_114048_create_interviews_table.php`

`InterviewController::store()` accepts `candidate_id` (lines 92, 103) and stores it, but the original migration has no such column. There is a referenced add-column migration that may or may not have run.

**Fix — create the migration explicitly if it doesn't exist:**
```php
Schema::table('interviews', function (Blueprint $table) {
    $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
});
```

---

## Phase 3 — Hardcoded Values & Auth Breakage

These silently break audit trails and will fail as soon as user ID 1 doesn't exist.

---

### BUG-20 🟡 All ATS controllers hardcode `user_id = 1` / `changed_by = 1`

**Files & Lines:**
| File | Line | Field |
|---|---|---|
| `CandidateController.php` | 153 | `'user_id' => 1` |
| `ApplicationController.php` | 140 | `'changed_by' => 1` |
| `ApplicationController.php` | 158 | `'changed_by' => 1` |
| `ApplicationController.php` | 179 | `'changed_by' => 1` |
| `ApplicationController.php` | 219 | `'created_by' => 1` |
| `ApplicationController.php` | 248 | `'changed_by' => 1` |
| `JobPostingController.php` | 109 | `'created_by' => '1'` (also wrong type: string) |

**Fix — add `use Illuminate\Support\Facades\Auth;` to each file and replace all occurrences:**
```php
// Replace every instance:
'user_id'    => 1  →  'user_id'    => Auth::id(),
'changed_by' => 1  →  'changed_by' => Auth::id(),
'created_by' => '1' →  'created_by' => Auth::id(),   // also removes the string/int type bug
```

---

### BUG-21 🟡 Job posting routes use `hr.ats.candidates.*` permissions instead of `hr.ats.job-postings.*`

**File:** `routes/hr.php:364–378`

**Problem:**
```php
Route::get('/job-postings/create', [JobPostingController::class, 'create'])
    ->middleware('permission:hr.ats.candidates.create'); // ← wrong permission
```
Job posting CRUD is gated behind candidate permissions. A user with only job-posting permissions gets a 403.

**Fix:**
```php
// Create/Store:
->middleware('permission:hr.ats.job-postings.create')
// Edit/Update:
->middleware('permission:hr.ats.job-postings.update')
// Delete:
->middleware('permission:hr.ats.job-postings.delete')
```
Ensure these permission strings are seeded in the permissions table.

---

### BUG-22 🟡 `CandidateController::destroy()` returns JSON but frontend expects redirect

**File:** `app/Http/Controllers/HR/ATS/CandidateController.php:159–163`

**Problem:**
```php
public function destroy(Candidate $candidate)
{
    $candidate->delete();
    return response()->json(['message' => 'Candidate deleted successfully']);
}
```
Also missing a route in `hr.php` (`candidates.destroy`).

**Fix:**
```php
public function destroy(Candidate $candidate)
{
    $candidate->delete();
    return redirect()->route('hr.ats.candidates.index')
        ->with('success', 'Candidate deleted.');
}
```
Add the route:
```php
Route::delete('/candidates/{id}', [CandidateController::class, 'destroy'])
    ->middleware('permission:hr.ats.candidates.delete')
    ->name('candidates.destroy');
```

---

### BUG-23 🟡 `InterviewController::update()` allows `duration_minutes` min:1 but `store()` requires min:15

**File:** `app/Http/Controllers/HR/ATS/InterviewController.php:124`

```php
// update() — line 124:
'duration_minutes' => 'nullable|integer|min:1',  // ← inconsistent

// store() — line 96:
'duration_minutes' => 'nullable|integer|min:15|max:480',  // ← correct
```

**Fix:**
```php
// update():
'duration_minutes' => 'nullable|integer|min:15|max:480',
```

---

## Phase 4 — Seeder & Data Quality Fixes

---

### BUG-24 🟡 `ApplicationSeeder` uses invalid status `'in_review'`

**File:** `database/seeders/ApplicationSeeder.php:36`

**Problem:**
```php
'status' => 'in_review',
```
`in_review` is not a valid `ApplicationStatus`. The valid enum values are `submitted`, `shortlisted`, `interviewed`, `offered`, `hired`, `rejected`, `withdrawn`.

**Fix:**
```php
'status' => 'shortlisted',
```

---

### BUG-25 🟡 `CandidateSeeder` relies on `Candidate::$fillable` being correct

**File:** `database/seeders/CandidateSeeder.php`

Once BUG-03 is fixed (updating `$fillable`), this seeder will work correctly. No change needed to the seeder itself — it depends on the model fix.

---

### BUG-26 🟡 `ApplicationSeeder` hardcodes `job_posting_id` 1, 2, 3 which may not exist

**File:** `database/seeders/ApplicationSeeder.php:27–46`

**Problem:**
If `JobPostingSeeder` hasn't run or those IDs don't exist, the FK constraint fails.

**Fix:**
```php
use App\Models\JobPosting;

$jobPostings = JobPosting::take(3)->get();

if ($jobPostings->isEmpty()) {
    $this->command->warn('No job postings found. Run JobPostingSeeder first.');
    return;
}

$applications = [
    [
        'candidate_id'   => $candidates[0]->id ?? 1,
        'job_posting_id' => $jobPostings[0]->id,
        'status'         => 'submitted',
        ...
    ],
    ...
];
```
Also add `CandidateSeeder` and `JobPostingSeeder` as dependencies in `DatabaseSeeder`:
```php
$this->call([
    JobPostingSeeder::class,
    CandidateSeeder::class,
    ApplicationSeeder::class,
]);
```

---

### BUG-27 🟡 `Note` model loaded via `Candidate::notes()` but `ApplicationController` also loads `'notes'` — need to confirm they share the same model

**Files:** `app/Models/Candidate.php:46–49`, `app/Http/Controllers/HR/ATS/ApplicationController.php:88`

**Problem:**
`Candidate::notes()` → `hasMany(Note::class)`. `ApplicationController::show()` loads `$application->notes`. If `Application` does not have a `notes()` relationship defined on the `Application` model, this eager-load silently returns an empty collection.

**Fix — add notes relationship to `Application` model if missing:**
```php
// app/Models/Application.php
public function notes()
{
    return $this->hasMany(Note::class);
}
```

---

### BUG-28 🟡 `CandidateNote` type references `created_by_name` but the `Note` model has no `user` relationship loading

**File:** `resources/js/types/ats-pages.ts:193`

```ts
export interface CandidateNote {
    created_by_name?: string;  // ← expected on frontend
}
```
`CandidateController::addNote()` and no controller appends `created_by_name`. The `Note` model's `user` relationship is loaded in `CandidateController::show()` (`notes.user`) but the mapped array never includes the user name.

**Fix — in `CandidateController::show()`, map the notes:**
```php
'notes' => $candidate->notes->map(fn($note) => [
    'id'              => $note->id,
    'note'            => $note->note,
    'is_private'      => $note->is_private,
    'user_id'         => $note->user_id,
    'created_by_name' => optional($note->user)->name,
    'created_at'      => $note->created_at,
    'updated_at'      => $note->updated_at,
]),
```

---

### BUG-29 🟡 `JobPostingController::store()` doesn't set `posted_at` to `null` when status is `'draft'`

**File:** `app/Http/Controllers/HR/ATS/JobPostingController.php:108–111`

**Problem:**
```php
'posted_at' => $validated['status'] === 'open' ? now() : null,
```
This is actually correct logic, but `posted_at` is not in the `$validated` array — `JobPosting::create()` with `array_merge` may fail if `posted_at` is not in `$fillable`. Check `JobPosting::$fillable` includes `posted_at`.

**Fix — verify `JobPosting::$fillable`** includes: `title`, `department_id`, `description`, `requirements`, `status`, `posted_at`, `closed_at`, `created_by`, `auto_post_facebook`.

---

### BUG-30 🟡 `ApplicationController::generateOffer()` creates `Offer` without required salary/term fields

**File:** `app/Http/Controllers/HR/ATS/ApplicationController.php:216–221`

**Problem:**
```php
Offer::create([
    'application_id' => $application->id,
    'title'          => $application->jobPosting->title,
    'created_by'     => 1,
]);
```
An offer without salary, start date, or terms is incomplete. If the `offers` table has `NOT NULL` constraints on any of those fields this will throw a DB error.

**Fix — add validation and pass salary details:**
```php
public function generateOffer(Request $request, Application $application)
{
    $validated = $request->validate([
        'salary'     => 'required|numeric|min:0',
        'start_date' => 'required|date|after_or_equal:today',
        'notes'      => 'nullable|string|max:2000',
    ]);

    Offer::create([
        'application_id' => $application->id,
        'title'          => $application->jobPosting->title ?? 'Job Offer',
        'salary'         => $validated['salary'],
        'start_date'     => $validated['start_date'],
        'notes'          => $validated['notes'] ?? null,
        'created_by'     => Auth::id(),
    ]);

    $application->status = 'offered';
    $application->save();

    ApplicationStatusHistory::create([
        'application_id' => $application->id,
        'status'         => 'offered',
        'changed_by'     => Auth::id(),
    ]);

    return back()->with('success', 'Offer generated.');
}
```

---

## Summary Table

| # | Severity | File | Description |
|---|---|---|---|
| BUG-01 | 🔴 | `ApplicationController.php:252` | `move()` uses CLI `alert()`, no return |
| BUG-02 | 🔴 | `routes/hr.php:458,464` | `addFeedback`/`markCompleted` methods missing |
| BUG-03 | 🔴 | `Candidate.php:12` | `$fillable` misaligned with migration |
| BUG-04 | 🔴 | `interviews migration:19` | `'canceled'` vs `'cancelled'` enum mismatch |
| BUG-05 | 🔴 | `interviews migration:18` | `location_type` enum doesn't include `video_call`/`phone` |
| BUG-06 | 🔴 | `interviews migration:22` | `recommendation` enum mismatch across all layers |
| BUG-07 | 🟠 | `routes/hr.php` | Missing routes: `move`, `schedule-interview`, `generate-offer`, `notes` |
| BUG-08 | 🟠 | `ApplicationController.php:195` | `scheduleInterview` location_type validation wrong |
| BUG-09 | 🟠 | `ats-pages.ts:423` | `ApplicationShowProps` shape doesn't match controller output |
| BUG-10 | 🟠 | `ApplicationController.php:105` | Dead status remapping (`new`→`submitted`) |
| BUG-11 | 🟠 | `ats-pages.ts:305` | `CandidateSummary` missing `offered`/`rejected` fields |
| BUG-12 | 🟠 | `ats-pages.ts:329` | `InterviewSummary` keys don't match controller output |
| BUG-13 | 🟠 | `ats-pages.ts:256` | `score_min`/`score_max` vs `min_score`/`max_score` key mismatch |
| BUG-14 | 🟠 | `ats-pages.ts:413` | Applications expects `PaginatedData` but gets plain array |
| BUG-15 | 🟠 | `ats-pages.ts:389` | Candidates expects `PaginatedData` but gets plain array |
| BUG-16 | 🟠 | `InterviewController.php:40` | `ilike` is PostgreSQL-only, breaks SQLite/MySQL |
| BUG-17 | 🟠 | `InterviewController.php:48` | Unsafe null chain — crashes on missing candidate |
| BUG-18 | 🟠 | `interviews migration:19` | `no_show` status not in DB enum |
| BUG-19 | 🟠 | `interviews migration` | Missing `candidate_id` column |
| BUG-20 | 🟡 | Multiple controllers | Hardcoded `user_id = 1` breaks auth/audit |
| BUG-21 | 🟡 | `routes/hr.php:364` | Job posting routes use wrong permission strings |
| BUG-22 | 🟡 | `CandidateController.php:159` | `destroy()` returns JSON + missing route |
| BUG-23 | 🟡 | `InterviewController.php:124` | `duration_minutes` min:1 vs min:15 inconsistency |
| BUG-24 | 🟡 | `ApplicationSeeder.php:36` | Invalid status `'in_review'` |
| BUG-25 | 🟡 | `CandidateSeeder.php` | Fixed by BUG-03 model fix |
| BUG-26 | 🟡 | `ApplicationSeeder.php:27` | Hardcoded FK IDs 1,2,3 |
| BUG-27 | 🟡 | `Application.php` | `notes()` relationship likely missing on Application model |
| BUG-28 | 🟡 | `CandidateController.php` | `created_by_name` never populated in note response |
| BUG-29 | 🟡 | `JobPostingController.php:108` | Verify `posted_at` in `JobPosting::$fillable` |
| BUG-30 | 🟡 | `ApplicationController.php:216` | `generateOffer()` creates incomplete offer record |

---

## Migrations Required

Create these new migrations in order:

1. **Fix interviews status enum** — add `cancelled`, `no_show`; remove `canceled`
2. **Fix interviews location_type enum** — replace `virtual` with `video_call`, `phone`
3. **Fix interviews recommendation enum** — replace `hold` with... or standardise; align TypeScript
4. **Add `candidate_id` to interviews** — if the add-column migration doesn't already exist
5. **Any column changes if Note needs `application_id`**

Run after all fixes: `php artisan migrate` then `php artisan test`.

---

## Acceptance Criteria

- [ ] Kanban pipeline drag-and-drop moves applications without errors
- [ ] Scheduling an interview from the Application Show page succeeds
- [ ] Cancelling an interview saves correctly and the status filter works
- [ ] Interview feedback form submits and persists score/recommendation/feedback
- [ ] Adding a candidate via the form creates a DB row with all fields
- [ ] Candidate search by name and email returns results
- [ ] Application notes can be added and display the author name
- [ ] Statistics cards on all pages show correct counts
- [ ] No `console.log`, `dd()`, or `dump()` left in code
- [ ] `php artisan test` passes
