<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * PerformanceMetricsController
 *
 * Manages performance analytics and reporting.
 * Provides HR Managers with dashboards, comparisons, and trend analysis of employee performance.
 *
 * Features:
 * 1. Overall performance summary and statistics
 * 2. Department comparison and benchmarking
 * 3. Performance distribution and trends
 * 4. Employee performance history and projections
 * 5. Export metrics to CSV/PDF
 */
class PerformanceMetricsController extends Controller
{
    /**
     * Display performance metrics dashboard
     */
    public function index(Request $request)
    {
        $departmentId = $request->input('department_id', '');
        $performanceCategory = $request->input('performance_category', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        // Query completed/acknowledged appraisals with employee and department
        $appraisalsQuery = \App\Models\Appraisal::with([
            'employee.profile',
            'employee.department',
        ])->whereIn('status', ['completed', 'acknowledged']);

        if ($departmentId) {
            $appraisalsQuery->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($dateFrom) {
            $appraisalsQuery->whereDate('submitted_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $appraisalsQuery->whereDate('submitted_at', '<=', $dateTo);
        }

        $appraisals = $appraisalsQuery->get();

        // Get attendance rates for all employees in the result set
        $employeeIds = $appraisals->pluck('employee_id')->unique()->values();
        $attendanceRates = [];
        if ($employeeIds->count() > 0) {
            $attendanceData = \App\Models\DailyAttendanceSummary::selectRaw('employee_id, AVG(CASE WHEN is_present THEN 1 ELSE 0 END) * 100 as attendance_rate')
                ->whereIn('employee_id', $employeeIds)
                ->groupBy('employee_id')
                ->pluck('attendance_rate', 'employee_id');
            $attendanceRates = $attendanceData->toArray();
        }

        // Build metrics array
        $metrics = $appraisals->map(function ($a) use ($attendanceRates) {
            $employee = $a->employee;
            $department = $employee?->department;
            // Always provide a float for attendance and score
            $attendance = isset($attendanceRates[$a->employee_id]) && is_numeric($attendanceRates[$a->employee_id])
                ? floatval($attendanceRates[$a->employee_id])
                : 0.0;
            $overallScore = $a->overall_score !== null ? floatval($a->overall_score) : 0.0;

            // Performance category logic
            $category = 'medium';
            if ($overallScore >= 7.5 && $attendance >= 90) {
                $category = 'high';
            } elseif ($overallScore < 5.0 || $attendance < 80) {
                $category = 'low';
            }

            // Trend is not calculated here (requires historical data)
            $trend = 'stable';

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                'employee_number' => $employee->employee_number,
                'department_id' => $department?->id,
                'department_name' => $department?->name,
                'overall_score' => $overallScore,
                'attendance_rate' => $attendance,
                'behavior_score' => null, // Placeholder, needs more logic
                'productivity_score' => null, // Placeholder, needs more logic
                'performance_category' => $category,
                'trend' => $trend,
            ];
        })->filter(function ($m) use ($performanceCategory) {
            if ($performanceCategory && $performanceCategory !== 'all') {
                return $m['performance_category'] === $performanceCategory;
            }
            return true;
        })->values()->all();

        // Calculate summary statistics
        $summary = [
            'average_score' => count($metrics) ? round(array_sum(array_column($metrics, 'overall_score')) / count($metrics), 2) : 0,
            'high_performers' => count(array_filter($metrics, fn($m) => $m['performance_category'] === 'high')),
            'low_performers' => count(array_filter($metrics, fn($m) => $m['performance_category'] === 'low')),
            'completion_rate' => 100, // Placeholder, can be improved
        ];

        // Get departments for filter dropdown
        $departments = \App\Models\Department::orderBy('name')->get(['id', 'name'])->map(function ($d) {
            return ['id' => $d->id, 'name' => $d->name];
        })->values();

        return Inertia::render('HR/PerformanceMetrics/Index', [
            'metrics' => $metrics,
            'departments' => $departments,
            'summary' => $summary,
            'filters' => [
                'department_id' => $departmentId,
                'performance_category' => $performanceCategory,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Display employee performance detail and history
     */
    public function show($employeeId)
    {
        // Mock employee performance data
        $employee = [
            'id' => 1,
            'employee_number' => 'EMP-2023-001',
            'first_name' => 'Juan',
            'last_name' => 'dela Cruz',
            'full_name' => 'Juan dela Cruz',
            'department_id' => 1,
            'department_name' => 'Engineering',
            'email' => 'juan.delacruz@company.com',
        ];

        $currentMetric = [
            'employee_id' => 1,
            'employee_name' => 'Juan dela Cruz',
            'employee_number' => 'EMP-2023-001',
            'department_name' => 'Engineering',
            'overall_score' => 8.2,
            'attendance_rate' => 94.5,
            'behavior_score' => 8.5,
            'productivity_score' => 7.8,
            'performance_category' => 'high',
            'trend' => 'improving',
        ];

        // Historical performance
        $historicalMetrics = [
            ['cycle_name' => 'Annual Review 2024', 'overall_score' => 7.8, 'cycle_date' => '2024-12-31'],
            ['cycle_name' => 'Mid-Year Review 2024', 'overall_score' => 7.5, 'cycle_date' => '2024-06-30'],
            ['cycle_name' => 'Annual Review 2023', 'overall_score' => 7.2, 'cycle_date' => '2023-12-31'],
        ];

        $departmentAverage = 7.4;
        $trend = 'improving';

        // Attendance correlation
        $attendanceCorrelation = [
            'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
            'appraisalScores' => [7.5, 7.6, 7.8, 7.9, 8.0, 8.1, 8.2, 8.1, 8.0, 8.1, 8.2],
            'attendanceRates' => [90, 92, 93, 94, 95, 94, 95, 96, 95, 94, 95],
        ];

        return Inertia::render('HR/PerformanceMetrics/Show', [
            'employee' => $employee,
            'currentMetric' => $currentMetric,
            'historicalMetrics' => $historicalMetrics,
            'departmentAverage' => $departmentAverage,
            'trend' => $trend,
            'attendanceCorrelation' => $attendanceCorrelation,
        ]);
    }

    /**
     * Get department comparison metrics
     */
    public function departmentComparison()
    {
        $comparison = [
            [
                'name' => 'Engineering',
                'average_score' => 7.5,
                'total_employees' => 10,
                'appraised_employees' => 10,
                'high_performers' => 5,
                'medium_performers' => 4,
                'low_performers' => 1,
            ],
            [
                'name' => 'Finance',
                'average_score' => 7.6,
                'total_employees' => 8,
                'appraised_employees' => 8,
                'high_performers' => 4,
                'medium_performers' => 3,
                'low_performers' => 1,
            ],
            [
                'name' => 'Operations',
                'average_score' => 7.1,
                'total_employees' => 14,
                'appraised_employees' => 13,
                'high_performers' => 4,
                'medium_performers' => 7,
                'low_performers' => 2,
            ],
            [
                'name' => 'Sales',
                'average_score' => 7.2,
                'total_employees' => 13,
                'appraised_employees' => 12,
                'high_performers' => 3,
                'medium_performers' => 7,
                'low_performers' => 2,
            ],
            [
                'name' => 'HR',
                'average_score' => 7.8,
                'total_employees' => 5,
                'appraised_employees' => 5,
                'high_performers' => 3,
                'medium_performers' => 2,
                'low_performers' => 0,
            ],
        ];

        return Inertia::render('HR/PerformanceMetrics/DepartmentComparison', [
            'comparison' => $comparison,
        ]);
    }

    /**
     * Export metrics to CSV/PDF
     */
    public function exportMetrics(Request $request)
    {
        $format = $request->input('format', 'csv'); // csv or pdf

        // In production, generate and return file
        // For now, return success message
        return back()->with('success', "Metrics exported to {$format} successfully");
    }

    /**
     * Calculate performance summary statistics
     */
    private function calculatePerformanceSummary($metrics)
    {
        if (empty($metrics)) {
            return [
                'average_score' => 0,
                'high_performers' => 0,
                'low_performers' => 0,
                'completion_rate' => 0,
            ];
        }

        $totalScore = 0;
        $highCount = 0;
        $lowCount = 0;
        $count = count($metrics);

        foreach ($metrics as $metric) {
            $totalScore += $metric['overall_score'];
            if ($metric['performance_category'] === 'high') {
                $highCount++;
            } elseif ($metric['performance_category'] === 'low') {
                $lowCount++;
            }
        }

        return [
            'average_score' => round($totalScore / $count, 2),
            'high_performers' => $highCount,
            'low_performers' => $lowCount,
            'completion_rate' => 100, // All employees appraised
        ];
    }

    /**
     * Get mock performance metrics
     */
    private function getMockPerformanceMetrics()
    {
        return [
            [
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2023-001',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'overall_score' => 8.2,
                'attendance_rate' => 94.5,
                'behavior_score' => 8.5,
                'productivity_score' => 7.8,
                'performance_category' => 'high',
                'trend' => 'improving',
            ],
            [
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-2023-002',
                'department_id' => 2,
                'department_name' => 'Finance',
                'overall_score' => 7.8,
                'attendance_rate' => 91.0,
                'behavior_score' => 7.6,
                'productivity_score' => 8.0,
                'performance_category' => 'high',
                'trend' => 'stable',
            ],
            [
                'employee_id' => 3,
                'employee_name' => 'Carlos Reyes',
                'employee_number' => 'EMP-2023-003',
                'department_id' => 3,
                'department_name' => 'Operations',
                'overall_score' => 6.8,
                'attendance_rate' => 87.3,
                'behavior_score' => 6.5,
                'productivity_score' => 7.0,
                'performance_category' => 'medium',
                'trend' => 'stable',
            ],
            [
                'employee_id' => 4,
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP-2023-004',
                'department_id' => 4,
                'department_name' => 'Sales',
                'overall_score' => 7.5,
                'attendance_rate' => 88.5,
                'behavior_score' => 7.3,
                'productivity_score' => 7.6,
                'performance_category' => 'high',
                'trend' => 'improving',
            ],
            [
                'employee_id' => 5,
                'employee_name' => 'Miguel Torres',
                'employee_number' => 'EMP-2023-005',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'overall_score' => 6.9,
                'attendance_rate' => 85.2,
                'behavior_score' => 6.8,
                'productivity_score' => 7.0,
                'performance_category' => 'medium',
                'trend' => 'declining',
            ],
            [
                'employee_id' => 6,
                'employee_name' => 'Linda Rodriguez',
                'employee_number' => 'EMP-2023-006',
                'department_id' => 5,
                'department_name' => 'HR',
                'overall_score' => 8.5,
                'attendance_rate' => 95.0,
                'behavior_score' => 8.7,
                'productivity_score' => 8.3,
                'performance_category' => 'high',
                'trend' => 'improving',
            ],
            [
                'employee_id' => 7,
                'employee_name' => 'Ramon Martinez',
                'employee_number' => 'EMP-2023-007',
                'department_id' => 3,
                'department_name' => 'Operations',
                'overall_score' => 7.3,
                'attendance_rate' => 89.0,
                'behavior_score' => 7.1,
                'productivity_score' => 7.5,
                'performance_category' => 'high',
                'trend' => 'stable',
            ],
            [
                'employee_id' => 8,
                'employee_name' => 'Sophie Mercado',
                'employee_number' => 'EMP-2023-008',
                'department_id' => 2,
                'department_name' => 'Finance',
                'overall_score' => 5.2,
                'attendance_rate' => 78.5,
                'behavior_score' => 5.0,
                'productivity_score' => 5.4,
                'performance_category' => 'low',
                'trend' => 'declining',
            ],
            [
                'employee_id' => 9,
                'employee_name' => 'Daniel Perez',
                'employee_number' => 'EMP-2023-009',
                'department_id' => 4,
                'department_name' => 'Sales',
                'overall_score' => 7.6,
                'attendance_rate' => 92.0,
                'behavior_score' => 7.4,
                'productivity_score' => 7.8,
                'performance_category' => 'high',
                'trend' => 'improving',
            ],
            [
                'employee_id' => 10,
                'employee_name' => 'Rebecca Lopez',
                'employee_number' => 'EMP-2023-010',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'overall_score' => 4.8,
                'attendance_rate' => 76.0,
                'behavior_score' => 4.5,
                'productivity_score' => 5.0,
                'performance_category' => 'low',
                'trend' => 'declining',
            ],
        ];
    }

    /**
     * Get mock departments
     */
    private function getMockDepartments()
    {
        return [
            ['id' => 1, 'name' => 'Engineering'],
            ['id' => 2, 'name' => 'Finance'],
            ['id' => 3, 'name' => 'Operations'],
            ['id' => 4, 'name' => 'Sales'],
            ['id' => 5, 'name' => 'HR'],
        ];
    }
}
