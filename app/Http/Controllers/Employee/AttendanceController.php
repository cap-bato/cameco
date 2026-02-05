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
            // PLACEHOLDER: Awaiting Timekeeping module integration
            // TODO: Replace with actual attendance data from Timekeeping module
            // Query should be: Attendance::where('employee_id', $employee->id)->whereBetween('date', [$startDate, $endDate])->get()
            
            $attendanceRecords = $this->getMockAttendanceRecords($employee->id, $startDate, $endDate);
            $attendanceSummary = $this->calculateAttendanceSummary($attendanceRecords);
            $rfidPunchHistory = $this->getMockRFIDPunchHistory($employee->id, $startDate, $endDate);

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

            // TODO: Send notification to HR Staff
            // Use Laravel Notifications: App\Notifications\AttendanceCorrectionRequested
            // Notify all users with 'HR Staff' role about pending correction request
            // Example:
            // $hrStaff = User::role('HR Staff')->get();
            // Notification::send($hrStaff, new AttendanceCorrectionRequested($employee, $correctionRequestId, $validated));

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

    /**
     * Generate mock attendance records for testing.
     * 
     * PLACEHOLDER: This method should be removed once Timekeeping module is integrated.
     * 
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getMockAttendanceRecords(int $employeeId, string $startDate, string $endDate): array
    {
        $records = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($start <= $end) {
            // Skip weekends
            if ($start->isWeekday()) {
                $statusOptions = ['present', 'late', 'on_leave'];
                $status = $statusOptions[array_rand($statusOptions)];

                $records[] = [
                    'date' => $start->format('Y-m-d'),
                    'day_name' => $start->format('l'),
                    'time_in' => $status === 'on_leave' ? null : $start->copy()->setTime(8, rand(0, 30))->format('H:i:s'),
                    'time_out' => $status === 'on_leave' ? null : $start->copy()->setTime(17, rand(0, 30))->format('H:i:s'),
                    'break_start' => $status === 'on_leave' ? null : '12:00:00',
                    'break_end' => $status === 'on_leave' ? null : '13:00:00',
                    'hours_worked' => $status === 'on_leave' ? 0 : round(8 + (rand(-10, 10) / 10), 2),
                    'status' => $status,
                    'late_minutes' => $status === 'late' ? rand(5, 30) : 0,
                    'remarks' => $status === 'on_leave' ? 'Approved Leave' : null,
                ];
            }

            $start->addDay();
        }

        return $records;
    }

    /**
     * Calculate attendance summary statistics.
     * 
     * @param array $records
     * @return array
     */
    private function calculateAttendanceSummary(array $records): array
    {
        $daysPresent = count(array_filter($records, fn($r) => $r['status'] === 'present'));
        $daysLate = count(array_filter($records, fn($r) => $r['status'] === 'late'));
        $daysOnLeave = count(array_filter($records, fn($r) => $r['status'] === 'on_leave'));
        $totalHoursWorked = array_sum(array_column($records, 'hours_worked'));

        return [
            'days_present' => $daysPresent,
            'days_late' => $daysLate,
            'days_absent' => 0, // Placeholder
            'days_on_leave' => $daysOnLeave,
            'total_hours_worked' => round($totalHoursWorked, 2),
            'average_hours_per_day' => count($records) > 0 ? round($totalHoursWorked / count($records), 2) : 0,
        ];
    }

    /**
     * Generate mock RFID punch history for testing.
     * 
     * PLACEHOLDER: This method should be removed once Timekeeping module is integrated.
     * 
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getMockRFIDPunchHistory(int $employeeId, string $startDate, string $endDate): array
    {
        $punches = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        // Generate last 7 days of RFID punches
        $lastSevenDays = min(7, $start->diffInDays($end) + 1);
        $punchDate = $end->copy();

        for ($i = 0; $i < $lastSevenDays; $i++) {
            if ($punchDate->isWeekday()) {
                $punches[] = [
                    'date' => $punchDate->format('Y-m-d'),
                    'timestamp' => $punchDate->copy()->setTime(8, rand(0, 30))->format('Y-m-d H:i:s'),
                    'punch_type' => 'IN',
                    'device_name' => 'Main Entrance - RFID Reader 1',
                ];

                $punches[] = [
                    'date' => $punchDate->format('Y-m-d'),
                    'timestamp' => $punchDate->copy()->setTime(17, rand(0, 30))->format('Y-m-d H:i:s'),
                    'punch_type' => 'OUT',
                    'device_name' => 'Main Entrance - RFID Reader 1',
                ];
            }

            $punchDate->subDay();
        }

        return array_reverse($punches);
    }
}
