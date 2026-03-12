<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'               => 'sometimes|in:all,online,offline,idle,maintenance',
            'location'             => 'sometimes|string|max:100',
            'include_recent_scans' => 'sometimes|boolean',
        ]);

        $statusFilter   = $validated['status'] ?? 'all';
        $locationFilter = $validated['location'] ?? null;
        $includeRecent  = $validated['include_recent_scans'] ?? false;

        $today = Carbon::today();

        $query = RfidDevice::query();
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($locationFilter) {
            $query->where('location', 'like', "%{$locationFilter}%");
        }

        $devices = $query->get();

        $enriched = $devices->map(function (RfidDevice $device) use ($today, $includeRecent) {
            $scansToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->count();

            $unknownToday = RfidLedger::where('device_id', $device->device_id)
                ->whereDate('scan_timestamp', $today)
                ->where('event_type', 'unknown_card')
                ->count();

            $errorRate = $scansToday > 0
                ? round(($unknownToday / $scansToday) * 100, 2)
                : 0;

            $row = [
                'id'             => $device->device_id,
                'location'       => $device->location,
                'status'         => $device->status,
                'last_heartbeat' => $device->last_heartbeat?->toISOString(),
                'scans_today'    => $scansToday,
                'error_rate'     => $errorRate,
            ];

            if ($includeRecent) {
                $row['recent_scans'] = RfidLedger::where('device_id', $device->device_id)
                    ->orderByDesc('scan_timestamp')
                    ->limit(5)
                    ->get(['employee_rfid', 'event_type', 'scan_timestamp'])
                    ->toArray();
            }

            return $row;
        });

        $summary = [
            'total'   => $devices->count(),
            'online'  => $devices->where('status', 'online')->count(),
            'offline' => $devices->where('status', 'offline')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $enriched->values(),
            'summary' => $summary,
            'meta'    => ['timestamp' => now()->toISOString()],
        ]);
    }

    public function show(string $deviceId): JsonResponse
    {
        $device = RfidDevice::where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found'], 404);
        }

        $today = Carbon::today();

        $hourlyScans = RfidLedger::where('device_id', $deviceId)
            ->whereDate('scan_timestamp', $today)
            ->selectRaw("EXTRACT(HOUR FROM scan_timestamp) as hour, COUNT(*) as count")
            ->groupByRaw("EXTRACT(HOUR FROM scan_timestamp)")
            ->orderBy('hour')
            ->pluck('count', 'hour');

        $hourlyDistribution = collect(range(0, 23))->map(fn($h) => [
            'hour'  => $h,
            'count' => (int) ($hourlyScans[$h] ?? 0),
        ]);

        $scansToday   = RfidLedger::where('device_id', $deviceId)->whereDate('scan_timestamp', $today)->count();
        $unknownToday = RfidLedger::where('device_id', $deviceId)->whereDate('scan_timestamp', $today)->where('event_type', 'unknown_card')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id'                  => $device->device_id,
                'location'            => $device->location,
                'status'              => $device->status,
                'last_heartbeat'      => $device->last_heartbeat?->toISOString(),
                'scans_today'         => $scansToday,
                'error_rate'          => $scansToday > 0 ? round(($unknownToday / $scansToday) * 100, 2) : 0,
                'hourly_distribution' => $hourlyDistribution,
            ],
        ]);
    }
}
