<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeavePolicy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates leave requests for both employees with user accounts and those without,
     * generating realistic, story-driven leave scenarios (e.g., sick leaves after absences, vacation leaves after project delivery, etc).
     */
    public function run(): void
    {

        $this->command->info('Seeding leave requests with realistic stories...');

        $policies = LeavePolicy::all()->keyBy('code');
        $employees = Employee::all();
        $userIds = User::pluck('id')->all();

        $faker = \Faker\Factory::create();
        $now = Carbon::now();
        $year = $now->year;

        // Ensure at least one approved leave request for each of the last 6 months for the first employee
        if ($employees->count() > 0 && $policies->count() > 0) {
            $firstEmployee = $employees->first();
            $policy = $policies->first();
            for ($i = 0; $i < 6; $i++) {
                $month = $now->copy()->subMonths($i)->startOfMonth();
                LeaveRequest::create([
                    'employee_id' => $firstEmployee->id,
                    'leave_policy_id' => $policy->id,
                    'start_date' => $month->copy()->addDays(2),
                    'end_date' => $month->copy()->addDays(4),
                    'days_requested' => 3,
                    'reason' => 'Seeded monthly leave for reporting.',
                    'status' => 'approved',
                    'submitted_by' => $firstEmployee->user_id ?? 1,
                    'submitted_at' => $month->copy()->addDay(),
                    'created_at' => $month->copy()->addDay(),
                    'updated_at' => $month->copy()->addDay(),
                ]);
            }
        }

        foreach ($employees as $employee) {
            $hasAccount = $employee->user_id && in_array($employee->user_id, $userIds);
            $baseDate = Carbon::create($year, rand(1, $now->month), rand(1, 20));

            // Story 1: Vacation Leave after project delivery
            if ($employee->department && Str::contains(strtolower($employee->department->name), ['it', 'production', 'operations'])) {
                $policy = $policies['VL'] ?? $policies->first();
                LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'start_date' => $baseDate->copy()->addDays(2),
                    'end_date' => $baseDate->copy()->addDays(6),
                    'days_requested' => 5,
                    'reason' => $hasAccount ? 'Vacation after successful project delivery.' : 'Family trip after overtime period.',
                    'status' => $hasAccount ? 'approved' : 'pending',
                    'submitted_by' => $employee->user_id ?? 1,
                    'submitted_at' => $baseDate,
                    'created_at' => $baseDate,
                    'updated_at' => $baseDate,
                ]);
            }

            // Story 2: Sick Leave after consecutive absences
            if ($faker->boolean(60)) {
                $policy = $policies['SL'] ?? $policies->first();
                $sickStart = $baseDate->copy()->addDays(rand(10, 40));
                LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'start_date' => $sickStart,
                    'end_date' => $sickStart->copy()->addDays(2),
                    'days_requested' => 3,
                    'reason' => $hasAccount ? 'Medical leave due to flu.' : 'Fever and doctor-recommended rest.',
                    'status' => $hasAccount ? 'approved' : 'pending',
                    'submitted_by' => $employee->user_id ?? 1,
                    'submitted_at' => $sickStart->copy()->subDays(2),
                    'created_at' => $sickStart->copy()->subDays(2),
                    'updated_at' => $sickStart->copy()->subDays(2),
                ]);
            }

            // Story 3: Emergency Leave for family matters
            if ($faker->boolean(30)) {
                $policy = $policies['EL'] ?? $policies->first();
                $emergencyDate = $baseDate->copy()->addDays(rand(20, 60));
                LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'start_date' => $emergencyDate,
                    'end_date' => $emergencyDate,
                    'days_requested' => 1,
                    'reason' => $hasAccount ? 'Emergency: Family member hospitalized.' : 'Urgent family matter.',
                    'status' => $hasAccount ? 'approved' : 'pending',
                    'submitted_by' => $employee->user_id ?? 1,
                    'submitted_at' => $emergencyDate->copy()->subDay(),
                    'created_at' => $emergencyDate->copy()->subDay(),
                    'updated_at' => $emergencyDate->copy()->subDay(),
                ]);
            }

            // Story 4: Maternity/Paternity Leave (for eligible employees)
            if ($employee->gender === 'female' && $faker->boolean(10)) {
                $policy = $policies['ML'] ?? $policies->first();
                $matStart = $baseDate->copy()->addMonths(rand(1, 6));
                LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'start_date' => $matStart,
                    'end_date' => $matStart->copy()->addDays(89),
                    'days_requested' => 90,
                    'reason' => 'Maternity leave for childbirth.',
                    'status' => $hasAccount ? 'approved' : 'pending',
                    'submitted_by' => $employee->user_id ?? 1,
                    'submitted_at' => $matStart->copy()->subDay(),
                    'created_at' => $matStart->copy()->subDay(),
                    'updated_at' => $matStart->copy()->subDay(),
                    'department_id' => $employee->department_id,
                    'supervisor_id' => $employee->immediate_supervisor_id,
                ]);
            }
        }

        $this->command->info('Leave requests seeded with realistic stories for both account and non-account employees.');
    }
}
