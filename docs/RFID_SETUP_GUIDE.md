# RFID System Setup Guide

Covers both **development** (laptop/local) and **production** (gate PC) setup for the Cameco RFID timekeeping system.

---

## Architecture Overview

```
┌──────────────────────────┐         ┌──────────────────────────┐
│   Gate / Canteen PC      │  HTTPS  │   Laravel Server         │
│                          │         │                          │
│  USB RFID Reader         │         │  POST /api/rfid/tap      │
│  (HID Keyboard mode)     │────────►│  POST /api/rfid/heartbeat│
│                          │         │                          │
│  rfid-server/            │         │  ProcessRfidLedgerJob    │
│  ├── main.py             │         │  (runs every 1 min)      │
│  ├── reader.py           │         │                          │
│  ├── sync.py             │         │  PostgreSQL              │
│  ├── display.py (UI)     │         │  rfid_ledger             │
│  └── buffer.db (SQLite)  │         │  rfid_devices            │
└──────────────────────────┘         └──────────────────────────┘
```

**How it works:**
1. Employee taps RFID card → reader outputs digits + Enter (USB HID keyboard)
2. `reader.py` captures the input, writes tap to local SQLite (`buffer.db`)
3. `sync.py` drains the queue → `POST /api/rfid/tap` with Bearer token
4. Laravel logs the tap in `rfid_ledger`, returns employee info + predicted action
5. `display.py` shows TIME IN / TIME OUT on the gate monitor
6. `heartbeat.py` pings Laravel every 30 s so the device shows "online"

---

## Part 1 — Laravel Server Setup

### 1.1 Run Migrations

Ensure the RFID-related tables exist:

```bash
php artisan migrate
```

Key tables: `rfid_devices`, `rfid_ledger`, `rfid_badges`

### 1.2 Register the Device

1. Log in as **Superadmin** or **HR Manager**
2. Go to **System → Device Management → Add Device**
3. Set:
   - **Device ID** — must match `DEVICE_ID` in `rfid-server/.env` (e.g. `GATE-01`)
   - **Location** — e.g. `Main Gate`
   - **Status** — Active
4. After saving, click **⋯ → Generate API Key**
5. Copy the API key — you will not see it again

### 1.3 Register Employee RFID Badges

1. Go to **HR → Timekeeping → RFID Badges → Add Badge**
2. Assign `card_uid` (the number printed/transmitted by the card) to each employee
3. Alternatively bulk-import via **Import CSV**

### 1.4 Enable the Scheduler (Queue Workers)

The `ProcessRfidLedgerJob` runs every minute to convert raw ledger entries into attendance records:

```bash
# Make sure the scheduler is running (cron or Windows Task Scheduler)
# See docs/SCHEDULER_SETUP_GUIDE.md for full detail

# Queue worker must also be running
php artisan queue:work --queue=default --tries=3
```

---

## Part 2 — Gate PC Setup (Development)

Use this when running on your **dev laptop** — windowed mode, no service install.

### 2.1 Prerequisites

| Requirement | Notes |
|---|---|
| Python 3.11+ | `python --version` |
| pip | bundled with Python |
| Tkinter | included in standard Python on Windows |
| USB RFID Reader | must output card UID as keystrokes + Enter (HID mode) |

### 2.2 Clone / Copy `rfid-server`

```powershell
cd C:\path\to\cameco\rfid-server
```

### 2.3 Create Virtual Environment

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

`requirements.txt` installs: `pynput`, `python-dotenv`, `requests`, `Pillow`

### 2.4 Configure `.env`

```powershell
Copy-Item .env.example .env
notepad .env
```

Fill in:

```env
# URL of your LOCAL Laravel dev server
API_URL=http://localhost:8000/api

# Paste the API key you generated in step 1.2
API_KEY=your-secret-api-key-here

# Must match the Device ID you registered in step 1.2
DEVICE_ID=GATE-01

TIMEZONE=Asia/Manila

# 0 = windowed (development), 1 = fullscreen (production)
DISPLAY_FULLSCREEN=0

# Seconds to show tap result before returning to idle
DISPLAY_CLEAR_AFTER=4

SYNC_INTERVAL=2
LOCAL_DB_PATH=buffer.db
```

### 2.5 Run

```powershell
# Activate venv first if not already active
.venv\Scripts\Activate.ps1

python main.py
```

You should see:
```
[STARTUP] RFID Reader starting — Device: GATE-01
[LOCAL DB] Buffer ready at ...\buffer.db
[HEARTBEAT] Heartbeat thread started
[READER] Listening for RFID input (USB HID keyboard mode)...
```

A windowed display (800×480) will appear showing **"PLEASE TAP YOUR CARD"**.

Press **Escape** to close.

---

## Part 3 — Gate PC Setup (Production)

Use this for the **actual gate monitor PC** running 24/7.

### 3.1 Prerequisites

| Requirement | Notes |
|---|---|
| Windows 10/11 | Gate PC OS |
| Python 3.11+ | Install from python.org — check "Add to PATH" |
| NSSM | Download from https://nssm.cc — place `nssm.exe` in `C:\tools\nssm\` |
| USB RFID Reader | Plugged into the gate PC |
| Monitor | Connected and set as primary display |

> **No internet on the gate PC?** That's fine — taps buffer in SQLite and sync when connectivity resumes.

### 3.2 Copy Files to Gate PC

Copy the entire `rfid-server/` folder to the gate PC, e.g.:

```
C:\apps\rfid-server\
```

### 3.3 Create Virtual Environment on Gate PC

```powershell
cd C:\apps\rfid-server
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

### 3.4 Configure `.env`

```powershell
Copy-Item .env.example .env
notepad .env
```

```env
# Production Laravel URL (must be HTTPS)
API_URL=https://cameco.yourdomain.com/api

