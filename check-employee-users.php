<?php

use App\Models\Employee;
use App\Models\User;

$employees = Employee::where('employee_number', 'like', 'EMP-2024-%')->get();

echo "=== Employee to User Linking Status ===\n";
foreach ($employees as $emp) {
    $status = $emp->user_id ? '✓' : '✗';
    $username = $emp->user_id ? User::find($emp->user_id)->username : 'N/A';
    echo "{$status} {$emp->employee_number} -> user_id: {$emp->user_id} (username: {$username})\n";
}
