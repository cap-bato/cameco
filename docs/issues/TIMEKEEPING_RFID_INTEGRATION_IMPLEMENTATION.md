# Timekeeping Module - RFID Event-Driven Integration Implementation

**Issue Type:** Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 4-5 weeks  
**Target Users:** HR Staff, HR Manager, Employees (via RFID scan)  
**Dependencies:** FastAPI RFID Server, PostgreSQL Ledger, Event Bus, Employee Module  
**Related Modules:** Payroll, Performance Appraisal, Workforce Management, Notifications

---

## 📋 Executive Summary

Implement an event-driven Timekeeping system that pulls time logs from an append-only PostgreSQL ledger populated by a FastAPI RFID server. This system replaces manual time entry with automated RFID scanning and provides tamper-resistant, replayable event logs for payroll, performance appraisal, and compliance auditing.

**Core Objectives:**
1. Pull time logs from append-only PostgreSQL ledger (populated by FastAPI RFID server)
2. Display real-time attendance events on existing Timekeeping pages
3. Implement event-driven architecture for downstream modules (Payroll, Appraisal, Notifications)
4. Ensure data integrity with hash-chained, cryptographically verifiable events
5. Support ledger replay for reconciliation and audit purposes
6. Handle offline device synchronization and duplicate event detection
7. Provide workforce coverage analytics and attendance monitoring
8. Generate attendance summaries for payroll processing

**Applied Implementation Decisions:**

**RFID Event Flow:**
- Employee scans RFID card at gate → FastAPI server receives scan → Saves to PostgreSQL ledger
- Laravel Timekeeping module polls/listens to ledger → Pulls new events → Processes into attendance records
- Event-driven dispatch to Payroll, Appraisal, and Notification modules
- Append-only ledger ensures tamper-resistance and audit trail

**Data Architecture:**
- **PostgreSQL Ledger Table**: `rfid_ledger` (append-only, hash-chained)
- **Attendance Events Table**: `attendance_events` (pulled from ledger, deduplicated)
- **Daily Summary Table**: `daily_attendance_summary` (aggregated for payroll)
- Sequence IDs ensure ordering; hash chains prevent tampering
- Replay engine for reconciliation and integrity verification

**Access Control:**
- **HR Staff**: View all attendance, manual corrections, import management
- **HR Manager**: View all attendance, approve corrections, analytics, export reports
- **Employees**: No direct access (scan RFID only; view own records via future portal)
- **System**: Automated ledger polling, event processing, and workflow gating

**Event-Driven Integration:**
- **Payroll Module**: Receives `AttendanceProcessed` events for salary calculations
- **Appraisal Module**: Receives `AttendanceViolation` events for performance scoring
- **Notification Module**: Sends alerts for late arrivals, absences, and violations
- **Workforce Module**: Coverage analytics based on real-time attendance data

**Compliance & Security:**
- DOLE labor law compliance (accurate time records for 5 years)
- Cryptographic hash chains for tamper-evidence
- Audit logging for manual corrections and ledger replay
- Automated snapshots to WORM storage for legal defensibility

---

## ✅ Implementation Decisions Applied

**FastAPI → PostgreSQL → Laravel Flow:**
1. RFID scanner captures employee card tap
2. FastAPI server receives scan, validates employee, writes to `rfid_ledger`
3. Laravel scheduled job (every 1 minute) polls `rfid_ledger` for new events
4. New events processed into `attendance_events` with deduplication
5. Daily summaries computed and stored in `daily_attendance_summary`
6. Events dispatched to Payroll, Appraisal, and Notification modules

**Ledger Schema (PostgreSQL):**
```sql
CREATE TABLE rfid_ledger (
    id BIGSERIAL PRIMARY KEY,
    sequence_id BIGINT NOT NULL UNIQUE,
    employee_rfid VARCHAR(255) NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    scan_timestamp TIMESTAMP NOT NULL,
    event_type VARCHAR(50) NOT NULL, -- 'time_in', 'time_out', 'break_start', 'break_end'
    raw_payload JSONB NOT NULL,
    hash_chain VARCHAR(255) NOT NULL, -- SHA-256 hash of (prev_hash || payload)
    device_signature TEXT, -- Optional Ed25519 signature
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_rfid_ledger_sequence ON rfid_ledger(sequence_id);
CREATE INDEX idx_rfid_ledger_processed ON rfid_ledger(processed);
CREATE INDEX idx_rfid_ledger_employee ON rfid_ledger(employee_rfid);
```

**Event-Driven Architecture:**
- `AttendanceEventProcessed` → Triggers daily summary recomputation
- `AttendanceSummaryUpdated` → Notifies Payroll module
- `AttendanceViolation` → Alerts HR and updates Appraisal score
- `DeviceOfflineDetected` → Triggers admin notification
- `LedgerIntegrityFailed` → Blocks payroll processing, triggers audit

**Deduplication & Replay Logic:**
- 15-second window for duplicate tap detection (same employee, same device, same event type)
- Sequence gaps trigger automated replay jobs
- Hash chain validation on every ledger read
- Manual corrections logged with user ID and reason (never modify ledger, only override computed summaries)

**Workflow Gating:**
- Payroll approval blocked if ledger health check fails (gaps, hash mismatches, processing delays)
- Performance appraisal imports attendance data only from verified ledger sequences
- Manual corrections require HR Manager approval before affecting payroll

---

## 🗄️ Database Schema Updates

### New Table: `rfid_ledger` (PostgreSQL)
Append-only ledger populated by FastAPI server. Never modified by Laravel.

```sql
CREATE TABLE rfid_ledger (
    id BIGSERIAL PRIMARY KEY,
    sequence_id BIGINT NOT NULL UNIQUE,
    employee_rfid VARCHAR(255) NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    scan_timestamp TIMESTAMP NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    raw_payload JSONB NOT NULL,
    hash_chain VARCHAR(255) NOT NULL,
    device_signature TEXT,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Updated Table: `attendance_events`
Existing table modified to reference ledger and track processing status.

```sql
ALTER TABLE attendance_events ADD COLUMN ledger_sequence_id BIGINT REFERENCES rfid_ledger(sequence_id);
ALTER TABLE attendance_events ADD COLUMN is_deduplicated BOOLEAN DEFAULT FALSE;
ALTER TABLE attendance_events ADD COLUMN duplicate_of_event_id BIGINT REFERENCES attendance_events(id);
ALTER TABLE attendance_events ADD COLUMN ledger_hash_verified BOOLEAN DEFAULT TRUE;
```

### Updated Table: `daily_attendance_summary`
Add ledger integrity tracking.

```sql
ALTER TABLE daily_attendance_summary ADD COLUMN ledger_sequence_start BIGINT;
ALTER TABLE daily_attendance_summary ADD COLUMN ledger_sequence_end BIGINT;
ALTER TABLE daily_attendance_summary ADD COLUMN ledger_verified BOOLEAN DEFAULT TRUE;
```

### New Table: `ledger_health_logs`
Track integrity checks and replay operations.

```sql
CREATE TABLE ledger_health_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_timestamp TIMESTAMP NOT NULL,
    last_sequence_id BIGINT NOT NULL,
    gaps_detected BOOLEAN DEFAULT FALSE,
    gap_details JSON,
    hash_failures BOOLEAN DEFAULT FALSE,
    hash_failure_details JSON,
    replay_triggered BOOLEAN DEFAULT FALSE,
    status ENUM('healthy', 'warning', 'critical') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 📦 Implementation Phases

### **Phase 1: Frontend Mockup - Replayable Time Logs UI (Week 1)**

**Objective:** Create visual mockup of replayable time logs interface with mock data to demonstrate the look and feel to HR Staff before backend implementation. This allows stakeholders to provide feedback on UI/UX before investing in backend work.

#### **Task 1.1: Create Time Logs Stream Component (Mock Data)**
**File:** `resources/js/components/timekeeping/time-logs-stream.tsx` (NEW)

