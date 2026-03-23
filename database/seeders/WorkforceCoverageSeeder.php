<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeavePolicy;
use App\Models\LeaveBalance;
use App\Models\WorkSchedule;
use App\Models\DailyAttendanceSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workforce Coverage Seeder
 * 
 * Generates realistic workforce coverage scenarios based on existing employees.
 * This seeder creates:
 * 
 * 1. Sample Leave Requests
 *    - Creates leave requests for 30% of active employees
 *    - Varies dates, types, and durations for realistic coverage impact
 *    - Sets different approval statuses (approved, pending, rejected)
 * 
 * 2. Workforce Coverage Cache
 *    - Calculates and caches coverage percentages by department
 *    - Simulates different coverage scenarios (optimal, adequate, low, critical)
 *    - Covers 30-day period starting today
 * 
 * 3. Daily Attendance Records
 *    - Creates attendance summaries for all active employees
 *    - Integrates leave status for employees on approved leaves
 *    - Tracks work hours, breaks, and overtime
 * 
 * Usage:
 *   php artisan db:seed --class=WorkforceCoverageSeeder
 * 
 * Dependencies:
 *   - Employees must exist (run EmployeeSeeder first)
 *   - Departments must exist
 *   - Leave Policies must exist (run LeavePolicySeeder)
 *   - Work Schedules must exist (run WorkforceSeeder)
 */
class WorkforceCoverageSeeder extends Seeder
{
    protected int $totalDays = 30;
    protected $startDate;
    protected $endDate;

    public function run(): void
    {
        $this->startDate = Carbon::now()->startOfDay();
        $this->endDate = $this->startDate->clone()->addDays($this->totalDays - 1);

        // Get active employees
        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found. Skipping workforce coverage seeding.');
            return;
        }

        $this->command->info("Seeding workforce coverage for {$employees->count()} active employees...");

        // 0. Create shift assignments (must be done BEFORE leave requests for accurate coverage)
        $this->command->info('Creating shift assignments...');
        $this->seedShiftAssignments($employees);

        // 1. Create sample leave requests (30% of employees)
        $this->command->info('Creating sample leave requests...');
        $this->seedLeaveRequests($employees);

        // 2. Create daily attendance records
        $this->command->info('Creating daily attendance records...');
        $this->seedDailyAttendance($employees);

        // 3. Calculate and cache workforce coverage by department
        $this->command->info('Calculating and caching workforce coverage...');
        $this->seedWorkforceCoverageCache($employees);

