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
                $this->command->line("  → Initializing balances for {$employee->employee_number}");
                $policies = LeavePolicy::where('is_active', true)->get();
                foreach ($policies as $policy) {
                    $balance = \App\Models\LeaveBalance::where([
                        'employee_id' => $employee->id,
                        'leave_policy_id' => $policy->id,
                        'year' => $currentYear,
                    ])->first();
                    if ($balance) {
                        // Only update earned, carried_forward, forfeited; never reset used
                        $balance->update([
                            'earned' => $policy->annual_entitlement,
                            'carried_forward' => 0,
                            'forfeited' => 0,
                            // Always update the physical remaining column to match the computed value
                            'remaining' => $policy->annual_entitlement + 0 - $balance->used,
                        ]);
                    } else {
                        // Create new balance with used = 0
                        \App\Models\LeaveBalance::create([
                            'employee_id' => $employee->id,
                            'leave_policy_id' => $policy->id,
                            'year' => $currentYear,
                            'earned' => $policy->annual_entitlement,
                            'used' => 0,
                            'carried_forward' => 0,
                            'forfeited' => 0,
                            'remaining' => $policy->annual_entitlement,
                        ]);
                    }
                }
                $totalBalancesCreated += $policies->count();
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
