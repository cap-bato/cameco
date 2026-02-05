<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyController extends Controller
{
    /**
     * Display leave policies configuration page.
     * 
     * Shows all leave types and their configuration.
     * Office Admin can create, edit, and configure leave policies.
     */
    public function index(Request $request): Response
    {
        // Get all leave policies
        $policies = LeavePolicy::query()
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get()
            ->map(function ($policy) {
                return [
                    'id' => $policy->id,
                    'code' => $policy->code,
                    'name' => $policy->name,
                    'description' => $policy->description,
                    'annual_entitlement' => (float) $policy->annual_entitlement,
                    'max_carryover' => (float) $policy->max_carryover,
                    'can_carry_forward' => (bool) $policy->can_carry_forward,
                    'is_paid' => (bool) $policy->is_paid,
                    'is_active' => (bool) $policy->is_active,
                    'effective_date' => $policy->effective_date?->format('Y-m-d'),
                    'created_at' => $policy->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Get approval rules configuration from system settings
        $approvalRules = $this->getApprovalRulesConfiguration();

        return Inertia::render('Admin/LeavePolicies/Index', [
            'policies' => $policies,
            'approvalRules' => $approvalRules,
        ]);
    }

    /**
     * Store a new leave policy.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:leave_policies,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'annual_entitlement' => 'required|numeric|min:0|max:365',
            'max_carryover' => 'required|numeric|min:0|max:365',
            'can_carry_forward' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

        $policy = LeavePolicy::create($validated);

        // Log the change
        activity('leave_policy_configuration')
            ->causedBy($request->user())
            ->performedOn($policy)
            ->withProperties([
                'policy_name' => $policy->name,
                'policy_code' => $policy->code,
            ])
            ->log('Created leave policy: ' . $policy->name);

        return redirect()->route('admin.leave-policies.index')
            ->with('success', 'Leave policy created successfully.');
    }

    /**
     * Update an existing leave policy.
     */
    public function update(Request $request, LeavePolicy $leavePolicy)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:leave_policies,code,' . $leavePolicy->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'annual_entitlement' => 'required|numeric|min:0|max:365',
            'max_carryover' => 'required|numeric|min:0|max:365',
            'can_carry_forward' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'effective_date' => 'nullable|date',
        ]);

        $oldValues = $leavePolicy->only(['name', 'annual_entitlement', 'is_active']);
        $leavePolicy->update($validated);

        // Log the change
        activity('leave_policy_configuration')
            ->causedBy($request->user())
            ->performedOn($leavePolicy)
            ->withProperties([
                'policy_name' => $leavePolicy->name,
                'old_values' => $oldValues,
                'new_values' => $validated,
            ])
            ->log('Updated leave policy: ' . $leavePolicy->name);

        return redirect()->route('admin.leave-policies.index')
            ->with('success', 'Leave policy updated successfully.');
    }

    /**
     * Archive/delete a leave policy (soft delete).
     */
    public function destroy(Request $request, LeavePolicy $leavePolicy)
    {
        // Check if policy is being used by any active leave balances or requests
        $activeBalances = $leavePolicy->balances()->whereHas('employee', function ($q) {
            $q->where('is_active', true);
        })->count();

        if ($activeBalances > 0) {
            return redirect()->route('admin.leave-policies.index')
                ->with('error', 'Cannot archive leave policy with active employee balances. Deactivate it instead.');
        }

        $policyName = $leavePolicy->name;
        $leavePolicy->delete();

        // Log the change
        activity('leave_policy_configuration')
            ->causedBy($request->user())
            ->log('Archived leave policy: ' . $policyName);

        return redirect()->route('admin.leave-policies.index')
            ->with('success', 'Leave policy archived successfully.');
    }

    /**
     * Display approval rules configuration page.
     */
    public function configureApprovalRules(Request $request): Response
    {
        $approvalRules = $this->getApprovalRulesConfiguration();

        // Get leave types for workflow tester
        $leaveTypes = LeavePolicy::where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name', 'is_paid'])
            ->toArray();

        return Inertia::render('Admin/LeavePolicies/ApprovalRules', [
            'approvalRules' => $approvalRules,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Update approval rules configuration.
     * 
     * Handles 7 configurable rule types for leave approval workflows.
     */
    public function updateApprovalRules(Request $request)
    {
        $validated = $request->validate([
            // Duration-based rules
            'duration_threshold_days' => 'required|integer|min:1|max:30',
            'duration_tier2_days' => 'required|integer|min:1|max:30',
            
            // Balance threshold
            'balance_threshold_days' => 'required|numeric|min:0|max:30',
            'balance_warning_enabled' => 'boolean',
            
            // Advance notice
            'advance_notice_days' => 'required|integer|min:0|max:90',
            'short_notice_requires_approval' => 'boolean',
            
            // Workforce impact
            'coverage_threshold_percentage' => 'required|numeric|min:0|max:100',
            'coverage_warning_enabled' => 'boolean',
            
            // Leave type specific
            'unpaid_leave_requires_manager' => 'boolean',
            'maternity_requires_admin' => 'boolean',
            
            // Blackout periods
            'blackout_periods_enabled' => 'boolean',
            'blackout_dates' => 'nullable|array',
            'blackout_dates.*.start' => 'required|date',
            'blackout_dates.*.end' => 'required|date|after_or_equal:blackout_dates.*.start',
            'blackout_dates.*.reason' => 'required|string|max:255',
            
            // Frequency limits
            'frequency_limit_enabled' => 'boolean',
            'frequency_max_requests' => 'required|integer|min:1|max:20',
            'frequency_period_days' => 'required|integer|min:1|max:365',
        ]);

        // Store each rule as a separate system setting
        $settingsMap = [
            // Duration rules
            'duration_threshold_days' => 'leave_approval.duration.threshold_days',
            'duration_tier2_days' => 'leave_approval.duration.tier2_days',
            
            // Balance rules
            'balance_threshold_days' => 'leave_approval.balance.threshold_days',
            'balance_warning_enabled' => 'leave_approval.balance.warning_enabled',
            
            // Advance notice rules
            'advance_notice_days' => 'leave_approval.advance_notice.required_days',
            'short_notice_requires_approval' => 'leave_approval.advance_notice.short_requires_approval',
            
            // Coverage rules
            'coverage_threshold_percentage' => 'leave_approval.coverage.threshold_percentage',
            'coverage_warning_enabled' => 'leave_approval.coverage.warning_enabled',
            
            // Leave type rules
            'unpaid_leave_requires_manager' => 'leave_approval.leave_type.unpaid_requires_manager',
            'maternity_requires_admin' => 'leave_approval.leave_type.maternity_requires_admin',
            
            // Blackout rules
            'blackout_periods_enabled' => 'leave_approval.blackout.enabled',
            
            // Frequency rules
            'frequency_limit_enabled' => 'leave_approval.frequency.enabled',
            'frequency_max_requests' => 'leave_approval.frequency.max_requests',
            'frequency_period_days' => 'leave_approval.frequency.period_days',
        ];

        foreach ($settingsMap as $field => $key) {
            if (isset($validated[$field])) {
                $value = $validated[$field];
                
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                        'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                        'category' => 'leave_approval',
                        'description' => $this->getApprovalRuleDescription($key),
                    ]
                );
            }
        }

        // Handle blackout dates separately as JSON
        if (isset($validated['blackout_dates'])) {
            SystemSetting::updateOrCreate(
                ['key' => 'leave_approval.blackout.dates'],
                [
                    'value' => json_encode($validated['blackout_dates']),
                    'type' => 'json',
                    'category' => 'leave_approval',
                    'description' => 'Blackout period dates for leave restrictions',
                ]
            );
        }

        // Log the change
        activity('leave_approval_configuration')
            ->causedBy($request->user())
            ->withProperties([
                'rules_updated' => array_keys($validated),
            ])
            ->log('Updated leave approval rules configuration');

        return redirect()->route('admin.leave-policies.approval-rules')
            ->with('success', 'Leave approval rules updated successfully.');
    }

    /**
     * Get current approval rules configuration from system settings.
     * 
     * @return array
     */
    private function getApprovalRulesConfiguration(): array
    {
        $settings = SystemSetting::where('key', 'LIKE', 'leave_approval.%')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        return [
            // Duration rules (Tier 1 & 2)
            'duration_threshold_days' => (int)($settings['leave_approval.duration.threshold_days'] ?? 5),
            'duration_tier2_days' => (int)($settings['leave_approval.duration.tier2_days'] ?? 15),
            
            // Balance threshold rules
            'balance_threshold_days' => (float)($settings['leave_approval.balance.threshold_days'] ?? 5),
            'balance_warning_enabled' => (bool)($settings['leave_approval.balance.warning_enabled'] ?? true),
            
            // Advance notice rules
            'advance_notice_days' => (int)($settings['leave_approval.advance_notice.required_days'] ?? 3),
            'short_notice_requires_approval' => (bool)($settings['leave_approval.advance_notice.short_requires_approval'] ?? true),
            
            // Coverage rules
            'coverage_threshold_percentage' => (float)($settings['leave_approval.coverage.threshold_percentage'] ?? 75),
            'coverage_warning_enabled' => (bool)($settings['leave_approval.coverage.warning_enabled'] ?? true),
            
            // Leave type specific rules
            'unpaid_leave_requires_manager' => (bool)($settings['leave_approval.leave_type.unpaid_requires_manager'] ?? true),
            'maternity_requires_admin' => (bool)($settings['leave_approval.leave_type.maternity_requires_admin'] ?? true),
            
            // Blackout period rules
            'blackout_periods_enabled' => (bool)($settings['leave_approval.blackout.enabled'] ?? false),
            'blackout_dates' => isset($settings['leave_approval.blackout.dates']) 
                ? json_decode($settings['leave_approval.blackout.dates'], true) 
                : [],
            
            // Frequency limit rules
            'frequency_limit_enabled' => (bool)($settings['leave_approval.frequency.enabled'] ?? false),
            'frequency_max_requests' => (int)($settings['leave_approval.frequency.max_requests'] ?? 3),
            'frequency_period_days' => (int)($settings['leave_approval.frequency.period_days'] ?? 30),
        ];
    }

    /**
     * Get human-readable description for approval rule setting key.
     * 
     * @param string $key
     * @return string
     */
    private function getApprovalRuleDescription(string $key): string
    {
        $descriptions = [
            'leave_approval.duration.threshold_days' => 'Number of days threshold for HR Manager approval',
            'leave_approval.duration.tier2_days' => 'Number of days threshold for Office Admin approval',
            'leave_approval.balance.threshold_days' => 'Minimum balance threshold warning',
            'leave_approval.balance.warning_enabled' => 'Enable balance threshold warnings',
            'leave_approval.advance_notice.required_days' => 'Required advance notice in days',
            'leave_approval.advance_notice.short_requires_approval' => 'Short notice requires manager approval',
            'leave_approval.coverage.threshold_percentage' => 'Minimum workforce coverage percentage',
            'leave_approval.coverage.warning_enabled' => 'Enable coverage warnings',
            'leave_approval.leave_type.unpaid_requires_manager' => 'Unpaid leave requires manager approval',
            'leave_approval.leave_type.maternity_requires_admin' => 'Maternity leave requires admin approval',
            'leave_approval.blackout.enabled' => 'Enable blackout period restrictions',
            'leave_approval.frequency.enabled' => 'Enable frequency limit checks',
            'leave_approval.frequency.max_requests' => 'Maximum number of requests allowed',
            'leave_approval.frequency.period_days' => 'Frequency checking period in days',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
