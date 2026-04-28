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
    if ($report->file_path) {
        $absPath = __DIR__ . '/../storage/app/' . $report->file_path;
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!file_exists($absPath)) {
            file_put_contents($absPath, "This is a demo placeholder for {$report->file_name}\n");
            $created++;
        }
    }
}
echo "Created $created demo report files.\n";
