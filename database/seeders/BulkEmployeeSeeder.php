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
        $this->command->info('Starting bulk employee seeding...');
        
        // Get all departments and positions
        $departments = Department::with('positions')->get();
        
        if ($departments->isEmpty()) {
            $this->command->error('No departments found. Please run DepartmentSeeder and PositionSeeder first.');
            return;
        }

        $this->command->info('Found ' . $departments->count() . ' departments');

        // Create employees in batches for better performance
        $batchSize = 50;
        $totalEmployees = 31;  // Changed from 200 to 31 to align with EmployeeSeeder
        $createdCount = 0;

        $this->command->info("Creating {$totalEmployees} employees in batches of {$batchSize}...");

        DB::beginTransaction();
        
        try {
            for ($i = 0; $i < $totalEmployees; $i += $batchSize) {
                $remaining = min($batchSize, $totalEmployees - $i);
                
                $this->command->info("Creating batch of {$remaining} employees...");
                
                // Create employees with active status (most common)
                $activeCount = (int)($remaining * 0.85); // 85% active
                Employee::factory($activeCount)->active()->create();
                $createdCount += $activeCount;
                
                // Create employees with other statuses
                $otherCount = $remaining - $activeCount;
                if ($otherCount > 0) {
                    Employee::factory($otherCount)->create();
                    $createdCount += $otherCount;
                }
                
                $this->command->info("Progress: {$createdCount}/{$totalEmployees} employees created");
            }

            // Assign supervisors to some employees
            $this->command->info('Assigning supervisors...');
            $this->assignSupervisors();

            DB::commit();
            
            $this->command->info('✅ Successfully created ' . $createdCount . ' employees!');
            
            // Display statistics
            $this->displayStatistics();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error creating employees: ' . $e->getMessage());
            throw $e;
        }
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
                ['Total Employees', Employee::count()],
                ['Active', Employee::where('status', 'active')->count()],
                ['On Leave', Employee::where('status', 'on_leave')->count()],
                ['Suspended', Employee::where('status', 'suspended')->count()],
                ['Terminated', Employee::where('status', 'terminated')->count()],
                ['Archived', Employee::where('status', 'archived')->count()],
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
