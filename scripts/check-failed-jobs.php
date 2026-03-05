<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$jobs = DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get();
if ($jobs->isEmpty()) {
    echo "No failed jobs.\n";
} else {
    foreach ($jobs as $job) {
        echo "=== Failed Job ID: {$job->id} ===\n";
        echo "Queue: {$job->queue}\n";
        echo "Failed at: {$job->failed_at}\n";
        echo "Exception:\n{$job->exception}\n";
        echo "\n";
    }
}
