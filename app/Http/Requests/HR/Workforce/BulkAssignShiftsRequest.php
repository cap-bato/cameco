<?php

namespace App\Http\Requests\HR\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignShiftsRequest extends FormRequest
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
            // Required fields
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'date_from' => ['required', 'date', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'shift_start' => ['required', 'date_format:H:i:s'],
            'shift_end' => ['required', 'date_format:H:i:s'],
            
            // Optional fields
            'shift_type' => ['nullable', Rule::in(['morning', 'afternoon', 'night', 'split', 'custom'])],
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allow_conflicts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'At least one employee must be selected',
            'employee_ids.array' => 'Employee IDs must be an array',
            'employee_ids.min' => 'At least one employee must be selected',
            'employee_ids.*.exists' => 'One or more selected employees do not exist',
            'schedule_id.required' => 'Work schedule is required',
            'schedule_id.exists' => 'Selected work schedule does not exist',
            'date_from.required' => 'Start date is required',
            'date_from.date_format' => 'Start date must be in Y-m-d format',
            'date_to.required' => 'End date is required',
            'date_to.date_format' => 'End date must be in Y-m-d format',
            'date_to.after_or_equal' => 'End date must be on or after the start date',
            'shift_start.required' => 'Shift start time is required',
            'shift_start.date_format' => 'Shift start time must be in H:i:s format',
            'shift_end.required' => 'Shift end time is required',
            'shift_end.date_format' => 'Shift end time must be in H:i:s format',
            'shift_type.in' => 'Shift type must be one of: morning, afternoon, night, split, custom',
            'department_id.exists' => 'Selected department does not exist',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate shift times
            $shiftStart = $this->input('shift_start');
            $shiftEnd = $this->input('shift_end');
            
            if ($shiftStart && $shiftEnd) {
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $shiftStart);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $shiftEnd);
                
                if ($end <= $start) {
                    $validator->errors()->add('shift_end', 'Shift end time must be after shift start time');
                }
            }
            
            // Validate date range doesn't exceed 90 days
            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');
            
            if ($dateFrom && $dateTo) {
                $from = \Carbon\Carbon::createFromFormat('Y-m-d', $dateFrom);
                $to = \Carbon\Carbon::createFromFormat('Y-m-d', $dateTo);
                $daysDifference = $from->diffInDays($to);
                
                if ($daysDifference > 90) {
                    $validator->errors()->add('date_to', 'Date range cannot exceed 90 days. Current range: ' . $daysDifference . ' days');
                }
            }
            
            // Validate no duplicate employee IDs
            $employeeIds = $this->input('employee_ids', []);
            if (count($employeeIds) !== count(array_unique($employeeIds))) {
                $validator->errors()->add('employee_ids', 'Duplicate employee IDs are not allowed');
            }
        });
    }
}
