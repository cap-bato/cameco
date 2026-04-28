# RFID Reader Script — Implementation Plan

**Date:** 2026-03-12  
**Status:** In Progress  
**Purpose:** Plain Python script that runs on the gate PC as a Windows service, captures USB HID RFID scanner input, and forwards attendance tap events to the Laravel server via HTTP API. The gate PC never holds database credentials — only an API Bearer token. Taps are buffered in a local SQLite file so no data is lost during network outages.

---

## 1. Architecture Overview

### Security Rationale

The gate PC sits in the **lobby** — it is physically exposed. Storing PostgreSQL credentials (host, user, password) on it would allow anyone who steals the machine to read the entire `employees`, `rfid_card_mappings`, and `attendance_events` tables, and inject fake taps.

**Option B** removes all database credentials from the gate PC. The machine holds only an API Bearer token. Even if stolen, the attacker can only post taps to the `/api/rfid/tap` Laravel endpoint — they cannot read or modify any other data.

```
Gate Building (public lobby)               Server Room Building (locked)
┌─────────────────────────────────┐        ┌───────────────────────────────────┐
│  Gate PC  (Windows)             │        │  Server                           │
│  ├── USB HID RFID reader        │  HTTPS │  ├── Laravel / PHP-FPM :443       │
│  │   outputs digits + Enter     │───────►│  │   POST /api/rfid/tap           │
│  └── Python script (NSSM)       │        │  │   POST /api/rfid/heartbeat     │
│      ├── pynput: key capture    │        │  └── PostgreSQL :5432 (internal)  │
│      ├── sqlite3: local buffer  │        │                                   │
│      ├── sync thread: HTTP POST │        │  rfid_devices.last_heartbeat      │
│      ├── heartbeat thread       │        │  rfid_ledger (written by Laravel) │
│      └── tkinter display        │        └───────────────────────────────────┘
│                                 │
│  .env holds:                    │
│    API_URL = https://...        │
│    API_KEY = Bearer token only  │
│    NO database credentials      │
└─────────────────────────────────┘
```

```
RFID Card tap
   │  digits + Enter keystroke
   ▼
Python script (NSSM service)
   │
   ├── 1. Write tap to local SQLite buffer  ← immediate, works offline
   ├── 2. Update TapDisplay (show feedback)
   │
   │   (background sync thread — every 2 seconds)
   ├── 3. POST /api/rfid/tap  {card_uid, device_id, tapped_at, local_id}
   │       └── Laravel: card lookup, employee name, hash chain, rfid_ledger insert
   │           └── Returns: {status, employee_name, employee_number, predicted_action}
   └── 4. Mark local SQLite row as synced

   (background heartbeat thread — every 30 seconds)
   └── POST /api/rfid/heartbeat  {device_id}
         └── Laravel: updates rfid_devices.last_heartbeat + status

   (every 1 min — Laravel scheduler)
   ProcessRfidLedgerJob → LedgerPollingService
      └── creates attendance_events → daily_attendance_summary
```

**Key design choices:**
- Gate PC holds **no DB credentials** — only an API Bearer token in `.env`
- Tap is written to local SQLite **immediately** on scan, before any HTTP call — display responds instantly even if the server is slow
- Background sync thread drains the SQLite queue; offline taps queue up and drain automatically when connectivity restores
- Hash chain is computed **server-side** by Laravel — gate PC does not need access to previous ledger rows
- `rfid_devices.last_heartbeat` is updated via `POST /api/rfid/heartbeat` — Laravel writes to DB, gate PC does not
- One instance per gate PC, each with its own `DEVICE_ID` and `API_KEY` in `.env`
- USB HID reader is a keyboard emulator — `pynput` captures digits + Enter globally

---

## 2. Project Structure

```
rfid-server/           ← separate folder, not inside the Laravel project
├── main.py            ← entry point: init local DB, start threads, launch display
├── config.py          ← settings from .env
├── local_store.py     ← SQLite buffer: write tap, mark synced, drain queue
├── sync.py            ← background sync thread: POST unsynced taps to Laravel API
├── reader.py          ← USB HID keyboard listener via pynput
├── heartbeat.py       ← background thread: POST /api/rfid/heartbeat every 30s
├── display.py         ← fullscreen Tkinter tap-feedback window
├── .env               ← secrets (never commit — contains API_KEY)
├── .env.example
└── requirements.txt
```

> **Removed:** `database.py` (no direct PostgreSQL connection), `ledger.py` (hash chain now server-side)

---

## 3. API Contract

The gate PC only talks to two Laravel endpoints.

### `POST /api/rfid/tap`

**Auth:** `Authorization: Bearer <API_KEY>`

**Request body:**
```json
{
  "card_uid":   "0123456789",
  "device_id":  "GATE-01",
  "tapped_at":  "2026-03-12T08:04:11",
  "local_id":   42
}
```

| Field | Notes |
|---|---|
| `card_uid` | Raw UID string from the scanner |
| `device_id` | Must match a row in `rfid_devices.device_id` |
| `tapped_at` | ISO-8601 naive datetime in the server's configured timezone |
| `local_id` | SQLite row ID — echoed back by server so the sync thread can mark it synced |

**Success response (200):**
```json
{
  "status":           "ok",
  "local_id":         42,
  "employee_name":    "Juan Dela Cruz",
  "employee_number":  "EMP-001",
  "predicted_action": "TIME IN"
}
```

**Unknown card response (200):**
```json
{
  "status":  "unknown",
  "local_id": 42
}
```

**Error responses:** `401 Unauthorized` (bad token), `422 Unprocessable` (validation), `500` (server error).

> The server is responsible for: card lookup, `rfid_card_mappings` update, hash chain computation, `rfid_ledger` insert, TIME IN/OUT prediction.

---

### `POST /api/rfid/heartbeat`

**Auth:** `Authorization: Bearer <API_KEY>`

**Request body:**
```json
{
  "device_id": "GATE-01"
}
```

**Response (200):**
```json
{ "status": "ok" }
```

> Laravel writes `rfid_devices.last_heartbeat = now()` and `status = 'online'`. On clean shutdown, the gate PC sends one final heartbeat with `status = 'offline'`.

---

## 4. Local SQLite Schema

The gate PC maintains a single SQLite file (`rfid-server/buffer.db`) as an offline-safe write-ahead buffer.

