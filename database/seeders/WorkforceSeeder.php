<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkSchedule;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeRotation;
use App\Models\RotationScheduleConfig;
use Carbon\Carbon;

class WorkforceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get departments (ensure they exist)
        $departments = Department::pluck('id')->toArray();
        if (empty($departments)) {
            $this->command->warn('No departments found. Skipping workforce seeding.');
            return;
        }

        // Get employees (ensure they exist)
        $employees = Employee::where('status', 'active')->pluck('id')->toArray();
        if (empty($employees)) {
            $this->command->warn('No active employees found. Skipping workforce seeding.');
            return;
        }

        $this->command->info('Seeding Work Schedules...');
        $schedules = $this->seedWorkSchedules($departments);

        $this->command->info('Seeding Employee Rotations...');
        $rotations = $this->seedEmployeeRotations();

        $this->command->info('Seeding Employee Schedules...');
        $this->seedEmployeeSchedules($schedules, $employees);

        $this->command->info('Seeding Rotation Assignments...');
        $this->seedRotationAssignments($rotations, $employees);

        $this->command->info('Seeding Shift Assignments...');
        $this->seedShiftAssignments($schedules, $employees);

        $this->command->info('Workforce seeding completed!');
    }

    /**
     * Seed work schedules
     */
    private function seedWorkSchedules(array $departments): array
    {
        $schedules = [];
        $deptId = $departments[array_rand($departments)];

        // 1. Day Shift (6 AM - 2 PM, Mon-Fri)
        $schedules[] = WorkSchedule::create([
            'name' => 'Standard Day Shift',
            'description' => 'Regular day shift for manufacturing floor - 6 AM to 2 PM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => '06:00:00',
            'monday_end' => '14:00:00',
            'tuesday_start' => '06:00:00',
            'tuesday_end' => '14:00:00',
            'wednesday_start' => '06:00:00',
            'wednesday_end' => '14:00:00',
            'thursday_start' => '06:00:00',
            'thursday_end' => '14:00:00',
            'friday_start' => '06:00:00',
            'friday_end' => '14:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 1.5,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 2. Night Shift (10 PM - 6 AM, Mon-Fri)
        $schedules[] = WorkSchedule::create([
            'name' => 'Standard Night Shift',
            'description' => 'Night shift for 24/7 operations - 10 PM to 6 AM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => '22:00:00',
            'monday_end' => '06:00:00',
            'tuesday_start' => '22:00:00',
            'tuesday_end' => '06:00:00',
            'wednesday_start' => '22:00:00',
            'wednesday_end' => '06:00:00',
            'thursday_start' => '22:00:00',
            'thursday_end' => '06:00:00',
            'friday_start' => '22:00:00',
            'friday_end' => '06:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 1.75,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 3. Afternoon Shift (2 PM - 10 PM, Mon-Fri)
        $schedules[] = WorkSchedule::create([
            'name' => 'Afternoon Shift',
            'description' => 'Afternoon shift - 2 PM to 10 PM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => '14:00:00',
            'monday_end' => '22:00:00',
            'tuesday_start' => '14:00:00',
            'tuesday_end' => '22:00:00',
            'wednesday_start' => '14:00:00',
            'wednesday_end' => '22:00:00',
            'thursday_start' => '14:00:00',
            'thursday_end' => '22:00:00',
            'friday_start' => '14:00:00',
            'friday_end' => '22:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 1.5,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 4. Weekend Shift (8 AM - 5 PM, Sat-Sun)
        $schedules[] = WorkSchedule::create([
            'name' => 'Weekend Shift',
            'description' => 'Weekend coverage shift - 8 AM to 5 PM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => null,
            'monday_end' => null,
            'tuesday_start' => null,
            'tuesday_end' => null,
            'wednesday_start' => null,
            'wednesday_end' => null,
            'thursday_start' => null,
            'thursday_end' => null,
            'friday_start' => null,
            'friday_end' => null,
            'saturday_start' => '08:00:00',
            'saturday_end' => '17:00:00',
            'sunday_start' => '08:00:00',
            'sunday_end' => '17:00:00',
            'lunch_break_duration' => 60,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 2.0,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 5. Manufacturing Floor Shift (7 AM - 3 PM, Mon-Sat)
        $schedules[] = WorkSchedule::create([
            'name' => 'Manufacturing Floor Shift',
            'description' => 'Production floor shift - 7 AM to 3 PM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => '07:00:00',
            'monday_end' => '15:00:00',
            'tuesday_start' => '07:00:00',
            'tuesday_end' => '15:00:00',
            'wednesday_start' => '07:00:00',
            'wednesday_end' => '15:00:00',
            'thursday_start' => '07:00:00',
            'thursday_end' => '15:00:00',
            'friday_start' => '07:00:00',
            'friday_end' => '15:00:00',
            'saturday_start' => '07:00:00',
            'saturday_end' => '15:00:00',
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 1.5,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 6. Maintenance Shift (5 PM - 2 AM, Mon-Fri)
        $schedules[] = WorkSchedule::create([
            'name' => 'Maintenance Shift',
            'description' => 'Maintenance and cleaning shift - 5 PM to 2 AM',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => Carbon::now()->addMonths(6)->endOfDay(),
            'status' => 'active',
            'monday_start' => '17:00:00',
            'monday_end' => '02:00:00',
            'tuesday_start' => '17:00:00',
            'tuesday_end' => '02:00:00',
            'wednesday_start' => '17:00:00',
            'wednesday_end' => '02:00:00',
            'thursday_start' => '17:00:00',
            'thursday_end' => '02:00:00',
            'friday_start' => '17:00:00',
            'friday_end' => '02:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 9,
            'overtime_rate_multiplier' => 1.75,
            'is_template' => false,
            'created_by' => 1,
        ]);

        // 7. Extended Day Shift (Template)
        $schedules[] = WorkSchedule::create([
            'name' => '24/7 Rotating Template',
            'description' => 'Template for 24/7 rotating shift operations',
            'department_id' => $deptId,
            'effective_date' => Carbon::now()->startOfDay(),
            'expires_at' => null,
            'status' => 'draft',
            'monday_start' => '06:00:00',
            'monday_end' => '14:00:00',
            'tuesday_start' => '14:00:00',
            'tuesday_end' => '22:00:00',
            'wednesday_start' => '22:00:00',
            'wednesday_end' => '06:00:00',
            'thursday_start' => '06:00:00',
            'thursday_end' => '14:00:00',
            'friday_start' => '14:00:00',
            'friday_end' => '22:00:00',
            'saturday_start' => '22:00:00',
            'saturday_end' => '06:00:00',
            'sunday_start' => '06:00:00',
            'sunday_end' => '14:00:00',
            'lunch_break_duration' => 30,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 8,
            'overtime_rate_multiplier' => 1.5,
            'is_template' => true,
            'created_by' => 1,
        ]);

        return $schedules;
    }

    /**
     * Seed employee rotations
     */
    private function seedEmployeeRotations(): array
    {
        $rotations = [];

        // 4x2 Pattern - Manufacturing Standard
        $rotations[] = EmployeeRotation::create([
            'name' => '4x2 Manufacturing Standard',
            'description' => '4 days work, 2 days rest - Standard manufacturing rotation',
            'pattern_type' => '4x2',
            'pattern_json' => json_encode([
                'work_days' => 4,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 0, 0],
            ]),
            'is_active' => true,
            'created_by' => 1,
        ]);

        // 6x1 Pattern - Production Peak
        $rotations[] = EmployeeRotation::create([
            'name' => '6x1 Production Peak',
            'description' => '6 days work, 1 day rest - For high production periods',
            'pattern_type' => '6x1',
            'pattern_json' => json_encode([
                'work_days' => 6,
                'rest_days' => 1,
                'pattern' => [1, 1, 1, 1, 1, 1, 0],
            ]),
            'is_active' => true,
            'created_by' => 1,
        ]);

        // 5x2 Pattern - Office Standard
        $rotations[] = EmployeeRotation::create([
            'name' => '5x2 Office Standard',
            'description' => '5 days work, 2 days rest - Traditional work week',
            'pattern_type' => '5x2',
            'pattern_json' => json_encode([
                'work_days' => 5,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 1, 0, 0],
            ]),
            'is_active' => true,
            'created_by' => 1,
        ]);

        // Custom 3-3-1 Pattern
        $rotations[] = EmployeeRotation::create([
            'name' => 'Custom 3-3-1 Flexible',
            'description' => '3 days work, 3 days work, 1 day rest - Flexible rotation',
            'pattern_type' => 'custom',
            'pattern_json' => json_encode([
                'work_days' => 6,
                'rest_days' => 1,
                'pattern' => [1, 1, 1, 1, 1, 1, 0],
            ]),
            'is_active' => true,
            'created_by' => 1,
        ]);

        // Custom 2-2-2-2 Pattern
        $rotations[] = EmployeeRotation::create([
            'name' => 'Custom 2-2-2 Shift Rotation',
            'description' => '2 days day shift, 2 days night shift, 2 days rest',
            'pattern_type' => 'custom',
            'pattern_json' => json_encode([
                'work_days' => 4,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 0, 0],
            ]),
            'is_active' => true,
            'created_by' => 1,
        ]);

        // Custom 12-2 Extended Shift
        $rotations[] = EmployeeRotation::create([
            'name' => 'Extended 12-2 Pattern',
            'description' => '12 days work, 2 days rest - For specialized roles',
            'pattern_type' => 'custom',
            'pattern_json' => json_encode([
                'work_days' => 12,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0],
            ]),
            'is_active' => false,
            'created_by' => 1,
        ]);

        return $rotations;
    }

    /**
     * Seed employee schedules
     */
    private function seedEmployeeSchedules(array $schedules, array $employees): void
    {
        $employeesToAssign = array_slice($employees, 0, min(50, count($employees)));

        foreach ($employeesToAssign as $employeeId) {
            $schedule = $schedules[array_rand($schedules)];
            
            EmployeeSchedule::create([
                'employee_id' => $employeeId,
                'work_schedule_id' => $schedule->id,
                'effective_date' => Carbon::now()->startOfDay(),
                'end_date' => Carbon::now()->addMonths(3)->endOfDay(),
                'is_active' => true,
                'created_by' => 1,
            ]);
        }
    }

    /**
     * Seed rotation assignments
     */
    /**
     * Seed rotation assignments with schedule configurations.
     *
     * Phase 2 & 4 Enhancement: This method now creates both RotationAssignment and
     * RotationScheduleConfig to explicitly link which schedule applies to each rotation.
     *
     * Pairing Strategy:
     * - 4x2 rotation pairs with Mon-Fri day/night schedules (typical manufacturing)
     * - 5x2 rotation pairs with Mon-Fri schedules (typical office)
     * - 6x1 rotation pairs with extended coverage schedules (production peaks)
     * - Custom patterns pair with matching coverage schedules
     *
     * Expected Results After Seeding:
     * - 40 employees assigned to random rotations
     * - Each gets explicit schedule config for 3 months
     * - Patterns tested: 4x2+MonFri, 5x2+MonFri, 6x1+MonSat, etc.
     */
    private function seedRotationAssignments(array $rotations, array $employees): void
    {
        $employeesToAssign = array_slice($employees, 0, min(40, count($employees)));
        $schedules = WorkSchedule::all();

        foreach ($employeesToAssign as $employeeId) {
            $rotation = $rotations[array_rand($rotations)];

            // Create rotation assignment
            $assignment = RotationAssignment::create([
                'employee_id' => $employeeId,
                'rotation_id' => $rotation->id,
                'start_date' => Carbon::now()->startOfDay(),
                'end_date' => Carbon::now()->addMonths(3)->endOfDay(),
                'is_active' => true,
                'created_by' => 1,
            ]);

            // Phase 2: Create schedule configuration
            // Assign a schedule that pairs well with the rotation pattern
            // For realistic data, we match rotation type with compatible schedules
            $schedule = $this->getCompatibleSchedule($rotation, $schedules);

            if ($schedule) {
                RotationScheduleConfig::create([
                    'rotation_assignment_id' => $assignment->id,
                    'work_schedule_id' => $schedule->id,
                    'effective_date' => $assignment->start_date,
                    'end_date' => $assignment->end_date,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info("Created rotation assignments with schedule configs for " . count($employeesToAssign) . " employees");
    }

    /**
     * Get a compatible work schedule for a rotation pattern.
     *
     * Matching Logic:
     * - 4x2 & 5x2 (work patterns ending on weekdays) → Mon-Fri schedules
     * - 6x1 (extended work) → Mon-Sat schedules
     * - Custom patterns → Default to first available schedule
     *
     * This ensures realistic pairings that don't cause conflicts.
     */
    private function getCompatibleSchedule(EmployeeRotation $rotation, $schedules)
    {
        if ($schedules->isEmpty()) {
            return null;
        }

        $patternType = $rotation->pattern_type;

        // Match schedule based on rotation type
        return match ($patternType) {
            '4x2', '5x2' => $schedules->firstWhere('name', 'like', '%Day Shift%') ?? $schedules->first(),
            '6x1' => $schedules->firstWhere('name', 'like', '%Manufacturing%') ?? $schedules->first(),
            '3x3', '2x2' => $schedules->whereNotNull('saturday_start')->first() ?? $schedules->first(),
            default => $schedules->first(),
        };
    }

    /**
     * Seed shift assignments
     */
    private function seedShiftAssignments(array $schedules, array $employees): void
    {
        $employeesToAssign = array_slice($employees, 0, min(30, count($employees)));
        $startDate = Carbon::now()->startOfDay();

        // Create assignments for next 60 days
        for ($dayOffset = 0; $dayOffset < 60; $dayOffset++) {
            $date = $startDate->copy()->addDays($dayOffset);

            // Skip some days randomly for realistic data
            if (rand(1, 100) > 85) {
                continue;
            }

            foreach ($employeesToAssign as $employeeId) {
                // Assign randomly to 60% of employees each day
                if (rand(1, 100) > 60) {
                    continue;
                }

                $schedule = $schedules[array_rand($schedules)];
                $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday
                $dayName = strtolower($date->format('l'));

                $startColumn = $dayName . '_start';
                $endColumn = $dayName . '_end';

                if ($schedule->$startColumn && $schedule->$endColumn) {
                    ShiftAssignment::create([
                        'employee_id' => $employeeId,
                        'schedule_id' => $schedule->id,
                        'date' => $date,
                        'shift_start' => $schedule->$startColumn,
                        'shift_end' => $schedule->$endColumn,
                        'shift_type' => $this->getShiftType($schedule->$startColumn),
                        'status' => 'scheduled',
                        'is_overtime' => false,
                        'overtime_hours' => 0,
                        'location' => 'Production Floor',
                        'created_by' => 1,
                    ]);
                }
            }
        }
    }

    /**
     * Determine shift type based on start time
     */
    private function getShiftType(string $startTime): string
    {
        $hour = (int) explode(':', $startTime)[0];

        if ($hour >= 6 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 18) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }
}
