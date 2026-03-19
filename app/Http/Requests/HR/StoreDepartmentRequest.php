<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['HR Manager', 'HR Staff', 'Superadmin']);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->whereNull('deleted_at')],
            'code'        => ['nullable', 'string', 'max:32', Rule::unique('departments', 'code')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'   => ['boolean'],
        ];
    }
}
