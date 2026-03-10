<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check period_number for adjustments
$adjustment = App\Models\PayrollAdjustment::with('payrollPeriod')->find(4);

if ($adjustment && $adjustment->payrollPeriod) {
    echo "Adjustment 4 Period Data:\n";
    echo "  ID: " . $adjustment->payrollPeriod->id . "\n";
    echo "  period_number: '" . $adjustment->payrollPeriod->period_number . "'\n";
    echo "  period_start: " . $adjustment->payrollPeriod->period_start->format('Y-m-d') . "\n";
    echo "  period_end: " . $adjustment->payrollPeriod->period_end->format('Y-m-d') . "\n";
    echo "\nFormatted: " . $adjustment->payrollPeriod->period_number . ' (' . 
        $adjustment->payrollPeriod->period_start->format('M d') . '–' . 
        $adjustment->payrollPeriod->period_end->format('M d, Y') . ')' . "\n";
} else {
    echo "Adjustment or period not found\n";
}
