<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
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
     * Employees can only update contact information fields:
     * - contact_number
     * - email
     * - address (including city, province, postal_code)
     * - emergency_contact (name, relationship, phone, address)
     * 
     * All other fields (personal details, government IDs, employment info)
     * can only be updated by HR Staff through the HR module.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Contact Information
            'contact_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[\d\s\-\+\(\)]+$/', // Allow digits, spaces, dashes, plus, parentheses
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255',
            ],
            
            // Home Address
            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
            'city' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'province' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],
            
            // Emergency Contact Information
            'emergency_contact_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'emergency_contact_relationship' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'emergency_contact_phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[\d\s\-\+\(\)]+$/',
            ],
            'emergency_contact_address' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'contact_number' => 'contact number',
            'email' => 'email address',
            'address' => 'home address',
            'city' => 'city',
            'province' => 'province',
            'postal_code' => 'postal code',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_relationship' => 'emergency contact relationship',
            'emergency_contact_phone' => 'emergency contact phone',
            'emergency_contact_address' => 'emergency contact address',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => 'The contact number format is invalid. Only digits, spaces, dashes, plus signs, and parentheses are allowed.',
            'email.email' => 'Please provide a valid email address.',
            'emergency_contact_phone.regex' => 'The emergency contact phone format is invalid. Only digits, spaces, dashes, plus signs, and parentheses are allowed.',
        ];
    }
}
