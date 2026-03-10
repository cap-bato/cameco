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
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
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
            $query->whereHas('rfidCardMapping.employee.profile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('rfidCardMapping.employee', function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%");
            });
        }
        
        // Paginate
        $logs = $query->paginate($perPage);
        
        // Transform for frontend
        $transformedLogs = $logs->getCollection()->map(function ($log) {
            $employee = $log->rfidCardMapping ? $log->rfidCardMapping->employee : null;
            return [
                'id' => $log->id,
                'sequence_id' => $log->sequence_id,
                'employee_rfid' => $log->employee_rfid,
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
     * Task 1.1: Replace mock implementation with real database query
     * 
     * @param int $sequenceId
     * @return Response
     */
    public function show(int $sequenceId): Response
    {
        // Permission check is handled by middleware in routes (4.3.4)
        
        // Query real ledger entry by sequence_id
        $ledgerEntry = RfidLedger::with([
            'rfidCardMapping:id,employee_id,card_uid',
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->where('sequence_id', $sequenceId)
          ->first();
        
        if (!$ledgerEntry) {
            abort(404, 'Ledger event not found');
        }
        
        // Transform ledger entry to event format
        $employee = $ledgerEntry->rfidCardMapping ? $ledgerEntry->rfidCardMapping->employee : null;
        $event = [
            'id' => $ledgerEntry->id,
            'sequence_id' => $ledgerEntry->sequence_id,
            'employee_rfid' => $ledgerEntry->employee_rfid,
            'employee_id' => $employee ? $employee->employee_number : 'Unknown',
            'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
            'event_type' => $ledgerEntry->event_type,
            'timestamp' => $ledgerEntry->scan_timestamp->toISOString(),
            'device_id' => $ledgerEntry->device_id,
            'device_location' => $ledgerEntry->device && $ledgerEntry->device->location ? $ledgerEntry->device->location : $ledgerEntry->device_id,
            'verified' => $ledgerEntry->processed,
            'rfid_card' => '****-' . substr($ledgerEntry->employee_rfid, -4),
            'hash_chain' => $ledgerEntry->hash_chain,
            'latency_ms' => $ledgerEntry->latency_ms ?? null,
            'source' => 'edge_machine',
        ];
        
        // Get linked attendance event (real query, not mock)
        $attendanceEvent = $this->getLinkedAttendanceEvent($ledgerEntry->sequence_id);
        
        // Get related events (real query, not mock)
        $relatedEvents = $this->getRelatedEventsReal($ledgerEntry);
        
        return Inertia::render('HR/Timekeeping/EventDetail', [
            'event' => $event,
            'attendanceEvent' => $attendanceEvent, // Linked attendance_events record
            'relatedEvents' => $relatedEvents,
        ]);
    }

    /**
     * API: Return ledger events as JSON (paginated).
     * 
     * Task 2.1: Replace mock API with real database queries
     * Subtask 4.3.1: Pagination with 20 events per page
     * Subtask 4.3.2: Filtering by employee_rfid, device_id, date_range, event_type
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function events(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20); // Default 20 per page (4.3.1)
        
        // Build query for rfid_ledger with filters (eager load relationships for performance)
        $query = RfidLedger::with([
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->orderBy('sequence_id', 'desc');
        
        // Apply filters (4.3.2: employee_rfid, device_id, date_range, event_type)
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
            $query->whereHas('rfidCardMapping.employee.profile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('rfidCardMapping.employee', function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%");
            });
        }
        
        // Paginate results using Eloquent pagination
        $logs = $query->paginate($perPage);
        
        // Transform for API response
        $transformedLogs = $logs->getCollection()->map(function ($log) {
            $employee = $log->rfidCardMapping ? $log->rfidCardMapping->employee : null;
            return [
                'id' => $log->id,
                'sequence_id' => $log->sequence_id,
                'employee_rfid' => $log->employee_rfid,
                'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
                'event_type' => $log->event_type,
                'timestamp' => $log->scan_timestamp->toISOString(),
                'device_id' => $log->device_id,
                'device_location' => $log->device && $log->device->location ? $log->device->location : $log->device_id,
                'verified' => $log->processed,
                'rfid_card' => '****-' . substr($log->employee_rfid, -4),
                'hash_chain' => $log->hash_chain,
                'latency_ms' => $log->latency_ms ?? null,
                'source' => 'edge_machine',
            ];
        });
        
        return response()->json([
            'data' => $transformedLogs,
            'current_page' => $logs->currentPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
            'last_page' => $logs->lastPage(),
            'from' => $logs->firstItem(),
            'to' => $logs->lastItem(),
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'next' => $logs->nextPageUrl(),
                'prev' => $logs->previousPageUrl(),
            ],
            'filters' => $request->only(['date_from', 'date_to', 'device_id', 'event_type', 'employee_rfid', 'employee_search']),
        ]);
    }

    /**
     * API: Get a single event by sequence ID (JSON response).
     * 
     * Task 2.2: Replace mock API with real database query
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
        
        // Query real ledger entry by sequence_id
        $ledgerEntry = RfidLedger::with([
            'employee:id,employee_number,profile_id',
            'employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->where('sequence_id', $sequenceId)
          ->first();
        
        if (!$ledgerEntry) {
            return response()->json([
                'message' => 'Event not found',
                'error' => 'EVENT_NOT_FOUND',
            ], 404);
        }
        
        // Transform to event format
        $employee = $ledgerEntry->rfidCardMapping ? $ledgerEntry->rfidCardMapping->employee : null;
        $event = [
            'id' => $ledgerEntry->id,
            'sequence_id' => $ledgerEntry->sequence_id,
            'employee_id' => $employee ? $employee->employee_number : 'Unknown',
            'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
            'event_type' => $ledgerEntry->event_type,
            'timestamp' => $ledgerEntry->scan_timestamp->toISOString(),
            'device_id' => $ledgerEntry->device_id,
            'device_location' => $ledgerEntry->device && $ledgerEntry->device->location ? $ledgerEntry->device->location : $ledgerEntry->device_id,
            'verified' => $ledgerEntry->processed,
            'rfid_card' => '****-' . substr($ledgerEntry->employee_rfid, -4),
            'hash_chain' => $ledgerEntry->hash_chain,
            'latency_ms' => $ledgerEntry->latency_ms ?? null,
            'source' => 'edge_machine',
        ];
        
        // Get linked attendance event (real query, not mock)
        $attendanceEvent = $this->getLinkedAttendanceEvent($ledgerEntry->sequence_id);
        
        // Get related events (real query, not mock)
        $relatedEvents = $this->getRelatedEventsReal($ledgerEntry);
        
        return response()->json([
            'success' => true,
            'data' => [
                'ledger_event' => $event, // Full ledger fields from real database (4.3.5)
                'attendance_event' => $attendanceEvent, // Linked attendance_events record (4.3.5)
            ],
            'related' => [
                'previous' => $relatedEvents['previous'] ?? null,
                'next' => $relatedEvents['next'] ?? null,
                'employee_today' => $relatedEvents['employee_today'] ?? [],
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
     * Get real ledger health status from database.
     * 
     * Task 4.1: Add Real Metric Calculations
     * - Calculates avg_latency_ms from today's events
     * - Calculates avg_processing_time_ms from processed attendance events
     * - Calculates hash verification failures from database
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
        
        // Task 4.1.1: Calculate average latency from today's events
        $avgLatency = RfidLedger::whereDate('scan_timestamp', today())
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');
        $avgLatencyMs = $avgLatency ? round($avgLatency, 0) : 0;
        
        // Task 4.1.2: Calculate average processing time from rfid_ledger table
        // Processing time = when ledger entry was processed (processed_at) - when scan occurred (scan_timestamp)
        // Database-agnostic approach: Calculate in PHP instead of using database-specific functions
        $processingTimes = RfidLedger::whereDate('scan_timestamp', today())
            ->whereNotNull('processed_at')
            ->get(['scan_timestamp', 'processed_at']);
        
        $avgProcessingTimeMs = 0;
        if ($processingTimes->count() > 0) {
            $totalMs = $processingTimes->sum(function ($log) {
                return $log->processed_at->diffInMilliseconds($log->scan_timestamp);
            });
            $avgProcessingTimeMs = round($totalMs / $processingTimes->count(), 0);
        }
        
        // Task 4.1.3: Get hash verification failures from health logs today
        $hashFailures = LedgerHealthLog::whereDate('created_at', today())
            ->where('hash_failures', true)
            ->sum('hash_failure_count');
        $hashFailures = $hashFailures ?: 0;
        
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
            'avg_latency_ms' => $avgLatencyMs, // REAL METRIC - Task 4.1.1: Calculated from actual events
            'hash_verification' => [
                'total_checked' => $eventsToday,
                'passed' => $eventsToday - $hashFailures, // REAL CALCULATION - Task 4.1.3
                'failed' => $hashFailures, // REAL METRIC - Task 4.1.3: From health logs
            ],
            'performance' => [
                'events_per_hour' => $eventsLastHour,
                'avg_processing_time_ms' => $avgProcessingTimeMs, // REAL METRIC - Task 4.1.2: Calculated from processing times
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
     * Task 1.1: Get linked attendance_events record from database.
     * 
     * Queries the attendance_events table for an entry linked to this ledger sequence ID.
     * Not all ledger entries have been processed into attendance_events yet.
     * 
     * @param int $sequenceId Ledger sequence ID
     * @return array|null Formatted attendance event or null if not yet processed
     */
    private function getLinkedAttendanceEvent(int $sequenceId): ?array
    {
        // Query attendance_events table for this ledger entry
        $attendanceEvent = AttendanceEvent::with([
            'employee:id,employee_number,profile_id',
            'employee.profile:id,first_name,last_name'
        ])->where('ledger_sequence_id', $sequenceId)
          ->first();
        
        if (!$attendanceEvent) {
            return null; // Not yet processed into attendance_events
        }
        
        $employee = $attendanceEvent->employee;
        
        return [
            'id' => $attendanceEvent->id,
            'ledger_sequence_id' => $attendanceEvent->ledger_sequence_id,
            'employee_id' => $employee ? $employee->employee_number : 'Unknown',
            'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
            'event_type' => $attendanceEvent->event_type,
            'recorded_at' => $attendanceEvent->event_time->toISOString(),
            'device_id' => $attendanceEvent->device_id ?? 'N/A',
            'device_location' => $attendanceEvent->location ?? 'N/A',
            'source' => $attendanceEvent->source,
            'is_deduplicated' => $attendanceEvent->is_deduplicated ?? false,
            'ledger_hash_verified' => (bool) $attendanceEvent->ledger_hash_verified,
            'attendance_date' => $attendanceEvent->event_date->toDateString(),
            'processed_at' => $attendanceEvent->updated_at ? $attendanceEvent->updated_at->toISOString() : null,
            'notes' => $attendanceEvent->notes,
            'created_at' => $attendanceEvent->created_at->toISOString(),
            'updated_at' => $attendanceEvent->updated_at->toISOString(),
        ];
    }

    /**
     * Task 1.1: Get related events from database (previous, next, same employee today).
     * 
     * Returns related ledger entries for context:
     * - Previous event in sequence
     * - Next event in sequence
     * - All events from the same employee today
     * 
     * @param RfidLedger $currentEvent Current ledger entry
     * @return array Array with 'previous', 'next', and 'employee_today' keys
     */
    private function getRelatedEventsReal(RfidLedger $currentEvent): array
    {
        $related = [
            'previous' => null,
            'next' => null,
            'employee_today' => []
        ];
        
        // Get previous event by sequence_id
        $previousEvent = RfidLedger::with([
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->where('sequence_id', '<', $currentEvent->sequence_id)
          ->orderByDesc('sequence_id')
          ->first();
        
        if ($previousEvent) {
            $employee = $previousEvent->rfidCardMapping ? $previousEvent->rfidCardMapping->employee : null;
            $related['previous'] = [
                'id' => $previousEvent->id,
                'sequence_id' => $previousEvent->sequence_id,
                'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
                'event_type' => $previousEvent->event_type,
                'timestamp' => $previousEvent->scan_timestamp->toISOString(),
                'device_id' => $previousEvent->device_id,
                'device_location' => $previousEvent->device && $previousEvent->device->location ? $previousEvent->device->location : $previousEvent->device_id,
                'verified' => $previousEvent->processed,
            ];
        }
        
        // Get next event by sequence_id
        $nextEvent = RfidLedger::with([
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->where('sequence_id', '>', $currentEvent->sequence_id)
          ->orderBy('sequence_id')
          ->first();
        
        if ($nextEvent) {
            $employee = $nextEvent->rfidCardMapping ? $nextEvent->rfidCardMapping->employee : null;
            $related['next'] = [
                'id' => $nextEvent->id,
                'sequence_id' => $nextEvent->sequence_id,
                'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
                'event_type' => $nextEvent->event_type,
                'timestamp' => $nextEvent->scan_timestamp->toISOString(),
                'device_id' => $nextEvent->device_id,
                'device_location' => $nextEvent->device && $nextEvent->device->location ? $nextEvent->device->location : $nextEvent->device_id,
                'verified' => $nextEvent->processed,
            ];
        }
        
        // Get same employee events today
        $employeeTodayEvents = RfidLedger::with([
            'rfidCardMapping.employee:id,employee_number,profile_id',
            'rfidCardMapping.employee.profile:id,first_name,last_name',
            'device:id,device_id,device_name,location'
        ])->where('employee_rfid', $currentEvent->employee_rfid)
          ->whereDate('scan_timestamp', today())
          ->orderBy('sequence_id')
          ->get()
          ->map(function ($event) {
              $employee = $event->rfidCardMapping ? $event->rfidCardMapping->employee : null;
              return [
                  'id' => $event->id,
                  'sequence_id' => $event->sequence_id,
                  'employee_id' => $employee ? $employee->employee_number : 'Unknown',
                  'employee_name' => $employee ? "{$employee->profile->first_name} {$employee->profile->last_name}" : 'Unknown Employee',
                  'event_type' => $event->event_type,
                  'timestamp' => $event->scan_timestamp->toISOString(),
                  'device_id' => $event->device_id,
                  'device_location' => $event->device && $event->device->location ? $event->device->location : $event->device_id,
                  'verified' => $event->processed,
              ];
          })
          ->toArray();
        
        $related['employee_today'] = $employeeTodayEvents;
        
        return $related;
    }
}
