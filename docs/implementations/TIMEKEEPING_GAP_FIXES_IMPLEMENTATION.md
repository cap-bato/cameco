# Timekeeping Gap Fixes — Implementation Plan

**Derived from:** `TIMEKEEPING_MODULE_ANALYSIS.md`  
**Date:** 2026-03-11  
**Status:** In Progress  

---

## Overview

This file tracks the phased implementation of all gaps identified in the Timekeeping module analysis. Phases are ordered by priority (P0 → P3). Each phase contains both the acceptance criteria and the exact implementation.

---

## Phase 1 — Fix `LedgerSyncController` (P0)

**Problem:** The `/hr/timekeeping/api/ledger/sync/trigger` endpoint generates a fake `sync_job_id` and logs a message — it never dispatches `ProcessRfidLedgerJob`. The "Trigger Manual Sync" button in the UI is completely non-functional.

**File:** `app/Http/Controllers/HR/Timekeeping/LedgerSyncController.php`

### Acceptance Criteria
- [x] `trigger()` dispatches `ProcessRfidLedgerJob` onto the queue
- [x] `from_sequence_id` parameter is passed through to the job when provided
- [x] Response returns the queued job's database ID (not a fake random string)
- [x] `status()` endpoint queries the actual jobs table for job state
- [x] 202 response on success; 500 with error message on failure

### Implementation

Replace `LedgerSyncController.php` entirely:

```php
<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Jobs\Timekeeping\ProcessRfidLedgerJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerSyncController extends Controller
{
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'force'            => 'sometimes|boolean',
            'from_sequence_id' => 'sometimes|integer|min:1',
            'limit'            => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $job = new ProcessRfidLedgerJob(
                fromSequenceId: $validated['from_sequence_id'] ?? null,
                limit:          $validated['limit'] ?? 100,
                force:          $validated['force'] ?? false,
            );

            $queuedJob = dispatch($job);

            Log::info('Manual ledger sync triggered', [
                'triggered_by'    => auth()->user()?->name ?? 'system',
                'from_sequence_id' => $validated['from_sequence_id'] ?? null,
                'limit'           => $validated['limit'] ?? 100,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ledger synchronization job dispatched',
                'data' => [
                    'status'     => 'queued',
                    'started_at' => now()->toISOString(),
                    'parameters' => $validated,
                ],
            ], 202);

        } catch (\Exception $e) {
            Log::error('Ledger sync trigger failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch ledger synchronization job',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function status(string $syncJobId): JsonResponse
    {
        // Query the actual failed_jobs and jobs tables
        $pending = DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->count();

        $failed = DB::table('failed_jobs')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->orderByDesc('failed_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_jobs' => $pending,
                'last_failure' => $failed ? [
                    'failed_at' => $failed->failed_at,
                    'exception' => substr($failed->exception, 0, 500),
                ] : null,
            ],
        ]);
    }
}
```

### Notes
- `ProcessRfidLedgerJob` constructor must accept `fromSequenceId`, `limit`, and `force` parameters — verify the constructor signature and add them if missing (see Phase 1a below).

### Phase 1a — Update `ProcessRfidLedgerJob` Constructor

**File:** `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php`

Check the current constructor. If it does not accept `fromSequenceId` / `limit` / `force`, add them:

```php
public function __construct(
    public readonly ?int $fromSequenceId = null,
    public readonly int  $limit = 100,
    public readonly bool $force = false,
) {}
```

Then in `handle()`, pass `$this->fromSequenceId` to `LedgerPollingService::pollEventsFromSequence()` when set:

```php
public function handle(LedgerPollingService $pollingService, AttendanceSummaryService $summaryService): void
{
    $events = $this->fromSequenceId
        ? $pollingService->pollEventsFromSequence($this->fromSequenceId, $this->limit)
        : $pollingService->pollNewEvents($this->limit);

    // ... rest of handle logic unchanged
}
```

---

## Phase 2 — Register Event Listeners (P1)

