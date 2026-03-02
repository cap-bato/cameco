<?php

namespace Database\Factories;

use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    protected $model = OvertimeRequest::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestDate = $this->faker->dateTimeBetween('-30 days', '+30 days');
        $startTime = Carbon::parse($requestDate)->setTime(17, 0, 0);
        $plannedHours = $this->faker->randomElement([2, 3, 4, 5, 6]);
        $endTime = $startTime->copy()->addHours($plannedHours);
        
        return [
            'employee_id' => Employee::factory(),
            'request_date' => $requestDate,
            'planned_start_time' => $startTime,
            'planned_end_time' => $endTime,
            'planned_hours' => $plannedHours,
            'reason' => $this->faker->randomElement([
                'Production rush order - urgent shipment',
                'Equipment maintenance during off-hours',
                'Project deadline approaching',
                'Staff shortage coverage',
                'Inventory reconciliation',
                'Emergency repair work',
                'Month-end processing',
                'Quality inspection backlog',
            ]),
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }
    
    /**
     * State: Approved overtime request
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays($this->faker->numberBetween(1, 5)),
        ]);
    }
    
    /**
     * State: Rejected overtime request
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays($this->faker->numberBetween(1, 5)),
            'rejection_reason' => $this->faker->randomElement([
                'Budget constraints',
                'Insufficient justification',
                'Not approved by department head',
                'Already exceeded monthly overtime limit',
            ]),
        ]);
    }
    
    /**
     * State: Completed overtime request
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $actualHours = $attributes['planned_hours'] + $this->faker->randomFloat(2, -0.5, 1);
            $actualStart = Carbon::parse($attributes['planned_start_time']);
            $actualEnd = $actualStart->copy()->addHours($actualHours);
            
            return [
                'status' => 'completed',
                'approved_by' => User::factory(),
                'approved_at' => now()->subDays($this->faker->numberBetween(5, 10)),
                'actual_start_time' => $actualStart,
                'actual_end_time' => $actualEnd,
                'actual_hours' => round($actualHours, 2),
            ];
        });
    }
    
    /**
     * State: For a specific employee
     */
    public function forEmployee(int $employeeId): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }
    
    /**
     * State: For today
     */
    public function today(): static
    {
        return $this->state(function (array $attributes) {
            $startTime = today()->setTime(17, 0, 0);
            $plannedHours = $attributes['planned_hours'];
            $endTime = $startTime->copy()->addHours($plannedHours);
            
            return [
                'request_date' => today(),
                'planned_start_time' => $startTime,
                'planned_end_time' => $endTime,
            ];
        });
    }
}
