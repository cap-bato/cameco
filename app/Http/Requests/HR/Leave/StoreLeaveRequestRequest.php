<?php

namespace App\Http\Requests\HR\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // authorization handled in controller for now
        return $this->user()->hasAnyRole(['HR Manager', 'HR Staff', 'Superadmin']);
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            // ensure we have a leave_policy_id after normalization (prepareForValidation)
            'leave_policy_id' => 'required|exists:leave_policies,id',
            'leave_type_id' => 'nullable|exists:leave_policies,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'hr_notes' => 'required|string|max:1000',
            // Leave type variant (for Sick Leave only)
            'leave_type_variant' => 'nullable|in:half_am,half_pm',
            // Auto-approve flag - HR staff can auto-approve when creating
            'auto_approve' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Support legacy `leave_type_id` field by normalizing to `leave_policy_id` for validation
        if (!$this->filled('leave_policy_id') && $this->filled('leave_type_id')) {
            $this->merge([ 'leave_policy_id' => $this->input('leave_type_id') ]);
        }
    }

    /**
     * Configure the validator instance.
     * 
     * Validates variant constraints:
     * - Variant only allowed with Sick Leave (SL policy code)
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate variant is only used with Sick Leave
            if ($this->has('leave_policy_id') && $this->has('leave_type_variant')) {
                $policy = \App\Models\LeavePolicy::find($this->input('leave_policy_id'));
                $variant = $this->input('leave_type_variant');
                
                if ($variant && $policy && $policy->code !== 'SL') {
                    $validator->errors()->add(
                        'leave_type_variant',
                        'Leave variants (Half Day AM/PM) are only available for Sick Leave. Please change the leave type or remove the variant selection.'
                    );
                }
            }
        });
    }
}
