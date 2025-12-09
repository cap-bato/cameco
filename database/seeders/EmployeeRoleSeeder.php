<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EmployeeRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates Employee role with self-service permissions for the Employee Portal.
     * Employees can view their own data, submit leave requests, report attendance issues,
     * view payslips, and manage their notifications.
     */
    public function run(): void
    {
        $this->command->info('Creating Employee role and permissions...');

        // Define all employee permissions following naming convention: employee.{resource}.{action}
        $permissions = [
            // Dashboard
            'employee.dashboard.view' => 'View employee portal dashboard',

            // Personal Information (Self-Service)
            'employee.profile.view' => 'View own profile information',
            'employee.profile.update' => 'Request profile updates (contact info) - requires HR approval',

            // Attendance (Self-Service)
            'employee.attendance.view' => 'View own attendance records and time logs',
            'employee.attendance.report' => 'Report attendance issues (missing punch, wrong time)',

            // Payslips (Self-Service)
            'employee.payslips.view' => 'View own payslips',
            'employee.payslips.download' => 'Download own payslips as PDF',

            // Leave Management (Self-Service)
            'employee.leave.view-balance' => 'View own leave balances by type',
            'employee.leave.view-history' => 'View own leave request history',
            'employee.leave.submit' => 'Submit leave requests',
            'employee.leave.cancel' => 'Cancel pending leave requests',

            // Notifications
            'employee.notifications.view' => 'View own notifications',
            'employee.notifications.manage' => 'Mark notifications as read, delete notifications',
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

        $this->command->info("✓ Created {$createdCount} new employee permissions");
        $this->command->info("✓ Total employee permissions: " . count($permissions));

        // Create Employee role
        $employee = Role::firstOrCreate(
            ['name' => 'Employee'],
            ['guard_name' => 'web']
        );

        // Assign all employee permissions to Employee role
        $employee->syncPermissions(array_keys($permissions));

        $this->command->info('✓ All employee permissions assigned to Employee role');

        // Ensure Superadmin role retains all permissions (including new employee permissions)
        $superadmin = Role::where('name', 'Superadmin')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo(Permission::all());
            $this->command->info('✓ Employee permissions assigned to Superadmin role');
        }

        $this->command->newLine();
        $this->command->info('Creating test employee users...');

        // Create test employee users linked to existing employee records
        $this->createTestEmployeeUsers($employee);

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Employee Role Setup Completed Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Role: Employee');
        $this->command->info('  Permissions: ' . count($permissions));
        $this->command->info('  Purpose: Self-service portal access');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }

    /**
     * Create test employee users linked to existing employee records.
     * 
     * @param Role $employeeRole The Employee role to assign to test users
     */
    private function createTestEmployeeUsers(Role $employeeRole): void
    {
        // Query existing employees who don't have a user_id yet
        $employees = \App\Models\Employee::whereNull('user_id')
            ->where('status', 'active')
            ->take(5)
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('⚠ No available employees found without user accounts');
            $this->command->info('  You may need to run EmployeeSeeder first or manually link users to employees');
            return;
        }

        $createdUsers = 0;
        foreach ($employees as $employee) {
            // Get profile information for the employee
            $profile = $employee->profile;
            if (!$profile) {
                $this->command->warn("⚠ Employee #{$employee->employee_number} has no profile, skipping...");
                continue;
            }

            // Create user account for this employee
            $username = 'employee' . $employee->employee_number;
            $email = strtolower($username) . '@cameco.com';

            $user = \App\Models\User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $profile->full_name ?? "Employee {$employee->employee_number}",
                    'username' => $username,
                    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                    'email_verified_at' => now(),
                ]
            );

            // Link user to employee record
            if (!$employee->user_id) {
                $employee->user_id = $user->id;
                $employee->save();
            }

            // Assign Employee role to test user
            if (!$user->hasRole('Employee')) {
                $user->assignRole($employeeRole);
                $createdUsers++;
                $this->command->info("✓ Created user {$email} → Employee #{$employee->employee_number}");
            } else {
                $this->command->info("✓ User {$email} already has Employee role");
            }
        }

        if ($createdUsers > 0) {
            $this->command->newLine();
            $this->command->info("═══════════════════════════════════════════════════════");
            $this->command->info("  Test Employee Users Created: {$createdUsers}");
            $this->command->info("═══════════════════════════════════════════════════════");
            $this->command->info("  Username Pattern: employee[EMPLOYEE_NUMBER]");
            $this->command->info("  Email Pattern: employee[EMPLOYEE_NUMBER]@cameco.com");
            $this->command->info("  Password: password123");
            $this->command->info("═══════════════════════════════════════════════════════");
        }
    }
}
