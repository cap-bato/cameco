<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Department;
use App\Models\AttendanceEvent;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

/**
 * TimekeepingTestDataSeeder
 * 
 * Seeds test data for timekeeping module integration tests.
 * Creates employees, departments, attendance events, and generates daily summaries.
 * 
 * Task 3.2: Frontend Integration Tests
 */
class TimekeepingTestDataSeeder extends Seeder
{
    // Date range aligned with payroll calculation test period
    private const SEED_DATE_FROM = '2026-03-01';
    private const SEED_DATE_TO   = '2026-03-15';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding timekeeping test data...');

        // Get or create departments
        $departments = [
            'Rolling Mill 3' => Department::firstOrCreate(
                ['name' => 'Rolling Mill 3'],
                ['code' => 'RM3', 'is_active' => true]
            ),
            'Wire Mill' => Department::firstOrCreate(
                ['name' => 'Wire Mill'],
                ['code' => 'WM', 'is_active' => true]
            ),
            'Quality Control' => Department::firstOrCreate(
                ['name' => 'Quality Control'],
                ['code' => 'QC', 'is_active' => true]
            ),
            'Maintenance' => Department::firstOrCreate(
                ['name' => 'Maintenance'],
                ['code' => 'MNT', 'is_active' => true]
            ),
        ];

        $this->command->info('Created/retrieved ' . count($departments) . ' departments');

        // Create test employees using factory
        $departmentArray = array_values($departments);
        $employees = [];

        $this->command->info('Creating 20 test employees...');
        
        for ($i = 1; $i <= 20; $i++) {
            $department = $departmentArray[$i % 4];
            $empNumber = 'EMP' . str_pad($i, 3, '0', STR_PAD_LEFT);
            
            // Check if employee exists, if not create with factory
            $employee = Employee::where('employee_number', $empNumber)->first();
            if (!$employee) {
                $employee = Employee::factory()
                    ->for($department)
                    ->create([
                        'employee_number' => $empNumber,
                        'status' => 'active',
                    ]);
            }

            $employees[] = $employee;
        }

        $this->command->info('Created/retrieved 20 test employees');

        // Assign default work schedule to employees that don't have one
        $this->command->info('Assigning work schedules to employees...');
        $defaultSchedule = \App\Models\WorkSchedule::first();
        $administratorUser = \App\Models\User::first();
        
