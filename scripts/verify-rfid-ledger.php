<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== RFID Ledger → Attendance Events: Verification ===\n\n";

// rfid_ledger stats
$totalLedger     = App\Models\RfidLedger::count();
$processedLedger = App\Models\RfidLedger::where('processed', true)->count();
$unprocessed     = App\Models\RfidLedger::where('processed', false)->count();

echo "rfid_ledger:\n";
echo "  Total rows:      $totalLedger\n";
echo "  Processed:       $processedLedger\n";
echo "  Unprocessed:     $unprocessed\n\n";

// attendance_events stats
$totalAE      = App\Models\AttendanceEvent::whereBetween('event_date', ['2026-02-02', '2026-03-06'])->count();
$edgeMachine  = App\Models\AttendanceEvent::whereBetween('event_date', ['2026-02-02', '2026-03-06'])->where('source', 'edge_machine')->count();
$withLedgerId = App\Models\AttendanceEvent::whereBetween('event_date', ['2026-02-02', '2026-03-06'])->whereNotNull('ledger_sequence_id')->count();
$deduplicated = App\Models\AttendanceEvent::whereBetween('event_date', ['2026-02-02', '2026-03-06'])->where('is_deduplicated', true)->count();

echo "attendance_events (Feb 2 - Mar 6):\n";
echo "  Total:                     $totalAE\n";
echo "  source=edge_machine:       $edgeMachine\n";
echo "  With ledger_sequence_id:   $withLedgerId\n";
echo "  Deduplicated (dup taps):   $deduplicated\n\n";

// Employee distribution check
$distinctEmployees = App\Models\AttendanceEvent::whereBetween('event_date', ['2026-02-02', '2026-03-06'])
    ->whereNotNull('ledger_sequence_id')
    ->distinct('employee_id')
    ->count('employee_id');

echo "Distinct employees with ledger-backed events: $distinctEmployees\n\n";

// Sample per-day check
echo "Per-date sample (first 4 dates):\n";
$dates = ['2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05'];
foreach ($dates as $d) {
    $ledger = App\Models\RfidLedger::whereDate('scan_timestamp', $d)->count();
    $ae     = App\Models\AttendanceEvent::whereDate('event_date', $d)->whereNotNull('ledger_sequence_id')->count();
    echo "  $d: ledger=$ledger  att_events=$ae\n";
}
