<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Determine if the user can view any departments.
     * Note: HR users and Office Admin can VIEW departments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('hr.departments.view') 
            || $user->can('hr.dashboard.view')
            || $user->can('admin.departments.view');
    }

    /**
     * Determine if the user can view a specific department.
     */
    public function view(User $user, Department $department): bool
    {
        return $user->can('hr.departments.view') 
            || $user->can('hr.dashboard.view')
            || $user->can('admin.departments.view');
    }

    /**
     * Determine if the user can create departments.
     * HR Manager and Office Admin can manage departments.
     */
    public function create(User $user): bool
    {
        return $user->can('hr.departments.manage')
            || $user->can('admin.departments.create');
    }

    /**
     * Determine if the user can update a department.
     * HR Manager and Office Admin can manage departments.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->can('hr.departments.manage')
            || $user->can('admin.departments.edit');
    }

    /**
     * Determine if the user can delete a department.
     * HR Manager and Office Admin can manage departments.
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->can('hr.departments.manage')
            || $user->can('admin.departments.delete');
    }
}
