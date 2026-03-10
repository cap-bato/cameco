# System - RFID Device Management Implementation

**Issue Type:** Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 2 weeks  
**Target Users:** SuperAdmin  
**Domain:** System (Infrastructure/Configuration)  
**Dependencies:** PostgreSQL Database, RFID Hardware  
**Related Documents:**
- [HR_BADGE_MANAGEMENT_IMPLEMENTATION.md](./HR_BADGE_MANAGEMENT_IMPLEMENTATION.md) - Badge Management (HR Domain)
- [FASTAPI_RFID_SERVER_IMPLEMENTATION.md](./FASTAPI_RFID_SERVER_IMPLEMENTATION.md) - FastAPI RFID Server Setup
- [TIMEKEEPING_MODULE_STATUS_REPORT.md](../TIMEKEEPING_MODULE_STATUS_REPORT.md) - Current Status

---

## 📋 Executive Summary

Implement a **System-level Device Management interface** that allows SuperAdmin to register, configure, and maintain RFID scanners/readers used for employee timekeeping. This is **infrastructure management** separate from HR operations.

**Key Points:**
- **Domain:** System (`/system/timekeeping-devices`)
- **Access:** SuperAdmin only (technical/infrastructure role)
- **Purpose:** Register physical RFID devices, configure network settings, monitor health
- **Separation:** Badge management remains in HR domain

---

## 💡 Clarifications & Suggestions

### **Clarifications Needed**

1. **Device Registration Approval:**
   - ❓ Should new device registration be immediately active or require testing first?
   - **Suggested Approach:** Require successful connection test before marking "operational"

2. **Device Replacement:**
   - ❓ What happens when physical device is replaced (same location, new hardware)?
   - **Suggested Approach:** Keep device_id same, update serial number + MAC address, log as "hardware replacement"

3. **Multi-Location Support:**
   - ❓ Do you have multiple company sites/branches?
   - **Suggested Approach:** Add site/branch field for multi-location deployments

4. **Device Sync with FastAPI:**
   - ❓ Should device registration in Laravel automatically register in FastAPI?
   - **Suggested Approach:** Manual sync button with sync status indicator

5. **Offline Device Alerts:**
   - ❓ Who gets notified when device goes offline? (SuperAdmin only? HR Manager?)
   - **Suggested Approach:** Email SuperAdmin, show alert in System dashboard

6. **IP Address Management:**
   - ❓ Do devices have static IPs or DHCP?
   - **Suggested Approach:** Support both, with IP change detection and alerts

### **Suggested Features**

1. **Device Discovery:**
   - Network scan to auto-discover RFID devices on local network
   - Shows IP, MAC, device type for quick registration

2. **Configuration Templates:**
   - Save device configuration as templates
   - Quick deployment for new devices with same settings

3. **Maintenance Calendar:**
   - System-wide calendar view of all device maintenance schedules
   - Preventive maintenance reminders

4. **Device Grouping:**
   - Group devices by location/zone (e.g., "Main Building", "Warehouse")
   - Bulk operations on device groups

5. **Health Monitoring Dashboard:**
   - Real-time status of all devices
   - Uptime statistics, response time trends
   - Alert history

---

## 🗄️ Database Schema

### **rfid_devices** (System-managed)

```sql
CREATE TABLE rfid_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL UNIQUE,  -- e.g., "GATE-01"
    device_name VARCHAR(255) NOT NULL,       -- e.g., "Main Gate Entrance"
    location VARCHAR(255),                   -- e.g., "Building A - Main Gate"
    site_branch VARCHAR(255),                -- For multi-location support
    ip_address VARCHAR(45),                  -- IPv4 or IPv6
    mac_address VARCHAR(17),                 -- MAC address format
    device_type ENUM('reader', 'controller', 'hybrid') DEFAULT 'reader',
    protocol ENUM('tcp', 'udp', 'http', 'mqtt') DEFAULT 'tcp',
    port INT UNSIGNED,
    is_online BOOLEAN DEFAULT FALSE,
    last_heartbeat_at TIMESTAMP NULL,
    firmware_version VARCHAR(50),
    serial_number VARCHAR(255),
    installation_date DATE,
    maintenance_schedule ENUM('weekly', 'monthly', 'quarterly', 'annually'),
    last_maintenance_at TIMESTAMP NULL,
    next_maintenance_date DATE,
    config_json JSON,                        -- Device-specific configs
    notes TEXT,
    created_by BIGINT UNSIGNED,              -- SuperAdmin user ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_device_id (device_id),
    INDEX idx_online_status (is_online),
    INDEX idx_last_heartbeat (last_heartbeat_at),
    INDEX idx_site_branch (site_branch)
);
```

