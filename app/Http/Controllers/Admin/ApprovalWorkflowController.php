<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class ApprovalWorkflowController extends Controller
{
    /**
     * Display approval workflow configuration page.
     * 
     * Shows all workflow types: Leave, Hiring, Payroll, Expense.
     */
    public function index(Request $request): Response
    {
        // Get all workflow settings
        $settings = SystemSetting::where('category', 'workflow')
            ->get()
            ->pluck('value', 'key');

        $workflows = [
            'leave' => $this->getLeaveWorkflowConfig($settings),
            'hiring' => $this->getHiringWorkflowConfig($settings),
            'payroll' => $this->getPayrollWorkflowConfig($settings),
            'expense' => $this->getExpenseWorkflowConfig($settings),
            'overtime' => $this->getOvertimeWorkflowConfig($settings),
        ];

        return Inertia::render('Admin/ApprovalWorkflows/Index', [
            'workflows' => $workflows,
        ]);
    }

    /**
     * Show leave approval workflow configuration page.
     */
    public function configureLeaveWorkflow(Request $request): Response
    {
        $settings = SystemSetting::where('category', 'workflow')
            ->where('key', 'like', 'workflow.leave.%')
            ->get()
            ->pluck('value', 'key');

        $leaveWorkflow = $this->getLeaveWorkflowConfig($settings);

        return Inertia::render('Admin/ApprovalWorkflows/LeaveWorkflow', [
            'workflow' => $leaveWorkflow,
        ]);
    }

    /**
     * Update leave approval workflow configuration.
     */
    public function updateLeaveWorkflow(Request $request)
    {
        $validated = $request->validate([
            // Duration-based rules
            'duration_tier1_days' => 'required|integer|min:1|max:30',
            'duration_tier2_days' => 'required|integer|min:1|max:90',
            'auto_approve_max_days' => 'required|integer|min:0|max:10',
            
            // Workforce coverage rules
            'coverage_minimum_percent' => 'required|numeric|min:0|max:100',
            'coverage_warning_percent' => 'required|numeric|min:0|max:100',
            'coverage_critical_percent' => 'required|numeric|min:0|max:100',
            'coverage_block_enabled' => 'boolean',
            
            // Advance notice rules
            'advance_notice_days' => 'required|integer|min:0|max:30',
            'short_notice_days' => 'required|integer|min:0|max:10',
            'emergency_exemption' => 'boolean',
            
            // Leave type specific rules
            'vacation_manager_days' => 'required|integer|min:1|max:30',
            'sick_manager_days' => 'required|integer|min:1|max:30',
            'emergency_always_staff' => 'boolean',
            'unpaid_requires_manager' => 'boolean',
            'maternity_requires_manager' => 'boolean',
            'loa_requires_admin' => 'boolean',
            
            // Balance threshold rules
            'balance_threshold_days' => 'required|integer|min:0|max:10',
            'balance_warning_days' => 'required|integer|min:0|max:10',
            'balance_block_enabled' => 'boolean',
            
            // Blackout period rules
            'blackout_enabled' => 'boolean',
            'blackout_periods' => 'nullable|array',
            'blackout_periods.*.start_date' => 'required|date',
            'blackout_periods.*.end_date' => 'required|date|after_or_equal:blackout_periods.*.start_date',
            'blackout_periods.*.reason' => 'required|string|max:255',
            'blackout_action' => 'required|in:require_manager,block_all,warning_only',
            
            // Frequency rules
            'frequency_enabled' => 'boolean',
            'frequency_max_requests' => 'required|integer|min:1|max:20',
            'frequency_period_days' => 'required|integer|min:7|max:365',
        ]);

        // Validate coverage percentages
        if ($validated['coverage_warning_percent'] < $validated['coverage_minimum_percent']) {
            return back()->withErrors(['coverage_warning_percent' => 'Warning threshold must be greater than or equal to minimum coverage.']);
        }

        $settings = [
            // Duration-based
            'workflow.leave.duration.tier1_days' => $validated['duration_tier1_days'],
            'workflow.leave.duration.tier2_days' => $validated['duration_tier2_days'],
            'workflow.leave.duration.auto_approve_max' => $validated['auto_approve_max_days'],
            
            // Coverage
            'workflow.leave.coverage.minimum_percent' => $validated['coverage_minimum_percent'],
            'workflow.leave.coverage.warning_percent' => $validated['coverage_warning_percent'],
            'workflow.leave.coverage.critical_percent' => $validated['coverage_critical_percent'],
            'workflow.leave.coverage.block_enabled' => $validated['coverage_block_enabled'] ?? false,
            
            // Advance notice
            'workflow.leave.advance_notice.days' => $validated['advance_notice_days'],
            'workflow.leave.advance_notice.short_notice_days' => $validated['short_notice_days'],
            'workflow.leave.advance_notice.emergency_exemption' => $validated['emergency_exemption'] ?? false,
            
            // Leave type specific
            'workflow.leave.leave_type.vacation_manager_days' => $validated['vacation_manager_days'],
            'workflow.leave.leave_type.sick_manager_days' => $validated['sick_manager_days'],
            'workflow.leave.leave_type.emergency_always_staff' => $validated['emergency_always_staff'] ?? false,
            'workflow.leave.leave_type.unpaid_requires_manager' => $validated['unpaid_requires_manager'] ?? true,
            'workflow.leave.leave_type.maternity_requires_manager' => $validated['maternity_requires_manager'] ?? true,
            'workflow.leave.leave_type.loa_requires_admin' => $validated['loa_requires_admin'] ?? true,
            
            // Balance threshold
            'workflow.leave.balance.threshold_days' => $validated['balance_threshold_days'],
            'workflow.leave.balance.warning_days' => $validated['balance_warning_days'],
            'workflow.leave.balance.block_enabled' => $validated['balance_block_enabled'] ?? false,
            
            // Blackout periods
            'workflow.leave.blackout.enabled' => $validated['blackout_enabled'] ?? false,
            'workflow.leave.blackout.action' => $validated['blackout_action'],
            
            // Frequency
            'workflow.leave.frequency.enabled' => $validated['frequency_enabled'] ?? false,
            'workflow.leave.frequency.max_requests' => $validated['frequency_max_requests'],
            'workflow.leave.frequency.period_days' => $validated['frequency_period_days'],
        ];

        // Store blackout periods as JSON
        if (!empty($validated['blackout_periods'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'workflow.leave.blackout.periods'],
                [
                    'value' => json_encode($validated['blackout_periods']),
                    'type' => 'json',
                    'category' => 'workflow',
                    'description' => 'Leave blackout periods',
                ]
            );
        }

        $this->updateSettings($settings, $request->user());

        return redirect()->route('admin.approval-workflows.index')
            ->with('success', 'Leave approval workflow updated successfully.');
    }

    /**
     * Show overtime approval workflow configuration page.
     */
    public function configureOvertimeWorkflow(Request $request): Response
    {
        $settings = SystemSetting::where('category', 'workflow')
            ->where('key', 'like', 'workflow.overtime.%')
            ->get()
            ->pluck('value', 'key');

        $overtimeWorkflow = $this->getOvertimeWorkflowConfig($settings);

        return Inertia::render('Admin/ApprovalWorkflows/OvertimeWorkflow', [
            'workflow' => $overtimeWorkflow,
        ]);
    }

    /**
     * Update overtime approval workflow configuration.
     */
    public function updateOvertimeWorkflow(Request $request)
    {
        $validated = $request->validate([
            'auto_approve_max_hours' => 'required|numeric|min:0|max:8',
            'manager_approval_max_hours' => 'required|numeric|min:0|max:12',
            'admin_approval_required_hours' => 'required|numeric|min:0|max:24',
            'advance_request_hours' => 'required|integer|min:0|max:72',
            'post_facto_enabled' => 'boolean',
            'post_facto_requires_manager' => 'boolean',
        ]);

        $settings = [
            'workflow.overtime.auto_approve_max_hours' => $validated['auto_approve_max_hours'],
            'workflow.overtime.manager_approval_max_hours' => $validated['manager_approval_max_hours'],
            'workflow.overtime.admin_approval_required_hours' => $validated['admin_approval_required_hours'],
            'workflow.overtime.advance_request_hours' => $validated['advance_request_hours'],
            'workflow.overtime.post_facto_enabled' => $validated['post_facto_enabled'] ?? false,
            'workflow.overtime.post_facto_requires_manager' => $validated['post_facto_requires_manager'] ?? true,
        ];

        $this->updateSettings($settings, $request->user());

        return redirect()->route('admin.approval-workflows.index')
            ->with('success', 'Overtime approval workflow updated successfully.');
    }

    /**
     * Test leave approval workflow with sample data.
     */
    public function testLeaveWorkflow(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string',
            'duration_days' => 'required|integer|min:1|max:90',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $department = $employee->department;

        // Get workflow configuration
        $settings = SystemSetting::where('category', 'workflow')
            ->where('key', 'like', 'workflow.leave.%')
            ->get()
            ->pluck('value', 'key');

        $workflow = $this->getLeaveWorkflowConfig($settings);
        
        // Simulate approval routing
        $result = $this->simulateLeaveApproval(
            $employee,
            $validated['leave_type'],
            $validated['duration_days'],
            Carbon::parse($validated['start_date']),
            Carbon::parse($validated['end_date']),
            $workflow
        );

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    /**
     * Simulate leave approval routing based on workflow rules.
     */
    private function simulateLeaveApproval(Employee $employee, string $leaveType, int $durationDays, Carbon $startDate, Carbon $endDate, array $workflow): array
    {
        $result = [
            'employee' => $employee->name,
            'leave_type' => $leaveType,
            'duration_days' => $durationDays,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'approval_path' => [],
            'warnings' => [],
            'blockers' => [],
            'final_status' => 'pending',
        ];

        // Check auto-approval
        if ($durationDays <= $workflow['duration']['auto_approve_max']) {
            $result['approval_path'][] = 'Auto-approved (≤ ' . $workflow['duration']['auto_approve_max'] . ' days)';
            $result['final_status'] = 'auto_approved';
            return $result;
        }

        // Check advance notice
        $daysUntilStart = Carbon::now()->diffInDays($startDate, false);
        if ($daysUntilStart < $workflow['advance_notice']['days']) {
            if ($daysUntilStart < $workflow['advance_notice']['short_notice_days']) {
                if ($workflow['advance_notice']['emergency_exemption'] && $leaveType === 'Emergency') {
                    $result['warnings'][] = 'Emergency leave exemption applied';
                } else {
                    $result['blockers'][] = 'Insufficient advance notice (' . $daysUntilStart . ' days, required: ' . $workflow['advance_notice']['days'] . ' days)';
                }
            } else {
                $result['warnings'][] = 'Short notice request (requires manager approval)';
            }
        }

        // Determine approval path based on duration
        if ($durationDays <= $workflow['duration']['tier1_days']) {
            $result['approval_path'][] = 'Step 1: HR Staff approval required';
        } elseif ($durationDays <= $workflow['duration']['tier2_days']) {
            $result['approval_path'][] = 'Step 1: HR Staff approval required';
            $result['approval_path'][] = 'Step 2: HR Manager approval required';
        } else {
            $result['approval_path'][] = 'Step 1: HR Staff approval required';
            $result['approval_path'][] = 'Step 2: HR Manager approval required';
            $result['approval_path'][] = 'Step 3: Office Admin approval required';
        }

        // Check blackout periods
        if ($workflow['blackout']['enabled']) {
            $blackoutPeriods = json_decode($workflow['blackout']['periods'] ?? '[]', true);
            foreach ($blackoutPeriods as $period) {
                $blackoutStart = Carbon::parse($period['start_date']);
                $blackoutEnd = Carbon::parse($period['end_date']);
                
                if ($startDate->between($blackoutStart, $blackoutEnd) || $endDate->between($blackoutStart, $blackoutEnd)) {
                    if ($workflow['blackout']['action'] === 'block_all') {
                        $result['blockers'][] = 'Leave falls within blackout period: ' . $period['reason'];
                    } elseif ($workflow['blackout']['action'] === 'require_manager') {
                        $result['warnings'][] = 'Leave falls within blackout period (requires manager approval): ' . $period['reason'];
                    } else {
                        $result['warnings'][] = 'Leave falls within blackout period: ' . $period['reason'];
                    }
                }
            }
        }

        // Check frequency rules
        if ($workflow['frequency']['enabled']) {
            // Simulate checking recent leave requests
            $recentRequestsCount = 2; // Simulated count
            if ($recentRequestsCount >= $workflow['frequency']['max_requests']) {
                $result['warnings'][] = 'Frequent leave requests detected (' . $recentRequestsCount . ' in last ' . $workflow['frequency']['period_days'] . ' days)';
            }
        }

        // Check workforce coverage (simulated)
        $coveragePercent = 78; // Simulated coverage
        if ($coveragePercent < $workflow['coverage']['minimum_percent']) {
            if ($workflow['coverage']['block_enabled']) {
                $result['blockers'][] = 'Insufficient workforce coverage (' . $coveragePercent . '%, minimum: ' . $workflow['coverage']['minimum_percent'] . '%)';
            } else {
                $result['warnings'][] = 'Low workforce coverage (' . $coveragePercent . '%, minimum: ' . $workflow['coverage']['minimum_percent'] . '%)';
            }
        } elseif ($coveragePercent < $workflow['coverage']['warning_percent']) {
            $result['warnings'][] = 'Coverage approaching threshold (' . $coveragePercent . '%, target: ' . $workflow['coverage']['warning_percent'] . '%)';
        }

        // Determine final status
        if (count($result['blockers']) > 0) {
            $result['final_status'] = 'blocked';
        } elseif (count($result['warnings']) > 0) {
            $result['final_status'] = 'requires_review';
        } else {
            $result['final_status'] = 'approved';
        }

        return $result;
    }

    /**
     * Get leave workflow configuration from settings.
     */
    private function getLeaveWorkflowConfig($settings): array
    {
        return [
            'duration' => [
                'tier1_days' => (int)($settings['workflow.leave.duration.tier1_days'] ?? 5),
                'tier2_days' => (int)($settings['workflow.leave.duration.tier2_days'] ?? 15),
                'auto_approve_max' => (int)($settings['workflow.leave.duration.auto_approve_max'] ?? 2),
            ],
            'coverage' => [
                'minimum_percent' => (float)($settings['workflow.leave.coverage.minimum_percent'] ?? 75),
                'warning_percent' => (float)($settings['workflow.leave.coverage.warning_percent'] ?? 80),
                'critical_percent' => (float)($settings['workflow.leave.coverage.critical_percent'] ?? 50),
                'block_enabled' => (bool)($settings['workflow.leave.coverage.block_enabled'] ?? false),
            ],
            'advance_notice' => [
                'days' => (int)($settings['workflow.leave.advance_notice.days'] ?? 3),
                'short_notice_days' => (int)($settings['workflow.leave.advance_notice.short_notice_days'] ?? 2),
                'emergency_exemption' => (bool)($settings['workflow.leave.advance_notice.emergency_exemption'] ?? true),
            ],
            'leave_type' => [
                'vacation_manager_days' => (int)($settings['workflow.leave.leave_type.vacation_manager_days'] ?? 5),
                'sick_manager_days' => (int)($settings['workflow.leave.leave_type.sick_manager_days'] ?? 5),
                'emergency_always_staff' => (bool)($settings['workflow.leave.leave_type.emergency_always_staff'] ?? true),
                'unpaid_requires_manager' => (bool)($settings['workflow.leave.leave_type.unpaid_requires_manager'] ?? true),
                'maternity_requires_manager' => (bool)($settings['workflow.leave.leave_type.maternity_requires_manager'] ?? true),
                'loa_requires_admin' => (bool)($settings['workflow.leave.leave_type.loa_requires_admin'] ?? true),
            ],
            'balance' => [
                'threshold_days' => (int)($settings['workflow.leave.balance.threshold_days'] ?? 3),
                'warning_days' => (int)($settings['workflow.leave.balance.warning_days'] ?? 5),
                'block_enabled' => (bool)($settings['workflow.leave.balance.block_enabled'] ?? false),
            ],
            'blackout' => [
                'enabled' => (bool)($settings['workflow.leave.blackout.enabled'] ?? false),
                'periods' => $settings['workflow.leave.blackout.periods'] ?? '[]',
                'action' => $settings['workflow.leave.blackout.action'] ?? 'require_manager',
            ],
            'frequency' => [
                'enabled' => (bool)($settings['workflow.leave.frequency.enabled'] ?? false),
                'max_requests' => (int)($settings['workflow.leave.frequency.max_requests'] ?? 3),
                'period_days' => (int)($settings['workflow.leave.frequency.period_days'] ?? 30),
            ],
        ];
    }

    /**
     * Get overtime workflow configuration from settings.
     */
    private function getOvertimeWorkflowConfig($settings): array
    {
        return [
            'auto_approve_max_hours' => (float)($settings['workflow.overtime.auto_approve_max_hours'] ?? 2),
            'manager_approval_max_hours' => (float)($settings['workflow.overtime.manager_approval_max_hours'] ?? 4),
            'admin_approval_required_hours' => (float)($settings['workflow.overtime.admin_approval_required_hours'] ?? 8),
            'advance_request_hours' => (int)($settings['workflow.overtime.advance_request_hours'] ?? 24),
            'post_facto_enabled' => (bool)($settings['workflow.overtime.post_facto_enabled'] ?? false),
            'post_facto_requires_manager' => (bool)($settings['workflow.overtime.post_facto_requires_manager'] ?? true),
        ];
    }

    /**
     * Get hiring workflow configuration from settings.
     */
    private function getHiringWorkflowConfig($settings): array
    {
        return [
            'enabled' => (bool)($settings['workflow.hiring.enabled'] ?? true),
            'steps' => [
                ['step' => 1, 'role' => 'HR Staff', 'action' => 'Screen applications'],
                ['step' => 2, 'role' => 'HR Manager', 'action' => 'Interview and recommend'],
                ['step' => 3, 'role' => 'Office Admin', 'action' => 'Final hiring approval'],
                ['step' => 4, 'role' => 'HR Staff', 'action' => 'Process onboarding'],
            ],
        ];
    }

    /**
     * Get payroll workflow configuration from settings.
     */
    private function getPayrollWorkflowConfig($settings): array
    {
        return [
            'enabled' => (bool)($settings['workflow.payroll.enabled'] ?? true),
            'steps' => [
                ['step' => 1, 'role' => 'Payroll Officer', 'action' => 'Calculate payroll'],
                ['step' => 2, 'role' => 'HR Manager', 'action' => 'Review calculations'],
                ['step' => 3, 'role' => 'Office Admin', 'action' => 'Final approval'],
                ['step' => 4, 'role' => 'Payroll Officer', 'action' => 'Distribute payment'],
            ],
        ];
    }

    /**
     * Get expense workflow configuration from settings.
     */
    private function getExpenseWorkflowConfig($settings): array
    {
        return [
            'enabled' => (bool)($settings['workflow.expense.enabled'] ?? false),
            'threshold_amount' => (float)($settings['workflow.expense.threshold_amount'] ?? 10000),
            'steps' => [
                ['step' => 1, 'role' => 'Employee', 'action' => 'Submit expense (via HR Staff)'],
                ['step' => 2, 'role' => 'Department Head', 'action' => 'Approve expense'],
                ['step' => 3, 'role' => 'Accounting', 'action' => 'Review and validate'],
                ['step' => 4, 'role' => 'Office Admin', 'action' => 'Approve if above threshold'],
            ],
        ];
    }

    /**
     * Helper method to update multiple settings at once.
     */
    private function updateSettings(array $settings, $user): void
    {
        foreach ($settings as $key => $value) {
            $setting = SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                    'category' => 'workflow',
                    'description' => $this->getSettingDescription($key),
                ]
            );

            activity('workflow_configuration')
                ->causedBy($user)
                ->performedOn($setting)
                ->withProperties([
                    'key' => $key,
                    'old_value' => $setting->getOriginal('value'),
                    'new_value' => $value,
                ])
                ->log('Updated workflow configuration: ' . $key);
        }
    }

    /**
     * Get human-readable description for setting key.
     */
    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            // Leave workflow
            'workflow.leave.duration.tier1_days' => 'Days requiring HR Manager approval',
            'workflow.leave.duration.tier2_days' => 'Days requiring Office Admin approval',
            'workflow.leave.duration.auto_approve_max' => 'Maximum days for auto-approval',
            'workflow.leave.coverage.minimum_percent' => 'Minimum department coverage percentage',
            'workflow.leave.advance_notice.days' => 'Standard advance notice days',
            
            // Overtime workflow
            'workflow.overtime.auto_approve_max_hours' => 'Maximum hours for auto-approval',
            'workflow.overtime.manager_approval_max_hours' => 'Maximum hours for manager approval',
            'workflow.overtime.admin_approval_required_hours' => 'Hours requiring admin approval',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
