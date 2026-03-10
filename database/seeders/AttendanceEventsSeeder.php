<?php

namespace Database\Seeders;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * AttendanceEventsSeeder
 *
 * Seeds attendance_events for all active employees across February 2 – March 7, 2026
 * (working days only, Mon–Fri), then drives timekeeping:generate-daily-summaries to
 * produce and auto-finalize daily_attendance_summary rows.
 *
 * Usage:
 *   php artisan db:seed --class=AttendanceEventsSeeder
 *
 * After running, daily_attendance_summary rows will exist for every working day
 * in the covered range with is_finalized = true, ready for payroll calculation.
 *
 * Attendance profile (deterministic per employee+date, safe to re-run):
 *   ~5%  absent  (no events created)
 *  ~20%  late    (time_in > 08:15 grace window)
 *  ~15%  overtime (time_out after 17:00)
 *  rest  normal   (arrive 07:55–08:05, leave 17:00–17:10)
 */
class AttendanceEventsSeeder extends Seeder
{
    private const SEED_FROM = '2026-02-02'; // First Mon of Feb 2026
    private const SEED_TO   = '2026-03-07'; // Mar 7 is Sat — getWorkingDays() stops at Mar 6 (Fri)

    private const ABSENT_RATE   = 5;  // % of days absent
    private const LATE_RATE     = 20; // % of present days arrive late
    private const OVERTIME_RATE = 15; // % of present days stay overtime

    private const DEVICE_GATE    = 'RFID-MAIN-01';
    private const DEVICE_CANTEEN = 'RFID-CNT-01';

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════');
        $this->command->info('  AttendanceEventsSeeder');
        $this->command->info('  Range: ' . self::SEED_FROM . ' → ' . self::SEED_TO);
        $this->command->info('═══════════════════════════════════════════════');

