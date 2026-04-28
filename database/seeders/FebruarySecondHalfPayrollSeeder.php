<?php

namespace Database\Seeders;

use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * FebruarySecondHalfPayrollSeeder
 *
 * Seeds payroll period and finalized attendance data for February 2nd Half (Feb 16–27).
 *
 * **Critical:** All attendance rows must have `is_finalized = TRUE` for PayrollCalculationService
 * to pick them up. Without this, calculations produce ₱0 for all employees.
 *
 * **Workflow:**
 * 1. Reset any existing period with `period_number = '2026-02-2H'` (safe to re-run)
 * 2. Create PayrollPeriod record (status = 'active')
 * 3. For each active employee, create 10 DailyAttendanceSummary rows (Mon–Fri)
 * 4. Set `is_finalized = TRUE` on all attendance (required for payroll pickup)
 * 5. Randomize attendance: 5% absence, 20% late arrival, 15% overtime
 *
 * **Period Configuration:**
 * - Period Number: 2026-02-2H
 * - Period Name: February 2026 - 2nd Half
 * - Start: Feb 16, 2026 (first workday of 2nd half)
 * - End:   Feb 27, 2026 (last workday; Feb 28 is Saturday)
 * - Working Days: 10 (Mon–Fri of weeks: Feb 16-20, Feb 23-27)
 * - Payment Date: Mar 5, 2026
 * - Cutoff Date: Feb 27, 2026
 *
 * **Usage:**
 * ```bash
 * php artisan db:seed --class=FebruarySecondHalfPayrollSeeder
 * ```
 *
 * **Idempotency:** Safe to run multiple times—automatically resets old data for this period.
 */
class FebruarySecondHalfPayrollSeeder extends Seeder
{
    private const PERIOD_NUMBER   = '2026-02-2H';
    private const PERIOD_NAME     = 'February 2026 - 2nd Half';
    private const PERIOD_START    = '2026-02-16';  // First workday of 2nd half
    private const PERIOD_END      = '2026-02-27';  // Last workday (Feb 28 is Saturday)
    private const PAYMENT_DATE    = '2026-03-05';
    private const CUTOFF_DATE     = '2026-02-27';

    public function run(): void
    {
        $this->command->info('=== FebruarySecondHalfPayrollSeeder (2nd Half: Feb 16–27) ===');

        // ─────────────────────────────────────────────────────────────────
        // Step 1: Get essential models
        // ─────────────────────────────────────────────────────────────────

        $creator = User::where('email', 'payroll@cameco.com')->first()
            ?? User::where('email', 'superadmin@cameco.com')->first()
            ?? User::first();

        if (!$creator) {
            $this->command->error('❌ No user found. Run RolesAndPermissionsSeeder first.');
            return;
        }

        $workSchedule = WorkSchedule::first();
        if (!$workSchedule) {
            $this->command->error('❌ No work schedules found. Run WorkScheduleSeeder first.');
            return;
        }

        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->error('❌ No active employees found. Run EmployeeSeeder first.');
            return;
        }

        $this->command->info("ℹ️  Found {$employees->count()} active employees");

        // ─────────────────────────────────────────────────────────────────
        // Step 2: Reset (idempotency)—delete stale data for this period
        // ─────────────────────────────────────────────────────────────────

        $this->command->info('Step 1: Resetting stale data for ' . self::PERIOD_NUMBER . '...');

        $existingPeriod = PayrollPeriod::where('period_number', self::PERIOD_NUMBER)->first();

        if ($existingPeriod) {
            // Delete associated calculations
            $calcDeleted = EmployeePayrollCalculation::where('payroll_period_id', $existingPeriod->id)
                ->forceDelete();
            $this->command->line("  → Deleted {$calcDeleted} calculation records");

            // Delete attendance for this period
            $attendanceDeleted = DailyAttendanceSummary::whereBetween(
                'attendance_date',
                [self::PERIOD_START, self::PERIOD_END]
            )->forceDelete();
            $this->command->line("  → Deleted {$attendanceDeleted} attendance records");

            // Delete the period itself
            $existingPeriod->forceDelete();
            $this->command->line('  → Deleted stale PayrollPeriod record');
        } else {
            $this->command->line('  → No stale data found (clean slate)');
        }

        // ─────────────────────────────────────────────────────────────────
        // Step 3: Create PayrollPeriod
        // ─────────────────────────────────────────────────────────────────

        $this->command->info('Step 2: Creating PayrollPeriod record...');

        $period = PayrollPeriod::create([
            'period_number'        => self::PERIOD_NUMBER,
            'period_name'          => self::PERIOD_NAME,
            'period_start'         => Carbon::parse(self::PERIOD_START),
            'period_end'           => Carbon::parse(self::PERIOD_END),
            'payment_date'         => Carbon::parse(self::PAYMENT_DATE),
            'timekeeping_cutoff_date' => Carbon::parse(self::CUTOFF_DATE),
            'leave_cutoff_date'    => Carbon::parse(self::CUTOFF_DATE),
            'adjustment_deadline'  => Carbon::parse(self::PAYMENT_DATE)->subDays(2),
            'period_month'         => '2026-02',
            'period_year'          => 2026,
            'period_type'          => 'regular',
            'status'               => 'completed',
            'timekeeping_data_locked' => false,
            'leave_data_locked'    => false,
            'total_employees'      => $employees->count(),
            'active_employees'     => $employees->count(),
            'excluded_employees'   => 0,
            'created_by'           => $creator->id,
        ]);

