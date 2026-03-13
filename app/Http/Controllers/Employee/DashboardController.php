<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\DailyAttendanceSummary;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\RfidCardMapping;
use App\Models\RfidLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the Employee Dashboard.
     * 
     * Shows quick stats (leave balances, attendance, pending requests, next payday),
     * recent activity, and quick action shortcuts for the authenticated employee.
     * 
     * This controller enforces "self-only" data access - employees can ONLY view
     * their own information.
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
            Log::error('Employee dashboard access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        Log::info('Employee dashboard accessed', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
        ]);

        try {
            // 1. Calculate Leave Balances (by type)
            $leaveBalances = LeaveBalance::where('employee_id', $employee->id)
                ->where('year', now()->year)
                ->with('leavePolicy:id,name,code')
                ->get()
                ->map(function ($balance) {
                    return [
                        'leave_type' => $balance->leavePolicy->name ?? 'Unknown',
                        'code' => $balance->leavePolicy->code ?? 'N/A',
                        'earned' => (float) $balance->earned,
                        'used' => (float) $balance->used,
                        'remaining' => (float) $balance->remaining,
                        'carried_forward' => (float) $balance->carried_forward,
                    ];
                });

            // 2. Get Today's Attendance from timekeeping data (summary, then live events fallback)
            $todayAttendance = $this->getTodayAttendance($employee->id);

            // 3. Count Pending Leave Requests
            $pendingRequestsCount = LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'Pending')
                ->count();

            // 4. Calculate Next Payday (placeholder - awaiting Payroll module integration)
            // TODO: Replace with actual payroll schedule from Payroll module
            $nextPayday = $this->calculateNextPayday();

            // 5. Get Recent Activity (last 5 actions)
            $recentActivity = $this->getRecentActivity($employee->id);

            return Inertia::render('Employee/Dashboard', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'position' => $employee->position->title ?? 'N/A',
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'quickStats' => [
                    'leave_balances' => $leaveBalances,
                    'today_attendance' => $todayAttendance,
                    'pending_requests_count' => $pendingRequestsCount,
                    'next_payday' => $nextPayday,
                ],
                'recentActivity' => $recentActivity,
            ]);
        } catch (\Exception $e) {
            Log::error('Employee dashboard data fetch failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Employee/Dashboard', [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->profile->full_name ?? $user->full_name,
                    'position' => $employee->position->title ?? 'N/A',
                    'department' => $employee->department->name ?? 'N/A',
                ],
                'quickStats' => [
                    'leave_balances' => [],
                    'today_attendance' => ['status' => 'Data unavailable'],
                    'pending_requests_count' => 0,
                    'next_payday' => null,
                ],
                'recentActivity' => [],
                'error' => 'Unable to load some dashboard data. Please refresh or contact HR if the issue persists.',
            ]);
        }
    }

    /**
     * Resolve today's attendance using the latest available source.
     *
     * Priority:
     * 1) DailyAttendanceSummary for today (if already generated/updated)
     * 2) Raw AttendanceEvent taps for today (real-time fallback)
     *
     * @param int $employeeId
     * @return array
     */
    private function getTodayAttendance(int $employeeId): array
    {
        $today = now()->toDateString();

        $summary = DailyAttendanceSummary::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $today)
            ->first();

        $liveAttendance = $this->getLiveTodayAttendance($employeeId, $today);

        if ($liveAttendance['status'] !== 'Not clocked in') {
            return $liveAttendance;
        }

        if ($summary) {
            $status = 'Not clocked in';

            if ($summary->is_on_leave) {
                $status = 'On leave';
            } elseif ($summary->is_present && $summary->is_late) {
                $status = 'Late';
            } elseif ($summary->is_present) {
                $status = 'Present';
            }

            return [
                'time_in' => $summary->time_in?->format('h:i A'),
                'time_out' => $summary->time_out?->format('h:i A'),
                'hours_worked' => $summary->total_hours_worked,
                'status' => $status,
            ];
        }

        return $liveAttendance;
    }

    /**
     * Build today's attendance from live records first so fresh RFID taps show immediately.
     *
     * @param int $employeeId
     * @param string $today
     * @return array
     */
    private function getLiveTodayAttendance(int $employeeId, string $today): array
    {
        // Fallback for same-day RFID taps before summaries are generated or refreshed
        $events = AttendanceEvent::where('employee_id', $employeeId)
            ->whereDate('event_date', $today)
            ->orderBy('event_time')
            ->get();

        $timeInEvent = $events->first(function (AttendanceEvent $event) {
            return in_array($event->event_type, ['time_in', 'IN'], true);
        });

        $timeOutEvent = $events->filter(function (AttendanceEvent $event) {
            return in_array($event->event_type, ['time_out', 'OUT'], true);
        })->sortByDesc('event_time')->first();

        $tapEvents = $events->filter(function (AttendanceEvent $event) {
            return in_array($event->event_type, ['tap', 'TAP'], true);
        })->values();

        // Devices that only emit "tap" are treated as alternating IN/OUT events.
        if (!$timeInEvent && $tapEvents->isNotEmpty()) {
            $timeInEvent = $tapEvents->first();
            if ($tapEvents->count() % 2 === 0) {
                $timeOutEvent = $tapEvents->last();
            }
        }

        if (!$timeInEvent) {
            // Last fallback: read today's raw ledger taps for active badge mappings,
            // so dashboard reflects scans even before the processor creates AttendanceEvent rows.
            $cardUids = RfidCardMapping::where('employee_id', $employeeId)
                ->where('is_active', true)
                ->pluck('card_uid');

            if ($cardUids->isEmpty()) {
                return [
                    'time_in' => null,
                    'time_out' => null,
                    'hours_worked' => null,
                    'status' => 'Not clocked in',
                ];
            }

            $ledgerTaps = RfidLedger::whereIn('employee_rfid', $cardUids)
                ->whereDate('scan_timestamp', $today)
                ->whereIn('event_type', ['tap', 'time_in', 'time_out'])
                ->orderBy('scan_timestamp')
                ->get();

            if ($ledgerTaps->isEmpty()) {
                return [
                    'time_in' => null,
                    'time_out' => null,
                    'hours_worked' => null,
                    'status' => 'Not clocked in',
                ];
            }

            $firstTap = $ledgerTaps->first();
            $lastTap = $ledgerTaps->last();
            $hasEvenTaps = ($ledgerTaps->count() % 2 === 0);

            $hoursWorked = null;
            if ($hasEvenTaps) {
                $workedMinutes = max(0, $firstTap->scan_timestamp->diffInMinutes($lastTap->scan_timestamp));
                $hoursWorked = round($workedMinutes / 60, 2);
            }

            return [
                'time_in' => $firstTap->scan_timestamp->format('h:i A'),
                'time_out' => $hasEvenTaps ? $lastTap->scan_timestamp->format('h:i A') : null,
                'hours_worked' => $hoursWorked,
                'status' => $hasEvenTaps ? 'Clocked out' : 'Clocked in',
            ];
        }

        $hoursWorked = null;
        if ($timeOutEvent) {
            $workedMinutes = max(0, $timeInEvent->event_time->diffInMinutes($timeOutEvent->event_time));
            $hoursWorked = round($workedMinutes / 60, 2);
        }

        return [
            'time_in' => $timeInEvent->event_time->format('h:i A'),
            'time_out' => $timeOutEvent?->event_time?->format('h:i A'),
            'hours_worked' => $hoursWorked,
            'status' => $timeOutEvent ? 'Clocked out' : 'Clocked in',
        ];
    }

    /**
     * Calculate next payday date based on payroll schedule.
     * 
     * PLACEHOLDER: This logic should be replaced with actual payroll schedule
     * from the Payroll module once integrated.
     * 
     * @return array|null
     */
    private function calculateNextPayday(): ?array
    {
        // Placeholder logic: Assume 15th and 30th of each month
        $now = now();
        $currentDay = $now->day;
        
        if ($currentDay < 15) {
            $nextPayday = $now->copy()->day(15);
        } elseif ($currentDay < 30) {
            $nextPayday = $now->copy()->day(30);
        } else {
            $nextPayday = $now->copy()->addMonth()->day(15);
        }

        $daysUntil = $now->diffInDays($nextPayday, false);

        return [
            'date' => $nextPayday->format('Y-m-d'),
            'formatted_date' => $nextPayday->format('F d, Y'),
            'days_until' => max(0, $daysUntil),
        ];
    }

    /**
     * Get recent activity for the employee (last 5 actions).
     * 
     * Returns a chronological list of recent events:
     * - Leave request submissions
     * - Leave approvals/rejections
     * - Profile update submissions
     * - Attendance corrections
     * - Payslip releases (placeholder)
     * 
     * @param int $employeeId
     * @return array
     */
    private function getRecentActivity(int $employeeId): array
    {
        $activities = [];

        // Leave request activity
        $leaveRequests = LeaveRequest::where('employee_id', $employeeId)
            ->with('leavePolicy:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($leaveRequests as $request) {
            $activities[] = [
                'type' => 'leave_request',
                'icon' => 'Calendar',
                'title' => $request->status === 'Pending' 
                    ? 'Leave request submitted' 
                    : 'Leave request ' . strtolower($request->status),
                'description' => ($request->leavePolicy->name ?? 'Leave') . ' - ' 
                    . $request->start_date->format('M d') . ' to ' . $request->end_date->format('M d, Y'),
                'timestamp' => $request->created_at,
                'status' => $request->status,
            ];
        }

        // Profile update activity (placeholder - awaiting profile_update_requests table integration)
        // TODO: Add profile update activity from profile_update_requests table

        // Attendance correction activity (placeholder - awaiting attendance_correction_requests table integration)
        // TODO: Add attendance correction activity from attendance_correction_requests table

        // Payslip release activity (placeholder - awaiting Payroll module integration)
        // TODO: Add payslip release activity from Payroll module

        // Sort all activities by timestamp (most recent first)
        usort($activities, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Return only the last 5 activities
        return array_slice(array_map(function ($activity) {
            return [
                'type' => $activity['type'],
                'icon' => $activity['icon'],
                'title' => $activity['title'],
                'description' => $activity['description'],
                'timestamp' => $activity['timestamp']->format('Y-m-d H:i:s'),
                'formatted_timestamp' => $activity['timestamp']->diffForHumans(),
                'status' => $activity['status'] ?? null,
            ];
        }, $activities), 0, 5);
    }
}
