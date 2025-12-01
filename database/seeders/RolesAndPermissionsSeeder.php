<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Base permissions (existing)
        $basePerms = [
            'users.create', 'users.update', 'users.delete', 'users.view',
            'workforce.schedules.create', 'workforce.assignments.update',
            'timekeeping.attendance.create', 'timekeeping.reports.view',
            'system.settings.update',
            'system.dashboard.view',
        ];

        // Phase 8: HR permissions
        $hrPermissions = [
            // Dashboard
            'hr.dashboard.view',

            // Employee Management
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.update',
            'hr.employees.delete', // archive
            'hr.employees.restore',

            // Department Management
            'hr.departments.view',
            'hr.departments.create',
            'hr.departments.update',
            'hr.departments.delete',

            // Position Management
            'hr.positions.view',
            'hr.positions.create',
            'hr.positions.update',
            'hr.positions.delete',

            // Leave Management
            'hr.leave-requests.view',
            'hr.leave-requests.create',
            'hr.leave-requests.approve',
            'hr.leave-requests.reject',
            'hr.leave-policies.view',
            'hr.leave-policies.create',
            'hr.leave-policies.update',
            'hr.leave-policies.manage',
            'hr.leave-balances.view',

            // Timekeeping
            'hr.timekeeping.view',
            'hr.timekeeping.manage',
            'hr.timekeeping.attendance.create',
            'hr.timekeeping.attendance.update',
            'hr.timekeeping.overtime.view',
            'hr.timekeeping.overtime.approve',

            // ATS (Applicant Tracking)
            'hr.ats.view',
            'hr.ats.candidates.view',
            'hr.ats.candidates.create',
            'hr.ats.candidates.update',
            'hr.ats.candidates.delete',
            'hr.ats.applications.view',
            'hr.ats.applications.update',
            'hr.ats.interviews.schedule',

            // Workforce Management
            'hr.workforce.schedules.view',
            'hr.workforce.schedules.create',
            'hr.workforce.schedules.update',
            'hr.workforce.rotations.view',
            'hr.workforce.rotations.create',
            'hr.workforce.assignments.view',
            'hr.workforce.assignments.manage',

            // Appraisals
            'hr.appraisals.view',
            'hr.appraisals.conduct',

            // Sensitive Data Access
            'hr.employees.view_salary',
            'hr.employees.view_government_ids',

            // Reports
            'hr.reports.view',
            'hr.reports.export',
        ];

        $timekeepingPermissions = [
            // Attendance
            'timekeeping.attendance.view',
            'timekeeping.attendance.create',
            'timekeeping.attendance.update',
            'timekeeping.attendance.delete',
            'timekeeping.attendance.correct',

            // Overtime
            'timekeeping.overtime.view',
            'timekeeping.overtime.create',
            'timekeeping.overtime.update',
            'timekeeping.overtime.delete',

            // Import
            'timekeeping.import.view',
            'timekeeping.import.create',

            // Analytics
            'timekeeping.analytics.view',
        ];

        $atsPermissions = ATSPermissionsSeeder::PERMISSIONS;

        $perms = array_values(
            array_unique(
                array_merge($basePerms, $hrPermissions, $timekeepingPermissions, $atsPermissions)
            )
        );

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Roles
        $superadmin = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $superadmin->givePermissionTo(Permission::all()); // Always retains all permissions

        $hrManager = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        // Grant HR Manager all HR-aligned permissions (do not revoke existing)
        $hrManager->givePermissionTo(array_merge($hrPermissions, $timekeepingPermissions, $atsPermissions));
    }
}
