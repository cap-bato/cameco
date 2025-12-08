<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware (hr.documents.reject permission)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Please provide a reason for rejecting this document.',
            'rejection_reason.max' => 'Rejection reason must not exceed 500 characters.',
        ];
    }
}
