# Device Management & RFID Badge System - Implementation Guide

**⚠️ IMPORTANT: This file is superseded by domain-separated implementation files:**
- **[SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md](./SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md)** - Device Management (System Domain, SuperAdmin)
- **[HR_BADGE_MANAGEMENT_IMPLEMENTATION.md](./HR_BADGE_MANAGEMENT_IMPLEMENTATION.md)** - Badge Management (HR Domain, HR Staff/Manager)

**Status:** ✅ Suggestions implemented in domain-separated files  
**Issue Type:** Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 4 weeks (2 weeks System + 2 weeks HR)  
**Target Users:** SuperAdmin (Device Management), HR Staff + HR Manager (Badge Management)  
**Dependencies:** Timekeeping Module Phase 1, PostgreSQL Database, Employee Module  
**Related Documents:**
- [TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md](./TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md)
- [FASTAPI_RFID_SERVER_IMPLEMENTATION.md](./FASTAPI_RFID_SERVER_IMPLEMENTATION.md)
- [TIMEKEEPING_MODULE_STATUS_REPORT.md](../TIMEKEEPING_MODULE_STATUS_REPORT.md)

---

## 📋 Executive Summary

**DOMAIN SEPARATION ARCHITECTURE:**

This implementation has been split into two domain-specific modules for better security and role clarity:

### **System Domain** (SuperAdmin)
**Route:** `/system/timekeeping-devices`  
**Purpose:** Technical infrastructure management  
**Responsibilities:**
1. **Register and configure RFID scanners/readers** (devices at gates, entrances, etc.)
2. **Monitor device health** and perform maintenance scheduling
3. **Test network connectivity** and device troubleshooting
4. **Manage device configurations** (IP, port, firmware)

### **HR Domain** (HR Staff + HR Manager)
**Route:** `/hr/timekeeping/badges`  
**Purpose:** Employee operations management  
**Responsibilities:**
1. **Issue and manage RFID badges** for employees
2. **Assign badges to employees** with activation/deactivation controls
3. **Track badge usage** and handle replacement workflows
4. **Generate compliance reports** (employees without badges)

This separation ensures technical infrastructure management remains with IT/SuperAdmin while employee badge operations are handled by HR personnel.

**See domain-specific implementation files for complete implementation details.**

---

## 💡 Clarifications & Suggestions

### **Clarifications Needed**

1. **Device Registration Workflow:**
   - ❓ Should device registration require approval from IT/Admin before activation?
   - ❓ Do devices need to be physically tested during registration (send test scan)?
   - ❓ Should we support device groups/zones (e.g., "Main Building", "Warehouse")?
   - **✅ IMPLEMENTED:** Immediate registration with health check required before marking as "operational" (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.3 & 1.7)

2. **RFID Badge Issuance:**
   - ❓ Should badge issuance require employee acknowledgment/signature?
   - ❓ Do we need to track physical badge inventory (stock management)?
   - ❓ Should lost/stolen badges require incident report filing?
   - **✅ IMPLEMENTED:** Simple issuance with optional notes field and acknowledgment signature (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.3)

3. **Security & Access Control:**
   - ❓ Who can register devices? (HR Manager only? System Admin?)
   - ❓ Who can issue badges? (HR Staff + HR Manager?)
   - ❓ Should badge deactivation require multi-step approval?
   - **✅ IMPLEMENTED:** Domain separation enforced:
     - Device registration: **SuperAdmin only** (System domain)
     - Badge issuance: **HR Staff + HR Manager** (HR domain)
     - Badge deactivation: **HR Staff** (with full audit logging)
     - See permission seeders in both implementation files

4. **Badge Replacement Workflow:**
   - ❓ What happens to old badge when new one is issued? (Auto-deactivate?)
   - ❓ Should we maintain badge history (all cards ever issued to employee)?
   - ❓ Do we need grace period where both old and new badges work?
   - **✅ IMPLEMENTED:** Auto-deactivate old badge immediately, maintain full history in `badge_issue_logs` table (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.5)

5. **Integration with FastAPI Server:**
   - ❓ Should device registration in Laravel automatically sync to FastAPI server?
   - ❓ How should we handle devices registered in Laravel but not yet in FastAPI?
   - **✅ IMPLEMENTED:** Manual sync via API endpoint, with sync status indicator in UI and "Sync with Server" button (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.1)

6. **Bulk Operations:**
   - ❓ Do we need bulk badge import (CSV upload for mass employee onboarding)?
   - ❓ Should we support bulk device registration (multiple gates/entrances at once)?
   - **✅ IMPLEMENTED:** Bulk badge import with CSV/Excel support, validation, and import preview (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.7)

### **Suggested Features**

1. **Device Health Dashboard:**
   - ✅ Real-time device status with heartbeat monitoring (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.1.2)
   - ✅ Historical uptime/downtime charts (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.4.3)
   - ✅ Maintenance reminder system (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.6.3)
   - ✅ Device health test runner with connectivity checks (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, Task 1.7)

2. **Badge Lifecycle Management:**
   - ✅ Badge expiration dates with auto-renewal reminders (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, expiration tracking in models)
   - ✅ Lost/stolen badge reporting with immediate deactivation (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.5.2)
   - ✅ Badge usage analytics - scans per day, most used devices, peak hours (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.4.3)
   - ✅ Employees without badges widget for compliance tracking (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.8)
   - ✅ Badge replacement workflow with reason tracking (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.5)

3. **Audit & Compliance:**
   - ✅ Full audit trail using Spatie Activity Log for all device/badge changes (both implementation files)
   - ✅ Compliance report: employees without active badges (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.6 & 1.8)
   - ✅ Device configuration change history in `device_maintenance_logs` (SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md, database schema)
   - ✅ Badge issue history in `badge_issue_logs` table (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, database schema)
   - ✅ Badge Reports: Active/Inactive/Expired/Lost badges (HR_BADGE_MANAGEMENT_IMPLEMENTATION.md, Task 1.6)

4. **Self-Service Portal (Future):**
   - ⏳ Employees can report lost badges via self-service portal
   - ⏳ Automatic deactivation request workflow
   - ⏳ Badge replacement request with HR approval
   - **Note:** Marked as future enhancement in both implementation files

---

## 🗄️ Database Schema

### **Existing Tables (from FastAPI implementation)**

These tables already exist or are planned in the FastAPI implementation:

```sql
-- rfid_devices: Device registry (scanners/readers)
CREATE TABLE rfid_devices (
    id BIGSERIAL PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL UNIQUE,
    device_name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    ip_address INET,
    mac_address MACADDR,
    device_type VARCHAR(50) DEFAULT 'reader',
    protocol VARCHAR(50) DEFAULT 'tcp',
    port INTEGER,
    is_online BOOLEAN DEFAULT FALSE,
    last_heartbeat_at TIMESTAMP,
    firmware_version VARCHAR(50),
    serial_number VARCHAR(255),
    installation_date DATE,
    maintenance_schedule VARCHAR(50),
    last_maintenance_at TIMESTAMP,
    config_json JSONB,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- rfid_card_mappings: Badge to employee mappings
CREATE TABLE rfid_card_mappings (
    id BIGSERIAL PRIMARY KEY,
    card_uid VARCHAR(255) NOT NULL UNIQUE,
    employee_id BIGINT NOT NULL,
    card_type VARCHAR(50) DEFAULT 'mifare',
    issued_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    usage_count INTEGER DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

### **New Tables (Laravel-specific tracking)**

```sql
-- device_maintenance_logs: Track device maintenance activities
CREATE TABLE device_maintenance_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'upgrade', 'replacement') NOT NULL,
    performed_by BIGINT UNSIGNED NOT NULL,
    performed_at TIMESTAMP NOT NULL,
    description TEXT,
    cost DECIMAL(10,2),
    next_maintenance_date DATE,
    status ENUM('completed', 'pending', 'failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_device_id (device_id),
    INDEX idx_performed_at (performed_at)
);

-- badge_issue_logs: Track badge issuance/replacement history
CREATE TABLE badge_issue_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    card_uid VARCHAR(255) NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    issued_by BIGINT UNSIGNED NOT NULL,
    issued_at TIMESTAMP NOT NULL,
    action_type ENUM('issued', 'replaced', 'deactivated', 'reactivated') NOT NULL,
    reason TEXT,
    previous_card_uid VARCHAR(255),
    acknowledgement_signature TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_card_uid (card_uid),
    INDEX idx_issued_at (issued_at)
);

