<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeavePolicyPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\PositionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Employee::class => EmployeePolicy::class,
        Department::class => DepartmentPolicy::class,
        Position::class => PositionPolicy::class,
        LeavePolicy::class => LeavePolicyPolicy::class,
        LeaveRequest::class => LeaveRequestPolicy::class,
        // AttendancePolicy doesn't need a model mapping - uses Gate directly
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define authorization gates
        Gate::define('system.onboarding.initialize', function (User $user) {
            return $user->hasRole('Superadmin');
        });
    }
}
