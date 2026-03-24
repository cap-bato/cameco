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
use App\Models\GovernmentContributionRate;
use App\Models\TaxBracket;
use App\Models\PayrollConfiguration;

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
class PayrollCalculationService {
    /**
     * Infer pay frequency from period date range.
     * @param PayrollPeriod $period
     * @return string
     */
    public function inferPeriodFrequency(PayrollPeriod $period): string
    {
        $start = $period->period_start instanceof \DateTimeInterface
            ? $period->period_start
            : Carbon::parse((string)$period->period_start);
        $end = $period->period_end instanceof \DateTimeInterface
            ? $period->period_end
            : Carbon::parse((string)$period->period_end);
        if (!$start || !$end) return 'unknown';
        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));
        // Weekly: 6-8 days
        if ($days >= 6 && $days <= 8) return 'weekly';
        // Bi-weekly: 12-15 days
        if ($days >= 12 && $days <= 15) return 'bi_weekly';
        // Semi-monthly: 13-17 days (1st/2nd half)
        if ($days >= 13 && $days <= 17) return 'semi_monthly';
        // Monthly: 27-32 days
        if ($days >= 27 && $days <= 32) return 'monthly';
        return 'unknown';
    }

    /**
     * Get weekly position in month (1..5) for weekly periods.
     * @param PayrollPeriod $period
     * @return int
     */
    public function getWeeklyPositionInMonth(PayrollPeriod $period): int
    {
        $start = Carbon::parse($period->period_start);
        // Find week number in month (1-based)
        return intval(ceil($start->day / 7));
    }

    /**
     * Get period position info for deduction logic.
     * @param PayrollPeriod $period
     * @return array
     */
    public function getPeriodPosition(PayrollPeriod $period): array
    {
        $frequency = $this->inferPeriodFrequency($period);
        $periodHalf = $this->getPeriodHalf($period);
        $weeklyPosition = $this->getWeeklyPositionInMonth($period);
        $start = Carbon::parse($period->period_start);
        $end = Carbon::parse($period->period_end);
        // Is last period in month?
        $isLastInMonth = $end->month !== $start->month || $end->day === $end->daysInMonth;
        return [
            'frequency' => $frequency,
            'periodHalf' => $periodHalf,
            'isLastInMonth' => $isLastInMonth,
            'weeklyPositionInMonth' => $weeklyPosition,
        ];
    }

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
                'calculation_started_at' => Carbon::now(),
            ]);

            Log::info("Payroll calculation started", [
                'period_id' => $period->id,
                'period_name' => $period->period_name,
                'start_date' => $period->period_start,
                'end_date' => $period->period_end,
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
                ->whereBetween('attendance_date', [$period->period_start, $period->period_end])
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
            $basicPay = $this->calculateBasicPay($daysWorked, $payrollInfo, $period);

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

            // Step 9 & 10: Government contributions and tax — configurable timing
            $periodHalf = $this->getPeriodHalf($period);
            $deductionConfig = $this->getDeductionTimingConfig($period);

            // SSS Contribution
            if ($this->shouldApplyDeduction($deductionConfig['sss'], $periodHalf)) {
                $sssContribution = $this->calculateSSSContribution($payrollInfo);
                $sssContribution *= $this->getDeductionMultiplier($deductionConfig['sss']);
            } else {
                $sssContribution = 0.0;
            }

            // PhilHealth Contribution
            if ($this->shouldApplyDeduction($deductionConfig['philhealth'], $periodHalf)) {
                $philhealthContribution = $this->calculatePhilHealthContribution($payrollInfo);
                $philhealthContribution *= $this->getDeductionMultiplier($deductionConfig['philhealth']);
            } else {
                $philhealthContribution = 0.0;
            }

            // Pag-IBIG Contribution
            if ($this->shouldApplyDeduction($deductionConfig['pagibig'], $periodHalf)) {
                $pagibigContribution = $this->calculatePagIBIGContribution($payrollInfo);
                $pagibigContribution *= $this->getDeductionMultiplier($deductionConfig['pagibig']);
            } else {
                $pagibigContribution = 0.0;
            }

            // Withholding Tax
            if ($this->shouldApplyDeduction($deductionConfig['withholding_tax'], $periodHalf)) {
                // Calculate tax based on monthly gross projection
                $monthlyGross   = ($basicPay * 2) + $totalAllowances;
                $monthlyTaxable = $monthlyGross - ($sssContribution / $this->getDeductionMultiplier($deductionConfig['sss']))
                                                   - ($philhealthContribution / $this->getDeductionMultiplier($deductionConfig['philhealth']))
                                                   - ($pagibigContribution / $this->getDeductionMultiplier($deductionConfig['pagibig']));
                $withholdingTax = $this->calculateWithholdingTax($monthlyTaxable, $payrollInfo->tax_status);
                $withholdingTax *= $this->getDeductionMultiplier($deductionConfig['withholding_tax']);
            } else {
                $withholdingTax = 0.0;
            }

            // Step 11: Get active deductions
            $deductions = $this->allowanceDeductionService->getActiveDeductions($employee);
            $totalDeductions = $deductions->sum('amount');

            // Step 12: Calculate loan deductions — configurable timing
            if ($this->shouldApplyDeduction($deductionConfig['loans'], $periodHalf)) {
                $loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);
                $loanDeductions *= $this->getDeductionMultiplier($deductionConfig['loans']);
            } else {
                $loanDeductions = 0.0;
            }

            // Step 13: Calculate late/undertime deductions
            $lateDeduction = $this->calculateLateDeduction($lateMinutes, $payrollInfo);
            $undertimeDeduction = $this->calculateUndertimeDeduction($undertimeMinutes, $payrollInfo);

            // Step 14: Calculate total deductions
            $allDeductions = $sssContribution + $philhealthContribution + $pagibigContribution
                + $withholdingTax + $totalDeductions + $loanDeductions + $lateDeduction + $undertimeDeduction;

            // Step 15: Calculate net pay
            $netPay = $grossPay - $allDeductions;

            // Step 16: Supersede any existing calculation to preserve audit trail.
            // Soft-delete the old record (leaves it visible to withTrashed() audits),
            // then create the new version pointing back to it via previous_version_id.
            $existingCalculation = EmployeePayrollCalculation::where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->latest('version')
                ->first();

            $newVersion = $existingCalculation ? $existingCalculation->version + 1 : 1;
            $previousVersionId = $existingCalculation?->id;

            if ($existingCalculation) {
                $existingCalculation->update(['calculation_status' => 'superseded']);
                $existingCalculation->delete(); // soft delete only — row stays in DB with deleted_at set
            }

            $expectedDays = Carbon::parse($period->period_start)
                ->diffInWeekdays(Carbon::parse($period->period_end)) + 1;

            // Step 17: Create calculation record
            $calculation = EmployeePayrollCalculation::create([
                'payroll_period_id'          => $period->id,
                'employee_id'                => $employee->id,
                'employee_number'            => $employee->employee_number,
                'employee_name'              => $employee->profile?->full_name ?? $employee->user?->name ?? 'Unknown',
                'department'                 => $employee->department?->name ?? null,
                'position'                   => $employee->position?->title ?? null,
                'employment_status'          => $this->normalizeEmploymentStatus($employee->employment_type),
                'hire_date'                  => $employee->date_hired ? Carbon::parse($employee->date_hired)->toDateString() : null,
                'basic_monthly_salary'       => (float) $payrollInfo->basic_salary,
                'daily_rate'                 => (float) $payrollInfo->daily_rate,
                'hourly_rate'                => (float) $payrollInfo->hourly_rate,
                'working_days_per_month'     => 26,
                'working_hours_per_day'      => 8,
                'expected_days'              => $expectedDays,
                'present_days'               => $daysWorked,
                'absent_days'                => max(0, $expectedDays - $daysWorked),
                'late_hours'                 => round($lateMinutes / 60, 2),
                'undertime_hours'            => round($undertimeMinutes / 60, 2),
                'regular_overtime_hours'     => (float) $overtimeHours,
                'total_overtime_hours'       => (float) $overtimeHours,
                'basic_pay'                  => (float) $basicPay,
                'regular_overtime_pay'       => (float) $overtimePay,
                'total_overtime_pay'         => (float) $overtimePay,
                'other_allowances'           => (float) $componentAmounts,
                'total_allowances'           => (float) ($totalAllowances + $componentAmounts),
                'gross_pay'                  => (float) $grossPay,
                'sss_contribution'           => (float) $sssContribution,
                'philhealth_contribution'    => (float) $philhealthContribution,
                'pagibig_contribution'       => (float) $pagibigContribution,
                'withholding_tax'            => (float) $withholdingTax,
                'total_government_deductions'=> (float) ($sssContribution + $philhealthContribution + $pagibigContribution),
                'total_loan_deductions'      => (float) $loanDeductions,
                'tardiness_deduction'        => (float) ($lateDeduction + $undertimeDeduction),
                'miscellaneous_deductions'   => (float) $totalDeductions,
                'total_deductions'           => (float) $allDeductions,
                'net_pay'                    => (float) $netPay,
                'final_net_pay'              => (float) $netPay,
                'version'                    => $newVersion,
                'previous_version_id'        => $previousVersionId,
                'calculation_status'         => 'calculated',
                'calculated_at'             => Carbon::now(),
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

            // Calculate employer contributions using DB bracket data (Option A)
            $totalEmployerSSS = $calculations->sum(function ($calc) {
                $bracket = GovernmentContributionRate::findSSSBracket((float) $calc->basic_monthly_salary);
                return $bracket
                    ? (float) $bracket->employer_amount
                    : (float) $calc->sss_contribution * (8.5 / 4.5);
            });
            // PhilHealth: 1:1 EE:ER split (both 2.5%)
            $totalEmployerPhilHealth = $calculations->sum('philhealth_contribution');
            // Pag-IBIG: employer rate mirrors employee rate (both 2%, same PHP100 ceiling)
            $totalEmployerPagIBIG = $calculations->sum('pagibig_contribution');

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
     * For semi-monthly payroll, monthly-salaried employees receive exactly half
     * their basic salary per period (1st half and 2nd half are equal).
     *
     * @param int $daysWorked
     * @param EmployeePayrollInfo $payrollInfo
     * @param PayrollPeriod $period  Used in Phase 2 for contribution half-gating
     * @return float
     */
    private function calculateBasicPay(int $daysWorked, EmployeePayrollInfo $payrollInfo, PayrollPeriod $period): float
    {
        $frequency = $this->inferPeriodFrequency($period);
        return match ($payrollInfo->salary_type) {
            'monthly' => match ($frequency) {
                'weekly' => ($payrollInfo->basic_salary * 12) / 52,
                'semi_monthly' => $payrollInfo->basic_salary / 2,
                'bi_weekly' => $payrollInfo->basic_salary / 2,
                'monthly' => $payrollInfo->basic_salary,
                default => $payrollInfo->basic_salary / 2,
            },
            'daily' => $daysWorked * ($payrollInfo->daily_rate ?? 0),
            'hourly' => $daysWorked * 8 * ($payrollInfo->hourly_rate ?? 0),
            default => 0,
        };
    }

    /**
     * Determine which half of the month the payroll period falls in.
     * 1st half: period_start day ≤ 15 (days 1–15)
     * 2nd half: period_start day > 15 (days 16–end)
     */
    private function getPeriodHalf(PayrollPeriod $period): int
    {
        return Carbon::parse($period->period_start)->day <= 15 ? 1 : 2;
    }

    /**
     * Get deduction timing configuration
     *
     * @return array
     */
    private function getDeductionTimingConfig(?PayrollPeriod $period = null): array
    {
        $globalConfig = PayrollConfiguration::get('deduction_timing', []);
        
        // Default fallback if no config exists
        $defaults = array_merge([
            'sss' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'philhealth' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'pagibig' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'withholding_tax' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
            'loans' => ['timing' => 'monthly_only', 'apply_on_period' => 2],
        ], $globalConfig);

        // Period-level overrides take precedence over global config
        if ($period && !empty($period->calculation_config['deduction_timing'])) {
            $periodOverrides = $period->calculation_config['deduction_timing'];
            foreach ($periodOverrides as $key => $override) {
                if (isset($defaults[$key]) && is_array($override) && !empty($override['timing'])) {
                    $defaults[$key] = array_merge($defaults[$key], $override);
                }
            }
        }

        return $defaults;
    }

    /**
     * Determine if a deduction should be applied for the given period
     *
     * @param array $deductionConfig  Config for specific deduction type
     * @param int $periodHalf         1 or 2 (which half of month)
     * @return bool
     */
    private function shouldApplyDeduction(array $deductionConfig, int $periodHalf): bool
    {
        $timing = $deductionConfig['timing'] ?? 'monthly_only';
        $applyOnPeriod = $deductionConfig['apply_on_period'] ?? 2;

        return match ($timing) {
            'per_cutoff' => true,
            'monthly_only' => $periodHalf === $applyOnPeriod,
            'split_monthly' => true,
            default => $periodHalf === 2,
        };
    }

    /**
     * Get the multiplier for split monthly deductions
     *
     * @param array $deductionConfig  Config for specific deduction type
     * @return float  1.0 for full amount, 0.5 for half amount
     */
    private function getDeductionMultiplier(array $deductionConfig): float
    {
        $timing = $deductionConfig['timing'] ?? 'monthly_only';

        return match ($timing) {
            'split_monthly' => 0.5,
            default => 1.0,
        };
    }

    /**
     * Map employee employment_type values into DB enum-safe calculation status values.
     */
    private function normalizeEmploymentStatus(?string $employmentType): string
    {
        $normalized = strtolower(trim((string) $employmentType));
        $normalized = str_replace('-', '_', $normalized);

        return match ($normalized) {
            'regular', 'probationary', 'contractual', 'project_based' => $normalized,
            default => 'regular',
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

        $bracket = GovernmentContributionRate::findSSSBracket((float) $payrollInfo->basic_salary);

        if (!$bracket) {
            Log::warning('SSS bracket not found for salary, returning 0', [
                'employee_id' => $payrollInfo->employee_id,
                'salary'      => $payrollInfo->basic_salary,
            ]);
            return 0;
        }

        return (float) $bracket->employee_amount;
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

        $rate = GovernmentContributionRate::getPhilHealthRate();

        if (!$rate) {
            Log::warning('PhilHealth rate not found in DB, returning 0', [
                'employee_id' => $payrollInfo->employee_id,
            ]);
            return 0;
        }

        $salary   = (float) $payrollInfo->basic_salary;
        $eeRate   = (float) $rate->employee_rate / 100;
        $computed = $salary * $eeRate;

        $minEE = (float) ($rate->minimum_contribution / 2);
        $maxEE = (float) ($rate->maximum_contribution / 2);

        return max($minEE, min($maxEE, $computed));
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

        $salary = (float) $payrollInfo->basic_salary;
        $rate   = GovernmentContributionRate::getPagIbigRate($salary);

        if (!$rate) {
            Log::warning('Pag-IBIG rate not found in DB, returning 0', [
                'employee_id' => $payrollInfo->employee_id,
                'salary'      => $salary,
            ]);
            return 0;
        }

        $eeRate   = (float) $rate->employee_rate / 100;
        $computed = $salary * $eeRate;
        $ceiling  = (float) ($rate->contribution_ceiling ?? 100);

        return min($ceiling, $computed);
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
        if ($taxStatus === 'Z') {
            return 0;
        }

        $annualIncome = $taxableIncome * 12;

        if ($annualIncome <= 0) {
            return 0;
        }

        $bracket = TaxBracket::findBracket($annualIncome, $taxStatus)
                ?? TaxBracket::findBracket($annualIncome, 'S');

        if (!$bracket) {
            Log::warning('Tax bracket not found, returning 0', [
                'tax_status'    => $taxStatus,
                'annual_income' => $annualIncome,
            ]);
            return 0;
        }

        return round($bracket->calculateTax($annualIncome) / 12, 2);
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
