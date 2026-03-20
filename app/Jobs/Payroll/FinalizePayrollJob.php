<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\PayrollCalculationCompleted;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
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
}