### **device_maintenance_logs** (System-managed)

```sql
CREATE TABLE device_maintenance_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'upgrade', 'replacement') NOT NULL,
    performed_by BIGINT UNSIGNED NOT NULL,   -- SuperAdmin user ID
    performed_at TIMESTAMP NOT NULL,
    description TEXT,
    cost DECIMAL(10,2),
    next_maintenance_date DATE,
    status ENUM('completed', 'pending', 'failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_device_id (device_id),
    INDEX idx_performed_at (performed_at),
    INDEX idx_status (status)
);
```

### **device_test_logs** (System-managed)

```sql
CREATE TABLE device_test_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    tested_by BIGINT UNSIGNED NOT NULL,      -- SuperAdmin user ID
    tested_at TIMESTAMP NOT NULL,
    test_type ENUM('connectivity', 'scan', 'heartbeat', 'full') NOT NULL,
    status ENUM('passed', 'failed', 'warning') NOT NULL,
    response_time_ms INT,
    error_message TEXT,
    test_results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tested_by) REFERENCES users(id),
    INDEX idx_device_id (device_id),
    INDEX idx_tested_at (tested_at),
    INDEX idx_status (status)
);
```

---

## 📦 Implementation Phases

## **PHASE 1: Device Management Frontend (Week 1)**

**Goal:** Build SuperAdmin interface for device registration, configuration, and monitoring.

---

### **Task 1.1: Create Device Management Layout**

**File:** `resources/js/pages/System/TimekeepingDevices/Index.tsx`

#### **Subtask 1.1.1: Setup Page Structure**
- Create System domain page component with Inertia wrapper
- Page header: "Timekeeping Device Management" with System breadcrumbs
- Action buttons: "Register New Device", "Network Scan", "Export Report"
- Tab navigation: "All Devices" | "Online" | "Offline" | "Maintenance Due"
- Responsive layout with system-style color scheme

#### **Subtask 1.1.2: Create Device Stats Dashboard**
- Summary cards:
  - Total Devices (with icon)
  - Online Devices (green badge, percentage)
  - Offline Devices (red badge, count with alert)
  - Maintenance Due (amber badge, next 7 days)
- Quick actions: "Test All Devices", "Sync with FastAPI Server"
- Last sync status with timestamp

#### **Subtask 1.1.3: Create Mock Data Structure**
```typescript
interface RfidDevice {
  id: string;
  deviceId: string;          // e.g., "GATE-01"
  deviceName: string;        // e.g., "Main Gate Entrance"
  location: string;
  siteBranch?: string;       // For multi-location
  deviceType: 'reader' | 'controller' | 'hybrid';
  ipAddress: string;
  macAddress: string;
  protocol: 'tcp' | 'udp' | 'http' | 'mqtt';
  port: number;
  isOnline: boolean;
  lastHeartbeat: string | null;
  firmwareVersion: string;
  serialNumber: string;
  installationDate: string;
  maintenanceSchedule: 'weekly' | 'monthly' | 'quarterly' | 'annually';
  lastMaintenance: string | null;
  nextMaintenance: string | null;
  uptimePercentage: number;
  scansToday: number;
  avgResponseTime: number;  // milliseconds
  status: 'operational' | 'warning' | 'critical' | 'maintenance';
  notes?: string;
  createdBy: string;        // SuperAdmin name
}

const mockDevices: RfidDevice[] = [
  {
    id: '1',
    deviceId: 'GATE-01',
    deviceName: 'Main Gate Entrance',
    location: 'Building A - Main Gate',
    siteBranch: 'Main Office',
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
    status: 'operational',
    createdBy: 'Admin User'
  },
  // ... 10-15 more devices
];
```

---

### **Task 1.2: Create Device List/Table Component**

**File:** `resources/js/components/system/device-management-table.tsx`

