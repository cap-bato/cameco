# RFID Ledger → Attendance Events: Seed & Process Pipeline

**Date:** 2026-03-07  
**Scope:** Seed `rfid_ledger` with realistic Feb 2 → Mar 6 scan data and drive the  
full `ProcessRfidLedgerJob` pipeline to produce `attendance_events`.

---

## How The Full Pipeline Works

```
[FastAPI RFID server]
    │  HTTP POST per tap (or emulated by seeder)
    ▼
[rfid_ledger] table                         ← append-only, immutable
    │  Fields: sequence_id, employee_rfid (→ rfid_card_mappings.card_uid),
    │          device_id, scan_timestamp, event_type, raw_payload,
    │          hash_chain (SHA-256), hash_previous, processed (default false)
    │
    ▼  scheduler: every 1 min  OR  manually via seeder
ProcessRfidLedgerJob
    │  app/Jobs/Timekeeping/ProcessRfidLedgerJob.php
    │  calls → LedgerPollingService::processLedgerEventsComplete()
    │
    ▼
LedgerPollingService  (app/Services/Timekeeping/LedgerPollingService.php)
    │
    ├── Step 1  pollNewEvents(1000)
    │           SELECT * FROM rfid_ledger WHERE processed=false ORDER BY sequence_id LIMIT 1000
    │
    ├── Step 2  deduplicateEvents()
    │           Within-batch: same employee_rfid + device_id + event_type within 15 s = duplicate
    │
    ├── Step 3  findExistingAttendanceEvents()
    │           Cross-check against attendance_events.ledger_sequence_id
    │
    ├── Step 4  validateHashChain()
    │           Verifies SHA-256(prev_hash || json(raw_payload)) = hash_chain for each row
    │
    ├── Step 5  createAttendanceEventsFromLedger()
    │           For each processable event:
    │             a. resolveEmployeeId(employee_rfid)  ← looks up rfid_card_mappings.card_uid
    │             b. AttendanceEvent::create([employee_id, event_date, event_time, event_type,
    │                                         ledger_sequence_id, source='edge_machine', ...])
    │
    └── Step 6  markLedgerEntriesAsProcessed()
                UPDATE rfid_ledger SET processed=true, processed_at=now() WHERE sequence_id IN (...)

    ▼
[attendance_events] table  ← one row per valid, non-duplicate tap
    │
    ▼  (handled separately by existing AttendanceEventsSeeder / generate-daily-summaries)
[daily_attendance_summary] table
```

### Key table / model linkage

| Table | Key field | Links to |
|---|---|---|
| `rfid_ledger` | `employee_rfid` | `rfid_card_mappings.card_uid` |
| `rfid_card_mappings` | `employee_id` | `employees.id` |
| `attendance_events` | `ledger_sequence_id` | `rfid_ledger.sequence_id` |
| `attendance_events` | `employee_id` | `employees.id` |

### Hash chain formula

```
row.hash_chain = SHA-256( (previous_row.hash_chain ?? '') || json_encode(row.raw_payload) )
```
Genesis block (first ever row): `hash_previous = null`, `hash_chain = SHA-256('' || json(...))`.

---

## Critical Bug (Pre-existing)

**File:** `app/Services/Timekeeping/LedgerPollingService.php`  
**Method:** `resolveEmployeeId(string $employeeRfid): ?int`

```php
// CURRENT — ignores the $employeeRfid argument entirely!
private function resolveEmployeeId(string $employeeRfid): ?int
{
    $employee = \App\Models\Employee::first();   // ← WRONG
    return $employee?->id;
}
```

This means **every scan gets attributed to employee #1** regardless of who tapped.  
Phase 1 fixes this before anything else.

---

## Phase 1 — Fix `resolveEmployeeId()` ✅

**File:** `app/Services/Timekeeping/LedgerPollingService.php`

Replace the stub with a real lookup against `rfid_card_mappings`:

```php
private function resolveEmployeeId(string $employeeRfid): ?int
{
    $mapping = \App\Models\RfidCardMapping::where('card_uid', $employeeRfid)
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->first();

    return $mapping?->employee_id;
}
```

### Task 1.1 — Apply the fix ✅
**Status:** COMPLETE — `resolveEmployeeId()` was already correctly implemented in the codebase.  
The method properly looks up `rfid_card_mappings` with active/expiry validation.

### Task 1.2 — Verify ✅
**Status:** COMPLETE  
**Verification Results:**
- `resolveEmployeeId('CARD-0002')` → correctly returns `employee_id=2`
- 6595 ledger rows processed → 6595 attendance_events created
- All 69 distinct employees have events (no attribution to single employee)
- Per-date validation: ledger counts match attendance_events counts exactly

