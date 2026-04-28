<?php

namespace App\Services\HR\Leave;

use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave Balance Service
 * 
 * Handles leave balance operations:
 * - Balance sufficiency checks
 * - Balance deduction after approval
 * - Balance restoration after cancellation
 */
class LeaveBalanceService
{
    /**
     * Check if employee has sufficient balance for leave
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @param float $requestedDays
     * @return bool
     */
    public function hasSufficientBalance(int $employeeId, int $leavePolicyId, float $requestedDays): bool
    {
        $balance = $this->getBalance($employeeId, $leavePolicyId);
        if (!$balance) {
            return false;
        }
        // Use the computed remaining attribute (earned + carried_forward - used)
        return $balance->remaining >= $requestedDays;
    }

    /**
     * Deduct balance after leave approval
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @param float $days
     * @param string|null $reason
     * @return bool
     */
    public function deductBalance(int $employeeId, int $leavePolicyId, float $days, ?string $reason = null): bool
    {
        try {
            DB::transaction(function () use ($employeeId, $leavePolicyId, $days, $reason) {
                $balance = $this->getBalance($employeeId, $leavePolicyId);
                if (!$balance) {
                    // Try to auto-create the balance using the policy's annual entitlement
                    $policy = LeavePolicy::find($leavePolicyId);
                    $entitlement = $policy ? ($policy->annual_entitlement ?? 0) : 0;
                    $balance = $this->createBalance($employeeId, $leavePolicyId, $entitlement);
                }
                if ($balance->remaining < $days) {
                    throw new \Exception("Insufficient leave balance. Available: {$balance->remaining}, Requested: {$days}");
                }
                // Increment used, do not touch earned/carried_forward here
                $balance->update([
                    'used' => DB::raw("used + {$days}")
                ]);
                Log::info('Leave balance deducted', [
                    'employee_id' => $employeeId,
                    'leave_policy_id' => $leavePolicyId,
                    'days_deducted' => $days,
                    'remaining_balance' => $balance->fresh()->remaining,
                    'reason' => $reason ?? 'Leave approved'
                ]);
            });
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to deduct leave balance', [
                'employee_id' => $employeeId,
                'leave_policy_id' => $leavePolicyId,
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore balance after leave cancellation
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @param float $days
     * @param string|null $reason
     * @return bool
     */
    public function restoreBalance(int $employeeId, int $leavePolicyId, float $days, ?string $reason = null): bool
    {
        try {
            DB::transaction(function () use ($employeeId, $leavePolicyId, $days, $reason) {
                $balance = $this->getBalance($employeeId, $leavePolicyId);
                if (!$balance) {
                    throw new \Exception("Leave balance not found for employee {$employeeId} and policy {$leavePolicyId}");
                }
                // Decrement used, do not touch earned/carried_forward here
                $balance->update([
                    'used' => DB::raw("GREATEST(used - {$days}, 0)")
                ]);
                Log::info('Leave balance restored', [
                    'employee_id' => $employeeId,
                    'leave_policy_id' => $leavePolicyId,
                    'days_restored' => $days,
                    'new_balance' => $balance->fresh()->remaining,
                    'reason' => $reason ?? 'Leave cancelled'
                ]);
            });
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to restore leave balance', [
                'employee_id' => $employeeId,
                'leave_policy_id' => $leavePolicyId,
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get available balance for employee and leave policy
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @return float
     */
    public function getAvailableBalance(int $employeeId, int $leavePolicyId): float
    {
        $balance = $this->getBalance($employeeId, $leavePolicyId);
        return $balance ? $balance->remaining : 0;
    }

    /**
     * Get used balance for employee and leave policy
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @return float
     */
    public function getUsedBalance(int $employeeId, int $leavePolicyId): float
    {
        $balance = $this->getBalance($employeeId, $leavePolicyId);
        return $balance ? $balance->used : 0;
    }

    /**
     * Get total allocated balance for employee and leave policy
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @return float
     */
    public function getTotalBalance(int $employeeId, int $leavePolicyId): float
    {
        $balance = $this->getBalance($employeeId, $leavePolicyId);
        return $balance ? ($balance->earned + $balance->carried_forward) : 0;
    }

    /**
     * Check if balance exists for employee and leave policy
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @return bool
     */
    public function balanceExists(int $employeeId, int $leavePolicyId): bool
    {
        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_policy_id', $leavePolicyId)
            ->exists();
    }

    /**
     * Create initial balance for employee
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @param float $totalDays
     * @return LeaveBalance
     */
    public function createBalance(int $employeeId, int $leavePolicyId, float $totalDays): LeaveBalance
    {
        return LeaveBalance::create([
            'employee_id' => $employeeId,
            'leave_policy_id' => $leavePolicyId,
            'earned' => $totalDays,
            'used' => 0,
            'carried_forward' => 0,
            'year' => now()->year,
        ]);
    }

    /**
     * Get leave balance record
     * 
     * @param int $employeeId
     * @param int $leavePolicyId
     * @return LeaveBalance|null
     */
    protected function getBalance(int $employeeId, int $leavePolicyId): ?LeaveBalance
    {
        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_policy_id', $leavePolicyId)
            ->where('year', now()->year)
            ->first();
    }

    /**
     * Get all balances for employee
     * 
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEmployeeBalances(int $employeeId)
    {
        return LeaveBalance::where('employee_id', $employeeId)
            ->where('year', now()->year)
            ->with('leavePolicy')
            ->get();
    }

    /**
     * Reset all balances for new year (called from scheduler/command)
     * 
     * @param int $year
     * @return int Number of balances created
     */
    public function resetBalancesForNewYear(int $year): int
    {
        $count = 0;

        // Get all active leave policies with employees
        $policies = LeavePolicy::with('employees')->get();

        DB::transaction(function () use ($policies, $year, &$count) {
            foreach ($policies as $policy) {
                foreach ($policy->employees as $employee) {
                    // Check if balance already exists for this year
                    $exists = LeaveBalance::where('employee_id', $employee->id)
                        ->where('leave_policy_id', $policy->id)
                        ->where('year', $year)
                        ->exists();

                    if (!$exists) {
                        LeaveBalance::create([
                            'employee_id' => $employee->id,
                            'leave_policy_id' => $policy->id,
                            'total_days' => $policy->days_per_year,
                            'used_days' => 0,
                            'available_days' => $policy->days_per_year,
                            'year' => $year,
                        ]);

                        $count++;
                    }
                }
            }
        });

        Log::info("Created {$count} leave balances for year {$year}");

        return $count;
    }
}