-- device_test_logs: Track device health tests
CREATE TABLE device_test_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    tested_by BIGINT UNSIGNED NOT NULL,
    tested_at TIMESTAMP NOT NULL,
    test_type ENUM('connectivity', 'scan', 'heartbeat', 'full') NOT NULL,
    status ENUM('passed', 'failed', 'warning') NOT NULL,
    response_time_ms INT,
    error_message TEXT,
    test_results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tested_by) REFERENCES users(id),
    INDEX idx_device_id (device_id),
    INDEX idx_tested_at (tested_at)
);
```

---

## 📦 Implementation Phases

**⚠️ NOTE:** The following phases are reference documentation. See domain-separated implementation files for current implementation:
- **SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md** - System Domain (Week 1-2) - **SuperAdmin/IT ONLY**
- **HR_BADGE_MANAGEMENT_IMPLEMENTATION.md** - HR Domain (Week 3-4) - **HR Staff/Manager**

**🔒 CRITICAL: Device Management = SuperAdmin/IT (System Domain) | Badge Management = HR (HR Domain)**

---

## **PHASE 1: Device Management Frontend (Week 1) - SYSTEM DOMAIN**

**🔒 ACCESS CONTROL: SuperAdmin/IT ONLY - NOT HR**

**Goal:** Build the device management UI with mock data for device registration, configuration, and monitoring.

**Route:** `/system/timekeeping-devices`  
**Access:** SuperAdmin only (technical infrastructure management)  
**Page Location:** `resources/js/pages/System/TimekeepingDevices/Index.tsx`  
**Components:** `resources/js/components/timekeeping/device-*.tsx` (shared)  
**Implementation File:** SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md

---

### **Task 1.1: Create Device Management Layout**

**⚠️ SYSTEM DOMAIN - SuperAdmin Only**

**File:** `resources/js/pages/System/TimekeepingDevices/Index.tsx`

#### **Subtask 1.1.1: Setup Page Structure** ✅ COMPLETED
- Create main page component with Inertia page wrapper
- Setup page header with title "Device Management" and breadcrumbs
- Add action buttons: "Register New Device", "Sync with Server", "Export Report"
- Create tab navigation: "All Devices" | "Active" | "Offline" | "Maintenance"
- Implement responsive layout (grid on desktop, stack on mobile)

#### **Subtask 1.1.2: Create Device Stats Dashboard**
- Display summary cards:
  - Total Devices (count with icon)
  - Online Devices (green badge with percentage)
  - Offline Devices (red badge with count)
  - Maintenance Due (amber badge with count)
- Add quick filters: "Show Critical Only", "Last 24h Issues"
- Include refresh button with last updated timestamp

#### **Subtask 1.1.3: Create Mock Data Structure**
```typescript
interface DeviceData {
  id: string;
  deviceId: string; // e.g., "GATE-01"
  deviceName: string; // e.g., "Main Gate Entrance"
  location: string;
  deviceType: 'reader' | 'controller' | 'hybrid';
  ipAddress: string;
  macAddress: string;
  protocol: 'tcp' | 'udp' | 'http';
  port: number;
  isOnline: boolean;
  lastHeartbeat: string | null;
  firmwareVersion: string;
  serialNumber: string;
  installationDate: string;
  maintenanceSchedule: 'weekly' | 'monthly' | 'quarterly';
  lastMaintenance: string | null;
  nextMaintenance: string | null;
  uptimePercentage: number;
  scansToday: number;
  avgResponseTime: number;
  status: 'operational' | 'warning' | 'critical' | 'maintenance';
  notes?: string;
}

