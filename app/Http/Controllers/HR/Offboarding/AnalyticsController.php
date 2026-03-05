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
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Display the exit analytics dashboard.
     * Phase 5, Task 5.1
     */
    public function index(Request $request): Response
    {
        $dateRange = $request->input('date_range', 'last_12_months');
        $department = $request->input('department', null);
        $sepType = $request->input('separation_type', null);

        // Calculate date range
        $startDate = $this->getStartDate($dateRange);
        $endDate = now();

        // Get all metrics
        $separationTrends = $this->getSeparationTrends($startDate, $endDate, $department, $sepType);
        $exitReasons = $this->getExitReasons($startDate, $endDate, $department, $sepType);
        $satisfactionScores = $this->getSatisfactionScores($startDate, $endDate, $department);
        $retentionInsights = $this->getRetentionInsights($startDate, $endDate, $department, $sepType);
        $offboardingEfficiency = $this->getOffboardingEfficiency($startDate, $endDate, $department);
        $departmentAnalytics = $this->getDepartmentAnalytics($startDate, $endDate);
        $separationTypeDistribution = $this->getSeparationTypeDistribution($startDate, $endDate);

        return Inertia::render('HR/Offboarding/Analytics', [
            'separationTrends' => $separationTrends,
            'exitReasons' => $exitReasons,
            'satisfactionScores' => $satisfactionScores,
            'retentionInsights' => $retentionInsights,
            'offboardingEfficiency' => $offboardingEfficiency,
            'departmentAnalytics' => $departmentAnalytics,
            'separationTypeDistribution' => $separationTypeDistribution,
            'dateRange' => $dateRange,
            'department' => $department,
            'separationType' => $sepType,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'departments' => $this->getAvailableDepartments(),
            'separationTypes' => ['voluntary', 'involuntary', 'retirement', 'contract_end'],
        ]);
    }

    /**
     * Get separation trends by month.
     */
    private function getSeparationTrends(Carbon $startDate, Carbon $endDate, ?string $department = null, ?string $sepType = null): array
    {
        $query = OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_TRUNC(\'month\', completed_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month');

        if ($department) {
            $query->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
        }

        if ($sepType) {
            $query->where('separation_type', $sepType);
        }

        $trends = $query->get()->map(function ($item) {
            $month = Carbon::parse($item->month);
            return [
                'month' => $month->format('M Y'),
                'month_key' => $month->format('m'),
                'count' => (int) $item->count,
            ];
        })->toArray();

        // Fill in missing months
        return $this->fillMissingMonths($trends, $startDate, $endDate);
    }

    /**
     * Get exit reasons from exit interviews.
     */
    private function getExitReasons(Carbon $startDate, Carbon $endDate, ?string $department = null, ?string $sepType = null): array
    {
        $query = ExitInterview::whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereNotNull('reason_for_leaving')
            ->select('reason_for_leaving', DB::raw('COUNT(*) as count'))
            ->groupBy('reason_for_leaving')
            ->orderByDesc('count')
            ->limit(10);

        if ($department) {
            $query->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
        }

        return $query->get()->map(function ($item) {
            return [
                'reason' => $item->reason_for_leaving,
                'count' => (int) $item->count,
                'percentage' => 0, // Will be calculated in frontend
            ];
        })->toArray();
    }

    /**
     * Get satisfaction scores by category.
     */
    private function getSatisfactionScores(Carbon $startDate, Carbon $endDate, ?string $department = null): array
    {
        $query = ExitInterview::whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->select(
                DB::raw('AVG(overall_satisfaction) as overall'),
                DB::raw('AVG(work_environment_rating) as environment'),
                DB::raw('AVG(management_rating) as management'),
                DB::raw('AVG(compensation_rating) as compensation'),
                DB::raw('AVG(career_growth_rating) as growth'),
                DB::raw('AVG(work_life_balance_rating) as balance'),
                DB::raw('COUNT(*) as total')
            );

        if ($department) {
            $query->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
        }

        $scores = $query->first();

        return [
            'overall' => round($scores->overall ?? 0, 2),
            'environment' => round($scores->environment ?? 0, 2),
            'management' => round($scores->management ?? 0, 2),
            'compensation' => round($scores->compensation ?? 0, 2),
            'growth' => round($scores->growth ?? 0, 2),
            'balance' => round($scores->balance ?? 0, 2),
            'total_respondents' => (int) $scores->total,
            'chart_data' => [
                ['category' => 'Overall Satisfaction', 'score' => round($scores->overall ?? 0, 2)],
                ['category' => 'Work Environment', 'score' => round($scores->environment ?? 0, 2)],
                ['category' => 'Management', 'score' => round($scores->management ?? 0, 2)],
                ['category' => 'Compensation', 'score' => round($scores->compensation ?? 0, 2)],
                ['category' => 'Career Growth', 'score' => round($scores->growth ?? 0, 2)],
                ['category' => 'Work-Life Balance', 'score' => round($scores->balance ?? 0, 2)],
            ],
        ];
    }

    /**
     * Get retention insights.
     */
    private function getRetentionInsights(Carbon $startDate, Carbon $endDate, ?string $department = null, ?string $sepType = null): array
    {
        $completedCases = OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate]);

        $allCases = OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate]);

        if ($department) {
            $completedCases->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
            $allCases->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
        }

        $voluntarySeparations = (clone $completedCases)->where('separation_type', 'voluntary')->count();
        $involuntarySeparations = (clone $completedCases)->where('separation_type', 'involuntary')->count();
        $totalSeparations = $completedCases->count();

        // Calculate average tenure
        $avgTenure = Employee::with('offboardingCases')
            ->whereHas('offboardingCases', fn($q) => $q->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate, $endDate]))
            ->select(
                DB::raw('AVG(EXTRACT(YEAR FROM AGE(termination_date, date_hired))) as years'),
                DB::raw('AVG(EXTRACT(DAY FROM AGE(termination_date, date_hired)) % 365 / 30) as months')
            )
            ->first();

        // Calculate rehire eligibility
        $rehireEligible = OffboardingCase::where('status', 'completed')
            ->where('rehire_eligible', true)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        return [
            'total_separations' => $totalSeparations,
            'voluntary_separations' => $voluntarySeparations,
            'involuntary_separations' => $involuntarySeparations,
            'voluntary_percentage' => $totalSeparations > 0 ? round(($voluntarySeparations / $totalSeparations) * 100, 1) : 0,
            'involuntary_percentage' => $totalSeparations > 0 ? round(($involuntarySeparations / $totalSeparations) * 100, 1) : 0,
            'average_tenure_years' => round($avgTenure->years ?? 0, 1),
            'average_tenure_months' => round($avgTenure->months ?? 0, 1),
            'rehire_eligible_count' => $rehireEligible,
            'rehire_eligible_percentage' => $totalSeparations > 0 ? round(($rehireEligible / $totalSeparations) * 100, 1) : 0,
        ];
    }

    /**
     * Get offboarding efficiency metrics.
     */
    private function getOffboardingEfficiency(Carbon $startDate, Carbon $endDate, ?string $department = null): array
    {
        $query = OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->select(
                DB::raw('AVG(EXTRACT(DAY FROM (completed_at - created_at))) as avg_days_to_complete')
            );

        if ($department) {
            $query->whereHas('employee', fn($q) => $q->whereHas('department', fn($dq) => $dq->where('id', $department)));
        }

        $efficiency = $query->first();

        // Get clearance bottlenecks (slowest approvers)
        $slowestApprovers = ClearanceItem::where('status', 'approved')
            ->whereNotNull('approved_at')
            ->select(
                'assigned_to',
                DB::raw('AVG(EXTRACT(DAY FROM (approved_at - created_at))) as avg_approval_days'),
                DB::raw('COUNT(*) as total_items')
            )
            ->groupBy('assigned_to')
            ->orderByDesc('avg_approval_days')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'approver_id' => $item->assigned_to,
                    'avg_days' => round($item->avg_approval_days ?? 0, 1),
                    'total_items' => (int) $item->total_items,
                ];
            });

        // Document generation speed (average time from case start to final docs)
        $docGenSpeed = OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('final_documents_generated_at')
            ->select(
                DB::raw('AVG(EXTRACT(DAY FROM (final_documents_generated_at - created_at))) as avg_doc_gen_days')
            )
            ->first();

        return [
            'average_days_to_complete' => round($efficiency->avg_days_to_complete ?? 0, 1),
            'average_doc_gen_days' => round($docGenSpeed->avg_doc_gen_days ?? 0, 1),
            'bottleneck_approvers' => $slowestApprovers->toArray(),
        ];
    }

    /**
     * Get analytics by department.
     */
    private function getDepartmentAnalytics(Carbon $startDate, Carbon $endDate): array
    {
        $departments = DB::table('departments as d')
            ->leftJoin('employees as e', 'd.id', '=', 'e.department_id')
            ->leftJoin('offboarding_cases as oc', function ($join) use ($startDate, $endDate) {
                $join->on('e.id', '=', 'oc.employee_id')
                    ->where('oc.status', 'completed')
                    ->whereBetween('oc.completed_at', [$startDate, $endDate]);
            })
            ->select(
                'd.id',
                'd.name',
                DB::raw('COUNT(DISTINCT e.id) as employee_count'),
                DB::raw('COUNT(DISTINCT oc.id) as separation_count')
            )
            ->where('e.status', 'active')
            ->orWhere('oc.id', '!=', null)
            ->groupBy('d.id', 'd.name')
            ->get()
            ->map(function ($dept) {
                return [
                    'department_id' => $dept->id,
                    'department_name' => $dept->name,
                    'employee_count' => (int) $dept->employee_count,
                    'separation_count' => (int) $dept->separation_count,
                    'separation_rate' => $dept->employee_count > 0 ? round(($dept->separation_count / $dept->employee_count) * 100, 1) : 0,
                ];
            })
            ->toArray();

        return $departments;
    }

    /**
     * Get separation type distribution.
     */
    private function getSeparationTypeDistribution(Carbon $startDate, Carbon $endDate): array
    {
        return OffboardingCase::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->select('separation_type', DB::raw('COUNT(*) as count'))
            ->groupBy('separation_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => ucfirst(str_replace('_', ' ', $item->separation_type ?? 'unknown')),
                    'type_key' => $item->separation_type ?? 'unknown',
                    'count' => (int) $item->count,
                    'percentage' => 0, // Calculated in frontend
                ];
            })
            ->toArray();
    }

    /**
     * Get start date based on date range.
     */
    private function getStartDate(string $dateRange): Carbon
    {
        return match ($dateRange) {
            'last_30_days' => now()->subDays(30),
            'last_90_days' => now()->subDays(90),
            'last_6_months' => now()->subMonths(6),
            'last_12_months' => now()->subMonths(12),
            'this_year' => now()->startOfYear(),
            default => now()->subMonths(12),
        };
    }

    /**
     * Get available departments.
     */
    private function getAvailableDepartments(): array
    {
        return DB::table('departments')
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->name])
            ->toArray();
    }

    /**
     * Fill missing months in trend data.
     */
    private function fillMissingMonths(array $trends, Carbon $startDate, Carbon $endDate): array
    {
        $filled = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current <= $endDate) {
            $monthKey = $current->format('m');
            $monthLabel = $current->format('M Y');

            $existing = collect($trends)->firstWhere('month_key', $monthKey);
            $filled[] = [
                'month' => $monthLabel,
                'month_key' => $monthKey,
                'count' => $existing ? $existing['count'] : 0,
            ];

            $current->addMonth();
        }

        return $filled;
    }
}
