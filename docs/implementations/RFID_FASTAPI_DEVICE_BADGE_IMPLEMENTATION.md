# RFID FastAPI Server, Device Registration & Badge Management — Implementation Plan

**Scope:**
1. FastAPI server — lightweight UI + attendance logging to `rfid_ledger`
2. Device registration — wire `DeviceController` + `LedgerDeviceController` to `rfid_devices` table
3. Badge management — wire `Create.tsx` to real API (currently mock)

**Status:** In Progress  
**Priority:** High  
**Date:** 2026-03

---

## § 1 — Current State

| Component | Layer | State |
|---|---|---|
| `rfid_ledger` table | DB | ✅ Exists — append-only, hash-chained, `processed` flag |
| `rfid_devices` table | DB | ✅ Exists — `device_id`, `device_name`, `location`, `status`, `last_heartbeat`, `config` |
| `rfid_card_mappings` table | DB | ✅ Exists — fully relational, soft deletes, `is_active`, `card_type`, `expires_at` |
| `RfidLedger` model | PHP | ✅ Complete |
| `RfidDevice` model | PHP | ✅ Complete — `ledgerEntries()` relation |
| `RfidCardMapping` model | PHP | ✅ Complete — soft deletes, `LogsActivity`, scopes |
| `BadgeIssueLog` model | PHP | ✅ Complete |
| `RfidBadgeController::index()` | PHP | ✅ DB-wired, fully implemented |
| `RfidBadgeController::create()` | PHP | ✅ DB-wired — loads real employees + existing UIDs |
| `RfidBadgeController::store()` | PHP | ✅ DB-wired — creates mapping, logs audit trail |
| `DeviceController::index()` | PHP | ❌ **100% mock data** — never queries `rfid_devices` |
| `LedgerDeviceController::index()` | PHP | ❌ **100% mock data** — never queries `rfid_devices` |
| `LedgerDeviceController::show()` | PHP | ❌ **100% mock data** |
| `Badges/Create.tsx` `handleSubmit()` | React | ❌ **Mock** — uses `setTimeout` to fake API call |
| `Badges/Show.tsx` `handleLoadMore()` | React | ❌ **Mock** — `setTimeout` with no data load |
| `HR/Timekeeping/Devices.tsx` | React | ✅ Frontend ready — expects DB-wired controller |
| `BadgeManagementPermissionsSeeder` | PHP | ✅ Complete |
| FastAPI RFID server | Python | ❌ **Does not exist yet** — plan doc only |
| Device registration UI | React | ❌ Missing full CRUD (register/edit/delete device) |
| `FinalizeAttendanceForPeriodCommand` | PHP | ✅ Complete — no changes needed |
| `ProcessRfidLedgerJob` | PHP | ✅ Scheduled every 1 min |

---

## § 2 — Architecture Overview

```
[RFID Card Reader]
       │ TCP/HTTP POST  card_uid + device_id + timestamp
       ▼
[FastAPI RFID Server]  ← NEW (Python)
  ├─ POST /api/scan        — receive tap, write to rfid_ledger
  ├─ GET  /api/devices     — list registered devices (from rfid_devices)
  ├─ POST /api/device/heartbeat/{device_id}  — update last_heartbeat
  └─ GET  /ui              — small web UI for live scan feed + device status
       │
       ▼  (direct DB write to shared PostgreSQL)
[rfid_ledger]  sequence_id, employee_rfid, device_id, scan_timestamp, hash_chain, processed=false
       │
       ▼  (every 1 min via Laravel scheduler)
[ProcessRfidLedgerJob] → [LedgerPollingService]
       │  maps employee_rfid → employee via rfid_card_mappings
       ▼
[attendance_events]
       │  (daily at 23:59 via GenerateDailySummariesCommand)
       ▼
[daily_attendance_summary]
       │  (via FinalizeAttendanceForPeriodCommand)
       ▼  is_finalized = true
[PayrollCalculationService] → [employee_payroll_calculations]
```

---

## § 3 — Issues to Resolve

