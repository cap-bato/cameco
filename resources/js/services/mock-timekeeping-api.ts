/**
 * Mock Timekeeping API Service
 * 
 * Simulates backend API responses for Timekeeping module frontend development.
 * Implements Task 2.1.1 and 2.1.2:
 * - All required API functions with realistic data structures
 * - API latency simulation (200-500ms)
 * 
 * This service will be replaced with real API calls in Phase 3.
 */

import { 
    AttendanceEvent, 
    EdgeMachineDevice, 
    EmployeeBasic,
    AttendanceRecord,
    EventType,
    AttendanceSource,
    EdgeDeviceStatus,
    AttendanceStatus
} from '@/types/timekeeping-pages';

// ============================================================================
// TYPES & INTERFACES
// ============================================================================

export interface TimeLogFilters {
    date_from?: string;
    date_to?: string;
    employee_id?: number;
    device_id?: string;
    event_type?: EventType;
    source?: AttendanceSource;
    page?: number;
    per_page?: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

export interface LedgerHealthStatus {
    status: 'healthy' | 'warning' | 'critical';
    metrics: {
        total_events: number;
        processed_events: number;
        pending_events: number;
        failed_events: number;
        last_sequence_id: number;
        last_processed_at: string | null;
        hash_chain_intact: boolean;
        device_sync_status: {
            online: number;
            offline: number;
            maintenance: number;
        };
    };
    alerts: Array<{
        severity: 'info' | 'warning' | 'error';
        message: string;
        timestamp: string;
    }>;
    performance: {
        avg_processing_time_ms: number;
        events_per_hour: number;
        ledger_size_mb: number;
    };
}

export interface EmployeeTimeline {
    employee: EmployeeBasic;
    date: string;
    events: AttendanceEvent[];
    summary: {
        time_in?: string;
        time_out?: string;
        break_start?: string;
        break_end?: string;
        total_hours: number;
        status: AttendanceStatus;
        is_late: boolean;
        late_minutes: number;
    };
    violations: Array<{
        type: 'late_arrival' | 'early_departure' | 'missing_punch' | 'unauthorized_break';
        message: string;
        timestamp: string;
    }>;
}

export interface EventDetail extends AttendanceEvent {
    employee: EmployeeBasic;
    ledger_metadata: {
        sequence_id: number;
        hash: string;
        prev_hash: string;
        signature?: string;
        verified: boolean;
    };
    device: EdgeMachineDevice | null;
    processing_metadata: {
        processed_at: string;
        processing_time_ms: number;
        duplicate_check_passed: boolean;
        reconciliation_status: 'matched' | 'conflict' | 'pending';
    };
}

// ============================================================================
// MOCK DATA GENERATORS
// ============================================================================

/**
 * Generate mock employees
 */
const generateMockEmployees = (): EmployeeBasic[] => {
    const departments = [
        { id: 1, name: 'Manufacturing' },
        { id: 2, name: 'Quality Control' },
        { id: 3, name: 'Logistics' },
        { id: 4, name: 'Administration' },
    ];

    const employees: EmployeeBasic[] = [];
    let empNumber = 2025001;

    for (let i = 0; i < 50; i++) {
        const dept = departments[i % departments.length];
        employees.push({
            id: i + 1,
            name: `Employee ${String(i + 1).padStart(2, '0')}`,
            employee_number: `EMP-${empNumber++}`,
            department_id: dept.id,
            department_name: dept.name,
            position: i % 3 === 0 ? 'Supervisor' : 'Staff',
            schedule_id: 1,
            schedule_name: 'Regular Day Shift (08:00-17:00)',
        });
    }

    return employees;
};

const MOCK_EMPLOYEES = generateMockEmployees();

/**
 * Generate mock edge devices
 */
const generateMockDevices = (): EdgeMachineDevice[] => {
    return [
        {
            id: 'DEVICE-001',
            name: 'Main Gate Scanner',
            location: 'Main Entrance Gate',
            status: 'online',
            last_sync: new Date().toISOString(),
            total_taps_today: 156,
        },
        {
            id: 'DEVICE-002',
            name: 'Production Floor Scanner',
            location: 'Production Building A',
            status: 'online',
            last_sync: new Date(Date.now() - 300000).toISOString(),
            total_taps_today: 89,
        },
        {
            id: 'DEVICE-003',
            name: 'Warehouse Scanner',
            location: 'Warehouse Entrance',
            status: 'online',
            last_sync: new Date(Date.now() - 120000).toISOString(),
            total_taps_today: 67,
        },
        {
            id: 'DEVICE-004',
            name: 'Admin Building Scanner',
            location: 'Admin Building Lobby',
            status: 'offline',
            last_sync: new Date(Date.now() - 7200000).toISOString(),
            total_taps_today: 23,
        },
        {
            id: 'DEVICE-005',
            name: 'Loading Dock Scanner',
            location: 'Loading Dock B',
            status: 'maintenance',
            last_sync: new Date(Date.now() - 86400000).toISOString(),
            total_taps_today: 0,
        },
    ];
};

const MOCK_DEVICES = generateMockDevices();

/**
 * Generate mock time log events
 */
const generateMockTimeLogs = (): AttendanceEvent[] => {
    const events: AttendanceEvent[] = [];
    const today = new Date();
    const baseDate = today.toISOString().split('T')[0];
    
    let eventId = 1;
    
    // Generate events for the past 7 days
    for (let dayOffset = 0; dayOffset < 7; dayOffset++) {
        const date = new Date(today);
        date.setDate(date.getDate() - dayOffset);
        const dateStr = date.toISOString().split('T')[0];
        
        // Generate events for 30 random employees per day
        const dailyEmployees = MOCK_EMPLOYEES.slice(0, 30 + Math.floor(Math.random() * 10));
        
        dailyEmployees.forEach((employee, idx) => {
            const device = MOCK_DEVICES[idx % MOCK_DEVICES.length];
            
            // Time in (07:45 - 08:30)
            const timeInHour = 7 + (Math.random() > 0.7 ? 1 : 0);
            const timeInMinute = Math.floor(Math.random() * 60);
            events.push({
                id: eventId++,
                attendance_record_id: Math.floor(eventId / 4),
                event_type: 'time_in',
                timestamp: `${dateStr} ${String(timeInHour).padStart(2, '0')}:${String(timeInMinute).padStart(2, '0')}:00`,
                source: 'edge_machine',
                device_id: device.id,
                device_location: device.location,
            });
            
            // Break start (12:00 - 12:15)
            if (Math.random() > 0.2) {
                const breakStartMinute = Math.floor(Math.random() * 15);
                events.push({
                    id: eventId++,
                    attendance_record_id: Math.floor(eventId / 4),
                    event_type: 'break_start',
                    timestamp: `${dateStr} 12:${String(breakStartMinute).padStart(2, '0')}:00`,
                    source: 'edge_machine',
                    device_id: device.id,
                    device_location: device.location,
                });
                
                // Break end (13:00 - 13:15)
                const breakEndMinute = Math.floor(Math.random() * 15);
                events.push({
                    id: eventId++,
                    attendance_record_id: Math.floor(eventId / 4),
                    event_type: 'break_end',
                    timestamp: `${dateStr} 13:${String(breakEndMinute).padStart(2, '0')}:00`,
                    source: 'edge_machine',
                    device_id: device.id,
                    device_location: device.location,
                });
            }
            
            // Time out (17:00 - 18:00)
            const timeOutHour = 17 + (Math.random() > 0.5 ? 1 : 0);
            const timeOutMinute = Math.floor(Math.random() * 60);
            events.push({
                id: eventId++,
                attendance_record_id: Math.floor(eventId / 4),
                event_type: 'time_out',
                timestamp: `${dateStr} ${String(timeOutHour).padStart(2, '0')}:${String(timeOutMinute).padStart(2, '0')}:00`,
                source: 'edge_machine',
                device_id: device.id,
                device_location: device.location,
            });
        });
    }
    
    return events.sort((a, b) => b.timestamp.localeCompare(a.timestamp));
};

let MOCK_TIME_LOGS = generateMockTimeLogs();

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Simulate API latency (200-500ms random delay)
 * Implements Task 2.1.2
 */
const simulateLatency = (): Promise<void> => {
    const delay = Math.floor(Math.random() * 300) + 200; // 200-500ms
    return new Promise(resolve => setTimeout(resolve, delay));
};

/**
 * Simulate network errors (5% chance)
 * Implements Task 2.1.5
 * 
 * Randomly throws realistic network/server errors to test error handling in components
 */
const simulateNetworkError = (): void => {
    const shouldError = Math.random() < 0.05; // 5% chance
    
    if (shouldError) {
        const errorTypes = [
            {
                name: 'NetworkError',
                message: 'Failed to fetch: Network request failed',
                status: 0,
            },
            {
                name: 'TimeoutError',
                message: 'Request timeout: The request took too long to complete',
                status: 408,
            },
            {
                name: 'ServerError',
                message: 'Internal Server Error: An unexpected error occurred',
                status: 500,
            },
            {
                name: 'ServiceUnavailable',
                message: 'Service Unavailable: The server is temporarily unavailable',
                status: 503,
            },
            {
                name: 'BadGateway',
                message: 'Bad Gateway: Invalid response from upstream server',
                status: 502,
            },
        ];
        
        const randomError = errorTypes[Math.floor(Math.random() * errorTypes.length)];
        
        const error = new Error(randomError.message);
        error.name = randomError.name;
        (error as any).status = randomError.status;
        
        throw error;
    }
};

/**
 * Generate random hash for ledger metadata
 */
const generateHash = (): string => {
    return Array.from({ length: 64 }, () => 
        Math.floor(Math.random() * 16).toString(16)
    ).join('');
};

/**
 * Apply filters to time logs
 */
const applyFilters = (logs: AttendanceEvent[], filters: TimeLogFilters): AttendanceEvent[] => {
    let filtered = [...logs];
    
    if (filters.date_from) {
        filtered = filtered.filter(log => log.timestamp >= filters.date_from!);
    }
    
    if (filters.date_to) {
        filtered = filtered.filter(log => log.timestamp <= filters.date_to!);
    }
    
    if (filters.device_id) {
        filtered = filtered.filter(log => log.device_id === filters.device_id);
    }
    
    if (filters.event_type) {
        filtered = filtered.filter(log => log.event_type === filters.event_type);
    }
    
    if (filters.source) {
        filtered = filtered.filter(log => log.source === filters.source);
    }
    
    return filtered;
};

/**
 * Paginate array
 */
const paginate = <T>(items: T[], page: number, perPage: number): PaginatedResponse<T> => {
    const total = items.length;
    const lastPage = Math.ceil(total / perPage);
    const from = (page - 1) * perPage;
    const to = Math.min(from + perPage, total);
    
    return {
        data: items.slice(from, to),
        meta: {
            current_page: page,
            from: from + 1,
            last_page: lastPage,
            per_page: perPage,
            to: to,
            total: total,
        },
    };
};

// ============================================================================
// MOCK API FUNCTIONS (Task 2.1.1)
// ============================================================================

/**
 * Fetch paginated time logs with filters
 * Implements Task 2.1.1, 2.1.2, 2.1.5
 */
export const fetchTimeLogs = async (
    filters: TimeLogFilters = {}
): Promise<PaginatedResponse<AttendanceEvent>> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    
    const page = filters.page || 1;
    const perPage = filters.per_page || 20;
    
