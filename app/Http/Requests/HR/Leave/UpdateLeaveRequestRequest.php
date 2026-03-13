<?php

namespace App\Http\Requests\HR\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        // Primary authorization path: permission-based.
        if (method_exists($user, 'can') && $user->can('hr.leave-requests.approve')) {
            return true;
        }

        // Fallback for role-based setups where permission sync may lag.
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['HR Staff', 'HR Manager', 'Office Admin']);
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'approval_comments' => 'nullable|string|max:1000',
        ];
    }
}
