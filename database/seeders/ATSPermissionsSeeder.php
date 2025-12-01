<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ATSPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Job Postings Permissions
        'recruitment.job_postings.view',
        'recruitment.job_postings.create',
        'recruitment.job_postings.update',
        'recruitment.job_postings.delete',
        'recruitment.job_postings.publish',
        'recruitment.job_postings.close',

        // Candidates Permissions
        'recruitment.candidates.view',
        'recruitment.candidates.create',
        'recruitment.candidates.update',
        'recruitment.candidates.delete',
        'recruitment.candidates.add_note',

        // Applications Permissions
        'recruitment.applications.view',
        'recruitment.applications.update',
        'recruitment.applications.shortlist',
        'recruitment.applications.reject',

        // Interviews Permissions
        'recruitment.interviews.view',
        'recruitment.interviews.create',
        'recruitment.interviews.update',
        'recruitment.interviews.cancel',
        'recruitment.interviews.add_feedback',

        // Hiring Pipeline Permissions
        'recruitment.hiring_pipeline.view',
        'recruitment.hiring_pipeline.update',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $hrManagerRole = Role::firstOrCreate(
            ['name' => 'HR Manager', 'guard_name' => 'web']
        );

        $hrManagerRole->givePermissionTo(self::PERMISSIONS);

        $this->command->info('ATS permissions seeded successfully!');
    }
}
