<?php

namespace App\Http\Requests\HR\Timekeeping;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateBadgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-badges');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for deactivating this badge.',
            'reason.string' => 'The reason must be a text value.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
        ];
    }
}
