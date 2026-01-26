<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['draft', 'pending_approval', 'approved', 'archived']);
        $types = array_keys(DocumentTemplate::TYPES);
        
        return [
            'name' => $this->faker->word() . ' Template',
            'description' => $this->faker->sentence(),
            'file_path' => null,
            'variables' => [
                'employee_name' => '{{employee_name}}',
                'employee_number' => '{{employee_number}}',
                'position' => '{{position}}',
                'department' => '{{department}}',
                'start_date' => '{{start_date}}',
            ],
            'template_type' => $this->faker->randomElement($types),
            'created_by' => User::factory(),
            'approved_by' => $status === 'approved' ? User::factory() : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'version' => 1,
            'is_locked' => $status === 'approved',
            'is_active' => $status === 'approved',
            'status' => $status,
        ];
    }

    public function approved(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'approved',
                'is_locked' => true,
                'is_active' => true,
                'approved_by' => User::factory(),
                'approved_at' => now(),
            ];
        });
    }

    public function draft(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'is_locked' => false,
                'is_active' => false,
            ];
        });
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending_approval',
                'is_locked' => false,
                'is_active' => false,
            ];
        });
    }

    public function archived(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'archived',
                'is_locked' => true,
                'is_active' => false,
            ];
        });
    }
}
