<?php

namespace Database\Factories;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * AttendanceEventFactory
 * 
 * Generates test data for attendance events
 */
class AttendanceEventFactory extends Factory
{
    protected $model = AttendanceEvent::class;

    public function definition(): array
    {
        $eventDate = Carbon::now()->subDays(fake()->numberBetween(0, 30));
        
        return [
            'employee_id' => Employee::factory(),
            'event_date' => $eventDate,
            'event_time' => $eventDate->copy()->setTimeFromTimeString(fake()->time()),
            'event_type' => fake()->randomElement(['time_in', 'time_out', 'break_start', 'break_end', 'overtime_start', 'overtime_end']),
            'ledger_sequence_id' => fake()->unique()->numberBetween(1000, 999999),
            'is_deduplicated' => false,
            'ledger_hash_verified' => true,
            'source' => fake()->randomElement(['edge_machine', 'manual', 'imported']),
            'imported_batch_id' => null,
            'is_corrected' => false,
            'original_time' => null,
            'correction_reason' => null,
            'corrected_by' => null,
            'corrected_at' => null,
            'device_id' => 'DEVICE' . fake()->numerify('##'),
            'location' => fake()->randomElement(['Gate 1', 'Gate 2', 'Reception', 'Office']),
            'notes' => null,
            'ledger_raw_payload' => [
                'device_version' => '1.0',
                'signal_strength' => fake()->numberBetween(-90, -40),
            ],
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create event from edge machine (RFID scanner)
     */
    public function fromEdgeMachine(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'source' => 'edge_machine',
                'imported_batch_id' => null,
            ];
        });
    }

    /**
     * Create manually entered event
     */
    public function manual(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'source' => 'manual',
                'created_by' => User::factory(),
                'imported_batch_id' => null,
            ];
        });
    }

    /**
     * Create imported event
     */
    public function imported(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'source' => 'imported',
                'imported_batch_id' => ImportBatch::factory(),
            ];
        });
    }

    /**
     * Mark as deduplicated
     */
    public function deduplicated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_deduplicated' => true,
            ];
        });
    }

    /**
     * Mark as corrected
     */
    public function corrected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_corrected' => true,
                'original_time' => now()->subHours(2),
                'correction_reason' => 'Manual correction',
                'corrected_by' => User::factory(),
                'corrected_at' => now(),
            ];
        });
    }

    /**
     * For specific employee
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(function (array $attributes) use ($employee) {
            return [
                'employee_id' => $employee->id,
            ];
        });
    }

    /**
     * For specific date
     */
    public function onDate(Carbon $date): static
    {
        return $this->state(function (array $attributes) use ($date) {
            return [
                'event_date' => $date,
                'event_time' => $date->copy()->setTimeFromTimeString(fake()->time()),
            ];
        });
    }

    /**
     * For specific event type
     */
    public function withEventType(string $type): static
    {
        return $this->state(function (array $attributes) use ($type) {
            return [
                'event_type' => $type,
            ];
        });
    }

    /**
     * Set ledger sequence ID
     */
    public function withLedgerSequence(int $sequenceId): static
    {
        return $this->state(function (array $attributes) use ($sequenceId) {
            return [
                'ledger_sequence_id' => $sequenceId,
            ];
        });
    }
}