**Subtasks:**
- [x] **1.1.1** Create `TimeLogsStream` component showing chronological list of RFID tap events
- [x] **1.1.2** Display each log entry with:
  - Employee photo/avatar
  - Employee name and ID
  - Event type badge (🟢 Time In, 🔴 Time Out, ☕ Break Start, ▶️ Break End)
  - Timestamp (e.g., "8:05 AM")
  - Device location (e.g., "Gate 1 - Main Entrance")
  - Sequence ID (e.g., "#12345")
  - Verification status icon (🔒 Verified / ⚠️ Pending)
- [x] **1.1.3** Use mock data array with 50+ sample events (various employees, times, events)
- [x] **1.1.4** Add auto-scroll animation (new events appear at top with slide-in effect)
- [x] **1.1.5** Add hover effect showing full event details tooltip
- [x] **1.1.6** Style with color coding: green (time in), red (time out), amber (breaks)
- [x] **1.1.7** Add "Live" indicator dot (pulsing green) in header

**Mock Data Structure:**
```typescript
const mockTimeLogs = [
  {
    id: 1,
    sequenceId: 12345,
    employeeId: "EMP-2024-001",
    employeeName: "Juan Dela Cruz",
    employeePhoto: "/avatars/juan.jpg",
    rfidCard: "****-1234",
    eventType: "time_in",
    timestamp: "2026-01-29T08:05:23",
    deviceId: "GATE-01",
    deviceLocation: "Gate 1 - Main Entrance",
    verified: true,
    hashChain: "a3f2b9c...",
    latencyMs: 125
  },
  // ... more mock entries
];
```

**Acceptance Criteria:**
- Component renders 50+ mock time log entries
- Visual hierarchy clear (employee → event → time → location)
- Color coding distinguishes event types at a glance
- Smooth animations for new entries
- Responsive design (works on tablet/desktop)

---

#### **Task 1.2: Create Ledger Health Dashboard Widget (Mock)**
**File:** `resources/js/components/timekeeping/ledger-health-widget.tsx` (NEW)

**Subtasks:**
- [x] **1.2.1** Create dashboard widget showing:
  - **Status Badge**: 🟢 HEALTHY / 🟡 WARNING / 🔴 CRITICAL
  - **Last Processed**: "Sequence #12,450 - 2 seconds ago"
  - **Processing Speed**: "425 events/min"
  - **Integrity Status**: "✅ All chains verified"
  - **Device Status**: "3 online, 0 offline"
  - **Backlog**: "0 pending events"
- [x] **1.2.2** Use color-coded card backgrounds (green/yellow/red)
- [x] **1.2.3** Add mini-chart showing processing rate over last hour (line chart)
- [x] **1.2.4** Add "View Details" button (opens modal with full metrics)
- [x] **1.2.5** Mock different states (healthy, warning with lag, critical with hash failure)
- [x] **1.2.6** Add tooltip explaining each metric

**Mock States:**
```typescript
const mockHealthStates = {
  healthy: {
    status: "healthy",
    lastSequence: 12450,
    lastProcessedAgo: "2 seconds ago",
    processingRate: 425,
    integrityStatus: "verified",
    devicesOnline: 3,
    devicesOffline: 0,
    backlog: 0
  },
  warning: {
    status: "warning",
    lastSequence: 12420,
    lastProcessedAgo: "8 minutes ago",
    processingRate: 180,
    integrityStatus: "verified",
    devicesOnline: 2,
    devicesOffline: 1,
    backlog: 245
  },
  critical: {
    status: "critical",
    lastSequence: 12380,
    lastProcessedAgo: "45 minutes ago",
    processingRate: 0,
    integrityStatus: "hash_mismatch_detected",
    devicesOnline: 1,
    devicesOffline: 2,
    backlog: 1250
  }
};
```

**Acceptance Criteria:**
- Widget displays all key metrics clearly
- Visual status (color) immediately communicates health
- Mock states demonstrate different scenarios
- Mini-chart shows trend visualization
- Responsive and fits in dashboard grid

---

#### **Task 1.3: Update Attendance Overview Page with Mock Logs**
**File:** `resources/js/pages/HR/Timekeeping/Overview.tsx`

**Subtasks:**
- [x] **1.3.1** Add `<LedgerHealthWidget />` to top of page
- [x] **1.3.2** Add `<TimeLogsStream />` in main content area (right sidebar or full width)
- [x] **1.3.3** Add "Live Event Stream" section header with toggle (Show/Hide)
- [x] **1.3.4** Add date/time range filter (Today, Yesterday, Last 7 days, Custom)
- [x] **1.3.5** Add event type filter checkboxes (Time In, Time Out, Breaks)
- [x] **1.3.6** Add employee search/filter input
- [x] **1.3.7** Keep existing summary cards but add "View Logs" links
- [x] **1.3.8** Add mock "Auto-refresh" toggle (simulates real-time updates every 5 seconds)

**Layout:**
```
┌─────────────────────────────────────────────────┐
│  Ledger Health Widget (green/yellow/red card)  │
├─────────────────┬───────────────────────────────┤
│  Summary Cards  │   Live Event Stream           │
│  - Present: 145 │   ┌─────────────────────────┐ │
│  - Late: 12     │   │ 🟢 Juan DC - Time In   │ │
│  - Absent: 3    │   │    8:05 AM • Gate 1    │ │
│  - On Leave: 5  │   ├─────────────────────────┤ │
│                 │   │ ☕ Maria G - Break     │ │
│  [View Logs]    │   │    10:15 AM • Cafeteria│ │
│                 │   ├─────────────────────────┤ │
│                 │   │ 🔴 Pedro S - Time Out  │ │
│                 │   │    5:30 PM • Gate 2    │ │
│                 │   └─────────────────────────┘ │
└─────────────────┴───────────────────────────────┘
```

**Acceptance Criteria:**
- Overview page integrates new components seamlessly
- Layout responsive (stacks on mobile)
- Filters affect visible logs in real-time
- Auto-refresh toggle simulates live updates
- Existing functionality preserved

---

#### **Task 1.4: Create Event Detail Modal (Mock)**
**File:** `resources/js/components/timekeeping/event-detail-modal.tsx` (NEW)

**Subtasks:**
- [x] **1.4.1** Create modal triggered by clicking any log entry
- [x] **1.4.2** Display full event details:
  - **Employee Section**: Photo, full name, ID, department, position
  - **Event Section**: Type, timestamp, duration (if paired with previous event)
  - **Device Section**: Device ID, location, status, last maintenance
  - **Ledger Section**: Sequence ID, hash chain value, signature, verification status
  - **Processing Section**: Processed at, processing latency, summary impact
- [x] **1.4.3** Add "Event Timeline" showing sequence of events for this employee today
- [x] **1.4.4** Add "Raw Ledger Data" collapsible JSON viewer
- [x] **1.4.5** Add "Export Event" button (downloads JSON)
- [x] **1.4.6** Add "Report Issue" button (for disputed timestamps)
- [x] **1.4.7** Show related events (previous/next in sequence)

**Acceptance Criteria:**
- Modal opens smoothly with animation
- All event metadata clearly organized
- Timeline shows context of employee's day
- JSON viewer properly formatted and collapsible
- Export downloads valid JSON file

---

#### **Task 1.5: Create Employee Daily Timeline View (Mock)**
**File:** `resources/js/components/timekeeping/employee-timeline-view.tsx` (NEW)

**Subtasks:**
- [x] **1.5.1** Create visual timeline component for single employee's day:
  - Horizontal timeline (8 AM → 6 PM)
  - Event markers at each tap (in/out/break)
  - Color-coded segments (working, break, off-duty)
  - Duration labels between events
