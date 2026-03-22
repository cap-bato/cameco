<?php
// scripts/generate-demo-report-files.php
// This script creates placeholder files for all demo GovernmentReport records (agency = 'bir')

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use App\Models\GovernmentReport;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Bootstrap Laravel
$kernel->bootstrap();

$reports = GovernmentReport::where('agency', 'bir')->get();

$created = 0;
foreach ($reports as $report) {
    if ($report->file_path && !Storage::exists($report->file_path)) {
        // Ensure directory exists
        $dir = dirname($report->file_path);
        if (!Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }
        // Create a placeholder file with a simple message
        Storage::put($report->file_path, "This is a demo placeholder for {$report->file_name}\n");
        $created++;
    }
}
echo "Created $created demo report files.\n";
