<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\PayrollCalculationStarted;
use App\Events\Payroll\PayrollCalculationCompleted;
use App\Events\Payroll\PayrollCalculationFailed;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculatePayrollJob implements ShouldQueue
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
     * Execute the job - orchestrate entire payroll calculation
     */
    public function handle(): void
    {
        try {
            Log::info('Starting payroll calculation', [
                'period_id' => $this->payrollPeriod->id,
                'period_name' => $this->payrollPeriod->name,
                'employee_count' => Employee::where('is_active', true)->count(),
            ]);

            // Dispatch event - calculation started
            PayrollCalculationStarted::dispatch($this->payrollPeriod, $this->userId);

            // Update period status to 'calculating'
            $this->payrollPeriod->update([
                'status' => 'calculating',
                'updated_by' => $this->userId,
            ]);

            // Get all active employees
            $employees = Employee::where('is_active', true)
                ->with('payrollInfo')
                ->get();

            Log::info('Fetched active employees for payroll', [
                'count' => $employees->count(),
            ]);

            // Dispatch job for each employee
            $dispatchedCount = 0;
            foreach ($employees as $employee) {
                if ($employee->payrollInfo) {
                    CalculateEmployeePayrollJob::dispatch(
                        $employee,
                        $this->payrollPeriod,
                        $this->userId
                    );
                    $dispatchedCount++;
                }
            }

            Log::info('Dispatched employee payroll jobs', [
                'dispatched_count' => $dispatchedCount,
                'period_id' => $this->payrollPeriod->id,
            ]);

            // Update period with total employees
            $this->payrollPeriod->update([
                'total_employees' => $dispatchedCount,
            ]);

            // Dispatch finalize job to run after all employee calculations complete
            FinalizePayrollJob::dispatch($this->payrollPeriod, $this->userId)
                ->delay(now()->addMinutes(30)); // Wait 30 minutes for all calculations

        } catch (\Exception $e) {
            Log::error('Payroll calculation failed', [
                'period_id' => $this->payrollPeriod->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update period status to 'failed'
            $this->payrollPeriod->update([
                'status' => 'failed',
                'updated_by' => $this->userId,
            ]);

            // Dispatch failure event
            PayrollCalculationFailed::dispatch(
                $this->payrollPeriod,
                $e->getMessage(),
                $this->userId
            );

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Payroll calculation job failed permanently', [
            'period_id' => $this->payrollPeriod->id,
            'error' => $exception->getMessage(),
        ]);

        // Update period status
        $this->payrollPeriod->update([
            'status' => 'failed',
            'updated_by' => $this->userId,
        ]);

        // Dispatch failure event
        PayrollCalculationFailed::dispatch(
            $this->payrollPeriod,
            $exception->getMessage(),
            $this->userId
        );
    }
}