- [x] **1.5.2** Add hover tooltips on each marker (full event details)
- [x] **1.5.3** Highlight violations (late arrival, early departure, missing punch)
- [x] **1.5.4** Show scheduled vs actual time (ghost outline for scheduled)
- [x] **1.5.5** Add summary stats above timeline (total hours, break time, overtime)
- [x] **1.5.6** Mock multiple employee timelines for comparison view

**Visual Example:**
```
Juan Dela Cruz - January 29, 2026
Total: 8h 45m | Break: 1h 15m | Overtime: 45m

8:00 ─────🟢──────☕──────▶──────☕──────▶──────🔴────── 6:00
      8:05    12:00  12:30  3:00  3:15    5:45
      Time In  Break       Break        Time Out
      (5m late)
```

**Acceptance Criteria:**
- Timeline accurately represents events chronologically
- Visual segments clearly show work/break periods
- Violations highlighted (red borders, warning icons)
- Comparison view shows multiple employees side-by-side
- Responsive (vertical stack on mobile)

---

#### **Task 1.6: Add Filters and Controls Panel (Mock)**
**File:** `resources/js/components/timekeeping/logs-filter-panel.tsx` (NEW)

**Subtasks:**
- [x] **1.6.1** Create filter panel with:
  - Date range picker (Today, This Week, Custom)
  - Department dropdown (All, Production, Admin, Sales, etc.)
  - Event type multi-select (Time In, Time Out, All Breaks)
  - Verification status (All, Verified, Pending, Failed)
  - Device location multi-select (All Gates, Gate 1, Gate 2, etc.)
  - Employee search autocomplete
- [x] **1.6.2** Add "Advanced Filters" collapsible section:
  - Sequence range (from/to)
  - Processing latency threshold (show only slow events)
  - Violation type (Late, Missing Punch, etc.)
- [x] **1.6.3** Add "Active Filters" chips showing current selections (with X to remove)
- [x] **1.6.4** Add "Clear All Filters" button
- [x] **1.6.5** Add "Save Filter Preset" feature (mock local storage)
- [x] **1.6.6** Apply filters to mock data and update log stream in real-time

**Acceptance Criteria:**
- All filters functional with mock data
- Filter combinations work correctly (AND logic)
- Active filters clearly visible
- Filter state preserved when navigating between tabs
- Preset filters can be saved and loaded

---

#### **Task 1.7: Create Device Status Dashboard (Mock)**
**File:** `resources/js/components/timekeeping/device-status-dashboard.tsx` (NEW)

**Subtasks:**
- [x] **1.7.1** Create grid view of all RFID devices:
  - Device card showing: ID, location, status (online/offline), last scan time
  - Event count today
  - Mini event log (last 5 scans)
- [x] **1.7.2** Add status indicators:
  - 🟢 Online (last scan < 10 min ago)
  - 🟡 Idle (last scan 10-60 min ago)
  - 🔴 Offline (last scan > 60 min ago)
  - 🔧 Maintenance mode
- [x] **1.7.3** Add "View Full Log" button per device
- [x] **1.7.4** Mock different device states (some online, some offline)
- [x] **1.7.5** Add device health metrics (uptime %, error rate)
- [x] **1.7.6** Add map view option (show devices on floor plan)

**Mock Devices:**
```typescript
const mockDevices = [
  {
    id: "GATE-01",
    location: "Gate 1 - Main Entrance",
    status: "online",
    lastScanAgo: "5 seconds ago",
    scansToday: 245,
    uptime: 99.8,
    recentScans: [/* last 5 events */]
  },
  {
    id: "GATE-02",
    location: "Gate 2 - Loading Dock",
    status: "idle",
    lastScanAgo: "25 minutes ago",
    scansToday: 87,
    uptime: 98.5,
    recentScans: [/* last 5 events */]
  },
  {
    id: "CAFETERIA-01",
    location: "Cafeteria Break Scanner",
    status: "offline",
    lastScanAgo: "2 hours ago",
    scansToday: 156,
    uptime: 85.2,
    recentScans: []
  }
];
```

**Acceptance Criteria:**
- Device grid shows all devices with current status
- Status updates reflected visually (color changes)
- Map view integrates device locations (can use simple SVG floor plan)
- Device detail view shows full history
- Offline devices clearly highlighted

---

#### **Task 1.8: Create Playback/Replay Control (Mock)**
**File:** `resources/js/components/timekeeping/event-replay-control.tsx` (NEW)

**Subtasks:**
- [x] **1.8.1** Create playback controls for replaying past events:
  - Timeline slider (drag to any point in time)
  - Play/Pause button
  - Speed control (1x, 2x, 5x, 10x)
  - Jump to controls (next event, previous event)
- [x] **1.8.2** Display "Replaying: January 28, 2026 08:00 → 18:00"
- [x] **1.8.3** Animate event stream to show events appearing in sequence
- [x] **1.8.4** Add "Jump to Violation" button (skips to next late/missing punch)
- [x] **1.8.5** Add "Export Replay" button (generates report of replayed period)
- [x] **1.8.6** Mock replay with smooth transitions between events

**Visual Example:**
```
┌────────────────────────────────────────────────┐
│  Replaying: Jan 28, 2026  [⏸] [2x]           │
│  ▶ 08:00 ════════🔵══════════════ 18:00        │
│          Current: 10:35 AM                     │
│  [◀◀ Prev] [Jump to Violation] [Next ▶▶]     │
└────────────────────────────────────────────────┘
```

**Acceptance Criteria:**
- Timeline slider functional (scrub through time)
- Play/Pause animates event stream
- Speed control affects animation speed
- Jump controls work correctly
- Replay preserves sequence integrity

---

### **Phase 2: Frontend Integration with Mock API (Week 1-2)**

**Objective:** Connect frontend components to mock API responses before real backend implementation.

#### **Task 2.1: Create Mock API Service**
**File:** `resources/js/services/mock-timekeeping-api.ts` (NEW)

**Subtasks:**
- [x] **2.1.1** Create mock API service with functions:
  - `fetchTimeLogs(filters)` → returns paginated mock events
  - `fetchLedgerHealth()` → returns mock health status
  - `fetchEmployeeTimeline(employeeId, date)` → returns mock timeline
  - `fetchDeviceStatus()` → returns mock device list
  - `fetchEventDetail(sequenceId)` → returns mock full event
- [x] **2.1.2** Simulate API latency (200-500ms random delay)
- [x] **2.1.3** Implement pagination logic (20 events per page)
- [x] **2.1.4** Implement filter logic (apply filters to mock dataset)
- [x] **2.1.5** Add error simulation (5% chance of network error)

**Acceptance Criteria:**
- Mock API returns realistic data structures
- Latency simulation makes UI feel like real API
- Pagination works correctly
- Filters applied server-side (simulated)

---

#### **Task 2.2: Integrate Mock API with Components**
**Files:** All components from Phase 1

**Subtasks:**
- [ ] **2.2.1** Replace hardcoded mock data with API calls
- [ ] **2.2.2** Add loading states (spinners, skeletons)
- [ ] **2.2.3** Add error handling (retry buttons, error messages)
- [ ] **2.2.4** Implement infinite scroll for event stream
- [ ] **2.2.5** Add optimistic UI updates (show new events immediately, confirm later)
- [ ] **2.2.6** Add polling logic (refresh every 30 seconds)

**Acceptance Criteria:**
- All components fetch from mock API
- Loading states displayed during fetch
- Errors handled gracefully
- Infinite scroll loads more events
- Polling keeps data fresh

---

### **Phase 3: Route Configuration & Navigation (Week 2)**

**Objective:** Set up routing and navigation for new timekeeping features.

#### **Task 3.1: Add New Routes**
**File:** `routes/hr.php`

