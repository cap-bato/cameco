<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LedgerDeviceController extends Controller
{
    /**
     * Get list of RFID devices with status and metrics (API endpoint).
     * 
     * This endpoint returns device information for AJAX widgets, monitoring dashboards,
     * and real-time status displays.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Validate optional filters
        $validated = $request->validate([
            'status' => 'sometimes|in:all,online,offline,idle,maintenance',
            'location' => 'sometimes|string|max:100',
            'include_recent_scans' => 'sometimes|boolean',
        ]);

        $includeRecentScans = $validated['include_recent_scans'] ?? false;
        $statusFilter = $validated['status'] ?? 'all';
        $locationFilter = $validated['location'] ?? null;

        // Generate mock device data
        $devices = $this->generateDeviceData($includeRecentScans);

        // Apply status filter
        if ($statusFilter !== 'all') {
            $devices = array_filter($devices, function ($device) use ($statusFilter) {
                return $device['status'] === $statusFilter;
            });
            $devices = array_values($devices);
        }

        // Apply location filter
        if ($locationFilter) {
            $devices = array_filter($devices, function ($device) use ($locationFilter) {
                return stripos($device['location'], $locationFilter) !== false;
            });
            $devices = array_values($devices);
        }

        // Compute summary statistics
        $summary = $this->computeSummaryStats($devices);

        return response()->json([
            'success' => true,
            'data' => $devices,
            'summary' => $summary,
            'meta' => [
                'total' => count($devices),
                'filters_applied' => [
                    'status' => $statusFilter,
                    'location' => $locationFilter,
                ],
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get detailed information about a specific device.
     * 
     * @param string $deviceId
     * @return JsonResponse
     */
    public function show(string $deviceId): JsonResponse
    {
        $devices = $this->generateDeviceData(true);
        $device = collect($devices)->firstWhere('id', $deviceId);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
                'error' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        // Add additional detailed metrics for single device view
        $device['detailed_metrics'] = [
            'hourly_scan_distribution' => $this->generateHourlyScanData(),
            'error_log' => $this->generateErrorLog($deviceId),
            'maintenance_history' => $this->generateMaintenanceHistory($deviceId),
        ];

        return response()->json([
            'success' => true,
            'data' => $device,
        ]);
    }

    /**
     * Generate mock device data.
     * 
     * @param bool $includeRecentScans
     * @return array
     */
    private function generateDeviceData(bool $includeRecentScans = false): array
    {
        $devices = [
            [
                'id' => 'GATE-01',
                'device_name' => 'Gate 1 Reader',
                'location' => 'Gate 1 - Main Entrance',
                'status' => 'online',
                'last_heartbeat' => now()->subSeconds(5)->toISOString(),
                'last_scan' => now()->subSeconds(5)->toISOString(),
                'last_scan_ago' => '5 seconds ago',
                'scans_today' => 245,
                'scans_this_hour' => 32,
                'uptime_percentage' => 99.8,
                'error_rate_percentage' => 0.2,
                'avg_response_time_ms' => 45,
                'firmware_version' => '2.4.1',
                'ip_address' => '192.168.1.101',
            ],
            [
                'id' => 'GATE-02',
                'device_name' => 'Gate 2 Reader',
                'location' => 'Gate 2 - Loading Dock',
                'status' => 'idle',
                'last_heartbeat' => now()->subMinutes(1)->toISOString(),
                'last_scan' => now()->subMinutes(25)->toISOString(),
                'last_scan_ago' => '25 minutes ago',
                'scans_today' => 87,
                'scans_this_hour' => 4,
                'uptime_percentage' => 98.5,
                'error_rate_percentage' => 1.5,
                'avg_response_time_ms' => 52,
                'firmware_version' => '2.4.1',
                'ip_address' => '192.168.1.102',
            ],
            [
                'id' => 'CAFETERIA-01',
                'device_name' => 'Cafeteria Reader',
                'location' => 'Cafeteria - Break Scanner',
                'status' => 'offline',
                'last_heartbeat' => now()->subHours(2)->toISOString(),
                'last_scan' => now()->subHours(2)->toISOString(),
                'last_scan_ago' => '2 hours ago',
                'scans_today' => 156,
                'scans_this_hour' => 0,
                'uptime_percentage' => 85.2,
                'error_rate_percentage' => 14.8,
                'avg_response_time_ms' => 0,
                'firmware_version' => '2.3.8',
                'ip_address' => '192.168.1.103',
            ],
            [
                'id' => 'WAREHOUSE-01',
                'device_name' => 'Warehouse Reader',
                'location' => 'Warehouse - Main Floor',
                'status' => 'online',
                'last_heartbeat' => now()->subSeconds(30)->toISOString(),
                'last_scan' => now()->subMinutes(2)->toISOString(),
                'last_scan_ago' => '2 minutes ago',
                'scans_today' => 178,
                'scans_this_hour' => 18,
                'uptime_percentage' => 97.3,
                'error_rate_percentage' => 2.7,
                'avg_response_time_ms' => 48,
                'firmware_version' => '2.4.0',
                'ip_address' => '192.168.1.104',
            ],
            [
                'id' => 'OFFICE-01',
                'device_name' => 'Office Reader',
                'location' => 'Office Floor - Entrance',
                'status' => 'maintenance',
                'last_heartbeat' => now()->subHours(4)->toISOString(),
                'last_scan' => now()->subHours(4)->toISOString(),
                'last_scan_ago' => '4 hours ago',
                'scans_today' => 62,
                'scans_this_hour' => 0,
                'uptime_percentage' => 72.5,
                'error_rate_percentage' => 27.5,
                'avg_response_time_ms' => 0,
                'firmware_version' => '2.3.5',
                'ip_address' => '192.168.1.105',
            ],
        ];

        // Add recent scans if requested
        if ($includeRecentScans) {
            $devices[0]['recent_scans'] = [
                [
                    'employee_id' => 'EMP-001',
                    'employee_name' => 'Juan Dela Cruz',
                    'event_type' => 'time_in',
                    'timestamp' => now()->subSeconds(5)->toISOString(),
                    'verified' => true,
                ],
                [
                    'employee_id' => 'EMP-002',
                    'employee_name' => 'Maria Santos',
                    'event_type' => 'time_in',
                    'timestamp' => now()->subSeconds(38)->toISOString(),
                    'verified' => true,
                ],
                [
                    'employee_id' => 'EMP-003',
                    'employee_name' => 'Pedro Reyes',
                    'event_type' => 'time_in',
                    'timestamp' => now()->subMinutes(2)->toISOString(),
                    'verified' => true,
                ],
            ];

            $devices[1]['recent_scans'] = [
                [
                    'employee_id' => 'EMP-004',
                    'employee_name' => 'Roberto Diaz',
                    'event_type' => 'time_in',
                    'timestamp' => now()->subMinutes(25)->toISOString(),
                    'verified' => true,
                ],
            ];

            $devices[3]['recent_scans'] = [
                [
                    'employee_id' => 'EMP-005',
                    'employee_name' => 'Sofia Morales',
                    'event_type' => 'break_start',
                    'timestamp' => now()->subMinutes(2)->toISOString(),
                    'verified' => true,
                ],
            ];

            // Offline and maintenance devices have no recent scans
            $devices[2]['recent_scans'] = [];
            $devices[4]['recent_scans'] = [];
        }

        return $devices;
    }

    /**
     * Compute summary statistics from device data.
     * 
     * @param array $devices
     * @return array
     */
    private function computeSummaryStats(array $devices): array
    {
        $totalDevices = count($devices);
        
        if ($totalDevices === 0) {
            return [
                'total_devices' => 0,
                'online_devices' => 0,
                'offline_devices' => 0,
                'idle_devices' => 0,
                'maintenance_devices' => 0,
                'total_scans_today' => 0,
                'total_scans_this_hour' => 0,
                'avg_uptime_percentage' => 0,
                'avg_error_rate_percentage' => 0,
            ];
        }

        $statusCounts = array_count_values(array_column($devices, 'status'));
        
        return [
            'total_devices' => $totalDevices,
            'online_devices' => $statusCounts['online'] ?? 0,
            'offline_devices' => $statusCounts['offline'] ?? 0,
            'idle_devices' => $statusCounts['idle'] ?? 0,
            'maintenance_devices' => $statusCounts['maintenance'] ?? 0,
            'total_scans_today' => array_sum(array_column($devices, 'scans_today')),
            'total_scans_this_hour' => array_sum(array_column($devices, 'scans_this_hour')),
            'avg_uptime_percentage' => round(array_sum(array_column($devices, 'uptime_percentage')) / $totalDevices, 1),
            'avg_error_rate_percentage' => round(array_sum(array_column($devices, 'error_rate_percentage')) / $totalDevices, 1),
        ];
    }

    /**
     * Generate mock hourly scan distribution data.
     * 
     * @return array
     */
    private function generateHourlyScanData(): array
    {
        $data = [];
        $baseHour = now()->startOfDay();
        
        for ($i = 0; $i < 24; $i++) {
            $hour = $baseHour->copy()->addHours($i);
            $scans = 0;
            
            // Simulate typical work day pattern
            if ($i >= 7 && $i <= 17) {
                $scans = rand(15, 45);
            } elseif ($i >= 18 && $i <= 20) {
                $scans = rand(5, 15);
            }
            
            $data[] = [
                'hour' => $hour->format('H:00'),
                'scans' => $scans,
            ];
        }
        
        return $data;
    }

    /**
     * Generate mock error log for a device.
     * 
     * @param string $deviceId
     * @return array
     */
    private function generateErrorLog(string $deviceId): array
    {
        if ($deviceId === 'GATE-01' || $deviceId === 'WAREHOUSE-01') {
            // Healthy devices - few errors
            return [
                [
                    'timestamp' => now()->subHours(3)->toISOString(),
                    'error_code' => 'TIMEOUT',
                    'message' => 'Card read timeout',
                    'severity' => 'warning',
                ],
            ];
        }
        
        if ($deviceId === 'CAFETERIA-01' || $deviceId === 'OFFICE-01') {
            // Problematic devices - more errors
            return [
                [
                    'timestamp' => now()->subMinutes(30)->toISOString(),
                    'error_code' => 'CONNECTION_LOST',
                    'message' => 'Lost connection to server',
                    'severity' => 'critical',
                ],
                [
                    'timestamp' => now()->subHours(1)->toISOString(),
                    'error_code' => 'CARD_READ_FAILURE',
                    'message' => 'Failed to read card data',
                    'severity' => 'error',
                ],
                [
                    'timestamp' => now()->subHours(2)->toISOString(),
                    'error_code' => 'TIMEOUT',
                    'message' => 'Card read timeout',
                    'severity' => 'warning',
                ],
            ];
        }
        
        return [];
    }

    /**
     * Generate mock maintenance history for a device.
     * 
     * @param string $deviceId
     * @return array
     */
    private function generateMaintenanceHistory(string $deviceId): array
    {
        return [
            [
                'date' => now()->subDays(30)->toISOString(),
                'type' => 'routine',
                'description' => 'Routine maintenance - cleaned reader, updated firmware',
                'performed_by' => 'Maintenance Team',
            ],
            [
                'date' => now()->subDays(90)->toISOString(),
                'type' => 'repair',
                'description' => 'Replaced card reader sensor',
                'performed_by' => 'Technician',
            ],
        ];
    }
}
