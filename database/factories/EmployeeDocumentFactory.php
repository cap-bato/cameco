<?php

namespace Database\Factories;

use App\Models\BulkUploadBatch;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'approved', 'rejected', 'auto_approved']);
        $categories = array_keys(EmployeeDocument::CATEGORIES);
        
        return [
            'employee_id' => Employee::factory(),
            'document_category' => $this->faker->randomElement($categories),
            'document_type' => $this->faker->word(),
            'file_name' => $this->faker->word() . '.pdf',
            'file_path' => 'storage/documents/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(1000, 5000000),
            'mime_type' => 'application/pdf',
            'uploaded_by' => User::factory(),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
            'status' => $status,
            'approved_by' => $status !== 'pending' ? User::factory() : null,
            'approved_at' => $status !== 'pending' ? now() : null,
            'rejection_reason' => $status === 'rejected' ? $this->faker->sentence() : null,
            'requires_approval' => $this->faker->boolean(),
            'is_critical' => $this->faker->boolean(),
            'notes' => $this->faker->optional()->sentence(),
            'reminder_sent_at' => null,
            'bulk_upload_batch_id' => null,
            'source' => $this->faker->randomElement(array_keys(EmployeeDocument::SOURCES)),
        ];
    }

    public function approved(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'approved',
                'approved_by' => User::factory(),
                'approved_at' => now(),
            ];
        });
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ];
        });
    }

    public function rejected(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'rejection_reason' => $this->faker->sentence(),
                'approved_by' => User::factory(),
                'approved_at' => now(),
            ];
        });
    }

    public function expired(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subDay(),
                'status' => 'approved',
                'approved_by' => User::factory(),
                'approved_at' => now(),
            ];
        });
    }
}
