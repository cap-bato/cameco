# System - Device List Page Implementation

**Page:** `/system/timekeeping-devices`  
**Controller:** `System\DeviceManagementController@index`  
**Priority:** HIGH  
**Estimated Duration:** 2 days  
**Current Status:** ⏳ PENDING - Database schema exists, no controller or frontend yet

---

## 📋 Current State Analysis

### ✅ Already Implemented
- ✅ Database table `rfid_devices` exists (migration: `2026_02_04_095813_create_rfid_devices_table.php`)
- ✅ RfidDevice model exists (`app/Models/RfidDevice.php`)
- ✅ System routes file exists (`routes/system.php`)
- ✅ SuperAdmin middleware configured
- ✅ Spatie Permissions package installed

### ⏳ Needs Implementation
- ⏳ DeviceManagementController with index() method
- ⏳ Device list frontend page (Inertia + React)
- ⏳ Device stats dashboard component
- ⏳ Device data table with filters
- ⏳ Search and filtering logic
- ⏳ Permissions seeder for device management
- ⏳ Routes configuration

### Related Files
- **Controller:** `app/Http/Controllers/System/DeviceManagementController.php` (NEW)
- **Model:** `app/Models/RfidDevice.php` (EXISTS)
- **Frontend:** `resources/js/pages/System/TimekeepingDevices/Index.tsx` (NEW)
- **Routes:** `routes/system.php` (EXISTS - needs new routes)
- **Migration:** `database/migrations/2026_02_04_095813_create_rfid_devices_table.php` (EXISTS)

---

## Phase 1: Backend - Controller & API (Day 1)

**Duration:** 1 day  
**Goal:** Create backend controller with device listing, filtering, and statistics

---

### Task 1.1: Create DeviceManagementController

**Goal:** Implement controller with index() method for device listing

**File to Create:** `app/Http/Controllers/System/DeviceManagementController.php`

#### Subtask 1.1.1: Create Controller File

**Implementation:**
```php
<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceManagementController extends Controller
{
    /**
     * Display list of RFID devices with filtering and statistics.
     * 
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Permission check: SuperAdmin only
        $this->authorize('manage-system-devices');

        $perPage = $request->get('per_page', 25);
        
        // Build query with filters
        $query = RfidDevice::query()
            ->orderBy('created_at', 'desc');
        
        // Apply search filter (device_id, device_name, location, ip_address)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('device_id', 'like', "%{$search}%")
                  ->orWhere('device_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('config->ip_address', 'like', "%{$search}%");
            });
        }
        
        // Apply status filter
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'online':
                    $query->where('status', 'online')
                          ->where('last_heartbeat', '>=', now()->subMinutes(10));
                    break;
                case 'offline':
                    $query->where('status', 'offline')
                          ->orWhere('last_heartbeat', '<', now()->subMinutes(10));
                    break;
                case 'maintenance':
                    $query->where('status', 'maintenance');
                    break;
            }
        }
        
        // Paginate
        $devices = $query->paginate($perPage);
        
        // Calculate statistics
        $stats = [
            'total' => RfidDevice::count(),
            'online' => RfidDevice::where('status', 'online')
                ->where('last_heartbeat', '>=', now()->subMinutes(10))
                ->count(),
            'offline' => RfidDevice::where(function($q) {
                    $q->where('status', 'offline')
                      ->orWhere('last_heartbeat', '<', now()->subMinutes(10));
                })->count(),
            'maintenance' => RfidDevice::where('status', 'maintenance')->count(),
            'last_sync' => now()->toIso8601String(),
        ];
        
        return Inertia::render('System/TimekeepingDevices/Index', [
            'devices' => $devices,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }
}
```

**Testing:**
- Verify controller returns Inertia response
- Test with valid SuperAdmin user
- Test permission denial for non-SuperAdmin
- Verify pagination works correctly
- Test search filtering
- Test status filtering
- Verify statistics calculations

---

### Task 1.2: Add Routes Configuration

**Goal:** Register device management routes in system routes file

**File to Modify:** `routes/system.php`

#### Subtask 1.2.1: Add Device Management Routes

**Implementation:**
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\System\DeviceManagementController;

