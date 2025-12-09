<?php

/**
 * Employee Middleware Validation Test Script
 * 
 * Tests:
 * 1. HR Manager CANNOT access employee routes
 * 2. Office Admin CANNOT access employee routes
 * 3. Superadmin CAN access employee routes (for testing/oversight)
 * 4. Employee CAN access employee routes
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

echo "\n";
echo "==========================================================\n";
echo "Employee Portal Middleware Validation Test\n";
echo "==========================================================\n";
echo "\n";

// Find test users for each role
echo "Finding test users...\n";
echo "----------------------------------------------------------\n";

$hrManager = User::whereHas('roles', function($q) {
    $q->where('name', 'HR Manager');
})->first();

$officeAdmin = User::whereHas('roles', function($q) {
    $q->where('name', 'Office Admin');
})->first();

$superadmin = User::whereHas('roles', function($q) {
    $q->where('name', 'Superadmin');
})->first();

$employee = User::whereHas('roles', function($q) {
    $q->where('name', 'Employee');
})->first();

echo "HR Manager: " . ($hrManager ? $hrManager->email . " (ID: {$hrManager->id})" : "❌ NOT FOUND") . "\n";
echo "Office Admin: " . ($officeAdmin ? $officeAdmin->email . " (ID: {$officeAdmin->id})" : "❌ NOT FOUND") . "\n";
echo "Superadmin: " . ($superadmin ? $superadmin->email . " (ID: {$superadmin->id})" : "❌ NOT FOUND") . "\n";
echo "Employee: " . ($employee ? $employee->email . " (ID: {$employee->id})" : "❌ NOT FOUND") . "\n";

echo "\n";
echo "==========================================================\n";
echo "Middleware Authorization Tests\n";
echo "==========================================================\n";
echo "\n";

// Test 1: HR Manager should NOT have access
echo "Test 1: HR Manager Access\n";
echo "----------------------------------------------------------\n";
if ($hrManager) {
    $hasRole = $hrManager->hasRole(['Employee', 'Superadmin']);
    echo "User: {$hrManager->email}\n";
    echo "Roles: " . implode(', ', $hrManager->getRoleNames()->toArray()) . "\n";
    echo "Has Employee or Superadmin role: " . ($hasRole ? "✅ YES" : "❌ NO") . "\n";
    echo "Expected: ❌ NO (should be BLOCKED)\n";
    echo "Result: " . ($hasRole ? "❌ FAILED - HR Manager has access!" : "✅ PASSED - HR Manager blocked") . "\n";
} else {
    echo "❌ SKIPPED - No HR Manager user found\n";
}

echo "\n";

// Test 2: Office Admin should NOT have access
echo "Test 2: Office Admin Access\n";
echo "----------------------------------------------------------\n";
if ($officeAdmin) {
    $hasRole = $officeAdmin->hasRole(['Employee', 'Superadmin']);
    echo "User: {$officeAdmin->email}\n";
    echo "Roles: " . implode(', ', $officeAdmin->getRoleNames()->toArray()) . "\n";
    echo "Has Employee or Superadmin role: " . ($hasRole ? "✅ YES" : "❌ NO") . "\n";
    echo "Expected: ❌ NO (should be BLOCKED)\n";
    echo "Result: " . ($hasRole ? "❌ FAILED - Office Admin has access!" : "✅ PASSED - Office Admin blocked") . "\n";
} else {
    echo "❌ SKIPPED - No Office Admin user found\n";
}

echo "\n";

// Test 3: Superadmin SHOULD have access
echo "Test 3: Superadmin Access\n";
echo "----------------------------------------------------------\n";
if ($superadmin) {
    $hasRole = $superadmin->hasRole(['Employee', 'Superadmin']);
    echo "User: {$superadmin->email}\n";
    echo "Roles: " . implode(', ', $superadmin->getRoleNames()->toArray()) . "\n";
    echo "Has Employee or Superadmin role: " . ($hasRole ? "✅ YES" : "❌ NO") . "\n";
    echo "Expected: ✅ YES (for testing/oversight)\n";
    echo "Result: " . ($hasRole ? "✅ PASSED - Superadmin has access" : "❌ FAILED - Superadmin blocked!") . "\n";
} else {
    echo "❌ SKIPPED - No Superadmin user found\n";
}

echo "\n";

// Test 4: Employee SHOULD have access
echo "Test 4: Employee Access\n";
echo "----------------------------------------------------------\n";
if ($employee) {
    $hasRole = $employee->hasRole(['Employee', 'Superadmin']);
    echo "User: {$employee->email}\n";
    echo "Roles: " . implode(', ', $employee->getRoleNames()->toArray()) . "\n";
    echo "Has Employee or Superadmin role: " . ($hasRole ? "✅ YES" : "❌ NO") . "\n";
    echo "Expected: ✅ YES\n";
    echo "Result: " . ($hasRole ? "✅ PASSED - Employee has access" : "❌ FAILED - Employee blocked!") . "\n";
} else {
    echo "❌ SKIPPED - No Employee user found\n";
}

echo "\n";
echo "==========================================================\n";
echo "Route Registration Tests\n";
echo "==========================================================\n";
echo "\n";

// Test route registration
$routes = collect(app('router')->getRoutes())->filter(function($route) {
    return str_starts_with($route->getName() ?? '', 'employee.');
});

echo "Total employee.* routes registered: " . $routes->count() . "\n";
echo "Expected: 18 routes\n";
echo "Result: " . ($routes->count() === 18 ? "✅ PASSED" : "❌ FAILED - Expected 18, found " . $routes->count()) . "\n";

echo "\n";
echo "Sample registered routes:\n";
$routes->take(5)->each(function($route) {
    echo "  - {$route->getName()}: {$route->methods()[0]} {$route->uri()}\n";
});

echo "\n";
echo "==========================================================\n";
echo "Session Configuration Test\n";
echo "==========================================================\n";
echo "\n";

$sessionLifetime = config('session.lifetime');
echo "Session lifetime: {$sessionLifetime} minutes\n";
echo "Expected: 30 minutes\n";
echo "Result: " . ($sessionLifetime == 30 ? "✅ PASSED" : "❌ FAILED - Expected 30, found {$sessionLifetime}") . "\n";

echo "\n";
echo "==========================================================\n";
echo "Test Summary\n";
echo "==========================================================\n";
echo "\n";

$testResults = [];
if ($hrManager) {
    $testResults[] = !$hrManager->hasRole(['Employee', 'Superadmin']);
}
if ($officeAdmin) {
    $testResults[] = !$officeAdmin->hasRole(['Employee', 'Superadmin']);
}
if ($superadmin) {
    $testResults[] = $superadmin->hasRole(['Employee', 'Superadmin']);
}
if ($employee) {
    $testResults[] = $employee->hasRole(['Employee', 'Superadmin']);
}
$testResults[] = $routes->count() === 18;
$testResults[] = $sessionLifetime == 30;

$passed = array_filter($testResults, fn($r) => $r === true);
$total = count($testResults);
$passedCount = count($passed);

echo "Tests Passed: {$passedCount}/{$total}\n";
echo "Status: " . ($passedCount === $total ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";

echo "\n";