#### **Subtask 1.2.1: Build Data Table**
- Columns:
  - Status (colored indicator dot)
  - Device ID & Name
  - Location & Site
  - Type badge
  - IP:Port
  - Online Status (with heartbeat time)
  - Scans Today
  - Uptime %
  - Actions dropdown
- Sortable columns
- Pagination (25/50/100 per page)

#### **Subtask 1.2.2: Implement Search & Filters**
- Global search (ID, name, location, IP)
- Filter by status (online/offline/maintenance)
- Filter by device type
- Filter by site/branch
- Filter by maintenance due date
- "Clear Filters" button

#### **Subtask 1.2.3: Add Row Actions**
- Actions dropdown per device:
  - "View Details"
  - "Test Device Now"
  - "Edit Configuration"
  - "Schedule Maintenance"
  - "View Activity Log"
  - "Sync to FastAPI"
  - "Deactivate" (with confirmation)
- Row background color coding for status
- Smooth hover transitions

---

### **Task 1.3: Create Device Registration Form Modal**

**File:** `resources/js/components/system/device-registration-modal.tsx`

#### **Subtask 1.3.1: Build Multi-Step Wizard**
- Step 1: Basic Information
- Step 2: Network Configuration
- Step 3: Maintenance Settings
- Step 4: Test & Review
- Progress bar showing current step
- "Back" and "Next" buttons with validation

#### **Subtask 1.3.2: Step 1 - Basic Information**
Form fields:
- **Device ID:** Auto-generated (e.g., GATE-XX), editable
- **Device Name:** Required, text input
- **Location:** Required, text input or location picker
- **Site/Branch:** Optional, dropdown (for multi-location)
- **Device Type:** Radio buttons (Reader | Controller | Hybrid)
- **Serial Number:** Optional, text input
- **Installation Date:** Date picker (defaults to today)
- **Notes:** Textarea

#### **Subtask 1.3.3: Step 2 - Network Configuration**
Form fields:
- **Protocol:** Select (TCP | UDP | HTTP | MQTT)
- **IP Address:** Required, with validation (IPv4/IPv6)
- **Port:** Required, number input (default 8000)
- **MAC Address:** Optional, MAC format validation
- **Firmware Version:** Optional, text input
- **Connection Timeout:** Number input (seconds, default 30)
- **Test Connection** button (inline test)

#### **Subtask 1.3.4: Step 3 - Maintenance Settings**
Form fields:
- **Maintenance Schedule:** Select (Weekly | Monthly | Quarterly | Annually)
- **Next Maintenance Date:** Date picker
- **Reminder Settings:**
  - Checkbox: "Email reminder 1 week before"
  - Checkbox: "Show in System dashboard"
- **Maintenance Notes:** Textarea

#### **Subtask 1.3.5: Step 4 - Test & Review**
- Display summary of all entered information
- "Edit" buttons to go back to specific steps
- **"Run Connection Test"** button:
  - Shows loading spinner
  - Tests: Ping → TCP/UDP → Handshake → Response time
  - Displays results with ✅ / ❌ / ⚠️
  - Mock results for Phase 1
- **"Register Device"** button (enabled after successful test)
- Warning if test fails: "Device registered but marked as offline"

#### **Subtask 1.3.6: Form Validation**
- Real-time validation with error messages
- IP address format validation (regex)
- MAC address format validation
- Device ID uniqueness check
- Port range validation (1-65535)
- Required field highlighting
- Disable "Next" if current step invalid

---

### **Task 1.4: Create Device Detail Modal**

**File:** `resources/js/components/system/device-detail-modal.tsx`

#### **Subtask 1.4.1: Build Detail View**
- **Device Overview:**
  - Status badge (operational/warning/critical)
  - Device ID and Name
  - Location and Site
  - Last online/heartbeat timestamp
  
- **Configuration:**
  - Network settings (IP, port, protocol)
  - Firmware version
  - Serial number
  - Installation date
  
- **Statistics:**
  - Uptime percentage (7/30/90 days)
  - Scans today/week/month
  - Average response time
  - Error rate
  
- **Maintenance:**
  - Last maintenance date
  - Next scheduled maintenance
  - Maintenance history (last 5 records)

- Action buttons:
  - "Edit Configuration"
  - "Test Now"
  - "Schedule Maintenance"
  - "View Full Logs"

