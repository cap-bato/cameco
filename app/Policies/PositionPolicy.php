<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

class PositionPolicy
{
    protected function isHrManager(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('HR Manager');
    }

    public function viewAny(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.positions.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $this->isHrManager($user) || $user->can('hr.positions.view');
    }

    public function create(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.positions.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $this->isHrManager($user) || $user->can('hr.positions.update');
    }

    public function delete(User $user, Position $position): bool
    {
        return $this->isHrManager($user) || $user->can('hr.positions.delete');
    }
}
