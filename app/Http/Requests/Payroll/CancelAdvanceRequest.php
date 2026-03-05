<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class CancelAdvanceRequest extends FormRequest
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
            'cancellation_reason' => 'required|string|min:10|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Cancellation reason is required',
            'cancellation_reason.string' => 'Cancellation reason must be text',
            'cancellation_reason.min' => 'Cancellation reason must be at least 10 characters',
            'cancellation_reason.max' => 'Cancellation reason cannot exceed 500 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from reason
        if ($this->has('cancellation_reason')) {
            $this->merge([
                'cancellation_reason' => trim($this->cancellation_reason),
            ]);
        }
    }
}
