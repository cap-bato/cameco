<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name'        => 'Human Resources',
                'description' => 'Manages employee relations, recruitment, and organizational development',
                'code'        => 'HR',
                'is_active'   => true,
            ],
            [
                'name'        => 'Information Technology',
                'description' => 'Manages technology infrastructure, software development, and technical support',
                'code'        => 'IT',
                'is_active'   => true,
            ],
            [
                'name'        => 'Finance & Accounting',
                'description' => 'Manages financial operations, budgeting, and accounting',
                'code'        => 'FIN',
                'is_active'   => true,
            ],
            [
                'name'        => 'Operations',
                'description' => 'Manages day-to-day business operations and production',
                'code'        => 'OPS',
                'is_active'   => true,
            ],
            [
                'name'        => 'Sales & Marketing',
                'description' => 'Manages sales operations, customer relations, and marketing strategies',
                'code'        => 'SALES',
                'is_active'   => true,
            ],
            [
                'name'        => 'Production',
                'description' => 'Manages manufacturing and production processes',
                'code'        => 'PROD',
                'is_active'   => true,
            ],
            [
                'name'        => 'Quality Assurance',
                'description' => 'Ensures product quality and compliance with standards',
                'code'        => 'QA',
                'is_active'   => true,
            ],
            [
                'name'        => 'Logistics & Supply Chain',
                'description' => 'Manages inventory, warehousing, and distribution',
                'code'        => 'LOG',
                'is_active'   => true,
            ],
            [
                'name'        => 'Research & Development',
                'description' => 'Manages product development and innovation',
                'code'        => 'RND',
                'is_active'   => true,
            ],
            [
                'name'        => 'Administration',
                'description' => 'Manages general administrative and support services',
                'code'        => 'ADMIN',
                'is_active'   => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                $department
            );
        }

        $this->command->info('Departments seeded successfully!');
    }
}