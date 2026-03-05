<?php

namespace App\Services\Payroll;

use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\PayrollPeriod;
use App\Models\EmployeePayrollCalculation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceDeductionService
{
    /**
     * Get pending deductions for employee in specific payroll period
     */
    public function getPendingDeductionsForEmployee(int $employeeId, int $payrollPeriodId): array
    {
        $deductions = AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('payroll_period_id', $payrollPeriodId)
            ->where('is_deducted', false)
            ->with(['cashAdvance', 'payrollPeriod'])
            ->orderBy('installment_number', 'asc')
            ->get();

        return $deductions->toArray();
    }

    /**
     * Get total pending deductions for employee
     */
    public function getTotalPendingDeductionsForEmployee(int $employeeId): float
    {
        return AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('is_deducted', false)
            ->sum('deduction_amount');
    }

    /**
     * Process advance deductions for employee payroll calculation
     * 
     * @param int $employeeId
     * @param int $payrollPeriodId
     * @param float $availableNetPay Net pay before advance deductions
     * @param int|null $employeePayrollCalculationId
     * @return array ['total_deduction', 'deductions_applied', 'insufficient_pay']
     */
    public function processDeductions(
        int $employeeId,
        int $payrollPeriodId,
        float $availableNetPay,
        ?int $employeePayrollCalculationId = null
    ): array {
        $pendingDeductions = AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('payroll_period_id', $payrollPeriodId)
            ->where('is_deducted', false)
            ->with('cashAdvance')
            ->orderBy('installment_number', 'asc')
            ->get();

        if ($pendingDeductions->isEmpty()) {
            return [
                'total_deduction' => 0,
                'deductions_applied' => 0,
                'insufficient_pay' => false,
            ];
        }

        $totalDeduction = 0;
        $deductionsApplied = 0;
        $insufficientPay = false;
        $skippedDeductions = [];

        DB::beginTransaction();
        try {
            foreach ($pendingDeductions as $deduction) {
                $advance = $deduction->cashAdvance;
                $deductionAmount = $deduction->deduction_amount;

                // Check if net pay is sufficient
                if ($availableNetPay < $deductionAmount) {
                    // Insufficient pay - handle based on amount available
                    $insufficientPay = true;
                    
                    if ($availableNetPay > 0) {
                        // Partial deduction possible
                        $deductionAmount = $availableNetPay;
                        $deduction->update([
                            'is_deducted' => true,
                            'deducted_at' => now(),
                            'deduction_amount' => $deductionAmount,
                            'employee_payroll_calculation_id' => $employeePayrollCalculationId,
                            'deduction_notes' => 'Partial deduction due to insufficient net pay - remaining balance rescheduled',
                        ]);

                        // Update advance balance
                        $this->updateAdvanceBalance($advance, $deductionAmount);

                        $totalDeduction += $deductionAmount;
                        $deductionsApplied++;
                        $availableNetPay = 0;

                        Log::warning("Partial advance deduction due to insufficient net pay", [
                            'advance_number' => $advance->advance_number,
                            'requested_deduction' => $deduction->deduction_amount,
                            'partial_deduction' => $deductionAmount,
                        ]);
                    } else {
                        // No net pay available - skip this deduction
                        $skippedDeductions[] = $deduction->id;
                        Log::warning("Advance deduction skipped - zero net pay", [
                            'advance_number' => $advance->advance_number,
                            'deduction_amount' => $deduction->deduction_amount,
                        ]);
                    }
                } else {
                    // Sufficient pay - apply full deduction
                    $deduction->update([
                        'is_deducted' => true,
                        'deducted_at' => now(),
                        'employee_payroll_calculation_id' => $employeePayrollCalculationId,
                    ]);

                    // Update advance balance
                    $this->updateAdvanceBalance($advance, $deductionAmount);

                    $totalDeduction += $deductionAmount;
                    $deductionsApplied++;
                    $availableNetPay -= $deductionAmount;
                }
            }

            DB::commit();

            return [
                'total_deduction' => $totalDeduction,
                'deductions_applied' => $deductionsApplied,
                'insufficient_pay' => $insufficientPay,
                'skipped_count' => count($skippedDeductions),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process advance deductions", [
                'employee_id' => $employeeId,
                'payroll_period_id' => $payrollPeriodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update advance balance after deduction
     * Also marks advance as completed if fully paid
     */
    private function updateAdvanceBalance(CashAdvance $advance, float $deductionAmount): void
    {
        $newTotalDeducted = $advance->total_deducted + $deductionAmount;
        $newRemainingBalance = $advance->amount_approved - $newTotalDeducted;
        $newInstallmentsCompleted = $advance->installments_completed + 1;

        $advance->update([
            'total_deducted' => $newTotalDeducted,
            'remaining_balance' => max(0, $newRemainingBalance),
            'installments_completed' => $newInstallmentsCompleted,
        ]);

        // Check if fully paid (allow 1 cent tolerance for rounding)
        if ($newRemainingBalance <= 0.01) {
            $advance->update([
                'deduction_status' => 'completed',
                'completion_reason' => 'fully_paid',
                'completed_at' => now(),
            ]);

            Log::info("Cash advance fully paid", [
                'advance_number' => $advance->advance_number,
                'total_deducted' => $newTotalDeducted,
                'remaining_balance' => $newRemainingBalance,
                'total_installments' => $advance->number_of_installments,
            ]);
        }
    }

    /**
     * Allow early repayment of advance
     */
    public function allowEarlyRepayment(CashAdvance $advance, float $repaymentAmount, $user = null): CashAdvance
    {
        if ($advance->deduction_status !== 'active') {
            throw new \Exception("Only active advances can be repaid. Current status: {$advance->deduction_status}");
        }

        if ($repaymentAmount <= 0) {
            throw new \Exception("Repayment amount must be greater than zero");
        }

        if ($repaymentAmount > $advance->remaining_balance) {
            throw new \Exception("Repayment amount (₱" . number_format($repaymentAmount, 2) 
                . ") exceeds remaining balance (₱" . number_format($advance->remaining_balance, 2) . ")");
        }

        DB::beginTransaction();
        try {
            // Update advance balance
            $this->updateAdvanceBalance($advance, $repaymentAmount);

            $advance = $advance->fresh();

            // Cancel pending deductions if fully paid
            if ($advance->deduction_status === 'completed') {
                $advance->advanceDeductions()
                    ->where('is_deducted', false)
                    ->delete();

                Log::info("Pending deductions cancelled - advance fully paid via early repayment", [
                    'advance_number' => $advance->advance_number,
                ]);
            }

            DB::commit();

            Log::info("Early repayment made", [
                'advance_number' => $advance->advance_number,
                'repayment_amount' => $repaymentAmount,
                'remaining_balance' => $advance->fresh()->remaining_balance,
                'processed_by' => $user ? $user->name : 'system',
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process early repayment", [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reschedule skipped deductions to next available payroll period
     */
    public function rescheduleSkippedDeductions(int $employeeId, int $currentPayrollPeriodId): int
    {
        $skippedDeductions = AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('payroll_period_id', $currentPayrollPeriodId)
            ->where('is_deducted', false)
            ->get();

        $rescheduledCount = 0;

        foreach ($skippedDeductions as $deduction) {
            // Find next available payroll period
            $nextPeriod = PayrollPeriod::where('pay_date', '>', now()->toDateString())
                ->orderBy('pay_date', 'asc')
                ->first();

            if ($nextPeriod) {
                $deduction->update([
                    'payroll_period_id' => $nextPeriod->id,
                ]);
                $rescheduledCount++;

                Log::info("Advance deduction rescheduled", [
                    'advance_number' => $deduction->cashAdvance->advance_number,
                    'from_period_id' => $currentPayrollPeriodId,
                    'to_period_id' => $nextPeriod->id,
                ]);
            }
        }

        return $rescheduledCount;
    }
}
