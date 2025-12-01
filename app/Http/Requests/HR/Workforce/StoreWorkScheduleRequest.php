<?php

namespace App\Http\Requests\HR\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkScheduleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:work_schedules,name,NULL,id,deleted_at,NULL'],
            'effective_date' => ['required', 'date', 'date_format:Y-m-d'],
            
            // Optional fields
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'date_format:Y-m-d', 'after:effective_date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            
            // Day schedule times (all optional, format H:i:s)
            'monday_start' => ['nullable', 'date_format:H:i:s'],
            'monday_end' => ['nullable', 'date_format:H:i:s', 'required_with:monday_start'],
            'tuesday_start' => ['nullable', 'date_format:H:i:s'],
            'tuesday_end' => ['nullable', 'date_format:H:i:s', 'required_with:tuesday_start'],
            'wednesday_start' => ['nullable', 'date_format:H:i:s'],
            'wednesday_end' => ['nullable', 'date_format:H:i:s', 'required_with:wednesday_start'],
            'thursday_start' => ['nullable', 'date_format:H:i:s'],
            'thursday_end' => ['nullable', 'date_format:H:i:s', 'required_with:thursday_start'],
            'friday_start' => ['nullable', 'date_format:H:i:s'],
            'friday_end' => ['nullable', 'date_format:H:i:s', 'required_with:friday_start'],
            'saturday_start' => ['nullable', 'date_format:H:i:s'],
            'saturday_end' => ['nullable', 'date_format:H:i:s', 'required_with:saturday_start'],
            'sunday_start' => ['nullable', 'date_format:H:i:s'],
            'sunday_end' => ['nullable', 'date_format:H:i:s', 'required_with:sunday_start'],
            
            // Break durations (in minutes, 0-120)
            'lunch_break_duration' => ['nullable', 'integer', 'min:0', 'max:120'],
            'morning_break_duration' => ['nullable', 'integer', 'min:0', 'max:120'],
            'afternoon_break_duration' => ['nullable', 'integer', 'min:0', 'max:120'],
            
            // Overtime settings
            'overtime_threshold' => ['nullable', 'integer', 'min:1', 'max:24'],
            'overtime_rate_multiplier' => ['nullable', 'numeric', 'min:1', 'max:3'],
            
            // Template flag
            'is_template' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Schedule name is required',
            'name.unique' => 'A schedule with this name already exists',
            'effective_date.required' => 'Effective date is required',
            'effective_date.date_format' => 'Effective date must be in Y-m-d format',
            'expires_at.after' => 'Expiration date must be after the effective date',
            'monday_end.required_with' => 'End time is required when start time is provided',
            'tuesday_end.required_with' => 'End time is required when start time is provided',
            'wednesday_end.required_with' => 'End time is required when start time is provided',
            'thursday_end.required_with' => 'End time is required when start time is provided',
            'friday_end.required_with' => 'End time is required when start time is provided',
            'saturday_end.required_with' => 'End time is required when start time is provided',
            'sunday_end.required_with' => 'End time is required when start time is provided',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that at least one working day is defined
            $workingDays = 0;
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach ($days as $day) {
                if ($this->input("{$day}_start") && $this->input("{$day}_end")) {
                    $workingDays++;
                }
            }
            
            if ($workingDays === 0) {
                $validator->errors()->add('schedule', 'At least one working day must be defined');
            }
            
            // Validate that end times are after start times for each day
            foreach ($days as $day) {
                $startKey = "{$day}_start";
                $endKey = "{$day}_end";
                
                if ($this->input($startKey) && $this->input($endKey)) {
                    try {
                        // Try parsing with seconds first, then without
                        $startStr = $this->input($startKey);
                        $endStr = $this->input($endKey);
                        
                        // Handle both H:i and H:i:s formats
                        $startFormat = strlen($startStr) === 8 ? 'H:i:s' : 'H:i';
                        $endFormat = strlen($endStr) === 8 ? 'H:i:s' : 'H:i';
                        
                        $start = \Carbon\Carbon::createFromFormat($startFormat, $startStr);
                        $end = \Carbon\Carbon::createFromFormat($endFormat, $endStr);
                        
                        if ($end <= $start) {
                            $validator->errors()->add($endKey, ucfirst($day) . ' end time must be after start time');
                        }
                    } catch (\Exception $e) {
                        // If parsing fails, let the date_format rule handle it
                    }
                }
            }
        });
    }
}
