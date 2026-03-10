<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendanceSummary;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AttendanceFinalizeController extends Controller
{
    /**
     * POST /hr/timekeeping/attendance/finalize
     * Finalize (lock) attendance for a date range or payroll period
     * 
     * Body: 
     *   - period_id: int (PayrollPeriod.id) OR
     *   - from: date string (YYYY-MM-DD)
     *   - to: date string (YYYY-MM-DD)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:payroll_periods,id',
            'from'      => 'nullable|date_format:Y-m-d|required_without:period_id',
            'to'        => 'nullable|date_format:Y-m-d|required_without:period_id|after_or_equal:from',
        ]);

        // Resolve date range
        $from = $validated['from'] ?? null;
        $to   = $validated['to']   ?? null;
        $periodId = $validated['period_id'] ?? null;

        if ($periodId) {
            $period = PayrollPeriod::findOrFail($periodId);
            $from = (string) $period->period_start;
            $to   = $period->timekeeping_cutoff_date 
                ? (string) $period->timekeeping_cutoff_date
                : (string) $period->period_end;
        }

        if (!$from || !$to) {
            return response()->json([
                'error' => 'Provide period_id or both from and to dates'
            ], 422);
        }

        try {
            // Count unfinalized rows
            $count = DailyAttendanceSummary::whereBetween('attendance_date', [$from, $to])
                ->where('is_finalized', false)
                ->count();

            if ($count === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No unfinalized rows found in range',
                    'finalized' => 0,
                ]);
            }

            // Finalize rows
            $updated = DailyAttendanceSummary::whereBetween('attendance_date', [$from, $to])
                ->where('is_finalized', false)
                ->update(['is_finalized' => true]);

            // If period provided, lock timekeeping on it
            if ($periodId) {
                $period->update(['timekeeping_data_locked' => true]);
            }

            Log::info('[AttendanceFinalizeController] Attendance finalized via UI', [
                'from'       => $from,
                'to'         => $to,
                'period_id'  => $periodId,
                'rows'       => $updated,
                'user_id'    => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Finalized {$updated} attendance row(s)",
                'finalized' => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error('[AttendanceFinalizeController] Failed to finalize attendance', [
                'error'  => $e->getMessage(),
                'from'   => $from,
                'to'     => $to,
            ]);

            return response()->json([
                'error' => 'Failed to finalize attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /hr/timekeeping/attendance/finalize
     * Unfinalize (unlock) attendance for a date range or payroll period
     * Allows corrections after finalization if needed.
     * 
     * Body:
     *   - period_id: int (PayrollPeriod.id) OR
     *   - from: date string (YYYY-MM-DD)
     *   - to: date string (YYYY-MM-DD)
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'nullable|integer|exists:payroll_periods,id',
            'from'      => 'nullable|date_format:Y-m-d|required_if:period_id,',
            'to'        => 'nullable|date_format:Y-m-d|required_if:period_id,|after_or_equal:from',
        ]);

        // Resolve date range
        $from = $validated['from'] ?? null;
        $to   = $validated['to']   ?? null;
        $periodId = $validated['period_id'] ?? null;

        if ($periodId) {
            $period = PayrollPeriod::findOrFail($periodId);
            $from = (string) $period->period_start;
            $to   = (string) $period->period_end;
        }

        if (!$from || !$to) {
            return response()->json([
                'error' => 'Provide period_id or both from and to dates'
            ], 422);
        }

        try {
            // Unfinalize rows
            $updated = DailyAttendanceSummary::whereBetween('attendance_date', [$from, $to])
                ->where('is_finalized', true)
                ->update(['is_finalized' => false]);

            // If period provided, unlock timekeeping on it
            if ($periodId) {
                $period->update(['timekeeping_data_locked' => false]);
            }

            Log::info('[AttendanceFinalizeController] Attendance unfinalized via UI', [
                'from'       => $from,
                'to'         => $to,
                'period_id'  => $periodId,
                'rows'       => $updated,
                'user_id'    => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Unfinalized {$updated} attendance row(s)",
                'unfinalized' => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error('[AttendanceFinalizeController] Failed to unfinalize attendance', [
                'error'  => $e->getMessage(),
                'from'   => $from,
                'to'     => $to,
            ]);

            return response()->json([
                'error' => 'Failed to unfinalize attendance: ' . $e->getMessage()
            ], 500);
        }
    }
}
