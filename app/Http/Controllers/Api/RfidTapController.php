<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfidCardMapping;
use App\Models\RfidDevice;
use App\Models\RfidLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RfidTapController extends Controller
{
    public function tap(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        $validated = $request->validate([
            'card_uid'  => 'required|string|max:255',
            'device_id' => 'required|string|max:255',
            'tapped_at' => 'required|date',
            'local_id'  => 'required|integer',
        ]);

        $cardUid  = $validated['card_uid'];
        $tappedAt = Carbon::parse($validated['tapped_at']);
        $localId  = (int) $validated['local_id'];

        return DB::transaction(function () use ($cardUid, $tappedAt, $localId, $device) {
            $mapping = RfidCardMapping::with('employee')
                ->where('card_uid', $cardUid)
                ->whereNull('deleted_at')
                ->first();

            if (!$mapping || !$mapping->is_active) {
                $this->insertLedgerRow($device->device_id, $cardUid, 'unknown_card', $tappedAt, null);
                return response()->json(['status' => 'unknown', 'local_id' => $localId]);
            }

            $employee = $mapping->employee;

            // Deduplication: reject if the same card tapped this device within 15 seconds
            $recentTap = RfidLedger::where('device_id', $device->device_id)
                ->where('employee_rfid', $cardUid)
                ->where('scan_timestamp', '>=', $tappedAt->copy()->subSeconds(15))
                ->where('scan_timestamp', '<=', $tappedAt)
                ->exists();

            if ($recentTap) {
                return response()->json([
                    'status'           => 'duplicate',
                    'local_id'         => $localId,
                    'employee_name'    => trim($employee->first_name . ' ' . $employee->last_name),
                    'employee_number'  => $employee->employee_number,
                    'predicted_action' => 'DUPLICATE TAP',
                ]);
            }

            // Determine predicted action from ledger directly (real-time, no queue needed).
            // Odd number of taps today = last was TIME IN → predict TIME OUT, and vice versa.
            $tapsToday = RfidLedger::where('employee_rfid', $cardUid)
                ->whereDate('scan_timestamp', $tappedAt->toDateString())
                ->where('event_type', 'tap')
                ->count();
            $predictedAction = ($tapsToday % 2 === 0) ? 'TIME IN' : 'TIME OUT';

            $this->insertLedgerRow($device->device_id, $cardUid, 'tap', $tappedAt, $employee->id);

            $mapping->increment('usage_count');
            $mapping->update(['last_used_at' => $tappedAt]);

            return response()->json([
                'status'           => 'ok',
                'local_id'         => $localId,
                'employee_name'    => trim($employee->first_name . ' ' . $employee->last_name),
                'employee_number'  => $employee->employee_number,
                'predicted_action' => $predictedAction,
            ]);
        });
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);
        if ($device instanceof JsonResponse) {
            return $device;
        }

        $status = $request->input('status', 'online');
        $device->update([
            'status'         => in_array($status, ['online', 'offline']) ? $status : 'online',
            'last_heartbeat' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function authenticateDevice(Request $request): RfidDevice|JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $device = RfidDevice::where('api_key', $token)->first();
        if (!$device) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $device;
    }

    private function insertLedgerRow(
        string $deviceId,
        string $cardUid,
        string $eventType,
        Carbon $tappedAt,
        ?int   $employeeId
    ): void {
        // Lock the last row globally to get a unique next sequence_id across all devices
        $last = RfidLedger::orderByDesc('sequence_id')
            ->lockForUpdate()
            ->first(['sequence_id', 'hash_chain', 'device_id']);

        $nextSeq  = $last ? $last->sequence_id + 1 : 1;
        // Hash chain is per-device: find the last hash for this specific device
        $prevHash = RfidLedger::where('device_id', $deviceId)
            ->orderByDesc('sequence_id')
            ->value('hash_chain');

        $payload = [
            'card_uid'    => $cardUid,
            'device_id'   => $deviceId,
            'employee_id' => $employeeId,
            'event_type'  => $eventType,
            'timestamp'   => $tappedAt->toIso8601String(),
        ];
        // Sort keys to match Python compact JSON
        ksort($payload);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hashChain   = hash('sha256', ($prevHash ?? '') . $payloadJson);

        RfidLedger::create([
            'sequence_id'    => $nextSeq,
            'employee_rfid'  => $cardUid,
            'device_id'      => $deviceId,
            'scan_timestamp' => $tappedAt,
            'event_type'     => $eventType,
            'raw_payload'    => $payload,
            'hash_chain'     => $hashChain,
            'hash_previous'  => $prevHash,
            'processed'      => false,
            'created_at'     => $tappedAt,
        ]);
    }
}
