<?php

namespace App\Services\Payroll;

use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\Employee;
use App\Models\User;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceManagementService
{
    /**
     * Create a new advance request
     */
    public function createAdvanceRequest(array $data, User $requestor): CashAdvance
    {
        // Validate eligibility
        $employee = Employee::findOrFail($data['employee_id']);
        $eligibility = $this->checkEmployeeEligibility($employee);
        
        if (!$eligibility['eligible']) {
            throw new \Exception($eligibility['reason']);
        }

        // Validate amount
        $maxAmount = $this->calculateMaxAdvanceAmount($employee);
        if ($data['amount_requested'] > $maxAmount) {
            throw new \Exception("Requested amount exceeds maximum allowed advance of ₱" . number_format($maxAmount, 2));
        }

        // Generate advance number
        $advanceNumber = $this->generateAdvanceNumber();

        $advance = CashAdvance::create([
            'advance_number' => $advanceNumber,
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'advance_type' => $data['advance_type'] ?? 'cash_advance',
            'amount_requested' => $data['amount_requested'],
            'purpose' => $data['purpose'],
            'requested_date' => $data['requested_date'] ?? now()->toDateString(),
            'priority_level' => $data['priority_level'] ?? 'normal',
            'supporting_documents' => $data['supporting_documents'] ?? [],
            'approval_status' => 'pending',
            'deduction_status' => 'pending',
            'created_by' => $requestor->id,
        ]);

        Log::info("Cash advance request created", [
            'advance_number' => $advanceNumber,
            'employee_id' => $employee->id,
            'amount' => $data['amount_requested'],
            'created_by' => $requestor->id,
        ]);

        return $advance;
    }

    /**
     * Approve cash advance and schedule deductions
     */
    public function approveAdvance(CashAdvance $advance, array $approvalData, User $approver): CashAdvance
    {
        DB::beginTransaction();
        try {
            // Update advance with approval details
            $advance->update([
                'approval_status' => 'approved',
                'amount_approved' => $approvalData['amount_approved'],
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $approvalData['approval_notes'] ?? null,
                'deduction_status' => 'active',
                'deduction_schedule' => $approvalData['deduction_schedule'] ?? 'installments',
                'number_of_installments' => $approvalData['number_of_installments'] ?? 1,
                'remaining_balance' => $approvalData['amount_approved'],
                'updated_by' => $approver->id,
            ]);

            // Recalculate deduction amount per period
            $advance->deduction_amount_per_period = $advance->amount_approved / $advance->number_of_installments;
            $advance->save();

            // Schedule deductions for future payroll periods
            $this->scheduleDeductions($advance);

            DB::commit();

            Log::info("Cash advance approved", [
                'advance_number' => $advance->advance_number,
                'approved_amount' => $approvalData['amount_approved'],
                'installments' => $advance->number_of_installments,
                'approver' => $approver->name,
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to approve cash advance", [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject cash advance
     */
    public function rejectAdvance(CashAdvance $advance, string $reason, User $rejector): CashAdvance
    {
        $advance->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'deduction_status' => 'cancelled',
            'updated_by' => $rejector->id,
        ]);

        Log::info("Cash advance rejected", [
            'advance_number' => $advance->advance_number,
            'reason' => $reason,
            'rejector' => $rejector->name,
        ]);

        return $advance;
    }

    /**
     * Cancel cash advance (before or after approval)
     */
    public function cancelAdvance(CashAdvance $advance, string $reason, User $canceller): CashAdvance
    {
        DB::beginTransaction();
        try {
            $advance->update([
                'deduction_status' => 'cancelled',
                'completion_reason' => 'cancelled',
                'completed_at' => now(),
                'updated_by' => $canceller->id,
            ]);

            // Cancel pending deductions
            $advance->advanceDeductions()
                ->where('is_deducted', false)
                ->delete();

            DB::commit();

            Log::info("Cash advance cancelled", [
                'advance_number' => $advance->advance_number,
                'reason' => $reason,
                'cancelled_by' => $canceller->name,
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to cancel cash advance", [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if employee is eligible for cash advance
     * 
     * Eligibility Rules:
     * 1. Employee must be active
     * 2. Employee must be regular/permanent (not probationary)
     * 3. Minimum 3 months employment
     * 4. Only 1 active advance allowed at a time
     */
    public function checkEmployeeEligibility(Employee $employee): array
    {
        // Rule 1: Employee must be active
        if ($employee->status !== 'active') {
            return [
                'eligible' => false,
                'reason' => 'Employee is not actively employed. Only active employees can request advances.'
            ];
        }

        // Rule 2: Employee must be regular/permanent (not probationary)
        if (strtolower($employee->employment_type) === 'probationary') {
            return [
                'eligible' => false,
                'reason' => 'Probationary employees are not eligible for cash advances.'
            ];
        }

        // Rule 3: Minimum 3 months employment
        if ($employee->date_hired) {
            $employmentMonths = $employee->date_hired->diffInMonths(now());
            if ($employmentMonths < 3) {
                return [
                    'eligible' => false,
                    'reason' => 'Employee must be employed for at least 3 months. (' . $employmentMonths . ' months employed)'
                ];
            }
        }

        // Rule 4: No active advances
        $activeAdvances = $employee->cashAdvances()
            ->where('deduction_status', 'active')
            ->count();

        if ($activeAdvances > 0) {
            return [
                'eligible' => false,
                'reason' => 'Employee already has an active advance. Only 1 active advance is allowed at a time.'
            ];
        }

        // All checks passed
        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Calculate maximum advance amount for employee
     * Max advance = 50% of monthly basic salary
     */
    public function calculateMaxAdvanceAmount(Employee $employee): float
    {
        // Get current active payroll info
        $payrollInfo = $employee->payrollInfo;
        
        if (!$payrollInfo || !$payrollInfo->basic_salary) {
            return 0; // No salary info available
        }

        // Max = 50% of basic salary
        return $payrollInfo->basic_salary * 0.50;
    }

    /**
     * Generate unique advance number (ADV-YYYY-NNNN)
     */
    public function generateAdvanceNumber(): string
    {
        $year = now()->year;
        $prefix = "ADV-{$year}-";

        $lastAdvance = CashAdvance::where('advance_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAdvance) {
            $lastNumber = (int) substr($lastAdvance->advance_number, -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . $nextNumber;
    }

    /**
     * Schedule deductions for approved advance
     * Creates AdvanceDeduction records for upcoming payroll periods
     */
    private function scheduleDeductions(CashAdvance $advance): void
    {
        // Get next N payroll periods starting from today
        $upcomingPeriods = PayrollPeriod::where('pay_date', '>=', now()->toDateString())
            ->orderBy('pay_date', 'asc')
            ->take($advance->number_of_installments)
            ->get();

        if ($upcomingPeriods->count() < $advance->number_of_installments) {
            throw new \Exception(
                "Not enough upcoming payroll periods to schedule deductions. Required: {$advance->number_of_installments}, Available: {$upcomingPeriods->count()}"
            );
        }

        $remainingBalance = $advance->amount_approved;

        foreach ($upcomingPeriods as $index => $period) {
            $installmentNumber = $index + 1;
            $deductionAmount = $advance->deduction_amount_per_period;

            // Last installment gets remaining balance (handles rounding)
            if ($installmentNumber === $advance->number_of_installments) {
                $deductionAmount = $remainingBalance;
            }

            $remainingBalance -= $deductionAmount;

            AdvanceDeduction::create([
                'cash_advance_id' => $advance->id,
                'payroll_period_id' => $period->id,
                'installment_number' => $installmentNumber,
                'deduction_amount' => $deductionAmount,
                'remaining_balance_after' => max(0, $remainingBalance),
                'is_deducted' => false,
            ]);
        }

        Log::info("Deductions scheduled for advance", [
            'advance_number' => $advance->advance_number,
            'total_installments' => $advance->number_of_installments,
            'approved_amount' => $advance->amount_approved,
        ]);
    }
}
