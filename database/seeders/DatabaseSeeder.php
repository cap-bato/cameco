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
     * PERMISSION SEEDING RULES — violating any of these causes missing permissions:
     *
     *  1. ALL permission seeders must finish before any role is assigned to a user.
     *  2. Use givePermissionTo() in every module seeder — never syncPermissions(),
     *     which replaces the role's full permission set instead of adding to it.
     *  3. Call forgetCachedPermissions() after all permission seeding and again
     *     after bulk role-assignment seeders, before anything reads permissions.
     */
    public function run(): void
    {
        // ── STAGE 1: Users — no roles yet ─────────────────────────────────
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

        // ── STAGE 2: ALL roles and ALL permissions ─────────────────────────
        // Every module permission seeder must run here — before any assignRole()
        // call anywhere in the file. Order within this block matters only if one
        // seeder references permissions created by another; otherwise alphabetical
        // is fine. The critical rule is: this entire block finishes first.
        $this->call([
            LeavePolicySeeder::class,
            RolesAndPermissionsSeeder::class,

            // Module extensions — each adds permissions and calls givePermissionTo()
            ATSPermissionsSeeder::class,
            TimekeepingPermissionsSeeder::class,
            BadgeManagementPermissionsSeeder::class,
            WorkforceManagementPermissionsSeeder::class,
            DocumentManagementPermissionsSeeder::class,
            PayrollPermissionsSeeder::class,
            OffboardingPermissionsSeeder::class,
            AppraisalPermissionsSeeder::class,      // ← was using syncPermissions; now fixed
        ]);

        // ── STAGE 3: Flush Spatie cache ────────────────────────────────────
        // Must happen after all permission rows exist and all role↔permission
        // pivots are written, and before the first assignRole() call.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── STAGE 4: Assign roles to seed users ────────────────────────────
        $superadmin = User::where('email', 'superadmin@cameco.com')->first();
        if ($superadmin && method_exists($superadmin, 'assignRole')) {
            try { $superadmin->assignRole('Superadmin'); } catch (\Throwable) {}
        }

        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        if ($hrManager && method_exists($hrManager, 'assignRole')) {
            try { $hrManager->assignRole('HR Manager'); } catch (\Throwable) {}
        }

        // ── STAGE 5: Additional accounts (these also assign roles internally) ──
        $this->call([
            PayrollOfficerAccountSeeder::class,
            OfficeAdminSeeder::class,
            EmployeeRoleSeeder::class,
            HRStaffAccountSeeder::class,
        ]);

        // Flush again — the account seeders above may have triggered a cache load
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

        // ── STAGE 7: HR structure ──────────────────────────────────────────
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

        // ── STAGE 18: Dev / test data ──────────────────────────────────────
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

        // ── STAGE 19: Cleanup ──────────────────────────────────────────────
        if (class_exists(RemoveDuplicateLuisTorresSeeder::class)) {
            $this->call(RemoveDuplicateLuisTorresSeeder::class);
        }
    }
}