// Add after existing system routes...

// System - Timekeeping Device Management
Route::prefix('timekeeping-devices')
    ->name('timekeeping-devices.')
    ->middleware(['auth', 'verified', 'role:superadmin'])
    ->group(function () {
        
        // Device list (index page)
        Route::get('/', [DeviceManagementController::class, 'index'])
            ->name('index')
            ->middleware('permission:manage-system-devices');
    });
```

**Testing:**
- Navigate to `/system/timekeeping-devices`
- Verify route resolves correctly
- Test middleware blocks non-SuperAdmin users
- Verify permission check works

---

### Task 1.3: Create Permission Seeder

**Goal:** Add permissions for device management and assign to SuperAdmin role

**File to Create:** `database/seeders/SystemDevicePermissionsSeeder.php`

#### Subtask 1.3.1: Create Seeder File

**Implementation:**
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemDevicePermissionsSeeder extends Seeder
{
    /**
     * Seed permissions for System Device Management.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            [
                'name' => 'manage-system-devices',
                'description' => 'Manage Timekeeping Devices (System Domain - SuperAdmin)',
                'guard_name' => 'web',
            ],
            [
                'name' => 'view-system-devices',
                'description' => 'View Timekeeping Devices (Read-only)',
                'guard_name' => 'web',
            ],
            [
                'name' => 'test-system-devices',
                'description' => 'Run Device Health Tests',
                'guard_name' => 'web',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                $permission
            );
        }

        // Assign all permissions to SuperAdmin role
        $superAdmin = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['description' => 'Super Administrator']
        );
        
        $superAdmin->syncPermissions([
            'manage-system-devices',
            'view-system-devices',
            'test-system-devices',
        ]);

        $this->command->info('✅ System Device Management permissions created and assigned to SuperAdmin');
    }
}
```

**Run Seeder:**
```bash
php artisan db:seed --class=SystemDevicePermissionsSeeder
```

**Testing:**
- Verify permissions created in `permissions` table
- Check SuperAdmin role has all 3 permissions
- Test authorization in controller

---

## Phase 2: Frontend - Device List UI (Day 2)

**Duration:** 1 day  
**Goal:** Build React/Inertia frontend with device table, stats, and filters

---

### Task 2.1: Create Page Structure

**Goal:** Build main device list page with layout and stats dashboard

**File to Create:** `resources/js/pages/System/TimekeepingDevices/Index.tsx`

#### Subtask 2.1.1: Create Page Component

