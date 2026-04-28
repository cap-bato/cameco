# Timekeeping Module Analysis Report

**Date:** 2025-07-11  
**Scope:** Full audit of the Timekeeping module — completeness, alignment, broken items, and FastAPI integration readiness  
**Framework:** Laravel 11, Inertia.js/React, PostgreSQL  

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [What is Complete and Working](#2-what-is-complete-and-working)
3. [Missing and Broken Items](#3-missing-and-broken-items)
4. [Mock Data Still in Production Path](#4-mock-data-still-in-production-path)
5. [Missing Event Listeners](#5-missing-event-listeners)
6. [Missing or Incomplete Migrations](#6-missing-or-incomplete-migrations)
7. [FastAPI Integration Specification](#7-fastapi-integration-specification)
8. [FastAPI Integration Gaps](#8-fastapi-integration-gaps)
9. [Priority Recommendations](#9-priority-recommendations)

---

## 1. Architecture Overview

### Data Pipeline

```
FastAPI RFID Server
        │
        │  writes rows with hash chain
        ▼
  PostgreSQL: rfid_ledger
        │
        │  ProcessRfidLedgerJob (every 1 min)
        │  via LedgerPollingService
        ▼
  attendance_events
        │
        │  GenerateDailySummariesCommand (daily 11:59 PM)
        │  via AttendanceSummaryService
        ▼
  daily_attendance_summary
        │
        ├──→  Payroll calculation engine
        └──→  Appraisal / performance metrics
```

### Supporting Tables

| Table | Purpose |
|---|---|
| `rfid_ledger` | Raw tap events written by FastAPI |
| `attendance_events` | Processed & de-duplicated events from ledger |
| `daily_attendance_summary` | Aggregated daily summaries per employee |
| `attendance_corrections` | Correction requests and approval workflow |
| `overtime_requests` | Employee overtime requests with approval |
| `rfid_devices` | Registered RFID readers with health status |
| `rfid_card_mappings` | Maps card UIDs to employees |
| `badge_issue_logs` | HR audit trail for badge issuance |
| `ledger_health_logs` | Device & ledger health event log |

### Key Integrity Mechanisms

- **Hash Chain:** SHA-256 chain per device — each row's hash is `SHA256(hash_previous_hex || raw_payload_json_string)`. Detects tampering, replays, and sequence gaps.
- **Deduplication Window:** 15-second window keyed by `"employee_rfid:device_id:event_type"`. Prevents double-taps from being counted.
- **Sequence Gaps:** `LedgerPollingService` compares `sequence_id` values and flags missing records for investigation.

---

## 2. What is Complete and Working

### 2.1 Services — Both Fully Implemented

**`LedgerPollingService`** (called by `ProcessRfidLedgerJob`)
- `pollNewEvents()` — queries `rfid_ledger` for unprocessed rows, ordered by `sequence_id`
- `deduplicateEvents()` — 15-second cache-backed deduplication
- `validateHashChain()` — per-device hash verification
- `createAttendanceEventsFromLedger()` — maps raw taps to semantic `attendance_events` records (time-in / time-out / break logic)
- `markLedgerEntriesAsProcessed()` — sets `processed_at` timestamp

**`AttendanceSummaryService`** (called by `GenerateDailySummariesCommand`)
- `computeDailySummary()` — calculates hours worked, break duration, overtime
- `applyBusinessRules()` — applies tardiness, undertime, holiday pay rules
- `storeDailySummary()` — upserts into `daily_attendance_summary`
- `dispatchSummaryUpdated()` — dispatches `AttendanceSummaryUpdated` event

### 2.2 Jobs and Scheduled Commands — All Registered

| Name | Schedule | Notes |
|---|---|---|
| `ProcessRfidLedgerJob` | Every 1 minute | 3 retries, exponential backoff (60/120/300s) |
| `CheckDeviceHealthCommand` | Every 2 minutes | Flags offline devices after >30 min silence |
| `CleanupDeduplicationCacheCommand` | Every 5 minutes | Clears stale dedup cache entries |
| `GenerateDailySummariesCommand` | Daily at 11:59 PM (Asia/Manila) | Triggers AttendanceSummaryService |
| `FinalizeAttendanceForPeriodCommand` | Manual / triggered | Locks attendance for payroll cut-off |

### 2.3 Controllers with Real Database Queries

| Controller | Key Functionality |
|---|---|
| `AnalyticsController` | 5-minute cache, real queries on DailyAttendanceSummary, AttendanceEvent, RfidLedger |
| `AttendanceController` | Full CRUD, 20/page pagination, filterable by date and employee |
| `AttendanceCorrectionController` | Complete 3-step workflow: request → approve/reject → summary update |
| `AttendanceFinalizeController` | Lock/unlock attendance for payroll periods, idempotent |
| `LedgerController` | Real DB pagination, health metrics, sequence gap detection |
| `LedgerHealthController` | Health monitoring with 5-minute cache, alert thresholds |
| `OvertimeController` | Real DB, full request → approve/reject lifecycle |
| `RfidBadgeController` | Real DB, badge issuance, activation/deactivation, audit via `badge_issue_logs` |

### 2.4 Database Migrations — All Core Tables Present

The following migration files exist and define the schema:

| Migration File | Table(s) |
|---|---|
| `2026_02_03_000001_create_rfid_ledger_table.php` | `rfid_ledger` |
| `2026_02_03_000002_create_attendance_events_table.php` | `attendance_events` |
| `2026_02_03_000003_create_daily_attendance_summary_table.php` | `daily_attendance_summary` |
| `2026_02_03_000004_create_ledger_health_logs_table.php` | `ledger_health_logs` |
| `2026_02_04_000001_create_attendance_corrections_table.php` | `attendance_corrections` |
| `2026_02_04_095813_create_rfid_devices_table.php` | `rfid_devices` |
| `2026_02_13_100000_create_rfid_card_mappings_table.php` | `rfid_card_mappings` |
| `2026_02_13_100100_create_badge_issue_logs_table.php` | `badge_issue_logs` |
| `2025_12_04_161139_create_attendance_correction_requests_table.php` | Older correction requests schema |
| `2026_03_01_165214_add_latency_ms_to_rfid_ledger_table.php` | `rfid_ledger.latency_ms` |
| `2026_03_06_100400_...` | `daily_attendance_summary.correction_applied` |
| `2026_03_07_041645_...` | `daily_attendance_summary.needs_schedule_review` |

> **Note:** There are now TWO migrations that create correction-related tables: `2025_12_04_161139_create_attendance_correction_requests_table.php` and `2026_02_04_000001_create_attendance_corrections_table.php`. The naming difference (`attendance_correction_requests` vs `attendance_corrections`) needs to be verified — the `AttendanceCorrection` model must reference the correct table name.

### 2.5 Events Defined

| Event | Where Dispatched |
|---|---|
| `AttendanceCorrectionRequested` | `AttendanceCorrectionController::store()` |
| `AttendanceCorrectionApproved` | `AttendanceCorrectionController::approve()` |
| `AttendanceCorrectionRejected` | `AttendanceCorrectionController::reject()` |
| `AttendanceSummaryUpdated` | `AttendanceSummaryService::dispatchSummaryUpdated()` |

### 2.6 Frontend Pages Implemented

All 15 Inertia/React pages exist:

| Page | Route |
|---|---|
| `Overview.tsx` | `/hr/timekeeping` |
| `Attendance/Index.tsx` | `/hr/timekeeping/attendance` |
| `Ledger.tsx` | `/hr/timekeeping/ledger` |
| `EventDetail.tsx` | `/hr/timekeeping/events/{id}` |
| `Devices.tsx` | `/hr/timekeeping/devices` |
| `EmployeeTimeline.tsx` | `/hr/timekeeping/timeline` |
| `Badges/Index.tsx` | `/hr/timekeeping/badges` |
| `Badges/Create.tsx` | `/hr/timekeeping/badges/create` |
| `Badges/Show.tsx` | `/hr/timekeeping/badges/{id}` |
| `Badges/InactiveBadges.tsx` | `/hr/timekeeping/badges/inactive` |
| `Overtime/Index.tsx` | `/hr/timekeeping/overtime` |
| `Import/Index.tsx` | `/hr/timekeeping/import` |
| `PerformanceTest.tsx` | `/hr/timekeeping/performance-test` |
| `IntegrationTest.tsx` | `/hr/timekeeping/integration-test` |
| `System/TimekeepingDevices/Index.tsx` | `/system/timekeeping-devices` |

---

## 3. Missing and Broken Items

### 3.1 Event Listeners — CRITICAL GAP

**Status:** 4 events are dispatched throughout the system, but **zero event listeners exist** for any of them, and **none are registered** in `EventServiceProvider`.

Current `EventServiceProvider` only maps Payroll events:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    Registered::class => [...],
    PayrollPeriodCreated::class => [...],
    PayrollCalculationStarted::class => [...],
    EmployeePayrollCalculated::class => [...],
    PayrollCalculationCompleted::class => [...],
    PayrollCalculationFailed::class => [...],
    // ← Timekeeping events are NOT listed here
];
```

The directory `app/Listeners/` contains only `Payroll/` subdirectory — there is no `Timekeeping/` subdirectory.

**Impact:**
- `AttendanceCorrectionRequested` — no notification sent to HR / employee
- `AttendanceCorrectionApproved` / `AttendanceCorrectionRejected` — no email notification sent
- `AttendanceSummaryUpdated` — downstream systems (payroll sync, appraisal) are not triggered

**Action Required:** Create listener classes and register them in `EventServiceProvider`.

### 3.2 Duplicate / Conflicting Correction Table Migrations

Two separate migration files create correction-related tables:

- `2025_12_04_161139_create_attendance_correction_requests_table.php` → table: `attendance_correction_requests`
- `2026_02_04_000001_create_attendance_corrections_table.php` → table: `attendance_corrections`

The `AttendanceCorrection` model must explicitly declare `protected $table` to avoid Eloquent's pluralization defaulting to the wrong table. The `AttendanceCorrectionController` references `AttendanceCorrection` model — if the table name mismatches, all correction operations will silently fail or throw.

**Action Required:** Confirm which table the `AttendanceCorrection` model uses and whether the older migration (`attendance_correction_requests`) should be dropped.

### 3.3 WorkSchedule Integration Not Confirmed

`AttendanceSummaryService::applyBusinessRules()` references employee work schedules to calculate tardiness and undertime. It is unclear whether:
- A `WorkSchedule` or `ScheduleAssignment` model exists
- The schedule lookup is wired or returns null/default fallback
- The `daily_attendance_summary.needs_schedule_review` flag is being used correctly when no schedule is found

**Action Required:** Trace the schedule lookup in `AttendanceSummaryService` and confirm the model/table exists.

### 3.4 LeaveRequest Integration Not Confirmed

`DailyAttendanceSummary` likely has an `is_on_leave` column. The logic that determines whether an absence should be marked as "on leave" (vs. AWOL) depends on reading approved `LeaveRequest` records. If this join/lookup is not implemented, all absences will be marked as AWOL regardless of approved leave.

**Action Required:** Confirm the leave lookup in `AttendanceSummaryService` and that the `LeaveRequest` model is queried during summary computation.

### 3.5 ImportBatch Model Not Found

`AttendanceEvent` or `ImportController` references an `ImportBatch` model (for tracking import jobs by file). This model file does not exist in `app/Models/`.

**Action Required:** Create the `ImportBatch` model and corresponding migration, or remove the reference if the import feature has been deferred entirely.

### 3.6 No Test Coverage for Timekeeping

The entire Timekeeping module has zero automated test coverage:
- No unit tests for `LedgerPollingService` or `AttendanceSummaryService`
- No feature tests for any controller
- No tests for hash chain validation logic
- No tests for deduplication window behavior

This is particularly risky for the hash chain and deduplication logic, which are correctness-critical.

---

## 4. Mock Data Still in Production Path

Five controllers return hardcoded / simulated data instead of querying the database. These are **not stubs** — they are wired into real routes and would be reached by users:

### 4.1 `DeviceController`

Returns 5 hardcoded device objects with fake IDs and status values. No query to `rfid_devices` table.

**Risk:** The Devices page shows fictional data. Any device registered in the database is invisible. Device health status is meaningless.

### 4.2 `EmployeeTimelineController`

Returns mock timeline data (hardcoded tap events). Marked internally as "work in progress."

**Risk:** Employee timeline view shows fake data. HR staff cannot use this for investigation or auditing.

### 4.3 `ImportController`

Simulates file parsing. The actual CSV/Excel import is not implemented — no file is read, no records are written.

**Risk:** Import functionality silently fails to create any records. Users receive success responses for imports that did nothing.

### 4.4 `LedgerDeviceController`

Generates random device metrics (throughput, latency, error rate) on each request. Not sourced from `rfid_ledger` or `ledger_health_logs`.

**Risk:** The Ledger → Device Metrics view shows fictional performance data. Cannot be used for monitoring.

### 4.5 `LedgerSyncController`

Simulates an async job dispatch rather than actually dispatching `ProcessRfidLedgerJob`. The sync endpoint responds with a fake job ID.

**Risk:** The "Trigger Manual Sync" button in the UI does nothing to actually process pending ledger entries.

---

## 5. Missing Event Listeners

This section details the specific listener classes that need to be created:

### Required Listener Classes

```
app/Listeners/Timekeeping/
├── NotifyAttendanceCorrectionRequested.php   ← email HR / manager
├── NotifyAttendanceCorrectionApproved.php    ← email employee
├── NotifyAttendanceCorrectionRejected.php    ← email employee
└── TriggerPayrollSyncOnSummaryUpdate.php     ← notify payroll of changed summaries
```

### Required EventServiceProvider Additions

```php
use App\Events\Timekeeping\AttendanceCorrectionRequested;
use App\Events\Timekeeping\AttendanceCorrectionApproved;
use App\Events\Timekeeping\AttendanceCorrectionRejected;
use App\Events\Timekeeping\AttendanceSummaryUpdated;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionRequested;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionApproved;
use App\Listeners\Timekeeping\NotifyAttendanceCorrectionRejected;
use App\Listeners\Timekeeping\TriggerPayrollSyncOnSummaryUpdate;

protected $listen = [
    // ... existing Payroll entries ...
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
];
```

---

## 6. Missing or Incomplete Migrations

### 6.1 Two Migrations for Correction Tables

As noted in Section 3.2, the system has two overlapping migrations:

```
2025_12_04_161139_create_attendance_correction_requests_table.php
2026_02_04_000001_create_attendance_corrections_table.php
```

Only one should be authoritative. Determine which table the `AttendanceCorrection` model actually uses and either:
- Drop the older migration entirely (requires checking if any code references `attendance_correction_requests`), or
- Add `protected $table = 'attendance_corrections';` explicitly in the model if not already present

### 6.2 `overtime_requests` Migration Exists

`2026_03_02_071219_create_overtime_requests_table.php` exists — `OvertimeController` is properly backed.

### 6.3 All Core Timekeeping Migrations Are Present

All 8 core timekeeping tables have migration files. Previously believed missing migrations (`attendance_corrections`, `ledger_health_logs`) **do exist**. The concern in earlier review was incorrect — both tables are properly defined.

---

## 7. FastAPI Integration Specification

This section defines the exact contract the FastAPI RFID server must fulfill to integrate with the Laravel Timekeeping module.

### 7.1 Shared Database

Both FastAPI and Laravel connect to the **same PostgreSQL database**. FastAPI is the writer; Laravel is the reader/processor.

> **Security Note:** FastAPI should use a dedicated PostgreSQL user with `INSERT` permission on `rfid_ledger` only, and `SELECT` on `rfid_card_mappings` and `rfid_devices`. It should not have write access to `attendance_events` or `daily_attendance_summary`.

### 7.2 `rfid_ledger` Table Schema (FastAPI writes this)

| Column | Type | Required | Notes |
|---|---|---|---|
| `id` | BIGSERIAL | auto | Primary key |
| `sequence_id` | BIGINT | **Yes** | Monotonically increasing per device; used for gap detection |
| `employee_rfid` | VARCHAR | **Yes** | The card UID scanned |
| `device_id` | VARCHAR | **Yes** | Unique device identifier (must exist in `rfid_devices`) |
| `scan_timestamp` | TIMESTAMPTZ | **Yes** | UTC timestamp of the tap event |
| `event_type` | VARCHAR | **Yes** | One of: `time_in`, `time_out`, `break_start`, `break_end` |
| `raw_payload` | JSONB | **Yes** | Full JSON payload from device firmware |
| `hash_chain` | VARCHAR(64) | **Yes** | SHA-256 hash of this row (see formula below) |
| `hash_previous` | VARCHAR(64) | **Yes** | Hash chain value of the previous row for this device |
| `latency_ms` | INTEGER | No | Network round-trip latency in milliseconds |
| `processed_at` | TIMESTAMPTZ | No | Set by Laravel after processing; **FastAPI must NOT write this** |

### 7.3 Hash Chain Formula

```
hash_chain = SHA256(hash_previous_hex_string + raw_payload_as_json_string)
```

**Rules:**
- `hash_previous` for the very first row of a device = `"0000000000000000000000000000000000000000000000000000000000000000"` (64 zeros)
- `raw_payload` must be serialized to a **consistent, deterministic** JSON string (sorted keys, no extra spaces)
- The hex digest must be lowercase

**Python Example:**
```python
import hashlib
import json

def compute_hash_chain(hash_previous: str, raw_payload: dict) -> str:
    payload_str = json.dumps(raw_payload, sort_keys=True, separators=(',', ':'))
    data = hash_previous + payload_str
    return hashlib.sha256(data.encode('utf-8')).hexdigest()
```

### 7.4 Device Heartbeat (FastAPI writes to `rfid_devices`)

FastAPI must periodically update the `last_heartbeat` column on the `rfid_devices` table:

```sql
UPDATE rfid_devices
SET last_heartbeat = NOW(), status = 'online'
WHERE device_id = $1;
```

- **Frequency:** Every 60 seconds or upon any successful tap event
- Laravel's `CheckDeviceHealthCommand` runs every 2 minutes and marks a device offline if `last_heartbeat` is older than 30 minutes

### 7.5 Card Validation (FastAPI reads `rfid_card_mappings`)

Before inserting into `rfid_ledger`, FastAPI should validate that the scanned card UID is registered:

```sql
SELECT employee_id, is_active
FROM rfid_card_mappings
WHERE card_uid = $1
  AND is_active = true;
```

- If no row is found → log to `rfid_ledger` with `event_type = 'unknown_card'` (or reject silently)
- If `is_active = false` → reject the tap, return appropriate device feedback

### 7.6 Manual Sync Trigger Endpoint (Laravel exposes this)

Laravel provides a REST endpoint for manual resync (e.g., after FastAPI replay):

```
POST /hr/timekeeping/api/ledger/sync/trigger
Authorization: Bearer <api_token>
Content-Type: application/json

{
  "device_id": "DEVICE-001",   // optional: limit sync to one device
  "from_sequence": 1000         // optional: reprocess from sequence
}
```

> **Warning:** `LedgerSyncController` currently has a **mock implementation** that does not actually dispatch the job. See Section 4.5. This endpoint is non-functional until fixed.

### 7.7 Deduplication Contract

Laravel deduplicates on the **Laravel side** using a 15-second cache window. FastAPI does **not** need to deduplicate. However, FastAPI should:
- Always include accurate `scan_timestamp`
- Never backdate events by more than 60 seconds without using the manual sync endpoint
- For offline replay scenarios, use the sync trigger endpoint rather than bulk-inserting to `rfid_ledger` directly

### 7.8 Sequence ID Management

`sequence_id` must be:
- **Per-device monotonically increasing** (not global)
- Managed by FastAPI (not auto-incremented by PostgreSQL default)
- Or alternatively, use a PostgreSQL sequence per device and read it before inserting

Laravel's `LedgerPollingService` detects gaps in `sequence_id`:
```
Expected: [1001, 1002, 1003]
Got:      [1001, 1003]         ← gap at 1002 → flagged for investigation
```

---

## 8. FastAPI Integration Gaps

These are items in the current Flask/FastAPI layer that may not align with what Laravel expects:

### 8.1 `sequence_id` Source Not Confirmed

It is unclear whether FastAPI currently generates per-device monotonic sequence IDs or uses the PostgreSQL `BIGSERIAL` default (which is global, not per-device). Using a global auto-increment will cause false gap alerts in `LedgerPollingService`.

**Action Required:** FastAPI must maintain per-device counters and write them as `sequence_id`.

### 8.2 Hash Chain on New Device Bootstrap

When a new device is registered in `rfid_devices`, FastAPI must initialize `hash_previous = "0000...0"` (64 zeros) for the very first insert. There is no endpoint in Laravel to retrieve the "last known hash" for a device after reconnection — FastAPI must query `rfid_ledger` directly:

```sql
SELECT hash_chain
FROM rfid_ledger
WHERE device_id = $1
ORDER BY sequence_id DESC
LIMIT 1;
```

### 8.3 `event_type` Vocabulary Must Match

Laravel's `LedgerPollingService` maps `event_type` values to attendance event semantics. FastAPI must use exactly these values:
- `time_in`
- `time_out`
- `break_start`
- `break_end`
- (optionally) `unknown_card`

Any other value (e.g., `checkin`, `checkout`, `in`, `out`) will cause mapping failures in `createAttendanceEventsFromLedger()`.

### 8.4 `processed_at` Column Must Be NULL on Insert

FastAPI must never set `processed_at`. Laravel uses `WHERE processed_at IS NULL` to find unprocessed entries. If FastAPI accidentally writes a value here, those entries will never be processed.

### 8.5 `raw_payload` Must Be Deterministic JSON

The hash chain depends on deterministic JSON serialization. FastAPI must not use language-default `json.dumps()` without `sort_keys=True`, as Python dict ordering (while insertion-ordered in 3.7+) may differ from other serialization paths.

### 8.6 No Card UID → Employee Lookup Endpoint in Laravel

Currently there is no authenticated REST API in Laravel that FastAPI can call to validate a card before tapping. FastAPI must either:
- Read `rfid_card_mappings` directly from the shared PostgreSQL database (current implicit assumption), or
- Use a to-be-added endpoint like `GET /api/timekeeping/cards/{uid}`

This is a design gap — direct database reads from FastAPI create tight coupling. A proper API endpoint would be safer and more maintainable.

---

## 9. Priority Recommendations

### P0 — Blocking Issues (Fix Before Going Live)

| # | Issue | File(s) to Fix |
|---|---|---|
| 1 | `LedgerSyncController` is mock — manual sync does nothing | `app/Http/Controllers/Hr/Timekeeping/LedgerSyncController.php` |
| 2 | Correction table name ambiguity (two migrations) | `app/Models/AttendanceCorrection.php`, migration audit |
| 3 | FastAPI `event_type` values must match Laravel vocabulary | FastAPI codebase |
| 4 | FastAPI must not write `processed_at` | FastAPI codebase |

### P1 — High Priority (Fix Before User Acceptance Testing)

| # | Issue | File(s) to Fix |
|---|---|---|
| 5 | Event listeners not created — all 4 events go unhandled | Create `app/Listeners/Timekeeping/` + update `EventServiceProvider` |
| 6 | `DeviceController` returns hardcoded data | `app/Http/Controllers/Hr/Timekeeping/DeviceController.php` |
| 7 | `LedgerDeviceController` returns fake metrics | `app/Http/Controllers/Hr/Timekeeping/LedgerDeviceController.php` |
| 8 | WorkSchedule integration in `AttendanceSummaryService` not confirmed | `app/Services/Timekeeping/AttendanceSummaryService.php` |
| 9 | LeaveRequest integration not confirmed | `app/Services/Timekeeping/AttendanceSummaryService.php` |

### P2 — Medium Priority (Complete Before Production)

| # | Issue | File(s) to Fix |
|---|---|---|
| 10 | `EmployeeTimelineController` is WIP mock | `app/Http/Controllers/Hr/Timekeeping/EmployeeTimelineController.php` |
| 11 | `ImportController` is mock — no CSV/Excel processing | `app/Http/Controllers/Hr/Timekeeping/ImportController.php` |
| 12 | `ImportBatch` model missing | Create `app/Models/ImportBatch.php` + migration |
| 13 | Card UID validation should use API endpoint, not direct DB | Design a `GET /api/timekeeping/cards/{uid}` route |
| 14 | FastAPI per-device `sequence_id` management | FastAPI codebase |

### P3 — Deferred / Phase 2

| # | Issue | Notes |
|---|---|---|
| 15 | Badge bulk import (CSV upload of many cards at once) | `RfidBadgeController` Phase 2 |
| 16 | Badge export (PDF/Excel/CSV) | `RfidBadgeController` Phase 2 |
| 17 | Email delivery on corrections | Depends on P1 listener creation |
| 18 | Unit + feature test coverage for Timekeeping | Zero coverage currently |

---

## Summary Status Table

| Component | Status |
|---|---|
| Core polling pipeline (RFID → ledger → events) | ✅ Complete |
| Daily summary generation | ✅ Complete |
| Attendance correction workflow | ✅ Complete (UI + controller logic) |
| Overtime request workflow | ✅ Complete |
| Badge management | ✅ Complete (Phase 2 deferred) |
| Finalization / lock for payroll | ✅ Complete |
| Analytics & reporting | ✅ Complete |
| Health monitoring | ✅ Complete |
| Database migrations | ✅ All tables present (⚠️ duplicate correction table names) |
| **Event listeners** | ❌ **None registered — all 4 events unhandled** |
| Device management UI | ❌ Mock data |
| Employee timeline view | ❌ Mock / WIP |
| Ledger device metrics | ❌ Mock data |
| Manual sync endpoint | ❌ Mock — does nothing |
| CSV/Excel import | ❌ Not implemented |
| FastAPI hash chain contract | ⚠️ Partially documented, alignment unverified |
| FastAPI sequence ID management | ⚠️ Not confirmed per-device |
| Test coverage | ❌ Zero |