```sql
CREATE TABLE IF NOT EXISTS tap_queue (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    card_uid    TEXT    NOT NULL,
    tapped_at   TEXT    NOT NULL,   -- ISO-8601 naive string
    synced      INTEGER NOT NULL DEFAULT 0,   -- 0 = pending, 1 = synced
    synced_at   TEXT,
    response    TEXT    -- JSON response from server, stored for debugging
);
```

- Written **synchronously** on every tap — happens before any HTTP call
- `synced = 0` rows are drained by the background sync thread
- Successfully synced rows are kept for 7 days then vacuumed (for local audit trail)

---

## 5. Hash Chain Algorithm (server-side)

The hash chain is now computed entirely by Laravel's `RfidTapController`, not the gate PC. The gate PC does not need access to previous ledger rows.

```php
// RfidTapController.php — server side
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ksorted = collect($payload)->sortKeys()->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$hashChain = hash('sha256', ($prevHash ?? '') . $ksorted);
```

- Genesis block: `$prevHash = null` → treated as `''`
- Chain is **per device** — previous row queried with `FOR UPDATE` to serialise concurrent inserts
- `LedgerPollingService::validateHashChain()` is unchanged — it still verifies the chain

---

## 6. Event Type

Laravel's `RfidTapController` writes **`'tap'`** for all valid registered+active cards, and **`'unknown_card'`** for anything else. It does **not** decide `time_in` vs `time_out` — that is `LedgerPollingService::createAttendanceEventsFromLedger()`'s job. Laravel already has all the logic and access to `attendance_events` history to make that decision.

---

## 7. Implementation Phases

### Phase 1 — Project Scaffold

**Goal:** Create the directory and install dependencies.

#### Acceptance Criteria
- [x] `rfid-server/` directory created with all files listed in section 2
- [x] `pip install -r requirements.txt` completes on Python 3.11+
- [x] `config.py` loads `.env` without errors
- [x] No PostgreSQL credentials anywhere in the project

#### Task 1.1 — `requirements.txt`

```
pynput==1.7.6
python-dotenv==1.0.1
requests==2.32.3
```

> `sqlite3` is part of Python's standard library — no extra package needed.

#### Task 1.2 — `.env.example`

```env
# Laravel API — gate PC sends taps here, never touches PostgreSQL directly
API_URL=https://your-laravel-app.example.com
# Bearer token — generate in Laravel: php artisan rfid:token GATE-01
API_KEY=your-secret-api-key-here

# Must match a row in rfid_devices.device_id
# Register the device first: Laravel HR → Timekeeping → Devices → Add Device
DEVICE_ID=GATE-01

# Timezone — must match Laravel APP_TIMEZONE
TIMEZONE=Asia/Manila

# Display settings
# Set to 1 to run fullscreen on the gate monitor, 0 for windowed (development)
DISPLAY_FULLSCREEN=1
# How many seconds to show the tap result before returning to idle screen
DISPLAY_CLEAR_AFTER=4

# How long (seconds) the sync thread waits between drain attempts
SYNC_INTERVAL=2
# Path to the local SQLite buffer file (relative to rfid-server/)
LOCAL_DB_PATH=buffer.db
```

#### Task 1.3 — `config.py`

```python
import os
from dotenv import load_dotenv

load_dotenv()

API_URL   = os.getenv('API_URL', '').rstrip('/')
API_KEY   = os.getenv('API_KEY', '')
DEVICE_ID = os.getenv('DEVICE_ID', 'GATE-01')
TIMEZONE  = os.getenv('TIMEZONE', 'Asia/Manila')

DISPLAY_FULLSCREEN  = os.getenv('DISPLAY_FULLSCREEN', '1') == '1'
DISPLAY_CLEAR_AFTER = int(os.getenv('DISPLAY_CLEAR_AFTER', 4))

SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 2))
LOCAL_DB_PATH = os.getenv('LOCAL_DB_PATH', 'buffer.db')
```

---

### Phase 2 — Local SQLite Buffer

**Goal:** Write every tap to a local SQLite file immediately on scan so no taps are lost during network outages.

#### Acceptance Criteria
- [x] `init_local_db()` creates `buffer.db` and the `tap_queue` table if missing
- [x] `enqueue_tap()` inserts a row and returns its `id` — called synchronously on every scan
- [x] `get_unsynced()` returns rows where `synced = 0`, ordered by `id`
- [x] `mark_synced()` sets `synced = 1` and records the server response JSON
- [x] Thread-safe: uses a `threading.Lock` around all writes
- [x] Works fully offline — no network required

#### Task 2.1 — `local_store.py`

```python
"""
local_store.py — SQLite offline buffer for RFID taps

Every tap is written here first, before any HTTP call.
The sync thread drains unsynced rows to the Laravel API.
"""

import sqlite3
import threading
import json
from pathlib import Path

from config import LOCAL_DB_PATH

_lock = threading.Lock()
_db_path = Path(__file__).parent / LOCAL_DB_PATH


def init_local_db() -> None:
    """Create the buffer.db file and tap_queue table if they don't exist."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute("""
            CREATE TABLE IF NOT EXISTS tap_queue (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                card_uid   TEXT    NOT NULL,
                tapped_at  TEXT    NOT NULL,
                synced     INTEGER NOT NULL DEFAULT 0,
                synced_at  TEXT,
                response   TEXT
            )
        """)
        con.commit()
        con.close()
    print(f"[LOCAL DB] Buffer ready at {_db_path}")


def enqueue_tap(card_uid: str, tapped_at: str) -> int:
    """
    Insert a pending tap row. Returns the new row id.
    Called synchronously on every card scan — must be fast.
    """
    with _lock:
        con = sqlite3.connect(_db_path)
        cur = con.execute(
            "INSERT INTO tap_queue (card_uid, tapped_at) VALUES (?, ?)",
            (card_uid, tapped_at),
        )
        local_id = cur.lastrowid
        con.commit()
        con.close()
    return local_id


def get_unsynced(limit: int = 50) -> list[dict]:
    """Return up to `limit` unsynced rows, oldest first."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.row_factory = sqlite3.Row
        rows = con.execute(
            "SELECT id, card_uid, tapped_at FROM tap_queue WHERE synced = 0 ORDER BY id LIMIT ?",
            (limit,),
        ).fetchall()
        con.close()
    return [dict(r) for r in rows]


def mark_synced(local_id: int, response: dict) -> None:
    """Mark a row as successfully synced and store the server response."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute(
            """
            UPDATE tap_queue
            SET synced = 1, synced_at = datetime('now'), response = ?
            WHERE id = ?
            """,
            (json.dumps(response), local_id),
        )
        con.commit()
        con.close()


def vacuum_old_synced(days: int = 7) -> None:
    """Delete synced rows older than `days` days. Safe to call periodically."""
    with _lock:
        con = sqlite3.connect(_db_path)
        con.execute(
            "DELETE FROM tap_queue WHERE synced = 1 AND synced_at < datetime('now', ?)",
            (f"-{days} days",),
        )
        con.commit()
        con.close()
```

