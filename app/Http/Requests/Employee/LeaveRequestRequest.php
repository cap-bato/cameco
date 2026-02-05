<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Leave Request Validation Request
 * 
 * Validates employee leave request submissions:
 * - Leave type selection (from active policies)
 * - Date range (start/end dates, advance notice)
 * - Reason for leave
 * - Supporting documents (PDF only, max 5MB)
 * - Contact information during leave
 */
class LeaveRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by Employee middleware and controller-level checks
        // This ensures only authenticated employees with valid employee records can submit
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Leave type selection
            'leave_policy_id' => [
                'required',
                'integer',
                Rule::exists('leave_policies', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],

            // Date range
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            // Reason for leave
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],

            // Supporting documents (medical certificates, etc.)
            'document' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:5120', // 5MB in kilobytes
            ],

            // Contact information during leave (optional)
            'contact_during_leave' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Leave policy errors
            'leave_policy_id.required' => 'Please select a leave type.',
            'leave_policy_id.exists' => 'The selected leave type is invalid or no longer available.',

            // Start date errors
            'start_date.required' => 'Please select a start date for your leave.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be today or a future date. You cannot request leave for past dates.',

            // End date errors
            'end_date.required' => 'Please select an end date for your leave.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be the same as or after the start date.',

            // Reason errors
            'reason.required' => 'Please provide a reason for your leave request.',
            'reason.min' => 'Reason must be at least 10 characters. Please provide more details.',
            'reason.max' => 'Reason must not exceed 1000 characters.',

            // Document upload errors
            'document.file' => 'The uploaded file is invalid. Please upload a valid PDF document.',
            'document.mimes' => 'Supporting documents must be in PDF format only. Please convert your file to PDF and try again.',
            'document.max' => 'Supporting document must not exceed 5MB in size. Please compress your PDF or upload a smaller file.',

            // Contact information errors
            'contact_during_leave.max' => 'Contact information must not exceed 255 characters.',
        ];
    }

    /**
     * Get custom attribute names for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'leave_policy_id' => 'leave type',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'reason' => 'reason',
            'document' => 'supporting document',
            'contact_during_leave' => 'contact information',
        ];
    }

    /**
     * Prepare the data for validation.
     * 
     * Additional business logic validation:
     * - Validate advance notice requirement (minimum days before leave starts)
     * - Validate maximum consecutive days allowed
     * - Validate against blackout periods (future enhancement)
     */
    protected function prepareForValidation(): void
    {
        // Additional validation can be added here if needed
        // For example: checking advance notice, blackout periods, etc.
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional cross-field validation
            
            // Validate minimum advance notice (3 days minimum, except emergencies)
            if ($this->has('start_date') && $this->has('leave_policy_id')) {
                $startDate = \Carbon\Carbon::parse($this->input('start_date'));
                $today = \Carbon\Carbon::today();
                $daysAdvance = $today->diffInDays($startDate, false);

                // Get leave policy to check if emergency leave
                $policy = \App\Models\LeavePolicy::find($this->input('leave_policy_id'));
                
                if ($policy) {
                    $isEmergency = str_contains(strtolower($policy->name), 'emergency') || strtolower($policy->code) === 'el';
                    $minAdvanceNotice = $policy->min_advance_notice_days ?? 3;

                    if (!$isEmergency && $daysAdvance < $minAdvanceNotice) {
                        $validator->errors()->add(
                            'start_date',
                            "Leave requests must be submitted at least {$minAdvanceNotice} days in advance. Please select a later date or contact HR Staff for emergency leave."
                        );
                    }
                }
            }

            // Validate maximum consecutive days
            if ($this->has('start_date') && $this->has('end_date') && $this->has('leave_policy_id')) {
                $startDate = \Carbon\Carbon::parse($this->input('start_date'));
                $endDate = \Carbon\Carbon::parse($this->input('end_date'));
                $daysRequested = $startDate->diffInDays($endDate) + 1;

                // Get leave policy max consecutive days
                $policy = \App\Models\LeavePolicy::find($this->input('leave_policy_id'));
                
                if ($policy && $policy->max_consecutive_days) {
                    if ($daysRequested > $policy->max_consecutive_days) {
                        $validator->errors()->add(
                            'end_date',
                            "Maximum consecutive days for {$policy->name} is {$policy->max_consecutive_days} days. Your request is for {$daysRequested} days. Please shorten your leave period or split it into multiple requests."
                        );
                    }
                }
            }

            // Validate document requirement for specific leave types
            if ($this->has('leave_policy_id') && !$this->hasFile('document')) {
                $policy = \App\Models\LeavePolicy::find($this->input('leave_policy_id'));
                
                if ($policy && $policy->requires_document) {
                    // Check if sick leave > 3 days or other leave types that require documents
                    $isSickLeave = str_contains(strtolower($policy->name), 'sick') || strtolower($policy->code) === 'sl';
                    
                    if ($isSickLeave && $this->has('start_date') && $this->has('end_date')) {
                        $startDate = \Carbon\Carbon::parse($this->input('start_date'));
                        $endDate = \Carbon\Carbon::parse($this->input('end_date'));
                        $daysRequested = $startDate->diffInDays($endDate) + 1;

                        if ($daysRequested >= 3) {
                            $validator->errors()->add(
                                'document',
                                'Medical certificate is required for sick leave of 3 days or more. Please upload a PDF copy of your medical certificate.'
                            );
                        }
                    } elseif (!$isSickLeave && $policy->requires_document) {
                        $validator->errors()->add(
                            'document',
                            "Supporting document is required for {$policy->name}. Please upload the required document in PDF format."
                        );
                    }
                }
            }
        });
    }
}
