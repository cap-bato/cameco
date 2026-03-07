<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\RfidCardMapping;
use App\Models\RfidLedger;
use App\Models\User;
use App\Services\Timekeeping\LedgerPollingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * RfidLedgerSeeder
 *
 * Seeds rfid_ledger with realistic tap data for Feb 2 → Mar 6 2026 (25 working days)
 * then drives the full LedgerPollingService pipeline to produce attendance_events.
 *
 * Pipeline tested:
 *   rfid_ledger (processed=false)
 *     → LedgerPollingService::processLedgerEventsComplete()
 *         → deduplication (15 s window)
 *         → SHA-256 hash-chain validation
 *         → AttendanceEvent rows (source='edge_machine', ledger_sequence_id set)
 *         → rfid_ledger.processed = true
 *
 * Profiles:
 *   ~5%  absent  (no ledger rows for that day)
 *   ~20% late    (time_in 10–45 min after 08:00)
 *   ~15% OT      (time_out 1–2 h after 17:00)
 *   ~5%  dup tap (extra time_in within 15 s → tests deduplication)
 *
 * Idempotent: skips employee-days where rfid_ledger rows already exist.
 *
 * Usage:
 *   php artisan db:seed --class=RfidLedgerSeeder
 */
class RfidLedgerSeeder extends Seeder
{
    private const DATE_FROM      = '2026-02-02';
    private const DATE_TO        = '2026-03-06';
    private const DEVICE_IDS     = ['GATE-01', 'GATE-02'];

    // Cumulative % thresholds (1–100 roll)
    private const PCT_ABSENT     = 5;
    private const PCT_LATE       = 25;   // 6–25  → late
    private const PCT_OVERTIME   = 85;   // 86–100 → OT; 26–85 → normal

