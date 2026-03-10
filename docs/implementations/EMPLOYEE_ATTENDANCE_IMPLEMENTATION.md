# Employee Attendance — Backend/Frontend Integration

**Page:** `http://localhost:8000/employee/attendance`  
**Status:** Mock attendance & RFID data only; `reportIssue()` partially functional (DB insert works, notification missing)  
**Priority:** HIGH — also has a **permission assignment problem** (see §3-J)  
**Created:** 2026-03-10

---

## 1. Current State

### What exists

| Layer | File | State |
|---|---|---|
| Controller | `app/Http/Controllers/Employee/AttendanceController.php` | `index()` uses mock; `reportIssue()` inserts into DB (working) but missing HR notification |
| Model | `app/Models/DailyAttendanceSummary.php` | Complete — all fields, scopes, relationships |
| Model | `app/Models/RfidLedger.php` | Complete — append-only ledger, scopes by RFID, date range |
| Model | `app/Models/RfidCardMapping.php` | Complete — maps card UIDs to employees, `active` scope |
| Model | `app/Models/RfidDevice.php` | Complete — device_id → device_name + location lookup |
| Migration | `database/migrations/2026_02_03_000003_create_daily_attendance_summary_table.php` | Table exists, complete schema |
| Migration | `database/migrations/2026_02_03_000001_create_rfid_ledger_table.php` | Table exists |
| Migration | `database/migrations/2025_12_04_161139_create_attendance_correction_requests_table.php` | Table exists |
| Routes | `routes/employee.php` lines 46–58 | 2 routes registered correctly |
| Frontend page | `resources/js/pages/Employee/Attendance/Index.tsx` | Fully structured — uses Inertia props |
| Request | `app/Http/Requests/Employee/AttendanceIssueRequest.php` | Complete validation rules |
| Employee model | `app/Models/Employee.php` | Missing `dailyAttendanceSummaries()` HasMany |
| Permissions | `database/seeders/EmployeeRoleSeeder.php` | `employee.attendance.view` and `employee.attendance.report` defined but may not be assigned |

---

## 2. Data Shape Analysis

### TypeScript `AttendanceIndexProps` (frontend contract)

```ts
interface AttendanceRecord {
    date: string;                                              // 'Y-m-d'
    status: 'present' | 'late' | 'absent' | 'on_leave' | 'rest_day';
    time_in?: string;                                         // 'H:i:s' or null
    time_out?: string;                                        // 'H:i:s' or null
    hours_worked?: number;
    late_minutes?: number;
    remarks?: string;
}

interface AttendanceSummary {
    days_present: number;
    days_late: number;
    days_absent: number;
    days_on_leave: number;
    total_hours_worked: number;
    average_hours_per_day: number;
}

interface RFIDPunch {
    id: number;
    timestamp: string;                                        // ISO datetime
    type: 'IN' | 'OUT' | 'BREAK_IN' | 'BREAK_OUT';
    device_name: string;
    location: string;
}

interface AttendanceIndexProps {
    employee: EmployeeInfo;
    attendanceRecords: AttendanceRecord[];
    attendanceSummary: AttendanceSummary;
    rfidPunchHistory: RFIDPunch[];
    filters: { view: string; start_date: string; end_date: string; };
    error?: string;
}
```

### `DailyAttendanceSummary` → `AttendanceRecord` field mapping

| Frontend field | DB column | Notes |
|---|---|---|
| `date` | `attendance_date` | Format as `'Y-m-d'` |
| `status` | `is_present`, `is_late`, `is_on_leave` | **Derived** (see status table) |
| `time_in` | `time_in` | Format as `'H:i:s'`; null if absent/leave |
| `time_out` | `time_out` | Format as `'H:i:s'`; null if absent/leave |
| `hours_worked` | `total_hours_worked` | Nullable decimal |
| `late_minutes` | `late_minutes` | Nullable int |
| `remarks` | `leave_request_id != null` → `'Approved Leave'`; `correction_applied` → `'Corrected'` | Derived string |

### Status derivation (DB flags → frontend `status`)