#### **Subtask 1.4.2: Create Activity Timeline**
- Show recent device events:
  - Heartbeat received
  - Scan processed
  - Configuration changed
  - Maintenance performed
  - Device went offline/online
  - Test executed
- Timeline component with timestamps
- Filter by event type
- Load more / pagination

#### **Subtask 1.4.3: Create Health Metrics Charts**
- **Uptime Chart:** Line chart (7/30/90 days)
- **Scans Chart:** Bar chart (scans per day)
- **Response Time:** Line chart (latency trends)
- Toggle between time ranges
- Use Chart.js or Recharts

---

### **Task 1.5: Create Device Health Test Runner**

**File:** `resources/js/components/system/device-test-runner.tsx`

#### **Subtask 1.5.1: Build Test Interface**
- Test type selector:
  - Quick Test (ping only)
  - Connectivity Test (TCP/UDP handshake)
  - Scan Test (simulate RFID scan)
  - Full Diagnostic (all tests + firmware check)
- "Run Test" button
- Real-time progress display
- Test results area

#### **Subtask 1.5.2: Mock Test Execution**
- Simulate test with delays:
  - Step 1: Pinging device... (2s) → ✅ Reachable (125ms)
  - Step 2: Establishing connection... (3s) → ✅ Connected
  - Step 3: Verifying handshake... (2s) → ✅ Handshake successful
  - Step 4: Testing scan... (4s) → ✅ Scan test passed
  - Step 5: Checking firmware... (2s) → ⚠️ Update available
- Progress bar showing current step
- Color-coded results (green/amber/red)

#### **Subtask 1.5.3: Display Test Results**
- Result summary box:
  - Overall status badge
  - Individual test results
  - Response times
  - Warnings/errors
  - Recommendations
- "Export Report" button (PDF/JSON)
- "Retest" button
- Auto-save test log to database

---

### **Task 1.6: Create Device Maintenance Scheduler**

**File:** `resources/js/components/system/device-maintenance-modal.tsx`

#### **Subtask 1.6.1: Build Maintenance Form**
Form fields:
- **Maintenance Type:** Radio (Routine | Repair | Upgrade | Replacement)
- **Scheduled Date/Time:** DateTime picker
- **Estimated Duration:** Number input (hours)
- **Technician/Performed By:** Text input or user selector
- **Description:** Required, textarea
- **Parts Required:** Optional, textarea
- **Estimated Cost:** Optional, money input (₱)
- **Notify:** Checkbox options (Email, System alert)

#### **Subtask 1.6.2: Create Maintenance Calendar View**
- Mini calendar showing scheduled maintenance
- Color-coded by maintenance type:
  - Routine = blue
  - Repair = amber
  - Upgrade = green
  - Replacement = red
- Click date to view/edit maintenance
- Month/year navigation
- "Today" button

#### **Subtask 1.6.3: Add Maintenance History Table**
- List past maintenance records:
  - Date performed
  - Type
  - Performed by
  - Status (completed/failed)
  - Cost
  - Notes
- Sortable/filterable
- Export maintenance history

---

### **Task 1.7: Create Device Edit Form**

**File:** `resources/js/components/system/device-edit-modal.tsx`

#### **Subtask 1.7.1: Build Edit Form**
- Pre-populate all fields with current data
- Editable fields:
  - Device Name
  - Location
  - Site/Branch
  - Network settings (IP, port, protocol)
  - Maintenance schedule
  - Notes
- Read-only fields (shown but disabled):
  - Device ID
  - Serial Number
  - Installation Date
  - Created By

#### **Subtask 1.7.2: Implement Change Detection**
- Track modified fields
- Show "Unsaved Changes" warning
- Confirm before closing with changes
- Highlight changed fields (yellow border)
- "Revert Changes" button

#### **Subtask 1.7.3: Add Configuration Test**
- "Test New Configuration" button
- Mock test with new settings
- Show comparison table (Current vs. New)
- Warning if changing IP/port: "May lose connection"
- Require test success before saving critical changes

---

## **PHASE 2: Device Management Backend (Week 2)**

**Goal:** Implement backend controllers, services, and API for device management.

---

### **Task 2.1: Create Device Models**

**File:** `app/Models/RfidDevice.php`

