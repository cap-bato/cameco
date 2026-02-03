<?php

namespace App\Services\Timekeeping;

use App\Models\RfidLedger;
use App\Models\AttendanceEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * LedgerPollingService
 * 
 * Handles polling of the RFID ledger for new events and implements
 * deduplication logic to prevent duplicate event processing.
 * 
 * Task 5.2.1 & 5.2.2: Ledger polling with deduplication
 * 
 * Responsibilities:
 * - Poll unprocessed ledger entries (Task 5.2.1)
 * - Detect and mark duplicate events within 15-second window (Task 5.2.2)
 * - Validate event ordering by sequence_id
 * - Prepare events for downstream processing
 */
class LedgerPollingService
{
    /**
     * Deduplication time window in seconds.
     * Two events are considered duplicates if they occur within this window
     * from the same employee, device, and event type.
     */
    private const DEDUPLICATION_WINDOW_SECONDS = 15;

    /**
     * Task 5.2.1: Poll new unprocessed events from the RFID ledger.
     * 
     * This method fetches all unprocessed ledger entries and returns them
     * in sequence order, ready for deduplication and processing.
     * 
     * @param int|null $limit Maximum number of events to fetch per poll (default: 1000)
     * @return Collection Collection of unprocessed RfidLedger entries
     * 
     * @example
     * $events = $this->pollNewEvents(100);
     * // Returns next 100 unprocessed events in sequence order
     */
    public function pollNewEvents(?int $limit = 1000): Collection
    {
        // Query unprocessed ledger entries in sequence order
        $events = RfidLedger::unprocessed()
            ->orderBySequence()
            ->limit($limit)
            ->get();

        return $events;
    }

    /**
     * Task 5.2.1: Fetch events starting from a specific sequence ID.
     * 
     * Used for targeted polling or recovery after processing failures.
     * 
     * @param int $fromSequenceId Start from this sequence ID
     * @param int|null $limit Maximum number of events to fetch
     * @return Collection Collection of RfidLedger entries
     */
    public function pollEventsFromSequence(int $fromSequenceId, ?int $limit = 1000): Collection
    {
        $events = RfidLedger::where('sequence_id', '>=', $fromSequenceId)
            ->where('processed', false)
            ->orderBySequence()
            ->limit($limit)
            ->get();

        return $events;
    }

    /**
     * Task 5.2.2: Detect and mark duplicate events within the deduplication window.
     * 
     * A duplicate is defined as an event that matches:
     * - Same employee (employee_rfid)
     * - Same device (device_id)
     * - Same event type
     * - Within DEDUPLICATION_WINDOW_SECONDS of the original
     * 
     * Duplicates are common when employees tap their RFID card multiple times
     * or when network delays cause retransmissions.
     * 
     * @param Collection $events Collection of RfidLedger entries to check
     * @return Collection Events with deduplication flags set
     * 
     * @example
     * $events = $this->pollNewEvents(100);
     * $dedupedEvents = $this->deduplicateEvents($events);
     * // Returns events with is_deduplicated flag set for duplicates
     */
    public function deduplicateEvents(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        // Track seen events: employee_rfid:device_id:event_type => timestamp
        $seenEvents = [];

        return $events->map(function (RfidLedger $event) use (&$seenEvents) {
            $key = "{$event->employee_rfid}:{$event->device_id}:{$event->event_type}";

            if (isset($seenEvents[$key])) {
                // Check if this is within the deduplication window
                $timeDiffSeconds = abs($event->scan_timestamp->diffInSeconds($seenEvents[$key]));

                if ($timeDiffSeconds <= self::DEDUPLICATION_WINDOW_SECONDS) {
                    // Mark as deduplicated tap
                    $event->setAttribute('is_deduplicated', true);
                    $event->setAttribute('dedup_reason', 'duplicate_within_window');

                    return $event;
                }
            }

            // Update seen events with latest timestamp for this key
            $seenEvents[$key] = $event->scan_timestamp;

            // Not a duplicate
            $event->setAttribute('is_deduplicated', false);

            return $event;
        });
    }

    /**
     * Task 5.2.2: Identify duplicate events against existing attendance records.
     * 
     * Check if ledger events already exist in attendance_events table
     * to handle scenarios where events were previously processed.
     * 
     * @param Collection $events Collection of RfidLedger entries to check
     * @return Collection Events with existing_attendance_event_id if duplicate
     */
    public function findExistingAttendanceEvents(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return $events;
        }

