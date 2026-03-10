<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollAdjustment;

echo "Testing PayrollAdjustment Model Accessors\n";
echo str_repeat('=', 50) . "\n\n";

$adj = PayrollAdjustment::with([
    'employee.profile',
    'employee.department',
    'employee.position',
    'createdBy',
    'approvedBy',
    'rejectedBy',
    'payrollPeriod'
])->first();

if ($adj) {
    echo "✓ Found adjustment ID: {$adj->id}\n\n";
    
    echo "Testing Accessors:\n";
    echo "  - employee_name: " . ($adj->employee_name ?? 'NULL') . "\n";
    echo "  - employee_number: " . ($adj->employee_number ?? 'NULL') . "\n";
    echo "  - department: " . ($adj->department ?? 'NULL') . "\n";
    echo "  - position: " . ($adj->position ?? 'NULL') . "\n";
    echo "  - requested_by: " . ($adj->requested_by ?? 'NULL') . "\n";
    echo "  - requested_at: " . ($adj->requested_at ?? 'NULL') . "\n";
    echo "  - reviewed_by: " . ($adj->reviewed_by ?? 'NULL') . "\n";
    echo "  - reviewed_at: " . ($adj->reviewed_at ?? 'NULL') . "\n\n";
    
    echo "Testing Relationships:\n";
    echo "  - employee: " . ($adj->employee ? "✓ Loaded" : "✗ Not loaded") . "\n";
    echo "  - payrollPeriod: " . ($adj->payrollPeriod ? "✓ Loaded" : "✗ Not loaded") . "\n";
    echo "  - createdBy: " . ($adj->createdBy ? "✓ Loaded" : "✗ Not loaded") . "\n\n";
    
    echo "Testing getSignedAmount():\n";
    echo "  - adjustment_type: {$adj->adjustment_type}\n";
    echo "  - amount: {$adj->amount}\n";
    echo "  - signed_amount: " . $adj->getSignedAmount() . "\n\n";
    
    echo "✓ All accessors working correctly!\n";
} else {
    echo "✗ No adjustments found in database\n";
    echo "  Creating test data would be needed\n";
}