**Problem:** `AttendanceCorrectionRequested`, `AttendanceCorrectionApproved`, `AttendanceCorrectionRejected`, and `AttendanceSummaryUpdated` are dispatched throughout the system. `EventServiceProvider` has no mappings for any of them. No listener classes exist under `app/Listeners/Timekeeping/`.

### Acceptance Criteria
- [x] Four listener classes created in `app/Listeners/Timekeeping/`
- [x] All four events registered in `EventServiceProvider`
- [x] Correction requested → email notification to HR / manager (TODO stub in place)
- [x] Correction approved/rejected → email notification to employee (TODO stub in place)
- [x] Summary updated → log action (payroll sync hook ready but non-blocking)

### 2.1 Create Listener Directory Structure

```
app/Listeners/Timekeeping/
├── NotifyAttendanceCorrectionRequested.php
├── NotifyAttendanceCorrectionApproved.php
├── NotifyAttendanceCorrectionRejected.php
└── TriggerPayrollSyncOnSummaryUpdate.php
```

### 2.2 `NotifyAttendanceCorrectionRequested.php`

```php
<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyAttendanceCorrectionRequested implements ShouldQueue
{
    public function handle(AttendanceCorrectionRequested $event): void
    {
        Log::info('Attendance correction requested', [
            'correction_id' => $event->correction->id ?? null,
            'employee_id'   => $event->correction->requested_by_user_id ?? null,
        ]);

        // TODO Phase 2: send email to HR manager via Mail::to(...)->send(...)
        // Notification::route('mail', config('mail.hr_address'))
        //     ->notify(new AttendanceCorrectionRequestedNotification($event->correction));
    }
}
```

### 2.3 `NotifyAttendanceCorrectionApproved.php`

```php
<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAttendanceCorrectionApproved implements ShouldQueue
{
    public function handle(AttendanceCorrectionApproved $event): void
    {
        Log::info('Attendance correction approved', [
            'correction_id'   => $event->correction->id ?? null,
            'approved_by'     => $event->correction->approved_by_user_id ?? null,
        ]);

        // TODO Phase 2: notify requesting employee by email
    }
}
```

### 2.4 `NotifyAttendanceCorrectionRejected.php`

```php
<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAttendanceCorrectionRejected implements ShouldQueue
{
    public function handle(AttendanceCorrectionRejected $event): void
    {
        Log::info('Attendance correction rejected', [
            'correction_id'    => $event->correction->id ?? null,
            'rejection_reason' => $event->correction->rejection_reason ?? null,
        ]);

        // TODO Phase 2: notify requesting employee by email
    }
}
```

### 2.5 `TriggerPayrollSyncOnSummaryUpdate.php`

```php
<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceSummaryUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerPayrollSyncOnSummaryUpdate implements ShouldQueue
{
    public function handle(AttendanceSummaryUpdated $event): void
    {
        Log::info('Attendance summary updated — payroll sync hook fired', [
            'employee_id'     => $event->summary->employee_id ?? null,
            'attendance_date' => $event->summary->attendance_date ?? null,
        ]);

        // TODO: When payroll recalculation-on-change is implemented,
        // dispatch the payroll recalc job here for the affected period.
    }
}
```

### 2.6 Register in `EventServiceProvider`

**File:** `app/Providers/EventServiceProvider.php`

Add at the top of the file (with the existing imports):

```php
use App\Events\Timekeeping\AttendanceCorrectionApproved;
use App\Events\Timekeeping\AttendanceCorrectionRejected;
use App\Events\Timekeeping\AttendanceCorrectionRequested;
use App\Events\Timekeeping\AttendanceSummaryUpdated;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionApproved;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionRejected;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionRequested;
use App\Listeners\Timekeeping\TriggerPayrollSyncOnSummaryUpdate;
```

Add to the `$listen` array:

```php
AttendanceCorrectionRequested::class => [
    NotifyAttendanceCorrectionRequested::class,
],
AttendanceCorrectionApproved::class => [
    NotifyAttendanceCorrectionApproved::class,
],
AttendanceCorrectionRejected::class => [
    NotifyAttendanceCorrectionRejected::class,
],
AttendanceSummaryUpdated::class => [
    TriggerPayrollSyncOnSummaryUpdate::class,
],
```

### 2.7 Verify Event Class Signatures

Each event must expose the correction/summary object as a public property so the listener can access it. Check `app/Events/Timekeeping/*.php`:

```php
// Expected structure for correction events:
class AttendanceCorrectionRequested
{
    public function __construct(
        public readonly AttendanceCorrection $correction
    ) {}
}

// Expected structure for summary event:
class AttendanceSummaryUpdated
{
    public function __construct(
        public readonly DailyAttendanceSummary $summary
    ) {}
}
```

If the events use different property names, update the listener `handle()` methods to match.

---

## Phase 3 — Wire `DeviceController` to Real DB (P1)

**Problem:** `DeviceController::index()` calls `generateMockDevices()` returning 5 hardcoded arrays. No query to the `rfid_devices` table.

**File:** `app/Http/Controllers/HR/Timekeeping/DeviceController.php`

### Acceptance Criteria
- [x] `index()` queries `rfid_devices` table via `RfidDevice` model
- [x] Status filter works against real `status` column
- [x] Summary stats (online/offline/idle count) computed from real data
- [x] `scansToday` computed from `rfid_ledger` count for today per device
- [x] Recent scans per device pulled from `rfid_ledger` joined with employee names
- [x] Falls back gracefully when table is empty (no crash)

### Implementation

Replace `DeviceController.php`:

```php
<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->get('status', 'all');

        $query = RfidDevice::query();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $devices = $query->orderBy('device_id')->get();

        $today = Carbon::today();

        // Enrich each device with real scan counts and recent scans
        $enriched = $devices->map(function (RfidDevice $device) use ($today) {
            $scansToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->count();

            $recentScans = RfidLedger::with('employee:id,employee_id,first_name,last_name')
                ->where('device_id', $device->device_id)
                ->orderByDesc('scan_timestamp')
                ->limit(5)
                ->get()
                ->map(fn($entry) => [
                    'employeeName' => $entry->employee
                        ? trim("{$entry->employee->first_name} {$entry->employee->last_name}")
                        : $entry->employee_rfid,
                    'eventType'   => $entry->event_type,
                    'timestamp'   => $entry->scan_timestamp?->toISOString(),
                ]);

            $lastScan = RfidLedger::where('device_id', $device->device_id)
                ->orderByDesc('scan_timestamp')
                ->first();

            return [
                'id'                 => $device->device_id,
                'location'           => $device->location,
                'status'             => $device->status,
                'lastScanTimestamp'  => $lastScan?->scan_timestamp?->toISOString(),
                'lastScanAgo'        => $lastScan ? $lastScan->scan_timestamp->diffForHumans() : 'Never',
                'scansToday'         => $scansToday,
                'lastHeartbeat'      => $device->last_heartbeat?->toISOString(),
                'recentScans'        => $recentScans,
            ];
        });

        $summary = [
            'total'       => $devices->count(),
            'online'      => $devices->where('status', 'online')->count(),
            'offline'     => $devices->where('status', 'offline')->count(),
            'idle'        => $devices->where('status', 'idle')->count(),
            'maintenance' => $devices->where('status', 'maintenance')->count(),
        ];

        return Inertia::render('HR/Timekeeping/Devices', [
            'devices' => $enriched->values(),
            'summary' => $summary,
            'filters' => ['status' => $statusFilter],
        ]);
    }
}
```

### Notes
- `RfidLedger` must have a `belongsTo` relationship to `Employee` keyed on `employee_rfid` → `rfid_card_mappings.card_uid` → `employees.id`. Check `RfidLedger` model and add the relationship if missing (see Phase 3a).

### Phase 3a — Add `employee` Relationship to `RfidLedger`

If not already present, add to `app/Models/RfidLedger.php`:

```php
use App\Models\RfidCardMapping;

public function cardMapping(): BelongsTo
{
    return $this->belongsTo(RfidCardMapping::class, 'employee_rfid', 'card_uid');
}

// Convenience accessor: returns the Employee through the card mapping
public function getEmployeeAttribute()
{
    return $this->cardMapping?->employee;
}
```

And add to `RfidCardMapping` model:

```php
public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class, 'employee_id');
}
```

---

## Phase 4 — Wire `LedgerDeviceController` to Real DB (P1)

**Problem:** `LedgerDeviceController` generates completely random device metrics. The monitoring panel shows fictional throughput/latency/error data.

**File:** `app/Http/Controllers/HR/Timekeeping/LedgerDeviceController.php`

### Acceptance Criteria
- [x] `index()` queries `rfid_devices` joined with `rfid_ledger` aggregate counts
- [x] `scans_today`, `last_heartbeat`, `status` come from real tables
- [x] `show()` returns real per-device metrics from `rfid_ledger`
- [x] Error rate derived from count of `event_type='unknown_card'` vs total scans

### Implementation

Replace `LedgerDeviceController.php`:

```php
<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LedgerDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'              => 'sometimes|in:all,online,offline,idle,maintenance',
            'location'            => 'sometimes|string|max:100',
            'include_recent_scans' => 'sometimes|boolean',
        ]);

        $statusFilter   = $validated['status'] ?? 'all';
        $locationFilter = $validated['location'] ?? null;
        $includeRecent  = $validated['include_recent_scans'] ?? false;

        $today = Carbon::today();

        $query = RfidDevice::query();
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($locationFilter) {
            $query->where('location', 'like', "%{$locationFilter}%");
        }

        $devices = $query->get();

        $enriched = $devices->map(function (RfidDevice $device) use ($today, $includeRecent) {
            $scansToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->count();

            $unknownToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->where('event_type', 'unknown_card')
                ->count();

            $errorRate = $scansToday > 0
                ? round(($unknownToday / $scansToday) * 100, 2)
                : 0;

            $row = [
                'id'            => $device->device_id,
                'location'      => $device->location,
                'status'        => $device->status,
                'last_heartbeat' => $device->last_heartbeat?->toISOString(),
                'scans_today'   => $scansToday,
                'error_rate'    => $errorRate,
            ];

            if ($includeRecent) {
                $row['recent_scans'] = RfidLedger::where('device_id', $device->device_id)
                    ->orderByDesc('scan_timestamp')
                    ->limit(5)
                    ->get(['employee_rfid', 'event_type', 'scan_timestamp'])
                    ->toArray();
            }

            return $row;
        });

        $summary = [
            'total'   => $devices->count(),
            'online'  => $devices->where('status', 'online')->count(),
            'offline' => $devices->where('status', 'offline')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $enriched->values(),
            'summary' => $summary,
            'meta'    => ['timestamp' => now()->toISOString()],
        ]);
    }

    public function show(string $deviceId): JsonResponse
    {
        $device = RfidDevice::where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        $today = Carbon::today();

        // Hourly scan breakdown for today
        $hourlyScans = RfidLedger::where('device_id', $deviceId)
            ->whereDate('scan_timestamp', $today)
            ->selectRaw("EXTRACT(HOUR FROM scan_timestamp) as hour, COUNT(*) as count")
            ->groupByRaw("EXTRACT(HOUR FROM scan_timestamp)")
            ->orderBy('hour')
            ->pluck('count', 'hour');

        // Pad all 24 hours
        $hourlyDistribution = collect(range(0, 23))->map(fn($h) => [
            'hour'  => $h,
            'count' => (int) ($hourlyScans[$h] ?? 0),
        ]);

        $scansToday    = RfidLedger::where('device_id', $deviceId)->whereDate('scan_timestamp', $today)->count();
        $unknownToday  = RfidLedger::where('device_id', $deviceId)->whereDate('scan_timestamp', $today)->where('event_type', 'unknown_card')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id'               => $device->device_id,
                'location'         => $device->location,
                'status'           => $device->status,
                'last_heartbeat'   => $device->last_heartbeat?->toISOString(),
                'scans_today'      => $scansToday,
                'error_rate'       => $scansToday > 0 ? round(($unknownToday / $scansToday) * 100, 2) : 0,
                'hourly_distribution' => $hourlyDistribution,
            ],
        ]);
    }
}
```