# API key from Device Management
API_KEY=your-secret-api-key-here

# Match the registered Device ID exactly
DEVICE_ID=GATE-01

TIMEZONE=Asia/Manila

# Fullscreen on production gate monitor
DISPLAY_FULLSCREEN=1

DISPLAY_CLEAR_AFTER=4
SYNC_INTERVAL=2
LOCAL_DB_PATH=buffer.db
```

### 3.5 Install as Windows Service (NSSM)

Open **PowerShell as Administrator**:

```powershell
cd C:\apps\rfid-server

# Basic install (runs as LOCAL SYSTEM — pynput keyboard capture may not work)
.\install-service.ps1

# Recommended: run as the gate PC's actual logged-in user account
.\install-service.ps1 -ServiceUser ".\GateUser" -ServicePass "UserPassword123"
```

> **Why use a real user account?** `pynput` requires access to the interactive keyboard session. `LOCAL SYSTEM` does not have this by default, so card scans won't be detected.

The installer will:
- Locate `nssm.exe` automatically
- Register `CamecoRfidServer` as an auto-start service
- Rotate logs at 5 MB → `C:\apps\rfid-server\logs\service.log`
- Auto-restart on crash with a 5-second delay
- Start the service immediately

### 3.6 Manage the Service

```powershell
nssm start   CamecoRfidServer
nssm stop    CamecoRfidServer      # sends SIGTERM → sets device offline cleanly
nssm restart CamecoRfidServer
nssm status  CamecoRfidServer
nssm remove  CamecoRfidServer confirm
```

### 3.7 Set Display to Auto-Login + Launch

For a kiosk-style setup, configure Windows to auto-login as `GateUser` on boot:

1. Press `Win + R` → `netplwiz`
2. Uncheck "Users must enter a username and password"
3. Enter the gate user credentials

The NSSM service starts automatically — no manual action needed after a power cycle.

---

## Part 4 — Verification Checklist

### Laravel Side
- [ ] `rfid_devices` table has a row with `device_id = GATE-01` and `status = active`
- [ ] Device has a valid API key
- [ ] At least one `rfid_badges` row is assigned to an employee
- [ ] Queue worker is running (`php artisan queue:work`)
- [ ] Scheduler is running (cron / Task Scheduler — see `docs/SCHEDULER_SETUP_GUIDE.md`)
- [ ] `POST /api/rfid/heartbeat` returns 200 (test with curl):
  ```bash
  curl -X POST https://your-app.com/api/rfid/heartbeat \
    -H "Authorization: Bearer YOUR_API_KEY" \
    -H "Content-Type: application/json" \
    -d '{"device_id":"GATE-01","status":"online"}'
  ```

### Gate PC Side
- [ ] `python main.py` starts without errors
- [ ] Display window appears with "PLEASE TAP YOUR CARD"
- [ ] Clock is ticking in the top-right corner
- [ ] Device label shows `Device: GATE-01` in the top-left
- [ ] Tapping a registered card shows green **TIME IN** or blue **TIME OUT**
- [ ] Tapping an unregistered card shows red **UNKNOWN CARD**
- [ ] `buffer.db` is created in `rfid-server/`
- [ ] `logs/service.log` is updating (production only)
- [ ] Laravel's Device Management page shows the device as **Online**

---

## Part 5 — Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| Display is blank blue, no text | Photo label overlapping text widgets | Update to latest `display.py` |
| Card tap does nothing | pynput not capturing — service running as LOCAL SYSTEM | Re-install service with `-ServiceUser` |
| `[HEARTBEAT] Warning: ...` in logs | Laravel unreachable | Check `API_URL`, firewall, SSL cert |
| `HTTP 401` in sync logs | Wrong or expired API key | Regenerate API key in Device Management |
| `HTTP 404` in sync logs | Wrong `API_URL` (missing `/api`) | Check `API_URL` in `.env` |
| `status: unknown` for all cards | Cards not registered in `rfid_badges` | Register badges in HR → Timekeeping → RFID Badges |
| Photo never loads | `photo_url` missing or server unreachable | Placeholder shown — not a functional issue |
| Taps recorded twice | `ProcessRfidLedgerJob` not deduplicating | Check job logs; ensure scheduler is running only once |
| `buffer.db` grows indefinitely | Sync thread can't reach server | Resolve connectivity; rows will drain automatically once online |

---

## Part 6 — Environment Variables Reference

| Variable | Default | Description |
|---|---|---|
| `API_URL` | *(required)* | Base URL of Laravel app, e.g. `https://app.com/api` |
| `API_KEY` | *(required)* | Bearer token from Device Management |
| `DEVICE_ID` | `GATE-01` | Must match `rfid_devices.device_id` |
| `TIMEZONE` | `Asia/Manila` | PHP/Python timezone string |
| `DISPLAY_FULLSCREEN` | `1` | `1` = fullscreen, `0` = 800×480 window |
| `DISPLAY_CLEAR_AFTER` | `4` | Seconds before display returns to idle |
| `SYNC_INTERVAL` | `2` | Seconds between sync drain attempts |
| `LOCAL_DB_PATH` | `buffer.db` | Path to SQLite offline buffer (relative to `rfid-server/`) |

---

## Part 7 — Adding a Second Gate / Canteen Reader

1. In Laravel: **System → Device Management → Add Device** with `DEVICE_ID = CAN-01`
2. Generate a separate API key for it
3. Copy `rfid-server/` to the second PC
4. Set `.env` with `DEVICE_ID=CAN-01` and its own API key
5. Install service same as Part 3

Each device has its own `buffer.db` and service instance. The Laravel API distinguishes taps by `device_id`.
