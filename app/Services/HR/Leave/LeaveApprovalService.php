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
     * 
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function canAutoApprove(LeaveRequest $leaveRequest): bool
    {
        // Check if auto-approval is enabled
        if (!$this->isAutoApprovalEnabled()) {
            return false;
        }

        // Check duration (1-2 days only)
        $duration = $this->calculateDuration($leaveRequest->start_date, $leaveRequest->end_date);
        if ($duration < 1 || $duration > 2) {
            return false;
        }

        // Check sufficient balance
        if (!$this->balanceService->hasSufficientBalance(
            $leaveRequest->employee_id,
            $leaveRequest->leave_policy_id,
            $duration
        )) {
            return false;
        }

        // Check workforce coverage
        if (!$this->checkWorkforceCoverage($leaveRequest)) {
            return false;
        }

        // Check advance notice
        if (!$this->meetsAdvanceNotice($leaveRequest->start_date)) {
            return false;
        }

        // Check blackout period
        if ($this->isInBlackoutPeriod($leaveRequest)) {
            return false;
        }

        return true;
    }

    /**
     * Check if department coverage meets minimum threshold
     * 
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function checkWorkforceCoverage(LeaveRequest $leaveRequest): bool
    {
        $employee = $leaveRequest->employee;
        $department = $employee->department;

        if (!$department) {
            return false;
        }

        // Get coverage percentage if this leave is granted
        $coverage = $this->coverageService->calculateCoverageWithLeave(
            $department->id,
            $leaveRequest->start_date,
            $leaveRequest->end_date,
            $employee->id
        );

        // Check against department minimum or default 75%
        $minCoverage = $department->min_coverage_percentage ?? 75.00;

        return $coverage >= $minCoverage;
    }

    /**
     * Determine approval route based on duration and requestor role
     * 
     * Rules:
     * - 1-2 days: Auto-approve (if criteria met)
     * - 3-5 days: HR Manager approval required
     * - 6+ days: HR Manager + Office Admin approval required
     * - If requestor is HR Manager: escalate to Office Admin
     * 
     * @param LeaveRequest $leaveRequest
     * @return array{route: string, required_approvers: array}
     */
    public function determineApprovalRoute(LeaveRequest $leaveRequest): array
    {
        $duration = $this->calculateDuration($leaveRequest->start_date, $leaveRequest->end_date);
        $employee = $leaveRequest->employee;
        
        // Check if requestor is HR Manager
        $isHRManager = $employee->user?->hasRole('HR Manager');

        // Get routing configuration
        $routingConfig = $this->getApprovalRoutingConfig();

        // Determine route based on duration
        if ($duration >= 1 && $duration <= 2) {
            // Auto-approve if possible
            if ($this->canAutoApprove($leaveRequest)) {
                return [
                    'route' => 'auto',
                    'required_approvers' => []
                ];
            }
            
            // Otherwise, HR Manager approval needed
            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager']
            ];
        } elseif ($duration >= 3 && $duration <= 5) {
            // HR Manager approval
            if ($isHRManager) {
                // Self-approval prevention: escalate to Office Admin
                return [
                    'route' => 'admin',
                    'required_approvers' => ['Office Admin']
                ];
            }
            
            return [
                'route' => 'manager',
                'required_approvers' => ['HR Manager']
            ];
        } else {
            // 6+ days: HR Manager + Office Admin
            if ($isHRManager) {
                // Skip HR Manager, go straight to Office Admin
                return [
                    'route' => 'admin',
                    'required_approvers' => ['Office Admin']
                ];
            }
            
            return [
                'route' => 'manager_and_admin',
                'required_approvers' => ['HR Manager', 'Office Admin']
            ];
        }
    }

    /**
     * Check if user can approve the leave request
     * 
     * Prevents self-approval and validates role-based permissions
     * 
     * @param LeaveRequest $leaveRequest
     * @param int $userId
     * @param string $role (e.g., 'HR Manager', 'Office Admin')
     * @return bool
     */
    public function canUserApprove(LeaveRequest $leaveRequest, int $userId, string $role): bool
    {
        // Prevent self-approval
        if ($leaveRequest->employee->user_id === $userId) {
            return false;
        }

        // Get approval route
        $route = $this->determineApprovalRoute($leaveRequest);

        // Check if role is required for this route
        if (!in_array($role, $route['required_approvers'])) {
            return false;
        }

        // If route requires both manager and admin, check approval sequence
        if ($route['route'] === 'manager_and_admin') {
            // Office Admin can only approve after HR Manager
            if ($role === 'Office Admin' && !$leaveRequest->manager_approved_at) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if leave request falls within blackout period
     * 
     * @param LeaveRequest $leaveRequest
     * @return bool
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
     * Calculate duration in days between two dates
     * 
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @return int
     */
    public function calculateDuration($startDate, $endDate): int
    {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return $start->diffInDays($end) + 1;
    }

    /**
     * Check if leave request meets minimum advance notice requirement
     * 
     * @param string|Carbon $startDate
     * @return bool
     */
    protected function meetsAdvanceNotice($startDate): bool
    {
        $minDays = $this->getMinAdvanceNoticeDays();
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        
        return Carbon::now()->diffInDays($start, false) >= $minDays;
    }

    /**
     * Get auto-approval enabled setting
     * 
     * @return bool
     */
    protected function isAutoApprovalEnabled(): bool
    {
        return SystemSetting::getValue('leave_auto_approval_enabled', true);
    }

    /**
     * Get minimum advance notice days setting
     * 
     * @return int
     */
    protected function getMinAdvanceNoticeDays(): int
    {
        return SystemSetting::getValue('leave_min_advance_notice_days', 3);
    }

    /**
     * Get approval routing configuration
     * 
     * @return array
     */
    protected function getApprovalRoutingConfig(): array
    {
        return SystemSetting::getValue('leave_approval_routing', [
            'short_leave' => ['min' => 1, 'max' => 2, 'approvers' => []],
            'medium_leave' => ['min' => 3, 'max' => 5, 'approvers' => ['HR Manager']],
            'long_leave' => ['min' => 6, 'max' => null, 'approvers' => ['HR Manager', 'Office Admin']]
        ]);
    }

    /**
     * Process auto-approval for leave request
     * 
     * @param LeaveRequest $leaveRequest
     * @return bool
     */
    public function processAutoApproval(LeaveRequest $leaveRequest): bool
    {
        if (!$this->canAutoApprove($leaveRequest)) {
            return false;
        }

        DB::transaction(function () use ($leaveRequest) {
            // Calculate coverage percentage
            $coverage = $this->coverageService->calculateCoverageWithLeave(
                $leaveRequest->employee->department_id,
                $leaveRequest->start_date,
                $leaveRequest->end_date,
                $leaveRequest->employee_id
            );

            // Update leave request
            $leaveRequest->update([
                'status' => 'approved',
                'auto_approved' => true,
                'coverage_percentage' => $coverage,
                'manager_approved_at' => now(),
            ]);

            // Deduct balance
            $duration = $this->calculateDuration($leaveRequest->start_date, $leaveRequest->end_date);
            $this->balanceService->deductBalance(
                $leaveRequest->employee_id,
                $leaveRequest->leave_policy_id,
                $duration
            );
        });

        return true;
    }
}