    // Apply filters
    const filtered = applyFilters(MOCK_TIME_LOGS, filters);
    
    // Paginate
    return paginate(filtered, page, perPage);
};

/**
 * Fetch ledger health status
 * Implements Task 2.1.1, 2.1.2, 2.1.5
 */
export const fetchLedgerHealth = async (): Promise<LedgerHealthStatus> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    
    const totalEvents = MOCK_TIME_LOGS.length;
    const pendingEvents = Math.floor(Math.random() * 10);
    const failedEvents = Math.random() > 0.9 ? Math.floor(Math.random() * 3) : 0;
    
    const onlineDevices = MOCK_DEVICES.filter(d => d.status === 'online').length;
    const offlineDevices = MOCK_DEVICES.filter(d => d.status === 'offline').length;
    const maintenanceDevices = MOCK_DEVICES.filter(d => d.status === 'maintenance').length;
    
    const alerts: LedgerHealthStatus['alerts'] = [];
    
    if (offlineDevices > 0) {
        alerts.push({
            severity: 'warning',
            message: `${offlineDevices} device(s) offline`,
            timestamp: new Date().toISOString(),
        });
    }
    
    if (failedEvents > 0) {
        alerts.push({
            severity: 'error',
            message: `${failedEvents} event(s) failed processing`,
            timestamp: new Date().toISOString(),
        });
    }
    
    if (pendingEvents > 5) {
        alerts.push({
            severity: 'warning',
            message: `${pendingEvents} events pending processing`,
            timestamp: new Date().toISOString(),
        });
    }
    
    const status: 'healthy' | 'warning' | 'critical' = 
        failedEvents > 0 ? 'critical' :
        (offlineDevices > 1 || pendingEvents > 5) ? 'warning' :
        'healthy';
    
    return {
        status,
        metrics: {
            total_events: totalEvents,
            processed_events: totalEvents - pendingEvents - failedEvents,
            pending_events: pendingEvents,
            failed_events: failedEvents,
            last_sequence_id: totalEvents + 1000,
            last_processed_at: new Date(Date.now() - 30000).toISOString(),
            hash_chain_intact: Math.random() > 0.05,
            device_sync_status: {
                online: onlineDevices,
                offline: offlineDevices,
                maintenance: maintenanceDevices,
            },
        },
        alerts,
        performance: {
            avg_processing_time_ms: Math.floor(Math.random() * 50) + 10,
            events_per_hour: Math.floor(Math.random() * 100) + 50,
            ledger_size_mb: Math.floor(Math.random() * 500) + 100,
        },
    };
};