#### **Subtask 2.1.1: Create RfidDevice Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class RfidDevice extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'device_id', 'device_name', 'location', 'site_branch',
        'ip_address', 'mac_address', 'device_type', 'protocol', 'port',
        'is_online', 'last_heartbeat_at', 'firmware_version', 'serial_number',
        'installation_date', 'maintenance_schedule', 'last_maintenance_at',
        'next_maintenance_date', 'config_json', 'notes', 'created_by'
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'installation_date' => 'date',
        'last_maintenance_at' => 'datetime',
        'next_maintenance_date' => 'date',
        'config_json' => 'array',
    ];

    // Relationships
    public function maintenanceLogs()
    {
        return $this->hasMany(DeviceMaintenanceLog::class, 'device_id', 'device_id');
    }

    public function testLogs()
    {
        return $this->hasMany(DeviceTestLog::class, 'device_id', 'device_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('is_online', true)
            ->where('last_heartbeat_at', '>=', now()->subMinutes(5));
    }

    public function scopeOffline($query)
    {
        return $query->where('is_online', false)
            ->orWhere('last_heartbeat_at', '<', now()->subMinutes(5));
    }

    public function scopeMaintenanceDue($query, $days = 7)
    {
        return $query->whereDate('next_maintenance_date', '<=', now()->addDays($days));
    }

    // Accessors
    public function getStatusAttribute()
    {
        if (!$this->is_online || $this->last_heartbeat_at < now()->subMinutes(5)) {
            return 'critical';
        }
        
        if ($this->next_maintenance_date && $this->next_maintenance_date <= now()->addDays(7)) {
            return 'warning';
        }
        
        if ($this->uptime_percentage < 95) {
            return 'warning';
        }
        
        return 'operational';
    }

    public function getUptimePercentageAttribute()
    {
        // Calculate from test logs or heartbeat history
        // For Phase 1, return mock value
        return 99.5;
    }
}
```

#### **Subtask 2.1.2: Create DeviceMaintenanceLog Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceMaintenanceLog extends Model
{
    protected $fillable = [
        'device_id', 'maintenance_type', 'performed_by', 'performed_at',
        'description', 'cost', 'next_maintenance_date', 'status'
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'next_maintenance_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function device()
    {
        return $this->belongsTo(RfidDevice::class, 'device_id', 'device_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'pending')
            ->where('performed_at', '>=', now());
    }
}
```

