<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test Carbon->date() method
$c = \Carbon\Carbon::parse('2026-02-02 08:00:00');
echo "method_exists date(): " . (method_exists($c, 'date') ? 'YES' : 'NO') . "\n";

try {
    $result = $c->date();
    echo "->date() result type: " . get_class($result) . "\n";
    echo "->date() value: " . $result . "\n";
} catch (\Throwable $e) {
    echo "->date() THROWS: " . $e->getMessage() . "\n";
}

echo "->toDateString(): " . $c->toDateString() . "\n";


echo "Per-date sample (first 4 dates):\n";
$dates = ['2026-02-02', '2026-02-03', '2026-02-04', '2026-02-05'];
foreach ($dates as $d) {
    $ledger = App\Models\RfidLedger::whereDate('scan_timestamp', $d)->count();
    $ae     = App\Models\AttendanceEvent::whereDate('event_date', $d)->whereNotNull('ledger_sequence_id')->count();
    echo "  $d: ledger=$ledger  att_events=$ae\n";
}
