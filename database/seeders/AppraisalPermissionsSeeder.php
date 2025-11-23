<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppraisalPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all appraisal-related permissions
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

        // Create or get permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        // Get or create the HR Manager role
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR Manager'], ['guard_name' => 'web']);

        // Assign all permissions to HR Manager role
        $hrManagerRole->syncPermissions($permissions);

        // Also get Admin role if it exists and grant permissions
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(array_merge($adminRole->permissions->pluck('name')->toArray(), $permissions));
        }
    }
}