        $this->command->info("✅ PayrollPeriod created: {$period->period_number}");

        // ─────────────────────────────────────────────────────────────────
        // Step 4: Generate working days
        // ─────────────────────────────────────────────────────────────────

        $workDays = $this->getWorkingDays(self::PERIOD_START, self::PERIOD_END);
        $this->command->info("Step 3: Generating attendance for {$workDays->count()} working days...");

        // ─────────────────────────────────────────────────────────────────
        // Step 5: Create attendance records (is_finalized = TRUE)
        // ─────────────────────────────────────────────────────────────────

        $totalRecords = $employees->count() * $workDays->count();
        $progressBar = $this->command->getOutput()->createProgressBar($totalRecords);
        $progressBar->start();

        $created = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Skip if attendance already exists for this employee/date
                $exists = DailyAttendanceSummary::where('employee_id', $employee->id)
                    ->where('attendance_date', $date)
                    ->exists();
                if ($exists) {
                    $progressBar->advance();
                    continue;
                }
                // Randomize attendance
                $rand = mt_rand(1, 100);
                $isAbsent = $rand <= 5;           // 5% absence
                $isLate = !$isAbsent && $rand <= 25;  // 20% late (given not absent)
                $isOvertime = !$isAbsent && $rand >= 85; // 15% overtime (given not absent)

                $lateMinutes = $isLate ? mt_rand(5, 45) : 0;
                $overtimeHours = $isOvertime ? mt_rand(1, 3) : 0.0;
                $regularHours = $isAbsent ? 0.0 : 8.0;

                // Calculate total hours: regular + OT - late penalty
                $totalHours = $isAbsent
                    ? 0.0
                    : max(0, round($regularHours + $overtimeHours - ($lateMinutes / 60), 2));

                // Set clock times
                $timeIn = $isAbsent ? null : Carbon::parse($date . ' 08:00:00')->addMinutes($lateMinutes);
                $timeOut = $isAbsent ? null : Carbon::parse($date . ' 17:00:00')->addHours($overtimeHours);

                // Create attendance record
                DailyAttendanceSummary::create([
                    'employee_id'         => $employee->id,
                    'attendance_date'     => $date,
                    'work_schedule_id'    => $workSchedule->id,
                    'time_in'             => $timeIn?->toDateTimeString(),
                    'time_out'            => $timeOut?->toDateTimeString(),
                    'break_start'         => $isAbsent ? null : Carbon::parse($date . ' 12:00:00')->toDateTimeString(),
                    'break_end'           => $isAbsent ? null : Carbon::parse($date . ' 13:00:00')->toDateTimeString(),
                    'break_duration'      => $isAbsent ? 0 : 60,
                    'total_hours_worked'  => $totalHours,
                    'regular_hours'       => round($regularHours, 2),
                    'overtime_hours'      => round($overtimeHours, 2),
                    'is_present'          => !$isAbsent,
                    'is_late'             => $isLate,
                    'is_undertime'        => false,
                    'is_overtime'         => $isOvertime,
                    'late_minutes'        => $lateMinutes,
                    'undertime_minutes'   => 0,
                    'is_on_leave'         => false,
                    'ledger_verified'     => true,
                    'is_finalized'        => true,  // ⚠️  CRITICAL — MUST be true for payroll
                    'calculated_at'       => now(),
                ]);

                $created++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->command->newLine();

        // ─────────────────────────────────────────────────────────────────
        // Summary
        // ─────────────────────────────────────────────────────────────────

        $this->command->info("✅ Successfully seeded {$created} attendance records");
        $this->command->info("   Period: {$period->period_number} ({$period->period_start} → {$period->period_end})");
        $this->command->info("   Employees: {$employees->count()}");
        $this->command->info("   Working Days: {$workDays->count()}");
        $this->command->info("   Status: {$period->status}");
        $this->command->line('');
        $this->command->info('⚠️  All attendance records have is_finalized = TRUE');
        $this->command->info('    → Ready for payroll calculation');
    }

    /**
     * Get all working days (Mon–Fri) within the given date range.
     *
     * @param string $start Start date (Y-m-d format)
     * @param string $end   End date (Y-m-d format)
     * @return \Illuminate\Support\Collection Collection of date strings (Y-m-d)
     */
    private function getWorkingDays(string $start, string $end)
    {
        $days = collect();
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);

        while ($cursor->lte($last)) {
            if (!$cursor->isWeekend()) {
                $days->push($cursor->toDateString());
            }
            $cursor->addDay();
        }

        return $days;
    }
}
