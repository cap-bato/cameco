<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    protected function isHrManager(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('HR Manager');
    }

    public function viewAny(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.view');
    }

    public function create(User $user): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.delete');
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $this->isHrManager($user) || $user->can('hr.employees.restore');
    }
}
