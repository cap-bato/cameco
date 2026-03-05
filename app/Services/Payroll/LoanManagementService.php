<?php

namespace App\Services\Payroll;

use App\Models\EmployeeLoan;
use App\Models\LoanDeduction;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * LoanManagementService
 *
 * Manages employee loans and repayments including:
 * - Loan creation with installment scheduling
 * - SSS, Pag-IBIG, and company loan management
 * - Loan deduction processing during payroll
 * - Early payment handling
 * - Loan completion and tracking
 * - Eligibility checking
 */
class LoanManagementService
{
    /**
     * Create new loan for employee
     *
     * @param Employee $employee
     * @param array $data Contains 'loan_type', 'amount', 'interest_rate', 'number_of_months', 'start_date', optional 'reason', 'remarks'
     * @param User $creator
     * @return EmployeeLoan
     */
    public function createLoan(Employee $employee, array $data, User $creator): EmployeeLoan
    {
        DB::beginTransaction();
        try {
            // Validate loan type
            $validTypes = ['sss_loan', 'pagibig_loan', 'company_loan', 'emergency_loan', 'housing_loan'];
            if (!isset($data['loan_type']) || !in_array($data['loan_type'], $validTypes)) {
                throw ValidationException::withMessages([
                    'loan_type' => "Invalid loan type. Allowed: " . implode(', ', $validTypes),
                ]);
            }

            // Validate amount
            if (!isset($data['amount']) || $data['amount'] <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Loan amount must be greater than 0',
                ]);
            }

            // Validate number of months
            if (!isset($data['number_of_months']) || $data['number_of_months'] <= 0) {
                throw ValidationException::withMessages([
                    'number_of_months' => 'Number of months must be greater than 0',
                ]);
            }

            // Check eligibility
            if (!$this->checkLoanEligibility($employee, $data['loan_type'])) {
                throw ValidationException::withMessages([
                    'eligibility' => "Employee is not eligible for {$data['loan_type']}",
                ]);
            }

            // Set default values
            $startDate = $data['start_date'] ?? Carbon::now()->toDateString();
            $interestRate = $data['interest_rate'] ?? $this->getDefaultInterestRate($data['loan_type']);

            // Calculate monthly payment
            $monthlyPayment = $this->calculateMonthlyPayment(
                $data['amount'],
                $interestRate,
                $data['number_of_months']
            );

            // Create loan record
            $loan = EmployeeLoan::create([
                'employee_id' => $employee->id,
                'loan_type' => $data['loan_type'],
                'amount' => (float) $data['amount'],
                'interest_rate' => (float) $interestRate,
                'number_of_months' => (int) $data['number_of_months'],
                'monthly_payment' => (float) $monthlyPayment,
                'total_amount_with_interest' => (float) ($monthlyPayment * $data['number_of_months']),
                'start_date' => $startDate,
                'expected_end_date' => Carbon::parse($startDate)->addMonths($data['number_of_months'])->toDateString(),
                'balance' => (float) $data['amount'],
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => 'active',
                'created_by' => $creator->id,
            ]);

            // Schedule loan deductions for all months
            $this->scheduleLoanDeductions($loan, $creator);

            DB::commit();

            Log::info("Employee loan created", [
                'employee_id' => $employee->id,
                'loan_type' => $data['loan_type'],
                'amount' => $data['amount'],
                'monthly_payment' => $monthlyPayment,
                'number_of_months' => $data['number_of_months'],
            ]);

            return $loan;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create employee loan", [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Schedule loan deductions for payroll periods
     *
     * @param EmployeeLoan $loan
     * @param User $creator
     * @return Collection of LoanDeduction
     */
    public function scheduleLoanDeductions(EmployeeLoan $loan, User $creator): Collection
    {
        $deductions = new Collection();
        $currentDate = Carbon::parse($loan->start_date);
        $monthlyPayment = $loan->monthly_payment;

        for ($i = 0; $i < $loan->number_of_months; $i++) {
            $deduction = LoanDeduction::create([
                'employee_loan_id' => $loan->id,
                'employee_id' => $loan->employee_id,
                'deduction_amount' => (float) $monthlyPayment,
                'deduction_month' => $currentDate->toDateString(),
                'status' => 'pending',
                'created_by' => $creator->id,
            ]);

            $deductions->push($deduction);
            $currentDate->addMonth();
        }

        Log::info("Loan deductions scheduled", [
            'loan_id' => $loan->id,
            'employee_id' => $loan->employee_id,
            'count' => $deductions->count(),
        ]);

        return $deductions;
    }