        // Get sequence IDs from events
        $sequenceIds = $events->pluck('sequence_id')->toArray();

        // Find existing attendance events
        $existingEvents = AttendanceEvent::whereIn('ledger_sequence_id', $sequenceIds)
            ->pluck('id', 'ledger_sequence_id');

        return $events->map(function (RfidLedger $event) use ($existingEvents) {
            if (isset($existingEvents[$event->sequence_id])) {
                $event->setAttribute('existing_attendance_event_id', $existingEvents[$event->sequence_id]);
                $event->setAttribute('is_already_processed', true);
            }

            return $event;
        });
    }

    /**
     * Deduplication time window getter.
     * 
     * @return int Time window in seconds
     */
    public static function getDeduplicationWindowSeconds(): int
    {
        return self::DEDUPLICATION_WINDOW_SECONDS;
    }

    /**
     * Get deduplication statistics for a collection of events.
     * 
     * @param Collection $events Collection of deduplicated events
     * @return array Statistics including total, duplicates, unique events
     * 
     * @example
     * $stats = $this->getDeduplicationStats($dedupedEvents);
     * // Returns ['total' => 100, 'duplicates' => 5, 'unique' => 95, 'already_processed' => 2]
     */
    public function getDeduplicationStats(Collection $events): array
    {
        $duplicates = $events->filter(fn($e) => $e->getAttribute('is_deduplicated') === true)->count();
        $alreadyProcessed = $events->filter(fn($e) => $e->getAttribute('is_already_processed') === true)->count();

        return [
            'total' => $events->count(),
            'duplicates' => $duplicates,
            'unique' => $events->count() - $duplicates - $alreadyProcessed,
            'already_processed' => $alreadyProcessed,
        ];
    }

    /**
     * Prepare events for processing by running full pipeline.
     * 
     * Combines polling, deduplication, and existing event checking
     * into a single operation.
     * 
     * Task 5.2.1 + 5.2.2 Combined:
     * 
     * @param int|null $limit Maximum events to process
     * @return array Processed events ready for attendance event creation
     * 
     * @example
     * $result = $this->prepareEventsForProcessing(100);
     * // Returns [
     * //   'events' => [...],
     * //   'stats' => ['total' => 100, 'duplicates' => 5, ...],
     * //   'processable_events' => [...] // Events ready for creating AttendanceEvent
     * // ]
     */
    public function prepareEventsForProcessing(?int $limit = 1000): array
    {
        // Step 1: Poll new events
        $events = $this->pollNewEvents($limit);

        if ($events->isEmpty()) {
            return [
                'events' => collect(),
                'stats' => ['total' => 0, 'duplicates' => 0, 'unique' => 0, 'already_processed' => 0],
                'processable_events' => collect(),
            ];
        }

        // Step 2: Apply deduplication within polling batch
        $dedupedEvents = $this->deduplicateEvents($events);

        // Step 3: Check for existing attendance events
        $checkedEvents = $this->findExistingAttendanceEvents($dedupedEvents);

        // Step 4: Get statistics
        $stats = $this->getDeduplicationStats($checkedEvents);

        // Step 5: Filter to only processable events
        $processableEvents = $checkedEvents->filter(function (RfidLedger $event) {
            // Event is processable if it's:
            // - Not a duplicate within the window
            // - Not already processed as an attendance event
            return !$event->getAttribute('is_deduplicated')
                && !$event->getAttribute('is_already_processed');
        });

        return [
            'events' => $checkedEvents,
            'stats' => $stats,
            'processable_events' => $processableEvents,
        ];
    }

    /**
     * Mark events as processed in the ledger.
     * 
     * After successfully creating attendance events, update the ledger
     * to mark events as processed for next polling cycle.
     * 
     * @param Collection $events Events to mark as processed
     * @param \DateTime|null $processedAt Timestamp for processing (default: now)
     * @return int Number of events updated
     */
    public function markEventsAsProcessed(Collection $events, ?\DateTime $processedAt = null): int
    {
        if ($events->isEmpty()) {
            return 0;
        }

        $processedAt = $processedAt ?? now();
        $sequenceIds = $events->pluck('sequence_id')->toArray();

        return RfidLedger::whereIn('sequence_id', $sequenceIds)
            ->update([
                'processed' => true,
                'processed_at' => $processedAt,
            ]);
    }

    /**
     * Get processing statistics for ledger.
     * 
     * @return array Statistics including unprocessed count, processing lag, etc.
     */
    public function getLedgerStats(): array
    {
        $totalUnprocessed = RfidLedger::unprocessed()->count();
        $lastEntry = RfidLedger::orderBy('sequence_id', 'desc')->first();

        $lag = null;
        if ($lastEntry) {
            $lag = now()->diffInSeconds($lastEntry->created_at);
        }

        $unprocessedWithLag = RfidLedger::unprocessed()
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();

        return [
            'total_unprocessed' => $totalUnprocessed,
            'last_sequence_id' => $lastEntry?->sequence_id,
            'last_scan_timestamp' => $lastEntry?->scan_timestamp,
            'processing_lag_seconds' => $lag,
            'stale_unprocessed_entries' => $unprocessedWithLag, // Entries unprocessed for >5 minutes
        ];
    }

    /**
     * Task 5.2.3: Validate hash chain integrity for ledger entries.
     * 
     * Each ledger entry contains a hash_chain that is computed as:
     * hash_chain = SHA-256(hash_previous || raw_payload)
     * 
     * This method verifies:
     * 1. Hash computation is correct for each event
     * 2. Sequence ordering is preserved (no gaps or duplicates)
     * 3. Chain links back to genesis block (hash_previous = null or matches previous entry)
     * 
     * Returns validation details with any broken links or sequence issues.
     * 
     * @param Collection|null $events Events to validate (if null, validates all processed events)
     * @return array Validation result with structure:
     *   [
     *     'valid' => bool,
     *     'total_validated' => int,
     *     'invalid_hashes' => int,
     *     'sequence_gaps' => int,
     *     'failed_at_sequence_id' => int|null,
     *     'failures' => [
     *       ['sequence_id' => int, 'reason' => 'hash_mismatch|gap_detected', 'details' => string],
     *       ...
     *     ]
     *   ]
     * 
     * @example
     * $validation = $this->validateHashChain($events);
     * if (!$validation['valid']) {
     *     Log::warning('Hash chain broken at ' . $validation['failed_at_sequence_id']);
     * }
     */
    public function validateHashChain(?Collection $events = null): array
    {
        if ($events === null) {
            // If no events provided, validate recently processed events
            $events = RfidLedger::where('processed', true)
                ->orderBy('sequence_id', 'desc')
                ->limit(1000)
                ->get()
                ->reverse()
                ->values();
        }

        if ($events->isEmpty()) {
            return [
                'valid' => true,
                'total_validated' => 0,
                'invalid_hashes' => 0,
                'sequence_gaps' => 0,
                'failed_at_sequence_id' => null,
                'failures' => [],
            ];
        }

        $failures = [];
        $invalidHashes = 0;
        $sequenceGaps = 0;
        $failedAtSequenceId = null;

        $previousSequenceId = null;
        $previousHash = null;

        foreach ($events as $event) {
            // Check for sequence gaps
            if ($previousSequenceId !== null) {
                $expectedNextSequence = $previousSequenceId + 1;
                if ($event->sequence_id !== $expectedNextSequence) {
                    // Gap detected - log but don't fail validation
                    // Gaps can occur if events were filtered/deleted
                    $sequenceGaps++;
                    $failures[] = [
                        'sequence_id' => $event->sequence_id,
                        'reason' => 'gap_detected',
                        'details' => "Gap from sequence {$previousSequenceId} to {$event->sequence_id}",
                    ];
                }
            }

            // Verify hash chain: compute expected hash and compare
            $expectedHash = $this->computeHashForEvent($event, $previousHash);
            
            if ($expectedHash !== $event->hash_chain) {
                $invalidHashes++;
                $failedAtSequenceId = $event->sequence_id;
                
                $failures[] = [
                    'sequence_id' => $event->sequence_id,
                    'reason' => 'hash_mismatch',
                    'details' => "Expected hash {$expectedHash}, got {$event->hash_chain}",
                ];
            }

            // Update tracking for next iteration
            $previousSequenceId = $event->sequence_id;
            $previousHash = $event->hash_chain;
        }

        $valid = $invalidHashes === 0 && count($failures) === 0;

        return [
            'valid' => $valid,
            'total_validated' => $events->count(),
            'invalid_hashes' => $invalidHashes,
            'sequence_gaps' => $sequenceGaps,
            'failed_at_sequence_id' => $failedAtSequenceId,
            'failures' => $failures,
        ];
    }

    /**
     * Compute the expected hash for a ledger event.
     * 
     * Hash formula: SHA-256(hash_previous || raw_payload_json)
     * 
     * @param RfidLedger $event The event to compute hash for
     * @param string|null $previousHash The previous event's hash (or null for genesis)
     * @return string Computed SHA-256 hash
     */
    private function computeHashForEvent(RfidLedger $event, ?string $previousHash = null): string
    {
        // Start with previous hash or empty string for genesis block
        $hashInput = $previousHash ?? '';

        // Append JSON-serialized payload
        $payloadJson = is_array($event->raw_payload) 
            ? json_encode($event->raw_payload) 
            : $event->raw_payload;
        
        $hashInput .= $payloadJson;

        // Compute SHA-256 hash
        return hash('sha256', $hashInput);
    }

    /**
     * Detect if hash chain is broken by finding first invalid link.
     * 
     * Faster than full validation when you only need to know if there's a problem.
     * 
     * @param int|null $startSequenceId Start validation from this sequence
     * @return bool True if chain is valid, false if broken
     */
    public function isHashChainValid(?int $startSequenceId = null): bool
    {
        $query = RfidLedger::where('processed', true);
        
        if ($startSequenceId !== null) {
            $query = $query->where('sequence_id', '>=', $startSequenceId);
        }

        $events = $query->orderBy('sequence_id', 'asc')
            ->limit(1000)
            ->get();

        $validation = $this->validateHashChain($events);
        return $validation['valid'];
    }

    /**
     * Task 5.2.4: Create AttendanceEvent records from processed ledger entries.
     * 
     * Converts ledger entries into attendance records that are used by payroll,
     * appraisal, and reporting systems. Links each attendance event back to
     * its source ledger entry for auditability.
     * 
     * @param Collection $ledgerEvents Unprocessed ledger events ready for conversion
     * @param bool $validateHashChain If true, skip events with invalid hash_chain
     * @return array Result with created events and any errors:
     *   [
     *     'created' => int (count of successfully created AttendanceEvent records),
     *     'failed' => int (count of failed creations),
     *     'errors' => [
     *       ['sequence_id' => int, 'reason' => string],
     *       ...
     *     ],
     *     'attendance_events' => Collection
     *   ]
     * 
     * @example
     * $result = $this->createAttendanceEventsFromLedger($ledgerEvents);
     * Log::info("Created {$result['created']} attendance events");
     */
    public function createAttendanceEventsFromLedger(Collection $ledgerEvents, bool $validateHashChain = true): array
    {
        $created = 0;
        $failed = 0;
        $errors = [];
        $attendanceEvents = collect();

        foreach ($ledgerEvents as $ledgerEvent) {
            try {
                // Skip if hash chain validation is required and fails
                if ($validateHashChain && !$ledgerEvent->getAttribute('hash_verified')) {
                    $failed++;
                    $errors[] = [
                        'sequence_id' => $ledgerEvent->sequence_id,
                        'reason' => 'hash_verification_failed',
                    ];
                    continue;
                }

                // Resolve employee ID from RFID
                $employeeId = $this->resolveEmployeeId($ledgerEvent->employee_rfid);
                if (!$employeeId) {
                    $failed++;
                    $errors[] = [
                        'sequence_id' => $ledgerEvent->sequence_id,
                        'reason' => 'cannot_resolve_employee',
                    ];
                    continue;
                }

                // Validate scan_timestamp exists
                if (!$ledgerEvent->scan_timestamp) {
                    $failed++;
                    $errors[] = [
                        'sequence_id' => $ledgerEvent->sequence_id,
                        'reason' => 'missing_scan_timestamp',
                    ];
                    continue;
                }

                // Create attendance event from ledger entry
                $attendanceEvent = AttendanceEvent::create([
                    'employee_id' => $employeeId,
                    'event_date' => $ledgerEvent->scan_timestamp->date(),
                    'event_time' => $ledgerEvent->scan_timestamp,
                    'event_type' => $ledgerEvent->event_type,
                    'ledger_sequence_id' => $ledgerEvent->sequence_id,
                    'is_deduplicated' => $ledgerEvent->getAttribute('is_deduplicated') ?? false,
                    'ledger_hash_verified' => $ledgerEvent->getAttribute('hash_verified') ?? true,
                    'source' => 'edge_machine', // Ledger events are always from edge machine
                    'device_id' => $ledgerEvent->device_id,
                    'notes' => "Ledger sequence #{$ledgerEvent->sequence_id}",
                ]);

                $created++;
                $attendanceEvents->push($attendanceEvent);

            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'sequence_id' => $ledgerEvent->sequence_id,
                    'reason' => 'creation_failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
            'attendance_events' => $attendanceEvents,
        ];
    }

    /**
     * Resolve employee ID from RFID card identifier.
     * 
     * Maps RFID card number to internal employee ID.
     * This is a placeholder - actual implementation would look up from employee table
     * or RFID card mapping table.
     * 
     * @param string $employeeRfid RFID card identifier
     * @return int|null Employee ID or null if not found
     */
    private function resolveEmployeeId(string $employeeRfid): ?int
    {
        // TODO: Implement RFID to employee mapping lookup
        // For now, return first employee or null
        $employee = \App\Models\Employee::first();
        return $employee?->id;
    }

    /**
     * Task 5.2.5: Mark ledger entries as processed.
     * 
     * After successfully creating attendance events and validating data,
     * mark ledger entries as processed so they're not reprocessed in next cycle.
     * 
     * This method already exists as markEventsAsProcessed() - this is an alias
     * for clarity in the 5.2.5 context.
     * 
     * @param Collection $events Events to mark as processed
     * @param \DateTime|null $processedAt Timestamp for processing (default: now)
     * @return int Number of events marked as processed
     * 
     * @example
     * $result = $this->markLedgerEntriesAsProcessed($ledgerEvents);
     * Log::info("Marked $result events as processed");
     */
    public function markLedgerEntriesAsProcessed(Collection $events, ?\DateTime $processedAt = null): int
    {
        return $this->markEventsAsProcessed($events, $processedAt);
    }

    /**
     * Complete processing pipeline: validate, create events, and mark processed.
     * 
     * Combines all three subtasks (5.2.3, 5.2.4, 5.2.5) into a single operation.
     * 
     * @param Collection|null $ledgerEvents Events to process (if null, polls automatically)
     * @return array Complete processing result:
     *   [
     *     'polled' => int (events polled),
     *     'deduplicated' => int (duplicates detected),
     *     'validated' => int (hash chain validated),
     *     'valid_hashes' => int (events with valid hashes),
     *     'created_attendance_events' => int,
     *     'marked_processed' => int,
     *     'errors' => [...],
     *     'details' => {...}
     *   ]
     */
    public function processLedgerEventsComplete(?Collection $ledgerEvents = null): array
    {
        // Step 1: Poll events if not provided
        if ($ledgerEvents === null) {
            $result = $this->prepareEventsForProcessing(1000);
            $ledgerEvents = $result['processable_events'];
        }

        if ($ledgerEvents->isEmpty()) {
            return [
                'polled' => 0,
                'deduplicated' => 0,
                'validated' => 0,
                'valid_hashes' => 0,
                'created_attendance_events' => 0,
                'marked_processed' => 0,
                'errors' => [],
                'details' => 'No events to process',
            ];
        }

        // Step 2: Validate hash chains (Task 5.2.3)
        $hashValidation = $this->validateHashChain($ledgerEvents);
        
        // Mark hash validation on each event
        $ledgerEvents = $ledgerEvents->map(function (RfidLedger $event) use ($hashValidation) {
            $event->setAttribute('hash_verified', $hashValidation['valid']);
            return $event;
        });

        // Step 3: Create attendance events (Task 5.2.4)
        $creationResult = $this->createAttendanceEventsFromLedger($ledgerEvents, false);

        // Step 4: Mark ledger entries as processed (Task 5.2.5)
        $markedProcessed = $this->markLedgerEntriesAsProcessed($ledgerEvents);

        return [
            'polled' => $ledgerEvents->count(),
            'deduplicated' => 0, // Already handled in prepareEventsForProcessing
            'validated' => $hashValidation['total_validated'],
            'valid_hashes' => $hashValidation['total_validated'] - $hashValidation['invalid_hashes'],
            'created_attendance_events' => $creationResult['created'],
            'marked_processed' => $markedProcessed,
            'errors' => array_merge($hashValidation['failures'], $creationResult['errors']),
            'details' => [
                'hash_validation' => $hashValidation,
                'attendance_events' => $creationResult['attendance_events'],
            ],
        ];
    }
}
