<?php

namespace App\Http\Requests\HR\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // authorization handled in controller for now
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            // ensure we have a leave_policy_id after normalization (prepareForValidation)
            'leave_policy_id' => 'required|exists:leave_policies,id',
            'leave_type_id' => 'nullable|exists:leave_policies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
            'hr_notes' => 'required|string|max:1000',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Support legacy `leave_type_id` field by normalizing to `leave_policy_id` for validation
        if (!$this->filled('leave_policy_id') && $this->filled('leave_type_id')) {
            $this->merge([ 'leave_policy_id' => $this->input('leave_type_id') ]);
        }
    }
}