**Subtasks:**
- [ ] **3.1.1** Add route: `GET /hr/timekeeping/live-logs` → `TimekeepingController@liveLogs`
- [ ] **3.1.2** Add route: `GET /hr/timekeeping/device-status` → `TimekeepingController@deviceStatus`
- [ ] **3.1.3** Add route: `GET /hr/timekeeping/replay` → `TimekeepingController@replay`
- [ ] **3.1.4** Add route: `GET /hr/timekeeping/employee-timeline/{employeeId}` → `TimekeepingController@employeeTimeline`

**Acceptance Criteria:**
- All routes return Inertia responses with mock data
- Routes protected with auth middleware
- Navigation from Overview page works

---

### **Phase 4: Backend API Endpoints (Week 2-3)**

**Objective:** Implement real backend API endpoints to replace mock data.

#### **Task 4.1: Create Ledger API Routes**
**File:** `routes/hr.php`

**Subtasks:**
- [ ] **4.1.1** Add route: `GET /api/timekeeping/ledger/health` → `LedgerHealthController@index`
- [ ] **4.1.2** Add route: `GET /api/timekeeping/ledger/events` → `LedgerController@index` (paginated list)
- [ ] **4.1.3** Add route: `GET /api/timekeeping/ledger/events/{sequenceId}` → `LedgerController@show`
- [ ] **4.1.4** Add route: `POST /api/timekeeping/ledger/sync` → `LedgerSyncController@trigger` (manual sync)
- [ ] **4.1.5** Add route: `GET /api/timekeeping/ledger/devices` → `LedgerDeviceController@index` (device list)

**Acceptance Criteria:**
- All routes protected with `auth` and `permission:timekeeping.attendance.view` middleware
- Routes return JSON responses matching mock API structure
- Route naming follows convention: `timekeeping.ledger.*`

---

#### **Task 4.2: Implement LedgerHealthController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerHealthController.php` (NEW)

**Subtasks:**
- [ ] **4.2.1** Create `index()` method returning latest ledger health status
- [ ] **4.2.2** Fetch last 24 hours of health logs from `ledger_health_logs`
- [ ] **4.2.3** Compute metrics: processing lag, gap count, hash failure count
- [ ] **4.2.4** Return JSON with status (healthy/warning/critical) and detailed metrics
- [ ] **4.2.5** Add caching (5-minute TTL) to reduce DB load

**Acceptance Criteria:**
- Endpoint returns comprehensive health data
- Response structure matches mock API
- Cached for performance

---

#### **Task 4.3: Implement LedgerController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerController.php` (NEW)

**Subtasks:**
- [ ] **4.3.1** Create `index()` method with pagination (20 events per page)
- [ ] **4.3.2** Support filtering by: employee_rfid, device_id, date_range, event_type
- [ ] **4.3.3** Create `show($sequenceId)` method returning single ledger entry
- [ ] **4.3.4** Add permission check: `timekeeping.attendance.view`
- [ ] **4.3.5** Return JSON with ledger fields + linked `attendance_events` record

**Acceptance Criteria:**
- Paginated list matches frontend expectations
- Filters work correctly
- Single entry includes full metadata

---

### **Phase 5: Backend Services & Database Integration (Week 3-4)**

**Objective:** Implement service layer for ledger processing, event handling, and summary computation.

#### **Task 5.1: Database Migrations**

**Subtasks:**
- [ ] **5.1.1** Create migration for `rfid_ledger` table (PostgreSQL)
- [ ] **5.1.2** Add columns to `attendance_events`: `ledger_sequence_id`, `is_deduplicated`, `ledger_hash_verified`
- [ ] **5.1.3** Add columns to `daily_attendance_summary`: `ledger_sequence_start`, `ledger_sequence_end`, `ledger_verified`
- [ ] **5.1.4** Create migration for `ledger_health_logs` table
- [ ] **5.1.5** Add indexes for performance

**Acceptance Criteria:**
- All migrations run successfully
- Indexes improve query performance
- Foreign keys properly configured

---

#### **Task 5.2: Create LedgerPollingService**
**File:** `app/Services/Timekeeping/LedgerPollingService.php` (NEW)

**Subtasks:**
- [ ] **5.2.1** Implement `pollNewEvents()` method to fetch unprocessed ledger entries
- [ ] **5.2.2** Implement deduplication logic (15-second window)
- [ ] **5.2.3** Validate hash chain on each event
- [ ] **5.2.4** Create `AttendanceEvent` records from ledger entries
- [ ] **5.2.5** Mark ledger entries as processed

**Acceptance Criteria:**
- Polling processes events without errors
- Deduplication prevents duplicates
- Hash validation works correctly

---

#### **Task 5.3: Create AttendanceSummaryService**
**File:** `app/Services/Timekeeping/AttendanceSummaryService.php` (NEW)

**Subtasks:**
- [ ] **5.3.1** Implement `computeDailySummary($employeeId, $date)` method
- [ ] **5.3.2** Apply business rules (late, absent, overtime thresholds)
- [ ] **5.3.3** Store/update `daily_attendance_summary` records
- [ ] **5.3.4** Dispatch `AttendanceSummaryUpdated` event

**Acceptance Criteria:**
- Summaries computed accurately
- Business rules applied correctly
- Events dispatched to downstream modules

---

### **Phase 6: Scheduled Jobs & Real-Time Updates (Week 4)**

**Objective:** Automate ledger polling and enable real-time updates.

#### **Task 6.1: Create ProcessRfidLedgerJob**
**File:** `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php` (NEW)

**Subtasks:**
- [ ] **6.1.1** Implement `handle()` method calling `LedgerPollingService`
- [ ] **6.1.2** Configure to run every 1 minute via Laravel Scheduler
- [ ] **6.1.3** Add retry logic and failure notifications

**Acceptance Criteria:**
- Job runs automatically every minute
- Failures trigger alerts

---

#### **Task 6.2: Connect Frontend to Real API**
**Files:** All frontend components

**Subtasks:**
- [ ] **6.2.1** Replace mock API calls with real API endpoints
- [ ] **6.2.2** Test all components with live backend data
- [ ] **6.2.3** Fix any data structure mismatches
- [ ] **6.2.4** Verify real-time polling works correctly

**Acceptance Criteria:**
- All components fetch from real backend
- Live data displays correctly
- No console errors

---

### **Phase 7: Testing & Refinement (Week 4-5)**

**Objective:** Test complete system and refine based on feedback.

#### **Task 7.1: HR Staff User Testing**

**Subtasks:**
- [ ] **7.1.1** Conduct user testing sessions with HR Staff
- [ ] **7.1.2** Gather feedback on UI/UX
- [ ] **7.1.3** Document pain points and improvement suggestions
- [ ] **7.1.4** Prioritize changes based on feedback

**Acceptance Criteria:**
- At least 3 HR Staff test the system
- Feedback documented and prioritized

---

#### **Task 7.2: Performance Optimization**

**Subtasks:**
- [ ] **7.2.1** Optimize database queries (N+1 issues)
- [ ] **7.2.2** Add caching for frequently accessed data
- [ ] **7.2.3** Optimize frontend bundle size
- [ ] **7.2.4** Test with 1000+ events loaded

**Acceptance Criteria:**
- Page load < 2 seconds
- Event stream scrolls smoothly with 1000+ items

---

#### **Task 7.3: Integration Testing**

**Subtasks:**
- [ ] **7.3.1** Test end-to-end flow: RFID scan → Display in UI
- [ ] **7.3.2** Test offline device handling
- [ ] **7.3.3** Test hash chain validation
- [ ] **7.3.4** Test workflow gating (Payroll integration)

**Acceptance Criteria:**
- All integration points work correctly
- Edge cases handled gracefully
- Manual corrections clearly separated from ledger source data

---

#### **Task 1.4: Add Ledger Health Dashboard Widget**
**File:** `resources/js/components/timekeeping/ledger-health-widget.tsx` (NEW)

