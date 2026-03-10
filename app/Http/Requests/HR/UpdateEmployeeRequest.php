<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has HR Manager role or is Superadmin
        return $this->user()->hasAnyRole(['HR Manager', 'Superadmin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $employeeId = $this->route('employee');
        
        return [
            // Personal Information (Profile data - can be updated)
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today', 'after:' . now()->subYears(100)->format('Y-m-d')],
            'place_of_birth' => ['nullable', 'string', 'max:200'],
            'is_pwd' => ['nullable', 'boolean'],
            'gender' => ['sometimes', 'required', 'string', 'in:male,female'],
            'civil_status' => ['sometimes', 'required', 'string', 'in:single,married,widowed,divorced,separated'],
            
            // Spouse Information (required if married)
            'spouse_name' => ['nullable', 'string', 'max:200'],
            'spouse_date_of_birth' => ['nullable', 'date'],
            'spouse_contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            
            // Parents Information
            'father_name' => ['nullable', 'string', 'max:200'],
            'father_date_of_birth' => ['nullable', 'date'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'mother_date_of_birth' => ['nullable', 'date'],
            
            // Contact Information
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:employees,email,' . $employeeId],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            
            // Address Information
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            
            // Emergency Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:200'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            
            // Government IDs
            'sss_number' => ['nullable', 'string', 'max:12'],
            'tin_number' => ['nullable', 'string', 'max:15'],
            'philhealth_number' => ['nullable', 'string', 'max:20'],
            'pagibig_number' => ['nullable', 'string', 'max:20'],
            
            // Profile Picture
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'], // 5MB max
            
            // Employment Information
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'required', 'integer', 'exists:positions,id'],
            'employment_type' => ['sometimes', 'required', 'string', 'in:regular,probationary,contractual,project-based,part-time'],
            'date_hired' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'regularization_date' => ['nullable', 'date', 'after:date_hired'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['sometimes', 'required', 'string', 'in:active,on_leave,suspended,terminated,archived'],
            'termination_date' => ['nullable', 'date', 'required_if:status,terminated'],
            'termination_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,terminated'],
            
            // Dependents
            'dependents' => ['nullable', 'array'],
            'dependents.*.id' => ['nullable', 'integer'],
            'dependents.*.first_name' => ['required_with:dependents.*', 'string', 'max:100'],
            'dependents.*.middle_name' => ['nullable', 'string', 'max:100'],
            'dependents.*.last_name' => ['required_with:dependents.*', 'string', 'max:100'],
            'dependents.*.date_of_birth' => ['nullable', 'date'],
            'dependents.*.relationship' => ['nullable', 'string', 'max:50'],
            'dependents.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'date_of_birth.after' => 'Employee must be less than 100 years old',
            'gender.required' => 'Gender is required',
            'civil_status.required' => 'Civil status is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Valid email address is required',
            'email.unique' => 'This email address is already registered',
            'phone.regex' => 'Phone number format is invalid',
            'mobile.regex' => 'Mobile number format is invalid',
            'emergency_contact_phone.regex' => 'Emergency contact phone format is invalid',
            'department_id.required' => 'Department is required',
            'department_id.exists' => 'Selected department does not exist',
            'position_id.required' => 'Position is required',
            'position_id.exists' => 'Selected position does not exist',
            'supervisor_id.exists' => 'Selected supervisor does not exist',
            'employment_type.required' => 'Employment type is required',
            'employment_type.in' => 'Invalid employment type selected',
            'date_hired.required' => 'Hire date is required',
            'date_hired.before_or_equal' => 'Hire date cannot be in the future',
            'regularization_date.after' => 'Regularization date must be after hire date',
            'status.required' => 'Employment status is required',
            'status.in' => 'Invalid employment status selected',
            'termination_date.required_if' => 'Termination date is required when status is terminated',
            'termination_reason.required_if' => 'Termination reason is required when status is terminated',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'middle_name' => 'middle name',
            'date_of_birth' => 'date of birth',
            'contact_number' => 'contact number',
            'email_address' => 'email address',
            'present_address' => 'present address',
            'permanent_address' => 'permanent address',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_relationship' => 'emergency contact relationship',
            'emergency_contact_number' => 'emergency contact number',
            'department_id' => 'department',
            'supervisor_id' => 'supervisor',
            'position_id' => 'position',
            'employment_type' => 'employment type',
            'date_hired' => 'hire date',
            'regularization_date' => 'regularization date',
            'status' => 'status',
            'termination_date' => 'termination date',
            'termination_reason' => 'termination reason',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->expectsJson()) {
            // Log validation errors for debugging
            \Log::debug('Validation failed on update employee', [
                'errors' => $validator->errors()->toArray(),
                'request_data_keys' => array_keys($this->all()),
            ]);
            
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        
        parent::failedValidation($validator);
    }
}
