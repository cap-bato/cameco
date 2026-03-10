<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * WorkScheduleFactory
 * 
 * Generates test data for work schedules
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'effective_date' => now()->startOfMonth(),
            'expires_at' => null,
            'status' => 'active',
            'monday_start' => '08:00:00',
            'monday_end' => '17:00:00',
            'tuesday_start' => '08:00:00',
            'tuesday_end' => '17:00:00',
            'wednesday_start' => '08:00:00',
            'wednesday_end' => '17:00:00',
            'thursday_start' => '08:00:00',
            'thursday_end' => '17:00:00',
            'friday_start' => '08:00:00',
            'friday_end' => '17:00:00',
            'saturday_start' => null,
            'saturday_end' => null,
            'sunday_start' => null,
            'sunday_end' => null,
            'lunch_break_duration' => 60,
            'morning_break_duration' => 15,
            'afternoon_break_duration' => 15,
            'overtime_threshold' => 0,
            'overtime_rate_multiplier' => 1.25,
            'department_id' => null,
            'is_template' => false,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create an active schedule
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'expires_at' => null,
            ];
        });
    }

    /**
     * Create an expired schedule
     */
    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
                'expires_at' => now()->subDay(),
            ];
        });
    }

    /**
     * Create a template schedule
     */
    public function template(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_template' => true,
            ];
        });
    }

    /**
     * Set for specific department
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(function (array $attributes) use ($department) {
            return [
                'department_id' => $department->id,
            ];
        });
    }

    /**
     * Set custom schedule times
     */
    public function withTimes(string $start, string $end): static
    {
        return $this->state(function (array $attributes) use ($start, $end) {
            return [
                'monday_start' => $start,
                'monday_end' => $end,
                'tuesday_start' => $start,
                'tuesday_end' => $end,
                'wednesday_start' => $start,
                'wednesday_end' => $end,
                'thursday_start' => $start,
                'thursday_end' => $end,
                'friday_start' => $start,
                'friday_end' => $end,
            ];
        });
    }
}
