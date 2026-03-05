<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAdvanceRequest extends FormRequest
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
        $advance = $this->route('id') ? \App\Models\CashAdvance::find($this->route('id')) : null;

        return [
            'amount_approved' => [
                'required',
                'numeric',
                'min:1000',
                'max:' . ($advance?->amount_requested ?? 999999),
            ],
            'deduction_schedule' => 'required|in:single_period,installments',
            'number_of_installments' => [
                'required',
                'integer',
                'min:1',
                'max:6',
            ],
            'approval_notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'amount_approved.required' => 'Approved amount is required',
            'amount_approved.numeric' => 'Approved amount must be a valid number',
            'amount_approved.min' => 'Minimum approved amount is ₱1,000',
            'amount_approved.max' => 'Approved amount cannot exceed requested amount',
            'deduction_schedule.required' => 'Deduction schedule is required',
            'deduction_schedule.in' => 'Invalid deduction schedule selected',
            'number_of_installments.required' => 'Number of installments is required',
            'number_of_installments.integer' => 'Number of installments must be a whole number',
            'number_of_installments.min' => 'Minimum 1 installment required',
            'number_of_installments.max' => 'Maximum 6 installments allowed',
            'approval_notes.string' => 'Approval notes must be text',
            'approval_notes.max' => 'Approval notes cannot exceed 500 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert numeric values to proper types
        if (is_string($this->amount_approved)) {
            $this->merge([
                'amount_approved' => (float) $this->amount_approved,
            ]);
        }

        if (is_string($this->number_of_installments)) {
            $this->merge([
                'number_of_installments' => (int) $this->number_of_installments,
            ]);
        }
    }
}
