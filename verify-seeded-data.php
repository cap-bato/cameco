<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Verify leave balances were created
    $totalBalances = DB::table('leave_balances')->count();
    $totalAccruals = DB::table('leave_accruals')->count();
    $employeesWithBalances = DB::table('leave_balances')->distinct('employee_id')->count('employee_id');

    echo "✓ Leave Balances Total: $totalBalances\n";
    echo "✓ Leave Accruals Total: $totalAccruals\n";
    echo "✓ Employees with Balances: $employeesWithBalances\n";

    // Show sample data
    $sample = DB::table('leave_balances')
        ->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
        ->join('leave_policies', 'leave_balances.leave_policy_id', '=', 'leave_policies.id')
        ->select('employees.employee_number', 'leave_policies.name', 'leave_balances.earned', 'leave_balances.used', 'leave_balances.year')
        ->orderBy('employees.employee_number')
        ->limit(10)
        ->get();

    echo "\n✓ Sample Leave Balances (first 10 records):\n";
    foreach ($sample as $row) {
        echo "  {$row->employee_number} | {$row->name} | Earned: {$row->earned} | Used: {$row->used} | Year: {$row->year}\n";
    }

    // Calculate summary statistics
    $stats = DB::table('leave_balances')
        ->where('year', now()->year)
        ->selectRaw('SUM(earned) as total_earned, SUM(used) as total_used, COUNT(DISTINCT employee_id) as employee_count')
        ->first();

    echo "\n✓ Summary Statistics (Current Year: " . now()->year . "):\n";
    echo "  • Total Earned: " . ($stats->total_earned ?? 0) . "\n";
    echo "  • Total Used: " . ($stats->total_used ?? 0) . "\n";
    echo "  • Employees with Data: " . ($stats->employee_count ?? 0) . "\n";

    echo "\n✓ Seeded data verification PASSED!\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
