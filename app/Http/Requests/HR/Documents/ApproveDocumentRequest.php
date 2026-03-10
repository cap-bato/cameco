<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('hr.documents.requests.approve') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'send_email' => ['boolean'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'template_id.exists' => 'The selected template does not exist.',
            'expiry_date.after' => 'Expiry date must be after effective date.',
        ];
    }
}
