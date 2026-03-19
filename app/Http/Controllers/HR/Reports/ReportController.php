<?php

namespace App\Http\Controllers\HR\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Services\HR\HRAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display employee statistics and reports.
     */
    public function employees(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        // Placeholder data for frontend development (will be replaced with real data in ISSUE-9 Phase 4)
        $summary = [
            'total_employees' => 45,
            'active_employees' => 38,
            'inactive_employees' => 5,
            'terminated_employees' => 2,
            'on_leave_employees' => 3,
            'average_tenure_years' => 4.5,
        ];

        $byDepartment = [
            [
                'department_id' => 1,
                'department_name' => 'Engineering',
                'employee_count' => 15,
                'percentage' => 33.33,
            ],
            [
                'department_id' => 2,
                'department_name' => 'Sales',
                'employee_count' => 12,
                'percentage' => 26.67,
            ],
            [
                'department_id' => 3,
                'department_name' => 'Human Resources',
                'employee_count' => 8,
                'percentage' => 17.78,
            ],
            [
                'department_id' => 4,
                'department_name' => 'Finance',
                'employee_count' => 6,
                'percentage' => 13.33,
            ],
            [
                'department_id' => 5,
                'department_name' => 'Operations',
                'employee_count' => 4,
                'percentage' => 8.89,
            ],
        ];

        // Placeholder recent hires with mock data
        $recentHires = [];

        return Inertia::render('HR/Reports/Employees', [
            'summary' => $summary,
            'by_department' => $byDepartment,
            'by_status' => [
                ['status' => 'active', 'count' => 38, 'percentage' => 84.44],
                ['status' => 'inactive', 'count' => 5, 'percentage' => 11.11],
                ['status' => 'terminated', 'count' => 2, 'percentage' => 4.44],
            ],
            'recent_hires' => $recentHires,
            'headcount_trend' => [],
            'can_export' => auth()->user()->can('hr.employees.export'),
        ]);
    }

    /**
     * Display leave statistics and reports.
     */
    public function leave(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        // Live leave statistics
        $now = now();
        $yearStart = $now->copy()->startOfYear();
        $leaveRequests = \App\Models\LeaveRequest::with('leavePolicy')
            ->whereYear('start_date', $now->year)
            ->get();

        $summary = [
            'total_pending_requests' => $leaveRequests->where('status', 'pending')->count(),
            'total_approved_requests' => $leaveRequests->where('status', 'approved')->count(),
            'total_rejected_requests' => $leaveRequests->where('status', 'rejected')->count(),
            'employees_on_leave' => \App\Models\LeaveRequest::where('status', 'approved')
                ->where('start_date', '<=', $now->toDateString())
                ->where('end_date', '>=', $now->toDateString())
                ->distinct('employee_id')->count('employee_id'),
            'leave_days_used_this_year' => $leaveRequests->where('status', 'approved')->sum('days_requested'),
            'leave_days_remaining_average' => round(\App\Models\LeaveBalance::where('year', $now->year)->avg('remaining'), 1),
        ];

        // Leave by type
        $byType = $leaveRequests->groupBy(fn($r) => $r->leavePolicy?->name ?? 'Unknown')
            ->map(function ($group, $type) use ($leaveRequests) {
                $count = $group->count();
                $total = $leaveRequests->count();
                return [
                    'leave_type' => $type,
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                ];
            })->values();

        // Leave by status
        $byStatus = $leaveRequests->groupBy('status')
            ->map(function ($group, $status) use ($leaveRequests) {
                $count = $group->count();
                $total = $leaveRequests->count();
                return [
                    'status' => ucfirst($status),
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                ];
            })->values();

        // Monthly leave trends (last 6 months)
        $months = collect(range(0, 5))->map(function ($i) use ($now) {
            return $now->copy()->subMonths($i)->startOfMonth();
        })->reverse();

        $byMonth = $months->map(function ($month) use ($leaveRequests) {
            $monthStr = $month->format('Y-m');
            $requests = $leaveRequests->filter(function ($r) use ($month) {
                return $r->start_date->format('Y-m') === $month->format('Y-m');
            });
            return [
                'month' => $month->format('M Y'),
                'total' => $requests->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'pending' => $requests->where('status', 'pending')->count(),
                'rejected' => $requests->where('status', 'rejected')->count(),
                'cancelled' => $requests->where('status', 'cancelled')->count(),
            ];
        });

        return Inertia::render('HR/Reports/Leave', [
            'summary' => $summary,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_month' => $byMonth,
            'top_users' => [],
            'can_export' => auth()->user()->can('hr.leave.export'),
        ]);
    }

    /**
     * Display HR analytics dashboard.
     */
    public function analytics(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        // Inject and use HRAnalyticsService for real data
        $analyticsService = app(HRAnalyticsService::class);
        $metrics = $analyticsService->getDashboardMetrics();

        return Inertia::render('HR/Reports/Analytics', [
            'metrics' => $metrics,
        ]);
    }
}
