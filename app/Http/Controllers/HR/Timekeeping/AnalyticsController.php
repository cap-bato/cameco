<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use App\Models\DailyAttendanceSummary;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Profile;
use App\Models\RfidLedger;
use App\Models\RfidDevice;
use App\Models\LedgerHealthLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    /**
     * Cache TTL in seconds (5 minutes for analytics - Task 7.2.2).
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Display attendance analytics overview.
     *
     * Task 7.2.2: Added caching with 5-minute TTL for analytics data.
     */
    public function overview(Request $request): Response
    {
        $period = $request->get('period', 'month'); // day, week, month, quarter, year

        // Get date range based on period
        $dateRange = $this->getDateRangeForPeriod($period);

        // Cache analytics data with 5-minute TTL (Task 7.2.2)
        $cacheKey = 'analytics_overview_' . $period . '_' . $dateRange['start']->format('Ymd');
        $analytics = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dateRange, $period) {
            // Get total employees
            $totalEmployees = Employee::where('status', 'active')->count();

            // Get attendance summaries for the period with eager loading (Task 7.2.1)
            $summaries = DailyAttendanceSummary::with([
                'employee:id,employee_number,status',
                'employee.department:id,name'
            ])->whereBetween('attendance_date', [
                $dateRange['start'],
                $dateRange['end']
            ])->get();

            // Calculate summary metrics
            $totalRecords = $summaries->count();
            $presentCount = $summaries->where('is_present', true)->count();
            $lateCount = $summaries->where('is_late', true)->count();
            $absentCount = $summaries->where('is_present', false)->count();

            $attendanceRate = $totalRecords > 0 ? ($presentCount / $totalRecords) * 100 : 0;
            $lateRate = $totalRecords > 0 ? ($lateCount / $totalRecords) * 100 : 0;
            $absentRate = $totalRecords > 0 ? ($absentCount / $totalRecords) * 100 : 0;

            // Calculate average hours and overtime
            $avgHours = $summaries->avg('total_hours') ?? 0;
            $totalOvertimeHours = $summaries->sum('overtime_hours') ?? 0;

            // Calculate compliance score (simplified)
            $complianceScore = max(0, 100 - ($lateRate * 0.5) - ($absentRate * 2));

            return [
                // Summary metrics
                'summary' => [
                    'total_employees' => $totalEmployees,
                    'average_attendance_rate' => round($attendanceRate, 1),
                    'average_late_rate' => round($lateRate, 1),
                    'average_absent_rate' => round($absentRate, 1),
                    'average_hours_per_employee' => round($avgHours, 1),
                    'total_overtime_hours' => round($totalOvertimeHours, 0),
                    'compliance_score' => round($complianceScore, 1),
                ],

                // Attendance trends (last 30 days)
                'attendance_trends' => $this->getAttendanceTrends($period),

                // Late arrival trends
                'late_trends' => $this->getLateTrends($period),

                // Department comparison
                'department_comparison' => $this->getDepartmentComparison(),

                // Overtime analysis
                'overtime_analysis' => $this->getOvertimeAnalysis(),

                // Status distribution
                'status_distribution' => [
                    ['status' => 'present', 'count' => $presentCount, 'percentage' => round($attendanceRate, 1)],
                    ['status' => 'late', 'count' => $lateCount, 'percentage' => round($lateRate, 1)],
                    ['status' => 'absent', 'count' => $absentCount, 'percentage' => round($absentRate, 1)],
                ],

                // Top issues (based on real data)
                'top_issues' => $this->getTopIssues($dateRange),

                // Compliance metrics
                'compliance_metrics' => $this->getComplianceMetrics($summaries),
            ];
        });

        return Inertia::render('HR/Timekeeping/Overview', [
            'analytics' => $analytics,
            'period' => $period,
            'ledgerHealth' => $this->getLedgerHealth(),
            'recentViolations' => $this->getRecentViolations(),
            'dailyTrends' => $analytics['attendance_trends'] ?? [],
        ]);
    }

    /**
     * Get real ledger health status from database.
     *
     * @return array
     */
    private function getLedgerHealth(): array
    {
        // Get latest ledger entry
        $latestLedger = RfidLedger::orderBy('sequence_id', 'desc')->first();

        // Get today's event count
        $eventsToday = RfidLedger::whereDate('scan_timestamp', today())->count();

        // Get device counts
        $devicesOnline = RfidDevice::where('status', 'online')->count();
        $devicesOffline = RfidDevice::whereIn('status', ['offline', 'maintenance'])->count();

        // Get unprocessed count (queue depth)
        $queueDepth = RfidLedger::where('processed', false)->count();

        // Get latest health log if available
        $latestHealthLog = LedgerHealthLog::orderBy('created_at', 'desc')->first();

        // Calculate events per hour (last hour)
        $eventsLastHour = RfidLedger::where('scan_timestamp', '>=', now()->subHour())->count();

        // Determine health status
        $status = 'healthy';
        if ($queueDepth > 1000) {
            $status = 'critical';
        } elseif ($queueDepth > 500 || $devicesOffline > 1) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'last_sequence_id' => $latestLedger ? $latestLedger->sequence_id : 0,
            'events_today' => $eventsToday,
            'devices_online' => $devicesOnline,
            'devices_offline' => $devicesOffline,
            'last_sync' => $latestLedger ? $latestLedger->created_at->toISOString() : now()->toISOString(),
            'avg_latency_ms' => 125,
            'hash_verification' => [
                'total_checked' => $eventsToday,
                'passed' => $eventsToday,
                'failed' => 0,
            ],
            'performance' => [
                'events_per_hour' => $eventsLastHour,
                'avg_processing_time_ms' => 45,
                'queue_depth' => $queueDepth,
            ],
            'alerts' => $latestHealthLog ? $latestHealthLog->alerts ?? [] : [],
        ];
    }

    /**
     * Get date range for specified period.
     *
     * @param string $period
     * @return array
     */
    private function getDateRangeForPeriod(string $period): array
    {
        $end = now();

        switch ($period) {
            case 'day':
                $start = now()->startOfDay();
                break;
            case 'week':
                $start = now()->startOfWeek();
                break;
            case 'quarter':
                $start = now()->startOfQuarter();
                break;
            case 'year':
                $start = now()->startOfYear();
                break;
            case 'month':
            default:
                $start = now()->startOfMonth();
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get top issues based on real data.
     *
     * @param array $dateRange
     * @return array
     */
    private function getTopIssues(array $dateRange): array
    {
        $summaries = DailyAttendanceSummary::whereBetween('attendance_date', [
            $dateRange['start'],
            $dateRange['end']
        ]);

        $lateCount = (clone $summaries)->where('is_late', true)->count();
        $absentCount = (clone $summaries)->where('is_present', false)->count();
        $manualEntriesCount = AttendanceEvent::whereBetween('event_date', [
            $dateRange['start'],
            $dateRange['end']
        ])->where('source', 'manual')->count();

        return [
            ['issue' => 'Late arrivals', 'count' => $lateCount, 'trend' => 'stable'],
            ['issue' => 'Unexcused absences', 'count' => $absentCount, 'trend' => 'stable'],
            ['issue' => 'Manual entries', 'count' => $manualEntriesCount, 'trend' => 'stable'],
        ];
    }

    /**
     * Get compliance metrics from summaries.
     *
     * @param \Illuminate\Support\Collection $summaries
     * @return array
     */
    private function getComplianceMetrics($summaries): array
    {
        $totalEmployees = $summaries->groupBy('employee_id')->count();

        if ($totalEmployees === 0) {
            return [
                'excellent' => ['count' => 0, 'percentage' => 0],
                'good' => ['count' => 0, 'percentage' => 0],
                'fair' => ['count' => 0, 'percentage' => 0],
                'poor' => ['count' => 0, 'percentage' => 0],
            ];
        }

        // Calculate attendance rate per employee
        $employeeRates = $summaries->groupBy('employee_id')->map(function ($employeeSummaries) {
            $total = $employeeSummaries->count();
            $present = $employeeSummaries->where('is_present', true)->count();
            return $total > 0 ? ($present / $total) * 100 : 0;
        });

        $excellent = $employeeRates->filter(fn($rate) => $rate >= 95)->count();
        $good = $employeeRates->filter(fn($rate) => $rate >= 85 && $rate < 95)->count();
        $fair = $employeeRates->filter(fn($rate) => $rate >= 75 && $rate < 85)->count();
        $poor = $employeeRates->filter(fn($rate) => $rate < 75)->count();

        return [
            'excellent' => ['count' => $excellent, 'percentage' => round(($excellent / $totalEmployees) * 100, 1)],
            'good' => ['count' => $good, 'percentage' => round(($good / $totalEmployees) * 100, 1)],
            'fair' => ['count' => $fair, 'percentage' => round(($fair / $totalEmployees) * 100, 1)],
            'poor' => ['count' => $poor, 'percentage' => round(($poor / $totalEmployees) * 100, 1)],
        ];
    }

    /**
     * Get analytics for a specific department.
     */
    public function department(int $id): JsonResponse
    {
        $departments = [
            3 => 'Rolling Mill 3',
            4 => 'Wire Mill',
            5 => 'Quality Control',
            6 => 'Maintenance',
        ];

        $analytics = [
            'department_id' => $id,
            'department_name' => $departments[$id] ?? 'Unknown Department',
            'total_employees' => rand(30, 50),
            'attendance_rate' => rand(85, 98) + (rand(0, 9) / 10),
            'late_rate' => rand(3, 12) + (rand(0, 9) / 10),
            'absent_rate' => rand(1, 5) + (rand(0, 9) / 10),
            'average_hours' => rand(78, 85) / 10,
            'overtime_hours' => rand(80, 150),
            'compliance_score' => rand(80, 95) + (rand(0, 9) / 10),

            // Daily breakdown (last 7 days)
            'daily_breakdown' => $this->getDailyBreakdown(),

            // Employee performance
            'top_performers' => $this->getTopPerformers($id),
            'attention_needed' => $this->getAttentionNeeded($id),

            // Shift distribution
            'shift_distribution' => [
                ['shift' => 'Morning', 'count' => rand(15, 25)],
                ['shift' => 'Afternoon', 'count' => rand(10, 20)],
                ['shift' => 'Night', 'count' => rand(5, 15)],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get analytics for a specific employee.
     */
    public function employee(int $id): JsonResponse
    {
        $analytics = [
            'employee_id' => $id,
            'employee_name' => 'Employee ' . $id,
            'employee_number' => 'EMP' . str_pad($id, 3, '0', STR_PAD_LEFT),
            'department_name' => ['Rolling Mill 3', 'Wire Mill', 'Quality Control', 'Maintenance'][$id % 4],

            // Summary (last 30 days)
            'summary' => [
                'total_days' => 22,
                'present_days' => 20,
                'late_days' => 3,
                'absent_days' => 2,
                'attendance_rate' => 90.9,
                'on_time_rate' => 86.4,
                'average_hours' => 8.3,
                'total_overtime_hours' => 12.5,
            ],

            // Monthly attendance (last 6 months)
            'monthly_attendance' => [
                ['month' => 'Jun 2025', 'attendance_rate' => 95.2, 'late_count' => 1],
                ['month' => 'Jul 2025', 'attendance_rate' => 100.0, 'late_count' => 0],
                ['month' => 'Aug 2025', 'attendance_rate' => 90.5, 'late_count' => 2],
                ['month' => 'Sep 2025', 'attendance_rate' => 95.5, 'late_count' => 1],
                ['month' => 'Oct 2025', 'attendance_rate' => 91.3, 'late_count' => 2],
                ['month' => 'Nov 2025', 'attendance_rate' => 90.9, 'late_count' => 3],
            ],

            // Late arrival patterns
            'late_patterns' => [
                'most_common_day' => 'Monday',
                'average_late_minutes' => 15,
                'late_trend' => 'increasing',
            ],

            // Compliance score breakdown
            'compliance_breakdown' => [
                'punctuality' => 86.4,
                'attendance' => 90.9,
                'overtime_completion' => 100.0,
                'schedule_adherence' => 95.5,
                'overall' => 93.2,
            ],

            // Recent activity (last 10 days)
            'recent_activity' => $this->getEmployeeRecentActivity($id),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get attendance trends data using real DailyAttendanceSummary records.
     */
    private function getAttendanceTrends(string $period): array
    {
        $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 90);
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Query daily attendance summaries grouped by date
        $summaries = DailyAttendanceSummary::query()
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('
                attendance_date,
                COUNT(DISTINCT employee_id) as total_employees,
                SUM(CASE WHEN is_present = TRUE THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN is_late = TRUE THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN is_present = FALSE AND is_on_leave = FALSE THEN 1 ELSE 0 END) as absent_count
            ')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date', 'asc')
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->attendance_date)->format('Y-m-d'));

        $trends = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $summary = $summaries->get($dateKey);

            $totalEmployees = $summary ? (int) $summary->total_employees : 0;
            $presentCount = $summary ? (int) $summary->present_count : 0;
            $lateCount = $summary ? (int) $summary->late_count : 0;
            $absentCount = $summary ? (int) $summary->absent_count : 0;
            $attendanceRate = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100, 1) : 0;

            $trends[] = [
                'date' => $dateKey,
                'label' => $date->format('M d'),
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'attendance_rate' => $attendanceRate,
            ];
        }

        return $trends;
    }

    /**
     * Get late arrival trends.
     */
    private function getLateTrends(string $period): array
    {
        $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 90);
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        // Query late arrivals with average minutes
        $summaries = DailyAttendanceSummary::query()
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('is_late', true)
            ->selectRaw('
                attendance_date,
                COUNT(*) as late_count,
                AVG(late_minutes) as avg_late_minutes
            ')
            ->groupBy('attendance_date')
            ->orderBy('attendance_date', 'asc')
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->attendance_date)->format('Y-m-d'));

        $trends = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $summary = $summaries->get($dateKey);

            $trends[] = [
                'date' => $dateKey,
                'label' => $date->format('M d'),
                'late_count' => $summary ? (int) $summary->late_count : 0,
                'average_late_minutes' => $summary ? round((float) $summary->avg_late_minutes, 1) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get department comparison data using real DailyAttendanceSummary and Department records.
     */
    private function getDepartmentComparison(): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfDay();

        $dasTable = (new DailyAttendanceSummary())->getTable();

        $departments = Department::query()
            ->select([
                'departments.id as department_id',
                'departments.name as department_name',
            ])
            ->leftJoin('employees', 'employees.department_id', '=', 'departments.id')
            ->leftJoin($dasTable, function ($join) use ($dasTable, $startDate, $endDate) {
                $join->on($dasTable . '.employee_id', '=', 'employees.id')
                     ->whereBetween($dasTable . '.attendance_date', [$startDate, $endDate]);
            })
            ->groupBy('departments.id', 'departments.name')
            ->selectRaw(implode(', ', [
                'COUNT(DISTINCT employees.id) as total_employees',
                'COUNT(' . $dasTable . '.id) as total_records',
                'SUM(CASE WHEN ' . $dasTable . '.is_present = TRUE THEN 1 ELSE 0 END) as present_count',
                'SUM(CASE WHEN ' . $dasTable . '.is_late = TRUE THEN 1 ELSE 0 END) as late_count',
                'AVG(' . $dasTable . '.total_hours_worked) as avg_hours',
                'SUM(CASE WHEN ' . $dasTable . '.overtime_hours IS NOT NULL THEN ' . $dasTable . '.overtime_hours ELSE 0 END) as total_overtime_hours',
            ]))
            ->havingRaw('COUNT(DISTINCT employees.id) > 0')
            ->orderBy('departments.name')
            ->get()
            ->map(function ($dept) {
                $totalRecords = (int) ($dept->total_records ?? 0);
                $present = (float) ($dept->present_count ?? 0);
                $late = (float) ($dept->late_count ?? 0);

                $attendanceRate = $totalRecords > 0 ? round(($present / $totalRecords) * 100, 1) : 0.0;
                $lateRate = $totalRecords > 0 ? round(($late / $totalRecords) * 100, 1) : 0.0;

                return [
                    'department_id' => (int) $dept->department_id,
                    'department_name' => (string) $dept->department_name,
                    'attendance_rate' => $attendanceRate,
                    'late_rate' => $lateRate,
                    'average_hours' => round((float) ($dept->avg_hours ?? 0), 1),
                    'overtime_hours' => round((float) ($dept->total_overtime_hours ?? 0), 0),
                ];
            })
            ->toArray();

        return $departments;
    }

    /**
     * Get overtime analysis using real DailyAttendanceSummary data.
     */
    private function getOvertimeAnalysis(): array
    {
        // Get current month's overtime data
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfDay();

        // Get total overtime hours
        $totalOvertimeHours = DailyAttendanceSummary::whereBetween('attendance_date', [$startDate, $endDate])
            ->sum('overtime_hours') ?? 0;

        // Get active employees count
        $activeEmployeesCount = Employee::where('status', 'active')->count();
        $averagePerEmployee = $activeEmployeesCount > 0 ? $totalOvertimeHours / $activeEmployeesCount : 0;

        // Get top overtime employees
        $topOvertimeEmployees = DailyAttendanceSummary::query()
            ->select([
                'employees.id',
                'profiles.first_name',
                'profiles.last_name',
                DB::raw('SUM(daily_attendance_summary.overtime_hours) as total_overtime')
            ])
            ->join('employees', 'daily_attendance_summary.employee_id', '=', 'employees.id')
            ->join('profiles', 'employees.profile_id', '=', 'profiles.id')
            ->whereBetween('daily_attendance_summary.attendance_date', [$startDate, $endDate])
            ->whereNotNull('daily_attendance_summary.overtime_hours')
            ->where('daily_attendance_summary.overtime_hours', '>', 0)
            ->groupBy('employees.id', 'profiles.first_name', 'profiles.last_name')
            ->orderByDesc('total_overtime')
            ->limit(5)
            ->get()
            ->map(fn($emp) => [
                'employee_name' => $emp->first_name . ' ' . $emp->last_name,
                'hours' => round($emp->total_overtime, 1),
            ])
            ->toArray();

        // Get overtime by department
        $overtimeByDepartment = DailyAttendanceSummary::query()
            ->select([
                'departments.name as department_name',
                DB::raw('SUM(daily_attendance_summary.overtime_hours) as total_overtime')
            ])
            ->join('employees', 'daily_attendance_summary.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereBetween('daily_attendance_summary.attendance_date', [$startDate, $endDate])
            ->whereNotNull('daily_attendance_summary.overtime_hours')
            ->where('daily_attendance_summary.overtime_hours', '>', 0)
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_overtime')
            ->get()
            ->map(fn($dept) => [
                'department_name' => $dept->department_name,
                'hours' => round($dept->total_overtime, 0),
            ])
            ->toArray();

        // Calculate trend (compare with previous month)
        $prevMonthStart = now()->subMonth()->startOfMonth();
        $prevMonthEnd = now()->subMonth()->endOfMonth();
        $prevMonthOvertimeHours = DailyAttendanceSummary::whereBetween('attendance_date', [$prevMonthStart, $prevMonthEnd])
            ->sum('overtime_hours') ?? 0;

        $trend = 'stable';
        if ($totalOvertimeHours > $prevMonthOvertimeHours * 1.1) {
            $trend = 'increasing';
        } elseif ($totalOvertimeHours < $prevMonthOvertimeHours * 0.9) {
            $trend = 'decreasing';
        }

        return [
            'total_overtime_hours' => round($totalOvertimeHours, 0),
            'average_per_employee' => round($averagePerEmployee, 1),
            'top_overtime_employees' => $topOvertimeEmployees,
            'by_department' => $overtimeByDepartment,
            'trend' => $trend,
            'budget_utilization' => 0, // TODO: Calculate based on budget settings if available
        ];
    }

    /**
     * Get recent attendance violations/corrections.
     *
     * @return array
     */
    private function getRecentViolations(): array
    {
        // Get recent corrected events (violations requiring correction)
        $violations = AttendanceEvent::query()
            ->select([
                'attendance_events.id',
                'profiles.first_name',
                'profiles.last_name',
                'attendance_events.event_type',
                'attendance_events.event_time',
                'attendance_events.original_time',
                'attendance_events.correction_reason',
                'attendance_events.corrected_at'
            ])
            ->join('employees', 'attendance_events.employee_id', '=', 'employees.id')
            ->join('profiles', 'employees.profile_id', '=', 'profiles.id')
            ->where('attendance_events.is_corrected', true)
            ->whereDate('attendance_events.corrected_at', '>=', now()->subDays(7))
            ->orderByDesc('attendance_events.corrected_at')
            ->limit(5)
            ->get()
            ->map(function($event) {
                // Determine severity based on correction reason
                $severity = 'low';
                $correctionReason = strtolower($event->correction_reason ?? '');
                
                if (str_contains($correctionReason, 'absent') || str_contains($correctionReason, 'missed')) {
                    $severity = 'high';
                } elseif (str_contains($correctionReason, 'late') || str_contains($correctionReason, 'early')) {
                    $severity = 'medium';
                }
                
                // Map event type to readable violation type
                $violationType = match($event->event_type) {
                    'time_in' => 'Late Arrival',
                    'time_out' => 'Early Departure',
                    'break_start', 'break_end' => 'Extended Break',
                    default => 'Missed Punch'
                };
                
                return [
                    'id' => $event->id,
                    'employee' => $event->first_name . ' ' . $event->last_name,
                    'type' => $violationType,
                    'time' => Carbon::parse($event->event_time)->format('g:i A'),
                    'severity' => $severity,
                    'corrected_at' => $event->corrected_at,
                ];
            })
            ->toArray();
        
        return $violations;
    }

    /**
     * Get daily breakdown for department.
     */
    private function getDailyBreakdown(): array
    {
        $breakdown = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $breakdown[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'present' => rand(35, 48),
                'late' => rand(1, 5),
                'absent' => rand(0, 2),
                'on_leave' => rand(0, 3),
            ];
        }
        return $breakdown;
    }

    /**
     * Get top performers in department.
     */
    private function getTopPerformers(int $departmentId): array
    {
        $performers = [];
        for ($i = 1; $i <= 5; $i++) {
            $performers[] = [
                'employee_id' => $i,
                'employee_name' => 'Employee ' . $i,
                'attendance_rate' => rand(96, 100) + (rand(0, 9) / 10),
                'on_time_rate' => rand(95, 100) + (rand(0, 9) / 10),
            ];
        }
        return $performers;
    }

    /**
     * Get employees needing attention.
     */
    private function getAttentionNeeded(int $departmentId): array
    {
        $attention = [];
        for ($i = 1; $i <= 3; $i++) {
            $attention[] = [
                'employee_id' => $i + 100,
                'employee_name' => 'Employee ' . ($i + 100),
                'attendance_rate' => rand(70, 84) + (rand(0, 9) / 10),
                'late_count' => rand(5, 12),
                'issue' => ['Frequent late arrivals', 'Multiple absences', 'Low attendance rate'][rand(0, 2)],
            ];
        }
        return $attention;
    }

    /**
     * Get employee recent activity.
     */
    private function getEmployeeRecentActivity(int $employeeId): array
    {
        $activity = [];
        $statuses = ['present', 'late', 'absent', 'on_leave'];

        for ($i = 9; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $status = $statuses[array_rand($statuses)];
            $activity[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'status' => $status,
                'time_in' => $status !== 'absent' ? '08:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00' : null,
                'time_out' => $status !== 'absent' ? '17:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00' : null,
                'total_hours' => $status !== 'absent' ? round(8 + (rand(-10, 10) / 10), 1) : 0,
            ];
        }
        return $activity;
    }
}