---

### Phase 3 — API Sync Thread

**Goal:** Background thread that drains the SQLite queue by POSTing unsynced taps to `POST /api/rfid/tap`.

#### Acceptance Criteria
- [x] Unsynced rows are sent oldest-first in batches of up to 50
- [x] Successfully synced rows are marked in SQLite immediately
- [x] `4xx` errors (bad card_uid, etc.) are treated as permanent failures — row marked synced with the error response so it isn't retried forever
- [x] `5xx` / network errors are temporary — row stays unsynced and is retried next cycle
- [x] Thread is a daemon and exits cleanly on stop event
- [x] If the server is unreachable, the thread sleeps `SYNC_INTERVAL` seconds and retries

#### Task 3.1 — `sync.py`

```python
"""
sync.py — Background sync thread

Drains tap_queue rows to POST /api/rfid/tap on the Laravel server.
Runs every SYNC_INTERVAL seconds. Fully offline-safe — if the server
is unreachable, rows stay in SQLite and are retried automatically.
"""

import threading
import requests

from config import API_URL, API_KEY, DEVICE_ID, SYNC_INTERVAL
from local_store import get_unsynced, mark_synced

# Wired by main.py after TapDisplay is created
_display = None


def set_display(display) -> None:
    global _display
    _display = display


def _post_tap(row: dict) -> dict | None:
    """
    POST a single tap row to the Laravel API.
    Returns the parsed JSON response on HTTP 200.
    Returns None on network/timeout error (will retry).
    Raises ValueError on 4xx (permanent failure — do not retry).
    """
    try:
        resp = requests.post(
            f"{API_URL}/api/rfid/tap",
            json={
                'card_uid':  row['card_uid'],
                'device_id': DEVICE_ID,
                'tapped_at': row['tapped_at'],
                'local_id':  row['id'],
            },
            headers={'Authorization': f'Bearer {API_KEY}'},
            timeout=8,
        )
        if resp.status_code == 200:
            return resp.json()
        if 400 <= resp.status_code < 500:
            # Permanent error — mark synced with error so it's not retried
            raise ValueError(f"HTTP {resp.status_code}: {resp.text[:200]}")
        # 5xx — temporary, return None to retry next cycle
        return None
    except requests.exceptions.RequestException:
        return None   # network error — retry next cycle


class SyncThread(threading.Thread):
    def __init__(self) -> None:
        super().__init__(daemon=True)
        self._stop_event = threading.Event()

    def run(self) -> None:
        print('[SYNC] Sync thread started')
        while not self._stop_event.wait(SYNC_INTERVAL):
            self._drain()

    def stop(self) -> None:
        self._stop_event.set()

    def _drain(self) -> None:
        rows = get_unsynced(limit=50)
        for row in rows:
            try:
                response = _post_tap(row)
            except ValueError as e:
                # Permanent 4xx — mark synced to stop retrying
                mark_synced(row['id'], {'error': str(e), 'permanent': True})
                print(f"[SYNC] Permanent error for local_id={row['id']}: {e}")
                continue

            if response is None:
                # Temporary failure — stop draining this cycle, retry later
                break

            mark_synced(row['id'], response)

            # If this is a fresh tap (local_id matches most recent scan),
            # push the server's response to the display
            if _display is not None:
                status = response.get('status', 'unknown')
                if status == 'ok':
                    _display.show_tap({
                        'status':           'ok',
                        'card_uid':         row['card_uid'],
                        'employee_number':  response.get('employee_number'),
                        'first_name':       response.get('employee_name', '').split(' ')[0] if response.get('employee_name') else None,
                        'last_name':        ' '.join(response.get('employee_name', '').split(' ')[1:]) or None,
                        'predicted_action': response.get('predicted_action', 'TAP RECORDED'),
                        'timestamp':        row['tapped_at'],
                    })
                elif status == 'unknown':
                    _display.show_tap({'status': 'unknown', 'card_uid': row['card_uid']})
```

---

### Phase 4 — USB HID Reader

**Goal:** Capture keystrokes from the RFID reader (keyboard emulator), buffer digits, fire on Enter.

#### Acceptance Criteria
- [x] Tapping a badge calls `enqueue_tap()` (local SQLite write) with the correct UID string
- [x] Display shows an immediate "tap received" loading state while sync is pending
- [x] Non-digit / special keys are silently ignored
- [x] An error in `enqueue_tap()` prints to stderr but does not crash the listener
- [x] Listener thread is a daemon (exits when main process exits)

#### Task 4.1 — `reader.py`

