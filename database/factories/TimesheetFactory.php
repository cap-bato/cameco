<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');
        $clockIn = (clone $date)->setTime(8, 0);
        $clockOut = (clone $date)->setTime(17, 0);

        return [
            'employee_id' => Employee::factory(),
            'date' => $date->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'regular_hours' => 8.0,
            'overtime_hours' => 0.0,
            'status' => 'present',
            'notes' => null,
        ];
    }

    public function late(): static
    {
        return $this->state(function (array $attributes) {
            $date = $attributes['date'];
            $clockIn = \Carbon\Carbon::parse($date)->setTime(9, 30);
            $clockOut = (clone $clockIn)->addHours(8);

            return [
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'status' => 'late',
            ];
        });
    }

    public function absent(): static
    {
        return $this->state([
            'clock_in' => null,
            'clock_out' => null,
            'regular_hours' => 0.0,
            'status' => 'absent',
        ]);
    }
}