<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OffboardingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Core offboarding access
            'hr.offboarding.view',
            'hr.offboarding.create',
            'hr.offboarding.update',
            'hr.offboarding.cancel',
            'hr.offboarding.complete',

            // Clearance
            'hr.offboarding.clearance.view',
            'hr.offboarding.clearance.approve',
            'hr.offboarding.clearance.waive',
            'hr.offboarding.clearance.edit',

            // Exit interview
            'hr.offboarding.exit-interview.view',
            'hr.offboarding.exit-interview.complete',

            // Company assets
            'hr.offboarding.assets.view',
            'hr.offboarding.assets.create',
            'hr.offboarding.assets.update',

            // Documents
            'hr.offboarding.documents.view',
            'hr.offboarding.documents.generate',
            'hr.offboarding.documents.upload',
            'hr.offboarding.documents.approve',

            // Reports / analytics
            'hr.offboarding.reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Grant all offboarding permissions to HR Manager
        $hrManager = Role::where('name', 'HR Manager')->first();
        if ($hrManager) {
            $hrManager->givePermissionTo($permissions);
        }

        // Grant all offboarding permissions to HR Staff
        $hrStaff = Role::where('name', 'HR Staff')->first();
        if ($hrStaff) {
            $hrStaff->givePermissionTo($permissions);
        }

        // Superadmin always has all permissions via Permission::all() — no action needed
    }
}
