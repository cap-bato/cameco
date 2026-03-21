<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollPeriodsSeeder extends Seeder
{
    /**
     * Seed payroll periods for testing the payments module.
     * Creates periods for the last 6 months with various statuses.
     */
    public function run(): void
    {
        // Always create a fresh completed payroll period for demo/testing
        // Truncate payroll_periods table for fresh seeding
        \DB::statement('TRUNCATE TABLE payroll_periods RESTART IDENTITY CASCADE');
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->day(15);
        $paymentDate = $periodEnd->copy()->addDays(2);
        $periodNumber = sprintf('%s-%02d-1H', $periodStart->year, $periodStart->month);
        $payrollOfficer = User::where('email', 'payroll@cameco.com')->first();
        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        $creatorId = $payrollOfficer?->id ?? $hrManager?->id ?? 1;
        $approverId = $hrManager?->id ?? $creatorId;
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $excludedEmployees = max(0, $totalEmployees - $activeEmployees);
        // For demo: use a realistic average net pay
        $averageNetPay = 25000;
        // Remove any existing period with this number (for idempotency)
        PayrollPeriod::where('period_number', $periodNumber)->delete();
        $periodsToDelete = [];
        // We'll collect all period_numbers to be inserted and delete them before insert
        // ...existing code...

        // Get actual employee counts from database
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $excludedEmployees = max(0, $totalEmployees - $activeEmployees);

        if ($totalEmployees === 0) {
            $this->command->warn('No employees found in database — skipping payroll periods seeding.');
            return;
        }

        $this->command->info("Found {$totalEmployees} total employees, {$activeEmployees} active");

        $payrollOfficer = User::where('email', 'payroll@cameco.com')->first();
        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        $creatorId = $payrollOfficer?->id ?? $hrManager?->id ?? 1;
        $approverId = $hrManager?->id ?? $creatorId;
        $periodsToDelete = [];

        $now = Carbon::now();
        $periods = [];

        // Create 6 months of periods (12 periods: 1H and 2H per month)

        for ($i = 5; $i >= 0; $i--) {
            // First half
            $periodStart = $now->copy()->subMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->day(15);
            $paymentDate = $periodEnd->copy()->addDays(2);
            $periodNumber = sprintf('%s-%02d-1H', $periodStart->year, $periodStart->month);

            // Determine status logic
            $isMarch = ($periodStart->month === 3 && $periodStart->year === $now->year);
            if ($isMarch) {
                $status = 'approved';
            } else {
                $status = 'completed';
            }
            $progress = ($status === 'completed' || $status === 'approved') ? 100 : 0;
            $periods[] = [
                'period_number' => $periodNumber,
                'period_name' => $periodNumber, // Use period_number as name for demo
                'period_month' => $periodStart->format('Y-m'),
                'period_year' => $periodStart->format('Y'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'timekeeping_cutoff_date' => $periodEnd->toDateString(),
                'leave_cutoff_date' => $periodEnd->toDateString(),
                'adjustment_deadline' => $periodEnd->toDateString(),
                'payment_date' => $paymentDate->toDateString(),
                'status' => $status,
                'created_by' => $creatorId,
                'approved_by' => $status === 'approved' || $status === 'completed' ? $approverId : null,
                'approved_at' => $status === 'approved' || $status === 'completed' ? $paymentDate->copy()->subDays(3) : null,
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'excluded_employees' => $excludedEmployees,
                'total_net_pay' => ($status === 'completed' || $status === 'approved') ? $averageNetPay * $activeEmployees : 0.00,
                'progress_percentage' => $progress,
                'created_at' => $periodStart->copy()->subDays(2),
                'updated_at' => $paymentDate,
            ];

            // Second half
            $periodStart2 = $periodEnd->copy()->addDay();
            $periodEnd2 = $periodStart->copy()->endOfMonth();
            $paymentDate2 = $periodEnd2->copy()->addDays(2);
            $periodNumber2 = sprintf('%s-%02d-2H', $periodStart->year, $periodStart->month);
            // For March, 2H should be 'draft', others 'completed'
            if ($isMarch) {
                $status2 = 'draft';
            } else {
                $status2 = 'completed';
            }
            $progress2 = ($status2 === 'completed' || $status2 === 'approved') ? 100 : 0;
            $periods[] = [
                'period_number' => $periodNumber2,
                'period_name' => $periodNumber2, // Use period_number as name for demo
                'period_month' => $periodStart2->format('Y-m'),
                'period_year' => $periodStart2->format('Y'),
                'period_start' => $periodStart2->toDateString(),
                'period_end' => $periodEnd2->toDateString(),
                'timekeeping_cutoff_date' => $periodEnd2->toDateString(),
                'leave_cutoff_date' => $periodEnd2->toDateString(),
                'adjustment_deadline' => $periodEnd2->toDateString(),
                'payment_date' => $paymentDate2->toDateString(),
                'status' => $status2,
                'created_by' => $creatorId,
                'approved_by' => $status2 === 'approved' || $status2 === 'completed' ? $approverId : null,
                'approved_at' => $status2 === 'approved' || $status2 === 'completed' ? $paymentDate2->copy()->subDays(3) : null,
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'excluded_employees' => $excludedEmployees,
                'total_net_pay' => ($status2 === 'completed' || $status2 === 'approved') ? $averageNetPay * $activeEmployees : 0.00,
                'progress_percentage' => $progress2,
                'created_at' => $periodStart2->copy()->subDays(2),
                'updated_at' => $paymentDate2,
            ];
        }

        // Remove any existing periods with the same period_number (idempotent bulk delete)
        $periodNumbers = array_column($periods, 'period_number');
        PayrollPeriod::whereIn('period_number', $periodNumbers)->delete();

        PayrollPeriod::insert($periods);

        $this->command->info('✅ Seeded ' . count($periods) . ' payroll periods (6 months, semi-monthly)');
    }
}
