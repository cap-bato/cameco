<?php

namespace Database\Factories;

use App\Models\RfidLedger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * RfidLedger Factory
 * 
 * Generates test data for RFID ledger entries
 */
class RfidLedgerFactory extends Factory
{
    protected $model = RfidLedger::class;

    public function definition(): array
    {
        $now = Carbon::now();
        
        return [
            'sequence_id' => fake()->unique()->numberBetween(1000, 999999),
            'employee_rfid' => 'RFID' . fake()->numerify('###'),
            'device_id' => 'DEVICE' . fake()->numerify('##'),
            'scan_timestamp' => $now->copy()->subMinutes(fake()->numberBetween(1, 60)),
            'event_type' => fake()->randomElement(['time_in', 'time_out', 'break_start', 'break_end']),
            'raw_payload' => [
                'device_version' => '1.0',
                'signal_strength' => fake()->numberBetween(-90, -40),
                'card_serial' => fake()->uuid(),
            ],
            'hash_chain' => fake()->sha256(),
            'hash_previous' => fake()->sha256(),
            'device_signature' => fake()->sha256(),
            'latency_ms' => fake()->numberBetween(50, 300), // Realistic latency: 50-300ms
            'processed' => false,
            'processed_at' => null,
            'created_at' => $now,
        ];
    }

    /**
     * Mark ledger entry as processed
     */
    public function processed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processed' => true,
                'processed_at' => now(),
            ];
        });
    }

    /**
     * Mark ledger entry as unprocessed
     */
    public function unprocessed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'processed' => false,
                'processed_at' => null,
            ];
        });
    }

    /**
     * Set specific event type
     */
    public function withEventType(string $eventType): static
    {
        return $this->state(function (array $attributes) use ($eventType) {
            return [
                'event_type' => $eventType,
            ];
        });
    }

    /**
     * Set specific employee RFID
     */
    public function forEmployee(string $rfid): static
    {
        return $this->state(function (array $attributes) use ($rfid) {
            return [
                'employee_rfid' => $rfid,
            ];
        });
    }

    /**
     * Set specific device
     */
    public function forDevice(string $deviceId): static
    {
        return $this->state(function (array $attributes) use ($deviceId) {
            return [
                'device_id' => $deviceId,
            ];
        });
    }

    /**
     * Set specific timestamp
     */
    public function atTime(Carbon $timestamp): static
    {
        return $this->state(function (array $attributes) use ($timestamp) {
            return [
                'scan_timestamp' => $timestamp,
            ];
        });
    }
}
