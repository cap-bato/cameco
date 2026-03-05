<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class RejectAdvanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('approve_cash_advances');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|min:10|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Rejection reason is required',
            'rejection_reason.string' => 'Rejection reason must be text',
            'rejection_reason.min' => 'Rejection reason must be at least 10 characters',
            'rejection_reason.max' => 'Rejection reason cannot exceed 500 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from reason
        if ($this->has('rejection_reason')) {
            $this->merge([
                'rejection_reason' => trim($this->rejection_reason),
            ]);
        }
    }
}