const mockDevices: DeviceData[] = [
  {
    id: '1',
    deviceId: 'GATE-01',
    deviceName: 'Main Gate Entrance',
    location: 'Building A - Main Gate',
    deviceType: 'reader',
    ipAddress: '192.168.1.101',
    macAddress: '00:1B:44:11:3A:B7',
    protocol: 'tcp',
    port: 8000,
    isOnline: true,
    lastHeartbeat: '2026-02-12T09:30:15',
    firmwareVersion: 'v2.3.1',
    serialNumber: 'SN-2024-001',
    installationDate: '2024-01-15',
    maintenanceSchedule: 'quarterly',
    lastMaintenance: '2025-11-10',
    nextMaintenance: '2026-02-10',
    uptimePercentage: 99.8,
    scansToday: 1247,
    avgResponseTime: 125,
    status: 'operational'
  },
  // ... 10-15 more devices
];
```

✅ **COMPLETION NOTES - SUBTASK 1.1.1: Setup Page Structure**

**Status:** ✅ COMPLETE

**Implementation Details:**
- **File Created:** `resources/js/pages/System/TimekeepingDevices/Index.tsx` (432 lines)
- **Component Type:** Inertia React functional component with TypeScript
- **Access Control:** SuperAdmin/IT only (System Domain)
- **Layout Wrapper:** Uses AppLayout (system admin layout)

**Key Features Implemented:**

1. **Page Header Section:**
   - Main title: "Device Management"
   - Subtitle: "Monitor and manage RFID scanners and readers"
   - Responsive layout (column on mobile, row on desktop)

2. **Action Buttons Section (3 buttons):**
   - ✅ "Register Device" button with Plus icon - links to registration page
   - ✅ "Sync Server" button with RefreshCw icon - triggers FastAPI sync
   - ✅ "Export" button with Download icon - exports device reports
   - Responsive: Stack on mobile (flex-col), row on desktop (flex-row)

3. **Status Dashboard (4 Stats Cards):**
   - ✅ Total Devices (count of all registered devices)
   - ✅ Online Devices (count with percentage of operational)
   - ✅ Offline Devices (count of devices requiring attention)
   - ✅ Maintenance Due (count of devices needing service)
   - Responsive Grid: 1 column on mobile, 2 on tablet (sm:), 4 on desktop (lg:)
   - Color-coded: Green for online, Red for offline, Yellow for maintenance

4. **Tab Navigation Section:**
   - ✅ Tabs implemented with Tabs component from shadcn/ui
   - ✅ Tab 1: "All" - shows all devices with total count
   - ✅ Tab 2: "Online" - shows only online/active devices with count
   - ✅ Tab 3: "Offline" - shows only offline devices with count
   - ✅ Tab 4: "Service" - shows maintenance due and maintenance status devices
   - ✅ Counts dynamically calculated from device data
   - ✅ Refresh button integrated in tab bar with loading animation

5. **Device List Content (Tab Content):**
   - ✅ Multiple empty states per tab with helpful messaging
   - ✅ DeviceRow component for each device showing:
     - Status icon (Wifi for online, WifiOff for offline, AlertTriangle for maintenance)
     - Device name (bold)
     - Location and IP:Port (secondary text)
     - Device type (reader/controller/hybrid)
     - Firmware version
     - Status badge (color-coded)
   - ✅ Hover effects and smooth transitions

6. **Responsive Layout Implementation:**
   - ✅ Mobile-first design approach
   - ✅ Breakpoints: sm: (640px), lg: (1024px)
   - ✅ Button section: Stack on mobile, row on desktop
   - ✅ Stats cards: 1 column mobile → 2 columns tablet → 4 columns desktop
   - ✅ Tab triggers responsive (tabs may scroll on very small screens)
   - ✅ Device rows: Full width with flex layout for responsive alignment

7. **Additional Features:**
   - ✅ TypeScript interfaces defined (Device, TimekeepingDevicesProps)
   - ✅ State management with useState hook (selectedTab, isRefreshing)
   - ✅ Event handlers: handleRegisterDevice, handleSyncWithServer, handleExportReport, handleRefresh
   - ✅ Helper functions: getFilteredDevices, getStatusBadge, getStatusIcon
   - ✅ Info card with help text explaining device management workflow
   - ✅ Empty state illustrations for each tab

**Component Structure:**
```
PageLayout
├── Header (Title + Description)
├── Action Buttons (Register, Sync, Export)
├── Status Dashboard (4 cards)
├── Device Management Card
│   └── Tabs Navigation
│       ├── Tab: All Devices
│       ├── Tab: Online
│       ├── Tab: Offline
│       └── Tab: Service
├── Help/Info Card
```

**Icons Used:**
- Plus (register device)
- RefreshCw (sync/refresh)
- Download (export)
- Wifi (online status)
- WifiOff (offline status)
- AlertTriangle (maintenance alert)
- CheckCircle2 (all good indicator)

**UI Components Used:**
- AppLayout (system admin header)
- Head (Inertia page title)
- Card, CardContent, CardHeader, CardTitle, CardDescription
- Tabs, TabsContent, TabsList, TabsTrigger
- Button (multiple variants: default, outline, link, ghost)
- Badge (color-coded status badges)
- All from shadcn/ui library

**Props Interface:**
```typescript
interface TimekeepingDevicesProps {
    devices: Device[];
    stats: {
        total_devices: number;
        online_devices: number;
        offline_devices: number;
        maintenance_due: number;
    };
}
```

**Device Interface:**
```typescript
interface Device {
    id: string;
    name: string;
    device_type: 'reader' | 'controller' | 'hybrid';
    status: 'online' | 'offline' | 'maintenance' | 'error';
    location: string;
    ip_address: string;
    port: number;
    last_heartbeat: string | null;
    installation_date: string;
    firmware_version: string;
    sync_status: 'synced' | 'pending' | 'failed';
    maintenance_due: boolean;
    notes?: string;
}
```

**Next Steps:**
- Subtask 1.1.2: Create Device Stats Dashboard (with charts/graphs)
- Subtask 1.1.3: Create Mock Data Structure (populate with test devices)
- Task 1.2: Create Device List/Table Component (detailed device table)

**Testing Checklist:**
- ✅ Component renders without errors
- ✅ All tabs functional and filter devices correctly
- ✅ Responsive layout works on mobile, tablet, desktop
- ✅ Status badges display correct colors
- ✅ Action buttons call appropriate handlers
- ✅ Empty states display correctly for filtered tabs
- ⏳ Backend integration (registration, sync, export endpoints) - deferred to Task 1.2+
- ⏳ Mock data population - deferred to Subtask 1.1.3

---

### **Task 1.2: Create Device List/Table Component**

**File:** `resources/js/components/timekeeping/device-management-table.tsx`

#### **Subtask 1.2.1: Build Data Table**
- Create table with columns:
  - Status indicator (colored dot)
  - Device ID & Name (bold primary, gray secondary)
  - Location
  - Type badge
  - IP Address
  - Online Status (badge with heartbeat time)
  - Scans Today
  - Uptime %
  - Actions (dropdown menu)
- Implement sorting on all columns
- Add pagination (25/50/100 per page)

#### **Subtask 1.2.2: Implement Search & Filters**
- Global search (searches ID, name, location, IP)
- Filter by status (online/offline/maintenance)
- Filter by device type (reader/controller/hybrid)
- Filter by location (dropdown with all locations)
- Date range filter for installation date
- "Clear Filters" button

#### **Subtask 1.2.3: Add Row Actions**
- Actions dropdown for each device:
  - "View Details" (opens detail modal)
  - "Test Device" (runs connectivity check)
  - "Edit Configuration" (opens edit form)
  - "Schedule Maintenance" (opens scheduling modal)
  - "View Logs" (shows activity history)
  - "Deactivate" (with confirmation)
- Color-coded row backgrounds (critical = light red, warning = light amber)
- Hover effect with smooth transition

---

### **Task 1.3: Create Device Registration Form Modal**

**File:** `resources/js/components/timekeeping/device-registration-modal.tsx`

#### **Subtask 1.3.1: Build Form Structure**
- Create multi-step modal wizard:
  - **Step 1:** Basic Information
  - **Step 2:** Network Configuration
  - **Step 3:** Maintenance Settings
  - **Step 4:** Review & Test
- Progress indicator showing current step
- "Back" and "Next" buttons with validation

#### **Subtask 1.3.2: Step 1 - Basic Information**
Form fields:
- Device ID (auto-generated with prefix, editable)
- Device Name (required, text input)
- Location (required, searchable dropdown or text)
- Device Type (required, radio buttons: Reader | Controller | Hybrid)
- Serial Number (optional, text input)
- Installation Date (date picker, defaults to today)
- Notes (optional, textarea)

#### **Subtask 1.3.3: Step 2 - Network Configuration**
Form fields:
- Protocol (required, select: TCP | UDP | HTTP | MQTT)
- IP Address (required, IP format validation)
- Port (required, number input, default 8000)
- MAC Address (optional, MAC format validation)
- Firmware Version (optional, text input)
- Connection Timeout (seconds, number input, default 30)

#### **Subtask 1.3.4: Step 3 - Maintenance Settings**
Form fields:
- Maintenance Schedule (required, select: Weekly | Monthly | Quarterly | Annually)
- Next Maintenance Date (date picker)
- Maintenance Reminder (checkbox: "Email HR Manager 1 week before")
- Maintenance Notes (textarea)

#### **Subtask 1.3.5: Step 4 - Review & Test**
- Display all entered information for review
- "Edit" buttons to go back to specific steps
- "Test Connection" button (shows loading spinner, then success/failure)
- Mock test results:
  - ✅ Device reachable at IP:Port
  - ✅ Handshake successful
  - ✅ Firmware version confirmed
  - ⚠️ Warning: Device certificate expires in 30 days
- "Register Device" button (enabled only after successful test)

#### **Subtask 1.3.6: Form Validation**
- Real-time validation with error messages
- IP address format check (regex)
- MAC address format check
- Device ID uniqueness check (against mock data)
- Required field highlighting
- Disable "Next" button if current step invalid

---

### **Task 1.4: Create Device Detail Modal**

**File:** `resources/js/components/timekeeping/device-detail-modal.tsx` (already exists, enhance it)

#### **Subtask 1.4.1: Enhance Device Detail View**
- Display all device information in sections:
  - **Overview:** Status, uptime, last heartbeat
  - **Configuration:** IP, port, protocol, firmware
  - **Statistics:** Scans today/week/month, avg response time
  - **Maintenance:** Last maintenance, next scheduled, history
  - **Location:** Map view (if coordinates available)
- Add "Edit" button (opens edit form)
- Add "Test Now" button (runs connectivity test)

#### **Subtask 1.4.2: Create Activity Timeline**
- Show recent device events:
  - Heartbeat received
  - Scan processed
  - Configuration changed
  - Maintenance performed
  - Device went offline/online
- Use timeline component with timestamps
- Filter by event type
- Pagination for history

#### **Subtask 1.4.3: Create Health Metrics Chart**
- Line chart showing device uptime over time (7 days, 30 days, 90 days)
- Bar chart showing scans per day
- Response time trend (latency over time)
- Use recharts or similar library
- Toggle between chart types

---

### **Task 1.5: Create Device Edit Form**

**File:** `resources/js/components/timekeeping/device-edit-modal.tsx`

#### **Subtask 1.5.1: Build Edit Form**
- Pre-populate all fields with current device data
- Allow editing of:
  - Device Name
  - Location
  - Network settings (IP, port, protocol)
  - Maintenance schedule
  - Notes
- Prevent editing of:
  - Device ID (show as read-only)
  - Serial Number (show as read-only)
  - Installation Date (show as read-only)

#### **Subtask 1.5.2: Implement Change Detection**
- Track which fields are modified
- Show "Unsaved Changes" indicator
- Confirm before closing if changes exist
- Highlight changed fields in yellow
- Show "Revert" button to undo changes

#### **Subtask 1.5.3: Add Configuration Test**
- "Test New Configuration" button
- Mock test before saving
- Show comparison: Current vs. New configuration
- Warning if changing IP/port (may lose connection)

---

### **Task 1.6: Create Device Maintenance Scheduler**

**File:** `resources/js/components/timekeeping/device-maintenance-modal.tsx`

#### **Subtask 1.6.1: Build Maintenance Form**
Form fields:
- Maintenance Type (radio: Routine | Repair | Upgrade | Replacement)
- Scheduled Date (date and time picker)
- Estimated Duration (hours, number input)
- Assigned Technician (searchable dropdown or text)
- Description (required, textarea)
- Parts Required (optional, textarea)
- Estimated Cost (optional, money input)

#### **Subtask 1.6.2: Create Maintenance Calendar View**
- Mini calendar showing scheduled maintenance dates
- Color-coded by maintenance type
- Click date to see scheduled maintenance
- "Today" button to jump to current date
- Month/year navigation

#### **Subtask 1.6.3: Add Maintenance Reminders**
- Checkbox: "Send email reminder"
- Reminder schedule (1 day before, 1 week before, etc.)
- List of recipients (HR Manager, assigned technician)
- SMS notification option (future feature)

---

### **Task 1.7: Create Device Health Test Component**

**File:** `resources/js/components/timekeeping/device-test-runner.tsx`

#### **Subtask 1.7.1: Build Test Runner UI**
- Test type selector (dropdown):
  - Quick Test (ping only)
  - Connectivity Test (TCP/UDP handshake)
  - Scan Test (simulate RFID scan)
  - Full Diagnostic (all tests)
- "Run Test" button
- Real-time progress indicator
- Test results display area

#### **Subtask 1.7.2: Mock Test Execution**
- Simulate test execution with delays:
  - Step 1: Pinging device... (2 seconds)
  - Step 2: Establishing connection... (3 seconds)
  - Step 3: Verifying handshake... (2 seconds)
  - Step 4: Testing scan functionality... (4 seconds)
- Show progress bar and current step
- Display success/failure for each step

#### **Subtask 1.7.3: Display Test Results**
- Result summary:
  - Overall status (Pass | Fail | Warning)
  - Individual test results
  - Response times
  - Error messages (if any)
  - Recommendations (e.g., "Consider firmware update")
- "Export Report" button (download as PDF or JSON)
- "Retest" button
- Save test log to history

---

## **PHASE 2: RFID Badge Management Frontend (Week 3) - HR DOMAIN**

**Goal:** Build the RFID badge management UI for issuing, assigning, and managing employee badges.

**Route:** `/hr/timekeeping/badges`  
**Access:** HR Staff + HR Manager  
**Implementation File:** HR_BADGE_MANAGEMENT_IMPLEMENTATION.md

---

### **Task 2.1: Create Badge Management Layout**

**File:** `resources/js/pages/HR/Timekeeping/Badges/Index.tsx`

#### **Subtask 2.1.1: Setup Page Structure**
- Create main page component with Inertia page wrapper
- Setup page header with title "RFID Badge Management"
- Add action buttons: "Issue New Badge", "Bulk Import", "Export Report"
- Create tab navigation: "Active Badges" | "Inactive" | "Unassigned" | "History"
- Implement responsive layout

#### **Subtask 2.1.2: Create Badge Stats Dashboard**
- Display summary cards:
  - Total Badges Issued (count)
  - Active Badges (percentage of employees)
  - Inactive/Lost Badges (count with alert)
  - Badges Expiring Soon (count, next 30 days)
- Add quick actions: "Report Lost Badge", "Batch Activation"
- Include sync status with FastAPI server

#### **Subtask 2.1.3: Create Mock Badge Data**
```typescript
interface BadgeData {
  id: string;
  cardUid: string; // e.g., "04:3A:B2:C5:D8"
  employeeId: string;
  employeeName: string;
  employeePhoto?: string;
  department: string;
  cardType: 'mifare' | 'desfire' | 'em4100';
  issuedAt: string;
  issuedBy: string;
  expiresAt: string | null;
  isActive: boolean;
  lastUsed: string | null;
  usageCount: number;
  status: 'active' | 'inactive' | 'lost' | 'expired' | 'replaced';
  notes?: string;
}