**Subtasks:**
- [ ] **1.4.1** Create new component `LedgerHealthWidget` showing:
  - Last sequence ID processed
  - Processing lag (seconds between scan and processing)
  - Hash chain status (✅ valid / ❌ broken)
  - Sequence gaps detected (count)
  - Replay jobs in progress (count)
- [ ] **1.4.2** Add color-coded status: Green (healthy), Yellow (lag > 5 min), Red (integrity failed)
- [ ] **1.4.3** Add "View Details" button opening ledger health logs modal
- [ ] **1.4.4** Add "Trigger Manual Sync" button for HR Manager (forces ledger poll)
- [ ] **1.4.5** Display device online/offline status (based on last scan timestamp)

**Acceptance Criteria:**
- Widget displays on Overview page and Attendance Index page
- Real-time health status updates every 30 seconds
- HR Manager can manually trigger sync

---

#### **Task 1.5: Update Attendance Filters Component**
**File:** `resources/js/components/timekeeping/attendance-filters.tsx`

**Subtasks:**
- [ ] **1.5.1** Add "Source" filter dropdown: All / RFID Ledger / Manual / Imported
- [ ] **1.5.2** Add "Ledger Verified" checkbox filter
- [ ] **1.5.3** Add "Sequence ID Range" input (for audit/replay scenarios)
- [ ] **1.5.4** Add "Device ID" dropdown (populated from `rfid_ledger.device_id`)
- [ ] **1.5.5** Update filter state management to include new ledger-specific filters

**Acceptance Criteria:**
- All ledger-specific filters functional
- Filter combinations work correctly (e.g., "RFID Ledger + Not Verified")

---

#### **Task 1.6: Update Source Indicator Component**
**File:** `resources/js/components/timekeeping/source-indicator.tsx`

**Subtasks:**
- [ ] **1.6.1** Update `edge_machine` source to display "RFID Ledger" label
- [ ] **1.6.2** Add ledger icon (e.g., 🔗 chain link) for ledger-sourced events
- [ ] **1.6.3** Add verification badge (🔒 verified / ⚠️ unverified) next to source label
- [ ] **1.6.4** Add tooltip showing device ID and sequence ID on hover

**Acceptance Criteria:**
- Source indicator clearly distinguishes ledger vs manual vs imported
- Verification status visible at a glance

---

### **Phase 2: Route Configuration & API Endpoints (Week 1-2)**

**Objective:** Configure Laravel routes for ledger polling, event processing, and health monitoring.

#### **Task 2.1: Create Ledger API Routes**
**File:** `routes/hr.php`

**Subtasks:**
- [ ] **2.1.1** Add route: `GET /api/timekeeping/ledger/health` → `LedgerHealthController@index`
- [ ] **2.1.2** Add route: `GET /api/timekeeping/ledger/events` → `LedgerController@index` (paginated list)
- [ ] **2.1.3** Add route: `GET /api/timekeeping/ledger/events/{sequenceId}` → `LedgerController@show`
- [ ] **2.1.4** Add route: `POST /api/timekeeping/ledger/sync` → `LedgerSyncController@trigger` (manual sync)
- [ ] **2.1.5** Add route: `POST /api/timekeeping/ledger/verify` → `LedgerVerificationController@verify` (hash chain check)
- [ ] **2.1.6** Add route: `GET /api/timekeeping/ledger/devices` → `LedgerDeviceController@index` (device list)

**Acceptance Criteria:**
- All routes protected with `auth` and `permission:timekeeping.attendance.view` middleware
- Routes return JSON responses
- Route naming follows convention: `timekeeping.ledger.*`

---

#### **Task 2.2: Update Attendance API Routes**
**File:** `routes/hr.php`

**Subtasks:**
- [ ] **2.2.1** Update `GET /api/timekeeping/attendance` to include ledger metadata in response
- [ ] **2.2.2** Add query param `?source=ledger|manual|imported` for filtering
- [ ] **2.2.3** Add query param `?verified=true|false` for hash verification filter
- [ ] **2.2.4** Update `GET /api/timekeeping/attendance/{id}` to include full ledger provenance
- [ ] **2.2.5** Add route: `POST /api/timekeeping/attendance/{id}/correct` → `AttendanceCorrectionController@store`

**Acceptance Criteria:**
- Existing routes enhanced with ledger data
- Filters work correctly
- Correction route creates correction record without modifying ledger

---

#### **Task 2.3: Create Event Streaming Route (Optional)**
**File:** `routes/hr.php`

**Subtasks:**
- [ ] **2.3.1** Add Server-Sent Events (SSE) route: `GET /api/timekeeping/attendance/stream`
- [ ] **2.3.2** Configure route to push new attendance events in real-time
- [ ] **2.3.3** Add authentication check on SSE connection
- [ ] **2.3.4** Implement event throttling (max 1 event per second per client)

**Acceptance Criteria:**
- SSE endpoint streams new events to connected clients
- Frontend can subscribe and update UI without polling (alternative to Task 1.1.1)

---

### **Phase 3: Backend Controllers (Week 2)**

**Objective:** Implement controllers to handle ledger data retrieval, health monitoring, and event processing.

---

#### **Task 4.2: Implement LedgerHealthController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerHealthController.php` (NEW)

**Subtasks:**
- [ ] **4.2.1** Create `index()` method returning latest ledger health status
- [ ] **4.2.2** Fetch last 24 hours of health logs from `ledger_health_logs`
- [ ] **4.2.3** Compute metrics: processing lag, gap count, hash failure count
- [ ] **4.2.4** Return JSON with status (healthy/warning/critical) and detailed metrics
- [ ] **4.2.5** Add caching (5-minute TTL) to reduce DB load

**Acceptance Criteria:**
- Endpoint returns comprehensive health data
- Response structure matches mock API
- Cached for performance

---

#### **Task 4.3: Implement LedgerController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerController.php` (NEW)

**Subtasks:**
- [ ] **4.3.1** Create `index()` method with pagination (20 events per page)
- [ ] **4.3.2** Support filtering by: employee_rfid, device_id, date_range, event_type
- [ ] **4.3.3** Create `show($sequenceId)` method returning single ledger entry
- [ ] **4.3.4** Add permission check: `timekeeping.attendance.view`
- [ ] **4.3.5** Return JSON with ledger fields + linked `attendance_events` record

**Acceptance Criteria:**
- Paginated list matches frontend expectations
- Filters work correctly
- Single entry includes full metadata

---

### **Phase 5: Backend Services & Database Integration (Week 3-4)**

**Objective:** Implement service layer for ledger processing, event handling, and summary computation.
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerSyncController.php` (NEW)

**Subtasks:**
- [ ] **3.3.1** Create `trigger()` method to manually invoke ledger polling job
- [ ] **3.3.2** Add permission check: `timekeeping.attendance.create` (HR Manager only)
- [ ] **3.3.3** Dispatch `ProcessRfidLedgerJob` with priority flag
- [ ] **3.3.4** Return JSON: `{ message: "Sync triggered", job_id: "..." }`
- [ ] **3.3.5** Log manual sync action in `activity_log`

**Acceptance Criteria:**
- Manual sync triggers immediate ledger processing
- Only HR Manager can trigger
- Action logged for audit

---

#### **Task 3.4: Create LedgerVerificationController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerVerificationController.php` (NEW)

**Subtasks:**
- [ ] **3.4.1** Create `verify()` method accepting sequence range (e.g., 1000-1500)
- [ ] **3.4.2** Fetch ledger entries in range, validate hash chain
- [ ] **3.4.3** Return JSON: `{ valid: true/false, broken_at: sequence_id, details: "..." }`
- [ ] **3.4.4** Add permission check: `timekeeping.analytics.view` (HR Manager only)
- [ ] **3.4.5** Log verification result in `ledger_health_logs`

**Acceptance Criteria:**
- Hash chain validation works correctly
- Broken chains reported with specific sequence ID
- Results logged for audit

---