| # | Component | Issue | Severity |
|---|---|---|---|
| D1 | `DeviceController::index()` | Returns hardcoded mock array — never reads `rfid_devices` table | **MOCK** |
| D2 | `LedgerDeviceController::index()` | Returns hardcoded mock array | **MOCK** |
| D3 | `LedgerDeviceController::show()` | Returns hardcoded mock device | **MOCK** |
| D4 | Device UI | No route/controller for registering a new device (CREATE) | **MISSING** |
| D5 | Device UI | No route/controller for editing device details (UPDATE) | **MISSING** |
| D6 | Device UI | No route/controller for decommissioning a device (DELETE/STATUS) | **MISSING** |
| B1 | `Badges/Create.tsx` | `handleSubmit` uses `setTimeout` mock — never calls real API | **MOCK** |
| B2 | `Badges/Show.tsx` | `handleLoadMore` uses `setTimeout` — never loads more scans | **MOCK** |
| B3 | `RfidBadgeController::show()` | Method unconfirmed — need to verify it passes all `ShowBadgeProps` | **VERIFY** |
| F1 | FastAPI server | Does not exist — no Python project, no `POST /api/scan` endpoint | **MISSING** |
| F2 | FastAPI server | No heartbeat endpoint to update `rfid_devices.last_heartbeat` | **MISSING** |
| F3 | FastAPI server | No live scan feed UI | **MISSING** |

---

## § 4 — Phased Implementation

---

### Phase 1 — Wire `DeviceController` to real DB data
**File:** `app/Http/Controllers/HR/Timekeeping/DeviceController.php`  
**Status:** [ ] Not started

Replace `generateMockDevices()` with a real query:

```php
public function index(Request $request): Response
{
    $statusFilter = $request->get('status', 'all');

    $query = RfidDevice::query()
        ->withCount(['ledgerEntries as scans_today' => fn($q) =>
            $q->whereDate('scan_timestamp', today())
        ]);

    if ($statusFilter !== 'all') {
        $query->where('status', $statusFilter);
    }

    $devices = $query->orderBy('status')->get()->map(function ($device) {
        $lastScan = RfidLedger::where('device_id', $device->device_id)
            ->latest('scan_timestamp')
            ->first();

        $recentScans = RfidLedger::where('device_id', $device->device_id)
            ->with('rfidCardMapping.employee.profile:id,first_name,last_name')
            ->latest('scan_timestamp')
            ->limit(5)
            ->get()
            ->map(fn($entry) => [
                'employeeName' => $entry->rfidCardMapping?->employee?->profile?->full_name ?? $entry->employee_rfid,
                'eventType'    => $entry->event_type,
                'timestamp'    => $entry->scan_timestamp->toISOString(),
            ]);

        return [
            'id'                => $device->device_id,
            'location'          => $device->location,
            'device_name'       => $device->device_name,
            'status'            => $device->status,
            'last_heartbeat'    => $device->last_heartbeat?->toISOString(),
            'lastScanTimestamp' => $lastScan?->scan_timestamp?->toISOString(),
            'lastScanAgo'       => $lastScan?->scan_timestamp?->diffForHumans() ?? 'Never',
            'scansToday'        => $device->scans_today ?? 0,
            'recentScans'       => $recentScans,
            'config'            => $device->config,
        ];
    });

    return Inertia::render('HR/Timekeeping/Devices', [
        'devices' => $devices,
        'summary' => $this->buildSummaryStats($devices),
        'filters' => ['status' => $statusFilter],
    ]);
}

private function buildSummaryStats(\Illuminate\Support\Collection $devices): array
{
    $now = now();
    return [
        'total'    => $devices->count(),
        'online'   => $devices->where('status', 'online')->count(),
        'offline'  => $devices->where('status', 'offline')->count(),
        'idle'     => $devices->where('status', 'idle')->count(),
        'scansToday' => $devices->sum('scansToday'),
    ];
}
```

Add `use App\Models\RfidDevice; use App\Models\RfidLedger;` to the controller imports.

---

### Phase 2 — Wire `LedgerDeviceController` to real DB data (JSON API)
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerDeviceController.php`  
**Status:** [ ] Not started

Replace mock generation with real queries. Both `index()` and `show()`:

```php
public function index(Request $request): JsonResponse
{
    $statusFilter = $request->input('status', 'all');

    $query = \App\Models\RfidDevice::query();
    if ($statusFilter !== 'all') {
        $query->where('status', $statusFilter);
    }

    $devices = $query->get()->map(fn($d) => $this->formatDevice($d, false));

    return response()->json([
        'success' => true,
        'data'    => $devices,
        'summary' => [
            'total'   => $devices->count(),
            'online'  => $devices->where('status', 'online')->count(),
            'offline' => $devices->where('status', 'offline')->count(),
        ],
        'meta' => ['timestamp' => now()->toISOString()],
    ]);
}