```php
// Precedence: on_leave > present/late > absent > rest_day
if ($record->is_on_leave) {
    return 'on_leave';
}
if ($record->is_present && $record->is_late) {
    return 'late';
}
if ($record->is_present) {
    return 'present';
}
// No record for date AND it's a weekday → 'absent'
// No record AND it's a weekend → 'rest_day'
return 'absent';
```

**Note on rest days:** `DailyAttendanceSummary` only has rows for working days. Weekend dates in the range will have no DB row. When building the date-by-date response, dates with no row AND falling on Saturday/Sunday should be `rest_day`; weekday dates with no row are `absent`.

### `RfidLedger` → `RFIDPunch` field mapping

| Frontend field | Source | Notes |
|---|---|---|
| `id` | `rfid_ledger.id` | |
| `timestamp` | `rfid_ledger.scan_timestamp` | ISO 8601 string |
| `type` | `rfid_ledger.event_type` | Map: `time_in`→`'IN'`, `time_out`→`'OUT'`, `break_start`→`'BREAK_IN'`, `break_end`→`'BREAK_OUT'` |
| `device_name` | `rfid_devices.device_name` | Join via `rfid_ledger.device_id = rfid_devices.device_id` |
| `location` | `rfid_devices.location` | Same join |

### RFID lookup chain

```
Employee → rfid_card_mappings (is_active=true) → card_uid
→ rfid_ledger (employee_rfid = card_uid) WHERE scan_timestamp BETWEEN dates
→ rfid_devices (device_id = rfid_ledger.device_id) for device_name + location
```

### `AttendanceSummary` derivation

```php
// Derived from DailyAttendanceSummary records in date range
$daysPresent = $records->filter(fn($r) => $r->is_present && !$r->is_late)->count();
$daysLate    = $records->filter(fn($r) => $r->is_late)->count();
$daysOnLeave = $records->filter(fn($r) => $r->is_on_leave)->count();
// days_absent = expected working days - (present + late + on_leave)
$expectedWorkdays = $this->countWeekdays($startDate, $endDate);
$daysAbsent = max(0, $expectedWorkdays - ($daysPresent + $daysLate + $daysOnLeave));
$totalHours = $records->sum('total_hours_worked');
$daysWithHours = $records->where('total_hours_worked', '>', 0)->count();
$avgHours = $daysWithHours > 0 ? $totalHours / $daysWithHours : 0;
```

---

## 3. Issues to Resolve

| # | Issue | Severity | Fix |
|---|---|---|---|
| A | `AttendanceController::index()` uses `getMockAttendanceRecords()` | BLOCKING | Replace with real `DailyAttendanceSummary` query |
| B | `AttendanceController::index()` uses `getMockRFIDPunchHistory()` | BLOCKING | Replace with real `RfidLedger` query via `RfidCardMapping` |
| C | `calculateAttendanceSummary()` receives mock records; `days_absent` is hardcoded 0 | BUG | Rework as real summary from DB records; compute absent from expected workdays |
| D | `Employee.php` missing `dailyAttendanceSummaries()` HasMany | MISSING | Add relationship |
| E | RFID lookup uses `employee_rfid` (card UID string), not `employee_id` — needs join through `RfidCardMapping` | DESIGN | Get active card UIDs from `rfid_card_mappings`, then query `rfid_ledger.employee_rfid IN (...)` |
| F | RFID punch `device_name` and `location` not available on `RfidLedger` — requires join with `rfid_devices` | MISSING | Cache device lookup in a keyed array within the controller method |
| G | Frontend month navigation uses local state only (`setCurrentMonth`); no Inertia visit triggered | INCOMPLETE | Replace `handlePreviousMonth`/`handleNextMonth` stubs with `router.get()` Inertia visits passing `start_date` and `end_date` query params |
| H | `reportIssue()` is missing the HR notification step (TODO comment in code) | INCOMPLETE | Add `Notification::send()` call to HR Staff users after successful DB insert |
| I | `event_type` mismatch: DB stores `time_in/time_out/break_start/break_end`; frontend expects `IN/OUT/BREAK_IN/BREAK_OUT` | BREAKING | Add `mapRfidEventType()` helper |
| J | **Permission problem**: `employee.attendance.view` defined in seeder but may not be assigned to employee users — causes 403 | PERMISSION | Run `php artisan db:seed --class=EmployeeRoleSeeder` OR manually assign role |