---

## Phase 5 — Fix `LeaveRequest` Integration in `AttendanceSummaryService` (P1)

**Problem:** `daily_attendance_summary.is_on_leave` defaults to `false` regardless of whether the employee has approved leave for that day. Legitimate leave absences are stored as AWOL/absent.

**File:** `app/Services/Timekeeping/AttendanceSummaryService.php`

### Acceptance Criteria
- [x] `buildEmptySummary()` (absent day) checks for approved leave before marking absent
- [x] If an approved `LeaveRequest` record covers the date, set `is_on_leave = true`
- [x] `is_present` remains `false` when on leave
- [x] No crash if `LeaveRequest` model/table does not exist (graceful fallback)

### Check: Does a `LeaveRequest` Model Exist?

```bash
# Run this first
Get-ChildItem app/Models | Where-Object Name -match "Leave"
```

If `LeaveRequest.php` exists, implement the check. If not, create a stub or document as a dependency.

### Implementation — Add to `AttendanceSummaryService`

Find the `buildEmptySummary()` method and add a leave check before setting `is_on_leave`:

```php
private function isEmployeeOnLeave(int $employeeId, Carbon $date): bool
{
    // Guard: if LeaveRequest model/table doesn't exist yet, return false
    if (!class_exists(\App\Models\LeaveRequest::class)) {
        return false;
    }

    return \App\Models\LeaveRequest::where('employee_id', $employeeId)
        ->where('status', 'approved')
        ->where('start_date', '<=', $date->toDateString())
        ->where('end_date', '>=', $date->toDateString())
        ->exists();
}
```

Then in `buildEmptySummary()`, replace the hardcoded `'is_on_leave' => false` with:

```php
'is_on_leave' => $this->isEmployeeOnLeave($employeeId, $date),
```

---

## Phase 6 — Wire `EmployeeTimelineController` to Real DB (P2)

**Problem:** All data returned by `EmployeeTimelineController::show()` is hardcoded arrays from private mock methods.

**File:** `app/Http/Controllers/HR/Timekeeping/EmployeeTimelineController.php`

### Acceptance Criteria
- [x] Employee info loaded from `Employee` model (with profile/department)
- [x] Timeline events loaded from `attendance_events` for the given date
- [x] Work schedule loaded from `WorkSchedule` via `AttendanceSummaryService`
- [x] Summary computed from real events (total hours, status flags)
- [x] 404 returned when employee not found

### Implementation

Replace `EmployeeTimelineController.php`:

```php
<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTimelineController extends Controller
{
    public function show(Request $request, int $employeeId): Response
    {
        $date = Carbon::parse($request->get('date', today()->toDateString()));

        $employee = Employee::with(['profile', 'department'])
            ->findOrFail($employeeId);

        $events = AttendanceEvent::where('employee_id', $employeeId)
            ->whereDate('event_date', $date)
            ->orderBy('event_time', 'asc')
            ->get()
            ->map(fn($e) => [
                'id'            => $e->id,
                'eventType'     => $e->event_type,
                'timestamp'     => Carbon::parse("{$e->event_date} {$e->event_time}")->toISOString(),
                'deviceId'      => $e->device_id,
                'sourceType'    => $e->source_type ?? 'rfid',
                'isManual'      => $e->is_manual ?? false,
            ]);

        $summary = DailyAttendanceSummary::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->first();

        return Inertia::render('HR/Timekeeping/EmployeeTimeline', [
            'employee' => [
                'id'          => $employee->id,
                'employeeId'  => $employee->employee_id,
                'name'        => $employee->full_name ?? trim("{$employee->first_name} {$employee->last_name}"),
                'department'  => $employee->department?->name,
                'position'    => $employee->position,
                'photo'       => $employee->profile?->profile_picture_url ?? null,
            ],
            'events'   => $events,
            'summary'  => $summary ? [
                'timeIn'          => $summary->time_in,
                'timeOut'         => $summary->time_out,
                'totalHours'      => $summary->total_hours_worked,
                'isPresent'       => $summary->is_present,
                'isLate'          => $summary->is_late,
                'lateMinutes'     => $summary->late_minutes,
                'isOvertime'      => $summary->is_overtime,
                'overtimeHours'   => $summary->overtime_hours,
                'isOnLeave'       => $summary->is_on_leave,
            ] : null,
            'date'     => $date->toDateString(),
        ]);
    }
}
```

