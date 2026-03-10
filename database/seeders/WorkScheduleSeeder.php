<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

/**
 * WorkScheduleSeeder
 *
 * Ensures every department has a Standard Office Hours work schedule that is
 * valid back to 2025-01-01 (covering all seeded attendance data: Feb–Mar 2026).
 *
 * Safe to re-run: skips any department that already has an active schedule
 * with effective_date <= 2025-01-01.
 *
 * Usage:
 *   php artisan db:seed --class=WorkScheduleSeeder
 *
 * Why this exists:
 *   AttendanceSummaryService::getWorkScheduleForDate() looks up:
 *     WorkSchedule WHERE department_id = ? AND effective_date <= ? AND (expires_at IS NULL OR expires_at >= ?)
 *   If no schedule is found, the employee is treated as absent for that day.
 *   The existing schedules all have effective_date = 2026-03-06 and only cover
 *   department_id = 2, so any February attendance generates zero-hour summaries.
 */
class WorkScheduleSeeder extends Seeder
{
    // Mon–Fri standard office hours
    private const SCHEDULE = [
        'monday_start'    => '08:00:00',
        'monday_end'      => '17:00:00',
        'tuesday_start'   => '08:00:00',
        'tuesday_end'     => '17:00:00',
        'wednesday_start' => '08:00:00',
        'wednesday_end'   => '17:00:00',
        'thursday_start'  => '08:00:00',
        'thursday_end'    => '17:00:00',
        'friday_start'    => '08:00:00',
        'friday_end'      => '17:00:00',
        'saturday_start'  => null,
        'saturday_end'    => null,
        'sunday_start'    => null,
        'sunday_end'      => null,
        'lunch_break_duration'      => 60,  // minutes
        'morning_break_duration'    => 15,
        'afternoon_break_duration'  => 15,
        'overtime_threshold'        => 8,   // hours
        'overtime_rate_multiplier'  => '1.25',
        'status'                    => 'active',
        'effective_date'            => '2025-01-01',  // well before Feb 2026
        'expires_at'                => null,           // never expires
        'is_template'               => false,
    ];

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('WorkScheduleSeeder — assigning Standard Office Hours to all departments');

        $creator = User::first();
        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->command->error('No departments found. Run DepartmentSeeder first.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($departments as $department) {
            // Check if an active schedule already covers 2025-01-01 or earlier for this department
            $existing = WorkSchedule::where('department_id', $department->id)
                ->where('status', 'active')
                ->where('effective_date', '<=', '2025-01-01')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', '2026-12-31');
                })
                ->exists();

            if ($existing) {
                $this->command->line("  <comment>SKIP</comment>  [{$department->id}] {$department->name} — schedule already exists");
                $skipped++;
                continue;
            }

            WorkSchedule::create(array_merge(self::SCHEDULE, [
                'name'          => "Standard Office Hours — {$department->name}",
                'description'   => "Mon–Fri 08:00–17:00, 1-hour lunch break. Auto-created for {$department->name}.",
                'department_id' => $department->id,
                'created_by'    => $creator?->id,
            ]));

            $this->command->line("  <info>CREATE</info> [{$department->id}] {$department->name}");
            $created++;
        }

        $this->command->info('');
        $this->command->info("Done — Created: {$created}, Skipped: {$skipped}");
        $this->command->info("Every department now has a schedule valid from 2025-01-01 with no expiry.");
        $this->command->info('');
        $this->command->info('Next step: run AttendanceEventsSeeder to seed attendance for Feb–Mar 2026:');
        $this->command->info('  php artisan db:seed --class=AttendanceEventsSeeder');
    }
}
