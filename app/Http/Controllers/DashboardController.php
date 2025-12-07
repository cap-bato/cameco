<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Show the appropriate dashboard based on the authenticated user's role.
     * 
     * Priority order:
     * 1. Payroll Officer → /payroll/dashboard
     * 2. HR Manager/HR Staff/HR → /hr/dashboard
     * 3. Superadmin → /system/dashboard
     * 4. Default → /dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Log user roles for debugging
        $userRoles = $user->getRoleNames()->toArray();
        \Log::debug('DashboardController - User roles', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $userRoles,
        ]);

        // Check Office Admin FIRST (most specific for configuration)
        if ($user->hasRole('Office Admin')) {
            \Log::debug('DashboardController - Routing to Office Admin Dashboard');
            return redirect()->route('admin.dashboard');
        }

        // Check Payroll Officer SECOND
        if ($user->hasRole('Payroll Officer')) {
            \Log::debug('DashboardController - Routing to Payroll Dashboard');
            return redirect()->route('payroll.dashboard');
        }

        // Check HR roles THIRD
        if ($user->hasRole('HR Manager') || $user->hasRole('HR Staff') || $user->hasRole('HR')) {
            \Log::debug('DashboardController - Routing to HR Dashboard');
            return redirect()->route('hr.dashboard');
        }

        // Check Employee role FOURTH (employee self-service portal)
        if ($user->hasRole('Employee')) {
            \Log::debug('DashboardController - Routing to Employee Portal Dashboard');
            return redirect()->route('employee.dashboard');
        }

        // Check Superadmin LAST (most general)
        if ($user->hasRole('Superadmin')) {
            \Log::debug('DashboardController - Routing to System Dashboard');
            return redirect()->route('system.dashboard');
        }

        // Default fallback for users with no special roles
        \Log::debug('DashboardController - Rendering default dashboard');
        return Inertia::render('dashboard');
    }
}
