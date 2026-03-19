<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['HR Manager', 'HR Staff', 'Superadmin']);
    }

    public function rules(): array
    {
        $id = $this->route('position')?->id ?? $this->route('id');

        return [
            'title'       => ['required', 'string', 'max:150', Rule::unique('positions', 'title')->ignore($id)->whereNull('deleted_at')],
            'code'        => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
            'level'       => ['required', 'string', 'max:50'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'reports_to'  => ['nullable', 'integer', 'exists:positions,id'],
            'salary_min'  => ['nullable', 'integer', 'min:0'],
            'salary_max'  => ['nullable', 'integer', 'gte:salary_min'],
            'is_active'   => ['boolean'],
        ];
    }
}
