<?php

namespace App\Services\Timekeeping;

use App\Events\Timekeeping\AttendanceSummaryUpdated;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * AttendanceSummaryService
 * 
 * Computes daily attendance summaries from ledger events and applies business rules.
 * Task 5.3.1 & 5.3.2: Attendance summary computation and business rule application
 * 
 * Business Rules:
 * - Late: time_in > scheduled_start + grace_period (default 15 minutes)
 * - Absent: no time_in event by scheduled end time
 * - Overtime: time_out > scheduled_end + overtime_threshold
 * - Present: employee clocked in within grace period
 */
class AttendanceSummaryService
{
    /**
     * Grace period for "on-time" arrival in minutes
     * Default: 15 minutes
     */
    const GRACE_PERIOD_MINUTES = 15;

    /**
     * Overtime threshold in minutes
     * Default: 0 (any time after scheduled end is overtime)
     */
    const OVERTIME_THRESHOLD_MINUTES = 0;

    /**
     * Task 5.3.1: Compute daily attendance summary for employee on specific date.
     * 
     * Fetches all attendance events for the date, calculates time metrics,
     * and determines attendance status (present, late, absent, etc).
     * 
     * @param int $employeeId Employee ID
     * @param \Carbon\Carbon $date Attendance date
     * @return array Summary data including times, durations, and status flags
     * 
     * @example
     * $summary = $this->computeDailySummary(1, Carbon::parse('2024-01-15'));
     * // Returns:
     * // [
     * //     'employee_id' => 1,
     * //     'attendance_date' => '2024-01-15',
     * //     'time_in' => '2024-01-15 08:05:00',
     * //     'time_out' => '2024-01-15 17:30:00',
     * //     'total_hours_worked' => 9.25,
     * //     'is_present' => true,
     * //     'is_late' => true,
     * //     'late_minutes' => 5,
     * //     ...
     * // ]
     */
    public function computeDailySummary(int $employeeId, Carbon $date): array
    {
        // Fetch employee
        $employee = Employee::findOrFail($employeeId);

        // Check events FIRST — so we never discard real scan data due to missing schedule
        $events = AttendanceEvent::where('employee_id', $employeeId)
            ->whereDate('event_date', $date)
            ->orderBy('event_time', 'asc')
            ->get();

        if ($events->isEmpty()) {
            // No events and we'll check schedule below; absent either way
            $workSchedule = $this->getWorkScheduleForDate($employeeId, $date);
            return $this->buildEmptySummary($employeeId, $date, $workSchedule);
        }

        // Events exist — now get work schedule (may be null)
        $workSchedule = $this->getWorkScheduleForDate($employeeId, $date);
        $needsScheduleReview = ($workSchedule === null);

        // Extract time_in and time_out from events
        $timeIn = $this->extractTimeIn($events);
        $timeOut = $this->extractTimeOut($events);
        $breakDuration = $this->calculateBreakDuration($events);

        // Get scheduled start/end for this day (null when no schedule)
        $scheduledStart = $workSchedule ? $this->getScheduledStart($workSchedule, $date) : null;
        $scheduledEnd   = $workSchedule ? $this->getScheduledEnd($workSchedule, $date)   : null;

        // Build summary with time values
        $summary = [
            'employee_id'          => $employeeId,
            'attendance_date'      => $date->toDateString(),
            'work_schedule_id'     => $workSchedule?->id,
            'needs_schedule_review' => $needsScheduleReview,
            'time_in'              => $timeIn?->toDateTimeString(),
            'time_out'             => $timeOut?->toDateTimeString(),
            'break_duration'       => $breakDuration,
            'total_hours_worked'   => null,
            'regular_hours'        => null,
            'overtime_hours'       => null,
        ];

        // Calculate hours worked if both time_in and time_out exist
        if ($timeIn && $timeOut) {
            $totalMinutes = $timeIn->diffInMinutes($timeOut) - $breakDuration;
            $totalHours   = $totalMinutes / 60;
            $summary['total_hours_worked'] = round($totalHours, 2);

            if ($workSchedule && $scheduledStart && $scheduledEnd) {
                // Split into regular vs overtime using schedule
                $scheduledHours = $scheduledStart->diffInHours($scheduledEnd) - ($breakDuration / 60);

                if ($totalHours > $scheduledHours) {
                    $summary['regular_hours']  = round($scheduledHours, 2);
                    $summary['overtime_hours'] = round($totalHours - $scheduledHours, 2);
                } else {
                    $summary['regular_hours']  = round($totalHours, 2);
                    $summary['overtime_hours'] = 0;
                }
            } else {
                // No schedule — treat all hours as regular; HR will review
                $summary['regular_hours']  = round($totalHours, 2);
                $summary['overtime_hours'] = 0;
            }
        }

        return $summary;
    }

