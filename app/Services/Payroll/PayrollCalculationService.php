<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeePayrollInfo;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\DailyAttendanceSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PayrollCalculationService
 *
 * Orchestrates the entire payroll calculation flow for employees.
 * Integrates with:
 * - EmployeePayrollInfoService (employee salary setup)
 * - SalaryComponentService (earning/deduction components)
 * - AllowanceDeductionService (recurring allowances and deductions)
 * - LoanManagementService (loan deductions)
 * - Timekeeping (daily attendance data)
 *
 * Calculation Flow:
 * 1. Fetch employee payroll info
 * 2. Get attendance data for period
 * 3. Calculate basic pay
 * 4. Calculate overtime pay
 * 5. Apply salary components
 * 6. Add allowances
 * 7. Calculate deductions (SSS, PhilHealth, Pag-IBIG, Tax)
 * 8. Deduct loans and other obligations
 * 9. Calculate final net pay
 * 10. Save calculation record
 */
class PayrollCalculationService
{
    public function __construct(
        private EmployeePayrollInfoService $payrollInfoService,
        private SalaryComponentService $componentService,
        private AllowanceDeductionService $allowanceDeductionService,
        private LoanManagementService $loanManagementService,
    ) {}

    /**
     * Start payroll calculation for a period
     *
     * @param PayrollPeriod $period
     * @param User $initiator
     * @return void
     */
    public function startCalculation(PayrollPeriod $period, User $initiator): void
    {
        DB::beginTransaction();
        try {
            // Update period status to calculating
            $period->update([
                'status' => 'calculating',
                'processed_at' => Carbon::now(),
            ]);

            Log::info("Payroll calculation started", [
                'period_id' => $period->id,
                'period_name' => $period->name,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'initiated_by' => $initiator->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to start payroll calculation", [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate payroll for a single employee
     *
     * @param Employee $employee
     * @param PayrollPeriod $period
     * @return EmployeePayrollCalculation
     */
    public function calculateEmployee(Employee $employee, PayrollPeriod $period): EmployeePayrollCalculation
    {
        DB::beginTransaction();
        try {
            // Step 1: Fetch employee payroll info
            $payrollInfo = $this->payrollInfoService->getActivePayrollInfo($employee);

            if (!$payrollInfo) {
                throw ValidationException::withMessages([
                    'payroll_info' => "Employee {$employee->id} has no active payroll information",
                ]);
            }

            // Step 2: Fetch attendance data from timekeeping
            $attendanceSummaries = DailyAttendanceSummary::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
                ->where('is_finalized', true)
                ->get();

            // Step 3: Calculate days worked and hours
            $daysWorked = $attendanceSummaries->where('is_present', true)->count();
            $totalHours = $attendanceSummaries->sum('total_hours_worked');
            $regularHours = $attendanceSummaries->sum('regular_hours');
            $overtimeHours = $attendanceSummaries->sum('overtime_hours');
            $lateMinutes = $attendanceSummaries->sum('late_minutes');
            $undertimeMinutes = $attendanceSummaries->sum('undertime_minutes');

            // Step 4: Calculate basic pay
            $basicPay = $this->calculateBasicPay($daysWorked, $payrollInfo);

            // Step 5: Calculate overtime pay
            $overtimePay = $this->calculateOvertimePay($overtimeHours, $payrollInfo);

            // Step 6: Get active salary components
            $components = $this->componentService->getEmployeeComponents($employee, true);
            $componentAmounts = $components->sum('pivot.amount');

            // Step 7: Get active allowances
            $allowances = $this->allowanceDeductionService->getActiveAllowances($employee);
            $totalAllowances = $allowances->sum('amount');

            // Step 8: Calculate gross pay
            $grossPay = $basicPay + $overtimePay + $componentAmounts + $totalAllowances;

            // Step 9: Calculate government contributions
            $sssContribution = $this->calculateSSSContribution($payrollInfo);
            $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
            $pagibigContribution = $this->calculatePagIBIGContribution($payrollInfo);

            // Step 10: Calculate withholding tax
            $taxableIncome = $grossPay - $sssContribution - $philhealthContribution - $pagibigContribution;
            $withholdingTax = $this->calculateWithholdingTax($taxableIncome, $payrollInfo->tax_status);

            // Step 11: Get active deductions
            $deductions = $this->allowanceDeductionService->getActiveDeductions($employee);
            $totalDeductions = $deductions->sum('amount');

            // Step 12: Calculate loan deductions
            $loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);

            // Step 13: Calculate late/undertime deductions
            $lateDeduction = $this->calculateLateDeduction($lateMinutes, $payrollInfo);
            $undertimeDeduction = $this->calculateUndertimeDeduction($undertimeMinutes, $payrollInfo);

            // Step 14: Calculate total deductions
            $allDeductions = $sssContribution + $philhealthContribution + $pagibigContribution
                + $withholdingTax + $totalDeductions + $loanDeductions + $lateDeduction + $undertimeDeduction;

            // Step 15: Calculate net pay
            $netPay = $grossPay - $allDeductions;

            // Step 16: Delete any existing calculation (for recalculation)
            EmployeePayrollCalculation::where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->delete();

            // Step 17: Create calculation record
            $calculation = EmployeePayrollCalculation::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
                'days_worked' => $daysWorked,
                'total_hours' => $totalHours,
                'regular_hours' => $regularHours,
                'overtime_hours' => $overtimeHours,
                'late_minutes' => $lateMinutes,
                'undertime_minutes' => $undertimeMinutes,
                'basic_pay' => (float) $basicPay,
                'overtime_pay' => (float) $overtimePay,
                'component_amount' => (float) $componentAmounts,
                'allowance_amount' => (float) $totalAllowances,
                'gross_pay' => (float) $grossPay,
                'sss_contribution' => (float) $sssContribution,
                'philhealth_contribution' => (float) $philhealthContribution,
                'pagibig_contribution' => (float) $pagibigContribution,
                'withholding_tax' => (float) $withholdingTax,
                'deduction_amount' => (float) $totalDeductions,
                'loan_deduction' => (float) $loanDeductions,
                'late_deduction' => (float) $lateDeduction,
                'undertime_deduction' => (float) $undertimeDeduction,
                'total_deductions' => (float) $allDeductions,
                'net_pay' => (float) $netPay,
                'status' => 'calculated',
                'calculated_at' => Carbon::now(),
            ]);

            DB::commit();

            Log::info("Employee payroll calculated", [
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay,
            ]);

            return $calculation;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to calculate employee payroll", [
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Recalculate payroll for a specific employee and period
     *
     * @param Employee $employee
     * @param PayrollPeriod $period
     * @return EmployeePayrollCalculation
     */
    public function recalculateEmployee(Employee $employee, PayrollPeriod $period): EmployeePayrollCalculation
    {
        Log::info("Recalculating employee payroll", [
            'employee_id' => $employee->id,
            'period_id' => $period->id,
        ]);

        return $this->calculateEmployee($employee, $period);
    }

    /**
     * Finalize payroll calculation for period
     *
     * @param PayrollPeriod $period
     * @param User $finalizer
     * @return void
     */
    public function finalizeCalculation(PayrollPeriod $period, User $finalizer): void
    {
        DB::beginTransaction();
        try {
            // Get all calculations for period
            $calculations = EmployeePayrollCalculation::where('payroll_period_id', $period->id)->get();

            if ($calculations->isEmpty()) {
                throw ValidationException::withMessages([
                    'calculations' => "No calculations found for period {$period->id}",
                ]);
            }

            // Calculate period totals
            $totalEmployees = $calculations->count();
            $totalGrossPay = $calculations->sum('gross_pay');
            $totalDeductions = $calculations->sum('total_deductions');
            $totalNetPay = $calculations->sum('net_pay');

            // Calculate employer contributions
            $totalEmployerSSS = $calculations->sum(function ($calc) {
                return $calc->sss_contribution * 1.45; // Employer contribution rate
            });
            $totalEmployerPhilHealth = $calculations->sum(function ($calc) {
                return $calc->philhealth_contribution * 1.00; // Employer contribution rate
            });
            $totalEmployerPagIBIG = $calculations->sum(function ($calc) {
                return $calc->pagibig_contribution * 0.20; // Employer contribution rate
            });

            $totalEmployerCost = $totalGrossPay + $totalEmployerSSS + $totalEmployerPhilHealth + $totalEmployerPagIBIG;

            // Update period with final data
            $period->update([
                'status' => 'calculated',
                'total_employees' => $totalEmployees,
                'total_gross_pay' => $totalGrossPay,
                'total_deductions' => $totalDeductions,
                'total_net_pay' => $totalNetPay,
                'total_employer_cost' => $totalEmployerCost,
                'calculated_at' => Carbon::now(),
            ]);

            // Mark all calculations as finalized
            EmployeePayrollCalculation::where('payroll_period_id', $period->id)
                ->update(['status' => 'finalized']);

            DB::commit();

            Log::info("Payroll calculation finalized", [
                'period_id' => $period->id,
                'total_employees' => $totalEmployees,
                'total_gross_pay' => $totalGrossPay,
                'total_net_pay' => $totalNetPay,
                'finalized_by' => $finalizer->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to finalize payroll calculation", [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate basic pay for employee
     *
     * @param int $daysWorked
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculateBasicPay(int $daysWorked, EmployeePayrollInfo $payrollInfo): float
    {
        return match ($payrollInfo->salary_type) {
            'monthly' => $payrollInfo->basic_salary,
            'daily' => $daysWorked * ($payrollInfo->daily_rate ?? 0),
            'hourly' => $daysWorked * 8 * ($payrollInfo->hourly_rate ?? 0),
            default => 0,
        };
    }

    /**
     * Calculate overtime pay
     *
     * @param float $overtimeHours
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculateOvertimePay(float $overtimeHours, EmployeePayrollInfo $payrollInfo): float
    {
        if ($overtimeHours <= 0 || !$payrollInfo->hourly_rate) {
            return 0;
        }

        // Standard OT rate: 1.25x hourly rate
        return $overtimeHours * $payrollInfo->hourly_rate * 1.25;
    }

    /**
     * Calculate SSS contribution (employee share)
     *
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculateSSSContribution(EmployeePayrollInfo $payrollInfo): float
    {
        if (!$payrollInfo->sss_number) {
            return 0;
        }

        // SSS contribution rates based on bracket (simplified)
        // In production, should use government_contribution_rates table
        $salary = $payrollInfo->basic_salary;

        if ($salary < 4250) return $salary * 0.08; // E1 bracket
        if ($salary < 8000) return $salary * 0.08; // E2 bracket
        if ($salary < 16000) return $salary * 0.08; // E3 bracket
        if ($salary < 30000) return $salary * 0.08; // E4 bracket
        if ($salary < 40000) return $salary * 0.08; // E5 bracket
        return $salary * 0.08; // E6 bracket
    }

    /**
     * Calculate PhilHealth contribution (employee share)
     *
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculatePhilHealthContribution(EmployeePayrollInfo $payrollInfo): float
    {
        if (!$payrollInfo->philhealth_number) {
            return 0;
        }

        // PhilHealth contribution: 2.75% of basic salary (employee share)
        return $payrollInfo->basic_salary * 0.0275;
    }

    /**
     * Calculate Pag-IBIG contribution (employee share)
     *
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculatePagIBIGContribution(EmployeePayrollInfo $payrollInfo): float
    {
        if (!$payrollInfo->pagibig_number) {
            return 0;
        }

        // Pag-IBIG contribution rates:
        // If salary > 1,500: 1% employee, 2% employer
        // If salary <= 1,500: Only employer pays 0.1%
        $rate = $payrollInfo->pagibig_employee_rate ?? 1.0; // Default 1%
        return $payrollInfo->basic_salary * ($rate / 100);
    }

    /**
     * Calculate withholding tax (BIR)
     *
     * @param float $taxableIncome
     * @param string $taxStatus
     * @return float
     */
    private function calculateWithholdingTax(float $taxableIncome, string $taxStatus): float
    {
        // Tax-exempt status
        if ($taxStatus === 'Z') {
            return 0;
        }

        // Simplified withholding tax calculation
        // In production, use government_tax_brackets table for exact BIR rates
        // Current simplified rates (monthly):
        $annualIncome = $taxableIncome * 12;

        if ($annualIncome <= 250000) return 0; // Non-taxable
        if ($annualIncome <= 400000) return ($annualIncome - 250000) * 0.05 / 12;
        if ($annualIncome <= 800000) return (150000 * 0.05 + ($annualIncome - 400000) * 0.10) / 12;
        if ($annualIncome <= 2000000) return (150000 * 0.05 + 400000 * 0.10 + ($annualIncome - 800000) * 0.15) / 12;
        
        // For higher incomes
        return (150000 * 0.05 + 400000 * 0.10 + 1200000 * 0.15 + ($annualIncome - 2000000) * 0.20) / 12;
    }

    /**
     * Calculate late deduction
     *
     * @param int $lateMinutes
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculateLateDeduction(int $lateMinutes, EmployeePayrollInfo $payrollInfo): float
    {
        if ($lateMinutes <= 0 || !$payrollInfo->hourly_rate) {
            return 0;
        }

        // Deduct late minutes at hourly rate
        $lateHours = $lateMinutes / 60;
        return $lateHours * $payrollInfo->hourly_rate;
    }

    /**
     * Calculate undertime deduction
     *
     * @param int $undertimeMinutes
     * @param EmployeePayrollInfo $payrollInfo
     * @return float
     */
    private function calculateUndertimeDeduction(int $undertimeMinutes, EmployeePayrollInfo $payrollInfo): float
    {
        if ($undertimeMinutes <= 0 || !$payrollInfo->hourly_rate) {
            return 0;
        }

        // Deduct undertime minutes at hourly rate
        $undertimeHours = $undertimeMinutes / 60;
        return $undertimeHours * $payrollInfo->hourly_rate;
    }

    /**
     * Get calculated payroll for employee in period
     *
     * @param Employee $employee
     * @param PayrollPeriod $period
     * @return EmployeePayrollCalculation|null
     */
    public function getEmployeeCalculation(Employee $employee, PayrollPeriod $period): ?EmployeePayrollCalculation
    {
        return EmployeePayrollCalculation::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->first();
    }

    /**
     * Get all calculations for period
     *
     * @param PayrollPeriod $period
     * @return \Illuminate\Support\Collection
     */
    public function getPeriodCalculations(PayrollPeriod $period)
    {
        return EmployeePayrollCalculation::where('payroll_period_id', $period->id)
            ->with('employee')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
