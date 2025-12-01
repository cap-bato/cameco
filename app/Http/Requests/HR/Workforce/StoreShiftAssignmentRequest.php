<?php

namespace App\Http\Requests\HR\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'shift_start' => ['required', 'date_format:H:i:s'],
            'shift_end' => ['required', 'date_format:H:i:s'],
            
            // Optional fields
            'shift_type' => ['nullable', Rule::in(['morning', 'afternoon', 'night', 'split', 'custom'])],
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required',
            'employee_id.exists' => 'Selected employee does not exist',
            'schedule_id.required' => 'Work schedule is required',
            'schedule_id.exists' => 'Selected work schedule does not exist',
            'date.required' => 'Assignment date is required',
            'date.date_format' => 'Date must be in Y-m-d format',
            'shift_start.required' => 'Shift start time is required',
            'shift_start.date_format' => 'Shift start time must be in H:i:s format',
            'shift_end.required' => 'Shift end time is required',
            'shift_end.date_format' => 'Shift end time must be in H:i:s format',
            'shift_type.in' => 'Shift type must be one of: morning, afternoon, night, split, custom',
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
            
            if ($shiftStart && $shiftEnd) {
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $shiftStart);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $shiftEnd);
                
                if ($end <= $start) {
                    $validator->errors()->add('shift_end', 'Shift end time must be after shift start time');
                }
            }
            
            // Check for existing assignments on same day (conflict detection)
            // This is optional - you can enable if needed
            // $this->validateNoConflicts($validator);
        });
    }

    /**
     * Optional: Check for conflicting shifts on the same day
     * Uncomment the call in withValidator to enable
     */
    protected function validateNoConflicts($validator)
    {
        $employeeId = $this->input('employee_id');
        $date = $this->input('date');
        $shiftStart = $this->input('shift_start');
        $shiftEnd = $this->input('shift_end');
        
        if (!$employeeId || !$date || !$shiftStart || !$shiftEnd) {
            return;
        }
        
        // Query for conflicting assignments
        $conflicts = \App\Models\ShiftAssignment::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->where(function ($q) use ($shiftStart, $shiftEnd) {
                $q->whereBetween('shift_start', [$shiftStart, $shiftEnd])
                  ->orWhereBetween('shift_end', [$shiftStart, $shiftEnd]);
            })
            ->exists();
        
        if ($conflicts) {
            $validator->errors()->add('shift_start', 'Employee already has a shift assignment on this date that conflicts with the selected time');
        }
    }
}
