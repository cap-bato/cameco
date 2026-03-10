<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class TimekeepingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create all Timekeeping permissions
        $permissions = [
            // Attendance Permissions
            'timekeeping.attendance.view',
            'timekeeping.attendance.create',
            'timekeeping.attendance.update',
            'timekeeping.attendance.delete',
            'timekeeping.attendance.correct',
            'timekeeping.attendance.bulk_entry',
            'hr.timekeeping.attendance.view',
            'hr.timekeeping.attendance.create',
            'hr.timekeeping.attendance.update',
            'hr.timekeeping.attendance.delete',
            'hr.timekeeping.attendance.correct',
            'hr.timekeeping.attendance.finalize',

            // Attendance Correction Permissions (Task 4.4)
            'hr.timekeeping.corrections.create',
            'hr.timekeeping.corrections.approve',

            // Overtime Permissions
            'timekeeping.overtime.view',
            'timekeeping.overtime.create',
            'timekeeping.overtime.update',
            'timekeeping.overtime.delete',
            'timekeeping.overtime.approve',
            'timekeeping.overtime.process',

            // Import Permissions
            'timekeeping.import.view',
            'timekeeping.import.create',
            'timekeeping.import.upload',
            'timekeeping.import.process',
            'timekeeping.import.delete',

            // Analytics Permissions
            'timekeeping.analytics.view',
            'timekeeping.analytics.export',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        // Get HR Manager role and assign all permissions
        $hrManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'HR Manager'],
            ['guard_name' => 'web']
        );

        // Assign all Timekeeping permissions to HR Manager role
        $hrManagerRole->givePermissionTo($permissions);

        // Get Payroll Officer role and assign finalize permission
        $payrollOfficerRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Payroll Officer'],
            ['guard_name' => 'web']
        );

        // Assign finalize permission to Payroll Officer
        $payrollOfficerRole->givePermissionTo('hr.timekeeping.attendance.finalize');

        $this->command->info('✓ Timekeeping permissions seeded successfully!');
    }
}
