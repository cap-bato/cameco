<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    /**
     * Determine if the user can view any positions.
     * Note: HR users and Office Admin can VIEW positions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.positions.view') 
            || $user->can('hr.dashboard.view')
            || $user->can('admin.positions.view');
    }

    /**
     * Determine if the user can view a specific position.
     */
    public function view(User $user, Position $position): bool
    {
        return $user->can('hr.positions.view') 
            || $user->can('hr.dashboard.view')
            || $user->can('admin.positions.view');
    }

    /**
     * Determine if the user can create positions.
     * HR Manager and Office Admin can manage positions.
     */
    public function create(User $user): bool
    {
        return $user->can('hr.positions.manage')
            || $user->can('admin.positions.create');
    }

    /**
     * Determine if the user can update a position.
     * HR Manager and Office Admin can manage positions.
     */
    public function update(User $user, Position $position): bool
    {
        return $user->can('hr.positions.manage')
            || $user->can('admin.positions.edit');
    }

    /**
     * Determine if the user can delete a position.
     * HR Manager and Office Admin can manage positions.
     */
    public function delete(User $user, Position $position): bool
    {
        return $user->can('hr.positions.manage')
            || $user->can('admin.positions.delete');
    }
}
