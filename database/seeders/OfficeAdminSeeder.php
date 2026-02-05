<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OfficeAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Office Admin role and permissions...');

        // Define all admin permissions following naming convention: admin.{module}.{action}
        $permissions = [
            // Company Setup
            'admin.company.view' => 'View company configuration',
            'admin.company.edit' => 'Edit company configuration',

            // Business Rules
            'admin.business-rules.view' => 'View business rules configuration',
            'admin.business-rules.edit' => 'Edit business rules configuration',

            // Departments & Positions (Office Admin can manage through admin interface)
            'admin.departments.view' => 'View departments',
            'admin.departments.create' => 'Create departments',
            'admin.departments.edit' => 'Edit departments',
            'admin.departments.delete' => 'Delete departments',
            'admin.positions.view' => 'View positions',
            'admin.positions.create' => 'Create positions',
            'admin.positions.edit' => 'Edit positions',
            'admin.positions.delete' => 'Delete positions',

            // Leave Policies
            'admin.leave-policies.view' => 'View leave policies',
            'admin.leave-policies.create' => 'Create leave policies',
            'admin.leave-policies.edit' => 'Edit leave policies',
            'admin.leave-policies.delete' => 'Delete leave policies',

            // Payroll Rules
            'admin.payroll-rules.view' => 'View payroll rules',
            'admin.payroll-rules.edit' => 'Edit payroll rules',

            // System Configuration
            'admin.system-config.view' => 'View system configuration',
            'admin.system-config.edit' => 'Edit system configuration',

            // Approval Workflows
            'admin.approval-workflows.view' => 'View approval workflows',
            'admin.approval-workflows.edit' => 'Edit approval workflows',

            // Dashboard
            'admin.dashboard.view' => 'View Office Admin dashboard',
        ];

        // Create permissions
        $createdCount = 0;
        foreach ($permissions as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
            
            if ($permission->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        $this->command->info("✓ Created {$createdCount} new admin permissions");
        $this->command->info("✓ Total admin permissions: " . count($permissions));

        // Create Office Admin role
        $officeAdmin = Role::firstOrCreate(
            ['name' => 'Office Admin'],
            ['guard_name' => 'web']
        );

        // Assign all admin permissions to Office Admin role
        $officeAdmin->syncPermissions(array_keys($permissions));

        $this->command->info('✓ All admin permissions assigned to Office Admin role');

        // Ensure Superadmin role retains all permissions (including new admin permissions)
        $superadmin = Role::where('name', 'Superadmin')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo(Permission::all());
            $this->command->info('✓ Admin permissions assigned to Superadmin role');
        }

        // Create sample Office Admin user for testing
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@cameco.com'],
            [
                'name' => 'Office Admin',
                'username' => 'officeadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign Office Admin role to test user
        if (!$adminUser->hasRole('Office Admin')) {
            $adminUser->assignRole('Office Admin');
            $this->command->info('✓ Office Admin role assigned to admin@cameco.com');
        } else {
            $this->command->info('✓ User admin@cameco.com already has Office Admin role');
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Office Admin Setup Completed Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Role: Office Admin');
        $this->command->info('  Permissions: ' . count($permissions));
        $this->command->info('  Test User: admin@cameco.com');
        $this->command->info('  Password: password');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
