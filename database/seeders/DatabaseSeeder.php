<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'superadmin@cameco.com'],
            [
                'name' => 'Test User',
                'username' => 'superadmin',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        
        User::firstOrCreate(
            ['email' => 'hrmanager@cameco.com'],
            [
                'name' => 'HR Manager',
                'username' => 'hrmanager',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        
        // Seed Leave policies first
        if (class_exists(\Database\Seeders\LeavePolicySeeder::class)) {
            $this->call(\Database\Seeders\LeavePolicySeeder::class);
        }

        // Seed roles and permissions (Spatie) and assign roles to users
        if (class_exists(\Database\Seeders\RolesAndPermissionsSeeder::class)) {
            $this->call(\Database\Seeders\RolesAndPermissionsSeeder::class);

            // Assign Superadmin role
            $superadmin = User::where('email', 'superadmin@cameco.com')->first();
            if ($superadmin && method_exists($superadmin, 'assignRole')) {
                try {
                    $superadmin->assignRole('Superadmin');
                } catch (\Throwable $e) {
                    // ignore assignment errors during seeding
                }
            }

            // Assign HR Manager role
            $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
            if ($hrManager && method_exists($hrManager, 'assignRole')) {
                try {
                    $hrManager->assignRole('HR Manager');
                } catch (\Throwable $e) {
                    // ignore assignment errors during seeding
                }
            }
        }

        // Seed ATS permissions
        if (class_exists(\Database\Seeders\ATSPermissionsSeeder::class)) {
            $this->call(\Database\Seeders\ATSPermissionsSeeder::class);
        }

        // Seed Timekeeping permissions
        if (class_exists(\Database\Seeders\TimekeepingPermissionsSeeder::class)) {
            $this->call(\Database\Seeders\TimekeepingPermissionsSeeder::class);
        }

        // Seed Workforce Management permissions
        if (class_exists(\Database\Seeders\WorkforceManagementPermissionsSeeder::class)) {
            $this->call(\Database\Seeders\WorkforceManagementPermissionsSeeder::class);
        }

        // Seed Payroll permissions
        if (class_exists(\Database\Seeders\PayrollPermissionsSeeder::class)) {
            $this->call(\Database\Seeders\PayrollPermissionsSeeder::class);
        }

        // Seed Payroll Officer account
        if (class_exists(\Database\Seeders\PayrollOfficerAccountSeeder::class)) {
            $this->call(\Database\Seeders\PayrollOfficerAccountSeeder::class);
        }

        // Seed Office Admin role and permissions (must run after RolesAndPermissionsSeeder)
        if (class_exists(\Database\Seeders\OfficeAdminSeeder::class)) {
            $this->call(\Database\Seeders\OfficeAdminSeeder::class);
        }

        // Seed Employee role and permissions (must run after RolesAndPermissionsSeeder)
        if (class_exists(\Database\Seeders\EmployeeRoleSeeder::class)) {
            $this->call(\Database\Seeders\EmployeeRoleSeeder::class);
        }

        if (class_exists(\Database\Seeders\HRStaffAccountSeeder::class)) {
            $this->call(\Database\Seeders\HRStaffAccountSeeder::class);
        }

        if (class_exists(\Database\Seeders\SLASeeder::class)) {
            $this->call(\Database\Seeders\SLASeeder::class);
        }

        // Seed cron jobs
        if (class_exists(\Database\Seeders\CronJobSeeder::class)) {
            $this->call(\Database\Seeders\CronJobSeeder::class);
        }

        // Seed default security policies
        if (class_exists(\Database\Seeders\SecurityPolicySeeder::class)) {
            $this->call(\Database\Seeders\SecurityPolicySeeder::class);
        }

        // Seed default security logs
        if (class_exists(\Database\Seeders\SecurityAuditLogSeeder::class)) {
            $this->call(\Database\Seeders\SecurityAuditLogSeeder::class);
        }

        // Seed system settings
        if (class_exists(\Database\Seeders\SystemSettingsSeeder::class)) {
            $this->call(\Database\Seeders\SystemSettingsSeeder::class);
        }

        // Seed System Error Logs
        if (class_exists(\Database\Seeders\SystemErrorLogSeeder::class)) {
            $this->call(\Database\Seeders\SystemErrorLogSeeder::class);
        }

        // Seed Scheduled Jobs
        if (class_exists(\Database\Seeders\ScheduledJobsSeeder::class)) {
            $this->call(\Database\Seeders\ScheduledJobsSeeder::class);
        }

        // Seed System Health Checks
        if (class_exists(\Database\Seeders\SystemHealthSeeder::class)) {
            $this->call(\Database\Seeders\SystemHealthSeeder::class);
        }

        // Seed Support Contract Settings
        if (class_exists(\Database\Seeders\SupportContractSettingsSeeder::class)) {
            $this->call(\Database\Seeders\SupportContractSettingsSeeder::class);
        }

         // Seed HR data
         
        if (class_exists(\Database\Seeders\DepartmentSeeder::class)) {
            $this->call(\Database\Seeders\DepartmentSeeder::class);
        }

        if (class_exists(\Database\Seeders\PositionSeeder::class)) {
            $this->call(\Database\Seeders\PositionSeeder::class);
        }
        
        if (class_exists(\Database\Seeders\EmployeeSeeder::class)) {
            $this->call(\Database\Seeders\EmployeeSeeder::class);
        }

        // Seed Workforce Management data
        if (class_exists(\Database\Seeders\WorkforceSeeder::class)) {
            $this->call(\Database\Seeders\WorkforceSeeder::class);
        }

    }


}
