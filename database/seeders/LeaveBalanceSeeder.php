<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Services\LeaveAccrualService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LeaveBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds leave balances for all active employees for the current year,
     * simulating monthly accruals for months that have elapsed so far.
     */
    public function run(LeaveAccrualService $service): void
    {
        $this->command->info('Starting Leave Balance seeding...');

        $currentYear = now()->year;
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found. Skipping leave balance seeding.');
            return;
        }

        $this->command->info("Found {$employees->count()} active employees. Seeding leave balances...");

        $totalBalancesCreated = 0;
        $totalAccrualsCreated = 0;

        foreach ($employees as $employee) {
            try {
                // Initialize current year balances for all policies
                $this->command->line("  → Initializing balances for {$employee->employee_number}");
                $service->initializeBalances($employee, $currentYear);

                $policies = LeavePolicy::where('is_active', true)->get();
                $totalBalancesCreated += $policies->count();

                // Simulate accruals for months that have already elapsed
                $monthsElapsed = now()->month;
                for ($month = 1; $month <= $monthsElapsed; $month++) {
                    $accrualDate = Carbon::create($currentYear, $month, 1);

                    foreach ($policies as $policy) {
                        $service->accrueLeave($employee, $policy, $accrualDate);
                        $totalAccrualsCreated++;
                    }
                }
            } catch (\Exception $e) {
                $this->command->error("Failed to seed balances for employee {$employee->employee_number}: {$e->getMessage()}");
                continue;
            }
        }

        $this->command->info("✓ Leave balances seeded successfully!");
        $this->command->info("  • Total leave balances created: {$totalBalancesCreated}");
        $this->command->info("  • Total accrual entries created: {$totalAccrualsCreated}");
    }
}
