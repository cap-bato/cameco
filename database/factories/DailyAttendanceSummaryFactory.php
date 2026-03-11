<?php

namespace Database\Factories;

use App\Models\DailyAttendanceSummary;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyAttendanceSummaryFactory extends Factory
{
    protected $model = DailyAttendanceSummary::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'attendance_date' => Carbon::now()->format('Y-m-d'),
            'time_in' => Carbon::now()->setTime(8, rand(0, 30)),
            'time_out' => Carbon::now()->setTime(17, rand(0, 30)),
            'total_hours_worked' => (float) rand(7, 9) + (rand(0, 59) / 100),
            'is_present' => true,
            'is_late' => false,
            'is_on_leave' => false,
            'late_minutes' => null,
            'leave_request_id' => null,
            'correction_applied' => false,
        ];
    }

    public function onLeave(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_on_leave' => true,
                'is_present' => false,
                'time_in' => null,
                'time_out' => null,
                'total_hours_worked' => 0,
                'leave_request_id' => 1, // Reference a valid leave request if needed
            ];
        });
    }

    public function late(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_late' => true,
                'late_minutes' => rand(5, 60),
            ];
        });
    }

    public function absent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_present' => false,
                'time_in' => null,
                'time_out' => null,
                'total_hours_worked' => 0,
            ];
        });
    }
}