**Implementation:**
```tsx
import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    Plus, 
    Activity, 
    AlertCircle, 
    CheckCircle, 
    XCircle,
    Settings,
    RefreshCw
} from 'lucide-react';

interface RfidDevice {
    id: number;
    device_id: string;
    device_name: string;
    location: string;
    status: 'online' | 'offline' | 'maintenance';
    last_heartbeat: string | null;
    config: {
        ip_address?: string;
        port?: number;
    };
    created_at: string;
    updated_at: string;
}

interface DeviceStats {
    total: number;
    online: number;
    offline: number;
    maintenance: number;
    last_sync: string;
}

interface Props {
    devices: {
        data: RfidDevice[];
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    stats: DeviceStats;
    filters: {
        search?: string;
        status?: string;
        per_page?: number;
    };
}

export default function TimekeepingDevicesIndex() {
    const { devices, stats, filters } = usePage<Props>().props;
    
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    const handleSearch = () => {
        router.get('/system/timekeeping-devices', {
            search: searchTerm,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            per_page: filters.per_page || 25,
        }, {
            preserveState: true,
        });
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setStatusFilter('all');
        router.get('/system/timekeeping-devices', {}, { preserveState: false });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                            Timekeeping Device Management
                        </h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            System / Devices
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.reload()}
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => router.visit('/system/timekeeping-devices/create')}
                        >
                            <Plus className="h-4 w-4 mr-2" />
                            Register Device
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title="Timekeeping Devices" />

            <div className="py-6">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Stats Dashboard */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Total Devices */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Total Devices
                                </CardTitle>
                                <Settings className="h-4 w-4 text-gray-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total}</div>
                            </CardContent>
                        </Card>

                        {/* Online Devices */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Online
                                </CardTitle>
                                <CheckCircle className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">{stats.online}</div>
                                <p className="text-xs text-gray-500 mt-1">
                                    {stats.total > 0 ? Math.round((stats.online / stats.total) * 100) : 0}% uptime
                                </p>
                            </CardContent>
                        </Card>

                        {/* Offline Devices */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Offline
                                </CardTitle>
                                <XCircle className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{stats.offline}</div>
                                {stats.offline > 0 && (
                                    <Badge variant="destructive" className="mt-1 text-xs">
                                        Needs attention
                                    </Badge>
                                )}
                            </CardContent>
                        </Card>

                        {/* Maintenance */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Maintenance
                                </CardTitle>
                                <AlertCircle className="h-4 w-4 text-amber-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-amber-600">{stats.maintenance}</div>
                                <p className="text-xs text-gray-500 mt-1">Under maintenance</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Search and Filters */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex flex-col md:flex-row gap-4">
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        placeholder="Search by Device ID, Name, Location, or IP..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                        className="w-full px-4 py-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700"
                                    />
                                </div>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="px-4 py-2 border rounded-lg dark:bg-gray-800 dark:border-gray-700"
                                >
                                    <option value="all">All Status</option>
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                                <Button onClick={handleSearch}>Search</Button>
                                <Button variant="outline" onClick={handleClearFilters}>
                                    Clear
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Device Table - Will be implemented in Task 2.2 */}
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-gray-500">Device table component goes here (Task 2.2)</p>
                        </CardContent>
                    </Card>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

**Testing:**
- Navigate to `/system/timekeeping-devices` as SuperAdmin
- Verify page renders without errors
- Check stats dashboard displays correctly
- Test search input and status dropdown
- Verify buttons are visible and clickable

---

### Task 2.2: Create Device Table Component

**Goal:** Build data table with device list, status indicators, and actions

**File to Create:** `resources/js/components/system/device-table.tsx`

#### Subtask 2.2.1: Create Table Component

**Implementation:**
```tsx
import React from 'react';
import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Eye, Edit, TestTube, Wrench, Power } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

interface RfidDevice {
    id: number;
    device_id: string;
    device_name: string;
    location: string;
    status: 'online' | 'offline' | 'maintenance';
    last_heartbeat: string | null;
    config: {
        ip_address?: string;
        port?: number;
    };
}

interface Props {
    devices: RfidDevice[];
}

