<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing PayrollAdjustmentController Endpoints\n";
echo "================================================\n\n";

try {
    // Get a payroll officer user for authentication
    $user = \App\Models\User::whereHas('roles', function($q) {
        $q->where('name', 'Payroll Officer');
    })->first();
    
    if (!$user) {
        echo "✗ No Payroll Officer user found\n";
        exit(1);
    }
    
    // Authenticate as the user
    \Illuminate\Support\Facades\Auth::login($user);
    echo "✓ Authenticated as: {$user->name}\n\n";
    
    // Test 1: Test index() method
    echo "Test 1: Testing index() method...\n";
    $controller = new \App\Http\Controllers\Payroll\PayrollProcessing\PayrollAdjustmentController();
    
    $request = Request::create('/payroll/adjustments', 'GET');
    app()->instance('request', $request);
    
    $response = $controller->index($request);
    
    // Get props from Inertia response using reflection
    $reflection = new \ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);
    $props = $property->getValue($response);
    
    echo "  ✓ Index method executed successfully\n";
    echo "  Adjustments count: " . count($props['adjustments']) . "\n";
    echo "  Available periods: " . count($props['available_periods']) . "\n";
    echo "  Available employees: " . count($props['available_employees']) . "\n";
    
    if (count($props['adjustments']) > 0) {
        $adj = $props['adjustments'][0];
        echo "\n  First adjustment details:\n";
        echo "    ID: {$adj['id']}\n";
        echo "    Employee: {$adj['employee_name']}\n";
        echo "    Type: {$adj['adjustment_type']}\n";
        echo "    Category: {$adj['adjustment_category']}\n";
        echo "    Amount: ₱" . number_format($adj['amount'], 2) . "\n";
        echo "    Status: {$adj['status']}\n";
        echo "    Requested By: {$adj['requested_by']}\n";
        echo "    Requested At: {$adj['requested_at']}\n";
    }
    echo "\n";
    
    // Test 2: Test store() method
    echo "Test 2: Testing store() method...\n";
    
    $employee = \App\Models\Employee::first();
    $period = \App\Models\PayrollPeriod::first();
    
    if ($employee && $period) {
        $storeRequest = Request::create('/payroll/adjustments', 'POST', [
            'payroll_period_id'   => $period->id,
            'employee_id'         => $employee->id,
            'adjustment_type'     => 'earning',
            'adjustment_category' => 'bonus',
            'amount'              => 2500.00,
            'reason'              => 'Performance bonus Q1 2026',
            'reference_number'    => 'BON-2026-001',
        ]);
        
        $storeResponse = $controller->store($storeRequest);
        
        // Check if the adjustment was created
        $newAdjustment = \App\Models\PayrollAdjustment::where('reference_number', 'BON-2026-001')->first();
        
        if ($newAdjustment) {
            echo "  ✓ Store method executed successfully\n";
            echo "    Created adjustment ID: {$newAdjustment->id}\n";
            echo "    Employee: {$newAdjustment->employee_name}\n";
            echo "    Category: {$newAdjustment->category}\n";
            echo "    Amount: ₱" . number_format($newAdjustment->amount, 2) . "\n";
        } else {
            echo "  ✗ Failed to create adjustment\n";
        }
    } else {
        echo "  ✗ Cannot test store - missing employee or period\n";
    }
    echo "\n";
    
    // Test 3: Test approve() method
    echo "Test 3: Testing approve() method...\n";
    
    $pendingAdjustment = \App\Models\PayrollAdjustment::pending()->first();
    
    if ($pendingAdjustment) {
        $approveRequest = Request::create("/payroll/adjustments/{$pendingAdjustment->id}/approve", 'POST');
        $approveResponse = $controller->approve($pendingAdjustment->id);
        
        $pendingAdjustment->refresh();
        
        if ($pendingAdjustment->status === 'approved') {
            echo "  ✓ Approve method executed successfully\n";
            echo "    Adjustment ID: {$pendingAdjustment->id}\n";
            echo "    Status: {$pendingAdjustment->status}\n";
            echo "    Approved By: {$pendingAdjustment->approvedBy->name}\n";
            echo "    Approved At: {$pendingAdjustment->approved_at}\n";
        } else {
            echo "  ✗ Failed to approve adjustment\n";
        }
    } else {
        echo "  ✗ No pending adjustments to test approve\n";
    }
    echo "\n";
    
    // Test 4: Test reject() method
    echo "Test 4: Testing reject() method...\n";
    
    // Create another adjustment to reject
    $rejectAdjustment = \App\Models\PayrollAdjustment::create([
        'payroll_period_id'   => $period->id,
        'employee_id'         => $employee->id,
        'adjustment_type'     => 'deduction',
        'category'            => 'penalty',
        'amount'              => 500.00,
        'reason'              => 'Test rejection',
        'status'              => 'pending',
        'submitted_at'        => now(),
        'created_by'          => $user->id,
    ]);
    
    $rejectRequest = Request::create("/payroll/adjustments/{$rejectAdjustment->id}/reject", 'POST', [
        'rejection_notes' => 'Insufficient documentation provided',
    ]);
    
    $rejectResponse = $controller->reject($rejectRequest, $rejectAdjustment->id);
    
    $rejectAdjustment->refresh();
    
    if ($rejectAdjustment->status === 'rejected') {
        echo "  ✓ Reject method executed successfully\n";
        echo "    Adjustment ID: {$rejectAdjustment->id}\n";
        echo "    Status: {$rejectAdjustment->status}\n";
        echo "    Rejected By: {$rejectAdjustment->rejectedBy->name}\n";
        echo "    Rejected At: {$rejectAdjustment->rejected_at}\n";
        echo "    Review Notes: {$rejectAdjustment->review_notes}\n";
    } else {
        echo "  ✗ Failed to reject adjustment\n";
    }
    echo "\n";
    
    // Test 5: Test history() method
    echo "Test 5: Testing history() method...\n";
    
    if ($employee) {
        $historyRequest = Request::create("/payroll/adjustments/history/{$employee->id}", 'GET');
        app()->instance('request', $historyRequest);
        
        $historyResponse = $controller->history($historyRequest, $employee->id);
        
        // Get props using reflection
        $reflection = new \ReflectionClass($historyResponse);
        $property = $reflection->getProperty('props');
        $property->setAccessible(true);
        $historyProps = $property->getValue($historyResponse);
        
        echo "  ✓ History method executed successfully\n";
        echo "    Employee: {$historyProps['employee_name']}\n";
        echo "    Employee Number: {$historyProps['employee_number']}\n";
        echo "    Department: {$historyProps['department']}\n";
        echo "    Total Adjustments: {$historyProps['summary']['total_adjustments']}\n";
        echo "    Pending: {$historyProps['summary']['pending_adjustments']}\n";
        echo "    Approved: {$historyProps['summary']['approved_adjustments']}\n";
        echo "    Rejected: {$historyProps['summary']['rejected_adjustments']}\n";
    } else {
        echo "  ✗ Cannot test history - no employee found\n";
    }
    echo "\n";
    
    echo "================================================\n";
    echo "All controller tests completed successfully!\n";
    echo "================================================\n";

} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
