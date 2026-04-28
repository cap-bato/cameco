<?php
// scripts/fix-demo-report-paths.php
// This script updates file_path for all demo BIR GovernmentReport records

use Illuminate\Support\Facades\App;
use App\Models\GovernmentReport;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Bootstrap Laravel
$kernel->bootstrap();

$reports = GovernmentReport::where('agency', 'bir')->get();

$updated = 0;
foreach ($reports as $report) {
    $expectedPath = 'reports/demo/' . $report->file_name;
    if ($report->file_path !== $expectedPath) {
        $report->file_path = $expectedPath;
        $report->save();
        $updated++;
    }
}
echo "Updated $updated demo report file_path values.\n";
