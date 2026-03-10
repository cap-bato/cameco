<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Payroll Adjustments Integration\n";
echo "========================================\n\n";

try {
    // Test 1: Check if review_notes column exists
    echo "Test 1: Checking database schema...\n";
    $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'payroll_adjustments' AND column_name = 'review_notes'");
    
    if (!empty($columns)) {
        echo "✓ review_notes column exists\n\n";
    } else {
        echo "✗ review_notes column missing - run migrations first\n\n";
    }

    // Test 2: Check adjustment_type enum values
    echo "Test 2: Checking adjustment_type constraint...\n";
    $checkConstraint = DB::select("
        SELECT check_clause 
        FROM information_schema.check_constraints 
        WHERE constraint_name LIKE '%adjustment_type%' 
        AND constraint_schema = 'public'
    ");
    
    if (!empty($checkConstraint)) {
        echo "✓ adjustment_type constraint found\n";
        echo "  Constraint: " . $checkConstraint[0]->check_clause . "\n\n";
    } else {
        echo "✗ adjustment_type constraint not found\n\n";
    }

    // Test 3: Test PayrollAdjustment model accessors
    echo "Test 3: Testing model accessors...\n";
    
    $adjustment = \App\Models\PayrollAdjustment::with([
        'employee.profile',
        'employee.department',
        'employee.position',
        'payrollPeriod',
        'createdBy',
        'approvedBy',
        'rejectedBy'
    ])->first();
    
    if ($adjustment) {
        echo "✓ Found adjustment ID: {$adjustment->id}\n";
        echo "  Employee Name: {$adjustment->employee_name}\n";
        echo "  Employee Number: {$adjustment->employee_number}\n";
        echo "  Department: {$adjustment->department}\n";
        echo "  Position: {$adjustment->position}\n";
        echo "  Requested By: {$adjustment->requested_by}\n";
        echo "  Requested At: {$adjustment->requested_at}\n";
        echo "  Reviewed By: " . ($adjustment->reviewed_by ?? 'N/A') . "\n";
        echo "  Reviewed At: " . ($adjustment->reviewed_at ?? 'N/A') . "\n";
        echo "  Adjustment Type: {$adjustment->adjustment_type}\n";
        echo "  Category: {$adjustment->category}\n";
        echo "  Amount: ₱" . number_format($adjustment->amount, 2) . "\n";
        echo "  Status: {$adjustment->status}\n\n";
    } else {
        echo "✗ No adjustments found in database\n";
        echo "  Creating test adjustment...\n";
        
        // Get first employee and period
        $employee = \App\Models\Employee::first();
        $period = \App\Models\PayrollPeriod::first();
        $user = \App\Models\User::first();
        
        if ($employee && $period && $user) {
            $testAdjustment = \App\Models\PayrollAdjustment::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
                'adjustment_type' => 'earning',
                'category' => 'bonus',
                'amount' => 1000.00,
                'reason' => 'Test adjustment for integration testing',
                'status' => 'pending',
                'submitted_at' => now(),
                'created_by' => $user->id,
            ]);
            
            echo "  ✓ Test adjustment created with ID: {$testAdjustment->id}\n";
            echo "  Employee Name: {$testAdjustment->employee_name}\n";
            echo "  Requested By: {$testAdjustment->requested_by}\n\n";
        } else {
            echo "  ✗ Cannot create test adjustment - missing employee, period, or user\n\n";
        }
    }

    // Test 4: Count adjustments by status
    echo "Test 4: Counting adjustments by status...\n";
    $statuses = ['pending', 'approved', 'rejected', 'applied'];
    foreach ($statuses as $status) {
        $count = \App\Models\PayrollAdjustment::where('status', $status)->count();
        echo "  {$status}: {$count}\n";
    }
    echo "\n";

    // Test 5: Test scopes
    echo "Test 5: Testing model scopes...\n";
    $pendingCount = \App\Models\PayrollAdjustment::pending()->count();
    $approvedCount = \App\Models\PayrollAdjustment::approved()->count();
    $rejectedCount = \App\Models\PayrollAdjustment::rejected()->count();
    
    echo "  ✓ Pending scope: {$pendingCount}\n";
    echo "  ✓ Approved scope: {$approvedCount}\n";
    echo "  ✓ Rejected scope: {$rejectedCount}\n\n";

    echo "========================================\n";
    echo "All tests completed!\n";

} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
    exit(1);
}
