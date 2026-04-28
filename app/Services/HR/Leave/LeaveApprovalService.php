<?php

namespace App\Services\HR\Leave;

use App\Models\LeaveRequest;
use App\Models\SystemSetting;
use App\Models\LeaveBlackoutPeriod;
use App\Services\HR\Workforce\WorkforceCoverageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave Approval Service
 *
 * Handles leave request approval logic including:
 * - Auto-approval determination
 * - Duration-based routing
 * - Self-approval prevention
 * - Coverage validation
 * - Blackout period checking
 */
class LeaveApprovalService
{
    public function __construct(
        protected WorkforceCoverageService $coverageService,
        protected LeaveBalanceService $balanceService
    ) {}

    /**
     * Check if leave request can be auto-approved
     *
     * Criteria:
     * - Duration is 1-2 days
     * - Employee has sufficient balance
     * - Department coverage meets minimum threshold
     * - Advance notice meets minimum requirement
     * - Not within blackout period
     * - Auto-approval is enabled system-wide
     */
    public function canAutoApprove(LeaveRequest $leaveRequest): bool
    {
        if (!$this->isAutoApprovalEnabled()) {
            return false;
        }

        // Use days_requested (including 0.5 for half-days) instead of calendar days
        if (!$leaveRequest->isAutoApprovable()) {
            return false;
        }

        if (!$this->balanceService->hasSufficientBalance(
            $leaveRequest->employee_id,
            $leaveRequest->leave_policy_id,
            $leaveRequest->days_requested
        )) {
            return false;
        }

        if (!$this->checkWorkforceCoverage($leaveRequest)) {
            return false;
        }

        if (!$this->meetsAdvanceNotice($leaveRequest->start_date)) {
            return false;
        }

        if ($this->isInBlackoutPeriod($leaveRequest)) {
            return false;
        }

        return true;
    }

    /**
     * Check if department coverage meets minimum threshold
     */
    public function checkWorkforceCoverage(LeaveRequest $leaveRequest): bool
    {
        $employee = $leaveRequest->employee;
        $department = $employee->department;

        if (!$department) {
            return false;
        }

        $coverage = $this->coverageService->calculateCoverageWithLeave(
            $department->id,
            $leaveRequest->start_date,
            $leaveRequest->end_date,
            $employee->id,
            $leaveRequest->days_requested
        );

        $minCoverage = $department->min_coverage_percentage ?? 75.00;

        return $coverage >= $minCoverage;
    }

    /**
     * Determine the required approvers for a leave request based on
     * duration and requestor role ONLY — does NOT re-evaluate auto-approval.
     *
     * This is the method used when deciding who can approve an already-submitted request.
     *
     * Rules:
     * - 1-2 days: HR Manager (auto-approval was already attempted at submission)
     * - 3-5 days: HR Manager (or Office Admin if requestor is HR Manager)
     * - 6+ days:  HR Manager + Office Admin (or Office Admin only if requestor is HR Manager)
     *
     * @return array{route: string, required_approvers: string[]}
     */
    public function getApprovalRouteForRequest(LeaveRequest $leaveRequest): array
    {
        // Use days_requested (including 0.5 for half-days) instead of calendar days
        $isHRManager = $leaveRequest->employee->user?->hasRole('HR Manager') ?? false;

        if ($leaveRequest->isAutoApprovable()) {
            // Short leave (1-2 days) that wasn't auto-approved — needs HR Manager
            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager'],
            ];
        }