const mockBadges: BadgeData[] = [
  {
    id: '1',
    cardUid: '04:3A:B2:C5:D8',
    employeeId: 'EMP-2024-001',
    employeeName: 'Juan Dela Cruz',
    employeePhoto: '/avatars/juan.jpg',
    department: 'Operations',
    cardType: 'mifare',
    issuedAt: '2024-01-15T10:00:00',
    issuedBy: 'Maria Santos (HR Manager)',
    expiresAt: '2026-01-15',
    isActive: true,
    lastUsed: '2026-02-12T08:05:23',
    usageCount: 1247,
    status: 'active'
  },
  // ... 50+ badges
];
```

---

### **Task 2.2: Create Badge List/Table Component**

**File:** `resources/js/components/timekeeping/badge-management-table.tsx`

#### **Subtask 2.2.1: Build Data Table**
- Create table with columns:
  - Status indicator (colored dot)
  - Employee (photo + name)
  - Card UID (monospace font, copyable)
  - Department
  - Card Type badge
  - Issued Date
  - Expires (with warning if < 30 days)
  - Last Used (relative time)
  - Usage Count
  - Actions (dropdown)
- Implement sorting and pagination

#### **Subtask 2.2.2: Implement Search & Filters**
- Global search (employee name, card UID, employee ID)
- Filter by status (active/inactive/lost/expired)
- Filter by department
- Filter by card type
- Filter by expiration (expired, expiring soon, valid)
- "Show Only Unassigned Badges" toggle

#### **Subtask 2.2.3: Add Row Actions**
- Actions dropdown:
  - "View Badge Details"
  - "View Usage History"
  - "Deactivate Badge" (with confirmation)
  - "Replace Badge" (starts replacement workflow)
  - "Report Lost/Stolen"
  - "Extend Expiration"
  - "Print Badge Info" (QR code, employee info)

---

### **Task 2.3: Create Badge Issuance Form Modal**

**File:** `resources/js/components/timekeeping/badge-issuance-modal.tsx`

#### **Subtask 2.3.1: Build Issuance Form**
Form fields:
- **Employee Selection:**
  - Search employees (autocomplete with photo, name, ID, dept)
  - Show employee details (photo, name, department, position)
  - Indicate if employee already has active badge (warning)
  
- **Badge Information:**
  - Card UID (required, text input, format validation)
  - Card Type (select: Mifare | DESFire | EM4100)
  - Expiration Date (optional, date picker)
  - Issue Notes (textarea, e.g., "Initial issuance", "Replacement for lost badge")

- **Verification:**
  - "Test Badge Scan" button (mock scan test)
  - Checkbox: "Employee received and signed for badge"
  - Checkbox: "Badge tested successfully"

#### **Subtask 2.3.2: Implement Card UID Scanner**
- "Scan Badge" button (in Phase 1, click shows mock scan)
- Mock scan simulation:
  - Show "Hold badge near reader..." animation
  - After 2 seconds, populate Card UID with mock value
  - Display card type detected
  - Show "Scan successful" message
- Real implementation note: Will integrate with device scanner API

#### **Subtask 2.3.3: Handle Existing Badge Check**
- When employee selected, check if they have active badge
- If yes, show warning modal:
  - "Employee already has active badge: [UID]"
  - "Last used: [timestamp]"
  - Options: "Replace Existing Badge" or "Cancel"
- If "Replace", auto-deactivate old badge with reason "Replaced with [new UID]"

#### **Subtask 2.3.4: Form Validation**
- Card UID format validation (MAC address format)
- Card UID uniqueness check (not already in system)
- Employee selection required
- Test scan completion required
- Confirmation checkboxes required

---

### **Task 2.4: Create Badge Detail Modal**

**File:** `resources/js/components/timekeeping/badge-detail-modal.tsx`

#### **Subtask 2.4.1: Build Detail View**
- Display badge information:
  - **Employee Info:** Photo, name, ID, department, position
  - **Badge Info:** Card UID, type, status badge
  - **Issuance Info:** Issued by, issued date, notes
  - **Expiration:** Expiration date, days remaining (with color coding)
  - **Usage Stats:** Total scans, last used, first scan, avg scans/day
  
- Action buttons:
  - "Print Badge Sheet" (PDF with QR code, employee info)
  - "View Full History"
  - "Replace Badge"
  - "Deactivate"

#### **Subtask 2.4.2: Create Usage Timeline**
- Show recent badge scans:
  - Timestamp
  - Device/Location
  - Event type (time in/out, break, etc.)
  - Status (success/failure)
- Pagination for history (last 100 scans)
- Export to CSV option

#### **Subtask 2.4.3: Create Usage Analytics**
- Show charts:
  - Scans per day (7-day bar chart)
  - Most used devices (pie chart)
  - Peak usage times (heatmap by hour)
- Usage patterns:
  - Typical time in/out
  - Most common entry point
  - Anomalies detected

---

### **Task 2.5: Create Badge Replacement Workflow**

**File:** `resources/js/components/timekeeping/badge-replacement-modal.tsx`

#### **Subtask 2.5.1: Build Replacement Form**
- Show existing badge info (read-only):
  - Employee name
  - Current card UID
  - Issued date
  - Usage stats

- New badge information:
  - New Card UID (required, scan or manual entry)
  - Replacement Reason (required, select):
    - Lost
    - Stolen
    - Damaged
    - Malfunctioning
    - Upgrade
    - Other (with text input)
  - Additional Notes (textarea)
  
- Automatic deactivation:
  - Checkbox: "Deactivate old badge immediately" (checked by default)
  - Warning: "Old badge will no longer work"

#### **Subtask 2.5.2: Implement Replacement Confirmation**
- Review screen showing:
  - Side-by-side comparison (Old vs. New)
  - Actions to be taken:
    - ❌ Deactivate old badge [UID]
    - ✅ Activate new badge [UID]
    - 📝 Log replacement reason
    - 📧 Notify employee (optional)
- "Confirm Replacement" button

#### **Subtask 2.5.3: Handle Lost/Stolen Badges**
- If reason is "Lost" or "Stolen":
  - Show additional fields:
    - Last known scan location
    - Date lost/stolen
    - Report filed? (Yes/No)
    - Security notified? (Yes/No)
  - Create incident log entry
  - Show "Report to Security" button (future integration)

---

### **Task 2.6: Create Badge Report & Export**

**File:** `resources/js/components/timekeeping/badge-report-modal.tsx`

#### **Subtask 2.6.1: Build Report Generator**
- Report type selector:
  - Active Badges Report
  - Inactive/Lost Badges Report
  - Expiring Badges Report
  - Badge Issuance History
  - Usage Statistics Report
  - Compliance Report (employees without badges)

- Report filters:
  - Date range
  - Department
  - Status
  - Employee selection

#### **Subtask 2.6.2: Create Report Preview**
- Show report data in table format
- Summary statistics at top
- Grouping options (by department, status, date)
- Sorting options
- "Print Preview" mode

#### **Subtask 2.6.3: Implement Export Options**
- Export formats:
  - PDF (formatted report with header/footer)
  - Excel (XLSX with multiple sheets)
  - CSV (raw data)
  - JSON (for API consumption)
- Email delivery option (send to HR Manager)
- Schedule recurring reports (future feature)

---

### **Task 2.7: Create Bulk Badge Import**

**File:** `resources/js/components/timekeeping/badge-bulk-import-modal.tsx`

#### **Subtask 2.7.1: Build Import Interface**
- File upload dropzone (accepts CSV, XLSX)
- Download CSV template button
- Template format:
  ```csv
  employee_id,card_uid,card_type,expiration_date,notes
  EMP-2024-001,04:3A:B2:C5:D8,mifare,2026-12-31,Initial issuance
  EMP-2024-002,04:3A:B2:C5:D9,mifare,2026-12-31,Initial issuance
  ```
- Maximum file size warning (5MB)

#### **Subtask 2.7.2: Implement Import Validation**
- Parse uploaded file
- Validate each row:
  - Employee ID exists in system
  - Card UID format valid
  - Card UID not duplicate
  - Card type valid
  - Expiration date format valid (if provided)
- Show validation results:
  - Total rows
  - Valid rows (green)
  - Invalid rows (red, with error messages)
  - Warnings (amber, e.g., "Employee already has badge")

#### **Subtask 2.7.3: Create Import Preview & Confirmation**
- Show table of badges to be imported:
  - Employee name (resolved from ID)
  - Card UID
  - Card type
  - Status (✅ Ready | ⚠️ Warning | ❌ Error)
  - Actions (✅ Will Create | ⚠️ Will Replace)
- Select which rows to import (checkbox selection)
- "Import Selected" button
- Show progress bar during import
- Final summary: X successful, Y failed

---

## **PHASE 3: Device Management Backend (Week 2) - SYSTEM DOMAIN**

**🔒 ACCESS CONTROL: SuperAdmin/IT ONLY - NOT HR**

**Goal:** Implement backend controllers, services, and API endpoints for device management.

**Access:** SuperAdmin only (technical infrastructure management)  
**Controller:** `app/Http/Controllers/System/DeviceManagementController.php`  
**Routes File:** `routes/system.php` (NOT routes/hr.php)  
**Route Prefix:** `/system/timekeeping-devices`  
**Permissions:** `manage-system-devices`, `view-system-devices`, `test-system-devices`  
**Implementation File:** SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md

---

### **Task 3.1: Create Device Models & Migrations**

**⚠️ SYSTEM DOMAIN ONLY - SuperAdmin Access**

**File:** `app/Models/RfidDevice.php`

#### **Subtask 3.1.1: Create RfidDevice Model**
- Create Eloquent model for `rfid_devices` table
- Define fillable fields
- Add casts:
  - `is_online` → boolean
  - `config_json` → array
  - `last_heartbeat_at` → datetime
  - `installation_date` → date
- Add accessors:
  - `getStatusAttribute()` (derives status from online + heartbeat)
  - `getUptimePercentageAttribute()` (calculated from logs)
- Add relationships:
  - `hasMany(DeviceMaintenanceLog::class)`
  - `hasMany(DeviceTestLog::class)`
- Add scopes:
  - `scopeOnline($query)`
  - `scopeOffline($query)`
  - `scopeMaintenanceDue($query)`

#### **Subtask 3.1.2: Create Migration for rfid_devices**
- Create migration file: `create_rfid_devices_table`
- Define table schema (as per database schema section)
- Add indexes:
  - `device_id` (unique)
  - `is_online`
  - `last_heartbeat_at`
- Add comments for clarity

#### **Subtask 3.1.3: Create DeviceMaintenanceLog Model**
- Create model for `device_maintenance_logs` table
- Add relationships:
  - `belongsTo(RfidDevice::class, 'device_id', 'device_id')`
  - `belongsTo(User::class, 'performed_by')`
- Add scopes:
  - `scopeCompleted($query)`
  - `scopePending($query)`
  - `scopeUpcoming($query)`

#### **Subtask 3.1.4: Create Migration for device_maintenance_logs**
- Create migration file
- Define schema (as per database schema section)
- Add foreign key constraints

#### **Subtask 3.1.5: Create DeviceTestLog Model**
- Create model for `device_test_logs` table
- Add relationships:
  - `belongsTo(RfidDevice::class, 'device_id', 'device_id')`
  - `belongsTo(User::class, 'tested_by')`
- Add casts:
  - `test_results` → array
- Add scopes:
  - `scopePassed($query)`
  - `scopeFailed($query)`
  - `scopeRecent($query)`

#### **Subtask 3.1.6: Create Migration for device_test_logs**
- Create migration file
- Define schema
- Add indexes

---

### **Task 3.2: Create System DeviceManagement Controller**

**⚠️ SYSTEM DOMAIN - SuperAdmin Only**

**File:** `app/Http/Controllers/System/DeviceManagementController.php`

#### **Subtask 3.2.1: Implement index() Method**
```php
namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceManagementController extends Controller
{
    public function index(Request $request)
    {
        // SuperAdmin only - check permission
        $this->authorize('manage-system-devices');
        
        // Fetch devices with filters
        $devices = RfidDevice::query()
        ->when($request->search, fn($q, $search) => 
            $q->where('device_id', 'like', "%{$search}%")
              ->orWhere('device_name', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%")
        )
        ->when($request->status, fn($q, $status) => 
            $status === 'online' ? $q->online() : $q->offline()
        )
        ->when($request->device_type, fn($q, $type) => 
            $q->where('device_type', $type)
        )
        ->with(['maintenanceLogs' => fn($q) => $q->latest()->limit(5)])
        ->paginate($request->per_page ?? 25);
    
    // Calculate statistics
    $stats = [
        'total' => RfidDevice::count(),
        'online' => RfidDevice::online()->count(),
        'offline' => RfidDevice::offline()->count(),
        'maintenance_due' => RfidDevice::maintenanceDue()->count(),
    ];
    
    return Inertia::render('System/TimekeepingDevices/Index', [
        'devices' => $devices,
        'stats' => $stats,
        'filters' => $request->only(['search', 'status', 'device_type']),
    ]);
    }
}
```

#### **Subtask 3.2.2: Implement store() Method (Register Device)**
```php
public function store(StoreDeviceRequest $request)
{
    // SuperAdmin only
    $this->authorize('manage-system-devices');
    
    $device = RfidDevice::create([
        'device_id' => $request->device_id,
        'device_name' => $request->device_name,
        'location' => $request->location,
        'device_type' => $request->device_type,
        'ip_address' => $request->ip_address,
        'mac_address' => $request->mac_address,
        'protocol' => $request->protocol,
        'port' => $request->port,
        'firmware_version' => $request->firmware_version,
        'serial_number' => $request->serial_number,
        'installation_date' => $request->installation_date ?? now(),
        'maintenance_schedule' => $request->maintenance_schedule,
        'config_json' => $request->config_json ?? [],
        'notes' => $request->notes,
        'is_online' => false, // Default to offline until heartbeat received
    ]);
    
    // Log activity
    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->log('Device registered: ' . $device->device_name);
    
    return redirect()->route('system.timekeeping-devices.index')
        ->with('success', 'Device registered successfully');
}
```

#### **Subtask 3.2.3: Implement show() Method (Device Details)**
```php
public function show(RfidDevice $device)
{
    // SuperAdmin only
    $this->authorize('view-system-devices');
    
    $device->load([
        'maintenanceLogs' => fn($q) => $q->latest()->limit(20),
        'testLogs' => fn($q) => $q->latest()->limit(50),
    ]);
    
    // Calculate uptime percentage
    $uptimeData = $this->calculateUptime($device);
    
    // Get scan statistics
    $scanStats = $this->getScanStatistics($device);
    
    return Inertia::render('System/TimekeepingDevices/Show', [
        'device' => $device,
        'uptimeData' => $uptimeData,
        'scanStats' => $scanStats,
    ]);
}
```

#### **Subtask 3.2.4: Implement update() Method**
```php
public function update(UpdateDeviceRequest $request, RfidDevice $device)
{
    // SuperAdmin only
    $this->authorize('manage-system-devices');
    
    $changes = $device->getDirty();
    
    $device->update($request->validated());
    
    // Log changes
    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->withProperties(['changes' => $changes])
        ->log('Device configuration updated');
    
    return redirect()->back()
        ->with('success', 'Device updated successfully');
}
```

#### **Subtask 3.2.5: Implement destroy() Method (Deactivate)**
```php
public function destroy(RfidDevice $device)
{
    // SuperAdmin only
    $this->authorize('manage-system-devices');
    
    $device->update(['is_online' => false]);
    $device->delete(); // Soft delete
    
    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->log('Device deactivated: ' . $device->device_name);
    
    return redirect()->back()
        ->with('success', 'Device deactivated successfully');
}
```

---

### **Task 3.3: Create Device Test Service**

**File:** `app/Services/Timekeeping/DeviceTestService.php`

#### **Subtask 3.3.1: Implement testDevice() Method**
```php
public function testDevice(RfidDevice $device, string $testType = 'full'): array
{
    $results = [];
    
    try {
        // Test 1: Ping device
        $results['ping'] = $this->pingDevice($device);
        
        if ($testType === 'quick') {
            return $this->formatResults($results);
        }
        
        // Test 2: TCP/UDP connection
        $results['connection'] = $this->testConnection($device);
        
        // Test 3: Handshake
        $results['handshake'] = $this->testHandshake($device);
        
        if ($testType === 'connectivity') {
            return $this->formatResults($results);
        }
        
        // Test 4: Scan simulation (if full test)
        if ($testType === 'full') {
            $results['scan'] = $this->testScanFunctionality($device);
        }
        
        // Log test
        DeviceTestLog::create([
            'device_id' => $device->device_id,
            'tested_by' => auth()->id(),
            'tested_at' => now(),
            'test_type' => $testType,
            'status' => $this->determineOverallStatus($results),
            'test_results' => $results,
        ]);
        
        return $this->formatResults($results);
        
    } catch (\Exception $e) {
        Log::error('Device test failed', [
            'device_id' => $device->device_id,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

#### **Subtask 3.3.2: Implement Connection Test Methods**
```php
protected function pingDevice(RfidDevice $device): array
{
    $start = microtime(true);
    
    // Use PHP sockets to ping device
    $socket = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 5);
    
    $responseTime = (microtime(true) - $start) * 1000; // Convert to ms
    
    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'response_time_ms' => round($responseTime, 2),
            'message' => 'Device reachable',
        ];
    }
    
    return [
        'success' => false,
        'error' => "Connection failed: {$errstr} ({$errno})",
    ];
}

protected function testConnection(RfidDevice $device): array
{
    // Implement protocol-specific connection test
    switch ($device->protocol) {
        case 'tcp':
            return $this->testTcpConnection($device);
        case 'udp':
            return $this->testUdpConnection($device);
        case 'http':
            return $this->testHttpConnection($device);
        default:
            return ['success' => false, 'error' => 'Unsupported protocol'];
    }
}

protected function testHandshake(RfidDevice $device): array
{
    // Implement device-specific handshake protocol
    // For now, return mock success
    return [
        'success' => true,
        'message' => 'Handshake successful',
        'firmware_version' => $device->firmware_version,
    ];
}

protected function testScanFunctionality(RfidDevice $device): array
{
    // Simulate sending test scan command
    // In real implementation, this would send actual RFID scan command
    return [
        'success' => true,
        'message' => 'Scan test successful',
        'test_card_uid' => '00:00:00:00:00:00',
    ];
}
```

---

### **Task 3.4: Create Device Maintenance Service**

**File:** `app/Services/Timekeeping/DeviceMaintenanceService.php`

#### **Subtask 3.4.1: Implement scheduleMaintenance() Method**
```php
public function scheduleMaintenance(array $data): DeviceMaintenanceLog
{
    $maintenance = DeviceMaintenanceLog::create([
        'device_id' => $data['device_id'],
        'maintenance_type' => $data['maintenance_type'],
        'performed_at' => $data['scheduled_date'],
        'performed_by' => auth()->id(),
        'description' => $data['description'],
        'cost' => $data['cost'] ?? null,
        'next_maintenance_date' => $this->calculateNextMaintenanceDate(
            $data['scheduled_date'],
            $data['device']->maintenance_schedule
        ),
        'status' => 'pending',
    ]);
    
    // Send reminder notification (if enabled)
    if ($data['send_reminder'] ?? false) {
        $this->scheduleMaintenanceReminder($maintenance, $data['reminder_date']);
    }
    
    return $maintenance;
}
```

#### **Subtask 3.4.2: Implement completeMaintenance() Method**
```php
public function completeMaintenance(DeviceMaintenanceLog $maintenance, array $data): void
{
    $maintenance->update([
        'status' => 'completed',
        'performed_at' => now(),
        'description' => $data['notes'] ?? $maintenance->description,
        'cost' => $data['actual_cost'] ?? $maintenance->cost,
        'next_maintenance_date' => $this->calculateNextMaintenanceDate(
            now(),
            $maintenance->device->maintenance_schedule
        ),
    ]);
    
    // Update device last maintenance
    $maintenance->device->update([
        'last_maintenance_at' => now(),
    ]);
    
    // Log activity
    activity()
        ->causedBy(auth()->user())
        ->performedOn($maintenance->device)
        ->log('Maintenance completed');
}
```

#### **Subtask 3.4.3: Implement getMaintenanceDue() Method**
```php
public function getMaintenanceDue(): Collection
{
    return RfidDevice::whereNotNull('last_maintenance_at')
        ->whereRaw('DATE_ADD(last_maintenance_at, INTERVAL 
            CASE maintenance_schedule
                WHEN "weekly" THEN 7
                WHEN "monthly" THEN 30
                WHEN "quarterly" THEN 90
                WHEN "annually" THEN 365
            END DAY) <= DATE_ADD(NOW(), INTERVAL 7 DAY)')
        ->with('maintenanceLogs')
        ->get();
}
```

---

### **Task 3.5: Create Form Request Validators**

**⚠️ SYSTEM DOMAIN - SuperAdmin Only**

#### **Subtask 3.5.1: Create StoreDeviceRequest**
**File:** `app/Http/Requests/System/StoreDeviceRequest.php`
```php
public function rules(): array
{
    return [
        'device_id' => ['required', 'string', 'max:255', 'unique:rfid_devices,device_id'],
        'device_name' => ['required', 'string', 'max:255'],
        'location' => ['required', 'string', 'max:255'],
        'device_type' => ['required', 'in:reader,controller,hybrid'],
        'protocol' => ['required', 'in:tcp,udp,http,mqtt'],
        'ip_address' => ['required', 'ip'],
        'port' => ['required', 'integer', 'min:1', 'max:65535'],
        'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
        'firmware_version' => ['nullable', 'string', 'max:50'],
        'serial_number' => ['nullable', 'string', 'max:255'],
        'installation_date' => ['nullable', 'date'],
        'maintenance_schedule' => ['required', 'in:weekly,monthly,quarterly,annually'],
        'config_json' => ['nullable', 'array'],
        'notes' => ['nullable', 'string'],
    ];
}
```

#### **Subtask 3.5.2: Create UpdateDeviceRequest**
**File:** `app/Http/Requests/System/UpdateDeviceRequest.php`
```php
public function rules(): array
{
    return [
        'device_name' => ['sometimes', 'required', 'string', 'max:255'],
        'location' => ['sometimes', 'required', 'string', 'max:255'],
        'protocol' => ['sometimes', 'required', 'in:tcp,udp,http,mqtt'],
        'ip_address' => ['sometimes', 'required', 'ip'],
        'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
        'maintenance_schedule' => ['sometimes', 'required', 'in:weekly,monthly,quarterly,annually'],
        'notes' => ['nullable', 'string'],
    ];
}
```

---

### **Task 3.6: Create System Domain Routes**

**File:** `routes/system.php` ⚠️ **SYSTEM DOMAIN - SuperAdmin Only**

#### **Subtask 3.6.1: Add Device Management Routes (System Domain)**
```php
use App\Http\Controllers\System\DeviceManagementController;
use App\Http\Controllers\System\DeviceMaintenanceController;

// SYSTEM DOMAIN: Device Management Routes (SuperAdmin Only)
Route::prefix('timekeeping-devices')->name('timekeeping-devices.')->group(function () {
    // Device Management Page
    Route::get('/', [DeviceManagementController::class, 'index'])
        ->name('index')
        ->can('manage-system-devices');
    
    // Device CRUD
    Route::post('/', [DeviceManagementController::class, 'store'])
        ->name('store')
        ->can('manage-system-devices');
    
    Route::get('/{device}', [DeviceManagementController::class, 'show'])
        ->name('show')
        ->can('view-system-devices');
    
    Route::put('/{device}', [DeviceManagementController::class, 'update'])
        ->name('update')
        ->can('manage-system-devices');
    
    Route::delete('/{device}', [DeviceManagementController::class, 'destroy'])
        ->name('destroy')
        ->can('manage-system-devices');
    
    // Device Testing (SuperAdmin only)
    Route::post('/{device}/test', [DeviceManagementController::class, 'test'])
        ->name('test')
        ->can('test-system-devices');
    
    // Maintenance (SuperAdmin only)
    Route::post('/{device}/maintenance', [DeviceMaintenanceController::class, 'schedule'])
        ->name('maintenance.schedule')
        ->can('manage-system-devices');
    
    Route::put('/maintenance/{maintenance}', [DeviceMaintenanceController::class, 'complete'])
        ->name('maintenance.complete')
        ->can('manage-system-devices');
    
    Route::get('/maintenance/due', [DeviceMaintenanceController::class, 'due'])
        ->name('maintenance.due')
        ->can('view-system-devices');
});
```

---

## **PHASE 4: Badge Management Backend (Week 4) - HR DOMAIN**

**Goal:** Implement backend for RFID badge management.

**Access:** HR Staff + HR Manager  
**Implementation File:** HR_BADGE_MANAGEMENT_IMPLEMENTATION.md

---

### **Task 4.1: Create Badge Models & Migrations**

**File:** `app/Models/RfidCardMapping.php`

#### **Subtask 4.1.1: Create RfidCardMapping Model**
- Create Eloquent model for `rfid_card_mappings` table
- Define fillable fields
- Add casts:
  - `is_active` → boolean
  - `issued_at` → datetime
  - `expires_at` → datetime
  - `last_used_at` → datetime
- Add relationships:
  - `belongsTo(Employee::class, 'employee_id')`
  - `hasMany(BadgeIssueLog::class, 'card_uid', 'card_uid')`
- Add scopes:
  - `scopeActive($query)`
  - `scopeInactive($query)`
  - `scopeExpired($query)`
  - `scopeExpiringSoon($query, $days = 30)`
- Add accessors:
  - `getStatusAttribute()` (active/inactive/expired/lost)
  - `getDaysUntilExpirationAttribute()`

#### **Subtask 4.1.2: Create Migration for rfid_card_mappings**
- Create migration (if not exists from FastAPI implementation)
- Add indexes
- Add unique constraint on (employee_id, is_active)

#### **Subtask 4.1.3: Create BadgeIssueLog Model**
- Create model for `badge_issue_logs` table
- Add relationships:
  - `belongsTo(Employee::class, 'employee_id')`
  - `belongsTo(User::class, 'issued_by')`
- Add casts:
  - `issued_at` → datetime
- Add scopes for action types

#### **Subtask 4.1.4: Create Migration for badge_issue_logs**
- Create migration
- Add indexes
- Add foreign key constraints

---

### **Task 4.2: Create RfidBadgeController**

**File:** `app/Http/Controllers/HR/Timekeeping/RfidBadgeController.php`

#### **Subtask 4.2.1: Implement index() Method**
```php
public function index(Request $request)
{
    $badges = RfidCardMapping::query()
        ->with(['employee.department'])
        ->when($request->search, function($q, $search) {
            $q->where('card_uid', 'like', "%{$search}%")
              ->orWhereHas('employee', fn($q) => 
                  $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
              );
        })
        ->when($request->status, function($q, $status) {
            switch($status) {
                case 'active':
                    $q->active();
                    break;
                case 'inactive':
                    $q->inactive();
                    break;
                case 'expired':
                    $q->expired();
                    break;
                case 'expiring_soon':
                    $q->expiringSoon(30);
                    break;
            }
        })
        ->when($request->department, fn($q, $dept) => 
            $q->whereHas('employee', fn($q) => $q->where('department_id', $dept))
        )
        ->paginate($request->per_page ?? 25);
    
    $stats = [
        'total' => RfidCardMapping::count(),
        'active' => RfidCardMapping::active()->count(),
        'inactive' => RfidCardMapping::inactive()->count(),
        'expiring_soon' => RfidCardMapping::expiringSoon(30)->count(),
    ];
    
    return Inertia::render('HR/Timekeeping/Badges/Index', [
        'badges' => $badges,
        'stats' => $stats,
        'filters' => $request->only(['search', 'status', 'department']),
    ]);
}
```

#### **Subtask 4.2.2: Implement store() Method (Issue Badge)**
```php
public function store(StoreBadgeRequest $request)
{
    DB::beginTransaction();
    try {
        // Check if employee has active badge
        $existingBadge = RfidCardMapping::where('employee_id', $request->employee_id)
            ->active()
            ->first();
        
        if ($existingBadge && !$request->replace_existing) {
            return back()->withErrors([
                'employee_id' => 'Employee already has an active badge'
            ]);
        }
        
        // Deactivate existing badge if replacing
        if ($existingBadge && $request->replace_existing) {
            $existingBadge->update(['is_active' => false]);
            
            BadgeIssueLog::create([
                'card_uid' => $existingBadge->card_uid,
                'employee_id' => $request->employee_id,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'action_type' => 'deactivated',
                'reason' => 'Replaced with new badge',
                'previous_card_uid' => null,
            ]);
        }
        
        // Create new badge
        $badge = RfidCardMapping::create([
            'card_uid' => $request->card_uid,
            'employee_id' => $request->employee_id,
            'card_type' => $request->card_type,
            'issued_at' => now(),
            'expires_at' => $request->expires_at,
            'is_active' => true,
            'notes' => $request->notes,
        ]);
        
        // Log issuance
        BadgeIssueLog::create([
            'card_uid' => $badge->card_uid,
            'employee_id' => $request->employee_id,
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'action_type' => $existingBadge ? 'replaced' : 'issued',
            'reason' => $request->notes,
            'previous_card_uid' => $existingBadge?->card_uid,
            'acknowledgement_signature' => $request->acknowledgement_signature,
        ]);
        
        // Activity log
        activity()
            ->causedBy(auth()->user())
            ->performedOn($badge)
            ->log('RFID badge issued to ' . $badge->employee->full_name);
        
        DB::commit();
        
        return redirect()->route('hr.timekeeping.badges.index')
            ->with('success', 'Badge issued successfully');
            
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Badge issuance failed', ['error' => $e->getMessage()]);
        return back()->withErrors(['error' => 'Failed to issue badge']);
    }
}
```

#### **Subtask 4.2.3: Implement show() Method (Badge Details)**
```php
public function show(RfidCardMapping $badge)
{
    $badge->load([
        'employee.department',
        'issueLogs' => fn($q) => $q->latest()->limit(20),
    ]);
    
    // Get usage statistics from rfid_ledger
    $usageStats = DB::table('rfid_ledger')
        ->where('employee_rfid', $badge->card_uid)
        ->selectRaw('
            COUNT(*) as total_scans,
            MIN(scan_timestamp) as first_scan,
            MAX(scan_timestamp) as last_scan,
            COUNT(DISTINCT DATE(scan_timestamp)) as days_used
        ')
        ->first();
    
    return Inertia::render('HR/Timekeeping/Badges/Show', [
        'badge' => $badge,
        'usageStats' => $usageStats,
    ]);
}
```

#### **Subtask 4.2.4: Implement deactivate() Method**
```php
public function deactivate(Request $request, RfidCardMapping $badge)
{
    $request->validate([
        'reason' => ['required', 'string', 'max:500'],
    ]);
    
    $badge->update(['is_active' => false]);
    
    // Log deactivation
    BadgeIssueLog::create([
        'card_uid' => $badge->card_uid,
        'employee_id' => $badge->employee_id,
        'issued_by' => auth()->id(),
        'issued_at' => now(),
        'action_type' => 'deactivated',
        'reason' => $request->reason,
    ]);
    
    activity()
        ->causedBy(auth()->user())
        ->performedOn($badge)
        ->log('Badge deactivated: ' . $request->reason);
    
    return redirect()->back()
        ->with('success', 'Badge deactivated successfully');
}
```

---

### **Task 4.3: Create Badge Service**

**File:** `app/Services/Timekeeping/BadgeService.php`

#### **Subtask 4.3.1: Implement replaceBadge() Method**
```php
public function replaceBadge(RfidCardMapping $oldBadge, array $newBadgeData): RfidCardMapping
{
    DB::beginTransaction();
    try {
        // Deactivate old badge
        $oldBadge->update(['is_active' => false]);
        
        // Create new badge
        $newBadge = RfidCardMapping::create([
            'card_uid' => $newBadgeData['card_uid'],
            'employee_id' => $oldBadge->employee_id,
            'card_type' => $newBadgeData['card_type'] ?? $oldBadge->card_type,
            'issued_at' => now(),
            'expires_at' => $newBadgeData['expires_at'] ?? $oldBadge->expires_at,
            'is_active' => true,
            'notes' => $newBadgeData['notes'] ?? null,
        ]);
        
        // Log replacement
        BadgeIssueLog::create([
            'card_uid' => $newBadge->card_uid,
            'employee_id' => $oldBadge->employee_id,
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'action_type' => 'replaced',
            'reason' => $newBadgeData['reason'],
            'previous_card_uid' => $oldBadge->card_uid,
        ]);
        
        DB::commit();
        return $newBadge;
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

#### **Subtask 4.3.2: Implement getBadgeUsageHistory() Method**
```php
public function getBadgeUsageHistory(RfidCardMapping $badge, int $limit = 100): Collection
{
    return DB::table('rfid_ledger')
        ->where('employee_rfid', $badge->card_uid)
        ->join('rfid_devices', 'rfid_ledger.device_id', '=', 'rfid_devices.device_id')
        ->select([
            'rfid_ledger.*',
            'rfid_devices.device_name as device_name',
            'rfid_devices.location as device_location',
        ])
        ->orderBy('scan_timestamp', 'desc')
        ->limit($limit)
        ->get();
}
```

#### **Subtask 4.3.3: Implement getEmployeesWithoutBadges() Method**
```php
public function getEmployeesWithoutBadges(): Collection
{
    return Employee::whereNotExists(function($query) {
            $query->select(DB::raw(1))
                ->from('rfid_card_mappings')
                ->whereColumn('rfid_card_mappings.employee_id', 'employees.id')
                ->where('is_active', true);
        })
        ->where('status', 'active') // Only active employees
        ->with('department')
        ->get();
}
```

---

### **Task 4.4: Implement Bulk Badge Import**

**File:** `app/Services/Timekeeping/BadgeBulkImportService.php`

#### **Subtask 4.4.1: Implement parseImportFile() Method**
```php
public function parseImportFile(UploadedFile $file): array
{
    $extension = $file->getClientOriginalExtension();
    
    if ($extension === 'csv') {
        return $this->parseCsvFile($file);
    } elseif (in_array($extension, ['xlsx', 'xls'])) {
        return $this->parseExcelFile($file);
    }
    
    throw new \InvalidArgumentException('Unsupported file format');
}

protected function parseCsvFile(UploadedFile $file): array
{
    $data = [];
    $headers = null;
    
    if (($handle = fopen($file->getPathname(), 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (!$headers) {
                $headers = $row;
                continue;
            }
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    
    return $data;
}
```

#### **Subtask 4.4.2: Implement validateImportData() Method**
```php
public function validateImportData(array $data): array
{
    $results = [
        'valid' => [],
        'invalid' => [],
        'warnings' => [],
    ];
    
    foreach ($data as $index => $row) {
        $errors = [];
        $warnings = [];
        
        // Validate employee_id
        $employee = Employee::where('employee_id', $row['employee_id'])->first();
        if (!$employee) {
            $errors[] = 'Employee not found';
        }
        
        // Validate card_uid
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $row['card_uid'])) {
            $errors[] = 'Invalid card UID format';
        }
        
        // Check for duplicates
        if (RfidCardMapping::where('card_uid', $row['card_uid'])->exists()) {
            $errors[] = 'Card UID already exists';
        }
        
        // Check if employee already has badge
        if ($employee && RfidCardMapping::where('employee_id', $employee->id)->active()->exists()) {
            $warnings[] = 'Employee already has active badge (will be replaced)';
        }
        
        // Categorize result
        if (!empty($errors)) {
            $results['invalid'][] = [
                'row' => $index + 2, // +2 for header and 0-index
                'data' => $row,
                'errors' => $errors,
            ];
        } else {
            $results['valid'][] = [
                'row' => $index + 2,
                'data' => $row,
                'warnings' => $warnings,
                'employee' => $employee,
            ];
        }
    }
    
    return $results;
}
```

#### **Subtask 4.4.3: Implement importBadges() Method**
```php
public function importBadges(array $validatedData): array
{
    $successful = 0;
    $failed = 0;
    $errors = [];
    
    DB::beginTransaction();
    try {
        foreach ($validatedData as $item) {
            try {
                $row = $item['data'];
                $employee = $item['employee'];
                
                // Deactivate existing badge if any
                RfidCardMapping::where('employee_id', $employee->id)
                    ->active()
                    ->update(['is_active' => false]);
                
                // Create new badge
                RfidCardMapping::create([
                    'card_uid' => $row['card_uid'],
                    'employee_id' => $employee->id,
                    'card_type' => $row['card_type'] ?? 'mifare',
                    'issued_at' => now(),
                    'expires_at' => $row['expiration_date'] ?? null,
                    'is_active' => true,
                    'notes' => $row['notes'] ?? 'Bulk import',
                ]);
                
                $successful++;
                
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'row' => $item['row'],
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        DB::commit();
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    
    return [
        'successful' => $successful,
        'failed' => $failed,
        'errors' => $errors,
    ];
}
```

---

### **Task 4.5: Create API Routes for Badges**

**File:** `routes/hr.php`

#### **Subtask 4.5.1: Add Badge Management Routes**
```php
// Badge Management Routes
Route::prefix('timekeeping/badges')->name('timekeeping.badges.')->group(function () {
    // Badge Management Page
    Route::get('/', [RfidBadgeController::class, 'index'])
        ->name('index')
        ->can('view-badges');
    
    // Badge CRUD
    Route::post('/', [RfidBadgeController::class, 'store'])
        ->name('store')
        ->can('issue-badges');
    
    Route::get('/{badge}', [RfidBadgeController::class, 'show'])
        ->name('show')
        ->can('view-badges');
    
    Route::post('/{badge}/deactivate', [RfidBadgeController::class, 'deactivate'])
        ->name('deactivate')
        ->can('issue-badges');
    
    Route::post('/{badge}/replace', [RfidBadgeController::class, 'replace'])
        ->name('replace')
        ->can('issue-badges');
    
    // Usage history
    Route::get('/{badge}/history', [RfidBadgeController::class, 'history'])
        ->name('history')
        ->can('view-badges');
    
    // Bulk import
    Route::post('/bulk-import', [RfidBadgeController::class, 'bulkImport'])
        ->name('bulk-import')
        ->can('issue-badges');
    
    Route::post('/bulk-import/validate', [RfidBadgeController::class, 'validateImport'])
        ->name('bulk-import.validate')
        ->can('issue-badges');
    
    // Reports
    Route::get('/employees-without-badges', [RfidBadgeController::class, 'employeesWithoutBadges'])
        ->name('employees-without-badges')
        ->can('view-badges');
});
```

---

### **Task 4.6: Create Form Request Validators for Badges**

#### **Subtask 4.6.1: Create StoreBadgeRequest**
**File:** `app/Http/Requests/Timekeeping/StoreBadgeRequest.php`
```php
public function rules(): array
{
    return [
        'employee_id' => ['required', 'exists:employees,id'],
        'card_uid' => [
            'required',
            'string',
            'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'unique:rfid_card_mappings,card_uid',
        ],
        'card_type' => ['required', 'in:mifare,desfire,em4100'],
        'expires_at' => ['nullable', 'date', 'after:today'],
        'notes' => ['nullable', 'string', 'max:500'],
        'replace_existing' => ['nullable', 'boolean'],
        'acknowledgement_signature' => ['nullable', 'string'],
    ];
}
```

---

## **PHASE 5: Testing & Integration (Week 4)**

**Goal:** Test all features and integrate with existing timekeeping module.

---

### **Task 5.1: Create Unit Tests**

#### **Subtask 5.1.1: Test DeviceManagementController**
**File:** `tests/Unit/Controllers/DeviceManagementControllerTest.php`
- Test device registration
- Test device update
- Test device deactivation
- Test device search and filtering

#### **Subtask 5.1.2: Test DeviceTestService**
**File:** `tests/Unit/Services/DeviceTestServiceTest.php`
- Test ping functionality
- Test connection tests
- Test full diagnostic

#### **Subtask 5.1.3: Test RfidBadgeController**
**File:** `tests/Unit/Controllers/RfidBadgeControllerTest.php`
- Test badge issuance
- Test badge replacement
- Test badge deactivation
- Test bulk import validation

---

### **Task 5.2: Create Feature Tests**

#### **Subtask 5.2.1: Test Device Management Workflow**
**File:** `tests/Feature/DeviceManagementTest.php`
- Test complete device registration flow
- Test device maintenance scheduling
- Test device health checks

#### **Subtask 5.2.2: Test Badge Management Workflow**
**File:** `tests/Feature/BadgeManagementTest.php`
- Test badge issuance workflow
- Test badge replacement workflow
- Test bulk badge import

---

### **Task 5.3: Create UI/Integration Tests**

#### **Subtask 5.3.1: Test Device Management UI**
- Test device registration form
- Test device list/table
- Test device detail modal
- Test maintenance scheduler

#### **Subtask 5.3.2: Test Badge Management UI**
- Test badge issuance form
- Test badge replacement workflow
- Test bulk import UI

---

### **Task 5.4: Update Documentation**

#### **Subtask 5.4.1: Update TIMEKEEPING_MODULE_STATUS_REPORT.md**
- Mark Device Management and Badge Management pages as complete
- Update progress percentages
- Add screenshots

#### **Subtask 5.4.2: Create User Guide**
**File:** `docs/workflows/guides/DEVICE_BADGE_MANAGEMENT_GUIDE.md`
- How to register a device
- How to issue a badge
- How to handle badge replacements
- How to schedule maintenance

#### **Subtask 5.4.3: Update API Documentation**
- Document all new endpoints
- Add request/response examples
- Add authentication requirements

---

## 📊 Implementation Checklist

**✅ ALL SUGGESTIONS IMPLEMENTED** - See domain-separated implementation files for detailed checklists

### **System Domain: Device Management** (Week 1-2)
**File:** [SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md](./SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md)  
**Route:** `/system/timekeeping-devices`  
**Access:** SuperAdmin only

- [ ] Device Management Layout with stats dashboard ✅ Implemented
- [ ] Device List/Table Component with filters ✅ Implemented
- [ ] Multi-step Device Registration Wizard ✅ Implemented
- [ ] Device Detail Modal with health metrics ✅ Implemented
- [ ] Device Edit Form with change detection ✅ Implemented
- [ ] Device Maintenance Scheduler with calendar ✅ Implemented
- [ ] Device Health Test Runner (connectivity/scan/full diagnostic) ✅ Implemented
- [ ] Device Models & Migrations (rfid_devices, device_maintenance_logs, device_test_logs) ✅ Implemented
- [ ] System\DeviceManagementController with CRUD methods ✅ Implemented
- [ ] DeviceTestService with network testing ✅ Implemented
- [ ] Form Request Validators (StoreDeviceRequest, UpdateDeviceRequest) ✅ Implemented
- [ ] System domain routes (/system/timekeeping-devices/*) ✅ Implemented
- [ ] Permission seeder (manage-system-devices, test-system-devices) ✅ Implemented

### **HR Domain: Badge Management** (Week 3-4)
**File:** [HR_BADGE_MANAGEMENT_IMPLEMENTATION.md](./HR_BADGE_MANAGEMENT_IMPLEMENTATION.md)  
**Route:** `/hr/timekeeping/badges`  
**Access:** HR Staff + HR Manager

- [ ] Badge Management Layout with stats dashboard ✅ Implemented
- [ ] Badge List/Table Component with comprehensive filters ✅ Implemented
- [ ] Badge Issuance Form with card UID scanner ✅ Implemented
- [ ] Badge Detail Modal with usage analytics ✅ Implemented
- [ ] 3-Step Badge Replacement Workflow (Reason → Scan → Confirm) ✅ Implemented
- [ ] Lost/Stolen Badge Handling with incident reporting ✅ Implemented
- [ ] Badge Report & Export (PDF/Excel/CSV) ✅ Implemented
- [ ] Bulk Badge Import with validation preview ✅ Implemented
- [ ] Employees Without Badges Widget for compliance ✅ Implemented
- [ ] Badge Models & Migrations (rfid_card_mappings, badge_issue_logs) ✅ Implemented
- [ ] RfidBadgeController with CRUD methods ✅ Implemented
- [ ] BadgeBulkImportService with CSV/Excel parsing ✅ Implemented
- [ ] Form Request Validators (StoreBadgeRequest, ReplaceBadgeRequest) ✅ Implemented
- [ ] HR domain routes (/hr/timekeeping/badges/*) ✅ Implemented
- [ ] Permission seeder (view-badges, manage-badges, bulk-import-badges) ✅ Implemented

### **Audit & Compliance Features** (Both Domains)
- [ ] Spatie Activity Log integration for all changes ✅ Implemented
- [ ] Device configuration change history ✅ Implemented
- [ ] Badge issue/replacement history logging ✅ Implemented
- [ ] Compliance reports (employees without badges) ✅ Implemented
- [ ] Badge usage analytics (scans per day, peak hours) ✅ Implemented
- [ ] Device health monitoring with uptime tracking ✅ Implemented

### **Phase 5: Testing & Integration** (Week 4)
- [ ] Unit Tests for controllers and services
- [ ] Feature Tests for workflows
- [ ] UI/Integration Tests
- [ ] Documentation Updates
- [ ] Permission smoke tests

---

## 🔐 Permissions Required

### **System Domain Permissions** (SuperAdmin)
```php
// Device Management Permissions - System Domain
'view-system-devices' => 'View System RFID Devices',
'manage-system-devices' => 'Manage System RFID Devices (Register, Configure, Deactivate)',
'test-system-devices' => 'Test System RFID Devices',
'schedule-device-maintenance' => 'Schedule Device Maintenance',
```

### **HR Domain Permissions** (HR Staff + HR Manager)
```php
// Badge Management Permissions - HR Domain
'view-badges' => 'View RFID Badges',
'manage-badges' => 'Manage RFID Badges (Issue, Replace, Deactivate)',
'bulk-import-badges' => 'Bulk Import RFID Badges',
'view-badge-reports' => 'View Badge Reports and Analytics',
```

**Role Assignment:**
- **SuperAdmin:** All System domain permissions (manage-system-devices, test-system-devices)
- **HR Manager:** All HR domain permissions (view-badges, manage-badges, bulk-import-badges, view-badge-reports)
- **HR Staff:** Limited HR permissions (view-badges, manage-badges)
- **Employee:** None (self-service portal planned as future enhancement)

---

## 📈 Success Metrics

**Device Management:**
- 100% of physical RFID devices registered in system
- < 5 minutes average device registration time
- Device health checks automated (hourly)
- Maintenance schedules tracked and followed

**Badge Management:**
- 100% of active employees have badges
- < 2 minutes average badge issuance time
- Lost badge replacement < 24 hours
- Badge usage analytics available for all users

**System Performance:**
- Device management pages load < 2 seconds
- Badge issuance completes < 1 second
- Bulk import (100 badges) completes < 30 seconds
- Export reports generation < 5 seconds

---

## 🚀 Future Enhancements

1. **Mobile Device Registration:**
   - Mobile app for on-site device registration
   - QR code scanning for device setup

2. **Self-Service Badge Portal:**
   - Employees can report lost badges
   - Automatic deactivation workflow
   - Badge replacement requests

3. **Advanced Analytics:**
   - Device performance trends
   - Badge usage patterns
   - Predictive maintenance alerts

4. **Integration with Access Control:**
   - Sync badges with door access systems
   - Real-time access logs
   - Security integration

---

## 🔄 Migration Guide

**This file has been superseded by domain-separated implementation files:**

### **For Device Management (System Domain):**
See: [SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md](./SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md)
- Route: `/system/timekeeping-devices`
- Access: SuperAdmin only
- Technical infrastructure focus
- Device registration, testing, maintenance

### **For Badge Management (HR Domain):**
See: [HR_BADGE_MANAGEMENT_IMPLEMENTATION.md](./HR_BADGE_MANAGEMENT_IMPLEMENTATION.md)
- Route: `/hr/timekeeping/badges`
- Access: HR Staff + HR Manager
- Employee operations focus
- Badge issuance, replacement, compliance

**All suggestions from this file have been implemented in the domain-separated files with enhanced features including:**
- ✅ Badge Lifecycle Management (expiration tracking, lost/stolen reporting, usage analytics)
- ✅ Device Health Dashboard (real-time monitoring, uptime charts, maintenance reminders)
- ✅ Audit & Compliance (full audit trail, compliance reports, change history)
- ✅ Bulk Operations (bulk badge import with validation)
- ✅ Domain Separation (System vs HR with proper access control)

---

**Document Version:** 2.0 (Superseded)  
**Last Updated:** February 12, 2026  
**Status:** ✅ All Suggestions Implemented in Domain-Separated Files  
**Migration:** Use SYSTEM_DEVICE_MANAGEMENT_IMPLEMENTATION.md and HR_BADGE_MANAGEMENT_IMPLEMENTATION.md
