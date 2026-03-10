<?php

/**
 * Phase 3 Controllers Database Integration Test
 *
 * Tests all three controllers updated in Phase 3:
 * - PayrollCalculationController - uses model methods for calculations
 * - PayrollAdjustmentController - queries database for adjustments
 * - PayrollReviewController - uses model methods for approvals
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Test counters
$tests_passed = 0;
$tests_failed = 0;
$errors = [];

echo "\n";
echo "========================================\n";
echo "   PHASE 3 CONTROLLERS TESTING\n";
echo "========================================\n\n";

/**
 * Helper function to log test results
 */
function test($description, $callback)
{
    global $tests_passed, $tests_failed, $errors;

    echo "Testing: {$description}... ";

    try {
        $result = $callback();
        if ($result === true || $result === null) {
            echo "✓ PASS\n";
            $tests_passed++;
            return true;
        } else {
            echo "✗ FAIL: {$result}\n";
            $tests_failed++;
            $errors[] = "{$description}: {$result}";
            return false;
        }
    } catch (Exception $e) {
        echo "✗ ERROR: {$e->getMessage()}\n";
        $tests_failed++;
        $errors[] = "{$description}: {$e->getMessage()}";
        return false;
    }
}

// ============================================================================
// TASK 3.1: PayrollCalculationController Tests
// ============================================================================

echo "\n--- Task 3.1: PayrollCalculationController ---\n";

test('PayrollCalculationController - Can create test payroll period', function () {
    DB::table('payroll_periods')->where('period_name', 'LIKE', 'Test Period%')->delete();

    $period = PayrollPeriod::create([
        'period_name' => 'Test Period for Calculation - Dec 1-15, 2025',
        'period_number' => 'TEST-DEC-2025-01',
        'period_type' => 'regular',
        'period_start' => '2025-12-01',
        'period_end' => '2025-12-15',
        'payment_date' => '2025-12-20',
        'period_month' => '2025-12',
        'period_year' => 2025,
        'timekeeping_cutoff_date' => '2025-12-15',
        'leave_cutoff_date' => '2025-12-15',
        'adjustment_deadline' => '2025-12-18',
        'status' => 'draft',
        'year' => 2025,
    ]);

    if (!$period || !$period->id) {
        return 'Failed to create test period';
    }

    return true;
});

test('PayrollCalculationController - Period can transition to calculating status', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();

    if (!$period) {
        return 'Test period not found';
    }

    $period->markAsCalculating();
    $period->refresh();

    if ($period->status !== 'calculating') {
        return "Expected status 'calculating', got '{$period->status}'";
    }

    return true;
});

test('PayrollCalculationController - Period can transition to calculated status', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();

    if (!$period) {
        return 'Test period not found';
    }

    $period->markAsCalculated();
    $period->refresh();

    if ($period->status !== 'calculated') {
        return "Expected status 'calculated', got '{$period->status}'";
    }

    return true;
});

test('PayrollCalculationController - Can approve period using model method', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();

    if (!$period) {
        return 'Test period not found';
    }

    // First submit for review
    $period->submitForReview();
    $period->refresh();

    // Then approve
    $user = User::first();
    if (!$user) {
        return 'No user found for approval';
    }

    $period->approve($user->id);
    $period->refresh();

    if ($period->status !== 'approved') {
        return "Expected status 'approved', got '{$period->status}'";
    }

    if ($period->approved_by !== $user->id) {
        return "Expected approved_by to be {$user->id}, got {$period->approved_by}";
    }

    if (!$period->approved_at) {
        return 'approved_at timestamp not set';
    }

    return true;
});

// ============================================================================
// TASK 3.2: PayrollAdjustmentController Tests
// ============================================================================

echo "\n--- Task 3.2: PayrollAdjustmentController ---\n";

