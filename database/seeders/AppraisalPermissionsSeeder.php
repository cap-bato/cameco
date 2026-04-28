<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppraisalPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * KEY FIX: syncPermissions() was used here previously, which REPLACES all
     * permissions on the role with only the appraisal set — wiping out every
     * permission granted by RolesAndPermissionsSeeder and every other module
     * seeder that ran before this one. Always use givePermissionTo() in seeders
     * so permissions accumulate rather than overwrite.
     */
    public function run(): void
    {
        $permissions = [
            // Appraisal Cycles
            'appraisal.cycles.view',
            'appraisal.cycles.create',
            'appraisal.cycles.edit',
            'appraisal.cycles.assign',
            'appraisal.cycles.close',

            // Appraisals
            'appraisal.view',
            'appraisal.create',
            'appraisal.edit',
            'appraisal.submit_feedback',

            // Performance Metrics
            'performance.metrics.view',
            'performance.metrics.export',

            // Rehire Recommendations
            'rehire.recommendations.view',
            'rehire.recommendations.override',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager'], ['guard_name' => 'web']);
        $hrManagerRole->givePermissionTo($permissions);  // additive, not destructive

        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);  // additive, not destructive
        }

        $this->command->info('Appraisal permissions seeded successfully!');
    }
}