<?php

namespace Database\Factories;

use App\Models\BulkUploadBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulkUploadBatchFactory extends Factory
{
    protected $model = BulkUploadBatch::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(10, 100);
        $success = $this->faker->numberBetween(0, $total);
        $error = $total - $success;
        
        $statuses = array_keys(BulkUploadBatch::STATUSES);
        $status = $this->faker->randomElement($statuses);

        return [
            'uploaded_by' => User::factory(),
            'total_count' => $total,
            'success_count' => $success,
            'error_count' => $error,
            'status' => $status,
            'csv_file_path' => 'bulk-uploads/' . $this->faker->uuid() . '.csv',
            'error_log' => $error > 0 ? [
                'row_1' => 'Missing employee_id',
                'row_2' => 'Invalid date format',
            ] : [],
            'notes' => $this->faker->optional()->sentence(),
            'started_at' => now()->subHours(random_int(1, 24)),
            'completed_at' => $status === 'processing' ? null : now(),
        ];
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'error_count' => 0,
                'success_count' => $attributes['total_count'],
                'error_log' => [],
                'completed_at' => now(),
            ];
        });
    }

    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'success_count' => 0,
                'completed_at' => now(),
            ];
        });
    }

    public function processing(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'processing',
                'completed_at' => null,
            ];
        });
    }

    public function partiallyCompleted(): self
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_count'];
            $success = (int)($total * 0.75);
            $error = $total - $success;
            
            return [
                'status' => 'partially_completed',
                'success_count' => $success,
                'error_count' => $error,
                'completed_at' => now(),
            ];
        });
    }
}
