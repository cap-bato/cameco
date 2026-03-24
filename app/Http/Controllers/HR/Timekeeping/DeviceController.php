<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(Request $request): Response
    {
        $statusFilter = $request->get('status', 'all');

        $query = RfidDevice::query();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $devices = $query->orderBy('device_id')->get();

        $today = Carbon::today();

        $enriched = $devices->map(function (RfidDevice $device) use ($today) {
            $scansToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->count();

            $recentScans = RfidLedger::with('rfidCardMapping.employee:id,first_name,last_name')
                ->where('device_id', $device->device_id)
                ->orderByDesc('scan_timestamp')
                ->limit(5)
                ->get()
                ->map(fn($entry) => [
                    'employeeName' => $entry->employee
                        ? trim("{$entry->employee->first_name} {$entry->employee->last_name}")
                        : $entry->employee_rfid,
                    'eventType'  => $entry->event_type,
                    'timestamp'  => $entry->scan_timestamp?->toISOString(),
                ]);

            $lastScan = RfidLedger::where('device_id', $device->device_id)
                ->orderByDesc('scan_timestamp')
                ->first();

            return [
                'id'                => $device->device_id,
                'location'          => $device->location,
                'status'            => $device->status,
                'lastScanTimestamp' => $lastScan?->scan_timestamp?->toISOString(),
                'lastScanAgo'       => $lastScan ? $lastScan->scan_timestamp->diffForHumans() : 'Never',
                'scansToday'        => $scansToday,
                'lastHeartbeat'     => $device->last_heartbeat?->toISOString(),
                'recentScans'       => $recentScans,
            ];
        });

        $summary = [
            'total'       => $devices->count(),
            'online'      => $devices->where('status', 'online')->count(),
            'offline'     => $devices->where('status', 'offline')->count(),
            'idle'        => $devices->where('status', 'idle')->count(),
            'maintenance' => $devices->where('status', 'maintenance')->count(),
        ];

        return Inertia::render('HR/Timekeeping/Devices', [
            'devices' => $enriched->values(),
            'summary' => $summary,
            'filters' => ['status' => $statusFilter],
        ]);
    }
}