public function show(string $deviceId): JsonResponse
{
    $device = \App\Models\RfidDevice::where('device_id', $deviceId)->firstOrFail();
    return response()->json([
        'success' => true,
        'data'    => $this->formatDevice($device, true),
    ]);
}

private function formatDevice(\App\Models\RfidDevice $device, bool $detailed): array
{
    $lastScan = \App\Models\RfidLedger::where('device_id', $device->device_id)
        ->latest('scan_timestamp')->first();

    $data = [
        'id'             => $device->device_id,
        'device_name'    => $device->device_name,
        'location'       => $device->location,
        'status'         => $device->status,
        'last_heartbeat' => $device->last_heartbeat?->toISOString(),
        'scans_today'    => \App\Models\RfidLedger::where('device_id', $device->device_id)
                                ->whereDate('scan_timestamp', today())->count(),
        'last_scan_at'   => $lastScan?->scan_timestamp?->toISOString(),
    ];

    if ($detailed) {
        $data['recent_scans'] = \App\Models\RfidLedger::where('device_id', $device->device_id)
            ->latest('scan_timestamp')->limit(20)
            ->get(['sequence_id', 'employee_rfid', 'event_type', 'scan_timestamp'])
            ->toArray();
    }

    return $data;
}
```

---

### Phase 3 — Add Device CRUD routes + controller methods
**Files:** `routes/hr.php`, `app/Http/Controllers/HR/Timekeeping/DeviceController.php`  
**Status:** [ ] Not started

#### 3a — New routes in `routes/hr.php`

In the timekeeping routes group, after the existing `GET /devices` route (line ~816):

```php
// Device CRUD
Route::get('/devices/create', [DeviceController::class, 'create'])
    ->middleware('permission:hr.timekeeping.devices.manage')
    ->name('devices.create');

Route::post('/devices', [DeviceController::class, 'store'])
    ->middleware('permission:hr.timekeeping.devices.manage')
    ->name('devices.store');

Route::get('/devices/{deviceId}', [DeviceController::class, 'show'])
    ->middleware('permission:hr.timekeeping.devices.view')
    ->name('devices.show');

Route::put('/devices/{deviceId}', [DeviceController::class, 'update'])
    ->middleware('permission:hr.timekeeping.devices.manage')
    ->name('devices.update');

Route::delete('/devices/{deviceId}', [DeviceController::class, 'destroy'])
    ->middleware('permission:hr.timekeeping.devices.manage')
    ->name('devices.destroy');

// Heartbeat endpoint (called by FastAPI)
Route::post('/api/devices/{deviceId}/heartbeat', [DeviceController::class, 'heartbeat'])
    ->name('devices.heartbeat')
    ->withoutMiddleware(['permission:hr.timekeeping.devices.manage']); // API key auth only
```

#### 3b — New controller methods

```php
// store() — Register a new RFID device
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'device_id'   => 'required|string|regex:/^[A-Z0-9\-]+$/|unique:rfid_devices,device_id',
        'device_name' => 'required|string|max:100',
        'location'    => 'required|string|max:255',
        'config'      => 'nullable|json',
    ]);

    RfidDevice::create(array_merge($validated, ['status' => 'offline']));

    activity()->causedBy(auth()->user())
        ->withProperties($validated)
        ->log('RFID device registered: ' . $validated['device_id']);

    return redirect()->route('hr.timekeeping.devices')
        ->with('success', 'Device ' . $validated['device_id'] . ' registered successfully.');
}

// update() — Edit device name, location, config
public function update(Request $request, string $deviceId): RedirectResponse
{
    $device = RfidDevice::where('device_id', $deviceId)->firstOrFail();

    $validated = $request->validate([
        'device_name' => 'required|string|max:100',
        'location'    => 'required|string|max:255',
        'status'      => 'in:online,offline,maintenance',
        'config'      => 'nullable|json',
    ]);

    $device->update($validated);

    return back()->with('success', 'Device updated.');
}

