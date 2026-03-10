<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Events\Timekeeping\AttendanceCorrectionRequested;
use App\Events\Timekeeping\AttendanceCorrectionApproved;
use App\Events\Timekeeping\AttendanceCorrectionRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * AttendanceCorrectionController
 * 
 * Task 4.4: Handle manual corrections to attendance records with audit trail and approval workflow.
 * 
 * Architecture Decision: Manual corrections NEVER modify the ledger or attendance_events.
 * Corrections are stored separately in attendance_corrections table to preserve data integrity.
 * 
 * Workflow:
 * 1. HR Staff submits correction request (store)
 * 2. HR Manager reviews and approves/rejects (approve/reject)
 * 3. Approved corrections update daily_attendance_summary
 * 4. Events dispatched to Payroll, Notification modules
 */
class AttendanceCorrectionController extends Controller
{
    /**
     * Task 4.4.1: Submit a new attendance correction request.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'attendance_id' => 'required|integer|exists:attendance_events,id',
                'corrected_time_in' => 'nullable|date_format:H:i',
                'corrected_time_out' => 'nullable|date_format:H:i',
                'corrected_break_start' => 'nullable|date_format:H:i',
                'corrected_break_end' => 'nullable|date_format:H:i',
                'correction_reason' => 'required|string|in:wrong_entry,machine_error,employee_reported,manual_adjustment,other',
                'justification' => 'required|string|min:10',
            ]);

            // Fetch the attendance event being corrected
            $attendanceEvent = AttendanceEvent::findOrFail($validated['attendance_id']);

            // Calculate hours difference
            $originalHours = $this->calculateHours(
                $attendanceEvent->time_in,
                $attendanceEvent->time_out,
                $attendanceEvent->break_start,
                $attendanceEvent->break_end
            );

            $correctedHours = $this->calculateHours(
                $validated['corrected_time_in'] ?? null,
                $validated['corrected_time_out'] ?? null,
                $validated['corrected_break_start'] ?? null,
                $validated['corrected_break_end'] ?? null
            );

            $hoursDifference = $correctedHours - $originalHours;

            // Create attendance correction record
            DB::beginTransaction();

            $correction = AttendanceCorrection::create([
                'attendance_event_id' => $validated['attendance_id'],
                'requested_by_user_id' => auth()->id(),
                'original_time_in' => $attendanceEvent->time_in,
                'original_time_out' => $attendanceEvent->time_out,
                'original_break_start' => $attendanceEvent->break_start,
                'original_break_end' => $attendanceEvent->break_end,
                'corrected_time_in' => $validated['corrected_time_in'] ?? null,
                'corrected_time_out' => $validated['corrected_time_out'] ?? null,
                'corrected_break_start' => $validated['corrected_break_start'] ?? null,
                'corrected_break_end' => $validated['corrected_break_end'] ?? null,
                'hours_difference' => $hoursDifference,
                'correction_reason' => $validated['correction_reason'],
                'justification' => $validated['justification'],
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            // Log audit trail
            Log::info('Attendance correction requested', [
                'correction_id' => $correction->id,
                'attendance_event_id' => $attendanceEvent->id,
                'requested_by' => auth()->id(),
                'hours_difference' => $hoursDifference,
            ]);

            // Dispatch event for downstream processing
            event(new AttendanceCorrectionRequested($correction));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance correction request submitted successfully.',
                'data' => [
                    'correction_id' => $correction->id,
                    'status' => $correction->status,
                    'hours_difference' => $hoursDifference,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create attendance correction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit attendance correction request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Task 4.4.2: Approve an attendance correction request.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            // Verify permission
            if (!auth()->user()->can('hr.timekeeping.corrections.approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You do not have permission to approve corrections.',
                ], 403);
            }

            // Fetch correction
            $correction = AttendanceCorrection::with('attendanceEvent')->findOrFail($id);

            // Check if already processed
            if ($correction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This correction has already been processed.',
                ], 400);
            }

            DB::beginTransaction();

            // Update correction status
            $correction->update([
                'status' => 'approved',
                'approved_by_user_id' => auth()->id(),
                'processed_at' => now(),
            ]);

            // Apply correction to daily_attendance_summary with corrected times
            $this->updateDailyAttendanceSummary($correction);

            // Log audit trail
            Log::info('Attendance correction approved', [
                'correction_id' => $correction->id,
                'attendance_event_id' => $correction->attendance_event_id,
                'approved_by' => auth()->id(),
                'hours_difference' => $correction->hours_difference,
            ]);

            // Dispatch event for downstream processing
            event(new AttendanceCorrectionApproved($correction));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance correction approved successfully.',
                'data' => [
                    'correction_id' => $correction->id,
                    'status' => $correction->status,
                    'approved_by' => auth()->user()->name,
                    'approved_at' => $correction->processed_at->toIso8601String(),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Correction request not found.',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to approve attendance correction', [
                'correction_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve attendance correction. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Task 4.4.3: Reject an attendance correction request.
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            // Verify permission
            if (!auth()->user()->can('hr.timekeeping.corrections.approve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You do not have permission to reject corrections.',
                ], 403);
            }

            // Validate rejection reason
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10',
            ]);

            // Fetch correction
            $correction = AttendanceCorrection::findOrFail($id);

            // Check if already processed
            if ($correction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This correction has already been processed.',
                ], 400);
            }

            DB::beginTransaction();

            // Update correction status
            $correction->update([
                'status' => 'rejected',
                'approved_by_user_id' => auth()->id(),
                'rejection_reason' => $validated['rejection_reason'],
                'processed_at' => now(),
            ]);

            // Log audit trail
            Log::info('Attendance correction rejected', [
                'correction_id' => $correction->id,
                'attendance_event_id' => $correction->attendance_event_id,
                'rejected_by' => auth()->id(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            // Dispatch event for notification
            event(new AttendanceCorrectionRejected($correction));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance correction rejected.',
                'data' => [
                    'correction_id' => $correction->id,
                    'status' => $correction->status,
                    'rejected_by' => auth()->user()->name,
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Correction request not found.',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reject attendance correction', [
                'correction_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject attendance correction. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Helper: Calculate hours from time inputs.
     * 
     * @param string|null $timeIn
     * @param string|null $timeOut
     * @param string|null $breakStart
     * @param string|null $breakEnd
     * @return float
     */
    private function calculateHours(?string $timeIn, ?string $timeOut, ?string $breakStart = null, ?string $breakEnd = null): float
    {
        if (!$timeIn || !$timeOut) {
            return 0.0;
        }

        try {
            $inTime = Carbon::createFromFormat('H:i', $timeIn);
            $outTime = Carbon::createFromFormat('H:i', $timeOut);
            
            // Calculate total hours
            $hours = $outTime->diffInMinutes($inTime) / 60;

            // Subtract break time if provided
            if ($breakStart && $breakEnd) {
                $breakStartTime = Carbon::createFromFormat('H:i', $breakStart);
                $breakEndTime = Carbon::createFromFormat('H:i', $breakEnd);
                $breakHours = $breakEndTime->diffInMinutes($breakStartTime) / 60;
                $hours -= $breakHours;
            }

            return round(max(0, $hours), 2);

        } catch (\Exception $e) {
            Log::warning('Failed to calculate hours', [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'error' => $e->getMessage(),
            ]);
            
            return 0.0;
        }
    }