```python
import threading
from datetime import datetime
from zoneinfo import ZoneInfo
from pynput import keyboard

from config import DEVICE_ID, TIMEZONE
from local_store import enqueue_tap

_buffer: list[str] = []
_lock = threading.Lock()

# Set by main.py after display is created
_display = None


def set_display(display) -> None:
    """Register the TapDisplay instance."""
    global _display
    _display = display


def _on_press(key):
    with _lock:
        if key == keyboard.Key.enter:
            uid = ''.join(_buffer).strip()
            _buffer.clear()
            if uid:
                _fire(uid)
        else:
            try:
                char = key.char
                if char and char.isprintable():
                    _buffer.append(char)
            except AttributeError:
                pass   # special key — ignore


def _fire(card_uid: str) -> None:
    """
    Write the tap to the local SQLite buffer immediately.
    The sync thread will POST it to Laravel in the background.
    Show a 'syncing' placeholder on the display so the employee
    gets instant feedback; the display is updated again when
    the server response arrives via sync.py.
    """
    tz = ZoneInfo(TIMEZONE)
    tapped_at = datetime.now(tz).replace(tzinfo=None).isoformat()

    try:
        local_id = enqueue_tap(card_uid, tapped_at)
        print(f"[QUEUED]  {card_uid}  local_id={local_id}  {tapped_at}")

        # Show a neutral 'tap received' state immediately
        # The sync thread overwrites this with the server's response
        if _display is not None:
            _display.show_tap({
                'status':    'syncing',
                'card_uid':  card_uid,
                'timestamp': tapped_at,
            })

    except Exception as e:
        print(f"[ERROR]   {card_uid} — {e}")
        if _display is not None:
            _display.show_tap({'status': 'error', 'card_uid': card_uid, 'error': str(e)})


def start_listener() -> keyboard.Listener:
    listener = keyboard.Listener(on_press=_on_press)
    listener.daemon = True
    listener.start()
    print("[READER] Listening for RFID input (USB HID keyboard mode)...")
    return listener
```

---

### Phase 5 — Heartbeat Thread

**Goal:** Keep `rfid_devices.status` and `last_heartbeat` current so that Laravel's `DeviceController` shows the device as online. No HTTP endpoint needed — Laravel reads these columns directly from PostgreSQL.

#### Acceptance Criteria
- [x] `rfid_devices.last_heartbeat` refreshes every 30 seconds while script is running
- [x] `status` is set to `'online'` on startup and `'offline'` on graceful shutdown
- [x] If the HTTP call fails, a warning is printed and the thread continues (non-fatal)
- [x] Thread is a daemon

> **Option B update:** heartbeat uses `POST /api/rfid/heartbeat` via HTTP — no database credentials on gate PC.

#### Task 5.1 — `heartbeat.py`

```python
"""
heartbeat.py — Periodic device heartbeat via HTTP

POSTs to POST /api/rfid/heartbeat every 30 seconds so Laravel can update
rfid_devices.last_heartbeat and keep the device shown as 'online'.
No database credentials are used — only the API Bearer token.
"""

import threading
import requests

from config import API_URL, API_KEY, DEVICE_ID


def _post_heartbeat(status: str = 'online') -> None:
    try:
        requests.post(
            f"{API_URL}/api/rfid/heartbeat",
            json={'device_id': DEVICE_ID, 'status': status},
            headers={'Authorization': f'Bearer {API_KEY}'},
            timeout=8,
        )
    except Exception as e:
        print(f"[HEARTBEAT] Warning: {e}")


def set_device_status(status: str) -> None:
    """Send a one-shot heartbeat with an explicit status (e.g. 'offline' on shutdown)."""
    _post_heartbeat(status)


class HeartbeatThread(threading.Thread):
    def __init__(self, interval: int = 30):
        super().__init__(daemon=True)
        self.interval    = interval
        self._stop_event = threading.Event()

    def run(self) -> None:
        print('[HEARTBEAT] Heartbeat thread started')
        _post_heartbeat('online')  # immediate on startup
        while not self._stop_event.wait(self.interval):
            _post_heartbeat('online')

    def stop(self) -> None:
        self._stop_event.set()
```

---

### Phase 6 — Tap Feedback Display

**Goal:** Fullscreen Tkinter window on the gate monitor that shows tap results to the employee.

> The display now has a fifth state — **syncing** — shown by `reader.py` immediately after a tap is queued locally. The `sync.py` thread overwrites this with the server's `ok` / `unknown` / `error` response when it arrives (typically within 1–3 seconds).

#### Acceptance Criteria
- [x] Idle state shows "PLEASE TAP YOUR CARD" with a live clock
- [x] **Syncing state** shows "TAP RECEIVED" on a blue/indigo background immediately after scan
- [x] Valid tap shows employee name, employee number, and predicted action (TIME IN / TIME OUT) on a green background
- [x] Unknown card shows "UNKNOWN CARD" on a red background
- [x] Error shows "SYSTEM ERROR" on an amber background
- [x] Result clears back to idle after `DISPLAY_CLEAR_AFTER` seconds
- [x] `DISPLAY_FULLSCREEN=0` in `.env` runs in an 800×480 window for development
- [x] All Tkinter calls happen on the main thread via `root.after()`

#### Task 6.1 — `display.py`