// destroy() — Decommission device (set status = offline, or hard delete if no ledger entries)
public function destroy(string $deviceId): RedirectResponse
{
    $device = RfidDevice::where('device_id', $deviceId)->firstOrFail();
    $hasLedgerEntries = RfidLedger::where('device_id', $deviceId)->exists();

    if ($hasLedgerEntries) {
        // Soft-decommission: set status to maintenance/offline
        $device->update(['status' => 'offline']);
        return back()->with('success', 'Device decommissioned (has ledger history, set to offline).');
    }

    $device->delete();
    return redirect()->route('hr.timekeeping.devices')->with('success', 'Device deleted.');
}

// heartbeat() — Called by FastAPI to signal device is alive
public function heartbeat(Request $request, string $deviceId): JsonResponse
{
    $device = RfidDevice::where('device_id', $deviceId)->first();
    if (!$device) {
        return response()->json(['error' => 'Device not registered'], 404);
    }

    $device->update([
        'last_heartbeat' => now(),
        'status'         => 'online',
    ]);

    return response()->json(['ok' => true, 'heartbeat_at' => now()->toISOString()]);
}
```

#### 3c — Add new permissions to `BadgeManagementPermissionsSeeder` (or new seeder)

```php
// Add to existing permissions or create DeviceManagementPermissionsSeeder:
'hr.timekeeping.devices.view'   => 'View RFID Devices dashboard',
'hr.timekeeping.devices.manage' => 'Register, edit, and decommission RFID devices',
```

---

### Phase 4 — Fix `Badges/Create.tsx` mock submit
**File:** `resources/js/pages/HR/Timekeeping/Badges/Create.tsx`  
**Status:** [ ] Not started

Replace the mock `setTimeout` in `handleSubmit` with a real Inertia form submit:

```ts
// Replace the entire handleSubmit function:
const handleSubmit = async (formData: BadgeFormData) => {
    setIsSubmitting(true);

    router.post(
        '/hr/timekeeping/badges',
        {
            employee_id:               formData.employee_id,
            card_uid:                  formData.card_uid,
            card_type:                 formData.card_type,
            expires_at:                formData.expires_at || null,
            notes:                     formData.notes || null,
            acknowledgement_signature: formData.acknowledgement_signature || null,
        },
        {
            onSuccess: () => {
                const selectedEmployee = employees.find((emp) => emp.id === formData.employee_id);
                setSubmitResult({
                    success: true,
                    message: `Badge successfully issued to ${selectedEmployee?.name}`,
                    badgeData: {
                        employeeName: selectedEmployee?.name || '',
                        employeeId:   selectedEmployee?.employee_id || '',
                        cardUid:      formData.card_uid,
                        cardType:     formData.card_type,
                        expiresAt:    formData.expires_at || '',
                        issuedAt:     new Date().toISOString(),
                    },
                });
                setIsModalOpen(false);
            },
            onError: (errors) => {
                setSubmitResult({
                    success: false,
                    message: Object.values(errors).join('; '),
                });
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        }
    );
};
```

Add `import { router } from '@inertiajs/react';` if not already present.

---

### Phase 5 — Build FastAPI RFID Server
**New project:** `fastapi-rfid/` (alongside Laravel project, or separate repo)  
**Status:** [ ] Not started

#### Project structure
```
fastapi-rfid/
├── main.py              — FastAPI app entry point
├── config.py            — DB DSN, device API key, scan dedup window
├── database.py          — SQLAlchemy engine + session (PostgreSQL)
├── models.py            — ORM models mirroring rfid_ledger, rfid_devices, rfid_card_mappings
├── routers/
│   ├── scan.py          — POST /api/scan
│   ├── devices.py       — GET /api/devices, POST /api/device/heartbeat/{device_id}
│   └── health.py        — GET /api/health
├── services/
│   ├── hash_chain.py    — SHA-256 chain builder
│   └── dedup.py         — 15-second dedup (Redis or in-memory TTL cache)
├── ui/
│   └── templates/
│       └── index.html   — Jinja2 live scan feed dashboard
├── requirements.txt
├── Dockerfile
└── docker-compose.yml   — FastAPI + PostgreSQL (shared with Laravel)
```

#### Core endpoint: `POST /api/scan`

```python
# routers/scan.py
from fastapi import APIRouter, Depends, HTTPException, Header
from sqlalchemy.orm import Session
from pydantic import BaseModel
from datetime import datetime
from ..database import get_db
from ..models import RfidLedger, RfidCardMapping, RfidDevice
from ..services.hash_chain import compute_hash
from ..services.dedup import is_duplicate

router = APIRouter()

class ScanRequest(BaseModel):
    card_uid: str          # hex string e.g. "A1:B2:C3:D4"
    device_id: str         # e.g. "GATE-01"
    scan_timestamp: datetime
    event_type: str        # "time_in" | "time_out" | "break_start" | "break_end"
    raw_payload: dict = {}

@router.post("/api/scan")
def receive_scan(
    body: ScanRequest,
    db: Session = Depends(get_db),
    x_api_key: str = Header(...),
):
    # 1. Authenticate device API key
    if x_api_key != settings.DEVICE_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid API key")

    # 2. Verify device is registered
    device = db.query(RfidDevice).filter_by(device_id=body.device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not registered")

    # 3. Dedup check (15-second window per card+device)
    dedup_key = f"{body.card_uid}:{body.device_id}"
    if is_duplicate(dedup_key, body.scan_timestamp, window_seconds=15):
        return {"status": "duplicate", "message": "Duplicate scan ignored"}

    # 4. Build hash chain
    last_entry = db.query(RfidLedger).order_by(RfidLedger.sequence_id.desc()).first()
    prev_hash = last_entry.hash_chain if last_entry else "GENESIS"
    next_seq  = (last_entry.sequence_id + 1) if last_entry else 1
    payload_str = f"{body.card_uid}|{body.device_id}|{body.scan_timestamp.isoformat()}|{body.event_type}"
    new_hash = compute_hash(prev_hash, payload_str)

    # 5. Write to rfid_ledger (append-only)
    entry = RfidLedger(
        sequence_id     = next_seq,
        employee_rfid   = body.card_uid.upper(),
        device_id       = body.device_id,
        scan_timestamp  = body.scan_timestamp,
        event_type      = body.event_type,
        raw_payload     = body.raw_payload,
        hash_chain      = new_hash,
        hash_previous   = prev_hash if prev_hash != "GENESIS" else None,
        processed       = False,
    )
    db.add(entry)

    # 6. Update device last_heartbeat
    device.last_heartbeat = datetime.utcnow()
    device.status = "online"

    db.commit()
    db.refresh(entry)

    return {
        "status":      "ok",
        "sequence_id": entry.sequence_id,
        "hash":        new_hash,
    }
```

#### Hash chain service

```python
# services/hash_chain.py
import hashlib

def compute_hash(prev_hash: str, payload: str) -> str:
    data = f"{prev_hash}|{payload}"
    return hashlib.sha256(data.encode()).hexdigest()
```

#### Dedup service (in-memory TTL cache)

```python
# services/dedup.py
from datetime import datetime, timedelta
from collections import OrderedDict

_cache: dict[str, datetime] = OrderedDict()

def is_duplicate(key: str, timestamp: datetime, window_seconds: int = 15) -> bool:
    _evict_expired(window_seconds)
    if key in _cache:
        return True
    _cache[key] = timestamp
    return False

def _evict_expired(window_seconds: int):
    cutoff = datetime.utcnow() - timedelta(seconds=window_seconds)
    stale = [k for k, v in _cache.items() if v < cutoff]
    for k in stale:
        del _cache[k]
```

#### Live scan feed UI

The FastAPI server serves a minimal HTML page at `GET /ui` using Jinja2 templates.
The page polls `GET /api/scan/recent` every 3 seconds and renders a live table of
recent scans. No React required — plain HTML + vanilla JS + Tailwind CDN.

```html
<!-- ui/templates/index.html (abridged) -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>RFID Scan Feed — {{ device_id }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-6">
  <h1 class="text-2xl font-bold mb-4">RFID Live Scan Feed</h1>

  <!-- Device status panel -->
  <div id="device-status" class="grid grid-cols-3 gap-4 mb-6"></div>

  <!-- Live scan table -->
  <table id="scan-table" class="w-full text-sm">
    <thead>
      <tr class="bg-gray-700">
        <th class="p-2 text-left">Time</th>
        <th class="p-2 text-left">Card UID</th>
        <th class="p-2 text-left">Employee</th>
        <th class="p-2 text-left">Device</th>
        <th class="p-2 text-left">Event</th>
        <th class="p-2 text-left">Status</th>
      </tr>
    </thead>
    <tbody id="scan-feed"></tbody>
  </table>

  <script>
    async function fetchScans() {
      const res = await fetch('/api/scan/recent?limit=50');
      const { data } = await res.json();
      const tbody = document.getElementById('scan-feed');
      tbody.innerHTML = data.map(scan => `
        <tr class="border-b border-gray-700 hover:bg-gray-800">
          <td class="p-2">${new Date(scan.scan_timestamp).toLocaleTimeString()}</td>
          <td class="p-2 font-mono text-xs">${scan.employee_rfid}</td>
          <td class="p-2">${scan.employee_name ?? '—'}</td>
          <td class="p-2">${scan.device_id}</td>
          <td class="p-2">
            <span class="px-2 py-0.5 rounded text-xs ${scan.event_type === 'time_in' ? 'bg-green-800' : 'bg-blue-800'}">
              ${scan.event_type}
            </span>
          </td>
          <td class="p-2">${scan.processed ? '✅' : '⏳'}</td>
        </tr>
      `).join('');
    }

    // Poll every 3 seconds
    fetchScans();
    setInterval(fetchScans, 3000);
  </script>
</body>
</html>
```

#### Add `GET /api/scan/recent` endpoint to FastAPI

```python
# In routers/scan.py
@router.get("/api/scan/recent")
def recent_scans(limit: int = 50, db: Session = Depends(get_db)):
    entries = (
        db.query(RfidLedger)
        .order_by(RfidLedger.sequence_id.desc())
        .limit(limit)
        .all()
    )
    # Resolve employee names via rfid_card_mappings join
    results = []
    for entry in entries:
        mapping = db.query(RfidCardMapping).filter_by(card_uid=entry.employee_rfid, is_active=True).first()
        results.append({
            "sequence_id":    entry.sequence_id,
            "employee_rfid":  entry.employee_rfid,
            "employee_name":  mapping.employee.full_name if mapping and mapping.employee else None,
            "device_id":      entry.device_id,
            "scan_timestamp": entry.scan_timestamp.isoformat(),
            "event_type":     entry.event_type,
            "processed":      entry.processed,
        })
    return {"data": results}
```

#### Heartbeat endpoint

```python
# In routers/devices.py
@router.post("/api/device/heartbeat/{device_id}")
def heartbeat(device_id: str, db: Session = Depends(get_db), x_api_key: str = Header(...)):
    if x_api_key != settings.DEVICE_API_KEY:
        raise HTTPException(401)
    device = db.query(RfidDevice).filter_by(device_id=device_id).first()
    if not device:
        raise HTTPException(404, "Device not registered")
    device.last_heartbeat = datetime.utcnow()
    device.status = "online"
    db.commit()
    return {"ok": True}
```

#### `config.py`

```python
import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    DATABASE_URL: str = os.getenv("DATABASE_URL", "postgresql://user:pass@localhost/cameco")
    DEVICE_API_KEY: str = os.getenv("DEVICE_API_KEY", "change-me-in-production")
    DEDUP_WINDOW_SECONDS: int = 15

settings = Settings()
```

#### `docker-compose.yml` (for local dev)

```yaml
version: '3.8'
services:
  fastapi-rfid:
    build: ./fastapi-rfid
    ports:
      - "8001:8001"
    environment:
      DATABASE_URL: postgresql://postgres:secret@db:5432/cameco
      DEVICE_API_KEY: dev-key-change-in-prod
    depends_on:
      - db

  db:
    image: postgres:16
    environment:
      POSTGRES_DB: cameco
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
    volumes:
      - pg_data:/var/lib/postgresql/data

volumes:
  pg_data:
```

#### `requirements.txt`

```
fastapi==0.115.0
uvicorn[standard]==0.32.0
sqlalchemy==2.0.36
psycopg2-binary==2.9.10
pydantic==2.9.2
pydantic-settings==2.6.1
jinja2==3.1.4
python-multipart==0.0.12
```

#### Start command

```bash
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
# UI accessible at: http://localhost:8001/ui
# Scan API:         http://localhost:8001/api/scan
# Health:           http://localhost:8001/api/health
```

---

### Phase 6 — Verify `RfidBadgeController::show()` passes all props
**File:** `app/Http/Controllers/HR/Timekeeping/RfidBadgeController.php`  
**Status:** [ ] Verify

`ShowBadgeProps` requires:
```ts
badge: {
    id, card_uid, employee_id, employee_name, employee_photo?,
    department, position, card_type, issued_at, issued_by,
    expires_at, is_active, last_used_at, usage_count,
    status, first_scan_at?, most_used_device?, employee_status?,
    employee?: { id, full_name, employee_number, department_name? }
    issued_by_name?, deactivated_by_name?
}
usageStats?:  { total_scans, first_scan, last_scan, days_used, devices_used }
recentScans:  [ { id, timestamp|scan_timestamp, event_type, device_id?, device_name, location?, duration_minutes? } ]
dailyScans:   [ { date, scans } ]
hourlyPeaks:  [ { hour, scans } ]
deviceUsage:  [ { device, scans } ]
```

Verify the controller queries all of this from `rfid_ledger` via `device_id` + `employee_rfid`. The `recentScans` "load more" button in `Badges/Show.tsx` calls `setTimeout` mock. Replace with a real paginated API call:

```ts
// In Show.tsx handleLoadMore:
const handleLoadMore = () => {
    setIsLoadingMore(true);
    router.reload({ only: ['recentScans'], data: { scan_page: displayedScans.length / 10 + 1 } });
    setIsLoadingMore(false);
};
```

---

## § 5 — Database Schema Reference

### `rfid_devices`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `device_id` | varchar UNIQUE | e.g. `GATE-01` |
| `device_name` | varchar | Human readable |
| `location` | varchar | Physical location |
| `status` | enum | online/offline/maintenance |
| `last_heartbeat` | timestamp NULL | Updated by FastAPI |
| `config` | json NULL | Device-specific settings |

### `rfid_ledger` (append-only)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sequence_id` | bigint UNIQUE | Ordered sequence |
| `employee_rfid` | varchar | Card UID (links to `rfid_card_mappings.card_uid`) |
| `device_id` | varchar | Links to `rfid_devices.device_id` |
| `scan_timestamp` | timestamp | Exact scan time |
| `event_type` | varchar | time_in / time_out / break_start / break_end |
| `raw_payload` | json | Full event payload |
| `hash_chain` | varchar | SHA-256 hash chain |
| `hash_previous` | varchar NULL | Previous hash |
| `device_signature` | text NULL | Ed25519 device signature |
| `processed` | boolean | False until `ProcessRfidLedgerJob` runs |
| `processed_at` | timestamp NULL | |
| `latency_ms` | integer NULL | Processing latency |
| `created_at` | timestamp | |

### `rfid_card_mappings`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `card_uid` | varchar UNIQUE | Hex card identifier |
| `employee_id` | FK employees | |
| `card_type` | enum | mifare / desfire / em4100 |
| `issued_at` | timestamp | |
| `issued_by` | FK users | |
| `expires_at` | timestamp NULL | |
| `is_active` | boolean | |
| `last_used_at` | timestamp NULL | Updated on each scan |
| `usage_count` | integer | Incremented on each scan |
| `deactivated_at` | timestamp NULL | |
| `deactivated_by` | FK users NULL | |
| `deactivation_reason` | varchar NULL | |
| `notes` | text NULL | |
| `deleted_at` | timestamp NULL | Soft delete |

---

## § 6 — Test Plan

### Phase 1–2 — Device dashboard live data

- [ ] Run `php artisan db:seed --class=DeviceSeeder` (create if doesn't exist) or manually insert a row into `rfid_devices`
- [ ] Navigate to `/hr/timekeeping/devices` — confirm device shows from DB
- [ ] Confirm `lastScanAgo` and `scansToday` are DB-sourced

### Phase 3 — Device registration

- [ ] Navigate to `/hr/timekeeping/devices` → "Register Device" button
- [ ] Fill form with `GATE-03`, location, etc. and submit
- [ ] Confirm row appears in DB and in device list
- [ ] Edit device name → confirm DB update
- [ ] Decommission device with no ledger entries → row deleted
- [ ] Decommission device with ledger entries → status set to `offline`

### Phase 4 — Badge creation (frontend mock removed)

- [ ] Navigate to `/hr/timekeeping/badges/create`
- [ ] Select employee and enter card UID, submit form
- [ ] Confirm `rfid_card_mappings` row created in DB
- [ ] Confirm `badge_issue_logs` row created
- [ ] Confirm activity log entry visible in Spatie logs
- [ ] Try to issue duplicate card UID → validation error

### Phase 5 — FastAPI server

- [ ] Start FastAPI: `uvicorn main:app --port 8001`
- [ ] `GET http://localhost:8001/api/health` → `{"status":"ok"}`
- [ ] `POST http://localhost:8001/api/scan` with valid payload → `sequence_id` returned
- [ ] Confirm row appears in `rfid_ledger` table with `processed=false`
- [ ] Wait 1 min → `ProcessRfidLedgerJob` runs → `processed=true`, `attendance_events` row created
- [ ] `GET http://localhost:8001/ui` → live scan feed updates every 3 seconds
- [ ] Send duplicate scan within 15 seconds → response `"status": "duplicate"`
- [ ] Send scan from unregistered device → 404 error

### Phase 6 — Full end-to-end

- [ ] Register device `GATE-TEST` via Laravel UI
- [ ] Issue badge to test employee via Laravel UI
- [ ] Send scan via FastAPI `POST /api/scan` with `card_uid` matching issued badge
- [ ] Confirm `rfid_ledger` row written
- [ ] Wait for `ProcessRfidLedgerJob` → `attendance_events` row created
- [ ] Run `GenerateDailySummariesCommand` → `daily_attendance_summary` row created
- [ ] Run `FinalizeAttendanceForPeriodCommand` → `is_finalized = true`
- [ ] Run payroll calculation → `employee_payroll_calculations` populated

---

## § 7 — Related Files

| File | Role |
|---|---|
| `app/Http/Controllers/HR/Timekeeping/DeviceController.php` | Devices UI — **replace mock with DB queries** |
| `app/Http/Controllers/HR/Timekeeping/LedgerDeviceController.php` | Devices JSON API — **replace mock with DB queries** |
| `app/Http/Controllers/HR/Timekeeping/RfidBadgeController.php` | Badge CRUD — `index()`, `create()`, `store()` already DB-wired |
| `app/Models/RfidDevice.php` | `rfid_devices` ORM |
| `app/Models/RfidLedger.php` | `rfid_ledger` ORM (append-only) |
| `app/Models/RfidCardMapping.php` | `rfid_card_mappings` ORM — soft deletes, scopes |
| `app/Models/BadgeIssueLog.php` | Audit log for badge actions |
| `resources/js/pages/HR/Timekeeping/Devices.tsx` | Device dashboard — expects DB-wired props |
| `resources/js/pages/HR/Timekeeping/Badges/Create.tsx` | **Replace mock handleSubmit** with real `router.post()` |
| `resources/js/pages/HR/Timekeeping/Badges/Show.tsx` | **Replace mock handleLoadMore** |
| `routes/hr.php` | Add device CRUD routes |
| `database/migrations/2026_02_04_095813_create_rfid_devices_table.php` | `rfid_devices` schema |
| `database/migrations/2026_02_03_000001_create_rfid_ledger_table.php` | `rfid_ledger` schema |
| `database/migrations/2026_02_13_100000_create_rfid_card_mappings_table.php` | `rfid_card_mappings` schema |
| `database/seeders/BadgeManagementPermissionsSeeder.php` | Badge permissions — extend for devices |
| `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php` | Consumes `rfid_ledger` every 1 min |
| `app/Console/Commands/Timekeeping/FinalizeAttendanceForPeriodCommand.php` | Locks attendance for payroll |
| `fastapi-rfid/` | **NEW** — FastAPI project (see Phase 5) |

---

## § 8 — Progress Checklist

- [ ] Phase 1: Wire `DeviceController::index()` to `rfid_devices` + `rfid_ledger`
- [ ] Phase 2: Wire `LedgerDeviceController::index()` + `show()` to real DB
- [ ] Phase 3a: Add device CRUD routes to `routes/hr.php`
- [ ] Phase 3b: Add `store()`, `update()`, `destroy()`, `heartbeat()` to `DeviceController`
- [ ] Phase 3c: Seed `hr.timekeeping.devices.*` permissions
- [ ] Phase 4: Fix `Badges/Create.tsx` — replace `setTimeout` mock with `router.post()`
- [ ] Phase 5: Build FastAPI project structure + `POST /api/scan` + `GET /ui` + heartbeat
- [ ] Phase 6: Verify `RfidBadgeController::show()` passes all `ShowBadgeProps`, fix `handleLoadMore`
- [ ] End-to-end test: RFID scan → ledger → attendance_events → daily_summary → payroll
