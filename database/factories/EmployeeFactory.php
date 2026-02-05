<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateHired = $this->faker->dateTimeBetween('-10 years', '-1 month');
        $employmentType = $this->faker->randomElement(['Regular', 'Probationary', 'Contractual', 'Project-Based', 'Part-Time']);
        
        // Calculate regularization date (6 months after hire date for Probationary)
        $regularizationDate = null;
        if ($employmentType === 'Regular') {
            $regularizationDate = (clone $dateHired)->modify('+6 months');
        } elseif ($employmentType === 'Probationary') {
            // Some probationary might not be regularized yet
            if ($dateHired < new \DateTime('-6 months')) {
                $regularizationDate = $this->faker->optional(0.7)->passthrough(
                    (clone $dateHired)->modify('+6 months')
                );
            }
        }

        // Get random department and position
        $department = Department::inRandomOrder()->first();
        $position = Position::where('department_id', $department?->id)
            ->inRandomOrder()
            ->first();
        
        // If no position found for department, get any position
        if (!$position) {
            $position = Position::inRandomOrder()->first();
        }

        // Generate unique employee number
        $year = $dateHired->format('Y');
        $randomNumber = $this->faker->unique()->numberBetween(1000, 9999);
        $employeeNumber = "EMP-{$year}-{$randomNumber}";

        return [
            'profile_id' => Profile::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'employee_number' => $employeeNumber,
            'department_id' => $department?->id,
            'position_id' => $position?->id,
            'employment_type' => $employmentType,
            'date_hired' => $dateHired->format('Y-m-d'),
            'regularization_date' => $regularizationDate?->format('Y-m-d'),
            'immediate_supervisor_id' => null, // Will be set separately if needed
            'status' => $this->faker->randomElement([
                'active', 'active', 'active', 'active', 'active', 'active', 'active', 'active', 'active', // 85% active
                'on_leave', // 5% on leave
                'suspended', // 2% suspended
                'terminated', // 5% terminated
                'archived', // 3% archived
            ]),
            'termination_date' => function (array $attributes) {
                if (in_array($attributes['status'], ['terminated', 'archived'])) {
                    return $this->faker->dateTimeBetween($attributes['date_hired'], 'now')->format('Y-m-d');
                }
                return null;
            },
            'termination_reason' => function (array $attributes) {
                if (in_array($attributes['status'], ['terminated', 'archived'])) {
                    return $this->faker->optional(0.8)->randomElement([
                        'Resignation',
                        'End of Contract',
                        'Performance Issues',
                        'Redundancy',
                        'Personal Reasons',
                        'Retirement',
                        'Company Restructuring'
                    ]);
                }
                return null;
            },
        ];
    }

    /**
     * Indicate that the employee is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'termination_date' => null,
            'termination_reason' => null,
        ]);
    }

    /**
     * Indicate that the employee is a regular employee.
     */
    public function regular(): static
    {
        return $this->state(function (array $attributes) {
            $dateHired = new \DateTime($attributes['date_hired']);
            $regularizationDate = (clone $dateHired)->modify('+6 months');
            
            return [
                'employment_type' => 'Regular',
                'regularization_date' => $regularizationDate->format('Y-m-d'),
            ];
        });
    }

    /**
     * Indicate that the employee is in a specific department.
     */
    public function inDepartment(int $departmentId): static
    {
        return $this->state(function (array $attributes) use ($departmentId) {
            $position = Position::where('department_id', $departmentId)
                ->inRandomOrder()
                ->first();
            
            return [
                'department_id' => $departmentId,
                'position_id' => $position?->id,
            ];
        });
    }

    /**
     * Indicate that the employee has a supervisor.
     */
    public function withSupervisor(int $supervisorId): static
    {
        return $this->state(fn (array $attributes) => [
            'immediate_supervisor_id' => $supervisorId,
        ]);
    }
}