        // ── Prerequisites ────────────────────────────────────────────────────
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->error('No active employees found. Run EmployeeSeeder / BulkEmployeeSeeder first.');
            return;
        }

        $seedUser = User::first();
        $workDays = $this->getWorkingDays(self::SEED_FROM, self::SEED_TO);

        $this->command->info("  Employees : {$employees->count()}");
        $this->command->info("  Work days : " . count($workDays) . " (Mon–Fri, excl. weekends)");
        $this->command->info('');

        // ── Step 1: Seed attendance_events ───────────────────────────────────
        $this->command->info('Step 1  →  Seeding attendance_events...');

        $eventsCreated = 0;
        $daySkipped    = 0;

        $bar = $this->command->getOutput()->createProgressBar($employees->count() * count($workDays));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%/%estimated:-6s%');
        $bar->start();

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                // Idempotent: skip if events already exist for this employee+date
                $alreadyExists = AttendanceEvent::where('employee_id', $employee->id)
                    ->whereDate('event_date', $date)
                    ->exists();

                if ($alreadyExists) {
                    $daySkipped++;
                    $bar->advance();
                    continue;
                }

                // Deterministic randomness so re-runs produce the same events
                mt_srand(crc32("{$employee->id}_{$date}"));

                $roll        = mt_rand(1, 100);
                $isAbsent    = $roll <= self::ABSENT_RATE;
                $isLate      = !$isAbsent && $roll <= (self::ABSENT_RATE + self::LATE_RATE);
                $isOvertime  = !$isAbsent && mt_rand(1, 100) >= (100 - self::OVERTIME_RATE);

                if ($isAbsent) {
                    $bar->advance();
                    continue;
                }

                // ── Time-in ──────────────────────────────────────────────────
                $timeIn = $isLate
                    ? Carbon::parse("{$date} 08:00:00")->addMinutes(mt_rand(16, 55))
                    : Carbon::parse("{$date} 08:00:00")->addMinutes(mt_rand(-5, 5));

                // ── Break 12:00–13:00 ─────────────────────────────────────────
                $breakStart = Carbon::parse("{$date} 12:00:00");
                $breakEnd   = Carbon::parse("{$date} 13:00:00");

                // ── Time-out ─────────────────────────────────────────────────
                $timeOut = $isOvertime
                    ? Carbon::parse("{$date} 17:00:00")->addMinutes(mt_rand(60, 180))
                    : Carbon::parse("{$date} 17:00:00")->addMinutes(mt_rand(0, 10));

                // Batch insert all four events for the day
                $now = now()->toDateTimeString();

                AttendanceEvent::insert([
                    [
                        'employee_id'           => $employee->id,
                        'event_date'            => $date,
                        'event_time'            => $timeIn->toDateTimeString(),
                        'event_type'            => 'time_in',
                        'source'                => 'edge_machine',
                        'device_id'             => self::DEVICE_GATE,
                        'location'              => 'Main Gate',
                        'is_corrected'          => false,
                        'is_deduplicated'       => false,
                        'ledger_hash_verified'  => true,
                        'created_by'            => $seedUser?->id,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ],
                    [
                        'employee_id'           => $employee->id,
                        'event_date'            => $date,
                        'event_time'            => $breakStart->toDateTimeString(),
                        'event_type'            => 'break_start',
                        'source'                => 'edge_machine',
                        'device_id'             => self::DEVICE_CANTEEN,
                        'location'              => 'Canteen Entrance',
                        'is_corrected'          => false,
                        'is_deduplicated'       => false,
                        'ledger_hash_verified'  => true,
                        'created_by'            => $seedUser?->id,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ],
                    [
                        'employee_id'           => $employee->id,
                        'event_date'            => $date,
                        'event_time'            => $breakEnd->toDateTimeString(),
                        'event_type'            => 'break_end',
                        'source'                => 'edge_machine',
                        'device_id'             => self::DEVICE_CANTEEN,
                        'location'              => 'Canteen Entrance',
                        'is_corrected'          => false,
                        'is_deduplicated'       => false,
                        'ledger_hash_verified'  => true,
                        'created_by'            => $seedUser?->id,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ],
                    [
                        'employee_id'           => $employee->id,
                        'event_date'            => $date,
                        'event_time'            => $timeOut->toDateTimeString(),
                        'event_type'            => 'time_out',
                        'source'                => 'edge_machine',
                        'device_id'             => self::DEVICE_GATE,
                        'location'              => 'Main Gate',
                        'is_corrected'          => false,
                        'is_deduplicated'       => false,
                        'ledger_hash_verified'  => true,
                        'created_by'            => $seedUser?->id,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ],
                ]);

                $eventsCreated++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("  → Created {$eventsCreated} employee-days of events (4 events each)");
        $this->command->info("  → Skipped {$daySkipped} employee-days (already had events)");

        // ── Step 2: Generate daily_attendance_summary via artisan ────────────
        $this->command->info('');
        $this->command->info('Step 2  →  Generating daily attendance summaries...');
        $this->command->info('         (timekeeping:generate-daily-summaries --force --auto-finalize per date)');

        $summaryBar = $this->command->getOutput()->createProgressBar(count($workDays));
        $summaryBar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  date: %message%');
        $summaryBar->start();

        $summaryErrors = 0;

        foreach ($workDays as $date) {
            $summaryBar->setMessage($date);

            $exitCode = Artisan::call('timekeeping:generate-daily-summaries', [
                '--date'          => $date,
                '--force'         => true,
                '--auto-finalize' => true,
            ]);

            if ($exitCode !== 0) {
                $summaryErrors++;
            }

            $summaryBar->advance();
        }

        $summaryBar->finish();
        $this->command->newLine(2);

        if ($summaryErrors > 0) {
            $this->command->warn("  ⚠  {$summaryErrors} date(s) had errors during summary generation.");
            $this->command->warn('     Possible cause: employees have no department-linked WorkSchedule.');
            $this->command->warn('     Check: php artisan tinker → WorkSchedule::count()');
        } else {
            $this->command->info('  → All summaries generated and auto-finalized successfully.');
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('════════════════════════════════════════');
        $this->command->info('✅  AttendanceEventsSeeder complete');
        $this->command->info('');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Date range',       self::SEED_FROM . ' → ' . self::SEED_TO],
                ['Working days',     count($workDays)],
                ['Employees',        $employees->count()],
                ['Employee-days',    $eventsCreated . ' created, ' . $daySkipped . ' skipped'],
                ['Summary errors',   $summaryErrors],
            ]
        );

        $this->command->info('');
        $this->command->info('To re-process a single date manually:');
        $this->command->info('  php artisan timekeeping:generate-daily-summaries --date=2026-02-03 --force --auto-finalize');
        $this->command->info('');
        $this->command->info('To finalize all summaries in a range (if auto-finalize failed):');
        $this->command->info("  php artisan tinker --execute=\"App\\Models\\DailyAttendanceSummary");
        $this->command->info("    ::whereBetween('attendance_date',['2026-02-02','2026-03-06'])");
        $this->command->info("    ->update(['is_finalized'=>true]);\"");
    }

    /**
     * Return all Monday–Friday dates (Y-m-d strings) in the inclusive range.
     */
    private function getWorkingDays(string $start, string $end): array
    {
        $days   = [];
        $cursor = Carbon::parse($start);
        $last   = Carbon::parse($end);

        while ($cursor->lte($last)) {
            if (! $cursor->isWeekend()) {
                $days[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $days;
    }
}
