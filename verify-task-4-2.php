<?php
/**
 * Phase 4, Task 4.2 — Verify old version is preserved (not destroyed)
 * 
 * This script verifies that when payroll is recalculated:
 * - The old version record still exists in the database (not force-deleted)
 * - The old version is marked as 'superseded'
 * - The old version has deleted_at timestamp set (soft-deleted)
 * - The new version is created fresh with 'calculated' status
 * - Both versions are queryable via withTrashed()
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Kernel registration for database access
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$app->booted(function () {
    echo "=== Phase 4, Task 4.2: Verify Old Version Preserved ===\n\n";
    
    $employee = \App\Models\Employee::first();
    $period = \App\Models\PayrollPeriod::first();

    if (!$employee || !$period) {
        echo "ERROR: Need test data\n";
        echo "  Employee count: " . \App\Models\Employee::count() . "\n";
        echo "  Period count: " . \App\Models\PayrollPeriod::count() . "\n";
        exit(1);
    }

    $svc = app(\App\Services\Payroll\PayrollCalculationService::class);
    
    echo "Step 1: Create first calculation (v1)...\n";
    try {
        $calc1 = $svc->calculateEmployee($employee, $period);
        echo "  ✓ Created v{$calc1->version} (id={$calc1->id}, status={$calc1->calculation_status})\n\n";
    } catch (\Exception $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "Step 2: Recalculate to create second version (v2)...\n";
    try {
        $calc2 = $svc->calculateEmployee($employee, $period);
        echo "  ✓ Created v{$calc2->version} (id={$calc2->id}, status={$calc2->calculation_status})\n\n";
    } catch (\Exception $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "Step 3: Query all versions (including soft-deleted) for this employee/period...\n";
    $history = \App\Models\EmployeePayrollCalculation::withTrashed()
        ->where('employee_id', $employee->id)
        ->where('payroll_period_id', $period->id)
        ->orderBy('version')
        ->get(['id', 'version', 'calculation_status', 'deleted_at']);

    echo "  Found " . count($history) . " records:\n";
    foreach ($history as $record) {
        $deletedStatus = $record->deleted_at ? '✓ SOFT-DELETED' : '  not deleted';
        echo "    - v{$record->version} (id={$record->id}): status='{$record->calculation_status}', deleted_at={$deletedStatus}\n";
    }
    echo "\n";
    
    echo "Step 4: Verify record preservation...\n";
    $passes = 0;
    $fails = 0;
    
    // Verify we have exactly 2 records
    if (count($history) === 2) {
        echo "  ✓ Exactly 2 versions exist\n";
        $passes++;
    } else {
        echo "  ✗ Expected 2 versions, found " . count($history) . "\n";
        $fails++;
    }
    
    // Verify v1 properties
    $v1 = $history->where('version', 1)->first();
    if ($v1) {
        if ($v1->calculation_status === 'superseded') {
            echo "  ✓ v1 status is 'superseded'\n";
            $passes++;
        } else {
            echo "  ✗ v1 status is '{$v1->calculation_status}', expected 'superseded'\n";
            $fails++;
        }
        
        if ($v1->deleted_at !== null) {
            echo "  ✓ v1 has deleted_at timestamp (soft-deleted)\n";
            $passes++;
        } else {
            echo "  ✗ v1 deleted_at is null (should be soft-deleted)\n";
            $fails++;
        }
    } else {
        echo "  ✗ v1 record not found\n";
        $fails++;
    }
    
    // Verify v2 properties
    $v2 = $history->where('version', 2)->first();
    if ($v2) {
        if ($v2->calculation_status === 'calculated') {
            echo "  ✓ v2 status is 'calculated'\n";
            $passes++;
        } else {
            echo "  ✗ v2 status is '{$v2->calculation_status}', expected 'calculated'\n";
            $fails++;
        }
        
        if ($v2->deleted_at === null) {
            echo "  ✓ v2 has no deleted_at (not soft-deleted)\n";
            $passes++;
        } else {
            echo "  ✗ v2 deleted_at is set (should not be soft-deleted)\n";
            $fails++;
        }
    } else {
        echo "  ✗ v2 record not found\n";
        $fails++;
    }
    
    echo "\n";
    
    // Summary
    if ($fails === 0) {
        echo "✓✓✓ TASK 4.2 VERIFICATION PASSED ✓✓✓\n";
        echo "All old versions are properly preserved with soft-delete!\n";
        exit(0);
    } else {
        echo "✗✗✗ TASK 4.2 VERIFICATION FAILED ✗✗✗\n";
        echo "Passed: {$passes}, Failed: {$fails}\n";
        exit(1);
    }
});
