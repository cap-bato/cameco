<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
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

            // 2. Get Today's Attendance (placeholder - awaiting Timekeeping module integration)
            // TODO: Replace with actual attendance data from Timekeeping module
            $todayAttendance = [
                'time_in' => null, // e.g., '08:15 AM'
                'time_out' => null, // e.g., '05:30 PM'
                'hours_worked' => 0, // e.g., 9.25
                'status' => 'Not clocked in', // 'Present', 'Late', 'Absent', 'Not clocked in'
            ];

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
