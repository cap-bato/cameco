<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * PERMISSION SEEDING ORDER — must follow this pattern to avoid empty roles:
     *
     *  1. Create ALL roles and ALL permissions (base + every module extension)
     *  2. Clear the Spatie permission cache
     *  3. Assign roles to users  ← only now, after every permission exists
     *  4. Everything else (employees, payroll, etc.)
     *
     * Previously, role assignment happened between steps 1 and the module
     * permission seeders, so the HR Manager role existed but had no module
     * permissions attached to it yet when the user was assigned that role.
     * Spatie then cached that incomplete role — subsequent requests saw it
     * as having no permissions even after the module seeders finished.
     */
    public function run(): void
    {
        // ── STAGE 1: Users (no roles yet) ─────────────────────────────────
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

        // ── STAGE 2: ALL roles and ALL permissions first ───────────────────
        // Base roles/permissions must come before any module extensions,
        // but ALL of them must finish before we assign roles to anyone.
        $this->call([
            LeavePolicySeeder::class,
            RolesAndPermissionsSeeder::class,

            // Module permission extensions — these add permissions to existing
            // roles (e.g. giving HR Manager access to ATS, Payroll, etc.)
            // They must ALL run here, before any role assignment below.
            ATSPermissionsSeeder::class,
            TimekeepingPermissionsSeeder::class,
            BadgeManagementPermissionsSeeder::class,
            WorkforceManagementPermissionsSeeder::class,
            DocumentManagementPermissionsSeeder::class,
            PayrollPermissionsSeeder::class,
            OffboardingPermissionsSeeder::class,
            AppraisalPermissionsSeeder::class,
        ]);

        // ── STAGE 3: Flush Spatie's permission cache ───────────────────────
        // Spatie caches roles+permissions on first load. If the cache is stale
        // from a previous seed run (or was partially populated during this one),
        // role assignments below will use the stale snapshot. Always flush here.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── STAGE 4: Assign roles — now that all permissions exist ─────────
        $superadmin = User::where('email', 'superadmin@cameco.com')->first();
        if ($superadmin && method_exists($superadmin, 'assignRole')) {
            try { $superadmin->assignRole('Superadmin'); } catch (\Throwable) {}
        }

        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        if ($hrManager && method_exists($hrManager, 'assignRole')) {
            try { $hrManager->assignRole('HR Manager'); } catch (\Throwable) {}
        }

        // ── STAGE 5: Additional user accounts (roles must exist first) ─────
        $this->call([
            PayrollOfficerAccountSeeder::class,
            OfficeAdminSeeder::class,
            EmployeeRoleSeeder::class,
            HRStaffAccountSeeder::class,
        ]);

        // Flush cache again after bulk account seeders assign more roles
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── STAGE 6: System & config data ─────────────────────────────────
        $this->call([
            SLASeeder::class,
            CronJobSeeder::class,
            SecurityPolicySeeder::class,
            SecurityAuditLogSeeder::class,
            SystemSettingsSeeder::class,
            SystemErrorLogSeeder::class,
            ScheduledJobSeeder::class,
            SystemHealthSeeder::class,
            TaxBracketsSeeder::class,
            GovernmentContributionRatesSeeder::class,
            PayrollConfigurationSeeder::class,
            SalaryComponentSeeder::class,
        ]);

        // ── STAGE 7: HR structure (departments → positions → schedules) ────
        $this->call([
            DepartmentSeeder::class,
            PositionSeeder::class,
            WorkScheduleSeeder::class,
        ]);

        // ── STAGE 8: ATS / Recruitment ────────────────────────────────────
        $this->call([
            JobPostingSeeder::class,
            CandidateSeeder::class,
            ApplicationSeeder::class,
            InterviewSeeder::class,
        ]);

        // ── STAGE 9: Offboarding system config ────────────────────────────
        $this->call([
            OffboardingSystemSeeder::class,
        ]);

        // ── STAGE 10: Employees & profiles ────────────────────────────────
        $this->call([
            EmployeeSeeder::class,
            BulkEmployeeSeeder::class,
            EmployeeFilipinoProfileSeeder::class,
            EmployeeAccountSeeder::class,
            LinkEmployeesToUsersSeeder::class,
            EmployeePayrollInfoSeeder::class,
        ]);

        // ── STAGE 11: Document management ─────────────────────────────────
        $this->call([
            DocumentTemplateSeeder::class,
        ]);

        // ── STAGE 12: Timekeeping & attendance ────────────────────────────
        $this->call([
            RfidLedgerSeeder::class,
            AttendanceEventsSeeder::class,
            DailyAttendanceSummarySeeder::class,
        ]);

        // ── STAGE 13: Appraisals ──────────────────────────────────────────
        $this->call([
            AppraisalCycleSeeder::class,
            AppraisalSeeder::class,
        ]);

        // ── STAGE 14: RFID / Badges ───────────────────────────────────────
        $this->call([
            RfidDeviceSeeder::class,
            RfidCardMappingSeeder::class,
        ]);

        // ── STAGE 15: Leave & overtime ────────────────────────────────────
        $this->call([
            LeaveBalanceSeeder::class,
            LeaveRequestSeeder::class,
            OvertimeRequestSeeder::class,
        ]);

        // ── STAGE 16: Workforce & scheduling ──────────────────────────────
        $this->call([
            WorkforceSeeder::class,
        ]);

        if (class_exists(OffboardingSeeder::class)) {
            $this->call(OffboardingSeeder::class);
        }

        // ── STAGE 17: Payroll ─────────────────────────────────────────────
        $this->call([
            PayrollPeriodsSeeder::class,
            PaymentMethodsSeeder::class,
            PayrollPaymentsSeeder::class,
            CashDistributionBatchSeeder::class,
            PayslipsSeeder::class,
        ]);

        // ── STAGE 18: Dev / test data (local & testing only) ──────────────
        if (app()->environment('local', 'testing')) {
            $this->call([
                TimekeepingTestDataSeeder::class,
                FebruaryFirstHalfPayrollSeeder::class,
                FebruarySecondHalfPayrollSeeder::class,
                FullPayrollTestDataSeeder::class,
            ]);

            if (env('SEED_PAYROLL_TEST_DATA', false)) {
                $this->call(PayrollCalculationTestSeeder::class);
            }
        }

        // ── STAGE 19: Cleanup (run last) ───────────────────────────────────
        if (class_exists(RemoveDuplicateLuisTorresSeeder::class)) {
            $this->call(RemoveDuplicateLuisTorresSeeder::class);
        }
    }
}