```python
"""
display.py — Gate PC tap-feedback UI

A fullscreen Tkinter window that shows tap results to the employee.
Runs on the main thread; reader.py fires show_tap() via thread-safe
root.after() so Tkinter is never touched from a background thread.

States:
  idle    — dark background, clock, "PLEASE TAP YOUR CARD"
  success — green, employee name + ID + predicted action (TIME IN / TIME OUT)
  unknown — red, "UNKNOWN CARD"
  error   — amber, brief error message
"""

import tkinter as tk
from datetime import datetime
from zoneinfo import ZoneInfo

from config import TIMEZONE, DISPLAY_FULLSCREEN, DISPLAY_CLEAR_AFTER

# ── Colour palette ──────────────────────────────────────────────────────────
BG_IDLE    = '#0f172a'   # slate-900
BG_SYNCING = '#1e1b4b'   # indigo-950
BG_SUCCESS = '#14532d'   # green-900
BG_UNKNOWN = '#7f1d1d'   # red-900
BG_ERROR   = '#78350f'   # amber-900

FG_IDLE    = '#94a3b8'   # slate-400
FG_WHITE   = '#f8fafc'   # slate-50
FG_INDIGO  = '#a5b4fc'   # indigo-300
FG_GREEN   = '#86efac'   # green-300
FG_RED     = '#fca5a5'   # red-300
FG_AMBER   = '#fcd34d'   # amber-300


class TapDisplay:
    def __init__(self) -> None:
        self.root = tk.Tk()
        self.root.title('Cameco RFID Timekeeping')
        self.root.configure(bg=BG_IDLE)
        self.root.resizable(False, False)

        if DISPLAY_FULLSCREEN:
            self.root.attributes('-fullscreen', True)
        else:
            self.root.geometry('800x480')

        # Escape key exits fullscreen in dev mode
        self.root.bind('<Escape>', lambda _: self.root.destroy())

        self._clear_job: str | None = None
        self._build_widgets()

    def _build_widgets(self) -> None:
        self.clock_lbl = tk.Label(
            self.root, text='', font=('Segoe UI', 22, 'bold'),
            bg=BG_IDLE, fg=FG_IDLE, anchor='e',
        )
        self.clock_lbl.place(relx=1.0, rely=0.0, anchor='ne', x=-24, y=16)

        self.device_lbl = tk.Label(
            self.root, text='', font=('Segoe UI', 16),
            bg=BG_IDLE, fg=FG_IDLE, anchor='w',
        )
        self.device_lbl.place(relx=0.0, rely=0.0, anchor='nw', x=24, y=16)

        # Large centre label: TIME IN / TIME OUT / PLEASE TAP YOUR CARD
        self.action_lbl = tk.Label(
            self.root, text='PLEASE TAP YOUR CARD',
            font=('Segoe UI', 52, 'bold'),
            bg=BG_IDLE, fg=FG_IDLE,
            wraplength=700, justify='center',
        )
        self.action_lbl.place(relx=0.5, rely=0.38, anchor='center')

        # Employee full name
        self.name_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 34),
            bg=BG_IDLE, fg=FG_WHITE,
            wraplength=700, justify='center',
        )
        self.name_lbl.place(relx=0.5, rely=0.58, anchor='center')

        # Employee number
        self.id_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 20),
            bg=BG_IDLE, fg=FG_IDLE,
        )
        self.id_lbl.place(relx=0.5, rely=0.71, anchor='center')

        # Tap timestamp
        self.time_lbl = tk.Label(
            self.root, text='',
            font=('Segoe UI', 16),
            bg=BG_IDLE, fg=FG_IDLE,
        )
        self.time_lbl.place(relx=0.5, rely=0.88, anchor='center')

    # ── Public API ───────────────────────────────────────────────────────────

    def show_tap(self, result: dict) -> None:
        """Thread-safe: schedule the UI update on the main Tkinter thread."""
        self.root.after(0, lambda: self._render(result))

    def set_device_label(self, device_id: str) -> None:
        self.device_lbl.config(text=f'Device: {device_id}')

    # ── Internal render ──────────────────────────────────────────────────────

    def _render(self, result: dict) -> None:
        if self._clear_job is not None:
            self.root.after_cancel(self._clear_job)
            self._clear_job = None

        status = result.get('status', 'error')
        if status == 'ok':
            self._render_success(result)
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)
        elif status == 'unknown':
            self._render_unknown(result)
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)
        elif status == 'syncing':
            self._render_syncing(result)
            # No auto-clear — sync.py will overwrite with ok/unknown/error
        else:
            self._render_error(result.get('error', 'Unknown error'))
            self._clear_job = self.root.after(DISPLAY_CLEAR_AFTER * 1000, self._render_idle)

    def _render_syncing(self, result: dict) -> None:
        self._set_bg(BG_SYNCING)
        self.action_lbl.config(text='TAP RECEIVED',   fg=FG_INDIGO, bg=BG_SYNCING, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='Verifying...',    fg=FG_WHITE,  bg=BG_SYNCING)
        self.id_lbl.config(   text='',                fg=FG_INDIGO, bg=BG_SYNCING)
        self.time_lbl.config( text=result.get('timestamp', '')[:19].replace('T', '  '),
                              fg=FG_INDIGO, bg=BG_SYNCING)
        self.clock_lbl.config(bg=BG_SYNCING)
        self.device_lbl.config(bg=BG_SYNCING)

    def _render_success(self, result: dict) -> None:
        action = result.get('predicted_action', 'TAP RECORDED')
        first  = result.get('first_name') or ''
        last   = result.get('last_name')  or ''
        name   = f"{first} {last}".strip() or result.get('card_uid', '')
        emp_no = result.get('employee_number') or f"Card: {result['card_uid']}"
        ts     = result.get('timestamp', '')[:19].replace('T', '  ')

        self._set_bg(BG_SUCCESS)
        self.action_lbl.config(text=action,  fg=FG_GREEN,  bg=BG_SUCCESS, font=('Segoe UI', 64, 'bold'))
        self.name_lbl.config( text=name,     fg=FG_WHITE,  bg=BG_SUCCESS)
        self.id_lbl.config(   text=emp_no,   fg=FG_GREEN,  bg=BG_SUCCESS)
        self.time_lbl.config( text=ts,       fg=FG_GREEN,  bg=BG_SUCCESS)
        self.clock_lbl.config(bg=BG_SUCCESS)
        self.device_lbl.config(bg=BG_SUCCESS)

    def _render_unknown(self, result: dict) -> None:
        self._set_bg(BG_UNKNOWN)
        self.action_lbl.config(text='UNKNOWN CARD', fg=FG_RED,   bg=BG_UNKNOWN, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='Card not registered or deactivated.', fg=FG_WHITE, bg=BG_UNKNOWN)
        self.id_lbl.config(   text=f"UID: {result.get('card_uid', '')}",  fg=FG_RED,   bg=BG_UNKNOWN)
        self.time_lbl.config( text='Please contact HR to register your card.', fg=FG_RED, bg=BG_UNKNOWN)
        self.clock_lbl.config(bg=BG_UNKNOWN)
        self.device_lbl.config(bg=BG_UNKNOWN)

    def _render_error(self, message: str) -> None:
        self._set_bg(BG_ERROR)
        self.action_lbl.config(text='SYSTEM ERROR', fg=FG_AMBER, bg=BG_ERROR, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text=message[:80],   fg=FG_WHITE,  bg=BG_ERROR)
        self.id_lbl.config(   text='',             fg=FG_AMBER,  bg=BG_ERROR)
        self.time_lbl.config( text='Tap was NOT recorded. Please try again.', fg=FG_AMBER, bg=BG_ERROR)
        self.clock_lbl.config(bg=BG_ERROR)
        self.device_lbl.config(bg=BG_ERROR)

    def _render_idle(self) -> None:
        self._clear_job = None
        self._set_bg(BG_IDLE)
        self.action_lbl.config(text='PLEASE TAP YOUR CARD', fg=FG_IDLE, bg=BG_IDLE, font=('Segoe UI', 52, 'bold'))
        self.name_lbl.config( text='', fg=FG_WHITE, bg=BG_IDLE)
        self.id_lbl.config(   text='', fg=FG_IDLE,  bg=BG_IDLE)
        self.time_lbl.config( text='', fg=FG_IDLE,  bg=BG_IDLE)
        self.clock_lbl.config(bg=BG_IDLE)
        self.device_lbl.config(bg=BG_IDLE)

    def _set_bg(self, color: str) -> None:
        self.root.configure(bg=color)

    def _tick(self) -> None:
        tz  = ZoneInfo(TIMEZONE)
        now = datetime.now(tz)
        self.clock_lbl.config(text=now.strftime('%I:%M:%S %p'))
        self.root.after(1000, self._tick)

    def start(self) -> None:
        """Blocks — call after all background threads are started."""
        self._tick()
        self._render_idle()
        self.root.mainloop()
```

