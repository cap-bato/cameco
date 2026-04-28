<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OffboardingCase;
use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Services\HR\OffboardingService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OffboardingSeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $departments = [
            Department::firstOrCreate(['id' => 1], ['name' => 'HR']),
            Department::firstOrCreate(['id' => 2], ['name' => 'IT']),
            Department::firstOrCreate(['id' => 3], ['name' => 'Finance']),
        ];

        // Users & Employees
        $users = [];
        $employees = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[$i] = User::updateOrCreate(
                [ 'username' => 'user'.$i ],
                [
                    'email' => "user{$i}@example.com",
                    'name' => "User {$i}",
                    'password' => bcrypt('password'),
                ]
            );
            $employees[$i] = Employee::firstOrCreate(
                ['employee_number' => 'EMP-10'.str_pad($i, 2, '0', STR_PAD_LEFT)],
                [
                    'profile_id' => $i,
                    'department_id' => $departments[$i % 3]->id,
                    'user_id' => $users[$i]->id,
                    'status' => 'active',
                    'position_id' => 1,
                    'employment_type' => 'regular',
                    'date_hired' => now()->subYears(rand(1,5)),
                    'created_by' => $users[$i]->id,
                ]
            );
        }

        $service = app(OffboardingService::class);
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled', 'pending', 'in_progress', 'overdue'];
        $separationTypes = ['resignation', 'termination', 'retirement', 'end_of_contract'];
        $now = Carbon::now();

        // Diverse offboarding cases for analytics
        $caseCount = 0;
        foreach ($employees as $idx => $employee) {
            for ($j = 0; $j < 3; $j++) {
                $status = $statuses[($idx + $j) % count($statuses)];
                $sepType = $separationTypes[($idx + $j) % count($separationTypes)];
                $daysOffset = ($j - 1) * 30 + ($idx * 3); // some past, some future
                $lastWorkingDay = $now->copy()->addDays($daysOffset - 10);
                $createdAt = $now->copy()->addDays($daysOffset - 40);
                $completedAt = in_array($status, ['completed']) ? $lastWorkingDay->copy()->addDays(1) : null;

                $case = OffboardingCase::create([
                    'employee_id' => $employee->id,
                    'initiated_by' => $users[$idx]->id,
                    'case_number' => $service->generateCaseNumber(),
                    'separation_type' => $sepType,
                    'separation_reason' => ucfirst($sepType).' reason',
                    'last_working_day' => $lastWorkingDay,
                    'notice_period_days' => 30,
                    'status' => $status === 'overdue' ? 'pending' : $status,
                    'resignation_submitted_at' => $createdAt->copy()->subDays(2),
                    'hr_coordinator_id' => $users[1]->id,
                    'completed_at' => $completedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $service->createDefaultClearanceItems($case);
                $service->createDefaultAccessRevocations($case);
                $caseCount++;
            }
        }
        $this->command->info("Seeded {$caseCount} offboarding cases with diverse statuses, types, and dates for analytics.");
    }
}
