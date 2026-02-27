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

        // Create attendance events for the last 7 days
        $this->command->info('Generating attendance events for last 7 days...');
        
        $progressBar = $this->command->getOutput()->createProgressBar(count($employees) * 7);
        $progressBar->start();

        for ($day = 6; $day >= 0; $day--) {
            $date = now()->subDays($day);

            // Skip weekends (Saturday = 6, Sunday = 0)
            if ($date->dayOfWeek === 0 || $date->dayOfWeek === 6) {
                $progressBar->advance(count($employees));
                continue;
            }

            foreach ($employees as $employee) {
                $this->createAttendanceForEmployee($employee, $date);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('Attendance events generated successfully');
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

        // Create check-in event
        AttendanceEvent::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'event_date' => $date,
                'event_type' => 'time_in',
                'ledger_sequence_id' => rand(10000, 999999),
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
                'ledger_sequence_id' => rand(10000, 999999),
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
            
            AttendanceEvent::create([
                'employee_id' => $employee->id,
                'event_date' => $date,
                'event_type' => 'time_out',
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
}
