<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization is handled by the Employee middleware (EnsureEmployee)
     * and controller-level checks. This method just returns true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * Employees can report attendance issues:
     * - attendance_date: The date of the attendance issue
     * - issue_type: missing_punch, wrong_time, or other
     * - actual_time_in: Claimed correct time in (optional if issue_type is not time-related)
     * - actual_time_out: Claimed correct time out (optional if issue_type is not time-related)
     * - reason: Detailed explanation of the issue
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:' . now()->subMonths(3)->format('Y-m-d'), // Can only report issues within last 3 months
            ],
            'issue_type' => [
                'required',
                'in:missing_punch,wrong_time,other',
            ],
            'actual_time_in' => [
                'nullable',
                'required_if:issue_type,missing_punch,wrong_time',
                'date_format:H:i',
            ],
            'actual_time_out' => [
                'nullable',
                'required_if:issue_type,missing_punch,wrong_time',
                'date_format:H:i',
                'after:actual_time_in',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'attendance_date' => 'attendance date',
            'issue_type' => 'issue type',
            'actual_time_in' => 'actual time in',
            'actual_time_out' => 'actual time out',
            'reason' => 'reason for correction',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'attendance_date.before_or_equal' => 'You cannot report attendance issues for future dates.',
            'attendance_date.after_or_equal' => 'You can only report attendance issues within the last 3 months. Please contact HR Staff for older corrections.',
            'actual_time_in.required_if' => 'Please provide your actual time in for this type of issue.',
            'actual_time_out.required_if' => 'Please provide your actual time out for this type of issue.',
            'actual_time_out.after' => 'Time out must be after time in.',
            'actual_time_in.date_format' => 'Time in must be in HH:MM format (e.g., 08:30).',
            'actual_time_out.date_format' => 'Time out must be in HH:MM format (e.g., 17:30).',
            'reason.min' => 'Please provide a detailed explanation (at least 10 characters).',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
