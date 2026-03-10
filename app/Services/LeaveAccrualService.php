<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveAccrual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * Initialize leave balances for an employee for a given year.
     */
    public function initializeBalances(Employee $employee, int $year): void
    {
        $policies = LeavePolicy::where('is_active', true)->get();

        foreach ($policies as $policy) {
            LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'year' => $year,
                ],
                [
                    'earned' => 0,
                    'used' => 0,
                    'carried_forward' => 0,
                    'forfeited' => 0,
                ]
            );
        }
    }

    /**
     * Accrue leave for an employee based on policy.
     */
    public function accrueLeave(Employee $employee, LeavePolicy $policy, Carbon $accrualDate): float
    {
        $year = $accrualDate->year;
        
        // Get or create balance
        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_policy_id' => $policy->id,
                'year' => $year,
            ],
            [
                'earned' => 0,
                'used' => 0,
                'carried_forward' => 0,
                'forfeited' => 0,
            ]
        );

        // Calculate accrual amount based on policy
        $accrualAmount = $this->calculateAccrualAmount($employee, $policy, $accrualDate);

        // Create accrual record
        DB::transaction(function () use ($balance, $accrualAmount, $accrualDate) {
            LeaveAccrual::create([
                'leave_balance_id' => $balance->id,
                'accrual_date' => $accrualDate,
                'amount' => $accrualAmount,
                'accrual_type' => 'monthly',
                'reason' => 'Automatic monthly accrual',
                'processed_by' => null,
            ]);

            $balance->increment('earned', $accrualAmount);
            $balance->update(['last_accrued_at' => $accrualDate]);
        });

        return $accrualAmount;
    }

    /**
     * Calculate accrual amount for a given period.
     */
    protected function calculateAccrualAmount(Employee $employee, LeavePolicy $policy, Carbon $date): float
    {
        // Annual entitlement / 12 months
        $monthlyAccrual = $policy->annual_entitlement / 12;

        // Check if employee is eligible
        if ($this->isEmployeeEligible($employee, $policy, $date)) {
            return round($monthlyAccrual, 2);
        }

        return 0.0;
    }

    /**
     * Check if employee is eligible for leave accrual.
     */
    protected function isEmployeeEligible(Employee $employee, LeavePolicy $policy, Carbon $date): bool
    {
        // Always eligible if no hire date
        if (!$employee->date_hired) {
            return true;
        }

        // All employees are eligible from hire date onwards
        $hireDate = Carbon::parse($employee->date_hired);
        
        if ($date->lt($hireDate)) {
            return false;
        }

        return true;
    }

    /**
     * Process monthly accrual for all active employees.
     */
    public function processMonthlyAccrualForAllEmployees(Carbon $date): array
    {
        $employees = Employee::where('status', 'active')
            ->with('profile')
            ->get();
        $policies = LeavePolicy::where('is_active', true)->get();
        
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                try {
                    $this->accrueLeave($employee, $policy, $date);
                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'employee_id' => $employee->id,
                        'policy_id' => $policy->id,
                        'error' => $e->getMessage(),
                    ];
                    $results['skipped']++;
                }
            }
        }

        return $results;
    }

    /**
     * Carry forward unused leave to next year.
     */
    public function carryForwardLeave(Employee $employee, int $fromYear, int $toYear): void
    {
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $fromYear)
            ->with('leavePolicy.carryForwardRule')
            ->get();

        foreach ($balances as $balance) {
            $rule = $balance->leavePolicy->carryForwardRule;
            
            if (!$rule || !$rule->is_active) {
                continue;
            }

            $remaining = $balance->remaining;
            $carryForward = min($remaining, $rule->max_carry_forward_days);

            if ($carryForward > 0) {
                $nextYearBalance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_policy_id' => $balance->leave_policy_id,
                        'year' => $toYear,
                    ],
                    [
                        'earned' => 0,
                        'used' => 0,
                        'carried_forward' => 0,
                        'forfeited' => 0,
                    ]
                );

                $nextYearBalance->update([
                    'carried_forward' => $carryForward,
                ]);

                // Record accrual
                LeaveAccrual::create([
                    'leave_balance_id' => $nextYearBalance->id,
                    'accrual_date' => Carbon::create($toYear, 1, 1),
                    'amount' => $carryForward,
                    'accrual_type' => 'carried_forward',
                    'reason' => "Carried forward from {$fromYear}",
                    'processed_by' => null,
                ]);
            }
        }
    }

    /**
     * Deduct used leave when request is approved.
     */
    public function deductLeave(Employee $employee, LeavePolicy $policy, float $days, Carbon $date): void
    {
        $year = $date->year;
        
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            throw new \Exception("No leave balance found for employee {$employee->id} in year {$year}");
        }

        if ($balance->remaining < $days) {
            throw new \Exception("Insufficient leave balance. Available: {$balance->remaining}, Required: {$days}");
        }

        $balance->increment('used', $days);
    }

    /**
     * Restore leave balance when a leave request is cancelled.
     * 
     * @param Employee $employee
     * @param LeavePolicy $policy
     * @param float $days
     * @param Carbon $date
     * @throws \Exception
     */
    public function restoreLeave(Employee $employee, LeavePolicy $policy, float $days, Carbon $date): void
    {
        $year = $date->year;
        
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            throw new \Exception("No leave balance found for employee {$employee->id} in year {$year}");
        }

        // Decrement used and restore to available
        // Ensure used never goes below 0
        if ($balance->used >= $days) {
            $balance->decrement('used', $days);
        } elseif ($balance->used > 0) {
            // Only restore what was actually deducted
            $balance->update(['used' => 0]);
        }
    }
}