#### **Task 3.5: Create LedgerDeviceController**
**File:** `app/Http/Controllers/HR/Timekeeping/LedgerDeviceController.php` (NEW)

**Subtasks:**
- [ ] **3.5.1** Create `index()` method returning unique devices from `rfid_ledger.device_id`
- [ ] **3.5.2** Compute per-device metrics: total scans, last scan timestamp, online/offline status
- [ ] **3.5.3** Return JSON array of devices with metadata
- [ ] **3.5.4** Add caching (15-minute TTL)

**Acceptance Criteria:**
- Device list shows all RFID scanners
- Online/offline status based on last scan (offline if > 10 minutes)

---

#### **Task 3.6: Update AttendanceController**
**File:** `app/Http/Controllers/HR/Timekeeping/AttendanceController.php`

**Subtasks:**
- [ ] **3.6.1** Update `index()` to include ledger metadata in response (eager load `ledgerSequence` relation)
- [ ] **3.6.2** Update `show($id)` to include full ledger provenance data
- [ ] **3.6.3** Add `source` and `verified` query param filters
- [ ] **3.6.4** Update response format to include: `ledger_sequence_id`, `hash_verified`, `device_id`, `is_deduplicated`

**Acceptance Criteria:**
- Attendance records include ledger data in API responses
- Filters work as expected
- No breaking changes to existing frontend code

---

#### **Task 3.7: Create AttendanceCorrectionController**
**File:** `app/Http/Controllers/HR/Timekeeping/AttendanceCorrectionController.php` (NEW)

**Subtasks:**
- [ ] **3.7.1** Create `store()` method accepting: attendance_event_id, correction_type, corrected_time, reason
- [ ] **3.7.2** Validate: only HR Manager can submit corrections
- [ ] **3.7.3** Create `AttendanceCorrection` record (do NOT modify ledger or original event)
- [ ] **3.7.4** Recompute affected `daily_attendance_summary` with correction applied
- [ ] **3.7.5** Dispatch `AttendanceCorrectionApplied` event to notify downstream modules
- [ ] **3.7.6** Log correction in `activity_log`

**Acceptance Criteria:**
- Corrections stored separately from ledger data
- Summaries recomputed with corrections
- Payroll receives correction event

---

### **Phase 4: Backend Services (Week 2-3)**

**Objective:** Implement service layer for ledger processing, event deduplication, summary computation, and replay orchestration.

#### **Task 4.1: Create LedgerPollingService**
**File:** `app/Services/Timekeeping/LedgerPollingService.php` (NEW)

**Subtasks:**
- [ ] **4.1.1** Implement `pollNewEvents()` method:
  - Query `rfid_ledger WHERE processed = false ORDER BY sequence_id ASC`
  - Fetch up to 500 unprocessed events per poll
- [ ] **4.1.2** Implement deduplication logic:
  - Check for duplicates within 15-second window (same employee, device, event_type)
  - Mark as `is_deduplicated = true`, reference original event ID
- [ ] **4.1.3** Validate hash chain:
  - Fetch previous event, compute expected hash, compare with current `hash_chain`
  - If mismatch, mark `hash_verified = false`, log to `ledger_health_logs`
- [ ] **4.1.4** Create `AttendanceEvent` record for each valid ledger entry
- [ ] **4.1.5** Mark ledger entry as `processed = true`, set `processed_at` timestamp
- [ ] **4.1.6** Return processing summary: `{ processed: 450, deduplicated: 3, failed: 2 }`

**Acceptance Criteria:**
- Polling processes up to 500 events per run
- Deduplication prevents duplicate attendance records
- Hash chain validated on every event
- Processing summary logged

---

#### **Task 4.2: Create AttendanceSummaryService**
**File:** `app/Services/Timekeeping/AttendanceSummaryService.php` (NEW)

**Subtasks:**
- [ ] **4.2.1** Implement `computeDailySummary($employeeId, $date)` method:
  - Fetch all `attendance_events` for employee + date (including corrections)
  - Compute: time_in, time_out, break_start, break_end, total_hours, late_minutes, undertime_minutes
  - Determine status: present, late, absent, on_leave, undertime, overtime
- [ ] **4.2.2** Implement business rules:
  - Late if time_in > scheduled_start + grace_period (e.g., 15 minutes)
  - Absent if no time_in event by scheduled_end
  - Overtime if time_out > scheduled_end + overtime_threshold
- [ ] **4.2.3** Store/update `daily_attendance_summary` record
- [ ] **4.2.4** Set `ledger_sequence_start` and `ledger_sequence_end` from processed events
- [ ] **4.2.5** Set `ledger_verified = true` only if all source events have `hash_verified = true`
- [ ] **4.2.6** Dispatch `AttendanceSummaryUpdated` event to notify downstream modules

**Acceptance Criteria:**
- Daily summaries computed accurately
- Business rules applied correctly
- Ledger integrity status tracked
- Event dispatched for Payroll integration

---

#### **Task 4.3: Create LedgerReplayService**
**File:** `app/Services/Timekeeping/LedgerReplayService.php` (NEW)

**Subtasks:**
- [ ] **4.3.1** Implement `detectGaps()` method:
  - Query `rfid_ledger` for missing sequence IDs
  - Return list of gaps: `[{ start: 1050, end: 1055 }]`
- [ ] **4.3.2** Implement `replaySequenceRange($start, $end)` method:
  - Fetch ledger entries in range
  - Reprocess each event (revalidate hash, deduplicate, create/update attendance_events)
  - Recompute affected daily summaries
- [ ] **4.3.3** Implement `verifyHashChain($start, $end)` method:
  - Iterate through sequence range, validate each hash against previous
  - Return validation result + list of broken sequences
- [ ] **4.3.4** Implement `exportLedgerSnapshot($start, $end)` method:
  - Export ledger entries as JSON for WORM storage
  - Include hash chain metadata for external verification
- [ ] **4.3.5** Log all replay operations to `ledger_health_logs`

**Acceptance Criteria:**
- Gap detection identifies missing sequences
- Replay reprocesses events without data loss
- Hash chain verification catches tampering
- Snapshots exportable for audit

---

#### **Task 4.4: Create WorkflowGatingService**
**File:** `app/Services/Timekeeping/WorkflowGatingService.php` (NEW)

**Subtasks:**
- [ ] **4.4.1** Implement `checkPayrollEligibility($payrollPeriod)` method:
  - Query `ledger_health_logs` for critical status in period
  - Check for unprocessed ledger events in period
  - Check for hash chain failures in period
  - Return: `{ eligible: true/false, reasons: [...] }`
- [ ] **4.4.2** Implement `checkAppraisalDataIntegrity($employeeId, $period)` method:
  - Verify all attendance summaries in period have `ledger_verified = true`
  - Check for processing gaps
  - Return integrity status
- [ ] **4.4.3** Implement `getBlockingIssues()` method:
  - Return list of critical ledger issues blocking workflows
  - Include: sequence gaps, hash failures, processing delays > 1 hour
- [ ] **4.4.4** Integrate with Payroll module: block payroll approval if not eligible

**Acceptance Criteria:**
- Payroll blocked if ledger integrity compromised
- Appraisal data imports only from verified ledger sequences
- Clear error messages for blocking issues

---

#### **Task 4.5: Create RfidEventMapper**
**File:** `app/Services/Timekeeping/RfidEventMapper.php` (NEW)

**Subtasks:**
- [ ] **4.5.1** Implement `mapToAttendanceEvent($ledgerEntry)` method:
  - Parse `raw_payload` JSON
  - Map RFID employee ID to system `employee_id`
  - Map event_type to system EventType enum
  - Validate timestamp (reject future timestamps, timestamps > 24 hours old)
  - Return `AttendanceEvent` data array or validation error
- [ ] **4.5.2** Implement employee lookup by RFID:
  - Query `employees.rfid_card_number` to get `employee_id`
  - Handle unknown RFID cards (log warning, create pending record)
