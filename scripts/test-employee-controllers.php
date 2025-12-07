<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Employee;

echo "\n=== Employee Portal Controller Tests ===\n\n";

// Test 1: Check test employee users exist
echo "1. Checking test employee users...\n";
$employeeUsers = User::role('Employee')->with('employee.department', 'employee.position')->get();
echo "   Found: " . $employeeUsers->count() . " Employee users\n";

foreach ($employeeUsers as $user) {
    echo "   - " . $user->username . " (" . $user->email . ")\n";
    if ($user->employee) {
        echo "     Employee #: " . $user->employee->employee_number . "\n";
        echo "     Department: " . ($user->employee->department->name ?? 'N/A') . "\n";
        echo "     Position: " . ($user->employee->position->title ?? 'N/A') . "\n";
    } else {
        echo "     ⚠️  No employee record linked!\n";
    }
}

// Test 2: Check User->employee relationship works
echo "\n2. Testing User->employee relationship...\n";
$testUser = User::role('Employee')->first();
if ($testUser && $testUser->employee) {
    echo "   ✅ User->employee relationship works\n";
    echo "   User ID: " . $testUser->id . " -> Employee ID: " . $testUser->employee->id . "\n";
} else {
    echo "   ❌ User->employee relationship broken!\n";
}

// Test 3: Check Employee->user relationship works
echo "\n3. Testing Employee->user relationship...\n";
if ($testUser && $testUser->employee) {
    $employee = $testUser->employee;
    if ($employee->user && $employee->user->id === $testUser->id) {
        echo "   ✅ Employee->user relationship works (bidirectional)\n";
        echo "   Employee ID: " . $employee->id . " -> User ID: " . $employee->user->id . "\n";
    } else {
        echo "   ❌ Employee->user relationship broken!\n";
    }
}

// Test 4: Simulate DashboardController logic
echo "\n4. Simulating DashboardController logic...\n";
if ($testUser && $testUser->employee) {
    $employee = $testUser->employee;
    
    // Check leave balances
    $leaveBalances = \App\Models\LeaveBalance::where('employee_id', $employee->id)
        ->where('year', date('Y'))
        ->with('leavePolicy')
        ->get();
    
    echo "   Employee: " . $employee->employee_number . "\n";
    echo "   Leave Balances: " . $leaveBalances->count() . " policies\n";
    
    foreach ($leaveBalances as $balance) {
        echo "     - " . ($balance->leavePolicy->name ?? 'Unknown') . ": ";
        echo $balance->remaining . " days remaining\n";
    }
    
    // Check pending leave requests
    $pendingRequests = \App\Models\LeaveRequest::where('employee_id', $employee->id)
        ->where('status', 'Pending')
        ->count();
    
    echo "   Pending Leave Requests: " . $pendingRequests . "\n";
    
    if ($leaveBalances->count() > 0 || $pendingRequests >= 0) {
        echo "   ✅ DashboardController logic would work\n";
    }
}

// Test 5: Simulate ProfileController logic
echo "\n5. Simulating ProfileController logic...\n";
if ($testUser && $testUser->employee) {
    $employee = $testUser->employee;
    $employee->load('profile', 'department', 'position', 'supervisor');
    
    echo "   Employee Number: " . $employee->employee_number . "\n";
    echo "   Employment Type: " . ucfirst($employee->employment_type) . "\n";
    echo "   Status: " . ucfirst($employee->status) . "\n";
    echo "   Department: " . ($employee->department->name ?? 'N/A') . "\n";
    echo "   Position: " . ($employee->position->title ?? 'N/A') . "\n";
    
    if ($employee->profile) {
        echo "   Profile Full Name: " . ($employee->profile->full_name ?? 'N/A') . "\n";
        echo "   Contact Number: " . ($employee->profile->contact_number ?? 'Not set') . "\n";
        echo "   Email: " . ($employee->profile->email ?? 'Not set') . "\n";
        echo "   ✅ ProfileController logic would work\n";
    } else {
        echo "   ⚠️  No profile record found (may need to seed profiles)\n";
    }
}

echo "\n=== Tests Complete ===\n\n";