        $this->command->info('Workforce coverage seeding completed!');
    }

    /**
     * Seed sample leave requests
     * Creates leave requests for ~30% of employees with varied scenarios
     */
    private function seedLeaveRequests($employees): void
    {
        $leavePolicies = LeavePolicy::where('is_active', true)->get();
        if ($leavePolicies->isEmpty()) {
            $this->command->warn('No active leave policies found. Skipping leave request seeding.');
            return;
        }

        $employeeCount = count($employees);
        $leaveEmployeeCount = max(1, (int)($employeeCount * 0.3)); // 30% of employees
        $leaveEmployees = $employees->random($leaveEmployeeCount);

        foreach ($leaveEmployees as $employee) {
            // Create 1-3 leave requests per employee
            $requestCount = rand(1, 3);
            
            for ($i = 0; $i < $requestCount; $i++) {
                $startDate = $this->startDate->clone()->addDays(rand(0, $this->totalDays - 8));
                $duration = rand(1, 5); // 1-5 days
                $endDate = $startDate->clone()->addDays($duration - 1);

                // Don't create leaves outside our seeding period
                if ($endDate->isAfter($this->endDate)) {
                    continue;
                }

                $policy = $leavePolicies->random();
                $leaveStatus = $this->getRandomLeaveStatus();
                $status = $leaveStatus['status'];
                $autoApproved = $leaveStatus['auto_approved'];
                
                // Determine days requested (accounting for weekends)
                $daysRequested = $this->calculateBusinessDays($startDate, $endDate);

                // Check if employee has sufficient balance
                $balance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_policy_id' => $policy->id,
                        'year' => Carbon::now()->year,
                    ],
                    [
                        'earned' => $policy->annual_entitlement,
                        'used' => 0,
                        'remaining' => $policy->annual_entitlement,
                        'carried_forward' => 0,
                        'forfeited' => 0,
                    ]
                );

                // Only create leave request if employee has balance
                if ($balance->remaining >= $daysRequested) {
                    $leaveRequest = LeaveRequest::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'start_date' => $startDate->format('Y-m-d'),
                            'end_date' => $endDate->format('Y-m-d'),
                            'leave_policy_id' => $policy->id,
                        ],
                        [
                            'department_id' => $employee->department_id,
                            'days_requested' => $daysRequested,
                            'reason' => $this->generateLeaveReason($policy->code),
                            'status' => $status,
                            'supervisor_id' => $employee->immediate_supervisor_id,
                            'supervisor_approved_at' => $status !== 'pending' ? now() : null,
                            'approved_by_manager_id' => in_array($status, ['approved']) ? 1 : null,
                            'auto_approved' => $autoApproved,
                            'submitted_at' => $startDate->clone()->subDays(rand(1, 10)),
                            'submitted_by' => $employee->user_id ?? 1, // Fallback to system user (ID 1) if no user_id
                        ]
                    );

                    // Update leave balance if approved
                    if ($status === 'approved') {
                        $balance->used += $daysRequested;
                        $balance->remaining -= $daysRequested;
                        $balance->save();
                    }
                }
            }
        }

        $this->command->info("Created leave requests for {$leaveEmployeeCount} employees");
    }

    /**
     * Seed daily attendance records
     * Creates attendance summaries for all employees covering the seeding period
     */
    private function seedDailyAttendance($employees): void
    {
        $schedules = WorkSchedule::where('is_template', false)->get();
        if ($schedules->isEmpty()) {
            $this->command->warn('No work schedules found. Skipping attendance seeding.');
            return;
        }

        $recordCount = 0;
        $currentDate = $this->startDate->clone();

        while ($currentDate->lte($this->endDate)) {
            // Skip weekends (optional - adjust based on your business)
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }

            foreach ($employees as $employee) {
                // Check if employee is on approved leave
                $onLeave = LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('end_date', '>=', $currentDate)
                    ->exists();

                // Get employee's work schedule
                $schedule = $schedules->random();

                // Generate time entries
                if ($onLeave) {
                    // Employee is on leave
                    $summary = DailyAttendanceSummary::firstOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'attendance_date' => $currentDate->format('Y-m-d'),
                        ],
                        [
                            'work_schedule_id' => $schedule->id,
                            'is_on_leave' => true,
                            'is_present' => false,
                            'time_in' => null,
                            'time_out' => null,
                            'total_hours_worked' => 0,
                            'regular_hours' => 0,
                            'overtime_hours' => 0,
                            'is_late' => false,
                            'is_undertime' => false,
                            'is_overtime' => false,
                            'is_finalized' => true,
                        ]
                    );
                } else {
                    // Employee is present
                    $isPresent = rand(1, 100) > 5; // 95% present
                    
                    if ($isPresent) {
                        $timeIn = $currentDate->clone()->setTime(6, rand(0, 59))->subMinutes(rand(0, 30));
                        $timeOut = $timeIn->clone()->addHours(8)->addMinutes(30);
                        $breakDuration = 30;
                        $totalHours = 8.5;
                        $regularHours = 8.5;
                        $overtimeHours = 0;

                        $isLate = $timeIn->format('Hi') > '060000';
                        $isUndertime = $totalHours < 8;

                        $summary = DailyAttendanceSummary::firstOrCreate(
                            [
                                'employee_id' => $employee->id,
                                'attendance_date' => $currentDate->format('Y-m-d'),
                            ],
                            [
                                'work_schedule_id' => $schedule->id,
                                'is_on_leave' => false,
                                'is_present' => true,
                                'time_in' => $timeIn,
                                'time_out' => $timeOut,
                                'break_start' => $timeIn->clone()->addHours(4),
                                'break_end' => $timeIn->clone()->addHours(4)->addMinutes($breakDuration),
                                'break_duration' => $breakDuration,
                                'total_hours_worked' => $totalHours,
                                'regular_hours' => $regularHours,
                                'overtime_hours' => $overtimeHours,
                                'is_late' => $isLate,
                                'late_minutes' => $isLate ? rand(1, 60) : 0,
                                'is_undertime' => $isUndertime,
                                'undertime_minutes' => $isUndertime ? rand(1, 60) : 0,
                                'is_overtime' => $overtimeHours > 0,
                                'is_finalized' => $currentDate->isBefore(Carbon::now()),
                                'calculated_at' => now(),
                            ]
                        );
                    } else {
                        // Absent
                        $summary = DailyAttendanceSummary::firstOrCreate(
                            [
                                'employee_id' => $employee->id,
                                'attendance_date' => $currentDate->format('Y-m-d'),
                            ],
                            [
                                'work_schedule_id' => $schedule->id,
                                'is_on_leave' => false,
                                'is_present' => false,
                                'time_in' => null,
                                'time_out' => null,
                                'total_hours_worked' => 0,
                                'regular_hours' => 0,
                                'overtime_hours' => 0,
                                'is_undertime' => true,
                                'undertime_minutes' => 480, // 8 hours
                                'is_finalized' => $currentDate->isBefore(Carbon::now()),
                            ]
                        );
                    }
                }

                $recordCount++;
            }

            $currentDate->addDay();
        }

        $this->command->info("Created {$recordCount} attendance records");
    }

    /**
     * Seed workforce coverage cache
     * Calculates coverage percentages by department and caches them
     */
    private function seedWorkforceCoverageCache($employees): void
    {
        $departments = Department::where('is_active', true)->get();
        $cacheRecords = 0;

        foreach ($departments as $department) {
            // Get employees in this department
            $deptEmployees = $employees->where('department_id', $department->id);
            if ($deptEmployees->isEmpty()) {
                continue;
            }

            $totalEmployees = $deptEmployees->count();
            $currentDate = $this->startDate->clone();

            while ($currentDate->lte($this->endDate)) {
                // Count available employees (not on approved leave)
                $availableEmployees = 0;

                foreach ($deptEmployees as $employee) {
                    $onLeave = LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('end_date', '>=', $currentDate)
                        ->exists();

                    if (!$onLeave) {
                        $availableEmployees++;
                    }
                }

                // Calculate coverage percentage
                $coveragePercentage = ($availableEmployees / $totalEmployees) * 100;

                // Cache the coverage data
                DB::table('workforce_coverage_cache')->updateOrInsert(
                    [
                        'department_id' => $department->id,
                        'date' => $currentDate->format('Y-m-d'),
                    ],
                    [
                        'employees_available' => $availableEmployees,
                        'total_employees' => $totalEmployees,
                        'coverage_percentage' => round($coveragePercentage, 2),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $cacheRecords++;
                $currentDate->addDay();
            }
        }

        $this->command->info("Created {$cacheRecords} workforce coverage cache records");
    }

    /**
     * Seed shift assignments for all employees
     * Creates actual daily shift assignments so coverage can be calculated
     */
    private function seedShiftAssignments($employees): void
    {
        $schedules = WorkSchedule::where('is_template', false)->get();
        if ($schedules->isEmpty()) {
            $this->command->warn('No work schedules found. Skipping shift assignment seeding.');
            return;
        }

        $assignmentCount = 0;
        $currentDate = $this->startDate->clone();

        while ($currentDate->lte($this->endDate)) {
            // Skip weekends for now (adjust based on business needs)
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }

            foreach ($employees as $employee) {
                // Randomly assign a schedule to each employee
                $schedule = $schedules->random();
                
                // Get shift times from schedule based on day of week
                $dayOfWeek = strtolower($currentDate->format('l')); // monday, tuesday, etc.
                $startTimeField = "{$dayOfWeek}_start";
                $endTimeField = "{$dayOfWeek}_end";

                if (!$schedule->$startTimeField || !$schedule->$endTimeField) {
                    continue; // Skip if no schedule for this day
                }

                $shiftStart = $currentDate->clone()->format('Y-m-d') . ' ' . $schedule->$startTimeField;
                $shiftEnd = $currentDate->clone()->format('Y-m-d') . ' ' . $schedule->$endTimeField;

                // Handle overnight shifts (e.g., 10 PM to 6 AM next day)
                if (strtotime($shiftEnd) <= strtotime($shiftStart)) {
                    $shiftEnd = $currentDate->clone()->addDay()->format('Y-m-d') . ' ' . $schedule->$endTimeField;
                }

                // Create shift assignment
                \App\Models\ShiftAssignment::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $currentDate->format('Y-m-d'),
                        'shift_start' => $shiftStart,
                    ],
                    [
                        'schedule_id' => $schedule->id,
                        'department_id' => $employee->department_id,
                        'shift_end' => $shiftEnd,
                        'shift_type' => $this->getShiftType($schedule->$startTimeField),
                        'location' => 'Main Office',
                        'has_conflict' => false,
                        'status' => 'scheduled',
                        'assignment_source' => 'seeder',
                        'generated_by_user_id' => 1,
                        'created_by' => 1,
                    ]
                );

                $assignmentCount++;
            }

            $currentDate->addDay();
        }

        $this->command->info("Created {$assignmentCount} shift assignments");
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

    /**
     * Generate a realistic reason based on leave policy
     */
    private function generateLeaveReason($policyCode): string
    {
        $reasons = [
            'SL' => [
                'Fever and body aches',
                'Flu symptoms',
                'Medical checkup appointment',
                'Dental treatment',
                'Recovery from illness',
                'Doctor\'s appointment scheduled',
            ],
            'VL' => [
                'Planned vacation with family',
                'Rest and relaxation',
                'Travel to provincial home',
                'Personal time off',
                'Family gathering',
            ],
            'EL' => [
                'Family emergency',
                'Personal urgent matter',
                'Emergency at home',
                'Critical family situation',
            ],
            'PL' => [
                'Personal errands',
                'Administrative work',
                'Personal matters',
                'School-related concern',
            ],
            'BL' => [
                'Death in family',
                'Funeral arrangements',
                'Bereavement',
            ],
        ];

        $reasonList = $reasons[$policyCode] ?? ['Leave request'];
        return $reasonList[array_rand($reasonList)];
    }

    /**
     * Get a random leave status weighted towards approved
     * Returns array with 'status' and 'auto_approved' flag
     */
    private function getRandomLeaveStatus(): array
    {
        $rand = rand(1, 100);
        
        if ($rand <= 70) {
            return ['status' => 'approved', 'auto_approved' => false]; // 70% regular approved
        } elseif ($rand <= 85) {
            return ['status' => 'approved', 'auto_approved' => true]; // 15% auto-approved
        } elseif ($rand <= 95) {
            return ['status' => 'pending', 'auto_approved' => false]; // 10% pending
        } else {
            return ['status' => 'rejected', 'auto_approved' => false]; // 5% rejected
        }
    }

    /**
     * Calculate business days between two dates
     * Excludes weekends
     */
    private function calculateBusinessDays(Carbon $start, Carbon $end): float
    {
        $days = 0;
        $current = $start->clone();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return (float) $days;
    }
}