- [ ] **4.5.3** Implement device lookup:
  - Query or create `edge_devices` record for device_id
  - Track device metadata (location, status)

**Acceptance Criteria:**
- Ledger entries correctly mapped to attendance events
- Unknown RFID cards handled gracefully
- Device metadata tracked

---

### **Phase 5: Scheduled Jobs & Event Listeners (Week 3)**

**Objective:** Automate ledger polling, health checks, and event-driven integrations.

#### **Task 5.1: Create ProcessRfidLedgerJob**
**File:** `app/Jobs/Timekeeping/ProcessRfidLedgerJob.php` (NEW)

**Subtasks:**
- [ ] **5.1.1** Implement `handle()` method:
  - Call `LedgerPollingService::pollNewEvents()`
  - For each processed event, dispatch `AttendanceEventProcessed` event
  - Log processing summary
  - Update `ledger_health_logs` with current status
- [ ] **5.1.2** Configure job to run every 1 minute via Laravel Scheduler
- [ ] **5.1.3** Add job priority: high (process before other background jobs)
- [ ] **5.1.4** Add retry logic: max 3 retries with exponential backoff
- [ ] **5.1.5** Add failure notification: alert HR Manager if job fails 3 times

**Acceptance Criteria:**
- Job runs every minute automatically
- Processes all unprocessed ledger events
- Failures trigger alerts

---

#### **Task 5.2: Create ComputeDailySummariesJob**
**File:** `app/Jobs/Timekeeping/ComputeDailySummariesJob.php` (NEW)

**Subtasks:**
- [ ] **5.2.1** Implement `handle($date)` method:
  - Fetch all employees with attendance events on $date
  - Call `AttendanceSummaryService::computeDailySummary()` for each
  - Log summary computation results
- [ ] **5.2.2** Configure job to run daily at 11:59 PM (end of day)
- [ ] **5.2.3** Add manual trigger option for recomputation

**Acceptance Criteria:**
- Daily summaries computed automatically at end of day
- Manual recomputation available for corrections

---

#### **Task 5.3: Create LedgerHealthCheckJob**
**File:** `app/Jobs/Timekeeping/LedgerHealthCheckJob.php` (NEW)

**Subtasks:**
- [ ] **5.3.1** Implement `handle()` method:
  - Call `LedgerReplayService::detectGaps()`
  - Call `LedgerReplayService::verifyHashChain()` on recent sequences
  - Compute processing lag (last processed sequence vs latest in ledger)
  - Determine health status (healthy/warning/critical)
  - Store result in `ledger_health_logs`
- [ ] **5.3.2** Configure job to run every 5 minutes
- [ ] **5.3.3** Add alert logic: notify HR Manager if status = critical

**Acceptance Criteria:**
- Health checks run every 5 minutes
- Critical issues trigger immediate alerts

---

#### **Task 5.4: Create AttendanceEventProcessedListener**
**File:** `app/Listeners/Timekeeping/AttendanceEventProcessedListener.php` (NEW)

**Subtasks:**
- [ ] **5.4.1** Listen for `AttendanceEventProcessed` event
- [ ] **5.4.2** Trigger daily summary recomputation for affected employee + date
- [ ] **5.4.3** Check for attendance violations (late, absent, undertime)
- [ ] **5.4.4** If violation detected, dispatch `AttendanceViolation` event
- [ ] **5.4.5** Log event processing

**Acceptance Criteria:**
- Summaries updated immediately after event processing
- Violations trigger downstream events

---

#### **Task 5.5: Create AttendanceSummaryUpdatedListener**
**File:** `app/Listeners/Timekeeping/AttendanceSummaryUpdatedListener.php` (NEW)

**Subtasks:**
- [ ] **5.5.1** Listen for `AttendanceSummaryUpdated` event
- [ ] **5.5.2** Dispatch to Payroll module: `PayrollAttendanceUpdated` event
- [ ] **5.5.3** Dispatch to Notification module: `SendAttendanceNotification` event (for late/absent)
- [ ] **5.5.4** Update workforce coverage analytics

**Acceptance Criteria:**
- Payroll module receives attendance updates in real-time
- Notifications sent for policy violations

---

#### **Task 5.6: Create AttendanceViolationListener**
**File:** `app/Listeners/Timekeeping/AttendanceViolationListener.php` (NEW)

**Subtasks:**
- [ ] **5.6.1** Listen for `AttendanceViolation` event
- [ ] **5.6.2** Store violation in `attendance_violations` table (if exists, or add to summary)
- [ ] **5.6.3** Dispatch to Appraisal module: `AppraisalAttendanceScore` event
- [ ] **5.6.4** Send notification to HR Manager and employee's supervisor

**Acceptance Criteria:**
- Violations tracked and reported
- Appraisal module receives violation data for performance scoring

---

### **Phase 6: Testing & Validation (Week 4)**

**Objective:** Comprehensive testing of ledger integration, event processing, and workflow gating.

#### **Task 6.1: Unit Tests**

**Subtasks:**
- [ ] **6.1.1** Test `LedgerPollingService::pollNewEvents()` with mock ledger data
- [ ] **6.1.2** Test deduplication logic (same employee, 10-second gap)
- [ ] **6.1.3** Test hash chain validation (valid chain, broken chain)
- [ ] **6.1.4** Test `AttendanceSummaryService::computeDailySummary()` with various scenarios
- [ ] **6.1.5** Test `LedgerReplayService::detectGaps()` with missing sequences
- [ ] **6.1.6** Test `WorkflowGatingService::checkPayrollEligibility()` with integrity failures

**Acceptance Criteria:**
- All service methods covered by unit tests
- Edge cases handled correctly

---

#### **Task 6.2: Integration Tests**

**Subtasks:**
- [ ] **6.2.1** Test end-to-end flow: RFID scan → Ledger → Attendance Event → Summary → Payroll Event
- [ ] **6.2.2** Test manual correction flow: Submit correction → Recompute summary → Notify Payroll
- [ ] **6.2.3** Test gap detection and replay: Create gap → Detect → Replay → Verify
- [ ] **6.2.4** Test workflow gating: Create hash failure → Block payroll → Resolve → Unblock
- [ ] **6.2.5** Test offline device scenario: Device offline 2 hours → Batch sync → Process all events

**Acceptance Criteria:**
- End-to-end flows work without errors
- Edge cases (gaps, offline devices) handled correctly

---

#### **Task 6.3: Performance Testing**

**Subtasks:**
- [ ] **6.3.1** Test ledger polling with 10,000 unprocessed events
- [ ] **6.3.2** Test hash chain validation on 50,000 sequences
- [ ] **6.3.3** Test summary computation for 500 employees per day
- [ ] **6.3.4** Measure API response times for ledger endpoints (< 500ms)
- [ ] **6.3.5** Test real-time polling/SSE performance with 100 concurrent clients

**Acceptance Criteria:**
- Polling processes 500 events in < 30 seconds
- Hash validation completes in < 5 seconds
- API responses within acceptable latency

---

#### **Task 6.4: Security Testing**

**Subtasks:**
- [ ] **6.4.1** Test permission enforcement on all ledger routes
- [ ] **6.4.2** Test SQL injection on ledger query parameters
- [ ] **6.4.3** Test CSRF protection on sync/verification endpoints
- [ ] **6.4.4** Test unauthorized access attempts (non-HR users)
- [ ] **6.4.5** Test ledger immutability (attempt to modify processed entries)

**Acceptance Criteria:**
- All routes properly protected
- No SQL injection vulnerabilities
- Ledger immutability enforced

---

### **Phase 7: Deployment & Monitoring (Week 4-5)**

**Objective:** Deploy to production, configure monitoring, and establish operational procedures.

#### **Task 7.1: Database Migration**