#### **Subtask 2.1.3: Create DeviceTestLog Model**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceTestLog extends Model
{
    protected $fillable = [
        'device_id', 'tested_by', 'tested_at', 'test_type',
        'status', 'response_time_ms', 'error_message', 'test_results'
    ];

    protected $casts = [
        'tested_at' => 'datetime',
        'test_results' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(RfidDevice::class, 'device_id', 'device_id');
    }

    public function testedBy()
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function scopePassed($query)
    {
        return $query->where('status', 'passed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('tested_at', '>=', now()->subDays($days));
    }
}
```

---

### **Task 2.2: Create Migrations**

#### **Subtask 2.2.1: Create rfid_devices Migration**
**File:** `database/migrations/YYYY_MM_DD_create_rfid_devices_table.php`
- Implement schema as defined in Database Schema section
- Add indexes for performance
- Add foreign key constraints

#### **Subtask 2.2.2: Create device_maintenance_logs Migration**
**File:** `database/migrations/YYYY_MM_DD_create_device_maintenance_logs_table.php`
- Implement schema
- Add indexes and foreign keys

#### **Subtask 2.2.3: Create device_test_logs Migration**
**File:** `database/migrations/YYYY_MM_DD_create_device_test_logs_table.php`
- Implement schema
- Add indexes and foreign keys

---

### **Task 2.3: Create DeviceManagementController**

**File:** `app/Http/Controllers/System/DeviceManagementController.php`

#### **Subtask 2.3.1: Implement index() Method**
```php
<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-system-devices'); // SuperAdmin only

        $devices = RfidDevice::query()
            ->when($request->search, function($q, $search) {
                $q->where('device_id', 'like', "%{$search}%")
                  ->orWhere('device_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            })
            ->when($request->status, function($q, $status) {
                if ($status === 'online') {
                    $q->online();
                } elseif ($status === 'offline') {
                    $q->offline();
                } elseif ($status === 'maintenance_due') {
                    $q->maintenanceDue(7);
                }
            })
            ->when($request->device_type, fn($q, $type) => 
                $q->where('device_type', $type)
            )
            ->when($request->site_branch, fn($q, $site) => 
                $q->where('site_branch', $site)
            )
            ->with(['maintenanceLogs' => fn($q) => $q->latest()->limit(5)])
            ->paginate($request->per_page ?? 25);

        $stats = [
            'total' => RfidDevice::count(),
            'online' => RfidDevice::online()->count(),
            'offline' => RfidDevice::offline()->count(),
            'maintenance_due' => RfidDevice::maintenanceDue(7)->count(),
        ];

        return Inertia::render('System/TimekeepingDevices/Index', [
            'devices' => $devices,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'device_type', 'site_branch']),
        ]);
    }
}
```

#### **Subtask 2.3.2: Implement store() Method**
```php
public function store(StoreDeviceRequest $request)
{
    $this->authorize('manage-system-devices');

    $device = RfidDevice::create([
        'device_id' => $request->device_id,
        'device_name' => $request->device_name,
        'location' => $request->location,
        'site_branch' => $request->site_branch,
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
        'created_by' => auth()->id(),
        'is_online' => false, // Default offline until heartbeat
    ]);

    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->log('RFID device registered: ' . $device->device_name);

    return redirect()->route('system.timekeeping-devices.index')
        ->with('success', 'Device registered successfully');
}
```

#### **Subtask 2.3.3: Implement show() Method**
```php
public function show(RfidDevice $device)
{
    $this->authorize('manage-system-devices');

    $device->load([
        'maintenanceLogs' => fn($q) => $q->latest()->limit(20),
        'testLogs' => fn($q) => $q->latest()->limit(50),
        'creator',
    ]);

    // Calculate uptime data
    $uptimeData = $this->calculateUptimeData($device);

    // Get scan statistics
    $scanStats = $this->getScanStatistics($device);

    return Inertia::render('System/TimekeepingDevices/Show', [
        'device' => $device,
        'uptimeData' => $uptimeData,
        'scanStats' => $scanStats,
    ]);
}
```

#### **Subtask 2.3.4: Implement update() Method**
```php
public function update(UpdateDeviceRequest $request, RfidDevice $device)
{
    $this->authorize('manage-system-devices');

    $originalData = $device->toArray();
    
    $device->update($request->validated());

    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->withProperties([
            'old' => $originalData,
            'attributes' => $device->toArray(),
        ])
        ->log('Device configuration updated');

    return redirect()->back()
        ->with('success', 'Device updated successfully');
}
```

#### **Subtask 2.3.5: Implement destroy() Method**
```php
public function destroy(RfidDevice $device)
{
    $this->authorize('manage-system-devices');

    $device->update(['is_online' => false]);
    $device->delete(); // Soft delete

    activity()
        ->causedBy(auth()->user())
        ->performedOn($device)
        ->log('Device deactivated: ' . $device->device_name);

    return redirect()->route('system.timekeeping-devices.index')
        ->with('success', 'Device deactivated successfully');
}
```

---

### **Task 2.4: Create DeviceTestService**

**File:** `app/Services/System/DeviceTestService.php`

#### **Subtask 2.4.1: Implement testDevice() Method**
```php
<?php

namespace App\Services\System;

use App\Models\RfidDevice;
use App\Models\DeviceTestLog;
use Illuminate\Support\Facades\Log;