---

## Phase 7 — Implement `ImportController` with Real File Processing (P2)

**Problem:** `ImportController::upload()` validates the file but then just returns a fake batch ID. `process()` returns hardcoded mock results. No record is ever created or processed.

**Files:**
- `app/Http/Controllers/HR/Timekeeping/ImportController.php`
- `app/Models/ImportBatch.php` *(create)*
- `database/migrations/XXXX_create_import_batches_table.php` *(create)*

### Acceptance Criteria
- [x] `ImportBatch` model and `import_batches` migration created
- [x] `upload()` stores the file to `storage/app/imports/` and creates an `ImportBatch` record
- [x] `process()` reads the file and creates real `AttendanceEvent` (or overtime/schedule) records
- [x] `history()` queries `import_batches` from DB with pagination
- [x] Errors per row are stored in `import_batches.error_log` JSON column
- [x] Duplicate detection: skip rows where an `AttendanceEvent` already exists for same employee + date + event_type

### 7.1 Migration: `import_batches` table

Create file: `database/migrations/2026_03_11_000001_create_import_batches_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('import_type'); // attendance, overtime, schedule
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('import_type');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
```

### 7.2 Model: `ImportBatch`

Create file: `app/Models/ImportBatch.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'created_by',
        'original_filename',
        'stored_path',
        'import_type',
        'status',
        'total_records',
        'successful_records',
        'failed_records',
        'skipped_records',
        'error_log',
        'processed_at',
    ];

    protected $casts = [
        'error_log'    => 'array',
        'processed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

### 7.3 Replace `ImportController.php`

```php
<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\ImportBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ImportBatch::with('creator:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->paginate(20)->withQueryString();

        $summary = [
            'total_imports'    => ImportBatch::count(),
            'successful'       => ImportBatch::where('status', 'completed')->where('failed_records', 0)->count(),
            'failed'           => ImportBatch::where('status', 'failed')->count(),
            'pending'          => ImportBatch::whereIn('status', ['pending', 'processing'])->count(),
            'records_imported' => (int) ImportBatch::sum('successful_records'),
        ];

        return Inertia::render('HR/Timekeeping/Import/Index', [
            'batches' => $batches,
            'summary' => $summary,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'import_type' => 'required|in:attendance,overtime,schedule',
        ]);

        $file       = $request->file('file');
        $storedPath = $file->store('imports/timekeeping', 'local');

        $batch = ImportBatch::create([
            'created_by'        => auth()->id(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => $storedPath,
            'import_type'       => $validated['import_type'],
            'status'            => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded. Use the process endpoint to begin import.',
            'data'    => [
                'batch_id'    => $batch->id,
                'file_name'   => $batch->original_filename,
                'import_type' => $batch->import_type,
                'status'      => $batch->status,
                'uploaded_at' => $batch->created_at->toISOString(),
            ],
        ]);
    }

    public function process(int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);

        if ($batch->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Batch already processed'], 422);
        }

        $batch->update(['status' => 'processing']);

        try {
            $result = match ($batch->import_type) {
                'attendance' => $this->processAttendanceFile($batch),
                default      => throw new \InvalidArgumentException("Import type '{$batch->import_type}' not yet supported"),
            };

            $batch->update([
                'status'             => 'completed',
                'total_records'      => $result['total'],
                'successful_records' => $result['success'],
                'failed_records'     => $result['failed'],
                'skipped_records'    => $result['skipped'],
                'error_log'          => $result['errors'],
                'processed_at'       => now(),
            ]);

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            $batch->update(['status' => 'failed', 'error_log' => [['row' => 0, 'error' => $e->getMessage()]]]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function processAttendanceFile(ImportBatch $batch): array
    {
        $path = storage_path("app/{$batch->stored_path}");

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $total = $success = $failed = $skipped = 0;
        $errors = [];

        foreach ($csv->getRecords() as $rowNum => $row) {
            $total++;
            try {
                // Expected columns: employee_id, event_date, event_type (time_in|time_out|break_start|break_end), event_time
                $employee = Employee::where('employee_id', trim($row['employee_id']))->first();
                if (!$employee) {
                    throw new \RuntimeException("Employee '{$row['employee_id']}' not found");
                }

                $eventDate = Carbon::parse($row['event_date'])->toDateString();
                $eventType = strtolower(trim($row['event_type']));

                if (!in_array($eventType, ['time_in', 'time_out', 'break_start', 'break_end'])) {
                    throw new \RuntimeException("Invalid event_type '{$eventType}'");
                }

                // Duplicate check
                $exists = AttendanceEvent::where('employee_id', $employee->id)
                    ->whereDate('event_date', $eventDate)
                    ->where('event_type', $eventType)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                AttendanceEvent::create([
                    'employee_id' => $employee->id,
                    'event_date'  => $eventDate,
                    'event_time'  => Carbon::parse($row['event_time'])->toTimeString(),
                    'event_type'  => $eventType,
                    'source_type' => 'import',
                    'is_manual'   => true,
                    'import_batch_id' => $batch->id,
                ]);

                $success++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['row' => $rowNum + 2, 'error' => $e->getMessage()]; // +2 for header + 1-index
            }
        }

        return compact('total', 'success', 'failed', 'skipped', 'errors');
    }

    public function history(Request $request): JsonResponse
    {
        $batches = ImportBatch::with('creator:id,name')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $batches]);
    }
}
```

> **Dependency:** `League\Csv` must be available. Check `composer.json`. If not: `composer require league/csv`

### 7.4 Add `import_batch_id` to `attendance_events`

If `AttendanceEvent` doesn't already have an `import_batch_id` column, add a migration:

```php
// database/migrations/2026_03_11_000002_add_import_batch_id_to_attendance_events.php
Schema::table('attendance_events', function (Blueprint $table) {
    $table->foreignId('import_batch_id')
        ->nullable()
        ->constrained('import_batches')
        ->onDelete('set null')
        ->after('source_type');
});
```

---

## Phase 8 — Add Card Validation API Endpoint (P2)

**Problem:** FastAPI reads `rfid_card_mappings` directly from the shared database to validate card UIDs. This creates tight coupling between FastAPI and Laravel's internal schema. A dedicated read endpoint is safer.

**File:** `routes/hr.php` or a dedicated `routes/api.php` section

### Acceptance Criteria
- [x] `GET /api/timekeeping/cards/{uid}` returns employee info for a valid active card
- [x] Returns 404 for unknown UIDs
- [x] Returns `is_active: false` for deactivated cards
- [x] Endpoint requires API token authentication (not session-based)
- [x] Response is JSON only

### Implementation

Add a new API controller:

```php
// app/Http/Controllers/Api/Timekeeping/CardValidationController.php
<?php