---

## 4. Phased Implementation

### Phase 1 — Employee model relationship + real attendance records

**Goal:** `GET /employee/attendance` returns real `DailyAttendanceSummary` records instead of mock data.

#### Task 1.1 — Add `dailyAttendanceSummaries()` to `Employee.php`

File: `app/Models/Employee.php`

Add after `rfidCardMappings()` relationship:

```php
/**
 * Get daily attendance summaries for this employee.
 */
public function dailyAttendanceSummaries(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\DailyAttendanceSummary::class)
        ->orderBy('attendance_date', 'desc');
}
```

#### Task 1.2 — Add private helpers to `AttendanceController`

File: `app/Http/Controllers/Employee/AttendanceController.php`

**a) `mapRfidEventType()`**
```php
private function mapRfidEventType(string $eventType): string
{
    return match ($eventType) {
        'time_in'     => 'IN',
        'time_out'    => 'OUT',
        'break_start' => 'BREAK_IN',
        'break_end'   => 'BREAK_OUT',
        default       => 'IN',
    };
}
```

**b) `deriveAttendanceStatus()`**
```php
private function deriveAttendanceStatus(?\App\Models\DailyAttendanceSummary $record, string $date): string
{
    if (!$record) {
        $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek;
        return in_array($dayOfWeek, [0, 6]) ? 'rest_day' : 'absent';
    }
    if ($record->is_on_leave)              return 'on_leave';
    if ($record->is_present && $record->is_late) return 'late';
    if ($record->is_present)               return 'present';
    return 'absent';
}
```

**c) `countWeekdays()`**
```php
private function countWeekdays(string $startDate, string $endDate): int
{
    $start  = \Carbon\Carbon::parse($startDate);
    $end    = \Carbon\Carbon::parse($endDate);
    $count  = 0;

    while ($start->lte($end)) {
        if ($start->isWeekday()) {
            $count++;
        }
        $start->addDay();
    }

    return $count;
}
```

**d) `buildAttendanceRecords()`**

Builds a date-by-date array covering the full range, filling absent/rest days:

```php
private function buildAttendanceRecords(
    \Illuminate\Support\Collection $summaries,
    string $startDate,
    string $endDate
): array {
    // Key summaries by date string for O(1) lookup
    $byDate = $summaries->keyBy(fn($s) => $s->attendance_date instanceof \Carbon\Carbon
        ? $s->attendance_date->format('Y-m-d')
        : $s->attendance_date
    );

    $records = [];
    $current = \Carbon\Carbon::parse($startDate);
    $end     = \Carbon\Carbon::parse($endDate);

    while ($current->lte($end)) {
        $dateStr = $current->format('Y-m-d');
        $record  = $byDate->get($dateStr);

        $records[] = [
            'date'         => $dateStr,
            'status'       => $this->deriveAttendanceStatus($record, $dateStr),
            'time_in'      => $record?->time_in?->format('H:i:s'),
            'time_out'     => $record?->time_out?->format('H:i:s'),
            'hours_worked' => $record ? (float) $record->total_hours_worked : null,
            'late_minutes' => $record?->late_minutes,
            'remarks'      => $record?->leave_request_id
                ? 'Approved Leave'
                : ($record?->correction_applied ? 'Corrected' : null),
        ];

        $current->addDay();
    }

    return $records;
}
```

#### Task 1.3 — Replace `getMockAttendanceRecords()` usage in `index()`

Replace the try block inside `index()`:

```php
// Query real attendance records
$summaries = \App\Models\DailyAttendanceSummary::where('employee_id', $employee->id)
    ->whereBetween('attendance_date', [$startDate, $endDate])
    ->orderBy('attendance_date')
    ->get();

$attendanceRecords  = $this->buildAttendanceRecords($summaries, $startDate, $endDate);
$attendanceSummary  = $this->buildAttendanceSummary($summaries, $startDate, $endDate);
$rfidPunchHistory   = $this->getRealRFIDPunchHistory($employee, $startDate, $endDate);

return Inertia::render('Employee/Attendance/Index', [
    'employee' => [
        'id'              => $employee->id,
        'employee_number' => $employee->employee_number,
        'full_name'       => $employee->profile->full_name ?? $user->name,
        'department'      => $employee->department->name ?? 'N/A',
    ],
    'attendanceRecords'  => $attendanceRecords,
    'attendanceSummary'  => $attendanceSummary,
    'rfidPunchHistory'   => $rfidPunchHistory,
    'filters' => [
        'view'       => $view,
        'start_date' => $startDate,
        'end_date'   => $endDate,
    ],
]);
```

---

### Phase 2 — Real attendance summary + RFID punch history

#### Task 2.1 — Add `buildAttendanceSummary()` (replaces `calculateAttendanceSummary()`)

```php
private function buildAttendanceSummary(
    \Illuminate\Support\Collection $summaries,
    string $startDate,
    string $endDate
): array {
    $daysPresent = $summaries->filter(fn($r) => $r->is_present && !$r->is_late)->count();
    $daysLate    = $summaries->filter(fn($r) => $r->is_late)->count();
    $daysOnLeave = $summaries->filter(fn($r) => $r->is_on_leave)->count();

    $expectedWorkdays = $this->countWeekdays($startDate, $endDate);
    $daysAbsent = max(0, $expectedWorkdays - ($daysPresent + $daysLate + $daysOnLeave));

    $totalHours    = (float) $summaries->sum('total_hours_worked');
    $daysWithHours = $summaries->where('total_hours_worked', '>', 0)->count();
    $avgHours      = $daysWithHours > 0 ? round($totalHours / $daysWithHours, 2) : 0.0;

    return [
        'days_present'         => $daysPresent,
        'days_late'            => $daysLate,
        'days_absent'          => $daysAbsent,
        'days_on_leave'        => $daysOnLeave,
        'total_hours_worked'   => round($totalHours, 2),
        'average_hours_per_day'=> $avgHours,
    ];
}
```

#### Task 2.2 — Add `getRealRFIDPunchHistory()` (replaces `getMockRFIDPunchHistory()`)

```php
private function getRealRFIDPunchHistory(
    \App\Models\Employee $employee,
    string $startDate,
    string $endDate
): array {
    // Get all active RFID card UIDs for this employee
    $cardUids = \App\Models\RfidCardMapping::where('employee_id', $employee->id)
        ->where('is_active', true)
        ->pluck('card_uid')
        ->toArray();

    if (empty($cardUids)) {
        return [];
    }

    // Fetch ledger entries within date range
    $ledgerEntries = \App\Models\RfidLedger::whereIn('employee_rfid', $cardUids)
        ->whereBetween('scan_timestamp', [
            \Carbon\Carbon::parse($startDate)->startOfDay(),
            \Carbon\Carbon::parse($endDate)->endOfDay(),
        ])
        ->orderBy('scan_timestamp', 'desc')
        ->limit(200) // Safety cap to avoid huge result sets
        ->get();

    if ($ledgerEntries->isEmpty()) {
        return [];
    }

    // Pre-fetch device names to avoid N+1
    $deviceIds = $ledgerEntries->pluck('device_id')->unique()->toArray();
    $devices   = \App\Models\RfidDevice::whereIn('device_id', $deviceIds)
        ->get()
        ->keyBy('device_id');

    return $ledgerEntries->map(function ($entry) use ($devices) {
        $device = $devices->get($entry->device_id);
        return [
            'id'          => $entry->id,
            'timestamp'   => $entry->scan_timestamp->toISOString(),
            'type'        => $this->mapRfidEventType($entry->event_type),
            'device_name' => $device?->device_name ?? $entry->device_id,
            'location'    => $device?->location ?? 'Unknown',
        ];
    })->toArray();
}
```

