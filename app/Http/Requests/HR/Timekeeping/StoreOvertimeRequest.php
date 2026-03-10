<?php

namespace App\Http\Requests\HR\Timekeeping;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('hr.timekeeping.overtime.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'request_date' => ['required', 'date', 'after_or_equal:today'],
            'planned_start_time' => ['required', 'date', 'date_format:Y-m-d H:i:s'],
            'planned_end_time' => ['required', 'date', 'date_format:Y-m-d H:i:s', 'after:planned_start_time'],
            'planned_hours' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'reason' => ['required', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'approved'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'request_date.required' => 'Request date is required.',
            'request_date.date' => 'Request date must be a valid date.',
            'request_date.after_or_equal' => 'Request date cannot be in the past.',
            'planned_start_time.required' => 'Planned start time is required.',
            'planned_start_time.date' => 'Planned start time must be a valid date and time.',
            'planned_start_time.date_format' => 'Planned start time must be in Y-m-d H:i:s format.',
            'planned_end_time.required' => 'Planned end time is required.',
            'planned_end_time.date' => 'Planned end time must be a valid date and time.',
            'planned_end_time.date_format' => 'Planned end time must be in Y-m-d H:i:s format.',
            'planned_end_time.after' => 'Planned end time must be after the start time.',
            'planned_hours.required' => 'Planned hours is required.',
            'planned_hours.numeric' => 'Planned hours must be a number.',
            'planned_hours.min' => 'Minimum overtime is 0.5 hours.',
            'planned_hours.max' => 'Maximum overtime is 12 hours per day.',
            'reason.required' => 'Please provide a reason for overtime.',
            'reason.string' => 'Reason must be text.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
            'status.in' => 'Status must be either pending or approved.',
        ];
    }
}
