<?php

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Services\HR\Workforce\WorkforceCoverageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave Management Service
 * 
 * Handles leave policy management, balance calculations, and synchronization.
 * This service is called when leave policies are created or updated to ensure
 * all employees have proper leave balance records.
 */
class LeaveManagementService
{
    protected WorkforceCoverageService $coverageService;

    public function __construct(WorkforceCoverageService $coverageService)
    {
        $this->coverageService = $coverageService;
    }

    /**
     * Create a new leave policy and initialize balances for all eligible employees.
     * 
     * This method is called after a leave policy is created to automatically
     * set up leave balances for all active employees.
     * 
     * @param LeavePolicy $policy The newly created leave policy
     * @return array Summary of balance creation
     */
    public function initializeBalancesForNewPolicy(LeavePolicy $policy): array
    {
        DB::beginTransaction();

        try {
            $currentYear = now()->year;
            $employees = Employee::where('status', 'active')->get();
            $balancesCreated = 0;
            $balancesSkipped = 0;

            foreach ($employees as $employee) {
                // Check if balance already exists for this employee, policy, and year
                $existingBalance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_policy_id', $policy->id)
                    ->where('year', $currentYear)
                    ->first();

                if ($existingBalance) {
                    $balancesSkipped++;
                    continue;
                }

                // Calculate prorated entitlement based on hire date
                $entitlement = $this->calculateProratedEntitlement(
                    $policy->annual_entitlement,
                    $employee->date_hired,
                    $currentYear
                );

                // Create balance record
                LeaveBalance::create([
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'year' => $currentYear,
                    'earned' => $entitlement,
                    'used' => 0,
                    'remaining' => $entitlement,
                    'carried_forward' => 0,
                ]);

                $balancesCreated++;
            }

            DB::commit();

            Log::info('Leave balances initialized for new policy', [
                'policy_id' => $policy->id,
                'policy_code' => $policy->code,
                'balances_created' => $balancesCreated,
                'balances_skipped' => $balancesSkipped,
            ]);

            return [
                'success' => true,
                'policy_id' => $policy->id,
                'policy_code' => $policy->code,
                'balances_created' => $balancesCreated,
                'balances_skipped' => $balancesSkipped,
                'total_employees' => $employees->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to initialize balances for new policy', [
                'policy_id' => $policy->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update existing employee balances when a policy's entitlement changes.
     * 
     * This method recalculates and updates leave balances for all employees
     * when the annual entitlement of a leave policy is modified.
     * 
     * @param LeavePolicy $policy The updated leave policy
     * @param float $oldEntitlement The previous annual entitlement value
     * @return array Summary of balance updates
     */
    public function recalculateBalancesForUpdatedPolicy(LeavePolicy $policy, float $oldEntitlement): array
    {
        DB::beginTransaction();

        try {
            $currentYear = now()->year;
            $balances = LeaveBalance::where('leave_policy_id', $policy->id)
                ->where('year', $currentYear)
                ->whereHas('employee', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            $balancesUpdated = 0;
            $entitlementDifference = $policy->annual_entitlement - $oldEntitlement;

            foreach ($balances as $balance) {
                // Calculate prorated difference based on hire date
                $employee = $balance->employee;
                $proratedDifference = $this->calculateProratedEntitlement(
                    $entitlementDifference,
                    $employee->date_hired,
                    $currentYear
                );

                // Update earned and remaining balances
                $balance->earned = $balance->earned + $proratedDifference;
                $balance->remaining = $balance->remaining + $proratedDifference;
                $balance->save();

                $balancesUpdated++;
            }

            DB::commit();

            Log::info('Leave balances recalculated for updated policy', [
                'policy_id' => $policy->id,
                'policy_code' => $policy->code,
                'old_entitlement' => $oldEntitlement,
                'new_entitlement' => $policy->annual_entitlement,
                'balances_updated' => $balancesUpdated,
            ]);

            return [
                'success' => true,
                'policy_id' => $policy->id,
                'policy_code' => $policy->code,
                'balances_updated' => $balancesUpdated,
                'entitlement_difference' => $entitlementDifference,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to recalculate balances for updated policy', [
                'policy_id' => $policy->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync leave balances for all employees.
     * 
     * This method ensures that all active employees have leave balance records
     * for all active leave policies for the current year. Useful for:
     * - New employees who were added after policy creation
     * - Data integrity checks
     * - Annual balance initialization
     * 
     * @param int|null $policyId Optional: Sync only for a specific policy
     * @return array Summary of synchronization
     */
    public function syncEmployeeBalances(?int $policyId = null): array
    {
        DB::beginTransaction();

        try {
            $currentYear = now()->year;
            $balancesCreated = 0;
            $balancesSkipped = 0;

            // Get active policies
            $policiesQuery = LeavePolicy::where('is_active', true);
            if ($policyId) {
                $policiesQuery->where('id', $policyId);
            }
            $policies = $policiesQuery->get();

            // Get active employees
            $employees = Employee::where('status', 'active')->get();

            foreach ($policies as $policy) {
                foreach ($employees as $employee) {
                    // Check if balance already exists
                    $existingBalance = LeaveBalance::where('employee_id', $employee->id)
                        ->where('leave_policy_id', $policy->id)
                        ->where('year', $currentYear)
                        ->first();

                    if ($existingBalance) {
                        $balancesSkipped++;
                        continue;
                    }

                    // Calculate prorated entitlement
                    $entitlement = $this->calculateProratedEntitlement(
                        $policy->annual_entitlement,
                        $employee->date_hired,
                        $currentYear
                    );

                    // Create balance record
                    LeaveBalance::create([
                        'employee_id' => $employee->id,
                        'leave_policy_id' => $policy->id,
                        'year' => $currentYear,
                        'earned' => $entitlement,
                        'used' => 0,
                        'remaining' => $entitlement,
                        'carried_forward' => 0,
                    ]);

                    $balancesCreated++;
                }
            }

            DB::commit();

            Log::info('Employee leave balances synchronized', [
                'policy_id' => $policyId,
                'balances_created' => $balancesCreated,
                'balances_skipped' => $balancesSkipped,
                'total_policies' => $policies->count(),
                'total_employees' => $employees->count(),
            ]);

            return [
                'success' => true,
                'balances_created' => $balancesCreated,
                'balances_skipped' => $balancesSkipped,
                'total_policies' => $policies->count(),
                'total_employees' => $employees->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to sync employee leave balances', [
                'policy_id' => $policyId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate prorated leave entitlement based on hire date.
     * 
     * For employees hired mid-year, their leave entitlement is prorated
     * based on the number of months remaining in the year.
     * 
     * @param float $annualEntitlement Full year entitlement
     * @param Carbon $hireDate Employee hire date
     * @param int $year Target year for calculation
     * @return float Prorated entitlement
     */
    protected function calculateProratedEntitlement(float $annualEntitlement, Carbon $hireDate, int $year): float
    {
        $yearStart = Carbon::create($year, 1, 1);
        $yearEnd = Carbon::create($year, 12, 31);

        // If hired before the target year, give full entitlement
        if ($hireDate->year < $year) {
            return round($annualEntitlement, 2);
        }

        // If hired after the target year, no entitlement
        if ($hireDate->year > $year) {
            return 0;
        }

        // Calculate remaining months in the year (including hire month)
        $monthsRemaining = 12 - $hireDate->month + 1;
        
        // Calculate monthly entitlement
        $monthlyEntitlement = $annualEntitlement / 12;
        
        // Calculate prorated amount
        $proratedAmount = $monthlyEntitlement * $monthsRemaining;

        return round($proratedAmount, 2);
    }

    /**
     * Calculate monthly accrual for a specific employee and policy.
     * 
     * This method is used by the monthly accrual job to calculate
     * how much leave credit should be added to an employee's balance.
     * 
     * @param Employee $employee The employee
     * @param LeavePolicy $policy The leave policy
     * @return float Monthly accrual amount
     */
    public function calculateMonthlyAccrual(Employee $employee, LeavePolicy $policy): float
    {
        // Monthly accrual is annual entitlement divided by 12
        $monthlyAccrual = $policy->annual_entitlement / 12;

        // For employees hired mid-month, prorate the first month
        if ($employee->date_hired->isCurrentMonth()) {
            $daysInMonth = $employee->date_hired->daysInMonth;
            $daysRemaining = $daysInMonth - $employee->date_hired->day + 1;
            $monthlyAccrual = ($monthlyAccrual / $daysInMonth) * $daysRemaining;
        }

        return round($monthlyAccrual, 2);
    }

    /**
     * Manually adjust an employee's leave balance.
     * 
     * This method is used by HR to manually adjust leave balances
     * for corrections, special grants, or other administrative reasons.
     * 
     * @param int $balanceId The leave balance ID
     * @param float $adjustmentAmount The adjustment amount (positive or negative)
     * @param string $reason The reason for adjustment
     * @param int $adjustedBy The user ID who made the adjustment
     * @return LeaveBalance Updated leave balance
     */
    public function adjustBalance(int $balanceId, float $adjustmentAmount, string $reason, int $adjustedBy): LeaveBalance
    {
        DB::beginTransaction();

        try {
            $balance = LeaveBalance::findOrFail($balanceId);
            
            // Update balance
            $balance->earned = $balance->earned + $adjustmentAmount;
            $balance->remaining = $balance->remaining + $adjustmentAmount;
            $balance->save();

            // Log the adjustment (if you have a leave_balance_adjustments table)
            // For now, we'll use activity log
            activity('leave_balance_adjustment')
                ->causedBy($adjustedBy)
                ->performedOn($balance)
                ->withProperties([
                    'employee_id' => $balance->employee_id,
                    'leave_policy_id' => $balance->leave_policy_id,
                    'adjustment_amount' => $adjustmentAmount,
                    'reason' => $reason,
                    'old_earned' => $balance->earned - $adjustmentAmount,
                    'new_earned' => $balance->earned,
                    'old_remaining' => $balance->remaining - $adjustmentAmount,
                    'new_remaining' => $balance->remaining,
                ])
                ->log('Leave balance adjusted manually');

            DB::commit();

            Log::info('Leave balance adjusted manually', [
                'balance_id' => $balanceId,
                'employee_id' => $balance->employee_id,
                'adjustment_amount' => $adjustmentAmount,
                'reason' => $reason,
                'adjusted_by' => $adjustedBy,
            ]);

            return $balance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to adjust leave balance', [
                'balance_id' => $balanceId,
                'adjustment_amount' => $adjustmentAmount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process monthly leave accrual for all active employees.
     * 
     * This method is called by a scheduled job (monthly) to add leave credits
     * to all active employees based on their leave policies.
     * 
     * @return array Summary of accrual processing
     */
    public function processMonthlyAccrual(): array
    {
        DB::beginTransaction();

        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $balancesUpdated = 0;
            $balancesCreated = 0;
            $errors = [];

            // Get all active employees
            $employees = Employee::where('status', 'active')->get();

            // Get all active leave policies
            $policies = LeavePolicy::where('is_active', true)->get();

            foreach ($employees as $employee) {
                foreach ($policies as $policy) {
                    try {
                        // Find or create balance record for current year
                        $balance = LeaveBalance::firstOrCreate(
                            [
                                'employee_id' => $employee->id,
                                'leave_policy_id' => $policy->id,
                                'year' => $currentYear,
                            ],
                            [
                                'earned' => 0,
                                'used' => 0,
                                'remaining' => 0,
                                'carried_forward' => 0,
                            ]
                        );

                        if ($balance->wasRecentlyCreated) {
                            $balancesCreated++;
                        }

                        // Calculate monthly accrual
                        $accrualAmount = $this->calculateMonthlyAccrual($employee, $policy);

                        // Update balance
                        $balance->earned += $accrualAmount;
                        $balance->remaining += $accrualAmount;
                        $balance->save();

                        $balancesUpdated++;

                        // Log accrual
                        activity('leave_accrual_processed')
                            ->performedOn($balance)
                            ->withProperties([
                                'employee_id' => $employee->id,
                                'leave_policy_id' => $policy->id,
                                'accrual_amount' => $accrualAmount,
                                'year' => $currentYear,
                                'month' => $currentMonth,
                                'new_earned' => $balance->earned,
                                'new_remaining' => $balance->remaining,
                            ])
                            ->log('Monthly leave accrual processed');
                    } catch (\Exception $e) {
                        $errors[] = [
                            'employee_id' => $employee->id,
                            'policy_id' => $policy->id,
                            'error' => $e->getMessage(),
                        ];
                        
                        Log::error('Failed to process accrual for employee', [
                            'employee_id' => $employee->id,
                            'policy_id' => $policy->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Monthly leave accrual processed', [
                'year' => $currentYear,
                'month' => $currentMonth,
                'employees_processed' => $employees->count(),
                'policies_processed' => $policies->count(),
                'balances_updated' => $balancesUpdated,
                'balances_created' => $balancesCreated,
                'errors' => count($errors),
            ]);

            return [
                'success' => true,
                'year' => $currentYear,
                'month' => $currentMonth,
                'employees_processed' => $employees->count(),
                'policies_processed' => $policies->count(),
                'balances_updated' => $balancesUpdated,
                'balances_created' => $balancesCreated,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process monthly leave accrual', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process year-end carryover for all employees.
     * 
     * This method is called at the end of each year to carry forward
     * unused leave balances to the next year based on policy rules.
     * 
     * @param int $year The year to process carryover for
     * @return array Summary of carryover processing
     */
    public function processYearEndCarryover(int $year): array
    {
        DB::beginTransaction();

        try {
            $nextYear = $year + 1;
            $balancesProcessed = 0;
            $balancesCreated = 0;
            $totalCarriedForward = 0;

            // Get all balances for the specified year
            $balances = LeaveBalance::with(['employee', 'leavePolicy'])
                ->where('year', $year)
                ->whereHas('employee', function ($query) {
                    $query->where('status', 'active');
                })
                ->whereHas('leavePolicy', function ($query) {
                    $query->where('can_carry_forward', true);
                })
                ->get();

            foreach ($balances as $balance) {
                $policy = $balance->leavePolicy;

                // Skip if no remaining balance
                if ($balance->remaining <= 0) {
                    continue;
                }

                // Calculate carryover amount (limited by max_carryover)
                $carryoverAmount = min($balance->remaining, $policy->max_carryover);

                // Create or update balance for next year
                $nextYearBalance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $balance->employee_id,
                        'leave_policy_id' => $balance->leave_policy_id,
                        'year' => $nextYear,
                    ],
                    [
                        'earned' => 0,
                        'used' => 0,
                        'remaining' => 0,
                        'carried_forward' => 0,
                    ]
                );

                if ($nextYearBalance->wasRecentlyCreated) {
                    $balancesCreated++;
                }

                // Add carryover to next year
                $nextYearBalance->carried_forward = $carryoverAmount;
                $nextYearBalance->earned += $carryoverAmount;
                $nextYearBalance->remaining += $carryoverAmount;
                $nextYearBalance->save();

                $balancesProcessed++;
                $totalCarriedForward += $carryoverAmount;

                // Log carryover
                activity('leave_carryover_processed')
                    ->performedOn($nextYearBalance)
                    ->withProperties([
                        'employee_id' => $balance->employee_id,
                        'leave_policy_id' => $balance->leave_policy_id,
                        'from_year' => $year,
                        'to_year' => $nextYear,
                        'carryover_amount' => $carryoverAmount,
                        'previous_remaining' => $balance->remaining,
                        'max_carryover' => $policy->max_carryover,
                    ])
                    ->log('Year-end leave carryover processed');
            }

            DB::commit();

            Log::info('Year-end leave carryover processed', [
                'from_year' => $year,
                'to_year' => $nextYear,
                'balances_processed' => $balancesProcessed,
                'balances_created' => $balancesCreated,
                'total_carried_forward' => $totalCarriedForward,
            ]);

            return [
                'success' => true,
                'from_year' => $year,
                'to_year' => $nextYear,
                'balances_processed' => $balancesProcessed,
                'balances_created' => $balancesCreated,
                'total_carried_forward' => $totalCarriedForward,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process year-end carryover', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
