<?php

namespace App\Http\Requests\HR\Timekeeping;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceBadgeRequest extends FormRequest
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
            'reason' => ['required', 'in:lost,stolen,damaged,malfunctioning,upgrade,other'],
            'reason_notes' => ['required_if:reason,other', 'nullable', 'string', 'max:500'],
            'new_card_uid' => [
                'required',
                'string',
                'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
                'unique:rfid_card_mappings,card_uid',
            ],
            'card_type' => ['nullable', 'in:mifare,desfire,em4100'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'replacement_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please select a reason for the badge replacement.',
            'reason.in' => 'The selected reason is invalid.',
            'reason_notes.required_if' => 'Please provide additional notes when selecting "Other" as the reason.',
            'new_card_uid.required' => 'Please enter the new card UID.',
            'new_card_uid.regex' => 'Card UID must be in format XX:XX:XX:XX:XX:XX.',
            'new_card_uid.unique' => 'This card UID is already registered in the system.',
            'card_type.in' => 'The selected card type is invalid.',
            'expires_at.date' => 'The expiration date must be a valid date.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'replacement_fee.numeric' => 'The replacement fee must be a number.',
            'replacement_fee.min' => 'The replacement fee cannot be negative.',
            'notes.max' => 'The notes cannot exceed 500 characters.',
        ];
    }
}
