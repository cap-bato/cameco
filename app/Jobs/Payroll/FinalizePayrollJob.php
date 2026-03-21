<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\PayrollCalculationCompleted;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizePayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 1;

    public function __construct(
        private PayrollPeriod $payrollPeriod,
        private int $userId
    ) {
        $this->onQueue('payroll');
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting payroll finalization', [
                'period_id' => $this->payrollPeriod->id,
                'period_name' => $this->payrollPeriod->period_name,
            ]);

            DB::transaction(function () use ($startTime) {
                $calculations = EmployeePayrollCalculation::query()
                    ->where('payroll_period_id', $this->payrollPeriod->id)
                    ->where('calculation_status', 'calculated')
                    ->get();

                Log::info('Fetched calculations for finalization', [
                    'count' => $calculations->count(),
                    'period_id' => $this->payrollPeriod->id,
                ]);

                $totalGrossPay   = $calculations->sum('gross_pay');
                $totalDeductions = $calculations->sum('total_deductions');
                $totalNetPay     = $calculations->sum('net_pay');

                $totalEmployerCost = $calculations->reduce(function ($carry, $calc) {
                    $sssEmployerShare        = $calc->sss_contribution * 1.48;
                    $philHealthEmployerShare  = $calc->philhealth_contribution * 0.63;
                    $pagibigEmployerShare     = $calc->pagibig_contribution;
                    return $carry + $sssEmployerShare + $philHealthEmployerShare + $pagibigEmployerShare;
                }, 0);

                $this->payrollPeriod->update([
                    'status'                  => 'calculated',
                    'total_gross_pay'          => $totalGrossPay,
                    'total_deductions'         => $totalDeductions,
                    'total_net_pay'            => $totalNetPay,
                    'progress_percentage'      => 100,
                    'calculation_completed_at' => now(),
                    'finalized_at'             => now(),
                ]);

                $successCount = $calculations->count();
                $failureCount = EmployeePayrollCalculation::query()
                    ->where('payroll_period_id', $this->payrollPeriod->id)
                    ->where('calculation_status', 'exception')
                    ->count();

                Log::info('Payroll finalization complete', [
                    'period_id'     => $this->payrollPeriod->id,
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ]);

                PayrollCalculationCompleted::dispatch(
                    $this->payrollPeriod,
                    $successCount,
                    $failureCount,
                    $this->userId
                );

                PayrollCalculationLog::logCalculationCompleted(
                    $this->payrollPeriod->id,
                    $successCount + $failureCount,
                    $successCount,
                    $failureCount,
                    $failureCount,
                    round(microtime(true) - $startTime, 2),
                );
            });

            // Generate payslips AFTER transaction succeeds (outside transaction to avoid conflicts)
            $this->generatePayslips();

        } catch (\Exception $e) {
            Log::error('Payroll finalization failed', [
                'period_id' => $this->payrollPeriod->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            $this->payrollPeriod->update([
                'status'     => 'cancelled',
                'updated_by' => $this->userId,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('Payroll finalization failed permanently', [
            'period_id' => $this->payrollPeriod->id,
            'error'     => $exception->getMessage(),
        ]);

        $this->payrollPeriod->update([
            'status'     => 'cancelled',
            'updated_by' => $this->userId,
        ]);
    }

    /**
     * Generate payslip records from employee payroll calculations.
     * This allows employees to view their payslips via the portal.
     * Called AFTER transaction to avoid conflicts.
     */
    private function generatePayslips(): void
    {
        try {
            $calculations = EmployeePayrollCalculation::where('payroll_period_id', $this->payrollPeriod->id)
                ->where('calculation_status', 'calculated')
                ->get();

            if ($calculations->isEmpty()) {
                Log::warning('No calculations found for payslip generation', [
                    'period_id' => $this->payrollPeriod->id,
                ]);
                return;
            }

            // Delete existing payslips for this period to avoid duplicates
            Payslip::where('payroll_period_id', $this->payrollPeriod->id)->delete();

            $payslipRecords = [];
            $now = now();

            foreach ($calculations as $calc) {
                // Build earnings breakdown from calculation data
                $earningsData = [
                    'basic_pay'             => (float) $calc->basic_monthly_salary,
                    'regular_pay'           => (float) $calc->basic_monthly_salary,
                    'holiday_pay'           => (float) ($calc->holiday_pay ?? 0),
                    'overtime_pay'          => (float) ($calc->overtime_pay ?? 0),
                    'allowance'             => (float) ($calc->allowances ?? 0),
                    'other_earnings'        => (float) ($calc->other_earnings ?? 0),
                ];

                // Build deductions breakdown from calculation data
                $deductionsData = [
                    'sss'                   => (float) $calc->sss_deduction,
                    'philhealth'            => (float) $calc->philhealth_deduction,
                    'pagibig'               => (float) $calc->pagibig_deduction,
                    'withholding_tax'       => (float) $calc->tax_deduction,
                    'sss_contribution'      => (float) ($calc->sss_contribution ?? 0),
                    'ph_contribution'       => (float) ($calc->philhealth_contribution ?? 0),
                    'pagibig_contribution'  => (float) ($calc->pagibig_contribution ?? 0),
                    'loan'                  => (float) ($calc->loan_deduction ?? 0),
                    'advance'               => (float) ($calc->advance_deduction ?? 0),
                    'leave'                 => (float) ($calc->leave_deduction ?? 0),
                    'attendance'            => (float) ($calc->attendance_deduction ?? 0),
                    'tardiness'             => (float) ($calc->tardiness_deduction ?? 0),
                    'absence'               => (float) ($calc->absence_deduction ?? 0),
                    'miscellaneous'         => (float) ($calc->miscellaneous_deductions ?? 0),
                    'other'                 => (float) ($calc->other_deductions ?? 0),
                ];

                $payslipRecords[] = [
                    'payroll_period_id'     => $this->payrollPeriod->id,
                    'employee_id'           => $calc->employee_id,
                    'payslip_number'        => 'PS-' . $this->payrollPeriod->period_number . '-' . str_pad($calc->employee_id, 5, '0', STR_PAD_LEFT),
                    'period_start'          => $this->payrollPeriod->period_start,
                    'period_end'            => $this->payrollPeriod->period_end,
                    'payment_date'          => $this->payrollPeriod->payment_date,
                    
                    // Employee snapshot
                    'employee_number'       => $calc->employee_number,
                    'employee_name'         => $calc->employee_name,
                    'department'            => $calc->department,
                    'position'              => $calc->position,
                    'sss_number'            => $calc->sss_number ?? null,
                    'philhealth_number'     => $calc->philhealth_number ?? null,
                    'pagibig_number'        => $calc->pagibig_number ?? null,
                    'tin'                   => $calc->tin ?? null,
                    
                    // Amounts
                    'total_earnings'        => (float) $calc->gross_pay,
                    'total_deductions'      => (float) $calc->total_deductions,
                    'net_pay'               => (float) ($calc->final_net_pay ?? $calc->net_pay),
                    
                    // JSON Data (must be JSON-encoded for insert() to work correctly)
                    'earnings_data'         => json_encode($earningsData),
                    'deductions_data'       => json_encode($deductionsData),
                    
                    // YTD values
                    'ytd_gross'             => (float) $calc->gross_pay,
                    'ytd_tax'               => (float) $calc->tax_deduction,
                    'ytd_sss'               => (float) $calc->sss_deduction,
                    'ytd_philhealth'        => (float) $calc->philhealth_deduction,
                    'ytd_pagibig'           => (float) $calc->pagibig_deduction,
                    'ytd_net'               => (float) ($calc->final_net_pay ?? $calc->net_pay),
                    
                    // Distribution defaults
                    'status'                => 'generated',
                    'distribution_method'   => 'portal',
                    'generated_by'          => $this->userId,
                    
                    // Timestamps
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ];
            }

            // Batch insert all payslips
            if (!empty($payslipRecords)) {
                Payslip::insert($payslipRecords);

                Log::info('Payslips created automatically', [
                    'period_id'      => $this->payrollPeriod->id,
                    'payslip_count'  => count($payslipRecords),
                    'created_by'     => $this->userId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate payslips during finalization', [
                'period_id' => $this->payrollPeriod->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            // Don't throw — payslip generation failure shouldn't fail the payroll calculation
        }
    }
}