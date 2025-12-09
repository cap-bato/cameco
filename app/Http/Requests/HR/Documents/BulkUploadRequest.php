<?php

namespace App\Http\Requests\HR\Documents;

use Illuminate\Foundation\Http\FormRequest;

class BulkUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by middleware (hr.documents.bulk-upload permission)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'max:5120', // 5MB in kilobytes
                'mimes:csv,txt'
            ],
            'zip_file' => [
                'required',
                'file',
                'max:102400', // 100MB in kilobytes
                'mimes:zip'
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please upload a CSV file containing document information.',
            'csv_file.max' => 'The CSV file size must not exceed 5MB.',
            'csv_file.mimes' => 'The file must be a CSV file.',
            'zip_file.required' => 'Please upload a ZIP file containing the documents.',
            'zip_file.max' => 'The ZIP file size must not exceed 100MB.',
            'zip_file.mimes' => 'The file must be a ZIP archive.',
        ];
    }
}