/**
 * Fetch employee timeline for a specific date
 * Implements Task 2.1.1, 2.1.2, 2.1.5
 */
export const fetchEmployeeTimeline = async (
    employeeId: number,
    date: string
): Promise<EmployeeTimeline> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    
    const employee = MOCK_EMPLOYEES.find(e => e.id === employeeId) || MOCK_EMPLOYEES[0];
    
    // Get events for this employee on this date
    const events = MOCK_TIME_LOGS.filter(log => 
        log.timestamp.startsWith(date)
    ).slice(0, 4); // Simulate having events for this employee
    
    // Calculate summary
    const timeIn = events.find(e => e.event_type === 'time_in');
    const timeOut = events.find(e => e.event_type === 'time_out');
    const breakStart = events.find(e => e.event_type === 'break_start');
    const breakEnd = events.find(e => e.event_type === 'break_end');
    
    const timeInHour = timeIn ? parseInt(timeIn.timestamp.split(' ')[1].split(':')[0]) : 8;
    const timeInMinute = timeIn ? parseInt(timeIn.timestamp.split(' ')[1].split(':')[1]) : 0;
    const isLate = timeInHour > 8 || (timeInHour === 8 && timeInMinute > 5);
    const lateMinutes = isLate ? (timeInHour - 8) * 60 + timeInMinute - 5 : 0;
    
    const totalHours = timeIn && timeOut ? 8 + Math.random() * 2 : 0;
    
    const violations: EmployeeTimeline['violations'] = [];
    if (isLate) {
        violations.push({
            type: 'late_arrival',
            message: `Late arrival by ${lateMinutes} minutes`,
            timestamp: timeIn?.timestamp || '',
        });
    }
    
    return {
        employee,
        date,
        events,
        summary: {
            time_in: timeIn?.timestamp.split(' ')[1],
            time_out: timeOut?.timestamp.split(' ')[1],
            break_start: breakStart?.timestamp.split(' ')[1],
            break_end: breakEnd?.timestamp.split(' ')[1],
            total_hours: Math.round(totalHours * 100) / 100,
            status: isLate ? 'late' : 'present',
            is_late: isLate,
            late_minutes: lateMinutes,
        },
        violations,
    };
};