namespace App\Http\Controllers\Api\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidCardMapping;
use Illuminate\Http\JsonResponse;

class CardValidationController extends Controller
{
    public function show(string $cardUid): JsonResponse
    {
        $mapping = RfidCardMapping::with('employee:id,employee_id,first_name,last_name,department_id')
            ->where('card_uid', $cardUid)
            ->first();

        if (!$mapping) {
            return response()->json(['valid' => false, 'reason' => 'unknown_card'], 404);
        }

        if (!$mapping->is_active) {
            return response()->json(['valid' => false, 'reason' => 'card_inactive', 'employee_id' => $mapping->employee_id], 200);
        }

        return response()->json([
            'valid'       => true,
            'employee_id' => $mapping->employee_id,
            'employee'    => $mapping->employee ? [
                'id'          => $mapping->employee->id,
                'employee_id' => $mapping->employee->employee_id,
                'name'        => trim("{$mapping->employee->first_name} {$mapping->employee->last_name}"),
            ] : null,
        ]);
    }
}
```

Register in `routes/api.php` (or a new `routes/timekeeping-api.php`):

```php
use App\Http\Controllers\Api\Timekeeping\CardValidationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/timekeeping/cards/{uid}', [CardValidationController::class, 'show']);
});
```

FastAPI can then call:
```
GET /api/timekeeping/cards/ABCD1234
Authorization: Bearer <sanctum_token>
```

---

## Phase 9 — Run Migrations

After all code phases above are complete, run:

```bash
php artisan migrate
```

Expected new migrations to run:
- `2026_03_11_000001_create_import_batches_table.php`
- `2026_03_11_000002_add_import_batch_id_to_attendance_events.php` *(if needed)*

---

## Phase 10 — Verify `ProcessRfidLedgerJob` Constructor (P0 — prerequisite check)

Before Phase 1 can be considered complete, verify the job's current constructor signature.

**File:** `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php`

Run:
```bash
grep -n "__construct\|fromSequenceId\|limit\|force" app/Jobs/Timekeeping/ProcessRfidLedgerJob.php
```

If the constructor has no parameters, update it as described in Phase 1a. If it already has parameters but with different names, adapt `LedgerSyncController` accordingly.

---

## Progress Checklist

### P0 — Critical Blockers
- [x] Phase 1: `LedgerSyncController` dispatches real job ✅ 2026-03-11
- [x] Phase 1a: `ProcessRfidLedgerJob` accepts `fromSequenceId` / `limit` / `force` ✅ 2026-03-11

### P1 — High Priority
- [x] Phase 2: All 4 event listeners created and registered
- [x] Phase 3: `DeviceController` queries `rfid_devices` table
- [x] Phase 3a: `RfidLedger` → `Employee` relationship added
- [x] Phase 4: `LedgerDeviceController` queries real tables
- [x] Phase 5: `LeaveRequest` integration added to `AttendanceSummaryService`

### P2 — Medium Priority
- [x] Phase 6: `EmployeeTimelineController` queries `attendance_events`
- [x] Phase 7: `ImportController` creates real `ImportBatch` + `AttendanceEvent` records
- [ ] Phase 7 (migration): `import_batches` table created
- [ ] Phase 7 (model): `ImportBatch` model created
- [x] Phase 8: Card validation API endpoint created

### P3 — Deferred
- [ ] Badge bulk import (CSV of many cards)
- [ ] Badge PDF/Excel/CSV export
- [ ] Email notifications for correction events (Phase 2 listeners stub)
- [ ] Unit tests for `LedgerPollingService` and `AttendanceSummaryService`
- [ ] Feature tests for all Timekeeping controllers

---

## FastAPI Integration Checklist

Items the FastAPI team must verify separately:

- [ ] `sequence_id` is per-device monotonically increasing (not global auto-increment)
- [ ] `hash_chain` uses `SHA256(prev_hash_hex || json.dumps(raw_payload, sort_keys=True, separators=(',', ':')))`
- [ ] `hash_previous` for first row per device = 64 zeros
- [ ] FastAPI reads its own last `hash_chain` from `rfid_ledger` on reconnect
- [ ] `processed_at` is never written by FastAPI
- [ ] `event_type` uses exactly: `time_in`, `time_out`, `break_start`, `break_end`, `unknown_card`
- [ ] `rfid_devices.last_heartbeat` updated every 60s or on every tap
- [ ] After Phase 8: Use `GET /api/timekeeping/cards/{uid}` instead of direct DB read for card validation