---

### Phase 3 — HR notification for reportIssue() + month navigation

#### Task 3.1 — Add HR notification to `reportIssue()`

File: `app/Http/Controllers/Employee/AttendanceController.php`

After the `DB::commit()` call inside `reportIssue()`, replace the TODO comment:

```php
// Notify all HR Staff users about the pending correction request
try {
    $hrUsers = \App\Models\User::role('HR Staff')->get();
    // TODO: Create App\Notifications\AttendanceCorrectionRequested notification class
    // \Illuminate\Support\Facades\Notification::send(
    //     $hrUsers,
    //     new \App\Notifications\AttendanceCorrectionRequested($employee, $correctionRequestId, $validated)
    // );
} catch (\Exception $notifyException) {
    Log::warning('Failed to send attendance correction notification to HR Staff', [
        'employee_id'           => $employee->id,
        'correction_request_id' => $correctionRequestId,
        'error'                 => $notifyException->getMessage(),
    ]);
    // Do not re-throw — the request was saved successfully
}
```

#### Task 3.2 — Wire month navigation in frontend

File: `resources/js/pages/Employee/Attendance/Index.tsx`

Replace the TODO stubs inside `handlePreviousMonth()` and `handleNextMonth()`:

```tsx
import { router } from '@inertiajs/react';
import { format, startOfMonth, endOfMonth, subMonths, addMonths } from 'date-fns';

const handlePreviousMonth = () => {
    const newMonth = subMonths(currentMonth, 1);
    setCurrentMonth(newMonth);
    router.get(route('employee.attendance.index'), {
        view: filters.view,
        start_date: format(startOfMonth(newMonth), 'yyyy-MM-dd'),
        end_date:   format(endOfMonth(newMonth), 'yyyy-MM-dd'),
    }, { preserveState: true, preserveScroll: true });
};

const handleNextMonth = () => {
    const newMonth = addMonths(currentMonth, 1);
    setCurrentMonth(newMonth);
    router.get(route('employee.attendance.index'), {
        view: filters.view,
        start_date: format(startOfMonth(newMonth), 'yyyy-MM-dd'),
        end_date:   format(endOfMonth(newMonth), 'yyyy-MM-dd'),
    }, { preserveState: true, preserveScroll: true });
};
```

---

### Phase 4 — Cleanup

#### Task 4.1 — Remove mock private methods from `AttendanceController`

Delete these three private methods:
- `getMockAttendanceRecords(int $employeeId, string $startDate, string $endDate): array`
- `calculateAttendanceSummary(array $records): array` (replaced by `buildAttendanceSummary()`)
- `getMockRFIDPunchHistory(int $employeeId, string $startDate, string $endDate): array`

---

## 5. Permission Problem — Root Cause & Fix

### Problem
Route `GET /employee/attendance` has middleware:
```php
->middleware('permission:employee.attendance.view')
```

If the authenticated user does not have this permission, Laravel throws a 403 / redirect. This is NOT a code bug — it is a **seeder/role assignment issue**.

### Diagnosis steps
```bash
# Check what permissions the current user has
php artisan tinker
>>> $user = \App\Models\User::find(YOUR_USER_ID);
>>> $user->getAllPermissions()->pluck('name');
>>> $user->getRoleNames();
```

### Fix options

**Option A — Re-run the seeder (recommended)**
```bash
php artisan db:seed --class=EmployeeRoleSeeder
```
This creates/re-grants all `employee.*` permissions to the `Employee` role.

**Option B — Manually assign role to a specific user**
```bash
php artisan tinker
>>> $user = \App\Models\User::where('email', 'employee@example.com')->first();
>>> $user->assignRole('Employee');
```

**Option C — Add permission directly to user (not recommended)**
```bash
>>> $user->givePermissionTo('employee.attendance.view');
>>> $user->givePermissionTo('employee.attendance.report');
```

### Prevention
The `EnsureEmployee` middleware already verifies the user has an employee record, but does NOT auto-assign the `Employee` role. Consider adding a check in `EnsureEmployee` to auto-assign the `Employee` role if the user is linked to an employee record.