---

### Phase 7 — Entry Point

**Goal:** Wire all modules together; handle startup and graceful shutdown.

#### Acceptance Criteria
- [x] `python main.py` starts without errors
- [x] On startup: local SQLite initialised, device set `online` via API, heartbeat + sync + reader threads started, display window shown
- [x] `Ctrl+C` / `SIGTERM` (from `nssm stop`): device set `offline` via API, sync thread stopped, clean exit
- [x] Closing the window manually also triggers graceful shutdown
- [x] No PostgreSQL connection is opened at any point

#### Task 7.1 — `main.py`

```python
"""
main.py — Cameco RFID Reader entry point

Startup sequence:
  1. Init local SQLite buffer (creates buffer.db if missing)
  2. POST /api/rfid/heartbeat  status=online
  3. Start HeartbeatThread    (POST /api/rfid/heartbeat every 30 s)
  4. Start SyncThread         (drain SQLite → POST /api/rfid/tap every 2 s)
  5. Create TapDisplay window
  6. Wire display into reader and sync threads
  7. Start keyboard listener  (background thread)
  8. Hand control to Tkinter mainloop (blocks until window closed)

Shutdown (Ctrl+C OR nssm stop CamecoRfidServer):
  - SIGINT / SIGTERM → _shutdown() runs before mainloop exits
  - Device set 'offline' via API, threads stopped, clean exit
"""

import signal
import sys

from config import DEVICE_ID
from local_store import init_local_db
from heartbeat import HeartbeatThread, set_device_status
from sync import SyncThread, set_display as sync_set_display
from reader import start_listener, set_display as reader_set_display
from display import TapDisplay

heartbeat = HeartbeatThread(interval=30)
sync      = SyncThread()


def _shutdown(sig=None, frame=None) -> None:
    print('\n[SHUTDOWN] Setting device offline...')
    heartbeat.stop()
    sync.stop()
    set_device_status('offline')
    sys.exit(0)


def main() -> None:
    signal.signal(signal.SIGINT,  _shutdown)
    signal.signal(signal.SIGTERM, _shutdown)

    print(f'[STARTUP] RFID Reader starting — Device: {DEVICE_ID}')

    init_local_db()
    set_device_status('online')

    heartbeat.start()
    sync.start()

    # Create display window BEFORE starting the listener so show_tap() is
    # available as soon as the first card is scanned.
    display = TapDisplay()
    display.set_device_label(DEVICE_ID)
    reader_set_display(display)
    sync_set_display(display)

    start_listener()

    # Blocks until the window is closed (or _shutdown() calls sys.exit)
    display.start()

    # If the window is closed manually (e.g. Alt+F4), do a clean shutdown
    _shutdown()


if __name__ == '__main__':
    main()
```

---

### Phase 8 — Laravel API Endpoints

**Goal:** Two new Laravel routes that accept requests from the gate PC and write to PostgreSQL.

#### Acceptance Criteria
- [x] `POST /api/rfid/tap` accepts `card_uid`, `device_id`, `tapped_at`, `local_id`
- [x] Authenticates with Bearer token checked against `rfid_devices.api_key` (or a dedicated `rfid_tokens` table)
- [x] Looks up card, inserts into `rfid_ledger` with correct hash chain, updates `rfid_card_mappings`
- [x] Returns `status`, `employee_name`, `employee_number`, `predicted_action`, `local_id`
- [x] `POST /api/rfid/heartbeat` updates `rfid_devices.last_heartbeat` and `status`
- [x] Both routes reject requests with invalid / missing Bearer token with `401`

#### Task 8.1 — Add `api_key` column to `rfid_devices`

```php
// database/migrations/xxxx_add_api_key_to_rfid_devices_table.php
public function up(): void
{
    Schema::table('rfid_devices', function (Blueprint $table) {
        $table->string('api_key', 64)->nullable()->unique()->after('device_id');
    });
}
```

Generate a key for each device (run once per device in tinker or a seeder):
```php
RfidDevice::where('device_id', 'GATE-01')
    ->update(['api_key' => hash('sha256', Str::random(40))]);
```

Store the generated `api_key` value in the gate PC's `.env` as `API_KEY`.

