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

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private PayrollPeriod $payrollPeriod,
        private int $userId
    ) {
        $this->onQueue('payroll');
    }

    /**
     * Execute the job - finalize payroll period
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting payroll finalization', [
                'period_id' => $this->payrollPeriod->id,
                'period_name' => $this->payrollPeriod->period_name,
            ]);

            DB::transaction(function () use ($startTime) {
                // Fetch all successful calculations for the period
                $calculations = EmployeePayrollCalculation::query()
                    ->where('payroll_period_id', $this->payrollPeriod->id)
                    ->where('calculation_status', 'calculated')
                    ->get();

                Log::info('Fetched calculations for finalization', [
                    'count' => $calculations->count(),
                    'period_id' => $this->payrollPeriod->id,
                ]);

                // Calculate period totals
                $totalGrossPay = $calculations->sum('gross_pay');
                $totalDeductions = $calculations->sum('total_deductions');
                $totalNetPay = $calculations->sum('net_pay');

                // Calculate employer costs (SSS, PhilHealth, Pag-IBIG employer share)
                $totalEmployerCost = $calculations->reduce(function ($carry, $calc) {
                    $sssEmployerShare = $calc->sss_contribution * 1.48; // 8% employee × 1.48 ratio
                    $philHealthEmployerShare = $calc->philhealth_contribution * 0.63; // 2.75% employee × 0.63 ratio
                    $pagibigEmployerShare = $calc->pagibig_contribution; // Employer matches employee 1-2%
                    
                    return $carry + $sssEmployerShare + $philHealthEmployerShare + $pagibigEmployerShare;
                }, 0);

                Log::info('Calculated period totals', [
                    'total_gross_pay' => $totalGrossPay,
                    'total_deductions' => $totalDeductions,
                    'total_net_pay' => $totalNetPay,
                    'total_employer_cost' => $totalEmployerCost,
                ]);

                // Update payroll period with totals
                $this->payrollPeriod->update([
                    'status'                   => 'calculated',
                    'total_gross_pay'           => $totalGrossPay,
                    'total_deductions'          => $totalDeductions,
                    'total_net_pay'             => $totalNetPay,
                    'calculation_completed_at'  => now(),
                    'finalized_at'              => now(),
                ]);

                Log::info('Updated payroll period status to calculated', [
                    'period_id' => $this->payrollPeriod->id,
                ]);

                // Count success and failure
                $successCount = $calculations->count();
                $failureCount = EmployeePayrollCalculation::query()
                    ->where('payroll_period_id', $this->payrollPeriod->id)
                    ->where('calculation_status', 'exception')
                    ->count();

                Log::info('Payroll finalization complete', [
                    'period_id' => $this->payrollPeriod->id,
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                ]);

                // Dispatch completion event
                PayrollCalculationCompleted::dispatch(
                    $this->payrollPeriod,
                    $successCount,
                    $failureCount,
                    $this->userId
                );

                // Log completion to DB audit log
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update period status to 'cancelled' ('failed' is not a valid enum value)
            $this->payrollPeriod->update([
                'status' => 'cancelled',
                'updated_by' => $this->userId,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Payroll finalization failed permanently', [
            'period_id' => $this->payrollPeriod->id,
            'error' => $exception->getMessage(),
        ]);

        // Update period status to 'cancelled' ('failed' is not a valid enum value)
        $this->payrollPeriod->update([
            'status' => 'cancelled',
            'updated_by' => $this->userId,
        ]);
    }
}