---

## 6. Notes on Timekeeping Module Dependency

The attendance page is **dependent on the Timekeeping module** having recorded real data:

| Data source | Module | Status |
|---|---|---|
| `daily_attendance_summary` | Timekeeping | Table exists; populated via RFID event processing pipeline |
| `rfid_ledger` | Timekeeping via FastAPI RFID server | Table exists; populated by external RFID server |
| `rfid_card_mappings` | HR (badge management) | Table exists; requires HR to assign badges to employees |

**If Timekeeping data is empty** (no RFID hardware, development environment), the page will render with:
- `attendanceRecords` = empty array of absent/rest days
- `rfidPunchHistory` = empty array
- Summary = all zeros

This is graceful degradation — no error thrown. The frontend renders an empty state correctly.

---

## 7. Test Plan

### Unit tests

| Test | Expected |
|---|---|
| `deriveAttendanceStatus(null, '2026-03-10')` → `'absent'` (Monday) | ✅ |
| `deriveAttendanceStatus(null, '2026-03-08')` → `'rest_day'` (Sunday) | ✅ |
| `mapRfidEventType('break_start')` → `'BREAK_IN'` | ✅ |
| `buildAttendanceSummary()` with 1 late + 1 present + 5 workdays → 3 absent | ✅ |
| `buildAttendanceSummary()` `average_hours_per_day` excludes zero-hour days | ✅ |

### Integration tests

| Test | Expected |
|---|---|
| `GET /employee/attendance` with no DAS records → empty arrays, no 500 | 200 |
| `GET /employee/attendance` returns only own records | Self-only |
| `POST /employee/attendance/report-issue` with valid data → success flash | 302 redirect |
| `POST /employee/attendance/report-issue` with `actual_time_out` before `actual_time_in` → validation error | 422 |
| Employee without `employee.attendance.view` permission → 403 | 403 |

### Manual tests

- [ ] Log in as Employee user, visit `/employee/attendance` — page loads without error
- [ ] Summary cards show correct days_present / days_late / days_absent
- [ ] Calendar shows colored date cells (green = present, yellow = late, etc.)
- [ ] Click on a date showing attendance data — time_in / time_out displayed
- [ ] RFID Punch History section shows real data (or empty state if no data)
- [ ] Previous/Next month navigation sends new Inertia request with updated date range
- [ ] "Report Attendance Issue" modal submits and shows success flash

---

## 8. Related Files

| File | Relation |
|---|---|
| `app/Models/DailyAttendanceSummary.php` | Core model for attendance records |
| `app/Models/RfidLedger.php` | RFID scan events source for punch history |
| `app/Models/RfidCardMapping.php` | Links employees to RFID card UIDs |
| `app/Models/RfidDevice.php` | Device name/location lookup by device_id |
| `app/Models/AttendanceCorrection.php` | Model for correction records (separate from requests table) |
| `app/Http/Requests/Employee/AttendanceIssueRequest.php` | Validation rules (complete — no changes needed) |
| `app/Http/Middleware/EnsureEmployee.php` | Confirms user has an employee record; does NOT auto-assign Employee role |
| `database/seeders/EmployeeRoleSeeder.php` | Source of truth for `employee.attendance.*` permissions |
| `docs/TIMEKEEPING_MODULE_STATUS_REPORT.md` | Timekeeping module status — `daily_attendance_summary` is real DB |
| `docs/implementations/TIMEKEEPING_TO_PAYROLL_FLOW.md` | How attendance feeds into payroll |

---

## 9. Progress

- [ ] Phase 1: `Employee::dailyAttendanceSummaries()` + helpers + real `index()`
- [ ] Phase 2: Real `buildAttendanceSummary()` + real `getRealRFIDPunchHistory()`
- [ ] Phase 3: HR notification in `reportIssue()` + frontend month navigation wiring
- [ ] Phase 4: Remove mock methods
- [ ] Fix: Resolve permission 403 by rerunning `EmployeeRoleSeeder`
