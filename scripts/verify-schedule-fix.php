<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Full-range verification
$total   = App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02', '2026-03-06'])->count();
$present = App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02', '2026-03-06'])->where('is_present', true)->count();
$absent  = App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02', '2026-03-06'])->where('is_present', false)->count();
$review  = App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02', '2026-03-06'])->where('needs_schedule_review', true)->count();
$late    = App\Models\DailyAttendanceSummary::whereBetween('attendance_date', ['2026-02-02', '2026-03-06'])->where('is_late', true)->count();

echo "Range: 2026-02-02 to 2026-03-06\n";
echo "Total rows:            $total\n";
echo "Present:               $present\n";
echo "Absent:                $absent\n";
echo "Late:                  $late\n";
echo "Needs Schedule Review: $review\n";
echo "\nSanity: total should be ~" . (69 * 25) . " (69 employees x 25 working days)\n";

// Per-date spot check
echo "\nPer-date present count:\n";
$dates = ['2026-02-03','2026-02-04','2026-02-05','2026-02-06'];
foreach ($dates as $d) {
    $p = App\Models\DailyAttendanceSummary::whereDate('attendance_date', $d)->where('is_present',true)->count();
    $at = App\Models\DailyAttendanceSummary::whereDate('attendance_date', $d)->count();
    echo "  $d: $p/$at present\n";
}