**Subtasks:**
- [ ] **7.1.1** Run migrations for `rfid_ledger`, `ledger_health_logs`, `attendance_events` updates
- [ ] **7.1.2** Add indexes for performance (sequence_id, employee_rfid, processed)
- [ ] **7.1.3** Verify foreign key constraints
- [ ] **7.1.4** Backup production database before migration

**Acceptance Criteria:**
- All tables created successfully
- Indexes improve query performance
- No data loss during migration

---

#### **Task 7.2: Configure Scheduled Jobs**

**Subtasks:**
- [ ] **7.2.1** Add to `app/Console/Kernel.php`:
  - `ProcessRfidLedgerJob` every 1 minute
  - `ComputeDailySummariesJob` daily at 11:59 PM
  - `LedgerHealthCheckJob` every 5 minutes
- [ ] **7.2.2** Verify Laravel Scheduler is running via cron
- [ ] **7.2.3** Test job execution in production environment

**Acceptance Criteria:**
- All jobs run on schedule
- Cron configured correctly
- Job logs visible in Laravel Horizon (if using)

---

#### **Task 7.3: Configure Monitoring & Alerts**

**Subtasks:**
- [ ] **7.3.1** Set up Grafana/Prometheus dashboards for:
  - Ledger processing lag (current time - last processed timestamp)
  - Events processed per minute
  - Hash verification failures
  - Sequence gaps detected
- [ ] **7.3.2** Configure email alerts for:
  - Critical ledger health status
  - Job failures (3+ retries)
  - Hash chain broken
  - Processing lag > 30 minutes
- [ ] **7.3.3** Add Slack webhook for real-time alerts to HR Manager

**Acceptance Criteria:**
- Dashboards show real-time metrics
- Alerts trigger correctly
- HR Manager receives notifications

---

#### **Task 7.4: Create Operational Runbook**

**Subtasks:**
- [ ] **7.4.1** Document ledger polling process and troubleshooting steps
- [ ] **7.4.2** Document gap detection and replay procedure
- [ ] **7.4.3** Document manual sync procedure for HR Manager
- [ ] **7.4.4** Document workflow gating resolution (how to unblock payroll)
- [ ] **7.4.5** Create escalation path for critical integrity failures

**Acceptance Criteria:**
- Runbook covers all operational scenarios
- HR Manager trained on manual procedures

---

#### **Task 7.5: Production Deployment**

**Subtasks:**
- [ ] **7.5.1** Deploy backend code (controllers, services, jobs, listeners)
- [ ] **7.5.2** Deploy frontend code (updated pages, new components)
- [ ] **7.5.3** Deploy database migrations
- [ ] **7.5.4** Verify scheduled jobs start running
- [ ] **7.5.5** Monitor first 24 hours for errors
- [ ] **7.5.6** Conduct smoke tests (scan RFID → verify event appears in UI)

**Acceptance Criteria:**
- Deployment successful with no downtime
- All features working in production
- No critical errors in first 24 hours

---

## 📊 Success Metrics

**Technical Metrics:**
- Ledger processing lag < 2 minutes (95th percentile)
- Hash chain validation passes 100% (critical)
- Zero sequence gaps in production
- API response time < 500ms (95th percentile)
- Job failure rate < 0.1%

**Business Metrics:**
- 100% of attendance events sourced from RFID ledger (vs manual entry)
- Payroll processing time reduced by 50% (automated data)
- Attendance dispute resolution time reduced by 70% (tamper-proof audit trail)
- Zero data integrity issues in payroll calculations

**User Adoption:**
- HR Staff views ledger health dashboard daily
- HR Manager uses manual sync < 5 times per month (system is reliable)
- Zero manual attendance entry for RFID-enabled employees

---

## 🔗 Integration Points

**Payroll Module:**
- Receives `AttendanceSummaryUpdated` events
- Blocks payroll approval if `WorkflowGatingService::checkPayrollEligibility()` returns false
- Uses `daily_attendance_summary` for salary calculations

**Appraisal Module:**
- Receives `AttendanceViolation` events
- Imports attendance/punctuality scores from verified ledger sequences
- References `ledger_health_logs` when finalizing performance ratings

**Notification Module:**
- Receives `AttendanceViolation` events
- Sends alerts to employees (late arrival, absent)
- Sends alerts to HR Manager (critical ledger issues)

**Workforce Management:**
- Receives real-time attendance data for coverage analytics
- Uses attendance events to validate rotation assignments
- Triggers alerts when coverage falls below threshold

---

## 🔐 Security & Compliance

**Data Integrity:**
- Append-only ledger ensures RFID events cannot be modified
- Hash chain validation detects tampering
- Manual corrections stored separately with full audit trail

**Access Control:**
- Ledger data read-only for all users (only FastAPI server writes)
- Manual sync and verification restricted to HR Manager
- All actions logged in `activity_log` with user ID and timestamp

**Philippine Labor Law Compliance:**
- 5-year retention of all attendance records (ledger + summaries)
- Automated export to WORM storage for legal defensibility
- Audit trail meets DOLE requirements for time record accuracy

**GDPR/Privacy (if applicable):**
- Employee RFID data pseudonymized in logs
- Data export tools for employee data portability
- Retention policy enforced with automated archiving

---

## 📚 Related Documentation

- [RFID Replayable Event-Log Proposal](../workflows/integrations/patentable-proposal/rfid-replayable-event-log-proposal.md)
- [Timekeeping Module Architecture](../TIMEKEEPING_MODULE_ARCHITECTURE.md)
- [Performance Appraisal Process](../workflows/processes/performance-appraisal.md)
- [Payroll Processing Workflow](../workflows/processes/payroll-processing.md)
- [HR Manager Workflow](../workflows/03-hr-manager-workflow.md)
- [HR Staff Workflow](../workflows/04-hr-staff-workflow.md)

---

## 🗓️ Timeline Summary

| Phase | Duration | Key Deliverables |
|-------|----------|------------------|
| **Phase 1: Frontend Updates** | Week 1 | Updated pages with ledger data, real-time polling, health dashboard |
| **Phase 2: Route Configuration** | Week 1-2 | API routes for ledger access, health monitoring, manual sync |
| **Phase 3: Backend Controllers** | Week 2 | Controllers for ledger, health, verification, corrections |
| **Phase 4: Backend Services** | Week 2-3 | Polling, summary, replay, gating, mapping services |
| **Phase 5: Jobs & Listeners** | Week 3 | Scheduled jobs, event listeners, downstream integrations |
| **Phase 6: Testing** | Week 4 | Unit, integration, performance, security tests |
| **Phase 7: Deployment** | Week 4-5 | Production deployment, monitoring, operational runbook |

**Total Duration:** 4-5 weeks  
**Team Size:** 2-3 developers (1 backend, 1 frontend, 1 QA/DevOps)

---

## ✅ Pre-Implementation Checklist

- [ ] FastAPI RFID server is operational and writing to `rfid_ledger` table
- [ ] PostgreSQL database configured and accessible from Laravel
- [ ] RFID devices enrolled and assigned device IDs
- [ ] Employee RFID cards registered in `employees.rfid_card_number`
- [ ] Existing Timekeeping pages functional (baseline before changes)
- [ ] Laravel Scheduler configured with cron job
- [ ] Event bus configured (Laravel Events or external queue)
- [ ] Monitoring infrastructure ready (Grafana, Prometheus, or equivalent)
- [ ] HR Manager and HR Staff trained on new workflows
- [ ] Rollback plan prepared in case of deployment issues

---

**Document Version:** 1.0  
**Last Updated:** January 29, 2026  
**Document Owner:** Development Team  
**Approved By:** [Pending]

---

## 📝 Change Log

| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2026-01-29 | 1.0 | Initial implementation plan created | AI Assistant |

---

**Status:** 🟡 READY FOR REVIEW  
**Next Steps:** Review with development team → Approve → Begin Phase 1 implementation