    /**
     * Helper: Update daily attendance summary with corrected values.
     *
     * Uses AttendanceCorrection->attendanceEvent to resolve employee/date,
     * applies corrected timestamps, and recalculates derived metrics.
     * Preserves existing is_finalized state.
     *
     * @param AttendanceCorrection $correction
     * @return void
     */
    private function updateDailyAttendanceSummary(AttendanceCorrection $correction): void
    {
        $attendanceEvent = $correction->attendanceEvent;

        if (!$attendanceEvent) {
            Log::warning('Cannot update daily summary: correction has no attendanceEvent', [
                'correction_id' => $correction->id,
                'attendance_event_id' => $correction->attendance_event_id,
            ]);
            return;
        }

        $attendanceDate = Carbon::parse($attendanceEvent->event_date)->toDateString();

        // Use Carbon date object for proper comparison with 'date' cast column
        $summaryDate = Carbon::parse($attendanceDate);
        
        $summary = DailyAttendanceSummary::where('employee_id', $attendanceEvent->employee_id)
            ->whereDate('attendance_date', $summaryDate)
            ->first();
        
        if (!$summary) {
            // Create new if not found, but ensure work_schedule_id is set
            $existingSummary = DailyAttendanceSummary::where('employee_id', $attendanceEvent->employee_id)
                ->orderBy('attendance_date', 'DESC')
                ->first();
            
            $workScheduleId = $existingSummary ? $existingSummary->work_schedule_id : 1;
            
            $summary = new DailyAttendanceSummary([
                'employee_id' => $attendanceEvent->employee_id,
                'attendance_date' => $summaryDate,
                'work_schedule_id' => $workScheduleId,
            ]);
        }

        // Apply corrected timestamps to the summary row.
        if ($correction->corrected_time_in) {
            $summary->time_in = Carbon::parse($attendanceDate . ' ' . $correction->corrected_time_in)->toDateTimeString();
        }

        if ($correction->corrected_time_out) {
            $summary->time_out = Carbon::parse($attendanceDate . ' ' . $correction->corrected_time_out)->toDateTimeString();
        }

        if ($correction->corrected_break_start) {
            $summary->break_start = Carbon::parse($attendanceDate . ' ' . $correction->corrected_break_start)->toDateTimeString();
        }

        if ($correction->corrected_break_end) {
            $summary->break_end = Carbon::parse($attendanceDate . ' ' . $correction->corrected_break_end)->toDateTimeString();
        }

        // Recompute derived values using standard 8:00-17:00 shift assumptions.
        if ($summary->time_in && $summary->time_out) {
            $timeIn = Carbon::parse($summary->time_in);
            $timeOut = Carbon::parse($summary->time_out);
            $shiftStart = Carbon::parse($attendanceDate . ' 08:00:00');
            $shiftEnd = Carbon::parse($attendanceDate . ' 17:00:00');

            $breakMinutes = (int) ($summary->break_duration ?? 60);
            if ($summary->break_start && $summary->break_end) {
                $breakMinutes = max(
                    0,
                    Carbon::parse($summary->break_end)->diffInMinutes(Carbon::parse($summary->break_start), false)
                );
            }

            $grossMinutes = max(0, $timeIn->diffInMinutes($timeOut, false));
            $netMinutes = max(0, $grossMinutes - $breakMinutes);

            $regularMinutes = min($netMinutes, 480);
            $overtimeMinutes = max(0, $netMinutes - 480);

            $lateBaseMinutes = max(0, $shiftStart->diffInMinutes($timeIn, false));
            $graceMinutes = 15;
            $lateMinutes = max(0, $lateBaseMinutes - $graceMinutes);

            $scheduledMinutes = max(0, $shiftStart->diffInMinutes($shiftEnd, false) - $breakMinutes);

            $summary->break_duration = $breakMinutes;
            $summary->total_hours_worked = round($netMinutes / 60, 2);
            $summary->regular_hours = round($regularMinutes / 60, 2);
            $summary->overtime_hours = round($overtimeMinutes / 60, 2);
            $summary->late_minutes = $lateMinutes;
            $summary->undertime_minutes = max(0, $scheduledMinutes - $regularMinutes);
            $summary->is_present = true;
            $summary->is_late = $lateMinutes > 0;
            $summary->is_overtime = $overtimeMinutes > 0;
            $summary->is_undertime = $summary->undertime_minutes > 0;
        }

        // Only set after migration adds the column.
        if (Schema::hasColumn('daily_attendance_summary', 'correction_applied')) {
            $summary->correction_applied = true;
        }

        $summary->calculated_at = now();
        $summary->save();

        Log::info('Daily attendance summary updated after correction approval', [
            'correction_id' => $correction->id,
            'attendance_event_id' => $correction->attendance_event_id,
            'employee_id' => $attendanceEvent->employee_id,
            'attendance_date' => $attendanceDate,
        ]);
    }
}
