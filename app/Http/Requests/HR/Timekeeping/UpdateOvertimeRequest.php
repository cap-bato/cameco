<?php

namespace App\Http\Requests\HR\Timekeeping;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('hr.timekeeping.overtime.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_date' => ['sometimes', 'date'],
            'planned_start_time' => ['sometimes', 'date', 'date_format:Y-m-d H:i:s'],
            'planned_end_time' => ['sometimes', 'date', 'date_format:Y-m-d H:i:s', 'after:planned_start_time'],
            'planned_hours' => ['sometimes', 'numeric', 'min:0.5', 'max:12'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'completed'])],
            'actual_start_time' => ['nullable', 'date', 'date_format:Y-m-d H:i:s'],
            'actual_end_time' => ['nullable', 'date', 'date_format:Y-m-d H:i:s', 'after:actual_start_time'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:12'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'request_date.date' => 'Request date must be a valid date.',
            'planned_start_time.date' => 'Planned start time must be a valid date and time.',
            'planned_start_time.date_format' => 'Planned start time must be in Y-m-d H:i:s format.',
            'planned_end_time.date' => 'Planned end time must be a valid date and time.',
            'planned_end_time.date_format' => 'Planned end time must be in Y-m-d H:i:s format.',
            'planned_end_time.after' => 'Planned end time must be after the planned start time.',
            'planned_hours.numeric' => 'Planned hours must be a number.',
            'planned_hours.min' => 'Minimum overtime is 0.5 hours.',
            'planned_hours.max' => 'Maximum overtime is 12 hours per day.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
            'status.in' => 'Invalid status. Must be one of: pending, approved, rejected, completed.',
            'actual_start_time.date' => 'Actual start time must be a valid date and time.',
            'actual_start_time.date_format' => 'Actual start time must be in Y-m-d H:i:s format.',
            'actual_end_time.date' => 'Actual end time must be a valid date and time.',
            'actual_end_time.date_format' => 'Actual end time must be in Y-m-d H:i:s format.',
            'actual_end_time.after' => 'Actual end time must be after the actual start time.',
            'actual_hours.numeric' => 'Actual hours must be a number.',
            'actual_hours.min' => 'Actual hours cannot be negative.',
            'actual_hours.max' => 'Actual hours cannot exceed 12 hours per day.',
        ];
    }
}