    /**
     * Process loan deduction during payroll
     *
     * Similar to AdvanceDeductionService::processDeduction()
     *
     * @param Employee $employee
     * @param $payrollPeriod
     * @return float Total loan deductions for period
     */
    public function processLoanDeduction(Employee $employee, $payrollPeriod): float
    {
        // Get active loans for employee
        $loans = $this->getActiveLoansByType($employee, null); // Get all active loans

        $totalDeduction = 0;

        foreach ($loans as $loan) {
            // Get pending deductions for this period
            $pendingDeductions = LoanDeduction::where('employee_loan_id', $loan->id)
                ->where('status', 'pending')
                ->orderBy('deduction_month', 'asc')
                ->first();

            if ($pendingDeductions) {
                // Mark deduction as processed
                $pendingDeductions->update([
                    'status' => 'processed',
                    'processed_date' => Carbon::now()->toDateString(),
                ]);

                $totalDeduction += $pendingDeductions->deduction_amount;

                // Update loan balance
                $loan->decrement('balance', $pendingDeductions->deduction_amount);

                // Check if loan is fully paid
                if ($loan->balance <= 0) {
                    $this->completeLoan($loan);
                }

                Log::info("Loan deduction processed", [
                    'loan_id' => $loan->id,
                    'employee_id' => $employee->id,
                    'amount' => $pendingDeductions->deduction_amount,
                    'remaining_balance' => $loan->balance,
                ]);
            }
        }

        return $totalDeduction;
    }

