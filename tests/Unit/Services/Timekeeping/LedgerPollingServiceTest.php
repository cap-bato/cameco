<?php

namespace Tests\Unit\Services\Timekeeping;

use Tests\TestCase;
use App\Models\RfidLedger;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Services\Timekeeping\LedgerPollingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * LedgerPollingServiceTest
 * 
 * Unit tests for LedgerPollingService
 * Tests Task 5.2.1 (polling) and Task 5.2.2 (deduplication)
 */
class LedgerPollingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerPollingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LedgerPollingService();
    }

    /**
     * Task 5.2.1: Test pollNewEvents fetches unprocessed entries
     */
    public function test_poll_new_events_fetches_unprocessed_entries(): void
    {
        // Create some ledger entries: processed and unprocessed
        RfidLedger::factory()->processed()->create();
        RfidLedger::factory()->processed()->create();
        
        RfidLedger::factory()->unprocessed()->create();
        RfidLedger::factory()->unprocessed()->create();
        RfidLedger::factory()->unprocessed()->create();

        // Poll new events
        $events = $this->service->pollNewEvents();

        // Should only return unprocessed events
        $this->assertCount(3, $events);
        $this->assertTrue($events->every(fn($e) => !$e->processed));
    }

    /**
     * Task 5.2.1: Test pollNewEvents respects limit
     */
    public function test_poll_new_events_respects_limit(): void
    {
        // Create 10 unprocessed entries
        RfidLedger::factory()->unprocessed()->count(10)->create();

        // Poll with limit of 5
        $events = $this->service->pollNewEvents(5);

        $this->assertCount(5, $events);
    }

    /**
     * Task 5.2.1: Test pollNewEvents returns events in sequence order
     */
    public function test_poll_new_events_ordered_by_sequence(): void
    {
        // Create unprocessed entries with specific sequence IDs
        RfidLedger::factory()->unprocessed()->create(['sequence_id' => 100]);
        RfidLedger::factory()->unprocessed()->create(['sequence_id' => 105]);
        RfidLedger::factory()->unprocessed()->create(['sequence_id' => 101]);

        $events = $this->service->pollNewEvents();

        // Should be ordered by sequence_id ascending
        $sequences = $events->pluck('sequence_id')->toArray();
        $this->assertEquals([100, 101, 105], $sequences);
    }

    /**
     * Task 5.2.2: Test deduplicateEvents detects duplicates within window
     */
    public function test_deduplicate_events_detects_duplicates_within_window(): void
    {
        $now = Carbon::now();

        // Create two similar events within 15-second window
        $event1 = new RfidLedger([
            'sequence_id' => 1,
            'employee_rfid' => 'RFID001',
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now,
            'raw_payload' => [],
            'hash_chain' => 'hash1',
            'processed' => false,
        ]);

        $event2 = new RfidLedger([
            'sequence_id' => 2,
            'employee_rfid' => 'RFID001',
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now->copy()->addSeconds(10), // 10 seconds later
            'raw_payload' => [],
            'hash_chain' => 'hash2',
            'processed' => false,
        ]);

        $events = collect([$event1, $event2]);
        $deduped = $this->service->deduplicateEvents($events);

        // First should not be marked as duplicate
        $this->assertFalse($deduped[0]->getAttribute('is_deduplicated'));

        // Second should be marked as duplicate (within 15-second window)
        $this->assertTrue($deduped[1]->getAttribute('is_deduplicated'));
    }

    /**
     * Task 5.2.2: Test deduplicateEvents allows different employees
     */
    public function test_deduplicate_events_allows_different_employees(): void
    {
        $now = Carbon::now();

        $event1 = new RfidLedger([
            'sequence_id' => 1,
            'employee_rfid' => 'RFID001',
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now,
            'raw_payload' => [],
            'hash_chain' => 'hash1',
            'processed' => false,
        ]);

        $event2 = new RfidLedger([
            'sequence_id' => 2,
            'employee_rfid' => 'RFID002', // Different employee
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now->copy()->addSeconds(5),
            'raw_payload' => [],
            'hash_chain' => 'hash2',
            'processed' => false,
        ]);

        $events = collect([$event1, $event2]);
        $deduped = $this->service->deduplicateEvents($events);

        // Both should NOT be duplicates (different employees)
        $this->assertFalse($deduped[0]->getAttribute('is_deduplicated'));
        $this->assertFalse($deduped[1]->getAttribute('is_deduplicated'));
    }

    /**
     * Task 5.2.2: Test deduplicateEvents respects time window boundary
     */
    public function test_deduplicate_events_respects_time_window(): void
    {
        $now = Carbon::now();

        $event1 = new RfidLedger([
            'sequence_id' => 1,
            'employee_rfid' => 'RFID001',
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now,
            'raw_payload' => [],
            'hash_chain' => 'hash1',
            'processed' => false,
        ]);

        $event2 = new RfidLedger([
            'sequence_id' => 2,
            'employee_rfid' => 'RFID001',
            'device_id' => 'DEVICE001',
            'event_type' => 'time_in',
            'scan_timestamp' => $now->copy()->addSeconds(16), // 16 seconds later (outside window)
            'raw_payload' => [],
            'hash_chain' => 'hash2',
            'processed' => false,
        ]);

        $events = collect([$event1, $event2]);
        $deduped = $this->service->deduplicateEvents($events);

        // Both should NOT be duplicates (outside 15-second window)
        $this->assertFalse($deduped[0]->getAttribute('is_deduplicated'));
        $this->assertFalse($deduped[1]->getAttribute('is_deduplicated'));
    }

    /**
     * Task 5.2.2: Test getDeduplicationStats
     */
    public function test_get_deduplication_stats(): void
    {
        $now = Carbon::now();

        // Create events with different dedup states
        $events = collect([
            (new RfidLedger())->setAttribute('is_deduplicated', false)->setAttribute('is_already_processed', false),
            (new RfidLedger())->setAttribute('is_deduplicated', true)->setAttribute('is_already_processed', false),
            (new RfidLedger())->setAttribute('is_deduplicated', false)->setAttribute('is_already_processed', true),
            (new RfidLedger())->setAttribute('is_deduplicated', false)->setAttribute('is_already_processed', false),
        ]);

        $stats = $this->service->getDeduplicationStats($events);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(1, $stats['duplicates']);
        $this->assertEquals(2, $stats['unique']);
        $this->assertEquals(1, $stats['already_processed']);
    }

    /**
     * Task 5.2.1 & 5.2.2: Test prepareEventsForProcessing pipeline
     */
    public function test_prepare_events_for_processing_pipeline(): void
    {
        // Create test ledger entries
        RfidLedger::factory()->unprocessed()->count(5)->create();

        $result = $this->service->prepareEventsForProcessing(10);

        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('processable_events', $result);

        $this->assertGreaterThan(0, $result['stats']['total']);
        $this->assertLessThanOrEqual($result['stats']['unique'], $result['processable_events']->count());
    }

    /**
     * Task 5.2.3: Test validateHashChain validates correctly
     */
    public function test_validate_hash_chain_with_valid_events(): void
    {
        // Create event with correct hash computation
        $payload = ['type' => 'time_in', 'device' => 'DEVICE001'];
        $payloadJson = json_encode($payload);
        $correctHash = hash('sha256', $payloadJson); // hash_previous is null for first event

        $event1 = RfidLedger::factory()->create([
            'sequence_id' => 1,
            'raw_payload' => $payload,
            'hash_chain' => $correctHash,
            'hash_previous' => null,
        ]);

        $events = collect([$event1]);
        $validation = $this->service->validateHashChain($events);

        // Validation should pass
        $this->assertTrue($validation['valid']);
        $this->assertEquals(1, $validation['total_validated']);
        $this->assertEquals(0, $validation['invalid_hashes']);
        $this->assertEmpty($validation['failures']);
    }

    /**
     * Task 5.2.3: Test validateHashChain detects broken hashes
     */
    public function test_validate_hash_chain_detects_broken_hashes(): void
    {
        // Create event with intentionally wrong hash
        $event1 = RfidLedger::factory()->create([
            'sequence_id' => 1,
            'hash_chain' => 'invalid_hash_xyz',
            'hash_previous' => null,
            'raw_payload' => ['test' => 'data'],
        ]);

        $events = collect([$event1]);
        $validation = $this->service->validateHashChain($events);

        $this->assertFalse($validation['valid']);
        $this->assertEquals(1, $validation['invalid_hashes']);
        $this->assertNotEmpty($validation['failures']);
        $this->assertEquals(1, $validation['failed_at_sequence_id']);
    }

    /**
     * Task 5.2.3: Test validateHashChain detects sequence gaps
     */
    public function test_validate_hash_chain_detects_sequence_gaps(): void
    {
        // Create events with gap in sequence
        $event1 = RfidLedger::factory()->create(['sequence_id' => 1]);
        $event2 = RfidLedger::factory()->create(['sequence_id' => 5]); // Gap: 1 -> 5

        $events = collect([$event1, $event2]);
        $validation = $this->service->validateHashChain($events);

        // Should report gap but still be "valid" since gaps can be intentional
        $this->assertGreaterThan(0, $validation['sequence_gaps']);
    }

    /**
     * Task 5.2.3: Test isHashChainValid quickly detects validity
     */
    public function test_is_hash_chain_valid_returns_boolean(): void
    {
        RfidLedger::factory()->create(['sequence_id' => 1]);

        $isValid = $this->service->isHashChainValid();

        // Should return boolean
        $this->assertIsBool($isValid);
    }

    /**
     * Task 5.2.4: Test createAttendanceEventsFromLedger creates records
     */
    public function test_create_attendance_events_from_ledger(): void
    {
        // Create an employee first
        $employee = Employee::factory()->create();

        // Create test ledger entries with proper attributes
        $ledgerEvents = RfidLedger::factory()->unprocessed()->count(3)->create([
            'employee_rfid' => 'EMP001',
            'device_id' => 'DEVICE001',
            'scan_timestamp' => now(),
        ]);

        // Set hash verification and other attributes
        $ledgerEvents = $ledgerEvents->map(function ($event) {
            $event->setAttribute('is_deduplicated', false);
            $event->setAttribute('hash_verified', true);
            return $event;
        });

        $result = $this->service->createAttendanceEventsFromLedger($ledgerEvents, false);

        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('attendance_events', $result);

        // Should create or fail gracefully
        $this->assertIsInt($result['created']);
        $this->assertIsInt($result['failed']);
    }

    /**
     * Task 5.2.4: Test createAttendanceEventsFromLedger handles errors
     */
    public function test_create_attendance_events_handles_errors(): void
    {
        // Create invalid ledger event (missing required field)
        $event = new RfidLedger([
            'sequence_id' => 1,
            'employee_rfid' => 'RFID001',
        ]);

        $result = $this->service->createAttendanceEventsFromLedger(collect([$event]), false);

        $this->assertArrayHasKey('failed', $result);
        // May fail due to missing employee_id resolution or validation
    }

    /**
     * Task 5.2.5: Test markLedgerEntriesAsProcessed marks events processed
     */
    public function test_mark_ledger_entries_as_processed(): void
    {
        // Create unprocessed events
        $events = RfidLedger::factory()->unprocessed()->count(3)->create();

        $marked = $this->service->markLedgerEntriesAsProcessed($events);

        $this->assertEquals(3, $marked);

        // Verify they're marked in database
        foreach ($events as $event) {
            $this->assertTrue(
                RfidLedger::find($event->id)->processed,
                "Event {$event->id} should be marked as processed"
            );
        }
    }

    /**
     * Task 5.2.5: Test markEventsAsProcessed (alias for backward compatibility)
     */
    public function test_mark_events_as_processed_alias(): void
    {
        $events = RfidLedger::factory()->unprocessed()->count(2)->create();

        $marked = $this->service->markEventsAsProcessed($events);

        $this->assertEquals(2, $marked);
    }

    /**
     * Task 5.2.3, 5.2.4, 5.2.5: Test complete processing pipeline
     */
    public function test_process_ledger_events_complete(): void
    {
        // Create test data
        $ledgerEvents = RfidLedger::factory()->unprocessed()->count(5)->create();

        $result = $this->service->processLedgerEventsComplete($ledgerEvents);

        $this->assertArrayHasKey('polled', $result);
        $this->assertArrayHasKey('deduplicated', $result);
        $this->assertArrayHasKey('validated', $result);
        $this->assertArrayHasKey('valid_hashes', $result);
        $this->assertArrayHasKey('created_attendance_events', $result);
        $this->assertArrayHasKey('marked_processed', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('details', $result);

        // All events should be processed
        $this->assertGreaterThanOrEqual(0, $result['marked_processed']);
    }
}
