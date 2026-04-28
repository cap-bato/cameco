<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Jobs\Timekeeping\ProcessRfidLedgerJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerSyncController extends Controller
{
    /**
     * Trigger manual ledger synchronization.
     *
     * Dispatches ProcessRfidLedgerJob onto the queue so that unprocessed
     * rfid_ledger entries are picked up and converted to attendance_events.
     *
     * @param Request $request
     * @return JsonResponse  202 on success, 500 on failure
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'force'            => 'sometimes|boolean',
            'from_sequence_id' => 'sometimes|integer|min:1',
            'limit'            => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $job = new ProcessRfidLedgerJob(
                fromSequenceId: $validated['from_sequence_id'] ?? null,
                limit:          $validated['limit'] ?? 1000,
                force:          $validated['force'] ?? false,
            );

            dispatch($job);

            Log::info('Manual ledger sync dispatched', [
                'triggered_by'     => auth()->user()?->name ?? 'system',
                'from_sequence_id' => $validated['from_sequence_id'] ?? null,
                'limit'            => $validated['limit'] ?? 1000,
                'force'            => $validated['force'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ledger synchronization job dispatched',
                'data' => [
                    'status'     => 'queued',
                    'started_at' => now()->toISOString(),
                    'parameters' => $validated,
                ],
            ], 202);

        } catch (\Exception $e) {
            Log::error('Ledger sync dispatch failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispatch ledger synchronization job',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return current status of queued / recently failed ProcessRfidLedgerJob runs.
     *
     * @param string $syncJobId  (kept for route compatibility — not used for lookup)
     * @return JsonResponse
     */
    public function status(string $syncJobId): JsonResponse
    {
        $pending = DB::table('jobs')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->count();

        $failed = DB::table('failed_jobs')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->orderByDesc('failed_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_jobs' => $pending,
                'last_failure' => $failed ? [
                    'failed_at' => $failed->failed_at,
                    'exception' => substr($failed->exception, 0, 500),
                ] : null,
            ],
        ]);
    }

    /**
     * Return recent sync run history pulled from the failed_jobs table.
     *
     * @return JsonResponse
     */
    public function history(): JsonResponse
    {
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get(['uuid', 'failed_at', 'exception'])
            ->map(fn($row) => [
                'uuid'      => $row->uuid,
                'status'    => 'failed',
                'failed_at' => $row->failed_at,
                'exception' => substr($row->exception, 0, 300),
            ]);

        $pendingCount = DB::table('jobs')
            ->where('payload', 'like', '%ProcessRfidLedgerJob%')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'failed_runs'  => $failedJobs,
                'pending_jobs' => $pendingCount,
            ],
            'meta' => [
                'next_scheduled_sync' => now()->addMinutes(1)->toISOString(),
            ],
        ]);
    }
}
