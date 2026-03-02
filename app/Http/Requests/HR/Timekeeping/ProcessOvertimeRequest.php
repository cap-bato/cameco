<?php

namespace App\Http\Requests\HR\Timekeeping;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessOvertimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('hr.timekeeping.overtime.approve');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'status' => ['required', Rule::in(['approved', 'rejected', 'completed'])],
        ];

        // Require rejection reason if rejecting
        if ($this->input('status') === 'rejected') {
            $rules['rejection_reason'] = ['required', 'string', 'max:500'];
        }

        // Require actual hours if completing
        if ($this->input('status') === 'completed') {
            $rules['actual_hours'] = ['required', 'numeric', 'min:0', 'max:12'];
            $rules['actual_start_time'] = ['nullable', 'date', 'date_format:Y-m-d H:i:s'];
            $rules['actual_end_time'] = ['nullable', 'date', 'date_format:Y-m-d H:i:s', 'after:actual_start_time'];
        }

        return $rules;
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status. Must be one of: approved, rejected, completed.',
            'rejection_reason.required' => 'Please provide a reason for rejection.',
            'rejection_reason.string' => 'Rejection reason must be a string.',
            'rejection_reason.max' => 'Rejection reason cannot exceed 500 characters.',
            'actual_hours.required' => 'Actual hours are required when marking as completed.',
            'actual_hours.numeric' => 'Actual hours must be a number.',
            'actual_hours.min' => 'Actual hours cannot be negative.',
            'actual_hours.max' => 'Actual hours cannot exceed 12 hours per day.',
            'actual_start_time.date' => 'Actual start time must be a valid date and time.',
            'actual_start_time.date_format' => 'Actual start time must be in Y-m-d H:i:s format.',
            'actual_end_time.date' => 'Actual end time must be a valid date and time.',
            'actual_end_time.date_format' => 'Actual end time must be in Y-m-d H:i:s format.',
            'actual_end_time.after' => 'Actual end time must be after the actual start time.',
        ];
    }
}
