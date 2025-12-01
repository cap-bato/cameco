<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    protected function isHrManager(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('HR Manager');
    }

    public function viewAny(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->isHrManager($user) || $user->can('hr.departments.view');
    }

    public function create(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->isHrManager($user) || $user->can('hr.departments.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->isHrManager($user) || $user->can('hr.departments.delete');
    }
}