    /**
     * Task 5.3.2: Apply business rules to compute attendance status.
     * 
     * Evaluates computed summary against business rules:
     * - Present: clocked in within grace period
     * - Late: clocked in after grace period
     * - Absent: no time_in event
     * - Undertime: worked fewer hours than scheduled
     * - Overtime: worked more than scheduled
     * 
     * @param array $summary Summary data from computeDailySummary()
     * @param \Carbon\Carbon|null $date Date for schedule lookup (optional, defaults to summary date)
     * @return array Updated summary with status flags and minutes calculations
     * 
     * @example
     * $summary = $this->computeDailySummary(1, Carbon::parse('2024-01-15'));
     * $withRules = $this->applyBusinessRules($summary);
     * // Now includes:
     * // 'is_present' => true,
     * // 'is_late' => true,
     * // 'late_minutes' => 5,
     * // 'is_undertime' => false,
     * // 'is_overtime' => true,
     * // ...
     */
    public function applyBusinessRules(array $summary, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::parse($summary['attendance_date']);
        $employeeId = $summary['employee_id'];

        // Get work schedule - either from summary or look it up
        $workSchedule = null;
        if (isset($summary['work_schedule_id']) && $summary['work_schedule_id']) {
            $workSchedule = WorkSchedule::find($summary['work_schedule_id']);
        } else {
            // If not in summary, try to get from employee
            $workSchedule = $this->getWorkScheduleForDate($employeeId, $date);
        }

        // Get scheduled start/end times - from summary or from schedule
        $scheduledStart = $summary['scheduled_start'] ?? null;
        $scheduledEnd = $summary['scheduled_end'] ?? null;
        
        if (!$scheduledStart || !$scheduledEnd) {
            $scheduledStart = $this->getScheduledStart($workSchedule, $date);
            $scheduledEnd = $this->getScheduledEnd($workSchedule, $date);
        } else {
            // Parse from strings if provided
            $scheduledStart = is_string($scheduledStart) ? Carbon::parse($scheduledStart) : $scheduledStart;
            $scheduledEnd = is_string($scheduledEnd) ? Carbon::parse($scheduledEnd) : $scheduledEnd;
        }

        // When no schedule is assigned, only record presence — HR must review
        if (!empty($summary['needs_schedule_review'])) {
            $summary['is_present']       = isset($summary['time_in']) && $summary['time_in'] !== null;
            $summary['is_late']          = false;
            $summary['is_undertime']     = false;
            $summary['is_overtime']      = false;
            $summary['late_minutes']     = null;
            $summary['undertime_minutes'] = null;
            return $summary;
        }

        // Default status flags
        $summary['is_present'] = false;
        $summary['is_late'] = false;
        $summary['is_undertime'] = false;
        $summary['is_overtime'] = false;
        $summary['late_minutes'] = null;
        $summary['undertime_minutes'] = null;

        // Parse time_in and time_out - handle both Carbon objects and strings
        $timeIn = null;
        if ($summary['time_in']) {
            if ($summary['time_in'] instanceof Carbon) {
                $timeIn = $summary['time_in'];
            } else {
                $timeIn = Carbon::parse($summary['time_in']);
            }
        }
        
        $timeOut = null;
        if ($summary['time_out']) {
            if ($summary['time_out'] instanceof Carbon) {
                $timeOut = $summary['time_out'];
            } else {
                $timeOut = Carbon::parse($summary['time_out']);
            }
        }

        // Rule 1: Check if present or absent
        if (!$timeIn) {
            // No time_in = Absent
            $summary['is_present'] = false;
            return $summary;
        }

        // Employee clocked in - mark as present
        $summary['is_present'] = true;

        // Rule 2: Check if late (time_in > scheduled_start + grace_period)
        if ($scheduledStart) {
            $graceDeadline = $scheduledStart->copy()->addMinutes(self::GRACE_PERIOD_MINUTES);
            $lateMinutes = abs($timeIn->diffInMinutes($scheduledStart));

            if ($timeIn->gt($graceDeadline)) {
                $summary['is_late'] = true;
                $summary['late_minutes'] = max(0, $lateMinutes - self::GRACE_PERIOD_MINUTES);
            }
        }

        // Rule 3: Check if undertime (worked fewer hours than scheduled)
        if ($summary['total_hours_worked'] && $scheduledStart && $scheduledEnd) {
            $breakMinutes = $summary['break_duration'] ?? 0;
            $scheduledMinutes = $scheduledStart->diffInMinutes($scheduledEnd) - $breakMinutes;
            $scheduledHours = $scheduledMinutes / 60;
            $workedHours = $summary['total_hours_worked'];

            if ($workedHours < $scheduledHours) {
                $summary['is_undertime'] = true;
                $undertimeMinutes = ($scheduledHours - $workedHours) * 60;
                $summary['undertime_minutes'] = (int)round($undertimeMinutes);
            }
        }

        // Rule 4: Check if overtime (time_out > scheduled_end + overtime_threshold)
        if ($timeOut && $scheduledEnd) {
            $overtimeDeadline = $scheduledEnd->copy()->addMinutes(self::OVERTIME_THRESHOLD_MINUTES);

            if ($timeOut->gt($overtimeDeadline)) {
                $summary['is_overtime'] = true;
            }
        }

        return $summary;
    }

