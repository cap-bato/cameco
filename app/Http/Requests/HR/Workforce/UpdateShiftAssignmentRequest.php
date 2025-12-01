<?php

namespace App\Http\Requests\HR\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Optional fields (all can be updated)
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'shift_start' => ['nullable', 'date_format:H:i:s'],
            'shift_end' => ['nullable', 'date_format:H:i:s'],
            
            // Optional fields
            'shift_type' => ['nullable', Rule::in(['morning', 'afternoon', 'night', 'split', 'custom'])],
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'is_overtime' => ['nullable', 'boolean'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Selected employee does not exist',
            'schedule_id.exists' => 'Selected work schedule does not exist',
            'date.date_format' => 'Date must be in Y-m-d format',
            'shift_start.date_format' => 'Shift start time must be in H:i:s format',
            'shift_end.date_format' => 'Shift end time must be in H:i:s format',
            'shift_type.in' => 'Shift type must be one of: morning, afternoon, night, split, custom',
            'status.in' => 'Status must be one of: scheduled, in_progress, completed, cancelled, no_show',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $shiftStart = $this->input('shift_start');
            $shiftEnd = $this->input('shift_end');
            
            // Only validate if both times are provided
            if ($shiftStart && $shiftEnd) {
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $shiftStart);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $shiftEnd);
                
                if ($end <= $start) {
                    $validator->errors()->add('shift_end', 'Shift end time must be after shift start time');
                }
            }
        });
    }
}
