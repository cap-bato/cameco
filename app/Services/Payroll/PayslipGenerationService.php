<?php

namespace App\Services\Payroll;

use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;

/**
 * PayslipGenerationService
 *
 * Generates formatted payslip data for employees from payroll calculations.
 * Integrates with PayrollCalculationService to create comprehensive payslip
 * records that include earnings, deductions (including cash advances), and net pay.
 *
 * Used by:
 * - PayslipsController for payslip listing and PDF generation
 * - Payroll reports for payslip distribution
 * - Employee portal for viewing their payslips
 *
 * Features:
 * - Formats payslip data for display and PDF export
 * - Includes detailed earnings breakdown
 * - Includes comprehensive deductions (gov't, loans, advances, tardiness)
 * - Calculates YTD totals
 * - Supports filtering and pagination
 */
class PayslipGenerationService
{
    /**
     * Generate formatted payslip data from a calculation record
     *
     * @param EmployeePayrollCalculation $calculation
     * @return array
     */
    public function generatePayslip(EmployeePayrollCalculation $calculation): array
    {
        try {
            $employee = $calculation->employee;
            $period = $calculation->payrollPeriod;

            // Earnings section - all earnings components
            $earnings = $this->getEarningsBreakdown($calculation);

            // Deductions section - all deductions including cash advances
            $deductions = $this->getDeductionsBreakdown($calculation);

            // Calculate YTD amounts for the period
            $ytdData = $this->calculateYTDAmounts($employee, $period);

            // Format payslip data
            $payslip = [
                // Employee Information
                'payslip_id' => $calculation->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->full_name ?? $employee->user->full_name,
                'position' => $employee->position_title ?? 'N/A',
                'department' => $employee->department->name ?? 'N/A',

                // Period Information
                'period_id' => $period->id,
                'period_name' => $period->name,
                'period_start' => $period->start_date->toDateString(),
                'period_end' => $period->end_date->toDateString(),
                'pay_date' => $period->pay_date->toDateString(),

                // Attendance Data
                'days_worked' => $calculation->days_worked,
                'total_hours' => (float) $calculation->total_hours,
                'regular_hours' => (float) $calculation->regular_hours,
                'overtime_hours' => (float) $calculation->overtime_hours,
                'late_minutes' => $calculation->late_minutes,
                'undertime_minutes' => $calculation->undertime_minutes,

                // Earnings
                'earnings' => $earnings,
                'gross_pay' => (float) $calculation->gross_pay,

                // Deductions
                'deductions' => $deductions,
                'total_deductions' => (float) $calculation->total_deductions,

                // Net Pay
                'net_pay' => (float) $calculation->net_pay,

                // YTD Amounts
                'ytd_gross_pay' => (float) $ytdData['ytd_gross_pay'],
                'ytd_total_deductions' => (float) $ytdData['ytd_total_deductions'],
                'ytd_net_pay' => (float) $ytdData['ytd_net_pay'],

                // Status
                'status' => $calculation->status,
                'generated_at' => $calculation->created_at->toDateTimeString(),
            ];

            Log::info('Payslip generated successfully', [
                'employee_id' => $employee->id,
                'payslip_id' => $calculation->id,
                'period_id' => $period->id,
                'gross_pay' => $calculation->gross_pay,
                'net_pay' => $calculation->net_pay,
            ]);

            return $payslip;
        } catch (\Exception $e) {
            Log::error('Failed to generate payslip', [
                'calculation_id' => $calculation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get earnings breakdown for payslip
     *
     * Returns array of earnings components:
     * - Basic pay
     * - Overtime pay
     * - Salary components (allowances)
     *
     * @param EmployeePayrollCalculation $calculation
     * @return array
     */
    private function getEarningsBreakdown(EmployeePayrollCalculation $calculation): array
    {
        $earnings = [];

        // Basic Pay
        if ($calculation->basic_pay > 0) {
            $earnings[] = [
                'description' => 'Basic Salary',
                'amount' => (float) $calculation->basic_pay,
            ];
        }

        // Overtime Pay
        if ($calculation->overtime_pay > 0) {
            $earnings[] = [
                'description' => 'Overtime',
                'amount' => (float) $calculation->overtime_pay,
            ];
        }

        // Salary Components (allowances, bonuses, etc.)
        if ($calculation->component_amount > 0) {
            $earnings[] = [
                'description' => 'Salary Components',
                'amount' => (float) $calculation->component_amount,
            ];
        }

        // Allowances
        if ($calculation->allowance_amount > 0) {
            $earnings[] = [
                'description' => 'Allowances',
                'amount' => (float) $calculation->allowance_amount,
            ];
        }

        return $earnings;
    }

    /**
     * Get deductions breakdown for payslip
     *
     * Returns array of all deductions in DOLE-compliant order:
     * 1. Government contributions (SSS, PhilHealth, Pag-IBIG)
     * 2. Tax withholdings
     * 3. Attendance deductions (tardiness, undertime)
     * 4. Loans (SSS, Pag-IBIG, company)
     * 5. Cash advances (NEW)
     * 6. Other deductions
     *
     * @param EmployeePayrollCalculation $calculation
     * @return array
     */
    private function getDeductionsBreakdown(EmployeePayrollCalculation $calculation): array
    {
        $deductions = [];

        // Government Contributions (Employee Share)
        if ($calculation->sss_contribution > 0) {
            $deductions[] = [
                'description' => 'SSS Employee',
                'amount' => (float) $calculation->sss_contribution,
            ];
        }

        if ($calculation->philhealth_contribution > 0) {
            $deductions[] = [
                'description' => 'PhilHealth Employee',
                'amount' => (float) $calculation->philhealth_contribution,
            ];
        }

        if ($calculation->pagibig_contribution > 0) {
            $deductions[] = [
                'description' => 'Pag-IBIG Employee',
                'amount' => (float) $calculation->pagibig_contribution,
            ];
        }

        // Withholding Tax
        if ($calculation->withholding_tax > 0) {
            $deductions[] = [
                'description' => 'Withholding Tax',
                'amount' => (float) $calculation->withholding_tax,
            ];
        }

        // Attendance Deductions
        if ($calculation->late_deduction > 0) {
            $deductions[] = [
                'description' => 'Tardiness/Undertime',
                'amount' => (float) $calculation->late_deduction,
            ];
        }

        if ($calculation->undertime_deduction > 0) {
            $deductions[] = [
                'description' => 'Undertime',
                'amount' => (float) $calculation->undertime_deduction,
            ];
        }

        // Loan Deductions
        if ($calculation->loan_deduction > 0) {
            $deductions[] = [
                'description' => 'Loan Deduction',
                'amount' => (float) $calculation->loan_deduction,
            ];
        }

        // Cash Advance Deduction (NEW - Phase 3 Task 3.2)
        // This is the key addition for payroll advances integration
        if ($calculation->advance_deduction > 0) {
            $deductions[] = [
                'description' => 'Cash Advance',
                'amount' => (float) $calculation->advance_deduction,
            ];
        }

        // Other Deductions
        if ($calculation->deduction_amount > 0) {
            $deductions[] = [
                'description' => 'Other Deductions',
                'amount' => (float) $calculation->deduction_amount,
            ];
        }

        return $deductions;
    }

    /**
     * Calculate YTD (Year-To-Date) amounts for employee up to the given period
     *
     * @param Employee $employee
     * @param PayrollPeriod $period
     * @return array
     */
    private function calculateYTDAmounts(Employee $employee, PayrollPeriod $period): array
    {
        try {
            // Get all calculations for employee up to and including current period
            $ytdCalculations = EmployeePayrollCalculation::where('employee_id', $employee->id)
                ->whereHas('payrollPeriod', function ($query) use ($period) {
                    // Get all periods up to current period within same year
                    $query->whereYear('pay_date', $period->pay_date->year)
                        ->where('pay_date', '<=', $period->pay_date)
                        ->where('status', '!=', 'draft');
                })
                ->get();

            return [
                'ytd_gross_pay' => $ytdCalculations->sum('gross_pay'),
                'ytd_total_deductions' => $ytdCalculations->sum('total_deductions'),
                'ytd_net_pay' => $ytdCalculations->sum('net_pay'),
            ];
        } catch (\Exception $e) {
            Log::warning('Could not calculate YTD amounts', [
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);

            // Return zero values if calculation fails
            return [
                'ytd_gross_pay' => 0,
                'ytd_total_deductions' => 0,
                'ytd_net_pay' => 0,
            ];
        }
    }

    /**
     * Get multiple payslips for a given period
     *
     * @param PayrollPeriod $period
     * @param array $filters
     * @return array
     */
    public function getPayslipsForPeriod(PayrollPeriod $period, array $filters = []): array
    {
        try {
            $query = EmployeePayrollCalculation::where('payroll_period_id', $period->id)
                ->with(['employee', 'payrollPeriod']);

            // Apply filters if provided
            if (isset($filters['department_id'])) {
                $query->whereHas('employee', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            }

            $calculations = $query->orderBy('employee_id')->get();

            // Generate payslip for each calculation
            return $calculations->map(fn ($calc) => $this->generatePayslip($calc))->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get payslips for period', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
