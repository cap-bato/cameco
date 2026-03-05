<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create_cash_advances');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'advance_type' => 'required|in:cash_advance,medical_advance,travel_advance,equipment_advance',
            'amount_requested' => 'required|numeric|min:1000',
            'purpose' => 'required|string|min:10|max:500',
            'requested_date' => 'required|date',
            'priority_level' => 'required|in:normal,urgent',
            'supporting_documents' => 'nullable|array',
            'supporting_documents.*' => 'file|max:5120', // 5MB max per file
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee',
            'employee_id.exists' => 'Selected employee does not exist',
            'advance_type.required' => 'Please select an advance type',
            'advance_type.in' => 'Invalid advance type selected',
            'amount_requested.required' => 'Amount is required',
            'amount_requested.numeric' => 'Amount must be a valid number',
            'amount_requested.min' => 'Minimum advance amount is ₱1,000',
            'purpose.required' => 'Purpose is required',
            'purpose.min' => 'Purpose must be at least 10 characters',
            'purpose.max' => 'Purpose cannot exceed 500 characters',
            'requested_date.required' => 'Requested date is required',
            'requested_date.date' => 'Requested date must be a valid date',
            'priority_level.required' => 'Priority level is required',
            'priority_level.in' => 'Invalid priority level selected',
            'supporting_documents.*.file' => 'Each supporting document must be a file',
            'supporting_documents.*.max' => 'Each supporting document cannot exceed 5MB',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert employee_id to integer if it's a string
        if (is_string($this->employee_id)) {
            $this->merge([
                'employee_id' => (int) $this->employee_id,
            ]);
        }
    }
}
