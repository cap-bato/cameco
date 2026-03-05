<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\ClearanceItem;
use App\Models\ExitInterview;
use App\Models\CompanyAsset;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Generate monthly separation report.
     * 
     * Report includes:
     *  - List of separated employees with details
     *  - Separation type breakdown
     *  - Department distribution
     *  - Trend comparisons
     * 
     * @param Request $request
     * @param string $format (pdf|csv)
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function monthlySeparationReport(Request $request, $format = 'pdf')
    {
        $this->authorize('viewOffboardingReports', OffboardingCase::class);

        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $month = $request->month ? Carbon::parse($request->month) : now();
        $departmentId = $request->department_id;

        // Query separated employees for the month
        $cases = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'employee.position',
        ])
            ->whereYear('actual_separation_date', $month->year)
            ->whereMonth('actual_separation_date', $month->month)
            ->when($departmentId, fn($q) => $q->whereHas('employee', fn($eq) => $eq->where('department_id', $departmentId)))
            ->orderBy('actual_separation_date')
            ->get();

        // Calculate statistics
        $stats = [
            'total_separations' => $cases->count(),
            'by_type' => $cases->groupBy('separation_type')->map(fn($group) => $group->count())->toArray(),
            'by_department' => $cases->groupBy('employee.department.name')->map(fn($group) => $group->count())->toArray(),
            'voluntary' => $cases->whereIn('separation_type', ['resignation', 'retirement'])->count(),
            'involuntary' => $cases->whereIn('separation_type', ['termination', 'end_of_contract'])->count(),
        ];

        // Comparison with previous month
        $previousMonth = $month->copy()->subMonth();
        $previousCount = OffboardingCase::whereYear('actual_separation_date', $previousMonth->year)
            ->whereMonth('actual_separation_date', $previousMonth->month)
            ->count();

        $stats['previous_month_count'] = $previousCount;
        $stats['change_percentage'] = $previousCount > 0
            ? round((($stats['total_separations'] - $previousCount) / $previousCount) * 100, 1)
            : 0;

        $data = [
            'cases' => $cases,
            'stats' => $stats,
            'month' => $month,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ];

        if ($format === 'csv') {
            return $this->exportMonthlySeparationCSV($data);
        }

        // Generate PDF
        $pdf = Pdf::loadView('HR.Offboarding.Reports.MonthlySeparation', $data)
            ->setPaper('letter', 'portrait');

        $fileName = 'monthly-separation-report-' . $month->format('Y-m') . '.pdf';

        Log::info('Monthly separation report generated', [
            'month' => $month->format('Y-m'),
            'total_separations' => $stats['total_separations'],
            'generated_by' => auth()->id(),
        ]);

        return $pdf->download($fileName);
    }

    /**
     * Generate clearance compliance report.
     * 
     * Shows pending and overdue clearance items across all active cases.
     * 
     * @param Request $request
     * @param string $format (pdf|csv)
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function clearanceComplianceReport(Request $request, $format = 'pdf')
    {
        $this->authorize('viewOffboardingReports', OffboardingCase::class);

        $request->validate([
            'status' => 'nullable|in:pending,approved,overdue',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $status = $request->status;
        $departmentId = $request->department_id;

        // Query clearance items
        $query = ClearanceItem::with([
            'offboardingCase.employee.profile',
            'offboardingCase.employee.department',
            'assignedTo',
        ])
            ->whereHas('offboardingCase', fn($q) => $q->whereIn('status', ['in_progress', 'clearance_pending']))
            ->when($status, function ($q) use ($status) {
                if ($status === 'overdue') {
                    $q->where('status', 'pending')
                        ->where('due_date', '<', now());
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($departmentId, fn($q) => 
                $q->whereHas('offboardingCase.employee', fn($eq) => $eq->where('department_id', $departmentId))
            );

        $clearanceItems = $query->orderBy('due_date')->get();

        // Calculate statistics
        $allItems = ClearanceItem::whereHas('offboardingCase', 
            fn($q) => $q->whereIn('status', ['in_progress', 'clearance_pending'])
        )->get();

        $stats = [
            'total_items' => $allItems->count(),
            'pending' => $allItems->where('status', 'pending')->count(),
            'approved' => $allItems->where('status', 'approved')->count(),
            'overdue' => $allItems->where('status', 'pending')
                ->where('due_date', '<', now())->count(),
            'compliance_rate' => $allItems->count() > 0
                ? round(($allItems->where('status', 'approved')->count() / $allItems->count()) * 100, 1)
                : 0,
            'by_department' => $allItems->groupBy('offboardingCase.employee.department.name')->map(fn($group) => $group->count())->toArray(),
        ];

        $data = [
            'clearance_items' => $clearanceItems,
            'stats' => $stats,
            'filter_status' => $status,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ];

        if ($format === 'csv') {
            return $this->exportClearanceComplianceCSV($data);
        }

        // Generate PDF
        $pdf = Pdf::loadView('HR.Offboarding.Reports.ClearanceCompliance', $data)
            ->setPaper('letter', 'landscape');

        $fileName = 'clearance-compliance-report-' . now()->format('Y-m-d') . '.pdf';

        Log::info('Clearance compliance report generated', [
            'total_items' => $stats['total_items'],
            'overdue' => $stats['overdue'],
            'generated_by' => auth()->id(),
        ]);

        return $pdf->download($fileName);
    }

    /**
     * Generate exit interview insights report.
     * 
     * Analyzes exit interview data for trends and actionable insights.
     * 
     * @param Request $request
     * @param string $format (pdf)
     * @return \Illuminate\Http\Response
     */
    public function exitInterviewInsights(Request $request, $format = 'pdf')
    {
        $this->authorize('viewOffboardingReports', OffboardingCase::class);

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subMonths(3);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();
        $departmentId = $request->department_id;

        // Query exit interviews
        $interviews = ExitInterview::with([
            'offboardingCase.employee.profile',
            'offboardingCase.employee.department',
            'conductedBy',
        ])
            ->whereBetween('interview_date', [$startDate, $endDate])
            ->when($departmentId, fn($q) => 
                $q->whereHas('offboardingCase.employee', fn($eq) => $eq->where('department_id', $departmentId))
            )
            ->get();

        // Analyze reasons for leaving
        $reasonsCount = [];
        foreach ($interviews as $interview) {
            $reasons = is_array($interview->reason_for_leaving) 
                ? $interview->reason_for_leaving 
                : [$interview->reason_for_leaving];

            foreach ($reasons as $reason) {
                $reasonsCount[$reason] = ($reasonsCount[$reason] ?? 0) + 1;
            }
        }
        arsort($reasonsCount);

        // Calculate average satisfaction scores
        $satisfactionMetrics = [
            'work_environment' => $interviews->avg('work_environment_satisfaction'),
            'management' => $interviews->avg('management_satisfaction'),
            'compensation' => $interviews->avg('compensation_satisfaction'),
            'career_growth' => $interviews->avg('career_growth_satisfaction'),
            'work_life_balance' => $interviews->avg('work_life_balance_satisfaction'),
        ];

        // Collect common feedback themes
        $feedbackThemes = [];
        foreach ($interviews as $interview) {
            if ($interview->additional_feedback) {
                $feedbackThemes[] = [
                    'employee' => $interview->offboardingCase->employee->profile?->first_name . ' ' . 
                                 $interview->offboardingCase->employee->profile?->last_name,
                    'department' => $interview->offboardingCase->employee->department?->name,
                    'feedback' => $interview->additional_feedback,
                    'date' => $interview->interview_date->format('M d, Y'),
                ];
            }
        }

        // Recommendations extraction
        $recommendations = $interviews->pluck('hr_recommendations')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $stats = [
            'total_interviews' => $interviews->count(),
            'average_overall_satisfaction' => round($interviews->avg(function ($interview) {
                return ($interview->work_environment_satisfaction +
                        $interview->management_satisfaction +
                        $interview->compensation_satisfaction +
                        $interview->career_growth_satisfaction +
                        $interview->work_life_balance_satisfaction) / 5;
            }), 1),
            'would_recommend_count' => $interviews->where('would_recommend_company', true)->count(),
            'would_recommend_percentage' => $interviews->count() > 0
                ? round(($interviews->where('would_recommend_company', true)->count() / $interviews->count()) * 100, 1)
                : 0,
        ];

        $data = [
            'interviews' => $interviews,
            'stats' => $stats,
            'reasons_count' => $reasonsCount,
            'satisfaction_metrics' => $satisfactionMetrics,
            'feedback_themes' => $feedbackThemes,
            'recommendations' => $recommendations,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ];

        // PDF only for this report (contains narrative insights)
        $pdf = Pdf::loadView('HR.Offboarding.Reports.ExitInterviewInsights', $data)
            ->setPaper('letter', 'portrait');

        $fileName = 'exit-interview-insights-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d') . '.pdf';

        Log::info('Exit interview insights report generated', [
            'total_interviews' => $stats['total_interviews'],
            'date_range' => [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')],
            'generated_by' => auth()->id(),
        ]);

        return $pdf->download($fileName);
    }

    /**
     * Generate asset liability report.
     * 
     * Lists all unreturned assets from separated employees.
     * Groups by department and calculates total liability.
     * 
     * @param Request $request
     * @param string $format (pdf|csv)
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function assetLiabilityReport(Request $request, $format = 'pdf')
    {
        $this->authorize('viewOffboardingReports', OffboardingCase::class);

        $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'min_value' => 'nullable|numeric|min:0',
        ]);

        $departmentId = $request->department_id;
        $minValue = $request->min_value ?? 0;

        // Query unreturned assets from offboarding cases
        $assets = CompanyAsset::with([
            'offboardingCase.employee.profile',
            'offboardingCase.employee.department',
            'assignedTo',
        ])
            ->whereHas('offboardingCase', fn($q) => 
                $q->whereIn('status', ['in_progress', 'clearance_pending', 'completed'])
            )
            ->whereIn('return_status', ['not_returned', 'overdue'])
            ->when($departmentId, fn($q) => 
                $q->whereHas('offboardingCase.employee', fn($eq) => $eq->where('department_id', $departmentId))
            )
            ->when($minValue > 0, fn($q) => $q->where('asset_value', '>=', $minValue))
            ->orderBy('expected_return_date')
            ->get();

        // Calculate statistics
        $stats = [
            'total_assets' => $assets->count(),
            'total_value' => $assets->sum('asset_value'),
            'overdue_assets' => $assets->where('return_status', 'overdue')->count(),
            'overdue_value' => $assets->where('return_status', 'overdue')->sum('asset_value'),
            'by_department' => $assets->groupBy('offboardingCase.employee.department.name')
                ->map(function ($items) {
                    return [
                        'count' => $items->count(),
                        'value' => $items->sum('asset_value'),
                    ];
                })
                ->toArray(),
            'by_asset_type' => $assets->groupBy('asset_type')->map(fn($group) => $group->count())->toArray(),
        ];

        $data = [
            'assets' => $assets,
            'stats' => $stats,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ];

        if ($format === 'csv') {
            return $this->exportAssetLiabilityCSV($data);
        }

        // Generate PDF
        $pdf = Pdf::loadView('HR.Offboarding.Reports.AssetLiability', $data)
            ->setPaper('letter', 'landscape');

        $fileName = 'asset-liability-report-' . now()->format('Y-m-d') . '.pdf';

        Log::info('Asset liability report generated', [
            'total_assets' => $stats['total_assets'],
            'total_value' => $stats['total_value'],
            'generated_by' => auth()->id(),
        ]);

        return $pdf->download($fileName);
    }

    /**
     * Generate rehire eligibility report.
     * 
     * Lists all separated employees eligible for rehire with their details.
     * 
     * @param Request $request
     * @param string $format (pdf|csv)
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function rehireEligibilityReport(Request $request, $format = 'pdf')
    {
        $this->authorize('viewOffboardingReports', OffboardingCase::class);

        $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'separation_date_from' => 'nullable|date',
            'separation_date_to' => 'nullable|date|after_or_equal:separation_date_from',
        ]);

        $departmentId = $request->department_id;
        $dateFrom = $request->separation_date_from ? Carbon::parse($request->separation_date_from) : now()->subYear();
        $dateTo = $request->separation_date_to ? Carbon::parse($request->separation_date_to) : now();

        // Query eligible employees
        $cases = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'employee.position',
            'exitInterview',
        ])
            ->where('status', 'completed')
            ->where('rehire_eligible', true)
            ->whereBetween('actual_separation_date', [$dateFrom, $dateTo])
            ->when($departmentId, fn($q) => 
                $q->whereHas('employee', fn($eq) => $eq->where('department_id', $departmentId))
            )
            ->orderBy('actual_separation_date', 'desc')
            ->get();

        // Calculate statistics
        $stats = [
            'total_eligible' => $cases->count(),
            'by_department' => $cases->groupBy('employee.department.name')->map(fn($group) => $group->count())->toArray(),
            'by_separation_type' => $cases->groupBy('separation_type')->map(fn($group) => $group->count())->toArray(),
            'with_high_performance' => $cases->filter(function ($case) {
                // Consider employees with average exit interview satisfaction >= 4
                if (!$case->exitInterview) return false;
                $avgScore = ($case->exitInterview->work_environment_satisfaction +
                            $case->exitInterview->management_satisfaction +
                            $case->exitInterview->compensation_satisfaction +
                            $case->exitInterview->career_growth_satisfaction +
                            $case->exitInterview->work_life_balance_satisfaction) / 5;
                return $avgScore >= 4;
            })->count(),
        ];

        $data = [
            'cases' => $cases,
            'stats' => $stats,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name,
        ];

        if ($format === 'csv') {
            return $this->exportRehireEligibilityCSV($data);
        }

        // Generate PDF
        $pdf = Pdf::loadView('HR.Offboarding.Reports.RehireEligibility', $data)
            ->setPaper('letter', 'portrait');

        $fileName = 'rehire-eligibility-report-' . now()->format('Y-m-d') . '.pdf';

        Log::info('Rehire eligibility report generated', [
            'total_eligible' => $stats['total_eligible'],
            'generated_by' => auth()->id(),
        ]);

        return $pdf->download($fileName);
    }

    /**
     * Export monthly separation report as CSV.
     */
    private function exportMonthlySeparationCSV($data)
    {
        $filename = 'monthly-separation-report-' . $data['month']->format('Y-m') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Case Number',
                'Employee Name',
                'Employee Number',
                'Department',
                'Position',
                'Separation Type',
                'Separation Date',
                'Reason',
                'Rehire Eligible',
            ]);

            // CSV Data
            foreach ($data['cases'] as $case) {
                fputcsv($file, [
                    $case->case_number,
                    $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                    $case->employee->employee_number,
                    $case->employee->department?->name,
                    $case->employee->position?->title,
                    ucfirst(str_replace('_', ' ', $case->separation_type)),
                    $case->actual_separation_date?->format('Y-m-d'),
                    $case->separation_reason,
                    $case->rehire_eligible ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export clearance compliance report as CSV.
     */
    private function exportClearanceComplianceCSV($data)
    {
        $filename = 'clearance-compliance-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Case Number',
                'Employee Name',
                'Department',
                'Clearance Item',
                'Assigned To',
                'Status',
                'Due Date',
                'Days Overdue',
            ]);

            // CSV Data
            foreach ($data['clearance_items'] as $item) {
                $daysOverdue = $item->status === 'pending' && $item->due_date < now()
                    ? now()->diffInDays($item->due_date)
                    : 0;

                fputcsv($file, [
                    $item->offboardingCase->case_number,
                    $item->offboardingCase->employee->profile?->first_name . ' ' . 
                        $item->offboardingCase->employee->profile?->last_name,
                    $item->offboardingCase->employee->department?->name,
                    $item->clearance_name,
                    $item->assignedTo?->name ?? 'Unassigned',
                    ucfirst($item->status),
                    $item->due_date->format('Y-m-d'),
                    $daysOverdue,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export asset liability report as CSV.
     */
    private function exportAssetLiabilityCSV($data)
    {
        $filename = 'asset-liability-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Case Number',
                'Employee Name',
                'Department',
                'Asset Name',
                'Asset Type',
                'Asset Value',
                'Expected Return Date',
                'Return Status',
                'Days Overdue',
            ]);

            // CSV Data
            foreach ($data['assets'] as $asset) {
                $daysOverdue = $asset->return_status === 'overdue'
                    ? now()->diffInDays($asset->expected_return_date)
                    : 0;

                fputcsv($file, [
                    $asset->offboardingCase->case_number,
                    $asset->offboardingCase->employee->profile?->first_name . ' ' . 
                        $asset->offboardingCase->employee->profile?->last_name,
                    $asset->offboardingCase->employee->department?->name,
                    $asset->asset_name,
                    ucfirst(str_replace('_', ' ', $asset->asset_type)),
                    number_format($asset->asset_value, 2),
                    $asset->expected_return_date?->format('Y-m-d'),
                    ucfirst(str_replace('_', ' ', $asset->return_status)),
                    $daysOverdue,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export rehire eligibility report as CSV.
     */
    private function exportRehireEligibilityCSV($data)
    {
        $filename = 'rehire-eligibility-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Case Number',
                'Employee Name',
                'Employee Number',
                'Department',
                'Position',
                'Separation Type',
                'Separation Date',
                'Rehire Eligible',
                'Eligibility Reason',
                'Overall Satisfaction Score',
            ]);

            // CSV Data
            foreach ($data['cases'] as $case) {
                $overallScore = null;
                if ($case->exitInterview) {
                    $overallScore = round(($case->exitInterview->work_environment_satisfaction +
                                          $case->exitInterview->management_satisfaction +
                                          $case->exitInterview->compensation_satisfaction +
                                          $case->exitInterview->career_growth_satisfaction +
                                          $case->exitInterview->work_life_balance_satisfaction) / 5, 1);
                }

                fputcsv($file, [
                    $case->case_number,
                    $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                    $case->employee->employee_number,
                    $case->employee->department?->name,
                    $case->employee->position?->title,
                    ucfirst(str_replace('_', ' ', $case->separation_type)),
                    $case->actual_separation_date?->format('Y-m-d'),
                    $case->rehire_eligible ? 'Yes' : 'No',
                    $case->rehire_eligible_reason ?? '',
                    $overallScore ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