export default function DeviceTable({ devices }: Props) {
    
    const getStatusBadge = (status: string, lastHeartbeat: string | null) => {
        // Check if device hasn't sent heartbeat in 10+ minutes
        const isStale = lastHeartbeat && 
            new Date(lastHeartbeat) < new Date(Date.now() - 10 * 60 * 1000);
        
        if (status === 'online' && !isStale) {
            return <Badge className="bg-green-500">Online</Badge>;
        }
        if (status === 'maintenance') {
            return <Badge className="bg-amber-500">Maintenance</Badge>;
        }
        return <Badge variant="destructive">Offline</Badge>;
    };

    const handleViewDetails = (deviceId: number) => {
        router.visit(`/system/timekeeping-devices/${deviceId}`);
    };

    const handleEdit = (deviceId: number) => {
        router.visit(`/system/timekeeping-devices/${deviceId}/edit`);
    };

    const handleTest = (deviceId: number) => {
        router.post(`/system/timekeeping-devices/${deviceId}/test`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Status</TableHead>
                        <TableHead>Device ID</TableHead>
                        <TableHead>Device Name</TableHead>
                        <TableHead>Location</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead>Last Heartbeat</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {devices.length === 0 ? (
                        <TableRow>
                            <TableCell colSpan={7} className="text-center py-8 text-gray-500">
                                No devices found. Register your first device to get started.
                            </TableCell>
                        </TableRow>
                    ) : (
                        devices.map((device) => (
                            <TableRow key={device.id}>
                                <TableCell>
                                    {getStatusBadge(device.status, device.last_heartbeat)}
                                </TableCell>
                                <TableCell className="font-mono font-semibold">
                                    {device.device_id}
                                </TableCell>
                                <TableCell>{device.device_name}</TableCell>
                                <TableCell>{device.location}</TableCell>
                                <TableCell className="font-mono text-sm">
                                    {device.config?.ip_address || 'N/A'}
                                    {device.config?.port && `:${device.config.port}`}
                                </TableCell>
                                <TableCell className="text-sm text-gray-600">
                                    {device.last_heartbeat 
                                        ? formatDistanceToNow(new Date(device.last_heartbeat), { addSuffix: true })
                                        : 'Never'
                                    }
                                </TableCell>
                                <TableCell className="text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm">
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onClick={() => handleViewDetails(device.id)}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View Details
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => handleEdit(device.id)}>
                                                <Edit className="mr-2 h-4 w-4" />
                                                Edit Configuration
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => handleTest(device.id)}>
                                                <TestTube className="mr-2 h-4 w-4" />
                                                Run Test
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>
                                                <Wrench className="mr-2 h-4 w-4" />
                                                Schedule Maintenance
                                            </DropdownMenuItem>
                                            <DropdownMenuItem className="text-red-600">
                                                <Power className="mr-2 h-4 w-4" />
                                                Deactivate
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
```

#### Subtask 2.2.2: Integrate Table into Index Page

Update `Index.tsx` to import and use the DeviceTable component:

```tsx
import DeviceTable from '@/components/system/device-table';

// Replace placeholder table card with:
<Card>
    <CardContent className="pt-6">
        <DeviceTable devices={devices.data} />
    </CardContent>
</Card>
```

**Testing:**
- Verify table renders device data correctly
- Check status badges show correct colors
- Test last heartbeat time calculation
- Verify action dropdown opens and closes
- Test all action menu items trigger correct routes

---

### Task 2.3: Add Pagination Component

**Goal:** Implement pagination for device list

**File to Modify:** `resources/js/pages/System/TimekeepingDevices/Index.tsx`

#### Subtask 2.3.1: Add Pagination UI

```tsx
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

// Add after DeviceTable component:
<div className="flex items-center justify-between mt-4">
    <div className="text-sm text-gray-600 dark:text-gray-400">
        Showing {devices.data.length > 0 ? ((devices.current_page - 1) * devices.per_page) + 1 : 0}
        {' '}to{' '}
        {Math.min(devices.current_page * devices.per_page, devices.total)}
        {' '}of {devices.total} devices
    </div>
    
    <Pagination>
        <PaginationContent>
            {devices.current_page > 1 && (
                <PaginationItem>
                    <PaginationPrevious 
                        href="#"
                        onClick={(e) => {
                            e.preventDefault();
                            router.get('/system/timekeeping-devices', {
                                ...filters,
                                page: devices.current_page - 1,
                            });
                        }}
                    />
                </PaginationItem>
            )}
            
            {Array.from({ length: Math.min(5, devices.last_page) }, (_, i) => {
                const page = i + 1;
                return (
                    <PaginationItem key={page}>
                        <PaginationLink
                            href="#"
                            isActive={page === devices.current_page}
                            onClick={(e) => {
                                e.preventDefault();
                                router.get('/system/timekeeping-devices', {
                                    ...filters,
                                    page,
                                });
                            }}
                        >
                            {page}
                        </PaginationLink>
                    </PaginationItem>
                );
            })}
            
            {devices.current_page < devices.last_page && (
                <PaginationItem>
                    <PaginationNext 
                        href="#"
                        onClick={(e) => {
                            e.preventDefault();
                            router.get('/system/timekeeping-devices', {
                                ...filters,
                                page: devices.current_page + 1,
                            });
                        }}
                    />
                </PaginationItem>
            )}
        </PaginationContent>
    </Pagination>
</div>
```

**Testing:**
- Test pagination navigation
- Verify page numbers are correct
- Check previous/next buttons work
- Test with different page sizes
- Verify filters persist across page changes

---

## Phase 3: Testing & Validation (Parallel)

**Duration:** 0.5 days  
**Goal:** Write tests and validate implementation

---

### Task 3.1: Unit Tests

**File to Create:** `tests/Unit/Controllers/System/DeviceManagementControllerTest.php`

```php
<?php

namespace Tests\Unit\Controllers\System;

use Tests\TestCase;
use App\Models\User;
use App\Models\RfidDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class DeviceManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create SuperAdmin user
        $superAdminRole = Role::create(['name' => 'superadmin']);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'SystemDevicePermissionsSeeder']);
    }

    /** @test */
    public function index_page_is_accessible_by_superadmin()
    {
        $this->actingAs($this->superAdmin)
            ->get('/system/timekeeping-devices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('System/TimekeepingDevices/Index')
                ->has('devices')
                ->has('stats')
            );
    }

    /** @test */
    public function index_page_is_not_accessible_by_regular_users()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user)
            ->get('/system/timekeeping-devices')
            ->assertForbidden();
    }

    /** @test */
    public function device_list_shows_correct_statistics()
    {
        // Create test devices
        RfidDevice::factory()->create(['status' => 'online']);
        RfidDevice::factory()->create(['status' => 'offline']);
        RfidDevice::factory()->create(['status' => 'maintenance']);

        $response = $this->actingAs($this->superAdmin)
            ->get('/system/timekeeping-devices')
            ->assertOk();

        $stats = $response->viewData('stats');
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['online']);
        $this->assertEquals(1, $stats['offline']);
        $this->assertEquals(1, $stats['maintenance']);
    }

    /** @test */
    public function search_filter_works_correctly()
    {
        RfidDevice::factory()->create(['device_id' => 'GATE-01', 'device_name' => 'Main Gate']);
        RfidDevice::factory()->create(['device_id' => 'WAREHOUSE-01', 'device_name' => 'Warehouse']);

        $response = $this->actingAs($this->superAdmin)
            ->get('/system/timekeeping-devices?search=GATE')
            ->assertOk();

        $devices = $response->viewData('devices');
        $this->assertCount(1, $devices->items());
        $this->assertEquals('GATE-01', $devices->items()[0]->device_id);
    }
}
```

**Run Tests:**
```bash
php artisan test --filter=DeviceManagementControllerTest
```

---

### Task 3.2: Feature Tests

**Testing Checklist:**
- ✅ SuperAdmin can access device list page
- ✅ Regular users cannot access device list page
- ✅ Statistics calculate correctly (total, online, offline, maintenance)
- ✅ Search filter works for device_id, device_name, location
- ✅ Status filter shows correct devices
- ✅ Pagination works correctly
- ✅ Empty state displays when no devices
- ✅ Device table renders all columns
- ✅ Action dropdown shows all options
- ✅ Heartbeat time displays correctly

---

## Success Criteria

- ✅ Device list page accessible at `/system/timekeeping-devices`
- ✅ Only SuperAdmin role can access
- ✅ Stats dashboard shows total, online, offline, maintenance counts
- ✅ Device table displays all devices with pagination
- ✅ Search works across device_id, device_name, location, ip_address
- ✅ Status filter (all/online/offline/maintenance) works correctly
- ✅ Devices show correct status badges
- ✅ Last heartbeat displays relative time (e.g., "2 minutes ago")
- ✅ Action dropdown provides access to view, edit, test, maintenance, deactivate
- ✅ Page is responsive (mobile, tablet, desktop)
- ✅ All unit and feature tests pass
- ✅ Performance: Page loads < 500ms with 100 devices

---

## Files Summary

### Files to Create:
1. `app/Http/Controllers/System/DeviceManagementController.php` - Main controller
2. `database/seeders/SystemDevicePermissionsSeeder.php` - Permissions seeder
3. `resources/js/pages/System/TimekeepingDevices/Index.tsx` - Main page
4. `resources/js/components/system/device-table.tsx` - Device table component
5. `tests/Unit/Controllers/System/DeviceManagementControllerTest.php` - Unit tests

### Files to Modify:
1. `routes/system.php` - Add device management routes
2. `app/Models/RfidDevice.php` - May need additional scopes/accessors

---

**Implementation Priority:** HIGH  
**Blocking:** SYSTEM_DEVICE_REGISTRATION_IMPLEMENTATION.md (registration needs this list page)  
**Can Start:** Immediately (database schema exists)  
**Dependencies:** RfidDevice model exists, SuperAdmin role/middleware configured
