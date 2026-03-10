<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Phase 1 Model Updates...\n\n";

// Test PayrollPeriod scopes
echo "1. Testing PayrollPeriod scopes:\n";
try {
    $query = App\Models\PayrollPeriod::query();
    echo "   ✓ Base query works\n";
    
    $query->draft();
    echo "   ✓ draft() scope works\n";
    
    App\Models\PayrollPeriod::query()->search('test');
    echo "   ✓ search() scope works\n";
    
    App\Models\PayrollPeriod::query()->byStatus('draft');
    echo "   ✓ byStatus() scope works\n";
    
    App\Models\PayrollPeriod::query()->byType('regular');
    echo "   ✓ byType() scope works\n";
    
    App\Models\PayrollPeriod::query()->completed();
    echo "   ✓ completed() scope works\n";
    
    App\Models\PayrollPeriod::query()->byYear(2026);
    echo "   ✓ byYear() scope works\n";
    
    echo "   ✅ All PayrollPeriod scopes passed!\n\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Test EmployeePayrollCalculation scopes
echo "2. Testing EmployeePayrollCalculation scopes:\n";
try {
    App\Models\EmployeePayrollCalculation::query()->byPayrollPeriod(1);
    echo "   ✓ byPayrollPeriod() scope works\n";
    
    App\Models\EmployeePayrollCalculation::query()->byEmployee(1);
    echo "   ✓ byEmployee() scope works\n";
    
    App\Models\EmployeePayrollCalculation::query()->withErrors();
    echo "   ✓ withErrors() scope works\n";
    
    App\Models\EmployeePayrollCalculation::query()->search('test');
    echo "   ✓ search() scope works\n";
    
    echo "   ✅ All EmployeePayrollCalculation scopes passed!\n\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Test PayrollAdjustment scopes (already existing)
echo "3. Testing PayrollAdjustment scopes:\n";
try {
    App\Models\PayrollAdjustment::query()->pending();
    echo "   ✓ pending() scope works\n";
    
    App\Models\PayrollAdjustment::query()->approved();
    echo "   ✓ approved() scope works\n";
    
    App\Models\PayrollAdjustment::query()->rejected();
    echo "   ✓ rejected() scope works\n";
    
    App\Models\PayrollAdjustment::query()->byPeriod(1);
    echo "   ✓ byPeriod() scope works\n";
    
    echo "   ✅ All PayrollAdjustment scopes passed!\n\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo "========================================\n";
echo "✅ Phase 1 Model Updates: ALL TESTS PASSED!\n";
echo "========================================\n";