class DeviceTestService
{
    public function testDevice(RfidDevice $device, string $testType = 'full'): array
    {
        $results = [];

        try {
            // Test 1: Ping device
            $results['ping'] = $this->pingDevice($device);

            if ($testType === 'quick') {
                return $this->formatAndLogResults($device, $testType, $results);
            }

            // Test 2: Connection test
            $results['connection'] = $this->testConnection($device);

            // Test 3: Handshake
            $results['handshake'] = $this->testHandshake($device);

            if ($testType === 'connectivity') {
                return $this->formatAndLogResults($device, $testType, $results);
            }

            // Test 4: Scan simulation (full test only)
            if ($testType === 'full') {
                $results['scan'] = $this->testScanFunctionality($device);
                $results['firmware'] = $this->checkFirmwareVersion($device);
            }

            return $this->formatAndLogResults($device, $testType, $results);

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

    protected function pingDevice(RfidDevice $device): array
    {
        $start = microtime(true);

        // Use fsockopen to test reachability
        $socket = @fsockopen($device->ip_address, $device->port, $errno, $errstr, 5);

        $responseTime = (microtime(true) - $start) * 1000; // ms

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
        // Protocol-specific connection test
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

    protected function testTcpConnection(RfidDevice $device): array
    {
        // Implement TCP connection test
        // For Phase 1, return mock success
        return [
            'success' => true,
            'message' => 'TCP connection successful',
            'protocol' => 'tcp',
        ];
    }

    protected function testHandshake(RfidDevice $device): array
    {
        // Implement device-specific handshake
        // For Phase 1, mock success
        return [
            'success' => true,
            'message' => 'Handshake successful',
            'firmware_version' => $device->firmware_version,
        ];
    }

    protected function testScanFunctionality(RfidDevice $device): array
    {
        // Send test scan command
        // For Phase 1, mock success
        return [
            'success' => true,
            'message' => 'Scan test successful',
            'test_card_uid' => '00:00:00:00:00:00',
        ];
    }

    protected function checkFirmwareVersion(RfidDevice $device): array
    {
        // Check if firmware update available
        // For Phase 1, mock check
        $latestVersion = 'v2.4.0';
        $isUpToDate = version_compare($device->firmware_version, $latestVersion, '>=');

        return [
            'success' => true,
            'current_version' => $device->firmware_version,
            'latest_version' => $latestVersion,
            'update_available' => !$isUpToDate,
            'message' => $isUpToDate ? 'Firmware up-to-date' : 'Update available',
        ];
    }

    protected function formatAndLogResults(RfidDevice $device, string $testType, array $results): array
    {
        $overallStatus = $this->determineOverallStatus($results);

        // Log test results
        DeviceTestLog::create([
            'device_id' => $device->device_id,
            'tested_by' => auth()->id(),
            'tested_at' => now(),
            'test_type' => $testType,
            'status' => $overallStatus,
            'response_time_ms' => $results['ping']['response_time_ms'] ?? null,
            'test_results' => $results,
        ]);

        return [
            'success' => $overallStatus !== 'failed',
            'status' => $overallStatus,
            'results' => $results,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function determineOverallStatus(array $results): string
    {
        $hasFailure = false;
        $hasWarning = false;

        foreach ($results as $result) {
            if (!$result['success']) {
                $hasFailure = true;
            }
            if (isset($result['warning']) && $result['warning']) {
                $hasWarning = true;
            }
        }

        if ($hasFailure) return 'failed';
        if ($hasWarning) return 'warning';
        return 'passed';
    }
}
```

---

### **Task 2.5: Create Form Request Validators**

#### **Subtask 2.5.1: Create StoreDeviceRequest**
**File:** `app/Http/Requests/System/StoreDeviceRequest.php`
```php
<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-system-devices');
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255', 'unique:rfid_devices,device_id'],
            'device_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'site_branch' => ['nullable', 'string', 'max:255'],
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

    public function messages(): array
    {
        return [
            'device_id.unique' => 'This device ID is already registered.',
            'ip_address.ip' => 'Please enter a valid IP address.',
            'port.between' => 'Port must be between 1 and 65535.',
            'mac_address.regex' => 'Please enter a valid MAC address (e.g., 00:1B:44:11:3A:B7).',
        ];
    }
}
```

#### **Subtask 2.5.2: Create UpdateDeviceRequest**
**File:** `app/Http/Requests/System/UpdateDeviceRequest.php`
```php
<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-system-devices');
    }

    public function rules(): array
    {
        $deviceId = $this->route('device')->id;

        return [
            'device_name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'site_branch' => ['nullable', 'string', 'max:255'],
            'protocol' => ['sometimes', 'required', 'in:tcp,udp,http,mqtt'],
            'ip_address' => ['sometimes', 'required', 'ip'],
            'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'maintenance_schedule' => ['sometimes', 'required', 'in:weekly,monthly,quarterly,annually'],
            'next_maintenance_date' => ['nullable', 'date'],
            'config_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

---

### **Task 2.6: Create Routes**

**File:** `routes/system.php` (or add to existing system routes file)

#### **Subtask 2.6.1: Add Device Management Routes**
```php
<?php

use App\Http\Controllers\System\DeviceManagementController;
use Illuminate\Support\Facades\Route;

// System - Timekeeping Device Management
Route::prefix('timekeeping-devices')
    ->name('timekeeping-devices.')
    ->middleware(['auth', 'role:superadmin'])
    ->group(function () {
        
        // List devices
        Route::get('/', [DeviceManagementController::class, 'index'])
            ->name('index');
        
        // Register new device
        Route::post('/', [DeviceManagementController::class, 'store'])
            ->name('store');
        
        // View device details
        Route::get('/{device}', [DeviceManagementController::class, 'show'])
            ->name('show');
        
        // Update device
        Route::put('/{device}', [DeviceManagementController::class, 'update'])
            ->name('update');
        
        // Deactivate device
        Route::delete('/{device}', [DeviceManagementController::class, 'destroy'])
            ->name('destroy');
        
        // Test device
        Route::post('/{device}/test', [DeviceManagementController::class, 'test'])
            ->name('test');
        
        // Schedule maintenance
        Route::post('/{device}/maintenance', [DeviceManagementController::class, 'scheduleMaintenance'])
            ->name('maintenance.schedule');
        
        // Complete maintenance
        Route::put('/maintenance/{maintenance}', [DeviceManagementController::class, 'completeMaintenance'])
            ->name('maintenance.complete');
        
        // Get maintenance due
        Route::get('/maintenance/due', [DeviceManagementController::class, 'maintenanceDue'])
            ->name('maintenance.due');
    });
```

---

### **Task 2.7: Create Permissions**

#### **Subtask 2.7.1: Add Permission Seeder**
**File:** `database/seeders/SystemDevicePermissionsSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemDevicePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'manage-system-devices' => 'Manage Timekeeping Devices (System)',
            'view-system-devices' => 'View Timekeeping Devices (System)',
            'test-system-devices' => 'Test Timekeeping Devices',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }

        // Assign to SuperAdmin role
        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdmin->givePermissionTo(array_keys($permissions));
    }
}
```

---

## 📊 Implementation Checklist

### **Phase 1: Frontend (Week 1)**
- [ ] Device Management page layout
- [ ] Device stats dashboard
- [ ] Device list/table component
- [ ] Device registration form (multi-step wizard)
- [ ] Device detail modal
- [ ] Device edit form
- [ ] Device health test runner
- [ ] Maintenance scheduler modal
- [ ] Activity timeline component
- [ ] Health metrics charts

### **Phase 2: Backend (Week 2)**
- [ ] RfidDevice model
- [ ] DeviceMaintenanceLog model
- [ ] DeviceTestLog model
- [ ] Database migrations
- [ ] DeviceManagementController
- [ ] DeviceTestService
- [ ] Form request validators
- [ ] Routes configuration
- [ ] Permission seeder
- [ ] Activity logging

### **Testing (Parallel)**
- [ ] Unit tests for models
- [ ] Unit tests for DeviceTestService
- [ ] Feature tests for controller
- [ ] UI integration tests
- [ ] Permission tests

### **Documentation**
- [ ] SuperAdmin user guide
- [ ] API documentation
- [ ] Troubleshooting guide

---

## 🔐 Access Control

**Permissions:**
```php
'manage-system-devices' => 'Full device management (SuperAdmin only)',
'view-system-devices' => 'View device list and details',
'test-system-devices' => 'Run device health tests',
```

**Role Assignment:**
- **SuperAdmin:** All permissions (manage, view, test)
- **Other roles:** No access (system infrastructure only)

---

## 📈 Success Metrics

- 100% of physical RFID devices registered in system
- < 5 minutes average device registration time
- Device health checks automated (hourly)
- Maintenance schedules tracked and followed
- 99%+ device uptime
- < 200ms average device response time

---

## 🚀 Future Enhancements

1. **Network Discovery:** Auto-detect RFID devices on network
2. **Firmware Updates:** Push automatic firmware updates
3. **Device Templates:** Configuration templates for quick deployment
4. **Mobile App:** Device management via mobile app
5. **IoT Integration:** Real-time monitoring dashboard with alerts

---

**Document Version:** 1.0  
**Last Updated:** February 12, 2026  
**Domain:** System (SuperAdmin)  
**Status:** Ready for Implementation
