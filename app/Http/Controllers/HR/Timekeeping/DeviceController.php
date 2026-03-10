<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    /**
     * Display the device status dashboard.
     * 
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $devices = $this->generateMockDevices();
        
        // Apply filters if needed
        $statusFilter = $request->get('status');
        if ($statusFilter && $statusFilter !== 'all') {
            $devices = array_filter($devices, function ($device) use ($statusFilter) {
                return $device['status'] === $statusFilter;
            });
            $devices = array_values($devices);
        }
        
        return Inertia::render('HR/Timekeeping/Devices', [
            'devices' => $devices,
            'summary' => $this->generateSummaryStats($devices),
            'filters' => [
                'status' => $statusFilter ?? 'all',
            ],
        ]);
    }

    /**
     * Generate mock device data.
     * 
     * @return array
     */
    private function generateMockDevices(): array
    {
        return [
            [
                'id' => 'GATE-01',
                'location' => 'Gate 1 - Main Entrance',
                'status' => 'online',
                'lastScanAgo' => '5 seconds ago',
                'lastScanTimestamp' => now()->subSeconds(5)->toISOString(),
                'scansToday' => 245,
                'uptime' => 99.8,
                'errorRate' => 0.2,
                'recentScans' => [
                    [
                        'employeeName' => 'Juan Dela Cruz',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subSeconds(5)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Maria Santos',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subSeconds(38)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Pedro Reyes',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subMinutes(2)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Ana Lopez',
                        'eventType' => 'time_out',
                        'timestamp' => now()->subMinutes(3)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Carlos Garcia',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subMinutes(4)->toISOString(),
                    ],
                ],
            ],
            [
                'id' => 'GATE-02',
                'location' => 'Gate 2 - Loading Dock',
                'status' => 'idle',
                'lastScanAgo' => '25 minutes ago',
                'lastScanTimestamp' => now()->subMinutes(25)->toISOString(),
                'scansToday' => 87,
                'uptime' => 98.5,
                'errorRate' => 1.5,
                'recentScans' => [
                    [
                        'employeeName' => 'Roberto Diaz',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subMinutes(25)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Linda Fernandez',
                        'eventType' => 'time_in',
                        'timestamp' => now()->subMinutes(30)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Miguel Torres',
                        'eventType' => 'time_out',
                        'timestamp' => now()->subMinutes(35)->toISOString(),
                    ],
                ],
            ],
            [
                'id' => 'CAFETERIA-01',
                'location' => 'Cafeteria - Break Scanner',
                'status' => 'offline',
                'lastScanAgo' => '2 hours ago',
                'lastScanTimestamp' => now()->subHours(2)->toISOString(),
                'scansToday' => 156,
                'uptime' => 85.2,
                'errorRate' => 14.8,
                'recentScans' => [],
            ],
            [
                'id' => 'WAREHOUSE-01',
                'location' => 'Warehouse - Main Floor',
                'status' => 'online',
                'lastScanAgo' => '2 minutes ago',
                'lastScanTimestamp' => now()->subMinutes(2)->toISOString(),
                'scansToday' => 178,
                'uptime' => 97.3,
                'errorRate' => 2.7,
                'recentScans' => [
                    [
                        'employeeName' => 'Sofia Morales',
                        'eventType' => 'break_start',
                        'timestamp' => now()->subMinutes(2)->toISOString(),
                    ],
                    [
                        'employeeName' => 'Diego Ramirez',
                        'eventType' => 'break_end',
                        'timestamp' => now()->subMinutes(10)->toISOString(),
                    ],
                ],
            ],
            [
                'id' => 'OFFICE-01',
                'location' => 'Office Floor - Entrance',
                'status' => 'maintenance',
                'lastScanAgo' => '4 hours ago',
                'lastScanTimestamp' => now()->subHours(4)->toISOString(),
                'scansToday' => 62,
                'uptime' => 72.5,
                'errorRate' => 27.5,
                'recentScans' => [],
            ],
        ];
    }

    /**
     * Generate summary statistics.
     * 
     * @param array $devices
     * @return array
     */
    private function generateSummaryStats(array $devices): array
    {
        $totalDevices = count($devices);
        $onlineDevices = count(array_filter($devices, fn($d) => $d['status'] === 'online'));
        $offlineDevices = count(array_filter($devices, fn($d) => $d['status'] === 'offline'));
        $maintenanceDevices = count(array_filter($devices, fn($d) => $d['status'] === 'maintenance'));
        
        $totalScans = array_sum(array_column($devices, 'scansToday'));
        $avgUptime = $totalDevices > 0 ? array_sum(array_column($devices, 'uptime')) / $totalDevices : 0;
        
        return [
            'totalDevices' => $totalDevices,
            'onlineDevices' => $onlineDevices,
            'offlineDevices' => $offlineDevices,
            'maintenanceDevices' => $maintenanceDevices,
            'totalScansToday' => $totalScans,
            'avgUptime' => round($avgUptime, 1),
        ];
    }
}
