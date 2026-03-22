<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkEmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates bulk employees to populate the system with test data.
     * Change $totalEmployees constant to adjust the number of employees created.
     * Default is 31 to match manual EmployeeSeeder + variations.
     */
    public function run(): void
    {
        $this->command->info('Seeding 17 generic employees to reach 142 total...');
        $createdCount = 0;
        $totalEmployees = 17;
        $departments = Department::all();
        $positions = Position::all();
        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $totalEmployees; $i++) {
                $dept = $departments->random();
                $pos = $positions->random();
                $profile = \App\Models\Profile::create([
                    'first_name' => 'Generic',
                    'middle_name' => 'Employee',
                    'last_name' => (string)$i,
                    'date_of_birth' => '1990-01-01',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'current_address' => 'Generic Address',
                    'permanent_address' => 'Generic Address',
                    'email' => "generic{$i}@cameco.com",
                ]);
                Employee::create([
                    'employee_number' => sprintf('GEN-%04d', $i),
                    'profile_id' => $profile->id,
                    'department_id' => $dept->id,
                    'position_id' => $pos->id,
                    'employment_type' => 'regular',
                    'date_hired' => '2020-01-01',
                    'status' => 'active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
                $createdCount++;
            }
            $this->command->info('Assigning supervisors...');
            $this->assignSupervisors();
            DB::commit();
            $this->command->info('✅ Successfully created ' . $createdCount . ' generic employees!');
            $this->displayStatistics();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error creating generic employees: ' . $e->getMessage());
            throw $e;
        }

        // Assign photos to male employees if available
        $sourceDir = base_path('employee-photos-mock');
        $employees = Employee::with('profile')->orderBy('id')->get();
        $photoFiles = collect(glob($sourceDir . '/*.jpg'))->map(fn($f) => basename($f))->toArray();
        $this->command->info("Assigning photos to male employees...");
        $i = 0;
        foreach ($employees as $employee) {
            $profile = $employee->profile;
            if (!$profile || strtolower($profile->gender) !== 'male') continue;
            $photoFile = $photoFiles[$i % count($photoFiles)] ?? null;
            if ($photoFile) {
                $sourcePath = $sourceDir . '/' . $photoFile;
                $destPath = "employees/{$employee->employee_number}/profile.jpg";
                if (file_exists($sourcePath)) {
                    \Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
                    $profile->update(['profile_picture_path' => $destPath]);
                }
            }
            $i++;
        }
        $this->command->info("Photo assignment complete.");
    }

    /**
     * Assign supervisors to employees based on department hierarchy
     */
    private function assignSupervisors(): void
    {
        $departments = Department::with(['employees' => function ($query) {
            $query->where('status', 'active');
        }])->get();

        foreach ($departments as $department) {
            $employees = $department->employees;
            
            if ($employees->count() < 2) {
                continue;
            }

            // Find potential supervisors (managers and supervisors)
            $supervisors = $employees->filter(function ($employee) {
                $position = Position::find($employee->position_id);
                return $position && in_array($position->level, ['manager', 'supervisor']);
            });

            if ($supervisors->isEmpty()) {
                // If no managers/supervisors, pick the most senior employee (earliest hire date)
                $supervisors = collect([$employees->sortBy('date_hired')->first()]);
            }

            // Assign supervisor to other employees (about 70% of employees have supervisors)
            $employeesNeedingSupervisor = $employees->diff($supervisors);
            
            foreach ($employeesNeedingSupervisor as $employee) {
                if (rand(1, 100) <= 70) { // 70% chance of having a supervisor
                    $supervisor = $supervisors->random();
                    $employee->update(['immediate_supervisor_id' => $supervisor->id]);
                }
            }
        }
    }

    /**
     * Display statistics about created employees
     */
    private function displayStatistics(): void
    {
        $this->command->newLine();
        $this->command->info('📊 Employee Statistics:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Total Employees', Employee::withTrashed()->count()],
                ['Active', Employee::where('status', 'active')->count()],
                ['On Leave', Employee::where('status', 'on_leave')->count()],
                ['Suspended', Employee::where('status', 'suspended')->count()],
                ['Terminated', Employee::where('status', 'terminated')->count()],
                ['Archived', Employee::withTrashed()->where('status', 'archived')->count()],
                ['With Supervisors', Employee::whereNotNull('immediate_supervisor_id')->count()],
                ['Regular Employees', Employee::where('employment_type', 'Regular')->count()],
                ['Probationary', Employee::where('employment_type', 'Probationary')->count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('👥 Employees by Department:');
        
        $departmentStats = Department::withCount('employees')
            ->has('employees', '>', 0)
            ->get()
            ->map(fn($dept) => [$dept->name, $dept->employees_count])
            ->toArray();
            
        if (!empty($departmentStats)) {
            $this->command->table(['Department', 'Employees'], $departmentStats);
        }
    }
}