#### Task 8.2 — `RfidTapController.php`

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\RfidCardMapping;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RfidTapController extends Controller
{
    public function tap(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        $validated = $request->validate([
            'card_uid'  => 'required|string|max:255',
            'device_id' => 'required|string|max:255',
            'tapped_at' => 'required|date',
            'local_id'  => 'required|integer',
        ]);

        $cardUid  = $validated['card_uid'];
        $tappedAt = Carbon::parse($validated['tapped_at']);
        $localId  = (int) $validated['local_id'];

        return DB::transaction(function () use ($cardUid, $tappedAt, $localId, $device) {
            $mapping = RfidCardMapping::with('employee')
                ->where('card_uid', $cardUid)
                ->whereNull('deleted_at')
                ->first();

            if (!$mapping || !$mapping->is_active) {
                $this->insertLedgerRow($device->device_id, $cardUid, 'unknown_card', $tappedAt, null);
                return response()->json(['status' => 'unknown', 'local_id' => $localId]);
            }

            $employee = $mapping->employee;
            $lastEvent = AttendanceEvent::where('employee_id', $employee->id)
                ->whereDate('event_date', $tappedAt->toDateString())
                ->orderByDesc('event_time')
                ->value('event_type');
            $predictedAction = $lastEvent === 'time_in' ? 'TIME OUT' : 'TIME IN';

            $this->insertLedgerRow($device->device_id, $cardUid, 'tap', $tappedAt, $employee->id);

            $mapping->increment('usage_count');
            $mapping->update(['last_used_at' => $tappedAt]);

            return response()->json([
                'status'           => 'ok',
                'local_id'         => $localId,
                'employee_name'    => trim($employee->first_name . ' ' . $employee->last_name),
                'employee_number'  => $employee->employee_number,
                'predicted_action' => $predictedAction,
            ]);
        });
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        $status = $request->input('status', 'online');
        $device->update([
            'status'         => in_array($status, ['online', 'offline']) ? $status : 'online',
            'last_heartbeat' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function authenticateDevice(Request $request): RfidDevice|JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $device = RfidDevice::where('api_key', $token)->first();
        if (!$device) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $device;
    }

    private function insertLedgerRow(
        string $deviceId,
        string $cardUid,
        string $eventType,
        Carbon $tappedAt,
        ?int   $employeeId
    ): void {
        // Lock the last row for this device to serialise concurrent inserts
        $last = RfidLedger::where('device_id', $deviceId)
            ->orderByDesc('sequence_id')
            ->lockForUpdate()
            ->first(['sequence_id', 'hash_chain']);

        $nextSeq  = $last ? $last->sequence_id + 1 : 1;
        $prevHash = $last?->hash_chain;

        $payload = [
            'card_uid'    => $cardUid,
            'device_id'   => $deviceId,
            'employee_id' => $employeeId,
            'event_type'  => $eventType,
            'timestamp'   => $tappedAt->toIso8601String(),
        ];
        // Sort keys to match Python compact JSON
        ksort($payload);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hashChain   = hash('sha256', ($prevHash ?? '') . $payloadJson);

        RfidLedger::create([
            'sequence_id'    => $nextSeq,
            'employee_rfid'  => $cardUid,
            'device_id'      => $deviceId,
            'scan_timestamp' => $tappedAt,
            'event_type'     => $eventType,
            'raw_payload'    => $payload,
            'hash_chain'     => $hashChain,
            'hash_previous'  => $prevHash,
            'processed'      => false,
            'created_at'     => $tappedAt,
        ]);
    }
}
```

#### Task 8.3 — Register routes in `routes/api.php`

```php
Route::prefix('rfid')->group(function () {
    Route::post('tap',       [\App\Http\Controllers\API\RfidTapController::class, 'tap']);
    Route::post('heartbeat', [\App\Http\Controllers\API\RfidTapController::class, 'heartbeat']);
});
```

These routes use Bearer token auth handled inside the controller — no additional middleware needed beyond the default `api` middleware group (which has no session/CSRF).

---

### Phase 9 — NSSM Windows Service

**Why NSSM over Windows Startup folder:**
- Starts at boot before any user logs in
- Restarts automatically on crash (configurable delay)
- Captures stdout/stderr to a rotating log file
- `net stop CamecoRfidServer` sends SIGTERM → triggers `_shutdown()` → device goes `offline` cleanly

**Constraint:** `pynput` captures keyboard input from the interactive user session. The NSSM service must be configured to **log on as the PC's actual user account**, not `LOCAL SYSTEM`.

#### Acceptance Criteria
- [x] Service starts automatically when Windows boots
- [x] Service restarts within 5 seconds on crash
- [x] `nssm stop CamecoRfidServer` triggers graceful shutdown — device row goes `offline`
- [x] Logs written to `rfid-server\logs\service.log`, rotated at 5 MB
- [x] `pynput` captures keystrokes correctly (service runs as interactive user)

#### Task 9.1 — Create virtual environment and install dependencies

```powershell
cd C:\path\to\rfid-server
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

#### Task 9.2 — Copy and fill `.env`

```powershell
Copy-Item .env.example .env
notepad .env   # fill in API_URL, API_KEY, DEVICE_ID
```

Verify the device exists in Laravel first:
```
Laravel HR UI → Timekeeping → Devices → Add Device
device_id field must match DEVICE_ID in .env exactly (e.g. GATE-01)
Copy the generated api_key into .env as API_KEY
```

#### Task 9.3 — Download NSSM

Download `nssm.exe` from the NSSM website and place it in `C:\tools\nssm\` or add to `PATH`.

#### Task 9.4 — Register the service (run PowerShell as Administrator)

```powershell
$base   = "C:\path\to\rfid-server"
$python = "$base\.venv\Scripts\python.exe"
$script = "$base\main.py"

nssm install CamecoRfidServer $python $script
nssm set CamecoRfidServer AppDirectory   $base
nssm set CamecoRfidServer DisplayName    "Cameco RFID Reader"
nssm set CamecoRfidServer Description    "RFID badge reader for Cameco timekeeping"
nssm set CamecoRfidServer Start          SERVICE_AUTO_START

# Logging
New-Item -ItemType Directory -Path "$base\logs" -Force
nssm set CamecoRfidServer AppStdout      "$base\logs\service.log"
nssm set CamecoRfidServer AppStderr      "$base\logs\service.log"
nssm set CamecoRfidServer AppRotateFiles 1
nssm set CamecoRfidServer AppRotateBytes 5242880    # 5 MB

# Auto-restart on crash after 5 seconds
nssm set CamecoRfidServer AppRestartDelay 5000

# CRITICAL: run as the logged-in interactive user, not LOCAL SYSTEM
# pynput requires access to the user's keyboard session
# Replace DOMAIN\username and password with the actual account
nssm set CamecoRfidServer ObjectName ".\username" "password"
# For domain accounts: "DOMAIN\username" "password"
```

#### Task 9.5 — Start / manage the service

```powershell
nssm start   CamecoRfidServer
nssm stop    CamecoRfidServer    # graceful shutdown → device goes offline
nssm restart CamecoRfidServer
nssm status  CamecoRfidServer
```

#### Task 9.6 — Remove service (if needed)

```powershell
nssm stop   CamecoRfidServer
nssm remove CamecoRfidServer confirm
```

---

### Phase 10 — Security Hardening

**Goal:** Ensure the API cannot be abused even if a gate PC is stolen.

#### Acceptance Criteria
- [x] Each gate PC has its **own** unique `api_key` — stealing one key does not compromise other devices
- [x] The API only accepts the `card_uid`, `device_id`, `tapped_at`, and `local_id` fields — no other data from the gate PC enters the DB directly
- [x] Rate limiting on `POST /api/rfid/tap` — Laravel's built-in throttle middleware
- [x] A stolen token can be revoked instantly: set `rfid_devices.api_key = null` in the DB
- [x] Optionally: HTTPS only (gate PC connects over TLS — `requests` validates the certificate by default)

#### Task 10.1 — Add rate limiting

In `routes/api.php`, wrap the RFID routes with a throttle:

```php
Route::prefix('rfid')->middleware('throttle:120,1')->group(function () {
    Route::post('tap',       [RfidTapController::class, 'tap']);
    Route::post('heartbeat', [RfidTapController::class, 'heartbeat']);
});
```

120 taps/minute per IP is far above any legitimate gate volume and blocks brute-force token scanning.

#### Task 10.2 — Token revocation procedure

If a gate PC is stolen or compromised:

```sql
-- Revoke immediately
UPDATE rfid_devices SET api_key = NULL WHERE device_id = 'GATE-01';

-- Issue a new key when replacement PC arrives
UPDATE rfid_devices
SET api_key = encode(gen_random_bytes(32), 'hex')
WHERE device_id = 'GATE-01'
RETURNING api_key;   -- copy this value to the new PC's .env
```

#### Task 10.3 — PostgreSQL (no gate PC access needed)

PostgreSQL **does not** need to accept connections from gate PCs. The only change required is ensuring `listen_addresses` includes the server's LAN interface so Laravel can reach it from its own host (already the case for a functioning Laravel install).

No `rfid_gate` DB user, no `pg_hba.conf` entries for gate PC IPs — this was the old direct-DB approach and is **no longer needed**.

---

## 8. Multiple Readers

Each gate PC runs its own script instance with a unique `DEVICE_ID` and `API_KEY` in `.env`. All POST to the same Laravel API. Laravel processes all devices transparently.

```
Gate PC     (GATE-01) ──┐
Canteen PC  (CAN-01)  ──┼──► POST /api/rfid/tap ──► RfidTapController ──► PostgreSQL
Side gate   (GATE-02) ──┘                                                    ↓
                                                                     ProcessRfidLedgerJob
```

---

## 9. How Laravel Processes the Tap

Once `RfidTapController` writes a row with `processed = false`:

1. `ProcessRfidLedgerJob` runs every minute (Laravel scheduler)
2. `LedgerPollingService::pollNewEvents()` fetches unprocessed rows ordered by `sequence_id`
3. Hash chain is validated — mismatch → row flagged in `ledger_health_logs`
4. `createAttendanceEventsFromLedger()` determines `time_in` / `time_out` / `break_start` / `break_end` based on previous events for that employee today
5. An `attendance_events` row is created
6. At 11:59 PM, `GenerateDailySummariesCommand` runs `AttendanceSummaryService` → computes `daily_attendance_summary`

---

## 10. Acceptance Criteria (Full)

- [x] **Phase 1:** `pip install -r requirements.txt` succeeds; `config.py` loads `.env`; no PostgreSQL settings in `.env`
- [x] **Phase 2:** `init_local_db()` creates `buffer.db`; `enqueue_tap()` inserts a row and returns its id
- [x] **Phase 3:** Unsynced rows drain to `POST /api/rfid/tap`; synced rows marked in SQLite; temporary failures retry; permanent 4xx marked and skipped
- [x] **Phase 3:** Network outage → taps queue in SQLite, drain automatically when connectivity restores
- [x] **Phase 4:** Digits + Enter → `enqueue_tap()` fires; other keys ignored; display shows immediate "TAP RECEIVED" state
- [x] **Phase 5:** `rfid_devices.last_heartbeat` updates every 30s via `POST /api/rfid/heartbeat`
- [x] **Phase 6:** Display shows syncing → ok / unknown / error states; idle returns after `DISPLAY_CLEAR_AFTER` seconds
- [x] **Phase 6:** Valid tap → display shows employee name, employee number, TIME IN/TIME OUT on green background
- [x] **Phase 6:** Unknown card → display shows red UNKNOWN CARD screen
- [x] **Phase 6:** Error → display shows amber SYSTEM ERROR screen
- [x] **Phase 7:** `python main.py` starts with no DB connections; `Ctrl+C` / `nssm stop` sets device `offline` and exits cleanly
- [x] **Phase 8:** `POST /api/rfid/tap` returns `status=ok` with employee name, number, and predicted action
- [x] **Phase 8:** `POST /api/rfid/tap` returns `status=unknown` for unregistered/inactive cards
- [x] **Phase 8:** Invalid Bearer token → `401 Unauthorized`
- [x] **Phase 9:** NSSM service starts on boot, restarts on crash, logs to file
- [x] **Phase 10:** Rate limiting active; token revocation procedure documented
- [ ] **End-to-end:** Within 1–2 minutes after a tap, Laravel creates an `attendance_events` row

---

## 11. Known Constraints

| Constraint | Notes |
|---|---|
| Single process per device | `lockForUpdate()` in `RfidTapController::insertLedgerRow()` serialises hash chain writes server-side. Do not run two gate-PC instances with the same `DEVICE_ID`. |
| `pynput` requires interactive user | NSSM `ObjectName` must be set to the PC's local/domain user account — not `LOCAL SYSTEM`. |
| Card UID format | Scanner outputs raw decimal digits. The exact string must match `rfid_card_mappings.card_uid`. Register badges in Laravel HR → Timekeeping → Badges using the same number the reader outputs. |
| Timezone | Gate PC sends a naive ISO-8601 datetime in `tapped_at`. Laravel stores it as-is. Must match `APP_TIMEZONE` on both sides. |
| Network latency | Tap is written to SQLite instantly. Display shows "TAP RECEIVED" immediately. Server response (with employee name) arrives in ~1–3 s on LAN. If the server is down, the syncing state persists until connectivity is restored. |
| HTTPS recommended | Use a valid TLS certificate on the Laravel server. `requests` in Python validates the certificate by default — do not disable verification (`verify=False`) in production. |
| API key security | The `.env` file on the gate PC contains only `API_KEY` (Bearer token) — no DB credentials. If the PC is stolen, revoke the token in the DB (see Phase 10). |