        if ($defaultSchedule && $administratorUser) {
            $scheduleCount = 0;
            foreach ($employees as $employee) {
                $hasSchedule = \App\Models\EmployeeSchedule::where('employee_id', $employee->id)
                    ->whereNull('end_date')
                    ->exists();
                
                if (!$hasSchedule) {
                    \App\Models\EmployeeSchedule::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'work_schedule_id' => $defaultSchedule->id,
                            'effective_date' => now()->subDays(30)->toDateString(),
                        ],
                        [
                            'end_date' => null,
                            'created_by' => $administratorUser->id,
                        ]
                    );
                    $scheduleCount++;
                }
            }
            $this->command->info("  → Assigned work schedule to {$scheduleCount} employees");
        }

        // Create attendance events for payroll-period working days
        $workDays = $this->getWorkingDays(self::SEED_DATE_FROM, self::SEED_DATE_TO);
        $this->command->info('Generating attendance events for ' . count($workDays) . ' working days...');

        $progressBar = $this->command->getOutput()->createProgressBar(count($employees) * count($workDays));
        $progressBar->start();

        foreach ($workDays as $date) {
            foreach ($employees as $employee) {
                $this->createAttendanceForEmployee($employee, $date);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('Attendance events generated successfully');

        // Step 2: Generate daily summaries from attendance events
        $this->command->info('Generating daily attendance summaries from events...');
        
        $summaryCreated = 0;

        foreach ($workDays as $date) {
            foreach ($employees as $employee) {
                try {
                    // Check if summary already exists
                    $existing = \App\Models\DailyAttendanceSummary::where('employee_id', $employee->id)
                        ->whereDate('attendance_date', $date)
                        ->exists();
                    
                    if ($existing) {
                        continue;
                    }

                    // Check if attendance events exist
                    $events = \App\Models\AttendanceEvent::where('employee_id', $employee->id)
                        ->whereDate('event_date', $date)
                        ->orderBy('event_time', 'asc')
                        ->get();

                    if ($events->isEmpty()) {
                        continue;
                    }

                    // Extract time_in and time_out
                    $timeIn = $events->firstWhere('event_type', 'time_in')?->event_time;
                    $timeOut = $events->lastWhere('event_type', 'time_out')?->event_time;

                    // Calculate hours worked
                    $totalHours = 0;
                    if ($timeIn && $timeOut) {
                        $totalMinutes = $timeIn->diffInMinutes($timeOut);
                        $totalHours = round($totalMinutes / 60, 2);
                    }

                    // Create summary record
                    \App\Models\DailyAttendanceSummary::create([
                        'employee_id' => $employee->id,
                        'attendance_date' => $date->toDateString(),
                        'work_schedule_id' => null,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'break_start' => null,
                        'break_end' => null,
                        'total_hours_worked' => $totalHours,
                        'regular_hours' => $totalHours,
                        'overtime_hours' => 0,
                        'break_duration' => 0,
                        'is_present' => $timeIn ? true : false,
                        'is_late' => false,
                        'is_undertime' => false,
                        'is_overtime' => false,
                        'late_minutes' => null,
                        'undertime_minutes' => null,
                        'is_on_leave' => false,
                        'is_finalized' => false,
                    ]);
                    $summaryCreated++;
                } catch (\Exception $e) {
                    // Log error for debugging but continue
                    \Illuminate\Support\Facades\Log::debug("Summary creation skipped for employee {$employee->id}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("  → Summaries created: {$summaryCreated}");

        // Step 3: Finalize all summaries (mark is_finalized = true so payroll can pick them up)
        $this->command->info('Finalizing attendance summaries for payroll...');
        $finalized = \App\Models\DailyAttendanceSummary::whereBetween('attendance_date', [
            self::SEED_DATE_FROM,
            self::SEED_DATE_TO,
            ])
            ->where('is_finalized', false)
            ->update(['is_finalized' => true]);

        $this->command->info("  → Finalized {$finalized} attendance rows for payroll processing");
        $this->command->info('✓ Timekeeping test data seeded successfully!');
    }

    /**
     * Create attendance events for an employee on a specific date
     */
    private function createAttendanceForEmployee(Employee $employee, Carbon $date): void
    {
        // Random attendance scenario
        $scenario = rand(1, 100);

        if ($scenario <= 10) {
            // 10% absent
            return;
        } elseif ($scenario <= 15) {
            // 5% late arrival (check-in at 9:30 AM instead of 8:00 AM)
            $checkInTime = $date->copy()->setHour(9)->setMinute(random_int(0, 59));
            $checkOutTime = $date->copy()->setHour(17)->setMinute(random_int(0, 59));
        } else {
            // 85% on-time
            $checkInTime = $date->copy()->setHour(8)->setMinute(random_int(0, 30));
            $checkOutTime = $date->copy()->setHour(17)->setMinute(random_int(0, 59));
        }

        $baseSequence = ((int) $date->format('Ymd')) * 100000 + ($employee->id * 10);

        // Create check-in event
        AttendanceEvent::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'event_date' => $date,
                'event_type' => 'time_in',
                'ledger_sequence_id' => $baseSequence + 1,
            ],
            [
                'event_time' => $checkInTime,
                'is_deduplicated' => false,
                'ledger_hash_verified' => true,
                'source' => 'edge_machine',
                'is_corrected' => false,
                'device_id' => 'DEVICE' . rand(1, 10),
                'location' => 'Gate 1',
                'notes' => null,
                'ledger_raw_payload' => [],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create check-out event
        AttendanceEvent::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'event_date' => $date,
                'event_type' => 'time_out',
                'ledger_sequence_id' => $baseSequence + 2,
            ],
            [
                'event_time' => $checkOutTime,
                'is_deduplicated' => false,
                'ledger_hash_verified' => true,
                'source' => 'edge_machine',
                'is_corrected' => false,
                'device_id' => 'DEVICE' . rand(1, 10),
                'location' => 'Gate 1',
                'notes' => null,
                'ledger_raw_payload' => [],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Randomly create some violations/corrections for demonstration
        if (rand(1, 100) <= 20) {
            // 20% get a correction event
            $correctionReason = ['Early departure', 'Manual override', 'Device malfunction'][rand(0, 2)];
            
            AttendanceEvent::firstOrCreate([
                'employee_id' => $employee->id,
                'event_date' => $date,
                'event_type' => 'time_out',
                'ledger_sequence_id' => $baseSequence + 3,
            ], [
                'event_time' => $checkOutTime->copy()->addMinutes(random_int(-30, 30)),
                'is_deduplicated' => false,
                'ledger_hash_verified' => true,
                'source' => 'manual',
                'is_corrected' => true,
                'original_time' => $checkOutTime,
                'correction_reason' => $correctionReason,
                'corrected_by' => 1, // Assume user 1 exists
                'corrected_at' => now(),
                'device_id' => 'DEVICE' . rand(1, 10),
                'location' => 'Gate 1',
                'notes' => 'Correction applied',
                'ledger_raw_payload' => [],
            ]);
        }
    }

    /**
     * Build a list of working days (Mon-Fri) within an inclusive date range.
     *
     * @return array<int, Carbon>
     */
    private function getWorkingDays(string $startDate, string $endDate): array
    {
        $days = [];
        $cursor = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        while ($cursor->lte($end)) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor->copy();
            }

            $cursor->addDay();
        }

        return $days;
    }
}