    /**
     * Helper: Get work schedule for employee on specific date
     * 
     * @param int $employeeId Employee ID
     * @param \Carbon\Carbon $date Date to check
     * @return \App\Models\WorkSchedule|null
     */
    private function getWorkScheduleForDate(int $employeeId, Carbon $date): ?WorkSchedule
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return null;
        }

        // Try to get department schedule first (simplified)
        if ($employee->department_id) {
            return WorkSchedule::where('department_id', $employee->department_id)
                ->where('effective_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', $date);
                })
                ->latest('effective_date')
                ->first();
        }

        return null;
    }

    /**
     * Helper: Get scheduled start time for specific date and schedule
     * 
     * @param \App\Models\WorkSchedule $schedule Work schedule
     * @param \Carbon\Carbon $date Date
     * @return \Carbon\Carbon|null
     */
    private function getScheduledStart(WorkSchedule $schedule, Carbon $date): ?Carbon
    {
        if (!$schedule) {
            return null;
        }

        $dayOfWeek = strtolower($date->format('l')); // 'monday', 'tuesday', etc
        $startColumn = $dayOfWeek . '_start';

        // Use the model's getAttribute to access dynamic attributes
        if (isset($schedule->attributes[$startColumn])) {
            $startTime = $schedule->getAttribute($startColumn);
            if ($startTime) {
                return $date->copy()->setTimeFromTimeString($startTime);
            }
        }

        return null;
    }

    /**
     * Helper: Get scheduled end time for specific date and schedule
     * 
     * @param \App\Models\WorkSchedule $schedule Work schedule
     * @param \Carbon\Carbon $date Date
     * @return \Carbon\Carbon|null
     */
    private function getScheduledEnd(WorkSchedule $schedule, Carbon $date): ?Carbon
    {
        if (!$schedule) {
            return null;
        }

        $dayOfWeek = strtolower($date->format('l')); // 'monday', 'tuesday', etc
        $endColumn = $dayOfWeek . '_end';

        // Use the model's getAttribute to access dynamic attributes
        if (isset($schedule->attributes[$endColumn])) {
            $endTime = $schedule->getAttribute($endColumn);
            if ($endTime) {
                return $date->copy()->setTimeFromTimeString($endTime);
            }
        }

        return null;
    }

    /**
     * Helper: Extract time_in from attendance events (first time_in event)
     * 
     * @param Collection $events Attendance events for the day
     * @return \Carbon\Carbon|null
     */
    private function extractTimeIn(Collection $events): ?Carbon
    {
        $timeInEvent = $events->firstWhere('event_type', 'time_in');
        return $timeInEvent ? Carbon::parse($timeInEvent->event_time) : null;
    }

    /**
     * Helper: Extract time_out from attendance events (last time_out event)
     * 
     * @param Collection $events Attendance events for the day
     * @return \Carbon\Carbon|null
     */
    private function extractTimeOut(Collection $events): ?Carbon
    {
        $timeOutEvent = $events->where('event_type', 'time_out')->last();
        return $timeOutEvent ? Carbon::parse($timeOutEvent->event_time) : null;
    }

    /**
     * Helper: Calculate total break duration from events
     * 
     * Sums all break periods (break_end - break_start) for the day
     * 
     * @param Collection $events Attendance events for the day
     * @return int Total break duration in minutes
     */
    private function calculateBreakDuration(Collection $events): int
    {
        $breakDuration = 0;
        $breakStart = null;

        foreach ($events as $event) {
            if ($event->event_type === 'break_start') {
                $breakStart = Carbon::parse($event->event_time);
            } elseif ($event->event_type === 'break_end' && $breakStart) {
                $breakEnd = Carbon::parse($event->event_time);
                $breakDuration += $breakStart->diffInMinutes($breakEnd);
                $breakStart = null;
            }
        }

        return $breakDuration;
    }

    /**
     * Helper: Build empty summary for absent employees
     * 
     * @param int $employeeId Employee ID
     * @param \Carbon\Carbon $date Attendance date
     * @param \App\Models\WorkSchedule|null $workSchedule Work schedule (optional)
     * @return array Empty summary with absent flags
     */
    private function buildEmptySummary(int $employeeId, Carbon $date, ?WorkSchedule $workSchedule = null): array
    {
        return [
            'employee_id'           => $employeeId,
            'attendance_date'       => $date->toDateString(),
            'work_schedule_id'      => $workSchedule?->id,
            'needs_schedule_review' => false,
            'time_in'               => null,
            'time_out'              => null,
            'break_duration'        => 0,
            'total_hours_worked'    => 0,
            'regular_hours'         => 0,
            'overtime_hours'        => 0,
            'is_present'            => false,
            'is_late'               => false,
            'is_undertime'          => false,
            'is_overtime'           => false,
            'is_on_leave'           => $this->isEmployeeOnLeave($employeeId, $date),
            'late_minutes'          => null,
            'undertime_minutes'     => null,
        ];
    }

    private function isEmployeeOnLeave(int $employeeId, Carbon $date): bool
    {
        if (!class_exists(\App\Models\LeaveRequest::class)) {
            return false;
        }

        return \App\Models\LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Get grace period in minutes (configurable for tests/overrides)
     * 
     * @return int Grace period in minutes
     */
    public static function getGracePeriodMinutes(): int
    {
        return self::GRACE_PERIOD_MINUTES;
    }

    /**
     * Get overtime threshold in minutes (configurable for tests/overrides)
     * 
     * @return int Overtime threshold in minutes
     */
    public static function getOvertimeThresholdMinutes(): int
    {
        return self::OVERTIME_THRESHOLD_MINUTES;
    }

    /**
     * Task 5.3.3: Store or update computed daily attendance summary in database.
     * 
     * Takes a computed summary array (from computeDailySummary + applyBusinessRules),
     * saves to daily_attendance_summary table, and dispatches AttendanceSummaryUpdated event.
     * 
     * Handles both creation and updates:
     * - New summary: Creates new DailyAttendanceSummary record
     * - Existing summary: Updates existing record with new calculated values
     * 
     * @param array $summary Computed summary array with all fields
     * @param int|null $ledgerSequenceStart Optional: Starting ledger sequence ID
     * @param int|null $ledgerSequenceEnd Optional: Ending ledger sequence ID
     * @return DailyAttendanceSummary The created/updated summary model
     * 
     * @throws \Exception If employee or work schedule not found
     * 
     * @example
     * $summary = $this->computeDailySummary(1, Carbon::parse('2024-01-15'));
     * $summary = $this->applyBusinessRules($summary);
     * $record = $this->storeDailySummary($summary, 100, 250); // Save to DB with ledger sequence
     * // Fires: AttendanceSummaryUpdated event
     * // Returns: DailyAttendanceSummary model instance
     */
    public function storeDailySummary(
        array $summary,
        ?int $ledgerSequenceStart = null,
        ?int $ledgerSequenceEnd = null
    ): DailyAttendanceSummary {
        try {
            $employeeId = $summary['employee_id'];
            $attendanceDate = $summary['attendance_date'];

            // Verify employee exists
            $employee = Employee::findOrFail($employeeId);

            // Parse attendance_date to ensure consistent format
            if (is_string($attendanceDate)) {
                $attendanceDateParsed = Carbon::parse($attendanceDate)->toDateString();
            } else {
                $attendanceDateParsed = $attendanceDate->toDateString();
            }

            // Prepare data for storage - map computed summary to database columns
            $storageData = [
                'work_schedule_id' => $summary['work_schedule_id'] ?? null,
                'time_in' => $summary['time_in'] ?? null,
                'time_out' => $summary['time_out'] ?? null,
                'break_start' => $summary['break_start'] ?? null,
                'break_end' => $summary['break_end'] ?? null,
                'total_hours_worked' => $summary['total_hours_worked'] ?? null,
                'regular_hours' => $summary['regular_hours'] ?? null,
                'overtime_hours' => $summary['overtime_hours'] ?? null,
                'break_duration' => $summary['break_duration'] ?? null,
                'is_present' => $summary['is_present'] ?? false,
                'is_late' => $summary['is_late'] ?? false,
                'is_undertime' => $summary['is_undertime'] ?? false,
                'is_overtime' => $summary['is_overtime'] ?? false,
                'late_minutes' => $summary['late_minutes'] ?? null,
                'undertime_minutes' => $summary['undertime_minutes'] ?? null,
                'is_on_leave'           => $summary['is_on_leave'] ?? false,
                'needs_schedule_review' => $summary['needs_schedule_review'] ?? false,
                'ledger_sequence_start' => $ledgerSequenceStart,
                'ledger_sequence_end'   => $ledgerSequenceEnd,
                'calculated_at'         => Carbon::now(),
            ];

            // Check if summary already exists for this date
            $existingRecord = DailyAttendanceSummary::where('employee_id', $employeeId)
                ->whereDate('attendance_date', $attendanceDateParsed)
                ->first();

            // Determine if this is a new record (for event dispatch)
            $isNew = $existingRecord === null;
            $previousValues = null;

            // For updates, capture previous values for change tracking
            if ($existingRecord) {
                $previousValues = $existingRecord->only(array_keys($storageData));
                // Update existing record
                $existingRecord->update($storageData);
                $summaryRecord = $existingRecord->fresh();
            } else {
                // Create new record
                $storageData['employee_id'] = $employeeId;
                $storageData['attendance_date'] = $attendanceDateParsed;
                $summaryRecord = DailyAttendanceSummary::create($storageData);
            }

            // Log the storage action for audit trail
            Log::info('Daily attendance summary stored', [
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
                'is_new' => $isNew,
                'summary_id' => $summaryRecord->id,
                'is_present' => $summaryRecord->is_present,
                'is_late' => $summaryRecord->is_late,
                'is_overtime' => $summaryRecord->is_overtime,
            ]);

            // Dispatch AttendanceSummaryUpdated event for downstream processing
            $this->dispatchSummaryUpdated($summaryRecord, $isNew, $previousValues);

            return $summaryRecord;

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Failed to store attendance summary: Employee not found', [
                'employee_id' => $summary['employee_id'] ?? 'unknown',
                'attendance_date' => $summary['attendance_date'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store attendance summary', [
                'employee_id' => $summary['employee_id'] ?? 'unknown',
                'attendance_date' => $summary['attendance_date'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Task 5.3.4: Dispatch AttendanceSummaryUpdated event for downstream processing.
     * 
     * Called after storeDailySummary() to trigger event listeners in:
     * - Payroll module: Recalculate affected payroll period
     * - Notification module: Send alerts for late/absent/violation scenarios
     * - Appraisal module: Update performance metrics (attendance quality)
     * - Audit logging: Record summary for compliance tracking
     * 
     * Event carries the summary model and metadata about the change
     * (is_new flag, previous values for change tracking).
     * 
     * @param DailyAttendanceSummary $summary The attendance summary model
     * @param bool $isNew True if this is a new record, false if updated
     * @param array|null $previousValues Previous field values for change tracking
     * @return void
     * 
     * @example
     * // Called internally by storeDailySummary
     * $this->dispatchSummaryUpdated($summaryRecord, true, null);
     * // Triggers: Payroll recalculation, Notifications, Appraisal updates
     */
    private function dispatchSummaryUpdated(
        DailyAttendanceSummary $summary,
        bool $isNew = false,
        ?array $previousValues = null
    ): void {
        try {
            // Dispatch the event with summary model and metadata
            AttendanceSummaryUpdated::dispatch($summary, $isNew, $previousValues);

            Log::info('Attendance summary updated event dispatched', [
                'summary_id' => $summary->id,
                'employee_id' => $summary->employee_id,
                'attendance_date' => $summary->attendance_date,
                'is_new' => $isNew,
                'event' => AttendanceSummaryUpdated::class,
            ]);

        } catch (\Exception $e) {
            // Log dispatch failure but don't throw - event dispatch should not fail the summary save
            Log::warning('Failed to dispatch AttendanceSummaryUpdated event', [
                'summary_id' => $summary->id ?? 'unknown',
                'employee_id' => $summary->employee_id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

}