    /**
     * Make early payment on loan
     *
     * @param EmployeeLoan $loan
     * @param float $paymentAmount
     * @param User $processor
     * @return void
     */
    public function makeEarlyPayment(EmployeeLoan $loan, float $paymentAmount, User $processor): void
    {
        if ($paymentAmount <= 0) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Payment amount must be greater than 0',
            ]);
        }

        if ($paymentAmount > $loan->balance) {
            throw ValidationException::withMessages([
                'payment_amount' => "Payment amount cannot exceed remaining balance of {$loan->balance}",
            ]);
        }

        DB::beginTransaction();
        try {
            // Deduct from loan balance
            $loan->decrement('balance', $paymentAmount);

            // Mark pending deductions as processed (proportional)
            $pendingDeductions = LoanDeduction::where('employee_loan_id', $loan->id)
                ->where('status', 'pending')
                ->get();

            $remainingPayment = $paymentAmount;

            foreach ($pendingDeductions as $deduction) {
                if ($remainingPayment <= 0) break;

                $deductionAmount = min($remainingPayment, $deduction->deduction_amount);
                $remainingPayment -= $deductionAmount;

                if ($deductionAmount == $deduction->deduction_amount) {
                    // Full deduction paid
                    $deduction->update([
                        'status' => 'processed',
                        'processed_date' => Carbon::now()->toDateString(),
                    ]);
                } else {
                    // Partial deduction paid - would need to track this separately
                    // For now, we'll keep it simple and only mark as processed if fully paid
                }
            }

            // Check if loan is fully paid
            if ($loan->balance <= 0) {
                $this->completeLoan($loan);
            }

            DB::commit();

            Log::info("Early payment made on loan", [
                'loan_id' => $loan->id,
                'employee_id' => $loan->employee_id,
                'payment_amount' => $paymentAmount,
                'remaining_balance' => $loan->balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process early payment on loan", [
                'loan_id' => $loan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete loan when fully paid
     *
     * @param EmployeeLoan $loan
     * @return void
     */
    public function completeLoan(EmployeeLoan $loan): void
    {
        $loan->update([
            'status' => 'completed',
            'end_date' => Carbon::now()->toDateString(),
            'balance' => 0,
        ]);

        Log::info("Loan marked as completed", [
            'loan_id' => $loan->id,
            'employee_id' => $loan->employee_id,
        ]);
    }

    /**
     * Check if employee is eligible for loan type
     *
     * @param Employee $employee
     * @param string $loanType
     * @return bool
     */
    public function checkLoanEligibility(Employee $employee, string $loanType): bool
    {
        // Get employee payroll info
        $payrollInfo = $employee->payrollInfo()->where('is_active', true)->first();

        if (!$payrollInfo) {
            return false;
        }

        // Check eligibility based on loan type
        switch ($loanType) {
            case 'sss_loan':
                // Must have SSS number and be enrolled in SSS
                return !empty($payrollInfo->sss_number);

            case 'pagibig_loan':
                // Must have Pag-IBIG number and be enrolled in Pag-IBIG
                return !empty($payrollInfo->pagibig_number);

            case 'company_loan':
                // Check if company loans are enabled and employee is eligible
                // Could check employment duration, salary level, etc.
                return true;

            case 'emergency_loan':
                // Emergency loans might have special eligibility rules
                return true;

            case 'housing_loan':
                // Housing loans might require minimum salary level
                return $payrollInfo->basic_salary >= 10000;

            default:
                return false;
        }
    }

    /**
     * Get default interest rate for loan type
     *
     * @param string $loanType
     * @return float
     */
    private function getDefaultInterestRate(string $loanType): float
    {
        return match ($loanType) {
            'sss_loan' => 0.0, // SSS loans typically 0% interest (handled by SSS)
            'pagibig_loan' => 0.0, // Pag-IBIG loans typically 0% interest
            'company_loan' => 1.0, // 1% interest per month
            'emergency_loan' => 2.0, // 2% interest per month
            'housing_loan' => 0.5, // 0.5% interest per month
            default => 0.0,
        };
    }

    /**
     * Calculate monthly payment using amortization formula
     *
     * Formula: M = P * [r(1+r)^n] / [(1+r)^n - 1]
     * Where:
     *  M = monthly payment
     *  P = principal amount
     *  r = monthly interest rate (annual rate / 12 / 100)
     *  n = number of months
     *
     * @param float $principal
     * @param float $annualInterestRate
     * @param int $numberOfMonths
     * @return float
     */
    private function calculateMonthlyPayment(float $principal, float $annualInterestRate, int $numberOfMonths): float
    {
        // Convert annual rate to monthly rate
        $monthlyRate = $annualInterestRate / 12 / 100;

        // If no interest, simple division
        if ($monthlyRate == 0) {
            return round($principal / $numberOfMonths, 2);
        }

        // Amortization formula
        $numerator = $monthlyRate * pow(1 + $monthlyRate, $numberOfMonths);
        $denominator = pow(1 + $monthlyRate, $numberOfMonths) - 1;

        return round($principal * ($numerator / $denominator), 2);
    }

    /**
     * Get active loans for employee by type
     *
     * @param Employee $employee
     * @param string|null $loanType If null, returns all active loans
     * @return Collection
     */
    public function getActiveLoansByType(Employee $employee, ?string $loanType = null): Collection
    {
        $query = EmployeeLoan::where('employee_id', $employee->id)
            ->where('status', 'active');

        if ($loanType) {
            $query->where('loan_type', $loanType);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get all loans for employee
     *
     * @param Employee $employee
     * @param bool $activeOnly
     * @return Collection
     */
    public function getEmployeeLoans(Employee $employee, bool $activeOnly = false): Collection
    {
        $query = EmployeeLoan::where('employee_id', $employee->id);

        if ($activeOnly) {
            $query->where('status', 'active');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get loan details with deduction history
     *
     * @param EmployeeLoan $loan
     * @return array
     */
    public function getLoanDetails(EmployeeLoan $loan): array
    {
        $deductions = LoanDeduction::where('employee_loan_id', $loan->id)
            ->orderBy('deduction_month', 'asc')
            ->get();

        return [
            'id' => $loan->id,
            'employee_id' => $loan->employee_id,
            'loan_type' => $loan->loan_type,
            'amount' => $loan->amount,
            'interest_rate' => $loan->interest_rate,
            'number_of_months' => $loan->number_of_months,
            'monthly_payment' => $loan->monthly_payment,
            'total_amount_with_interest' => $loan->total_amount_with_interest,
            'start_date' => $loan->start_date,
            'expected_end_date' => $loan->expected_end_date,
            'balance' => $loan->balance,
            'status' => $loan->status,
            'deductions' => $deductions->map(function ($deduction) {
                return [
                    'id' => $deduction->id,
                    'month' => $deduction->deduction_month,
                    'amount' => $deduction->deduction_amount,
                    'status' => $deduction->status,
                    'processed_date' => $deduction->processed_date,
                ];
            })->toArray(),
            'total_deductions_processed' => $deductions->where('status', 'processed')->sum('deduction_amount'),
            'total_deductions_pending' => $deductions->where('status', 'pending')->sum('deduction_amount'),
        ];
    }

    /**
     * Get deduction history for loan
     *
     * @param EmployeeLoan $loan
     * @return Collection
     */
    public function getLoanDeductionHistory(EmployeeLoan $loan): Collection
    {
        return LoanDeduction::where('employee_loan_id', $loan->id)
            ->orderBy('deduction_month', 'asc')
            ->get();
    }

    /**
     * Get pending deductions for employee across all loans
     *
     * @param Employee $employee
     * @return float
     */
    public function getPendingDeductionsTotal(Employee $employee): float
    {
        return LoanDeduction::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->sum('deduction_amount');
    }
}
