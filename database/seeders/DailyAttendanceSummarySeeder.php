<?php

namespace Database\Seeders;

use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DailyAttendanceSummarySeeder extends Seeder
{
    // Align these with PayrollCalculationTestSeeder
    private const PERIOD_START = '2026-03-01';
    private const PERIOD_END   = '2026-03-15';

    public function run(): void
    {
        $this->command->info('Seeding DailyAttendanceSummary (Mon-Fri, is_finalized=true)...');

        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->error('No active employees. Run EmployeeSeeder first.');
            return;
        }

        $workScheduleId = WorkSchedule::query()->value('id');
        if (!$workScheduleId) {
            $this->command->error('No work schedules found. Run schedule seeders first.');
            return;
        }

        $workDays = $this->getWorkingDays(self::PERIOD_START, self::PERIOD_END);
        $this->command->info('Work days in range: ' . count($workDays));

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Prevent unique constraint violation: skip if already exists
                $exists = DailyAttendanceSummary::where('employee_id', $employee->id)
                    ->where('attendance_date', $date)
                    ->exists();
                if ($exists) {
                    $skipped++;
                    $this->command->line("  -> Skipped: employee_id={$employee->id}, date={$date} (already exists)");
                    continue;
                }
                $rand = mt_rand(1, 100);
                $isAbsent = $rand <= 5;
                $isLate = !$isAbsent && $rand <= 25;
                $isOvertime = !$isAbsent && $rand >= 85;
                $lateMinutes = $isLate ? mt_rand(5, 45) : 0;
                $overtimeHours = $isOvertime ? mt_rand(1, 3) : 0.0;
                $regularHours = $isAbsent ? 0.0 : 8.0;
                $totalHours = $isAbsent
                    ? 0.0
                    : ($regularHours + $overtimeHours - ($lateMinutes / 60));
                $timeIn = $isAbsent ? null : Carbon::parse($date . ' 08:00:00')->addMinutes($lateMinutes);
                $timeOut = $isAbsent ? null : Carbon::parse($date . ' 17:00:00')->addHours($overtimeHours);
                DailyAttendanceSummary::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'work_schedule_id' => $workScheduleId,
                    'time_in' => $timeIn?->toDateTimeString(),
                    'time_out' => $timeOut?->toDateTimeString(),
                    'break_start' => $isAbsent ? null : Carbon::parse($date . ' 12:00:00')->toDateTimeString(),
                    'break_end' => $isAbsent ? null : Carbon::parse($date . ' 13:00:00')->toDateTimeString(),
                    'break_duration' => $isAbsent ? 0 : 60,
                    'total_hours_worked' => round(max(0, $totalHours), 2),
                    'regular_hours' => round($regularHours, 2),
                    'overtime_hours' => round($overtimeHours, 2),
                    'is_present' => !$isAbsent,
                    'is_late' => $isLate,
                    'is_undertime' => false,
                    'is_overtime' => $isOvertime,
                    'late_minutes' => $lateMinutes,
                    'undertime_minutes' => 0,
                    'is_on_leave' => false,
                    'ledger_verified' => true,
                    'is_finalized' => true,
                    'calculated_at' => now(),
                ]);
                $created++;
            }
        }

        $this->command->info("  -> Created: {$created}, Skipped: {$skipped}");
    }

    /**
     * Returns all Mon-Fri dates (Y-m-d) within the given inclusive date range.
     */
    private function getWorkingDays(string $start, string $end): array
    {
        $days = [];
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);

        while ($cursor->lte($last)) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $days;
    }
}