test('PayrollAdjustmentController - Can create payroll adjustment in database', function () {
    // Clean up any existing test adjustments
    DB::table('payroll_adjustments')->where('reference_number', 'LIKE', 'TEST-ADJ%')->delete();

    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();
    $employee = Employee::with('profile')->first();

    if (!$period || !$employee) {
        return 'Missing test data (period or employee)';
    }

    // Create a payroll calculation first (required for adjustments)
    $calculation = EmployeePayrollCalculation::create([
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'employee_name' => ($employee->profile->first_name ?? '') . ' ' . ($employee->profile->last_name ?? ''),
        'employee_number' => $employee->employee_number,
        'department' => $employee->department->name ?? 'Test Department',
        'position' => $employee->position->title ?? 'Test Position',
        'basic_monthly_salary' => 30000.00,
        'daily_rate' => 1363.64,
        'hourly_rate' => 170.45,
        'gross_pay' => 30000.00,
        'total_deductions' => 5000.00,
        'net_pay' => 25000.00,
        'status' => 'calculated',
    ]);

    if (!$calculation) {
        return 'Failed to create payroll calculation';
    }

    $adjustment = PayrollAdjustment::create([
        'employee_payroll_calculation_id' => $calculation->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'adjustment_type' => 'addition',
        'category' => 'correction',
        'amount' => 5000.00,
        'reason' => 'Test overtime adjustment',
        'reference_number' => 'TEST-ADJ-001',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    if (!$adjustment || !$adjustment->id) {
        return 'Failed to create adjustment';
    }

    return true;
});

test('PayrollAdjustmentController - Can query adjustments by period using scope', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();

    if (!$period) {
        return 'Test period not found';
    }

    $adjustments = PayrollAdjustment::byPeriod($period->id)->get();

    if ($adjustments->count() === 0) {
        return 'No adjustments found for period';
    }

    $testAdj = $adjustments->where('reference_number', 'TEST-ADJ-001')->first();

    if (!$testAdj) {
        return 'Test adjustment not found in query results';
    }

    return true;
});

test('PayrollAdjustmentController - Can query adjustments by status using scope', function () {
    $pending = PayrollAdjustment::pending()->get();

    if ($pending->count() === 0) {
        return 'No pending adjustments found';
    }

    $testAdj = $pending->where('reference_number', 'TEST-ADJ-001')->first();

    if (!$testAdj) {
        return 'Test adjustment not found in pending adjustments';
    }

    return true;
});

test('PayrollAdjustmentController - Can approve adjustment using model method', function () {
    $adjustment = PayrollAdjustment::where('reference_number', 'TEST-ADJ-001')->first();

    if (!$adjustment) {
        return 'Test adjustment not found';
    }

    $user = User::first();
    if (!$user) {
        return 'No user found for approval';
    }

    $adjustment->approve($user);
    $adjustment->refresh();

    if ($adjustment->status !== 'approved') {
        return "Expected status 'approved', got '{$adjustment->status}'";
    }

    if ($adjustment->approved_by !== $user->id) {
        return "Expected approved_by to be {$user->id}, got {$adjustment->approved_by}";
    }

    if (!$adjustment->approved_at) {
        return 'approved_at timestamp not set';
    }

    return true;
});

test('PayrollAdjustmentController - Can reject adjustment using model method', function () {
    // Create another test adjustment for rejection
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Calculation%')->first();
    $employee = Employee::with('profile')->first();

    // Get the existing calculation or create a new one
    $calculation = EmployeePayrollCalculation::where('payroll_period_id', $period->id)
        ->where('employee_id', $employee->id)
        ->first();

    if (!$calculation) {
        $calculation = EmployeePayrollCalculation::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'employee_name' => ($employee->profile->first_name ?? '') . ' ' . ($employee->profile->last_name ?? ''),
            'employee_number' => $employee->employee_number,
            'department' => $employee->department->name ?? 'Test Department',
            'position' => $employee->position->title ?? 'Test Position',
            'basic_monthly_salary' => 30000.00,
            'daily_rate' => 1363.64,
            'hourly_rate' => 170.45,
            'gross_pay' => 30000.00,
            'total_deductions' => 5000.00,
            'net_pay' => 25000.00,
            'status' => 'calculated',
        ]);
    }

    $adjustment = PayrollAdjustment::create([
        'employee_payroll_calculation_id' => $calculation->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'adjustment_type' => 'deduction',
        'category' => 'penalty',
        'amount' => 1000.00,
        'reason' => 'Test deduction for rejection',
        'reference_number' => 'TEST-ADJ-002',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $user = User::first();
    $adjustment->reject($user, 'Test rejection reason');
    $adjustment->refresh();

    if ($adjustment->status !== 'rejected') {
        return "Expected status 'rejected', got '{$adjustment->status}'";
    }

    if ($adjustment->rejected_by !== $user->id) {
        return "Expected rejected_by to be {$user->id}, got {$adjustment->rejected_by}";
    }

    if ($adjustment->rejection_reason !== 'Test rejection reason') {
        return "Rejection reason not saved correctly";
    }

    return true;
});

test('PayrollAdjustmentController - Can query adjustments by employee', function () {
    $employee = Employee::first();

    if (!$employee) {
        return 'No employee found';
    }

    $adjustments = PayrollAdjustment::where('employee_id', $employee->id)->get();

    if ($adjustments->count() === 0) {
        return 'No adjustments found for employee';
    }

    // Should have at least our 2 test adjustments
    $count = $adjustments->whereIn('reference_number', ['TEST-ADJ-001', 'TEST-ADJ-002'])->count();

    if ($count < 2) {
        return "Expected at least 2 test adjustments, found {$count}";
    }

    return true;
});

// ============================================================================
// TASK 3.3: PayrollReviewController Tests
// ============================================================================

echo "\n--- Task 3.3: PayrollReviewController ---\n";

test('PayrollReviewController - Can create period for review workflow', function () {
    DB::table('payroll_periods')->where('period_name', 'Test Period for Review')->delete();

    $period = PayrollPeriod::create([
        'period_name' => 'Test Period for Review - Dec 16-31, 2025',
        'period_number' => 'TEST-DEC-2025-02',
        'period_type' => 'regular',
        'period_start' => '2025-12-16',
        'period_end' => '2025-12-31',
        'payment_date' => '2026-01-05',
        'period_month' => '2025-12',
        'period_year' => 2025,
        'timekeeping_cutoff_date' => '2025-12-31',
        'leave_cutoff_date' => '2025-12-31',
        'adjustment_deadline' => '2026-01-03',
        'status' => 'calculated',
        'year' => 2025,
    ]);

    if (!$period || !$period->id) {
        return 'Failed to create review test period';
    }

    return true;
});

test('PayrollReviewController - Can submit period for review using model method', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Review%')->first();

    if (!$period) {
        return 'Review test period not found';
    }

    $period->submitForReview();
    $period->refresh();

    if ($period->status !== 'under_review') {
        return "Expected status 'under_review', got '{$period->status}'";
    }

    if (!$period->submitted_for_review_at) {
        return 'submitted_for_review_at timestamp not set';
    }

    return true;
});