    public function run(): void
    {
        $this->command->line('');
        $this->command->line('═══════════════════════════════════════════════════════');
        $this->command->line('  RfidLedgerSeeder');
        $this->command->line('  Range: ' . self::DATE_FROM . ' → ' . self::DATE_TO);
        $this->command->line('═══════════════════════════════════════════════════════');

        // ── 1. Load employees ────────────────────────────────────────────────
        $employees = Employee::where('status', 'active')->get();
        if ($employees->isEmpty()) {
            $this->command->error('No active employees. Run EmployeeSeeder first.');
            return;
        }

        // ── 2. Ensure rfid_card_mappings exist for every employee ────────────
        $this->command->info("Step 1  →  Ensuring RFID card mappings ({$employees->count()} employees)...");
        $issuer      = User::first();
        $cardMappings = $this->ensureCardMappings($employees, $issuer);

        if ($cardMappings->isEmpty()) {
            $this->command->error('Could not resolve card mappings. Aborting.');
            return;
        }

        // ── 3. Seed rfid_ledger rows ─────────────────────────────────────────
        $workDays = $this->getWorkingDays(self::DATE_FROM, self::DATE_TO);

        $this->command->line('');
        $this->command->info('Step 2  →  Seeding rfid_ledger rows...');
        $this->command->info(
            '           Employees : ' . $employees->count() .
            ' | Work days : ' . count($workDays) .
            ' (Mon–Fri, excl. weekends)'
        );

        // Continue hash chain from last existing ledger row
        $lastEntry = RfidLedger::orderBy('sequence_id', 'desc')->first();
        $nextSeqId = $lastEntry ? ($lastEntry->sequence_id + 1) : 1;
        $prevHash  = $lastEntry ? $lastEntry->hash_chain        : null;

        $bar = $this->command->getOutput()->createProgressBar(
            $employees->count() * count($workDays)
        );
        $bar->start();

        $batch         = [];
        $ledgerCreated = 0;
        $skippedDays   = 0;

        foreach ($workDays as $workDay) {
            foreach ($employees as $employee) {
                $bar->advance();

                $mapping = $cardMappings->get($employee->id);
                if (!$mapping) {
                    continue;
                }

                // Idempotency: skip if ledger rows already exist for this employee-day
                if (
                    RfidLedger::where('employee_rfid', $mapping->card_uid)
                        ->whereDate('scan_timestamp', $workDay)
                        ->exists()
                ) {
                    $skippedDays++;
                    continue;
                }

                [$rows, $nextSeqId, $prevHash] = $this->buildDayEvents(
                    $mapping->card_uid,
                    $workDay,
                    $nextSeqId,
                    $prevHash
                );

                foreach ($rows as $row) {
                    $batch[] = $row;
                    $ledgerCreated++;
                }

                // Flush in chunks to avoid huge memory usage
                if (count($batch) >= 300) {
                    RfidLedger::insert($batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            RfidLedger::insert($batch);
        }

        $bar->finish();
        $this->command->line('');
        $this->command->line(
            '         → Created ' . $ledgerCreated . ' ledger rows' .
            ' | Skipped ' . $skippedDays . ' employee-days (already had data)'
        );

        // ── 4. Process ledger → attendance_events ────────────────────────────
        $this->command->line('');
        $this->command->info('Step 3  →  Processing rfid_ledger → attendance_events...');
        $this->command->info(
            '           (LedgerPollingService::processLedgerEventsComplete — 1000 rows/batch)'
        );

        $service          = new LedgerPollingService();
        $totalPolled      = 0;
        $totalAttendance  = 0;
        $totalErrors      = [];
        $pass             = 0;

        do {
            $result = $service->processLedgerEventsComplete();

            $totalPolled     += $result['polled'];
            $totalAttendance += $result['created_attendance_events'];
            $totalErrors      = array_merge($totalErrors, $result['errors']);
            $pass++;

            if ($result['polled'] > 0) {
                $this->command->line(
                    "           Pass {$pass}: polled={$result['polled']}" .
                    " | att_created={$result['created_attendance_events']}" .
                    ' | errors=' . count($result['errors'])
                );
            }
        } while ($result['polled'] > 0);

        // ── 5. Summary ───────────────────────────────────────────────────────
        $this->command->line('');
        $this->command->line('════════════════════════════════════════');
        $this->command->line('✅  RfidLedgerSeeder complete');
        $this->command->line('');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Date range',               self::DATE_FROM . ' → ' . self::DATE_TO],
                ['Working days',             count($workDays)],
                ['Employees',                $employees->count()],
                ['rfid_ledger rows created', $ledgerCreated],
                ['Skipped (already exist)',  $skippedDays . ' employee-days'],
                ['attendance_events created', $totalAttendance],
                ['Processing errors',         count($totalErrors)],
            ]
        );

        if (!empty($totalErrors)) {
            $this->command->warn('First 5 errors:');
            foreach (array_slice($totalErrors, 0, 5) as $err) {
                $seqId  = $err['sequence_id'] ?? '?';
                $reason = $err['reason']      ?? '?';
                $this->command->warn("  seq#{$seqId}: {$reason}");
            }
        }

        $this->command->line('');
        $this->command->info(
            'Next: php artisan timekeeping:generate-daily-summaries' .
            ' --from=' . self::DATE_FROM . ' --to=' . self::DATE_TO . ' --force'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ensure every active employee has at least one active rfid_card_mapping.
     * Creates a deterministic card_uid (CARD-{id:04d}) where one is missing.
     *
     * @return \Illuminate\Support\Collection  keyed by employee_id → RfidCardMapping
     */
    private function ensureCardMappings(
        \Illuminate\Support\Collection $employees,
        ?User $issuer
    ): \Illuminate\Support\Collection {
        $result  = collect();
        $created = 0;

        foreach ($employees as $employee) {
            $mapping = RfidCardMapping::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if (!$mapping) {
                $cardUid = sprintf('CARD-%04d', $employee->id);

                // Guard: card_uid is unique — skip if another employee already owns it
                $existing = RfidCardMapping::where('card_uid', $cardUid)->first();
                if ($existing) {
                    $cardUid = sprintf('CARD-%04d-ALT', $employee->id);
                }

                $mapping = RfidCardMapping::create([
                    'card_uid'    => $cardUid,
                    'employee_id' => $employee->id,
                    'card_type'   => 'standard',
                    'issued_at'   => Carbon::parse('2025-01-01'),
                    'issued_by'   => $issuer?->id,
                    'is_active'   => true,
                    'usage_count' => 0,
                ]);
                $created++;
            }

            $result->put($employee->id, $mapping);
        }

        if ($created > 0) {
            $this->command->info("           Created {$created} new card mapping(s).");
        } else {
            $this->command->info('           All card mappings already exist.');
        }

        return $result;
    }

    /**
     * Build all rfid_ledger rows for one employee on one working day.
     *
     * Returns: [ $rows[], $nextSeqId, $prevHash ]
     *
     * Hash formula:  hash_chain = SHA-256( (prev_hash ?? '') . json_encode(raw_payload) )
     * This matches LedgerPollingService::computeHashForEvent() exactly.
     */
    private function buildDayEvents(
        string  $cardUid,
        string  $date,
        int     $nextSeqId,
        ?string $prevHash
    ): array {
        $roll = mt_rand(1, 100);

        // Absent — no events at all
        if ($roll <= self::PCT_ABSENT) {
            return [[], $nextSeqId, $prevHash];
        }

        $deviceId    = self::DEVICE_IDS[array_rand(self::DEVICE_IDS)];
        $isLate      = ($roll <= self::PCT_LATE);
        $isOT        = ($roll > self::PCT_OVERTIME);
        $lateMinutes = $isLate ? mt_rand(10, 45) : mt_rand(0, 5);
        $otHours     = $isOT   ? mt_rand(1, 2)   : 0;

        $timeIn     = Carbon::parse("{$date} 08:00:00")->addMinutes($lateMinutes);
        $breakStart = Carbon::parse("{$date} 12:00:00");
        $breakEnd   = Carbon::parse("{$date} 13:00:00");
        $timeOut    = Carbon::parse("{$date} 17:00:00")->addHours($otHours);

        // 4 canonical events per day
        $events = [
            ['time_in',     $timeIn],
            ['break_start', $breakStart],
            ['break_end',   $breakEnd],
            ['time_out',    $timeOut],
        ];

        $rows = [];

        foreach ($events as [$eventType, $scanTime]) {
            [$row, $nextSeqId, $prevHash] = $this->buildRow(
                $cardUid, $deviceId, $eventType, $scanTime, $nextSeqId, $prevHash
            );
            $rows[] = $row;
        }

        // 5% chance: add a duplicate time_in tap within the 15-second dedup window
        // This exercises LedgerPollingService::deduplicateEvents()
        if (mt_rand(1, 100) <= 5) {
            $dupTime = $timeIn->copy()->addSeconds(mt_rand(1, 14));
            [$row, $nextSeqId, $prevHash] = $this->buildRow(
                $cardUid, $deviceId, 'time_in', $dupTime, $nextSeqId, $prevHash,
                ['duplicate' => true]
            );
            $rows[] = $row;
        }

        return [$rows, $nextSeqId, $prevHash];
    }

    /**
     * Build a single rfid_ledger row and advance the hash chain.
     */
    private function buildRow(
        string  $cardUid,
        string  $deviceId,
        string  $eventType,
        Carbon  $scanTime,
        int     $seqId,
        ?string $prevHash,
        array   $extraPayload = []
    ): array {
        $payload = array_merge([
            'employee_rfid' => $cardUid,
            'device_id'     => $deviceId,
            'event_type'    => $eventType,
            'timestamp'     => $scanTime->toIso8601String(),
        ], $extraPayload);

        $payloadJson = json_encode($payload);
        $hashInput   = ($prevHash ?? '') . $payloadJson;
        $hashChain   = hash('sha256', $hashInput);

        $row = [
            'sequence_id'      => $seqId,
            'employee_rfid'    => $cardUid,
            'device_id'        => $deviceId,
            'scan_timestamp'   => $scanTime->toDateTimeString(),
            'event_type'       => $eventType,
            'raw_payload'      => $payloadJson,
            'hash_chain'       => $hashChain,
            'hash_previous'    => $prevHash,
            'device_signature' => null,
            'latency_ms'       => mt_rand(5, 150),
            'processed'        => false,
            'processed_at'     => null,
            'created_at'       => now()->toDateTimeString(),
        ];

        return [$row, $seqId + 1, $hashChain];
    }

    /**
     * Return all Mon–Fri dates (Y-m-d strings) within the inclusive range.
     */
    private function getWorkingDays(string $from, string $to): array
    {
        $days   = [];
        $cursor = Carbon::parse($from);
        $end    = Carbon::parse($to);

        while ($cursor->lte($end)) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $days;
    }
}
