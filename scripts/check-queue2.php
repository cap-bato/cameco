<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Pending Jobs ===\n";
$jobs = DB::table('jobs')->select('id', 'queue', 'payload', 'available_at')->orderBy('available_at')->get();
foreach ($jobs as $j) {
    $p = json_decode($j->payload);
    echo "  queue={$j->queue} job=" . class_basename($p->displayName) . " avail=" . date('H:i:s', $j->available_at) . "\n";
}

echo "\n=== Failed Jobs summary ===\n";
$failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->get();
echo "  Total failed: " . $failed->count() . "\n";
$byType = $failed->groupBy(function($j){ return class_basename(json_decode($j->payload)->displayName); });
foreach ($byType as $type => $items) { echo "  $type: " . $items->count() . "\n"; }

echo "\n=== Latest Employee job error ===\n";
$e = DB::table('failed_jobs')->where('payload','like','%CalculateEmployeePayrollJob%')->orderBy('failed_at','desc')->first();
if ($e) { echo substr($e->exception, 0, 600) . "\n"; }

echo "\n=== Payroll Periods ===\n";
$periods = DB::table('payroll_periods')->select('id','period_name','status','total_employees')->get();
foreach ($periods as $p) { echo "  id={$p->id} [{$p->status}] {$p->period_name} emp={$p->total_employees}\n"; }

echo "\n=== EPC count: " . DB::table('employee_payroll_calculations')->count() . "\n";
