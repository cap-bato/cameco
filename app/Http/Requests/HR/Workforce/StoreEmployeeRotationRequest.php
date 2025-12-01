<?php

namespace App\Http\Requests\HR\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields
            'name' => ['required', 'string', 'max:255', 'unique:employee_rotations,name,NULL,id,deleted_at,NULL'],
            'pattern_type' => ['required', Rule::in(['4x2', '6x1', '5x2', 'custom'])],
            'pattern_json' => ['required', 'array'],
            
            // Optional fields
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Rotation name is required',
            'name.unique' => 'A rotation with this name already exists',
            'pattern_type.required' => 'Pattern type is required',
            'pattern_type.in' => 'Pattern type must be one of: 4x2, 6x1, 5x2, custom',
            'pattern_json.required' => 'Pattern structure is required',
            'pattern_json.array' => 'Pattern must be a valid JSON object',
        ];
    }

    /**
     * Configure the validator instance.
     */
    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $patternJson = $this->input('pattern_json');
            
            if (!is_array($patternJson)) {
                $validator->errors()->add('pattern_json', 'Pattern must be a valid JSON object');
                return;
            }
            
            // Validate required fields in pattern
            if (!isset($patternJson['work_days']) || !isset($patternJson['rest_days']) || !isset($patternJson['pattern'])) {
                $validator->errors()->add('pattern_json', 'Pattern must contain work_days, rest_days, and pattern array');
                return;
            }
            
            $workDays = $patternJson['work_days'];
            $restDays = $patternJson['rest_days'];
            $pattern = $patternJson['pattern'];
            
            // Validate data types
            if (!is_int($workDays) || !is_int($restDays) || !is_array($pattern)) {
                $validator->errors()->add('pattern_json', 'Pattern fields must have correct types (work_days: int, rest_days: int, pattern: array)');
                return;
            }
            
            // Validate pattern length matches work_days + rest_days
            $expectedLength = $workDays + $restDays;
            if (count($pattern) !== $expectedLength) {
                $validator->errors()->add('pattern_json', "Pattern length must equal work_days ({$workDays}) + rest_days ({$restDays}) = {$expectedLength}, but got " . count($pattern));
                return;
            }
            
            // Count 1s and 0s in pattern
            $ones = count(array_filter($pattern, fn($v) => $v === 1));
            $zeros = count(array_filter($pattern, fn($v) => $v === 0));
            
            // Validate pattern contains only 1s and 0s
            if ($ones + $zeros !== count($pattern)) {
                $validator->errors()->add('pattern_json', 'Pattern must contain only 1s (work days) and 0s (rest days)');
                return;
            }
            
            // Validate correct count of 1s and 0s
            if ($ones !== $workDays) {
                $validator->errors()->add('pattern_json', "Pattern must contain exactly {$workDays} work days (1s), but got {$ones}");
                return;
            }
            
            if ($zeros !== $restDays) {
                $validator->errors()->add('pattern_json', "Pattern must contain exactly {$restDays} rest days (0s), but got {$zeros}");
                return;
            }
            
            // Validate against pattern type
            $patternType = $this->input('pattern_type');
            $expectedPatterns = [
                '4x2' => ['work_days' => 4, 'rest_days' => 2, 'pattern' => [1, 1, 1, 1, 0, 0]],
                '6x1' => ['work_days' => 6, 'rest_days' => 1, 'pattern' => [1, 1, 1, 1, 1, 1, 0]],
                '5x2' => ['work_days' => 5, 'rest_days' => 2, 'pattern' => [1, 1, 1, 1, 1, 0, 0]],
            ];
            
            if ($patternType !== 'custom' && isset($expectedPatterns[$patternType])) {
                $expected = $expectedPatterns[$patternType];
                if ($workDays !== $expected['work_days'] || $restDays !== $expected['rest_days'] || $pattern !== $expected['pattern']) {
                    $validator->errors()->add('pattern_json', "Pattern doesn't match {$patternType} pattern format");
                }
            }
        });
    }
}
