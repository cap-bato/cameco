<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\RfidLedger;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\LedgerHealthLog;
use App\Models\RfidDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class LedgerController extends Controller
{
    /**
     * Display the RFID ledger page with event stream.
     * 
     * Implements MVC pattern returning Inertia response (not part of API endpoints).
     * 
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $perPage = $request->get('per_page', 20);
        
        // Build query for rfid_ledger with filters (eager load relationships)
        $query = RfidLedger::with([
            'employee:id,employee_number,profile_id',
            'employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->orderBy('sequence_id', 'desc');
        
        // Apply filters
        if ($request->filled('date_from')) {
            $query->where('scan_timestamp', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        
        if ($request->filled('date_to')) {
            $query->where('scan_timestamp', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        
        if ($request->filled('device_id') && $request->device_id !== 'all') {
            $query->where('device_id', $request->device_id);
        }
        
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        
        if ($request->filled('employee_rfid')) {
            $query->where('employee_rfid', $request->employee_rfid);
        }
        
        if ($request->filled('employee_search')) {
            $search = $request->employee_search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        
        // Paginate
        $logs = $query->paginate($perPage);
        
        // Transform for frontend
        $transformedLogs = $logs->getCollection()->map(function ($log) {
            $employee = $log->employee;
            return [
                'id' => $log->id,
                'sequence_id' => $log->sequence_id,
                'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
                'event_type' => $log->event_type,
                'timestamp' => $log->scan_timestamp->toISOString(),
                'device_id' => $log->device_id,
                'device_location' => $log->device && $log->device->location ? $log->device->location : $log->device_id,
                'verified' => $log->processed,
                'rfid_card' => '****-' . substr($log->employee_rfid, -4),
                'hash_chain' => $log->hash_chain,
                'latency_ms' => null,
                'source' => 'edge_machine',
            ];
        });
        
        return Inertia::render('HR/Timekeeping/Ledger', [
            'logs' => [
                'data' => $transformedLogs,
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'next_page_url' => $logs->nextPageUrl(),
                'prev_page_url' => $logs->previousPageUrl(),
            ],
            'ledgerHealth' => $this->getLedgerHealth(),
            'devices' => $this->getDeviceStatus(),
            'filters' => [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'device_id' => $request->get('device_id'),
                'event_type' => $request->get('event_type'),
                'employee_rfid' => $request->get('employee_rfid'),
                'employee_search' => $request->get('employee_search'),
            ],
        ]);
    }

    /**
     * Show a single event detail by sequence ID.
     * 
     * Subtask 4.3.3: Return single ledger entry (Inertia response for page view)
     * Subtask 4.3.4: Permission check applied in routes (timekeeping.attendance.view)
     * 
     * @param int $sequenceId
     * @return Response
     */
    public function show(int $sequenceId): Response
    {
        // Permission check is handled by middleware in routes (4.3.4)
        
        $allLogs = $this->generateMockTimeLogs();
        $event = collect($allLogs)->firstWhere('sequence_id', $sequenceId);
        
        if (!$event) {
            abort(404, 'Event not found');
        }
        
        // Generate linked attendance_events record (4.3.5)
        $attendanceEvent = $this->generateLinkedAttendanceEvent($event);
        
        return Inertia::render('HR/Timekeeping/EventDetail', [
            'event' => $event,
            'attendanceEvent' => $attendanceEvent, // Linked attendance_events record
            'relatedEvents' => $this->getRelatedEvents($event),
        ]);
    }

    /**
     * API: Return ledger events as JSON (paginated).
     * 
     * Subtask 4.3.1: Pagination with 20 events per page
     * Subtask 4.3.2: Filtering by employee_rfid, device_id, date_range, event_type
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function events(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20); // Default 20 per page (4.3.1)
        $page = $request->get('page', 1);
        
        // Generate mock time logs
        $allLogs = $this->generateMockTimeLogs();
        
        // Apply filters from request (4.3.2: employee_rfid, device_id, date_range, event_type)
        $filteredLogs = $this->applyFilters($allLogs, $request);
        
        // Paginate results
        $logs = collect($filteredLogs)
            ->forPage($page, $perPage)
            ->values()
            ->toArray();
        
        // Generate pagination meta
        $total = count($filteredLogs);
        $lastPage = ceil($total / $perPage);
        
        return response()->json([
            'data' => $logs,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
            'links' => [
                'first' => route('timekeeping.api.ledger.events', ['page' => 1]),
                'last' => route('timekeeping.api.ledger.events', ['page' => $lastPage]),
                'next' => $page < $lastPage ? route('timekeeping.api.ledger.events', ['page' => $page + 1]) : null,
                'prev' => $page > 1 ? route('timekeeping.api.ledger.events', ['page' => $page - 1]) : null,
            ],
            'filters' => [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'device_id' => $request->get('device_id'),
                'event_type' => $request->get('event_type'),
                'employee_rfid' => $request->get('employee_rfid'),
                'employee_search' => $request->get('employee_search'),
            ],
        ]);
    }

    /**
     * API: Get a single event by sequence ID (JSON response).
     * 
     * Subtask 4.3.3: Return single ledger entry as JSON
     * Subtask 4.3.4: Permission check applied in routes (timekeeping.attendance.view)
     * Subtask 4.3.5: Return JSON with ledger fields + linked attendance_events record
     * 
     * @param int $sequenceId
     * @return JsonResponse
     */
    public function eventDetail(int $sequenceId): JsonResponse
    {
        // Permission check is handled by middleware in routes (4.3.4)
        
        $allLogs = $this->generateMockTimeLogs();
        $event = collect($allLogs)->firstWhere('sequence_id', $sequenceId);
        
        if (!$event) {
            return response()->json([
                'message' => 'Event not found',
                'error' => 'EVENT_NOT_FOUND',
            ], 404);
        }
        
        $relatedEvents = $this->getRelatedEvents($event);
        
        // Generate linked attendance_events record (4.3.5)
        $attendanceEvent = $this->generateLinkedAttendanceEvent($event);
        
        return response()->json([
            'success' => true,
            'data' => [
                'ledger_event' => $event, // Full ledger fields (4.3.5)
                'attendance_event' => $attendanceEvent, // Linked attendance_events record (4.3.5)
            ],
            'related' => [
                'previous' => $relatedEvents['previous'] ?? null,
                'next' => $relatedEvents['next'] ?? null,
                'employee_today' => array_values($relatedEvents['employee_today'] ?? []),
            ],
            'links' => [
                'self' => route('timekeeping.api.ledger.event', ['sequenceId' => $sequenceId]),
                'previous' => isset($relatedEvents['previous']) 
                    ? route('timekeeping.api.ledger.event', ['sequenceId' => $relatedEvents['previous']['sequence_id']]) 
                    : null,
                'next' => isset($relatedEvents['next']) 
                    ? route('timekeeping.api.ledger.event', ['sequenceId' => $relatedEvents['next']['sequence_id']]) 
                    : null,
            ],
        ]);
    }

    /**
     * Generate mock time logs (50+ events).
     * 
     * @return array
     */
    private function generateMockTimeLogs(): array
    {
        $logs = [];
        $employees = [
            ['id' => 'EMP-001', 'name' => 'Juan Dela Cruz'],
            ['id' => 'EMP-002', 'name' => 'Maria Santos'],
            ['id' => 'EMP-003', 'name' => 'Pedro Garcia'],
            ['id' => 'EMP-004', 'name' => 'Ana Reyes'],
            ['id' => 'EMP-005', 'name' => 'Jose Mendoza'],
            ['id' => 'EMP-006', 'name' => 'Rosa Martinez'],
            ['id' => 'EMP-007', 'name' => 'Carlos Lopez'],
            ['id' => 'EMP-008', 'name' => 'Linda Torres'],
            ['id' => 'EMP-009', 'name' => 'Miguel Rivera'],
            ['id' => 'EMP-010', 'name' => 'Sofia Flores'],
        ];
        
        $devices = [
            ['id' => 'GATE-01', 'location' => 'Gate 1 - Main Entrance'],
            ['id' => 'GATE-02', 'location' => 'Gate 2 - Side Entrance'],
            ['id' => 'CAFETERIA-01', 'location' => 'Cafeteria'],
            ['id' => 'WAREHOUSE-01', 'location' => 'Warehouse Entry'],
            ['id' => 'OFFICE-01', 'location' => 'Office Floor'],
        ];
        
        $eventTypes = ['time_in', 'time_out', 'break_start', 'break_end'];
        
        $sequenceId = 12345;
        $baseTime = now()->startOfDay()->addHours(7); // Start at 7 AM
        
        // Generate 60 events over the course of a day
        for ($i = 0; $i < 60; $i++) {
            $employee = $employees[array_rand($employees)];
            $device = $devices[array_rand($devices)];
            $eventType = $eventTypes[array_rand($eventTypes)];
            
            // Distribute events throughout the day
            $timestamp = $baseTime->copy()->addMinutes($i * 15 + rand(-5, 5));
            
            $logs[] = [
                'id' => $i + 1,
                'sequence_id' => $sequenceId++,
                'employee_id' => $employee['id'],
                'employee_name' => $employee['name'],
                'event_type' => $eventType,
                'timestamp' => $timestamp->toISOString(),
                'device_id' => $device['id'],
                'device_location' => $device['location'],
                'verified' => rand(1, 100) > 5, // 95% verified
                'rfid_card' => '****-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'hash_chain' => bin2hex(random_bytes(16)),
                'latency_ms' => rand(50, 500),
                'source' => 'edge_machine',
            ];
        }
        
        return $logs;
    }

    /**
     * Get real ledger health status from database.
     * 
     * @return array
     */
    private function getLedgerHealth(): array
    {
        // Get latest ledger entry
        $latestLedger = RfidLedger::orderBy('sequence_id', 'desc')->first();
        
        // Get today's event count
        $eventsToday = RfidLedger::whereDate('scan_timestamp', today())->count();
        
        // Get device counts
        $devicesOnline = RfidDevice::where('status', 'online')->count();
        $devicesOffline = RfidDevice::whereIn('status', ['offline', 'maintenance'])->count();
        
        // Get unprocessed count (queue depth)
        $queueDepth = RfidLedger::where('processed', false)->count();
        
        // Get latest health log if available
        $latestHealthLog = LedgerHealthLog::orderBy('created_at', 'desc')->first();
        
        // Calculate events per hour (last hour)
        $eventsLastHour = RfidLedger::where('scan_timestamp', '>=', now()->subHour())->count();
        
        // Determine health status
        $status = 'healthy';
        if ($queueDepth > 1000) {
            $status = 'critical';
        } elseif ($queueDepth > 500 || $devicesOffline > 1) {
            $status = 'degraded';
        }
        
        return [
            'status' => $status,
            'last_sequence_id' => $latestLedger ? $latestLedger->sequence_id : 0,
            'events_today' => $eventsToday,
            'devices_online' => $devicesOnline,
            'devices_offline' => $devicesOffline,
            'last_sync' => $latestLedger ? $latestLedger->created_at->toISOString() : now()->toISOString(),
            'avg_latency_ms' => 125, // TODO: Calculate from actual metrics
            'hash_verification' => [
                'total_checked' => $eventsToday,
                'passed' => $eventsToday, // TODO: Calculate from hash validation results
                'failed' => 0,
            ],
            'performance' => [
                'events_per_hour' => $eventsLastHour,
                'avg_processing_time_ms' => 45, // TODO: Calculate from processing metrics
                'queue_depth' => $queueDepth,
            ],
            'alerts' => $latestHealthLog ? $latestHealthLog->alerts ?? [] : [],
        ];
    }

    /**
     * Get real device status from database.
     * 
     * @return array
     */
    private function getDeviceStatus(): array
    {
        $devices = RfidDevice::all();
        
        return $devices->map(function ($device) {
            // Get today's event count for this device
            $eventsToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', today())
                ->count();
            
            // Calculate uptime percentage (simplified - based on last heartbeat)
            $minutesSinceHeartbeat = $device->last_heartbeat ? 
                now()->diffInMinutes($device->last_heartbeat) : 9999;
            $uptimePercentage = $minutesSinceHeartbeat < 10 ? 99.5 : 
                ($minutesSinceHeartbeat < 60 ? 95.0 : 85.0);
            
            return [
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'location' => $device->location,
                'status' => $device->status,
                'last_heartbeat' => $device->last_heartbeat ? 
                    $device->last_heartbeat->toISOString() : 
                    now()->subHours(24)->toISOString(),
                'events_today' => $eventsToday,
                'uptime_percentage' => $uptimePercentage,
            ];
        })->toArray();
    }

    /**
     * Apply filters to logs collection.
     * 
     * Subtask 4.3.2: Support filtering by employee_rfid, device_id, date_range, event_type
     * 
     * @param array $logs
     * @param Request $request
     * @return array
     */
    private function applyFilters(array $logs, Request $request): array
    {
        $filtered = $logs;
        
        // Date range filter (date_range: date_from and date_to)
        if ($request->has('date_from')) {
            $dateFrom = \Carbon\Carbon::parse($request->get('date_from'))->startOfDay();
            $filtered = array_filter($filtered, function ($log) use ($dateFrom) {
                return \Carbon\Carbon::parse($log['timestamp'])->gte($dateFrom);
            });
        }
        
        if ($request->has('date_to')) {
            $dateTo = \Carbon\Carbon::parse($request->get('date_to'))->endOfDay();
            $filtered = array_filter($filtered, function ($log) use ($dateTo) {
                return \Carbon\Carbon::parse($log['timestamp'])->lte($dateTo);
            });
        }
        
        // Device filter (device_id)
        if ($request->has('device_id') && $request->get('device_id') !== 'all' && $request->get('device_id') !== '') {
            $deviceId = $request->get('device_id');
            $filtered = array_filter($filtered, function ($log) use ($deviceId) {
                return $log['device_id'] === $deviceId;
            });
        }
        
        // Event type filter (event_type)
        if ($request->has('event_type') && $request->get('event_type') !== '' && $request->get('event_type') !== 'all') {
            $eventType = $request->get('event_type');
            $filtered = array_filter($filtered, function ($log) use ($eventType) {
                return $log['event_type'] === $eventType;
            });
        }
        
        // Employee RFID filter (employee_rfid) - NEW for 4.3.2
        if ($request->has('employee_rfid') && $request->get('employee_rfid') !== '' && $request->get('employee_rfid') !== 'all') {
            $employeeRfid = $request->get('employee_rfid');
            $filtered = array_filter($filtered, function ($log) use ($employeeRfid) {
                return $log['employee_id'] === $employeeRfid;
            });
        }
        
        // Employee search filter (for backward compatibility and free-text search)
        if ($request->has('employee_search') && $request->get('employee_search')) {
            $search = strtolower($request->get('employee_search'));
            $filtered = array_filter($filtered, function ($log) use ($search) {
                return str_contains(strtolower($log['employee_name']), $search) ||
                       str_contains(strtolower($log['employee_id']), $search) ||
                       str_contains(strtolower($log['rfid_card']), $search);
            });
        }
        
        return array_values($filtered);
    }

    /**
     * Get related events for a specific event.
     * 
     * @param array $event
     * @return array
     */
    private function getRelatedEvents(array $event): array
    {
        $allLogs = $this->generateMockTimeLogs();
        
        // Get previous and next events in sequence
        $currentIndex = array_search($event['sequence_id'], array_column($allLogs, 'sequence_id'));
        
        $related = [];
        
        if ($currentIndex > 0) {
            $related['previous'] = $allLogs[$currentIndex - 1];
        }
        
        if ($currentIndex < count($allLogs) - 1) {
            $related['next'] = $allLogs[$currentIndex + 1];
        }
        
        // Get same employee events today
        $related['employee_today'] = array_filter($allLogs, function ($log) use ($event) {
            return $log['employee_id'] === $event['employee_id'] &&
                   \Carbon\Carbon::parse($log['timestamp'])->isToday();
        });
        
        return $related;
    }

    /**
     * Generate linked attendance_events record for a ledger event.
     * 
     * Subtask 4.3.5: Generate mock attendance_events record linked to ledger entry.
     * In production, this would query the attendance_events table using ledger_sequence_id.
     * 
     * @param array $ledgerEvent
     * @return array|null
     */
    private function generateLinkedAttendanceEvent(array $ledgerEvent): ?array
    {
        // Simulate processing: not all ledger events have been processed into attendance_events yet
        $isProcessed = $ledgerEvent['verified'] && rand(1, 100) > 10; // 90% processed if verified
        
        if (!$isProcessed) {
            return null; // Event not yet processed into attendance_events
        }
        
        return [
            'id' => rand(1000, 9999),
            'ledger_sequence_id' => $ledgerEvent['sequence_id'], // Links back to ledger
            'employee_id' => $ledgerEvent['employee_id'],
            'employee_name' => $ledgerEvent['employee_name'],
            'event_type' => $ledgerEvent['event_type'],
            'recorded_at' => $ledgerEvent['timestamp'],
            'device_id' => $ledgerEvent['device_id'],
            'device_location' => $ledgerEvent['device_location'],
            'source' => $ledgerEvent['source'],
            'is_deduplicated' => false,
            'ledger_hash_verified' => $ledgerEvent['verified'],
            'attendance_date' => \Carbon\Carbon::parse($ledgerEvent['timestamp'])->toDateString(),
            'processed_at' => \Carbon\Carbon::parse($ledgerEvent['timestamp'])->addSeconds(rand(5, 300))->toISOString(),
            'notes' => $ledgerEvent['verified'] ? 'Automatically processed from ledger' : 'Manual verification required',
            'created_at' => \Carbon\Carbon::parse($ledgerEvent['timestamp'])->addSeconds(rand(1, 60))->toISOString(),
            'updated_at' => \Carbon\Carbon::parse($ledgerEvent['timestamp'])->addSeconds(rand(1, 60))->toISOString(),
        ];
    }
}