/**
 * Fetch device status list
 * Implements Task 2.1.1, 2.1.2, 2.1.5
 */
export const fetchDeviceStatus = async (): Promise<EdgeMachineDevice[]> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    
    // Randomly update device status to simulate real-time changes
    return MOCK_DEVICES.map(device => ({
        ...device,
        last_sync: device.status === 'online' 
            ? new Date(Date.now() - Math.random() * 300000).toISOString()
            : device.last_sync,
        total_taps_today: device.status === 'online'
            ? device.total_taps_today! + Math.floor(Math.random() * 5)
            : device.total_taps_today,
    }));
};

/**
 * Fetch full event detail with ledger metadata
 * Implements Task 2.1.1, 2.1.2, 2.1.5
 */
export const fetchEventDetail = async (sequenceId: number): Promise<EventDetail> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    
    // Find event by ID (using id as sequence for mock)
    const event = MOCK_TIME_LOGS.find(log => log.id === sequenceId) || MOCK_TIME_LOGS[0];
    
    const employee = MOCK_EMPLOYEES[Math.floor(Math.random() * MOCK_EMPLOYEES.length)];
    const device = MOCK_DEVICES.find(d => d.id === event.device_id) || null;
    
    const prevHash = generateHash();
    const currentHash = generateHash();
    
    return {
        ...event,
        employee,
        ledger_metadata: {
            sequence_id: event.id,
            hash: currentHash,
            prev_hash: prevHash,
            signature: Math.random() > 0.5 ? generateHash().slice(0, 128) : undefined,
            verified: Math.random() > 0.05,
        },
        device,
        processing_metadata: {
            processed_at: new Date(new Date(event.timestamp).getTime() + 5000).toISOString(),
            processing_time_ms: Math.floor(Math.random() * 100) + 10,
            duplicate_check_passed: true,
            reconciliation_status: Math.random() > 0.1 ? 'matched' : 'pending',
        },
    };
};

// ============================================================================
// ADDITIONAL HELPER FUNCTIONS
// ============================================================================

/**
 * Get all employees (for dropdowns, filters)
 * Implements Task 2.1.2, 2.1.5
 */
export const fetchEmployees = async (): Promise<EmployeeBasic[]> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    return MOCK_EMPLOYEES;
};

/**
 * Get all devices (for dropdowns, filters)
 * Implements Task 2.1.2, 2.1.5
 */
export const fetchDevices = async (): Promise<EdgeMachineDevice[]> => {
    await simulateLatency();
    simulateNetworkError(); // 5% chance of error
    return MOCK_DEVICES;
};

/**
 * Refresh mock data (useful for testing)
 */
export const refreshMockData = (): void => {
    MOCK_TIME_LOGS = generateMockTimeLogs();
};
