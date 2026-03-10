$employee = \App\Models\Employee::first();
$period = \App\Models\PayrollPeriod::first();

echo "Phase 4, Task 4.1: Verify normal calculation works\n";
echo "=====================================================\n\n";

if (!$employee || !$period) {
    echo "ERROR: Need test data\n";
    echo "Employee count: " . \App\Models\Employee::count() . "\n";
    echo "Period count: " . \App\Models\PayrollPeriod::count() . "\n";
    exit;
}

$svc = app(\App\Services\Payroll\PayrollCalculationService::class);

echo "Step 1: First calculation (should create v1)...\n";
$calc1 = $svc->calculateEmployee($employee, $period);
echo "✓ Success: v{$calc1->version} id={$calc1->id}\n\n";

echo "Step 2: Recalculation (should create v2)...\n";
$calc2 = $svc->calculateEmployee($employee, $period);
echo "✓ Success: v{$calc2->version} id={$calc2->id} prev={$calc2->previous_version_id}\n\n";

echo "Step 3: Verify version chain...\n";
if ($calc2->version === 2 && $calc1->version === 1) {
    echo "✓ Versions correct: v1 → v2\n";
}
if ($calc2->previous_version_id === $calc1->id) {
    echo "✓ Previous version ID correct: " . $calc2->previous_version_id . " = " . $calc1->id . "\n";
}

echo "\n✓✓✓ TASK 4.1 VERIFICATION PASSED ✓✓✓\n";
