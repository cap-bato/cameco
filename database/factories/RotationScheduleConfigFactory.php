<?php

namespace Database\Factories;

use App\Models\RotationAssignment;
use App\Models\WorkSchedule;
use App\Models\RotationScheduleConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RotationScheduleConfig>
 */
class RotationScheduleConfigFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RotationScheduleConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $effectiveDate = Carbon::now()->startOfDay();
        $endDate = $effectiveDate->clone()->addMonths(3);

        return [
            'rotation_assignment_id' => RotationAssignment::factory(),
            'work_schedule_id' => WorkSchedule::factory(),
            'effective_date' => $effectiveDate,
            'end_date' => $endDate,
            'is_active' => true,
        ];
    }

    /**
     * State: Configuration without end date (indefinite)
     *
     * @return static
     */
    public function indefinite()
    {
        return $this->state(function (array $attributes) {
            return [
                'end_date' => null,
            ];
        });
    }

    /**
     * State: Inactive configuration
     *
     * @return static
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * State: Configuration starting today for 30 days
     *
     * @return static
     */
    public function thirtyDays()
    {
        return $this->state(function (array $attributes) {
            $start = Carbon::now()->startOfDay();
            return [
                'effective_date' => $start,
                'end_date' => $start->clone()->addDays(30),
            ];
        });
    }

    /**
     * State: Configuration starting today for 90 days
     *
     * @return static
     */
    public function ninetyDays()
    {
        return $this->state(function (array $attributes) {
            $start = Carbon::now()->startOfDay();
            return [
                'effective_date' => $start,
                'end_date' => $start->clone()->addDays(90),
            ];
        });
    }

    /**
     * State: Configuration with specific rotation assignment
     *
     * @param RotationAssignment $assignment
     * @return static
     */
    public function forAssignment(RotationAssignment $assignment)
    {
        return $this->state(function (array $attributes) use ($assignment) {
            return [
                'rotation_assignment_id' => $assignment->id,
            ];
        });
    }

    /**
     * State: Configuration with specific work schedule
     *
     * @param WorkSchedule $schedule
     * @return static
     */
    public function forSchedule(WorkSchedule $schedule)
    {
        return $this->state(function (array $attributes) use ($schedule) {
            return [
                'work_schedule_id' => $schedule->id,
            ];
        });
    }

    /**
     * State: Configuration starting on a specific date
     *
     * @param Carbon|string $startDate
     * @param int $durationDays
     * @return static
     */
    public function startingOn($startDate, $durationDays = 90)
    {
        return $this->state(function (array $attributes) use ($startDate, $durationDays) {
            if (is_string($startDate)) {
                $startDate = Carbon::parse($startDate);
            }

            return [
                'effective_date' => $startDate->startOfDay(),
                'end_date' => $startDate->clone()->addDays($durationDays),
            ];
        });
    }
}
