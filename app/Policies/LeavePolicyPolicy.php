<?php

namespace App\Policies;

use App\Models\LeavePolicy;
use App\Models\User;

class LeavePolicyPolicy
{
    /**
     * Determine if the user can view any leave policies.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin.leave-policies.view');
    }

    /**
     * Determine if the user can view a specific leave policy.
     */
    public function view(User $user, LeavePolicy $leavePolicy): bool
    {
        return $user->can('admin.leave-policies.view');
    }

    /**
     * Determine if the user can create leave policies.
     */
    public function create(User $user): bool
    {
        return $user->can('admin.leave-policies.create');
    }

    /**
     * Determine if the user can update a leave policy.
     */
    public function update(User $user, LeavePolicy $leavePolicy): bool
    {
        return $user->can('admin.leave-policies.edit');
    }

    /**
     * Determine if the user can delete a leave policy.
     * 
     * Only Office Admin can delete, and only if there are no active balances
     * or pending/approved leave requests associated with this policy.
     */
    public function delete(User $user, LeavePolicy $leavePolicy): bool
    {
        if (!$user->can('admin.leave-policies.delete')) {
            return false;
        }

        // Check for active employee balances
        $activeBalances = $leavePolicy->balances()
            ->whereHas('employee', function ($q) {
                $q->where('is_active', true);
            })
            ->count();

        if ($activeBalances > 0) {
            return false;
        }

        // Check for pending or approved leave requests
        $activeRequests = $leavePolicy->requests()
            ->whereIn('status', ['pending', 'approved', 'pending_final_approval'])
            ->count();

        if ($activeRequests > 0) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can restore a soft-deleted leave policy.
     */
    public function restore(User $user, LeavePolicy $leavePolicy): bool
    {
        return $user->can('admin.leave-policies.edit');
    }

    /**
     * Determine if the user can permanently delete a leave policy.
     */
    public function forceDelete(User $user, LeavePolicy $leavePolicy): bool
    {
        // Force delete should be extremely restricted - only Superadmin
        return $user->hasRole('Superadmin');
    }
}
