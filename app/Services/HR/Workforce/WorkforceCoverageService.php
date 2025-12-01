<?php

namespace App\Services\HR\Workforce;

use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkforceCoverageService
{
    protected ShiftAssignmentService $shiftService;

    public function __construct(ShiftAssignmentService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Analyze coverage for a date range
     */
    public function analyzeCoverage(
        Carbon $fromDate,
        Carbon $toDate,
        ?int $departmentId = null
    ): array {
        $dailyCoverage = [];
        $currentDate = $fromDate->copy();
        $totalDays = $fromDate->diffInDays($toDate) + 1;

        while ($currentDate <= $toDate) {
            $dailyCoverage[] = $this->getCoverageForDate($currentDate, $departmentId);
            $currentDate->addDay();
        }

        $avgStaff = count($dailyCoverage) > 0
            ? array_sum(array_column($dailyCoverage, 'assigned_staff')) / count($dailyCoverage)
            : 0;

        return [
            'date_range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'total_days' => $totalDays,
            ],
            'summary' => [
                'total_assignments' => array_sum(array_column($dailyCoverage, 'assigned_staff')),
                'average_daily_staff' => round($avgStaff, 2),
                'average_coverage_level' => $this->calculateCoverageLevel(round($avgStaff, 2)),
                'days_above_target' => count(array_filter($dailyCoverage, fn($d) => $d['coverage_status'] === 'optimal')),
                'days_below_target' => count(array_filter($dailyCoverage, fn($d) => $d['coverage_status'] === 'critical')),
            ],
            'daily_breakdown' => $dailyCoverage,
        ];
    }

    /**
     * Get coverage data for a specific date
     */
    public function getCoverageForDate(Carbon $date, ?int $departmentId = null): array
    {
        $query = ShiftAssignment::whereDate('date', $date)
            ->where('status', 'scheduled');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $assignments = $query->get();
        $assignedStaff = $assignments->count();
        $overtimeStaff = $assignments->where('is_overtime', true)->count();
        $conflictedStaff = $assignments->where('has_conflict', true)->count();

        // Determine coverage status based on typical 10-person requirement
        $targetStaff = 10;
        $coveragePercentage = ($assignedStaff / $targetStaff) * 100;
        $status = $this->determineCoverageStatus($coveragePercentage);

        return [
            'date' => $date->toDateString(),
            'day_name' => $date->format('l'),
            'assigned_staff' => $assignedStaff,
            'target_staff' => $targetStaff,
            'coverage_percentage' => round($coveragePercentage, 1),
            'coverage_status' => $status,
            'overtime_staff' => $overtimeStaff,
            'conflicted_staff' => $conflictedStaff,
            'by_shift_type' => $this->getShiftTypeCoverage($assignments),
        ];
    }

    /**
     * Get coverage by department for a specific date
     */
    public function getCoverageByDepartment(Carbon $date): array
    {
        $departments = Department::all();
        $coverage = [];

        foreach ($departments as $dept) {
            $assignments = ShiftAssignment::whereDate('date', $date)
                ->where('department_id', $dept->id)
                ->where('status', 'scheduled')
                ->get();

            $staffCount = $assignments->count();
            $targetStaff = 5; // Default target per department

            $coverage[] = [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'assigned_staff' => $staffCount,
                'target_staff' => $targetStaff,
                'coverage_percentage' => round(($staffCount / $targetStaff) * 100, 1),
                'status' => $this->determineCoverageStatus(($staffCount / $targetStaff) * 100),
                'shift_types' => $this->getShiftTypeCoverage($assignments),
            ];
        }

        return [
            'date' => $date->toDateString(),
            'by_department' => $coverage,
            'total_staff' => array_sum(array_column($coverage, 'assigned_staff')),
        ];
    }

    /**
     * Get coverage by shift type for a date
     */
    public function getCoverageByShiftType(Carbon $date, ?int $departmentId = null): array
    {
        $shiftTypes = ['morning', 'afternoon', 'night', 'split', 'custom'];
        $coverage = [];

        foreach ($shiftTypes as $type) {
            $query = ShiftAssignment::whereDate('date', $date)
                ->where('shift_type', $type)
                ->where('status', 'scheduled');

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            $assignments = $query->get();
            $staffCount = $assignments->count();
            $targetStaff = 3; // Default target per shift type

            $coverage[$type] = [
                'assigned_staff' => $staffCount,
                'target_staff' => $targetStaff,
                'coverage_percentage' => round(($staffCount / $targetStaff) * 100, 1),
                'status' => $this->determineCoverageStatus(($staffCount / $targetStaff) * 100),
            ];
        }

        return [
            'date' => $date->toDateString(),
            'by_shift_type' => $coverage,
        ];
    }

    /**
     * Identify coverage gaps for a date range
     */
    public function identifyCoverageGaps(
        Carbon $fromDate,
        Carbon $toDate,
        array $requirements = []
    ): array {
        $gaps = [];
        $currentDate = $fromDate->copy();
        $minStaff = $requirements['min_staff'] ?? 10;

        while ($currentDate <= $toDate) {
            $assigned = ShiftAssignment::whereDate('date', $currentDate)
                ->where('status', 'scheduled')
                ->count();

            if ($assigned < $minStaff) {
                $gaps[] = [
                    'date' => $currentDate->toDateString(),
                    'day_name' => $currentDate->format('l'),
                    'required' => $minStaff,
                    'assigned' => $assigned,
                    'gap' => $minStaff - $assigned,
                    'gap_percentage' => round((($minStaff - $assigned) / $minStaff) * 100, 1),
                ];
            }

            $currentDate->addDay();
        }

        return [
            'date_range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'minimum_required' => $minStaff,
            'gap_count' => count($gaps),
            'total_gap_days' => $fromDate->diffInDays($toDate) + 1 - count(range(0, $fromDate->diffInDays($toDate))),
            'gaps' => $gaps,
        ];
    }

    /**
     * Suggest optimal staffing for a date
     */
    public function suggestOptimalStaffing(Carbon $date, int $departmentId): array
    {
        $department = Department::find($departmentId);
        $currentAssignments = ShiftAssignment::whereDate('date', $date)
            ->where('department_id', $departmentId)
            ->where('status', 'scheduled')
            ->get();

        $availableEmployees = Employee::where('department_id', $departmentId)
            ->whereNotIn('id', $currentAssignments->pluck('employee_id'))
            ->get();

        return [
            'date' => $date->toDateString(),
            'department' => $department?->name,
            'current_assignments' => $currentAssignments->count(),
            'available_employees' => $availableEmployees->count(),
            'recommendations' => [
                'add_staff' => max(0, 5 - $currentAssignments->count()), // Target 5 per shift
                'consider_overtime' => $currentAssignments->count() >= 8,
                'available_employees' => $availableEmployees->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'position' => $emp->position,
                    ];
                })->values()->all(),
            ],
        ];
    }

    /**
     * Calculate staffing efficiency
     */
    public function calculateStaffingEfficiency(Carbon $fromDate, Carbon $toDate): float
    {
        $totalDays = $fromDate->diffInDays($toDate) + 1;
        $totalCapacityNeeded = $totalDays * 10; // Assuming 10 staff per day
        $totalAssigned = ShiftAssignment::whereBetween('date', [$fromDate, $toDate])
            ->where('status', 'scheduled')
            ->count();

        return round(($totalAssigned / $totalCapacityNeeded) * 100, 2);
    }

    /**
     * Get overtime trends for a date range
     */
    public function getOvertimeTrends(Carbon $fromDate, Carbon $toDate): array
    {
        $dailyTrends = [];
        $currentDate = $fromDate->copy();

        while ($currentDate <= $toDate) {
            $overtimeAssignments = ShiftAssignment::whereDate('date', $currentDate)
                ->where('is_overtime', true)
                ->get();

            $dailyTrends[] = [
                'date' => $currentDate->toDateString(),
                'day_name' => $currentDate->format('l'),
                'overtime_staff' => $overtimeAssignments->count(),
                'total_overtime_hours' => $overtimeAssignments->sum('overtime_hours'),
                'average_overtime_per_staff' => $overtimeAssignments->count() > 0
                    ? round($overtimeAssignments->sum('overtime_hours') / $overtimeAssignments->count(), 2)
                    : 0,
            ];

            $currentDate->addDay();
        }

        $totalOvertimeStaff = array_sum(array_column($dailyTrends, 'overtime_staff'));
        $totalOvertimeHours = array_sum(array_column($dailyTrends, 'total_overtime_hours'));

        return [
            'date_range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'summary' => [
                'total_overtime_assignments' => $totalOvertimeStaff,
                'total_overtime_hours' => round($totalOvertimeHours, 2),
                'average_daily_overtime_staff' => round($totalOvertimeStaff / ($fromDate->diffInDays($toDate) + 1), 2),
                'trend' => $this->analyzeTrend($dailyTrends),
            ],
            'daily_breakdown' => $dailyTrends,
        ];
    }

    /**
     * Generate comprehensive coverage report
     */
    public function generateCoverageReport(
        Carbon $fromDate,
        Carbon $toDate,
        ?int $departmentId = null
    ): array {
        $analysis = $this->analyzeCoverage($fromDate, $toDate, $departmentId);
        $byDept = $this->getCoverageByDepartment($fromDate);
        $overtimeTrends = $this->getOvertimeTrends($fromDate, $toDate);
        $gaps = $this->identifyCoverageGaps($fromDate, $toDate);

        return [
            'report_title' => 'Workforce Coverage Report',
            'generated_at' => now()->toDateTimeString(),
            'date_range' => $analysis['date_range'],
            'coverage_analysis' => $analysis['summary'],
            'by_department' => $byDept,
            'overtime_trends' => $overtimeTrends['summary'],
            'coverage_gaps' => $gaps,
            'recommendations' => $this->generateRecommendations($analysis, $overtimeTrends, $gaps),
        ];
    }

    /**
     * Export coverage data (placeholder for CSV/Excel)
     */
    public function exportCoverageData(Carbon $fromDate, Carbon $toDate, string $format = 'csv'): string
    {
        $report = $this->generateCoverageReport($fromDate, $toDate);

        if ($format === 'csv') {
            return $this->generateCsvExport($report);
        }

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Helper: Determine coverage status
     */
    private function determineCoverageStatus(float $coveragePercentage): string
    {
        if ($coveragePercentage >= 100) {
            return 'optimal';
        } elseif ($coveragePercentage >= 90) {
            return 'adequate';
        } elseif ($coveragePercentage >= 70) {
            return 'low';
        } else {
            return 'critical';
        }
    }

    /**
     * Helper: Calculate coverage level label
     */
    private function calculateCoverageLevel(float $avgStaff): string
    {
        if ($avgStaff >= 10) {
            return 'Optimal';
        } elseif ($avgStaff >= 9) {
            return 'Adequate';
        } elseif ($avgStaff >= 7) {
            return 'Low';
        } else {
            return 'Critical';
        }
    }

    /**
     * Helper: Get shift type breakdown for assignments
     */
    private function getShiftTypeCoverage(Collection $assignments): array
    {
        $types = ['morning', 'afternoon', 'night', 'split', 'custom'];
        $result = [];

        foreach ($types as $type) {
            $count = $assignments->where('shift_type', $type)->count();
            if ($count > 0) {
                $result[$type] = $count;
            }
        }

        return $result;
    }

    /**
     * Helper: Analyze overtime trend (increasing/decreasing/stable)
     */
    private function analyzeTrend(array $dailyTrends): string
    {
        if (count($dailyTrends) < 2) {
            return 'insufficient_data';
        }

        $first = $dailyTrends[0]['total_overtime_hours'];
        $last = $dailyTrends[count($dailyTrends) - 1]['total_overtime_hours'];

        if ($last > $first * 1.1) {
            return 'increasing';
        } elseif ($last < $first * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Helper: Generate CSV export
     */
    private function generateCsvExport(array $report): string
    {
        $csv = "Workforce Coverage Report\n";
        $csv .= "Generated: " . $report['generated_at'] . "\n";
        $csv .= "Period: " . $report['date_range']['from'] . " to " . $report['date_range']['to'] . "\n\n";

        $csv .= "COVERAGE SUMMARY\n";
        $csv .= "Total Assignments," . $report['coverage_analysis']['total_assignments'] . "\n";
        $csv .= "Average Daily Staff," . $report['coverage_analysis']['average_daily_staff'] . "\n";
        $csv .= "Coverage Level," . $report['coverage_analysis']['average_coverage_level'] . "\n\n";

        $csv .= "OVERTIME SUMMARY\n";
        $csv .= "Total OT Assignments," . $report['overtime_trends']['total_overtime_assignments'] . "\n";
        $csv .= "Total OT Hours," . $report['overtime_trends']['total_overtime_hours'] . "\n";
        $csv .= "Trend," . $report['overtime_trends']['trend'] . "\n\n";

        $csv .= "COVERAGE GAPS\n";
        $csv .= "Gap Count," . $report['coverage_gaps']['gap_count'] . "\n";
        $csv .= "Minimum Required," . $report['coverage_gaps']['minimum_required'] . "\n";

        return $csv;
    }

    /**
     * Helper: Generate recommendations based on report
     */
    private function generateRecommendations(array $analysis, array $overtime, array $gaps): array
    {
        $recommendations = [];

        if ($gaps['gap_count'] > 0) {
            $recommendations[] = [
                'type' => 'staffing',
                'priority' => 'high',
                'message' => "Address {$gaps['gap_count']} days with staffing gaps",
            ];
        }

        if ($overtime['summary']['total_overtime_assignments'] > 5) {
            $recommendations[] = [
                'type' => 'overtime',
                'priority' => 'medium',
                'message' => "High overtime usage ({$overtime['summary']['total_overtime_assignments']} assignments)",
            ];
        }

        if ($analysis['summary']['average_daily_staff'] < 8) {
            $recommendations[] = [
                'type' => 'recruitment',
                'priority' => 'high',
                'message' => 'Consider hiring additional staff to meet coverage targets',
            ];
        }

        return $recommendations;
    }
}
