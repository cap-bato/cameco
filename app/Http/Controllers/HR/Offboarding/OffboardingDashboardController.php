<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\ClearanceItem;
use App\Models\ExitInterview;
use App\Models\CompanyAsset;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OffboardingDashboardController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * Display the offboarding dashboard with statistics, pending cases, and activity.
     * 
     * Shows:
     * - Key statistics (pending, in progress, overdue)
     * - Cases due this week or next week
     * - Recent activity timeline
     * - Separation trends chart
     * - Quick action links
     */
    public function index(Request $request): Response
    {
        // Get statistics
        $statistics = $this->getStatistics();

        // Get cases due this week and next week
        $casesThisWeek = $this->getCasesDueThisWeek();
        $casesNextWeek = $this->getCasesDueNextWeek();
        $overduesCases = $this->getOverdueCases();

        // Get recent activity
        $recentActivity = $this->getRecentActivity();

        // Get trends data (last 12 months)
        $trends = $this->getTrendData();

        // Get separation reasons for the period
        $separationReasons = $this->getSeparationReasons();

        // Get clearance statistics
        $clearanceStats = $this->getClearanceStatistics();

        // Get user's assigned cases (if coordinator)
        $myAssignedCases = $this->getMyAssignedCases();

        return Inertia::render('HR/Offboarding/Dashboard', [
            'statistics' => $statistics,
            'casesThisWeek' => $casesThisWeek,
            'casesNextWeek' => $casesNextWeek,
            'overdueCases' => $overduesCases,
            'recentActivity' => $recentActivity,
            'trends' => $trends,
            'separationReasons' => $separationReasons,
            'clearanceStats' => $clearanceStats,
            'myAssignedCases' => $myAssignedCases,
            'userCanInitiate' => auth()->user()->hasPermissionTo('hr.offboarding.create'),
        ]);
    }

    /**
     * Get summary statistics for the dashboard.
     */
    private function getStatistics(): array
    {
        $now = now();

        // Get all cases
        $allCases = OffboardingCase::count();
        $pending = OffboardingCase::where('status', 'pending')->count();
        $inProgress = OffboardingCase::where('status', 'in_progress')->count();
        $clearancePending = OffboardingCase::where('status', 'clearance_pending')->count();
        $completed = OffboardingCase::where('status', 'completed')->count();
        $cancelled = OffboardingCase::where('status', 'cancelled')->count();

        // Get overdue cases (past last_working_day without completion)
        $overdue = OffboardingCase::where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->where('last_working_day', '<', now()->toDateString())
            ->count();

        // Get pending clearance items
        $pendingClearances = ClearanceItem::where('status', 'pending')->count();
        $approvedClearances = ClearanceItem::where('status', 'approved')->count();
        $issuedClearances = ClearanceItem::where('status', 'issues')->count();

        // Get average clearance approval rate
        $totalClearances = ClearanceItem::count();
        $clearanceApprovalRate = $totalClearances > 0 
            ? round(($approvedClearances / $totalClearances) * 100, 1)
            : 0;

        // Get exit interviews completed
        $exitInterviewsCompleted = ExitInterview::where('status', 'completed')->count();

        // Get assets pending return
        $assetsPendingReturn = CompanyAsset::where('status', 'issued')->count();
        $assetsReturned = CompanyAsset::where('status', 'returned')->count();
        $assetsLostDamaged = CompanyAsset::whereIn('status', ['lost', 'damaged'])->count();

        return [
            'total_cases' => $allCases,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'clearance_pending' => $clearancePending,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'completion_rate' => $allCases > 0 ? round(($completed / $allCases) * 100, 1) : 0,
            'clearance' => [
                'pending' => $pendingClearances,
                'approved' => $approvedClearances,
                'issues' => $issuedClearances,
                'approval_rate' => $clearanceApprovalRate,
            ],
            'exit_interviews' => [
                'completed' => $exitInterviewsCompleted,
            ],
            'assets' => [
                'pending_return' => $assetsPendingReturn,
                'returned' => $assetsReturned,
                'lost_damaged' => $assetsLostDamaged,
            ],
        ];
    }

    /**
     * Get cases due within the current week.
     */
    private function getCasesDueThisWeek(): array
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        return OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'hrCoordinator',
        ])
        ->where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->whereBetween('last_working_day', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
        ->orderBy('last_working_day')
        ->get()
        ->map(fn($case) => $this->transformCaseForList($case))
        ->toArray();
    }

    /**
     * Get cases due next week.
     */
    private function getCasesDueNextWeek(): array
    {
        $startOfNextWeek = now()->addWeek()->startOfWeek();
        $endOfNextWeek = now()->addWeek()->endOfWeek();

        return OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'hrCoordinator',
        ])
        ->where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->whereBetween('last_working_day', [$startOfNextWeek->toDateString(), $endOfNextWeek->toDateString()])
        ->orderBy('last_working_day')
        ->limit(5)
        ->get()
        ->map(fn($case) => $this->transformCaseForList($case))
        ->toArray();
    }

    /**
     * Get overdue cases (past last_working_day without completion).
     */
    private function getOverdueCases(): array
    {
        return OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'hrCoordinator',
        ])
        ->where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->where('last_working_day', '<', now()->toDateString())
        ->orderBy('last_working_day')
        ->limit(10)
        ->get()
        ->map(fn($case) => $this->transformCaseForList($case))
        ->toArray();
    }

    /**
     * Get recent activity timeline.
     */
    private function getRecentActivity(): array
    {
        $activities = [];

        // Recent case completions
        $recentCompletions = OffboardingCase::with('employee.profile', 'employee.department')
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        foreach ($recentCompletions as $case) {
            $activities[] = [
                'type' => 'case_completed',
                'title' => 'Offboarding Case Completed',
                'description' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name . ' (Case #' . $case->case_number . ')',
                'timestamp' => $case->completed_at?->format('H:i'),
                'date' => $case->completed_at?->format('M d, Y'),
                'severity' => 'success',
            ];
        }

        // Recent exit interviews
        $recentInterviews = ExitInterview::with('offboardingCase.employee.profile')
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();

        foreach ($recentInterviews as $interview) {
            $activities[] = [
                'type' => 'exit_interview_completed',
                'title' => 'Exit Interview Completed',
                'description' => 'Interview completed for ' . $interview->offboardingCase->employee->profile?->first_name,
                'timestamp' => $interview->completed_at?->format('H:i'),
                'date' => $interview->completed_at?->format('M d, Y'),
                'severity' => 'info',
            ];
        }

        // Recent clearance approvals
        $recentApprovals = ClearanceItem::with('offboardingCase.employee.profile')
            ->where('status', 'approved')
            ->where('approved_at', '>=', now()->subDays(7))
            ->orderByDesc('approved_at')
            ->limit(3)
            ->get();

        foreach ($recentApprovals as $clearance) {
            $activities[] = [
                'type' => 'clearance_approved',
                'title' => 'Clearance Approved',
                'description' => $clearance->category . ' clearance approved',
                'timestamp' => $clearance->approved_at?->format('H:i'),
                'date' => $clearance->approved_at?->format('M d, Y'),
                'severity' => 'success',
            ];
        }

        // Sort by date descending
        usort($activities, function ($a, $b) {
            return strtotime($b['date'] . ' ' . $b['timestamp']) - strtotime($a['date'] . ' ' . $a['timestamp']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Get trend data for the last 12 months.
     */
    private function getTrendData(): array
    {
        $data = [];
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');

            $count = OffboardingCase::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $data[] = $count;
        }

        return [
            'labels' => $months,
            'data' => $data,
        ];
    }

    /**
     * Get separation reasons statistics.
     */
    private function getSeparationReasons(): array
    {
        $reasons = OffboardingCase::select('separation_type', DB::raw('count(*) as count'))
            ->where('status', '!=', 'cancelled')
            ->groupBy('separation_type')
            ->get()
            ->mapWithKeys(fn($item) => [
                'label' => ucfirst(str_replace('_', ' ', $item->separation_type)),
                'value' => $item->count,
            ])
            ->toArray();

        return [
            'labels' => array_keys($reasons),
            'data' => array_values($reasons),
        ];
    }

    /**
     * Get clearance statistics by category.
     */
    private function getClearanceStatistics(): array
    {
        $stats = ClearanceItem::select('category', DB::raw('count(*) as total'))
            ->selectRaw('sum(case when status = \'approved\' then 1 else 0 end) as approved')
            ->selectRaw('sum(case when status = \'pending\' then 1 else 0 end) as pending')
            ->selectRaw('sum(case when status = \'issues\' then 1 else 0 end) as issues')
            ->groupBy('category')
            ->get()
            ->map(function ($stat) {
                return [
                    'category' => ucfirst(str_replace('_', ' ', $stat->category)),
                    'total' => $stat->total,
                    'approved' => $stat->approved,
                    'pending' => $stat->pending,
                    'issues' => $stat->issues,
                    'approval_rate' => $stat->total > 0 ? round(($stat->approved / $stat->total) * 100, 1) : 0,
                ];
            })
            ->toArray();

        return $stats;
    }

    /**
     * Get cases assigned to the current user (if they are an HR coordinator).
     */
    private function getMyAssignedCases(): array
    {
        return OffboardingCase::with([
            'employee.profile',
            'employee.department',
        ])
        ->where('hr_coordinator_id', auth()->id())
        ->where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->orderBy('last_working_day')
        ->limit(5)
        ->get()
        ->map(fn($case) => $this->transformCaseForList($case))
        ->toArray();
    }

    /**
     * Transform case data for list display.
     */
    private function transformCaseForList(OffboardingCase $case): array
    {
        $clearanceCompletionRate = $case->clearanceItems->count() > 0
            ? round(($case->clearanceItems->where('status', 'approved')->count() / $case->clearanceItems->count()) * 100)
            : 0;

        $daysRemaining = (int) now()->diffInDays($case->last_working_day, false);
        $isOverdue = $daysRemaining < 0;

        return [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'employee_name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
            'employee_number' => $case->employee->employee_number,
            'department' => $case->employee->department?->name,
            'status' => $case->status,
            'separation_type' => ucfirst(str_replace('_', ' ', $case->separation_type)),
            'last_working_day' => $case->last_working_day->format('M d, Y'),
            'hr_coordinator' => $case->hrCoordinator?->name,
            'clearance_completion' => $clearanceCompletionRate,
            'days_remaining' => abs($daysRemaining),
            'is_overdue' => $isOverdue,
            'exit_interview_completed' => $case->exitInterview ? $case->exitInterview->status === 'completed' : false,
        ];
    }
}