test('PayrollReviewController - Can approve period from under_review to approved', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Review%')->first();

    if (!$period) {
        return 'Review test period not found';
    }

    $user = User::first();
    if (!$user) {
        return 'No user found for approval';
    }

    $period->approve($user->id);
    $period->refresh();

    if ($period->status !== 'approved') {
        return "Expected status 'approved', got '{$period->status}'";
    }

    return true;
});

test('PayrollReviewController - Can finalize period using model method', function () {
    $period = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Review%')->first();

    if (!$period) {
        return 'Review test period not found';
    }

    $user = User::first();
    if (!$user) {
        return 'No user found for finalization';
    }

    $period->finalize($user->id);
    $period->refresh();

    if ($period->status !== 'finalized') {
        return "Expected status 'finalized', got '{$period->status}'";
    }

    if (!$period->finalized_at) {
        return 'finalized_at timestamp not set';
    }

    if (!$period->locked_at) {
        return 'locked_at timestamp not set';
    }

    if ($period->locked_by !== $user->id) {
        return "Expected locked_by to be {$user->id}, got {$period->locked_by}";
    }

    return true;
});

test('PayrollReviewController - Can query periods by status for review', function () {
    $reviewableStatuses = ['calculated', 'under_review', 'pending_approval', 'approved'];

    $periods = PayrollPeriod::whereIn('status', $reviewableStatuses)->get();

    if ($periods->count() === 0) {
        return 'No reviewable periods found';
    }

    // Should include our finalized test period (was approved before finalization)
    $testPeriod = PayrollPeriod::where('period_name', 'LIKE', 'Test Period for Review%')->first();

    if (!$testPeriod) {
        return 'Review test period not persisted to database';
    }

    return true;
});

// ============================================================================
// Test Summary
// ============================================================================

echo "\n";
echo "========================================\n";
echo "   TEST SUMMARY\n";
echo "========================================\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: {$tests_passed} ✓\n";
echo "Failed: {$tests_failed} ✗\n";
echo "Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) . "%\n";

if ($tests_failed > 0) {
    echo "\n--- Failed Tests ---\n";
    foreach ($errors as $error) {
        echo "✗ {$error}\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "\n✓ All Phase 3 tests passed successfully!\n\n";
    exit(0);
}
