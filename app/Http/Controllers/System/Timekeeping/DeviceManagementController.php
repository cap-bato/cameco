<?php

namespace App\Http\Controllers\System\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidDevice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DeviceManagementController extends Controller
{
    /**
     * List all RFID devices with stats for the System management page.
     */
    public function index(): Response
    {
        $deviceRows = RfidDevice::orderBy('device_name')->get();

        $devices = $deviceRows->map(fn (RfidDevice $d) => [
            'id'             => $d->id,
            'device_id'      => $d->device_id,
            'device_name'    => $d->device_name,
            'location'       => $d->location,
            'status'         => $d->status,
            'last_heartbeat' => $d->last_heartbeat?->toISOString(),
            'has_api_key'    => ! is_null($d->api_key),
            'config'         => $d->config,
            'created_at'     => $d->created_at->toISOString(),
        ])->values();

        $maintenanceDue = $deviceRows->filter(function (RfidDevice $d) {
            $date = data_get($d->config, 'next_maintenance_date');
            return $date && Carbon::now()->gte(Carbon::parse($date));
        })->count();

        $stats = [
            'total_devices'   => $deviceRows->count(),
            'online_devices'  => $deviceRows->where('status', 'online')->count(),
            'offline_devices' => $deviceRows->where('status', 'offline')->count(),
            'maintenance_due' => $maintenanceDue,
        ];

        return Inertia::render('System/TimekeepingDevices/Index', [
            'devices' => $devices,
            'stats'   => $stats,
        ]);
    }

    /**
     * Register a new RFID device.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_id'   => ['required', 'string', 'max:64', 'unique:rfid_devices,device_id'],
            'device_name' => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'config'      => ['nullable', 'array'],
        ]);

        RfidDevice::create([
            'device_id'   => $validated['device_id'],
            'device_name' => $validated['device_name'],
            'location'    => $validated['location'],
            'status'      => 'offline',
            'config'      => $validated['config'] ?? null,
        ]);

        return redirect()->route('system.timekeeping.devices.index')
            ->with('success', "Device '{$validated['device_id']}' registered. Generate an API key so the gate PC can connect.");
    }

    /**
     * Update an existing RFID device's settings.
     */
    public function update(Request $request, RfidDevice $device): RedirectResponse
    {
        $validated = $request->validate([
            'device_name' => ['sometimes', 'string', 'max:255'],
            'location'    => ['sometimes', 'string', 'max:255'],
            'status'      => ['sometimes', 'in:online,offline,maintenance'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        // Merge notes into the config JSON column
        $config = $device->config ?? [];
        if (array_key_exists('notes', $validated)) {
            $config['notes'] = $validated['notes'] ?: null;
            unset($validated['notes']);
        }

        $device->update(array_merge($validated, ['config' => $config]));

        return redirect()->route('system.timekeeping.devices.index')
            ->with('success', "Device '{$device->device_id}' updated.");
    }

    /**
     * Delete an RFID device record.
     */
    public function destroy(RfidDevice $device): RedirectResponse
    {
        $label = $device->device_id;
        $device->delete();

        Log::info("RFID device deleted by superadmin: {$label}");

        return redirect()->route('system.timekeeping.devices.index')
            ->with('success', "Device '{$label}' has been deleted.");
    }

    /**
     * Generate a new API key for the device and return it once via flash.
     *
     * The key is stored in plaintext so the gate PC can send it as a Bearer token
     * and the API controller can do an exact match. It is NEVER returned in the index
     * props — only once in this flash response.
     */
    public function generateKey(RfidDevice $device): RedirectResponse
    {
        $rawKey = bin2hex(random_bytes(32)); // 64 hex characters

        $device->update(['api_key' => $rawKey]);

        Log::info("API key generated for RFID device: {$device->device_id}");

        return redirect()->route('system.timekeeping.devices.index')
            ->with('generated_api_key', $rawKey)
            ->with('for_device_id', $device->device_id);
    }

    /**
     * Revoke the device's API key — the gate PC will receive 401 until a new key is generated.
     */
    public function revokeKey(RfidDevice $device): RedirectResponse
    {
        $device->update(['api_key' => null]);

        Log::info("API key revoked for RFID device: {$device->device_id}");

        return redirect()->route('system.timekeeping.devices.index')
            ->with('success', "API key revoked for '{$device->device_id}'. The gate PC is now disconnected.");
    }
}
