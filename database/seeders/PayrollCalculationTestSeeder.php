<?php

namespace Database\Seeders;

use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PayrollCalculationTestSeeder
 *
 * Seeds the minimum data needed to trigger and verify a full payroll calculation:
 *
 *  1. EmployeePayrollInfo — salary config for every active employee (skips employees that
 *     already have active payroll info).
 *
 *  2. PayrollPeriod — one period in 'open' status (March 2026 1st Half, Mar 1–15).
 *     Skipped if a period with the same period_number already exists.
 *
 *  3. DailyAttendanceSummary — one finalized row per working day (Mon–Fri) within the
 *     period for each active employee. Skipped if rows already exist for that employee /
 *     period combination to allow re-running safely.
 *
 * After running this seeder, go to /payroll/calculations → click "Start Calculation"
 * for the "March 2026 - 1st Half" period.  The queue worker will process the jobs and
 * populate employee_payroll_calculations.
 */
class PayrollCalculationTestSeeder extends Seeder
{
    // Period date range (adjust as needed)
    private const PERIOD_START = '2026-03-01';
    private const PERIOD_END   = '2026-03-15';

    public function run(): void
    {
        $this->command->info('=== PayrollCalculationTestSeeder ===');

        $creatorUser = User::where('email', 'payroll@cameco.com')->first()
            ?? User::where('email', 'superadmin@cameco.com')->first()
            ?? User::first();

        if (!$creatorUser) {
            $this->command->error('No users found — run DatabaseSeeder first.');
            return;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 1. EmployeePayrollInfo
        // ─────────────────────────────────────────────────────────────────────
        $this->command->info('Step 1: Seeding EmployeePayrollInfo...');
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->error('No active employees found — run EmployeeSeeder first.');
            return;
        }

        $salaryPresets = [
            // Vary salaries slightly so calculations are diverse
            ['basic_salary' => 35000, 'daily_rate' => 1346.15, 'hourly_rate' => 168.27],
            ['basic_salary' => 28000, 'daily_rate' => 1076.92, 'hourly_rate' => 134.62],
            ['basic_salary' => 22000, 'daily_rate' => 846.15,  'hourly_rate' => 105.77],
            ['basic_salary' => 18000, 'daily_rate' => 692.31,  'hourly_rate' => 86.54],
            ['basic_salary' => 15000, 'daily_rate' => 576.92,  'hourly_rate' => 72.12],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($employees as $index => $employee) {
            $alreadyHasInfo = EmployeePayrollInfo::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->whereNull('end_date')
                ->exists();

            if ($alreadyHasInfo) {
                $skipped++;
                continue;
            }

            $preset = $salaryPresets[$index % count($salaryPresets)];

            EmployeePayrollInfo::create([
                'employee_id'                  => $employee->id,
                'salary_type'                  => 'monthly',
                'basic_salary'                 => $preset['basic_salary'],
                'daily_rate'                   => $preset['daily_rate'],
                'hourly_rate'                  => $preset['hourly_rate'],
                'payment_method'               => 'bank_transfer',
                'tax_status'                   => 'S', // Single
                'rdo_code'                     => '055',
                'withholding_tax_exemption'    => 0,
                'is_tax_exempt'                => false,
                'is_substituted_filing'        => false,
                'sss_number'                   => sprintf('33-%07d-%d', $employee->id * 1000 + $index, mt_rand(0, 9)),
                'philhealth_number'            => sprintf('%012d', $employee->id * 100000 + $index),
                'pagibig_number'               => sprintf('%012d', $employee->id * 200000 + $index),
                'tin_number'                   => sprintf('%09d-%03d', $employee->id * 1000000, mt_rand(0, 999)),
                'sss_bracket'                  => $this->detectSSSBracket($preset['basic_salary']),
                'is_sss_voluntary'             => false,
                'philhealth_is_indigent'       => false,
                'pagibig_employee_rate'        => 2.00,
                'bank_name'                    => 'BDO Unibank',
                'bank_code'                    => 'BDO',
                'bank_account_number'          => sprintf('%010d', $employee->id * 1000000 + 1000000000),
                'bank_account_name'            => $employee->profile?->full_name ?? $employee->user?->name ?? 'Employee ' . $employee->employee_number,
                'is_entitled_to_rice'          => true,
                'is_entitled_to_uniform'       => false,
                'is_entitled_to_laundry'       => false,
                'is_entitled_to_medical'       => true,
                'effective_date'               => '2026-01-01',
                'end_date'                     => null,
                'is_active'                    => true,
                'created_by'                   => $creatorUser->id,
            ]);

            $created++;
        }

        $this->command->info("  → Created: {$created}, Skipped (already has info): {$skipped}");

        // ─────────────────────────────────────────────────────────────────────
        // 2. PayrollPeriod
        // ─────────────────────────────────────────────────────────────────────
        $this->command->info('Step 2: Seeding test PayrollPeriod...');
        $periodNumber = '2026-03-1H';

        $period = PayrollPeriod::firstOrCreate(
            ['period_number' => $periodNumber],
            [
                'period_name'             => 'March 2026 - 1st Half (Test)',
                'period_start'            => self::PERIOD_START,
                'period_end'              => self::PERIOD_END,
                'payment_date'            => '2026-03-17',
                'period_month'            => 3,
                'period_year'             => 2026,
                'period_type'             => 'regular',
                'timekeeping_cutoff_date' => '2026-03-14',
                'leave_cutoff_date'       => '2026-03-14',
                'adjustment_deadline'     => '2026-03-12',
                'total_employees'         => $employees->count(),
                'active_employees'        => $employees->count(),
                'excluded_employees'      => 0,
                'status'                  => 'active',
                'timekeeping_data_locked' => false,
                'leave_data_locked'       => false,
            ]
        );

        if ($period->wasRecentlyCreated) {
            $this->command->info("  → Created period: [{$period->id}] {$period->period_name}");
        } else {
            $this->command->warn("  → Period already exists: [{$period->id}] {$period->period_name} (status={$period->status})");
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. DailyAttendanceSummary
        // ─────────────────────────────────────────────────────────────────────
        $this->command->info('Step 3: Seeding DailyAttendanceSummary (Mon–Fri)...');

        $workDays = $this->getWorkingDays(self::PERIOD_START, self::PERIOD_END);
        $this->command->info("  → Work days in period: " . count($workDays));

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($employees as $employee) {
            // Check if this employee already has attendance in this period
            $existingCount = DailyAttendanceSummary::where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [self::PERIOD_START, self::PERIOD_END])
                ->count();

            if ($existingCount >= count($workDays)) {
                $totalSkipped += count($workDays);
                continue;
            }

            foreach ($workDays as $date) {
                // Skip if this specific date already exists
                $exists = DailyAttendanceSummary::where('employee_id', $employee->id)
                    ->where('attendance_date', $date)
                    ->exists();

                if ($exists) {
                    $totalSkipped++;
                    continue;
                }

                // Occasional late arrival (20% chance) or absence (5% chance)
                $rand       = mt_rand(1, 100);
                $isAbsent   = $rand <= 5;
                $isLate     = !$isAbsent && $rand <= 25;
                $isOvertime = !$isAbsent && $rand >= 85;

                $lateMinutes       = $isLate ? mt_rand(5, 45) : 0;
                $overtimeHours     = $isOvertime ? mt_rand(1, 3) : 0.0;
                $regularHours      = $isAbsent ? 0.0 : 8.0;
                $totalHoursWorked  = $isAbsent ? 0.0 : ($regularHours + $overtimeHours - $lateMinutes / 60);
                $undertimeMinutes  = 0;

                $timeIn  = $isAbsent ? null : Carbon::parse($date . ' 08:00:00')->addMinutes($lateMinutes);
                $timeOut = $isAbsent ? null : Carbon::parse($date . ' 17:00:00')->addHours($overtimeHours);

                DailyAttendanceSummary::create([
                    'employee_id'            => $employee->id,
                    'attendance_date'        => $date,
                    'work_schedule_id'       => 1, // Standard Day Shift
                    'time_in'                => $timeIn?->toDateTimeString(),
                    'time_out'               => $timeOut?->toDateTimeString(),
                    'break_start'            => $isAbsent ? null : Carbon::parse($date . ' 12:00:00')->toDateTimeString(),
                    'break_end'              => $isAbsent ? null : Carbon::parse($date . ' 13:00:00')->toDateTimeString(),
                    'break_duration'         => $isAbsent ? 0 : 60,
                    'total_hours_worked'     => round(max(0, $totalHoursWorked), 2),
                    'regular_hours'          => round($regularHours, 2),
                    'overtime_hours'         => round($overtimeHours, 2),
                    'is_present'             => !$isAbsent,
                    'is_late'                => $isLate,
                    'is_undertime'           => false,
                    'is_overtime'            => $isOvertime,
                    'late_minutes'           => $lateMinutes,
                    'undertime_minutes'      => $undertimeMinutes,
                    'is_on_leave'            => false,
                    'ledger_verified'        => true,
                    'is_finalized'           => true,  // Must be true for payroll to pick this up
                    'calculated_at'          => now(),
                ]);

                $totalCreated++;
            }
        }

        $this->command->info("  → Attendance rows created: {$totalCreated}, skipped: {$totalSkipped}");

        // ─────────────────────────────────────────────────────────────────────
        // Summary
        // ─────────────────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✅ PayrollCalculationTestSeeder complete!');
        $this->command->info('');
        $this->command->info('Next steps:');
        $this->command->info("  1. Ensure the queue worker is running:");
        $this->command->info("       php artisan queue:work --queue=payroll,default --tries=3");
        $this->command->info("  2. Visit /payroll/calculations in the browser");
        $this->command->info("  3. Click 'Start Calculation' for 'March 2026 - 1st Half (Test)'");
        $this->command->info("  4. Watch the queue worker terminal for progress logs");
        $this->command->info("  5. Refresh the page — employee_payroll_calculations will populate");
    }

    /**
     * Returns all Mon–Fri dates (as 'Y-m-d' strings) within the given range.
     */
    private function getWorkingDays(string $start, string $end): array
    {
        $days   = [];
        $cursor = Carbon::parse($start);
        $last   = Carbon::parse($end);

        while ($cursor->lte($last)) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Rough SSS bracket detection based on monthly salary.
     */
    private function detectSSSBracket(float $salary): string
    {
        if ($salary <= 3250)  return '1';
        if ($salary <= 3750)  return '2';
        if ($salary <= 4250)  return '3';
        if ($salary <= 4750)  return '4';
        if ($salary <= 5250)  return '5';
        if ($salary <= 5750)  return '6';
        if ($salary <= 6250)  return '7';
        if ($salary <= 6750)  return '8';
        if ($salary <= 7250)  return '9';
        if ($salary <= 7750)  return '10';
        if ($salary <= 8250)  return '11';
        if ($salary <= 8750)  return '12';
        if ($salary <= 9250)  return '13';
        if ($salary <= 9750)  return '14';
        if ($salary <= 10250) return '15';
        if ($salary <= 10750) return '16';
        if ($salary <= 11250) return '17';
        if ($salary <= 11750) return '18';
        if ($salary <= 12250) return '19';
        if ($salary <= 12750) return '20';
        if ($salary <= 13250) return '21';
        if ($salary <= 13750) return '22';
        if ($salary <= 14250) return '23';
        if ($salary <= 14750) return '24';
        if ($salary <= 15250) return '25';
        if ($salary <= 15750) return '26';
        if ($salary <= 16250) return '27';
        if ($salary <= 16750) return '28';
        if ($salary <= 17250) return '29';
        if ($salary <= 17750) return '30';
        if ($salary <= 18250) return '31';
        if ($salary <= 18750) return '32';
        if ($salary <= 19250) return '33';
        if ($salary <= 19750) return '34';
        return '35'; // 20,000+
    }
}
