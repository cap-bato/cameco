<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Models\LeavePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 month');
        $end = (clone $start)->modify('+'. $this->faker->numberBetween(1,5) .' days');
        $employee = Employee::factory()->create();
        $policy = LeavePolicy::inRandomOrder()->first() ?? LeavePolicy::factory()->create();

        // Randomly assign variant to Sick Leave requests
        $variant = $policy->code === 'SL' 
            ? $this->faker->randomElement([null, 'half_am', 'half_pm'])
            : null;

        return [
            'employee_id' => $employee->id,
            'leave_policy_id' => $policy->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days_requested' => $this->faker->randomFloat(1, 1, 5),
            'leave_type_variant' => $variant,
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
            'supervisor_id' => $employee->immediate_supervisor_id,
            'submitted_at' => now(),
            'submitted_by' => 1,
        ];
    }
}
