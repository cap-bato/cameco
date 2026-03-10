<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Timekeeping\LedgerPollingService;

$service = new LedgerPollingService();
$totalCreated = 0;
$pass = 0;

echo "Starting ledger processing...\n\n";

while (true) {
    $result = $service->processLedgerEventsComplete();
    
    if ($result['polled'] === 0) {
        echo "No more events to process.\n";
        break;
    }
    
    $pass++;
    $totalCreated += $result['created_attendance_events'];
    $errorCount = count($result['errors']);
    
    echo "Pass {$pass}: polled={$result['polled']}, created={$result['created_attendance_events']}, errors={$errorCount}\n";
    
    if ($pass > 20) {
        echo "Safety limit reached (20 passes)\n";
        break;
    }
}

echo "\n=== Processing Complete ===\n";
echo "Total passes: {$pass}\n";
echo "Total attendance_events created: {$totalCreated}\n";

$distinctEmployees = DB::table('attendance_events')
    ->whereNotNull('ledger_sequence_id')
    ->distinct('employee_id')
    ->count('employee_id');

echo "Distinct employees with events: {$distinctEmployees}\n";

$deduplicatedCount = DB::table('attendance_events')
    ->whereNotNull('ledger_sequence_id')
    ->where('is_deduplicated', true)
    ->count();

echo "Deduplicated events: {$deduplicatedCount}\n";
