<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\LeavePolicy;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    /**
     * Display the Office Admin Dashboard.
     * 
     * Shows setup completion checklist, quick stats, and recent configuration changes.
     * Office Admins manage company setup, business rules, and system configuration.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get setup completion status for 7 major configuration areas
        $setupStatus = $this->getSetupCompletionStatus();
        
        // Get quick statistics
        $quickStats = $this->getQuickStatistics();
        
        // Get recent configuration changes (last 10 from activity_log)
        $recentChanges = $this->getRecentConfigurationChanges();
        
        // Determine user role
        $userRole = $user->hasRole('Superadmin') ? 'Superadmin' : 'Office Admin';

        return Inertia::render('Admin/Dashboard', [
            'setupStatus' => $setupStatus,
            'quickStats' => $quickStats,
            'recentChanges' => $recentChanges,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Calculate setup completion status for all 7 configuration areas.
     * 
     * @return array
     */
    private function getSetupCompletionStatus(): array
    {
        $steps = [
            [
                'id' => 'company_setup',
                'title' => 'Company Information',
                'description' => 'Configure company details, tax info, and government numbers',
                'route' => route('admin.company.index'),
                'completed' => $this->isCompanySetupComplete(),
                'priority' => 'high',
            ],
            [
                'id' => 'business_rules',
                'title' => 'Business Rules',
                'description' => 'Define working hours, holidays, overtime, and attendance policies',
                'route' => route('admin.business-rules.index'),
                'completed' => $this->isBusinessRulesComplete(),
                'priority' => 'high',
            ],
            [
                'id' => 'departments',
                'title' => 'Departments & Positions',
                'description' => 'Set up organizational structure and job positions',
                'route' => route('admin.departments.index'),
                'completed' => $this->isDepartmentsComplete(),
                'priority' => 'medium',
            ],
            [
                'id' => 'leave_policies',
                'title' => 'Leave Policies',
                'description' => 'Configure leave types, accrual methods, and approval rules',
                'route' => route('admin.leave-policies.index'),
                'completed' => $this->isLeavePoliciesComplete(),
                'priority' => 'medium',
            ],
            [
                'id' => 'payroll_rules',
                'title' => 'Payroll Rules',
                'description' => 'Set up salary structures, deductions, and government rates',
                'route' => route('admin.payroll-rules.index'),
                'completed' => $this->isPayrollRulesComplete(),
                'priority' => 'medium',
            ],
            [
                'id' => 'system_config',
                'title' => 'System Configuration',
                'description' => 'Configure payment methods, notifications, and integrations',
                'route' => route('admin.system-config.index'),
                'completed' => $this->isSystemConfigComplete(),
                'priority' => 'low',
            ],
            [
                'id' => 'approval_workflows',
                'title' => 'Approval Workflows',
                'description' => 'Set up approval chains for leave, overtime, and expenses',
                'route' => route('admin.approval-workflows.index'),
                'completed' => $this->isApprovalWorkflowsComplete(),
                'priority' => 'low',
            ],
        ];

        $completedCount = collect($steps)->where('completed', true)->count();
        $totalSteps = count($steps);
        $completionPercentage = $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0;

        return [
            'steps' => $steps,
            'completedCount' => $completedCount,
            'totalSteps' => $totalSteps,
            'completionPercentage' => $completionPercentage,
            'isComplete' => $completedCount === $totalSteps,
        ];
    }

    /**
     * Get quick statistics for dashboard overview.
     * 
     * @return array
     */
    private function getQuickStatistics(): array
    {
        return [
            'departments' => [
                'count' => Department::where('is_active', true)->count(),
                'label' => 'Active Departments',
                'icon' => 'building',
                'route' => route('admin.departments.index'),
            ],
            'positions' => [
                'count' => Position::where('is_active', true)->count(),
                'label' => 'Active Positions',
                'icon' => 'briefcase',
                'route' => route('admin.positions.index'),
            ],
            'leavePolicies' => [
                'count' => LeavePolicy::count(),
                'label' => 'Leave Types Configured',
                'icon' => 'calendar',
                'route' => route('admin.leave-policies.index'),
            ],
            'configurationChanges' => [
                'count' => Activity::where('subject_type', SystemSetting::class)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'label' => 'Changes This Week',
                'icon' => 'activity',
                'route' => null, // Will link to audit logs when implemented
            ],
        ];
    }

    /**
     * Get recent configuration changes from activity log.
     * 
     * @return array
     */
    private function getRecentConfigurationChanges(): array
    {
        // Get recent activities from Spatie Activity Log
        $activities = Activity::query()
            ->where('log_name', 'default')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'subject_type' => class_basename($activity->subject_type ?? 'Unknown'),
                    'subject_id' => $activity->subject_id,
                    'causer_name' => $activity->causer?->name ?? 'System',
                    'causer_email' => $activity->causer?->email ?? null,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'relative_time' => $activity->created_at->diffForHumans(),
                ];
            });

        return $activities->toArray();
    }

    /**
     * Check if company setup is complete.
     * 
     * @return bool
     */
    private function isCompanySetupComplete(): bool
    {
        $requiredSettings = [
            'company.name',
            'company.address',
            'company.tin',
            'company.sss_number',
            'company.philhealth_number',
            'company.pagibig_number',
        ];

        foreach ($requiredSettings as $key) {
            $setting = SystemSetting::where('key', $key)->first();
            if (!$setting || empty($setting->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if business rules are complete.
     * 
     * @return bool
     */
    private function isBusinessRulesComplete(): bool
    {
        $requiredSettings = [
            'business_rules.working_hours_start',
            'business_rules.working_hours_end',
            'business_rules.overtime_rate_regular',
            'business_rules.grace_period_minutes',
        ];

        foreach ($requiredSettings as $key) {
            $setting = SystemSetting::where('key', $key)->first();
            if (!$setting || empty($setting->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if departments are configured.
     * 
     * @return bool
     */
    private function isDepartmentsComplete(): bool
    {
        return Department::where('is_active', true)->count() >= 1 &&
               Position::where('is_active', true)->count() >= 1;
    }

    /**
     * Check if leave policies are configured.
     * 
     * @return bool
     */
    private function isLeavePoliciesComplete(): bool
    {
        // Check for at least 3 basic leave types (Vacation, Sick, Emergency)
        return LeavePolicy::count() >= 3;
    }

    /**
     * Check if payroll rules are configured.
     * 
     * @return bool
     */
    private function isPayrollRulesComplete(): bool
    {
        $requiredSettings = [
            'payroll.sss_contribution_rate',
            'payroll.philhealth_contribution_rate',
            'payroll.pagibig_contribution_rate',
        ];

        foreach ($requiredSettings as $key) {
            $setting = SystemSetting::where('key', $key)->first();
            if (!$setting || empty($setting->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if system configuration is complete.
     * 
     * @return bool
     */
    private function isSystemConfigComplete(): bool
    {
        $requiredSettings = [
            'system.payment_schedule', // bi-monthly, monthly, weekly
            'system.notification_email_enabled',
        ];

        foreach ($requiredSettings as $key) {
            $setting = SystemSetting::where('key', $key)->first();
            if (!$setting || empty($setting->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if approval workflows are configured.
     * 
     * @return bool
     */
    private function isApprovalWorkflowsComplete(): bool
    {
        // Check if leave approval rules are configured
        $leaveApprovalRules = SystemSetting::where('key', 'LIKE', 'approval.leave.%')->count();
        
        return $leaveApprovalRules >= 1;
    }
}
