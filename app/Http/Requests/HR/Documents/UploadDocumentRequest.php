<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware (hr.documents.upload permission)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'document_category' => [
                'required',
                'string',
                'in:personal,educational,employment,medical,contracts,benefits,performance,separation,government,special'
            ],
            'document_type' => ['required', 'string', 'max:100'],
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimes:pdf,jpg,jpeg,png,docx'
            ],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'document_category.required' => 'Please select a document category.',
            'document_category.in' => 'The selected document category is invalid.',
            'document_type.required' => 'Please specify the document type.',
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'The file size must not exceed 10MB.',
            'file.mimes' => 'The file must be a PDF, JPEG, PNG, or DOCX file.',
            'expires_at.after' => 'The expiry date must be a future date.',
            'notes.max' => 'Notes must not exceed 500 characters.',
        ];
    }
}