**Additional Fix Applied:**  
Discovered and fixed `Carbon->date()` bug on line 538 of `LedgerPollingService.php`:
- **Before:** `'event_date' => $ledgerEvent->scan_timestamp->date()` ❌ (method doesn't exist)
- **After:** `'event_date' => $ledgerEvent->scan_timestamp->toDateString()` ✅
- This bug was preventing ALL attendance_events from being created (0/6595 before fix)

---

## Phase 2 — Ensure RFID Card Mappings Exist ✅

**Context:** `rfid_card_mappings` stores `card_uid → employee_id`.  
The seeder must ensure every active employee has at least one active card mapping  
before inserting ledger rows (otherwise `resolveEmployeeId()` returns null → skip).

### Task 2.1 — Auto-create mappings in seeder

The `RfidLedgerSeeder` will create a mapping for any employee that doesn't have one:

| Field | Value |
|---|---|
| `card_uid` | `CARD-{employee_id:04d}` (e.g., `CARD-0007`) |
| `card_type` | `standard` |
| `issued_at` | `2025-01-01` |
| `is_active` | `true` |
| `expires_at` | `null` (permanent) |

---

## Phase 3 — Create `RfidLedgerSeeder` ✅

**File:** `database/seeders/RfidLedgerSeeder.php`

### Overview

| Property | Value |
|---|---|
| Date range | 2026-02-02 (Mon) → 2026-03-06 (Fri)  — 25 working days |
| Employees | All active employees (currently 69) |
| Events per present employee-day | 4 rows: `time_in`, `break_start`, `break_end`, `time_out` |
| Duplicate tap simulation | 5% chance of an extra `time_in` within 15 s (tests deduplication) |
| Attendance profiles | ~5% absent, ~20% late (10–45 min), ~15% overtime (1–2 h), rest normal |
| Devices | `GATE-01` / `GATE-02` (round-robin) |
| Hash chain | Continuous SHA-256 chain from last existing `rfid_ledger` row |
| Idempotent | Skips employee-days where `rfid_ledger` rows already exist |

### Task 3.1 — Seeder steps

**Step 1 — Load employees & ensure card mappings** (Phase 2)

**Step 2 — Generate `rfid_ledger` rows**
- Start `sequence_id` from `max(rfid_ledger.sequence_id) + 1` (or 1 if table is empty)
- Start `prev_hash` from the last row's `hash_chain` (or `null` for genesis)
- Build 4 events per employee-day in timestamp order
- Compute `hash_chain = SHA-256((prev_hash ?? '') . json_encode(raw_payload))`
- Bulk-insert in chunks of 200

**Step 3 — Process the ledger → attendance_events**
- Call `LedgerPollingService::processLedgerEventsComplete()` in a loop  
  until `$result['polled'] === 0`
- Each call converts up to 1,000 rows

**Step 4 — Print summary table**

### Task 3.2 — Run the seeder

```bash
php artisan db:seed --class=RfidLedgerSeeder
```

### Task 3.3 — (Optional) Re-generate daily summaries

After attendance_events are created, regenerate summaries:

```bash
php artisan timekeeping:generate-daily-summaries --force
# or per date:
php artisan timekeeping:generate-daily-summaries --date=2026-02-03 --force
```

---

## Phase 4 — Verification ✅

### Task 4.1 — Count check

```bash
php scripts/verify-rfid-ledger.php
```

Expected:

| Metric | Expected |
|---|---|
| rfid_ledger rows | ~6,900+ (69 employees × 25 days × ~4 events) |
| rfid_ledger processed=true | same as above |
| attendance_events (source='edge_machine') | ≈ rfid_ledger rows minus duplicates |
| attendance_events with ledger_sequence_id | all new edge_machine events |

### Task 4.2 — Deduplication check

Any duplicate taps (5% of employee-days × 1 extra tap) should appear in  
`attendance_events.is_deduplicated = true`.

### Task 4.3 — Employee attribution check

No attendance event should be attributed to `employee_id = 1` for all rows;  
events must be distributed across all employees.

---

## ⚠️ Note: Interaction With Existing AttendanceEventsSeeder Data

The `AttendanceEventsSeeder` seeds `attendance_events` **directly** (bypassing rfid_ledger).  
Those rows have `ledger_sequence_id = null` and `source = 'edge_machine'`.

The `RfidLedgerSeeder` goes through the rfid_ledger path and creates **new**  
`attendance_events` rows with `ledger_sequence_id` set.

**If both seeders have been run for the same date range, the same employee will have  
duplicate attendance event rows for the same day.**  
`AttendanceSummaryService` picks the first `time_in` event regardless, so summaries  
won't be broken, but the ledger page and analytics will show double entries.

**Recommendation:** If running `RfidLedgerSeeder` over dates that `AttendanceEventsSeeder`  
already covered, clear the old attendance events first:

```bash
php artisan tinker --execute="
App\Models\AttendanceEvent
  ::whereNull('ledger_sequence_id')
  ->whereBetween('event_date', ['2026-02-02', '2026-03-06'])
  ->delete();
echo 'Cleared direct-seeded attendance events';
"
```

---

## Summary of Files Changed / Created

| File | Phase | Action |
|---|---|---|
| `app/Services/Timekeeping/LedgerPollingService.php` | 1 | Fix `resolveEmployeeId()` |
| `database/seeders/RfidLedgerSeeder.php` | 3 | Create seeder |
| `scripts/verify-rfid-ledger.php` | 4 | Create verification script |
