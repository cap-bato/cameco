<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Reset any 'calculating' or 'cancelled' periods back to 'active' so they can be retried
$updated = DB::table('payroll_periods')
    ->whereIn('status', ['calculating', 'cancelled'])
    ->update(['status' => 'active']);

echo "Reset {$updated} period(s) back to 'active'.\n";

$periods = DB::table('payroll_periods')->select('id', 'period_name', 'status')->orderBy('id')->get();
foreach ($periods as $p) {
    echo "  [{$p->id}] {$p->period_name} → {$p->status}\n";
}
