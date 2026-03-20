<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ── Default Users ──────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'superadmin@cameco.com'],
            [
                'name'              => 'Alex Tamayo',
                'username'          => 'superadmin',
                'password'          => 'password',
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'hrmanager@cameco.com'],
            [
                'name'              => 'Mitch Magno',
                'username'          => 'hrmanager',
                'password'          => 'password',
                'email_verified_at' => now(),
            ]
        );



        // ── Roles & Base Permissions (must run first) ──────────────────────
        $this->call([
            LeavePolicySeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        // Assign roles to the default users
        $superadmin = User::where('email', 'superadmin@cameco.com')->first();
        if ($superadmin && method_exists($superadmin, 'assignRole')) {
            try { $superadmin->assignRole('Superadmin'); } catch (\Throwable) {}
        }

        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        if ($hrManager && method_exists($hrManager, 'assignRole')) {
            try { $hrManager->assignRole('HR Manager'); } catch (\Throwable) {}
        }

        // ── Permission Extensions (must run after RolesAndPermissionsSeeder) ──
        $this->call([
            ATSPermissionsSeeder::class,
            TimekeepingPermissionsSeeder::class,
            BadgeManagementPermissionsSeeder::class,
            WorkforceManagementPermissionsSeeder::class,
            DocumentManagementPermissionsSeeder::class,
            PayrollPermissionsSeeder::class,
            OffboardingPermissionsSeeder::class,
            AppraisalPermissionsSeeder::class,
        ]);

        // ── Additional User Accounts (must run after roles) ────────────────
        $this->call([
            PayrollOfficerAccountSeeder::class,
            OfficeAdminSeeder::class,
            EmployeeRoleSeeder::class,
            HRStaffAccountSeeder::class,
        ]);

        // ── System & Config Data ───────────────────────────────────────────
        $this->call([
            SLASeeder::class,
            CronJobSeeder::class,
            SecurityPolicySeeder::class,
            SecurityAuditLogSeeder::class,
            SystemSettingsSeeder::class,
            SystemErrorLogSeeder::class,
            ScheduledJobSeeder::class,          // ← correct name (no trailing 's')
            SystemHealthSeeder::class,
            TaxBracketsSeeder::class,
            GovernmentContributionRatesSeeder::class,
            PayrollConfigurationSeeder::class,
            SalaryComponentSeeder::class,
        ]);

        // ── HR Structure (departments & positions before employees) ────────
        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            WorkScheduleSeeder::class,
        ]);

        // ── ATS / Recruitment ──────────────────────────────────────────────
        // Order matters: job postings → candidates → applications → interviews
        $this->call([
            JobPostingSeeder::class,
            CandidateSeeder::class,
            ApplicationSeeder::class,
            InterviewSeeder::class,
        ]);

        // ── Offboarding ────────────────────────────────────────────────────
        $this->call([
            OffboardingSystemSeeder::class,
        ]);

            if (class_exists(RolesAndPermissionsSeeder::class)) {
                $this->call(RolesAndPermissionsSeeder::class);
            }

        // ── Employees & Profiles ───────────────────────────────────────────
        $this->call([
            EmployeeSeeder::class,
            // too much employees for demo  BulkEmployeeSeeder::class,
            EmployeeFilipinoProfileSeeder::class,
            EmployeeAccountSeeder::class,
            LinkEmployeesToUsersSeeder::class,
            EmployeePayrollInfoSeeder::class,

        ]);

        if (class_exists(RemoveDuplicateLuisTorresSeeder::class)) {
            $this->call(RemoveDuplicateLuisTorresSeeder::class);
        }

        // ── Document Management ────────────────────────────────────────────
        $this->call([
            DocumentTemplateSeeder::class,
        ]);


        // ── Timekeeping & Attendance ───────────────────────────────────────
        $this->call([
            RfidLedgerSeeder::class,
            AttendanceEventsSeeder::class,
            DailyAttendanceSummarySeeder::class,
        ]);

        // ── Appraisals ─────────────────────────────────────────────────────
        $this->call([
            AppraisalCycleSeeder::class,
            AppraisalSeeder::class,
        ]);


        // ── RFID / Badges ──────────────────────────────────────────────────
        $this->call([
            RfidDeviceSeeder::class,
            RfidCardMappingSeeder::class,
        ]);

        // ── Leave & Overtime ───────────────────────────────────────────────
        $this->call([
            LeaveBalanceSeeder::class,
            LeaveRequestSeeder::class,
            OvertimeRequestSeeder::class,
        ]);

        // ── Workforce & Scheduling ─────────────────────────────────────────
        $this->call([
            WorkforceSeeder::class,
        ]);

        if (class_exists(OffboardingSeeder::class)) {
            $this->call(OffboardingSeeder::class);
        }

                // ── Payroll ────────────────────────────────────────────────────────
        $this->call([
            PayrollPeriodsSeeder::class, // Ensure this runs after all employee/attendance seeders
            PaymentMethodsSeeder::class,
            PayrollPaymentsSeeder::class,
            CashDistributionBatchSeeder::class,
            PayslipsSeeder::class,              // ← called once only (was duplicated)
        ]);



        // ── Dev / Test Data (local environment only) ───────────────────────
        if (app()->environment('local', 'testing')) {
            $this->call([
                TimekeepingTestDataSeeder::class,
                FebruaryFirstHalfPayrollSeeder::class,
                FebruarySecondHalfPayrollSeeder::class,
                FullPayrollTestDataSeeder::class,
            ]);

            // Requires SEED_PAYROLL_TEST_DATA=true in .env
            if (env('SEED_PAYROLL_TEST_DATA', false)) {
                $this->call(PayrollCalculationTestSeeder::class);
            }

        }

    }
}
