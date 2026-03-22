<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OfficeAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 1. Creates the Office Admin role and its permissions.
     * 2. Creates the test user account (admin@cameco.com).
     * 3. Creates the matching Profile + Employee record so the user
     *    appears properly in the HRIS like every other employee.
     */
    public function run(): void
    {
        $this->command->info('Creating Office Admin role and permissions...');

        // ── 1. Permissions ─────────────────────────────────────────────────
        $permissions = [
            // Company Setup
            'admin.company.view'                  => 'View company configuration',
            'admin.company.edit'                  => 'Edit company configuration',

            // Business Rules
            'admin.business-rules.view'           => 'View business rules configuration',
            'admin.business-rules.edit'           => 'Edit business rules configuration',

            // Departments & Positions
            'admin.departments.view'              => 'View departments',
            'admin.departments.create'            => 'Create departments',
            'admin.departments.edit'              => 'Edit departments',
            'admin.departments.delete'            => 'Delete departments',
            'admin.positions.view'                => 'View positions',
            'admin.positions.create'              => 'Create positions',
            'admin.positions.edit'                => 'Edit positions',
            'admin.positions.delete'              => 'Delete positions',

            // Leave Policies
            'admin.leave-policies.view'           => 'View leave policies',
            'admin.leave-policies.create'         => 'Create leave policies',
            'admin.leave-policies.edit'           => 'Edit leave policies',
            'admin.leave-policies.delete'         => 'Delete leave policies',

            // Payroll Rules
            'admin.payroll-rules.view'            => 'View payroll rules',
            'admin.payroll-rules.edit'            => 'Edit payroll rules',

            // System Configuration
            'admin.system-config.view'            => 'View system configuration',
            'admin.system-config.edit'            => 'Edit system configuration',

            // Approval Workflows
            'admin.approval-workflows.view'       => 'View approval workflows',
            'admin.approval-workflows.edit'       => 'Edit approval workflows',

            // Dashboard
            'admin.dashboard.view'                => 'View Office Admin dashboard',
        ];

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
        $this->command->info('✓ Total admin permissions: ' . count($permissions));

        // ── 2. Role ────────────────────────────────────────────────────────
        $officeAdmin = Role::firstOrCreate(
            ['name' => 'Office Admin'],
            ['guard_name' => 'web']
        );

        $officeAdmin->syncPermissions(array_keys($permissions));
        $this->command->info('✓ All admin permissions assigned to Office Admin role');

        // Ensure Superadmin retains everything
        $superadmin = Role::where('name', 'Superadmin')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo(Permission::all());
            $this->command->info('✓ Admin permissions propagated to Superadmin role');
        }

        // ── 3. User account ────────────────────────────────────────────────
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@cameco.com'],
            [
                'name'              => 'Bob Percival',
                'username'          => 'officeadmin',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (!$adminUser->hasRole('Office Admin')) {
            $adminUser->assignRole('Office Admin');
            $this->command->info('✓ Office Admin role assigned to admin@cameco.com');
        } else {
            $this->command->info('✓ admin@cameco.com already has Office Admin role');
        }

        // ── 4. Profile + Employee record ───────────────────────────────────
        // Only create if no employee record exists for this account yet.
        $createdBy  = User::where('email', 'superadmin@cameco.com')->value('id') ?? 1;
        $empNumber  = 'EMP-OA-0001';

        if (!Employee::where('employee_number', $empNumber)->exists()
            && !Profile::where('email', 'admin@cameco.com')->exists()
        ) {
            // Resolve department and position — fall back gracefully if not seeded yet
            $adminDept = Department::where('code', 'HR')->first()
                ?? Department::first();

            $adminPosition = Position::where('title', 'Office Administrator')->first()
                ?? Position::firstOrCreate(
                    ['title' => 'Office Administrator'],
                    [
                        'department_id' => $adminDept?->id,
                        'level'         => 'manager',
                        'is_active'     => true,
                    ]
                );

            $profile = Profile::create([
                'first_name'      => 'Bob',
                'middle_name'     => null,
                'last_name'       => 'Percival',
                'suffix'          => null,
                'date_of_birth'   => '1982-04-10',
                'gender'          => 'male',
                'civil_status'    => 'married',
                'mobile'          => '+63 917 000 0002',
                'email'           => 'admin@cameco.com',
                'current_address' => 'Administration Office, Cathay Metal Corporation',
                'permanent_address' => 'Administration Office, Cathay Metal Corporation',
                'emergency_contact_name'         => 'Admin Emergency Contact',
                'emergency_contact_relationship' => 'Spouse',
                'emergency_contact_phone'        => '+63 917 000 0098',
                'sss_number'        => '33-0000002-2',
                'tin_number'        => '000-000-002-000',
                'philhealth_number' => '00-000000002-2',
                'pagibig_number'    => '0000-0000-0002',
            ]);

            Employee::create([
                'employee_number'     => $empNumber,
                'profile_id'          => $profile->id,
                'department_id'       => $adminDept?->id,
                'position_id'         => $adminPosition->id,
                'employment_type'     => 'regular',
                'date_hired'          => '2016-03-01',
                'regularization_date' => '2016-09-01',
                'status'              => 'active',
                'created_by'          => $createdBy,
                'updated_by'          => $createdBy,
            ]);

            $this->command->info('✓ Profile and Employee record created for admin@cameco.com');
        } else {
            $this->command->info('✓ Profile/Employee for admin@cameco.com already exists — skipped');
        }

        // ── Summary ────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Office Admin Setup Completed Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Role:        Office Admin');
        $this->command->info('  Permissions: ' . count($permissions));
        $this->command->info('  Test User:   admin@cameco.com');
        $this->command->info('  Password:    password');
        $this->command->info('  Employee No: ' . $empNumber);
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}