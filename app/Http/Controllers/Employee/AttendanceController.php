<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\AttendanceIssueRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    /**
     * Display attendance records for the authenticated employee.
     * 
     * Shows:
     * - Daily/Weekly/Monthly attendance records
     * - RFID punch history (placeholder - awaiting Timekeeping module)
     * - Attendance summary (days present, late, absent, hours worked)
     * - Filter by date range
     * 
     * Enforces "self-only" data access - employees can ONLY view their own attendance.
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee attendance access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        Log::info('Employee attendance viewed', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
        ]);

        // Get filter parameters
        $view = $request->input('view', 'monthly'); // daily, weekly, monthly
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        try {
            // Phase 1 Task 1.3: Query real attendance records from DailyAttendanceSummary
            $summaries = \App\Models\DailyAttendanceSummary::where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->orderBy('attendance_date')
                ->get();

            // Build real attendance records and summary
            $attendanceRecords = $this->buildAttendanceRecords($summaries, $startDate, $endDate);
            $attendanceSummary = $this->buildAttendanceSummary($summaries, $startDate, $endDate);
            $rfidPunchHistory = $this->getRealRFIDPunchHistory($employee, $startDate, $endDate);

            return Inertia::render('Employee/Attendance/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'attendanceRecords' => $attendanceRecords,
                'attendanceSummary' => $attendanceSummary,
                'rfidPunchHistory' => $rfidPunchHistory,
                'filters' => [
                    'view' => $view,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Employee attendance data fetch failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Employee/Attendance/Index', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'attendanceRecords' => [],
                'attendanceSummary' => [
                    'days_present' => 0,
                    'days_late' => 0,
                    'days_absent' => 0,
                    'total_hours_worked' => 0,
                ],
                'rfidPunchHistory' => [],
                'filters' => [
                    'view' => $view,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'error' => 'Unable to load attendance data. Please refresh or contact HR if the issue persists.',
            ]);
        }
    }

    /**
     * Submit an attendance correction request.
     * 
     * Employees can report attendance issues such as:
     * - Missing time punch (forgot to clock in/out)
     * - Wrong time recorded (system error, RFID malfunction)
     * - Other attendance discrepancies
     * 
     * All correction requests are stored in attendance_correction_requests table
     * with status 'pending' and require HR Staff verification before correction.
     * 
     * Enforces "self-only" data access - employees can ONLY report issues for their own attendance.
     * 
     * @param AttendanceIssueRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reportIssue(AttendanceIssueRequest $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Attendance correction request attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        DB::beginTransaction();

        try {
            $validated = $request->validated();

            // Store attendance correction request
            $correctionRequestId = DB::table('attendance_correction_requests')->insertGetId([
                'employee_id' => $employee->id,
                'attendance_date' => $validated['attendance_date'],
                'issue_type' => $validated['issue_type'],
                'actual_time_in' => $validated['actual_time_in'] ?? null,
                'actual_time_out' => $validated['actual_time_out'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Notify all HR Staff users about the pending correction request
            // Phase 3 Task 3.1: HR notification
            try {
                $hrUsers = \App\Models\User::role('HR Staff')->get();
                if ($hrUsers->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send(
                        $hrUsers,
                        new \App\Notifications\AttendanceCorrectionRequested($employee, $correctionRequestId, $validated)
                    );
                }
            } catch (\Exception $notifyException) {
                Log::warning('Failed to send attendance correction notification to HR Staff', [
                    'employee_id' => $employee->id,
                    'correction_request_id' => $correctionRequestId,
                    'error' => $notifyException->getMessage(),
                ]);
                // Do not re-throw — the request was saved successfully
            }

            DB::commit();

            Log::info('Attendance correction request submitted successfully', [
                'employee_id' => $employee->id,
                'correction_request_id' => $correctionRequestId,
                'attendance_date' => $validated['attendance_date'],
                'issue_type' => $validated['issue_type'],
            ]);

            return back()->with('success', 
                'Attendance correction request submitted successfully. ' .
                'HR Staff will review your request and make necessary corrections. ' .
                'You will be notified once your request is processed.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Attendance correction request failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 
                'Failed to submit attendance correction request. Please try again or contact HR Staff if the issue persists.'
            );
        }
    }

    // ========== Real Attendance Helper Methods (Phase 1 Task 1.2, Phase 2 Task 2.1-2.2) ==========

    /**
     * Map RFID event type from database format to frontend format.
     * 
     * Database stores: time_in, time_out, break_start, break_end
     * Frontend expects: IN, OUT, BREAK_IN, BREAK_OUT
     * 
     * @param string $eventType
     * @return string
     */
    private function mapRfidEventType(string $eventType): string
    {
        return match ($eventType) {
            'time_in'     => 'IN',
            'time_out'    => 'OUT',
            'break_start' => 'BREAK_IN',
            'break_end'   => 'BREAK_OUT',
            default       => 'IN',
        };
    }

    /**
     * Derive attendance status from DailyAttendanceSummary record.
     * 
     * Status precedence:
     * 1. is_on_leave → 'on_leave'
     * 2. is_present && is_late → 'late'
     * 3. is_present → 'present'
     * 4. No record & weekend → 'rest_day'
     * 5. No record & weekday → 'absent'
     * 
     * @param \App\Models\DailyAttendanceSummary|null $record
     * @param string $date ISO date string (Y-m-d)
     * @return string Status: present|late|absent|on_leave|rest_day
     */
    private function deriveAttendanceStatus(?\App\Models\DailyAttendanceSummary $record, string $date): string
    {
        // Record exists - check in order of precedence
        if ($record) {
            if ($record->is_on_leave) {
                return 'on_leave';
            }
            if ($record->is_present && $record->is_late) {
                return 'late';
            }
            if ($record->is_present) {
                return 'present';
            }
            // Present flag false but record exists (absent)
            return 'absent';
        }

        // No record for this date - check if weekend or weekday
        $dayOfWeek = \Carbon\Carbon::parse($date)->dayOfWeek;
        // 0 = Sunday, 6 = Saturday
        return in_array($dayOfWeek, [0, 6]) ? 'rest_day' : 'absent';
    }

    /**
     * Count the number of weekdays (Monday-Friday) between two dates inclusive.
     * 
     * @param string $startDate ISO date string (Y-m-d)
     * @param string $endDate ISO date string (Y-m-d)
     * @return int Number of weekdays
     */
    private function countWeekdays(string $startDate, string $endDate): int
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $count = 0;

        while ($start->lte($end)) {
            if ($start->isWeekday()) {
                $count++;
            }
            $start->addDay();
        }

        return $count;
    }

    /**
     * Build date-by-date attendance records array for date range.
     * 
     * Fills in all dates from start to end, including:
     * - Dates with DailyAttendanceSummary records
     * - Absent/rest days for dates with no record
     * 
     * @param \Illuminate\Support\Collection $summaries DailyAttendanceSummary records
     * @param string $startDate ISO date string (Y-m-d)
     * @param string $endDate ISO date string (Y-m-d)
     * @return array Date-indexed attendance records
     */
    private function buildAttendanceRecords(
        \Illuminate\Support\Collection $summaries,
        string $startDate,
        string $endDate
    ): array {
        // Key summaries by date string for O(1) lookup
        $byDate = $summaries->keyBy(function ($s) {
            return $s->attendance_date instanceof \Carbon\Carbon
                ? $s->attendance_date->format('Y-m-d')
                : $s->attendance_date;
        });

        $records = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $record = $byDate->get($dateStr);

            $records[] = [
                'date'         => $dateStr,
                'status'       => $this->deriveAttendanceStatus($record, $dateStr),
                'time_in'      => $record?->time_in ? $record->time_in->format('H:i:s') : null,
                'time_out'     => $record?->time_out ? $record->time_out->format('H:i:s') : null,
                'hours_worked' => $record ? (float) $record->total_hours_worked : null,
                'late_minutes' => $record?->late_minutes,
                'remarks'      => $record?->leave_request_id
                    ? 'Approved Leave'
                    : ($record?->correction_applied ? 'Corrected' : null),
            ];

            $current->addDay();
        }

        return $records;
    }

    /**
     * Build attendance summary statistics from real DailyAttendanceSummary records.
     * 
     * Calculates:
     * - Days present/late/absent/on_leave
     * - Total and average hours worked
     * 
     * Days absent is computed from expected weekdays minus accounted days.
     * 
     * Phase 1 Task 1.3: Real attendance statistics (Phase 2 Task 2.1)
     * 
     * @param \Illuminate\Support\Collection $summaries DailyAttendanceSummary records
     * @param string $startDate ISO date string (Y-m-d)
     * @param string $endDate ISO date string (Y-m-d)
     * @return array Attendance summary with day counts and hour stats
     */
    private function buildAttendanceSummary(
        \Illuminate\Support\Collection $summaries,
        string $startDate,
        string $endDate
    ): array {
        // Count different status categories
        $daysPresent = $summaries->filter(fn($r) => $r->is_present && !$r->is_late)->count();
        $daysLate = $summaries->filter(fn($r) => $r->is_late)->count();
        $daysOnLeave = $summaries->filter(fn($r) => $r->is_on_leave)->count();

        // Compute days absent from expected weekdays
        $expectedWorkdays = $this->countWeekdays($startDate, $endDate);
        $daysAbsent = max(0, $expectedWorkdays - ($daysPresent + $daysLate + $daysOnLeave));

        // Calculate hours statistics
        $totalHours = (float) $summaries->sum('total_hours_worked');
        $daysWithHours = $summaries->where('total_hours_worked', '>', 0)->count();
        $avgHours = $daysWithHours > 0 ? round($totalHours / $daysWithHours, 2) : 0.0;

        return [
            'days_present' => $daysPresent,
            'days_late' => $daysLate,
            'days_absent' => $daysAbsent,
            'days_on_leave' => $daysOnLeave,
            'total_hours_worked' => round($totalHours, 2),
            'average_hours_per_day' => $avgHours,
        ];
    }

    /**
     * Get real RFID punch history for the employee from RfidLedger.
     * 
     * Retrieves punch records by:
     * 1. Finding all active RFID cards assigned to this employee
     * 2. Querying RfidLedger for punches by those card UIDs in date range
     * 3. Joining with RfidDevice to get device_name and location
     * 4. Mapping event types and formatting timestamps
     * 
     * Phase 1 Task 1.3: Real RFID data (Phase 2 Task 2.2)
     * 
     * @param \App\Models\Employee $employee
     * @param string $startDate ISO date string (Y-m-d)
     * @param string $endDate ISO date string (Y-m-d)
     * @return array RFID punch records with type, device, location, timestamp
     */
    private function getRealRFIDPunchHistory(
        \App\Models\Employee $employee,
        string $startDate,
        string $endDate
    ): array {
        // Get all active RFID card UIDs for this employee
        $cardUids = \App\Models\RfidCardMapping::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->pluck('card_uid')
            ->toArray();

        if (empty($cardUids)) {
            return [];
        }

        // Fetch ledger entries within date range with safety limit
        $ledgerEntries = \App\Models\RfidLedger::whereIn('employee_rfid', $cardUids)
            ->whereBetween('scan_timestamp', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('scan_timestamp', 'desc')
            ->limit(200) // Safety cap to avoid huge result sets
            ->get();

        if ($ledgerEntries->isEmpty()) {
            return [];
        }

        // Pre-fetch only the devices that are actually used in results
        $deviceIds = $ledgerEntries->pluck('device_id')->unique()->toArray();
        $devices = \App\Models\RfidDevice::whereIn('device_id', $deviceIds)
            ->get()
            ->keyBy('device_id');

        // Transform ledger entries to RFIDPunch format
        return $ledgerEntries->map(function ($entry) use ($devices) {
            $device = $devices->get($entry->device_id);
            return [
                'id' => $entry->id,
                'timestamp' => $entry->scan_timestamp->toIso8601String(),
                'type' => $this->mapRfidEventType($entry->event_type),
                'device_name' => $device?->device_name ?? $entry->device_id,
                'location' => $device?->location ?? 'Unknown',
            ];
        })->toArray();
    }
}