        if ($leaveRequest->requiresManagerApproval() && !$leaveRequest->requiresAdminApproval()) {
            // Medium leave (3-5 days)
            if ($isHRManager) {
                return [
                    'route' => 'admin',
                    'required_approvers' => ['Office Admin'],
                ];
            }

            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager'],
            ];
        }

        // 6+ days
        if ($isHRManager) {
            return [
                'route' => 'admin',
                'required_approvers' => ['Office Admin'],
            ];
        }

        return [
            'route' => 'manager_and_admin',
            'required_approvers' => ['HR Manager', 'Office Admin'],
        ];
    }

    /**
     * Determine approval route at submission time.
     *
     * This is called when a request is first submitted. It checks if the request
     * qualifies for auto-approval and returns 'auto' if so. For already-submitted
     * requests use getApprovalRouteForRequest() instead.
     *
     * @return array{route: string, required_approvers: string[], message?: string}
     */
    public function determineApprovalRoute(LeaveRequest $leaveRequest): array
    {
        // Use days_requested (including 0.5 for half-days) instead of calendar days
        $isHRManager = $leaveRequest->employee->user?->hasRole('HR Manager') ?? false;

        if ($leaveRequest->isAutoApprovable()) {
            if ($this->canAutoApprove($leaveRequest)) {
                return [
                    'route' => 'auto',
                    'required_approvers' => [],
                    'message' => 'Request has been auto-approved.',
                ];
            }

            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager'],
                'message' => 'Request is awaiting HR Manager approval.',
            ];
        }

        if ($leaveRequest->requiresManagerApproval() && !$leaveRequest->requiresAdminApproval()) {
            if ($isHRManager) {
                return [
                    'route' => 'admin',
                    'required_approvers' => ['Office Admin'],
                    'message' => 'Request has been forwarded to the Office Admin for approval.',
                ];
            }

            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager'],
                'message' => 'Request is awaiting HR Manager approval.',
            ];
        }

        // 6+ days
        if ($isHRManager) {
            return [
                'route' => 'admin',
                'required_approvers' => ['Office Admin'],
                'message' => 'Request has been forwarded to the Office Admin for approval.',
            ];
        }

        return [
            'route' => 'manager_and_admin',
            'required_approvers' => ['HR Manager', 'Office Admin'],
            'message' => 'Request is awaiting HR Manager approval, then Office Admin.',
        ];
    }

    /**
     * Check if a user can approve the given leave request.
     *
     * Uses getApprovalRouteForRequest() — which is based purely on duration/role —
     * so it never re-runs canAutoApprove() and never produces an empty approvers list
     * for a pending request.
     *
     * @param LeaveRequest $leaveRequest
     * @param int          $userId  The ID of the user attempting to approve
     * @param string       $role    The approving user's role ('HR Manager' | 'Office Admin')
     */
    public function canUserApprove(LeaveRequest $leaveRequest, int $userId, string $role): bool
    {
        // Prevent self-approval
        if ($leaveRequest->employee->user_id === $userId) {
            return false;
        }

        // Use the submission-time-agnostic route resolver
        $route = $this->getApprovalRouteForRequest($leaveRequest);

        // Role must be in the required list for this route
        if (!in_array($role, $route['required_approvers'], true)) {
            return false;
        }

        // For sequential approval (manager_and_admin), Office Admin must wait for HR Manager
        if ($route['route'] === 'manager_and_admin') {
            if ($role === 'Office Admin' && !$leaveRequest->manager_approved_at) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if leave request falls within a blackout period
     */
    public function isInBlackoutPeriod(LeaveRequest $leaveRequest): bool
    {
        return LeaveBlackoutPeriod::where('department_id', $leaveRequest->employee->department_id)
            ->where(function ($query) use ($leaveRequest) {
                $query->whereBetween('start_date', [$leaveRequest->start_date, $leaveRequest->end_date])
                    ->orWhereBetween('end_date', [$leaveRequest->start_date, $leaveRequest->end_date])
                    ->orWhere(function ($q) use ($leaveRequest) {
                        $q->where('start_date', '<=', $leaveRequest->start_date)
                          ->where('end_date', '>=', $leaveRequest->end_date);
                    });
            })
            ->exists();
    }

    /**
     * Calculate duration in days (inclusive of both start and end date)
     *
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     */
    public function calculateDuration($startDate, $endDate): int
    {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end   = $endDate   instanceof Carbon ? $endDate   : Carbon::parse($endDate);

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * Check if leave request meets the minimum advance-notice requirement
     *
     * @param string|Carbon $startDate
     */
    protected function meetsAdvanceNotice($startDate): bool
    {
        $minDays = $this->getMinAdvanceNoticeDays();
        $start   = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);

        return Carbon::now()->diffInDays($start, false) >= $minDays;
    }

    protected function isAutoApprovalEnabled(): bool
    {
        return SystemSetting::getValue('leave_auto_approval_enabled', true);
    }

    protected function getMinAdvanceNoticeDays(): int
    {
        return SystemSetting::getValue('leave_min_advance_notice_days', 3);
    }

    protected function getApprovalRoutingConfig(): array
    {
        return SystemSetting::getValue('leave_approval_routing', [
            'short_leave'  => ['min' => 1, 'max' => 2, 'approvers' => []],
            'medium_leave' => ['min' => 3, 'max' => 5, 'approvers' => ['HR Manager']],
            'long_leave'   => ['min' => 6, 'max' => null, 'approvers' => ['HR Manager', 'Office Admin']],
        ]);
    }

    /**
     * Process auto-approval for a leave request
     */
    public function processAutoApproval(LeaveRequest $leaveRequest): bool
    {
        if (!$this->canAutoApprove($leaveRequest)) {
            return false;
        }

        DB::transaction(function () use ($leaveRequest) {
            $coverage = $this->coverageService->calculateCoverageWithLeave(
                $leaveRequest->employee->department_id,
                $leaveRequest->start_date,
                $leaveRequest->end_date,
                $leaveRequest->employee_id,
                $leaveRequest->days_requested
            );

            $leaveRequest->update([
                'status'              => 'approved',
                'auto_approved'       => true,
                'coverage_percentage' => $coverage,
                'manager_approved_at' => now(),
            ]);

            // Use days_requested (including 0.5 for half-days) instead of calendar days
            $this->balanceService->deductBalance(
                $leaveRequest->employee_id,
                $leaveRequest->leave_policy_id,
                $leaveRequest->days_requested
            );
        });

        return true;
    }
}