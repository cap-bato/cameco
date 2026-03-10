<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$app->booted(function () {
    $employee = \App\Models\Employee::first();
    $period = \App\Models\PayrollPeriod::first();

    if (!$employee || !$period) {
        echo "ERROR: Need test data. Employee count: " . \App\Models\Employee::count() . ", Period count: " . \App\Models\PayrollPeriod::count() . "\n";
        exit(1);
    }

    $svc = app(\App\Services\Payroll\PayrollCalculationService::class);
    
    echo "=== Phase 4, Task 4.1 Verification ===\n\n";
    
    // First calculation
    echo "1. Testing first calculation (should create v1)...\n";
    try {
        $calc1 = $svc->calculateEmployee($employee, $period);
        echo "   ✓ First calculation succeeded\n";
        echo "   Version: {$calc1->version}\n";
        echo "   ID: {$calc1->id}\n";
        echo "   Status: {$calc1->calculation_status}\n";
        echo "   Previous Version ID: " . ($calc1->previous_version_id ?? 'null') . "\n\n";
    } catch (\Exception $e) {
        echo "   ✗ First calculation failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Recalculate
    echo "2. Testing recalculation (should create v2 with link to v1)...\n";
    try {
        $calc2 = $svc->calculateEmployee($employee, $period);
        echo "   ✓ Recalculation succeeded\n";
        echo "   Version: {$calc2->version}\n";
        echo "   ID: {$calc2->id}\n";
        echo "   Status: {$calc2->calculation_status}\n";
        echo "   Previous Version ID: " . ($calc2->previous_version_id ?? 'null') . "\n\n";
    } catch (\Exception $e) {
        echo "   ✗ Recalculation failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Verify version chain
    echo "3. Verifying version chain...\n";
    if ($calc2->version === ($calc1->version + 1)) {
        echo "   ✓ Version incremented correctly (v{$calc1->version} → v{$calc2->version})\n";
    } else {
        echo "   ✗ Version not incremented correctly\n";
        exit(1);
    }
    
    if ($calc2->previous_version_id === $calc1->id) {
        echo "   ✓ Previous version ID points to first calculation\n\n";
    } else {
        echo "   ✗ Previous version ID doesn't match first calculation\n";
        exit(1);
    }
    
    echo "=== Task 4.1 VERIFICATION PASSED ✓ ===\n";
});
