<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\PayrollCalculationStarted;
use App\Events\Payroll\PayrollCalculationCompleted;
use App\Events\Payroll\PayrollCalculationFailed;
use App\Models\Employee;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculatePayrollJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                'period_name' => $this->payrollPeriod->period_name,
                'employee_count' => Employee::where('status', 'active')->count(),
            ]);

            // Dispatch event - calculation started
            PayrollCalculationStarted::dispatch($this->payrollPeriod, $this->userId);

            // Update period status to 'calculating'
            $this->payrollPeriod->update([
                'status' => 'calculating',
                'progress_percentage' => 0.00,
                'updated_by' => $this->userId,
            ]);

            // Get all active employees
            $employees = Employee::where('status', 'active')
                ->with('payrollInfo')
                ->get();

            Log::info('Fetched active employees for payroll', [
                'count' => $employees->count(),
            ]);

            // Log calculation start to DB audit log
            PayrollCalculationLog::logCalculationStarted(
                $this->payrollPeriod->id,
                $employees->count(),
            );

            // Dispatch job for each employee using Bus::batch()
            // This ensures FinalizePayrollJob only runs after ALL employee jobs complete
            $employeeJobs = [];
            foreach ($employees as $employee) {
                if ($employee instanceof Employee && $employee->payrollInfo) {
                    $employeeJobs[] = new CalculateEmployeePayrollJob(
                        $employee,
                        $this->payrollPeriod,
                        $this->userId
                    );
                }
            }

            $dispatchedCount = count($employeeJobs);

            Log::info('Prepared employee payroll jobs for batch dispatch', [
                'dispatched_count' => $dispatchedCount,
                'period_id' => $this->payrollPeriod->id,
            ]);

            // Update period with total employees
            $this->payrollPeriod->update([
                'total_employees' => $dispatchedCount,
            ]);

            if ($dispatchedCount > 0) {
                $periodId = $this->payrollPeriod->id;
                $userId = $this->userId;

                // Dispatch as a batch; FinalizePayrollJob runs only after ALL succeed (or exhaust retries)
                $batch = Bus::batch($employeeJobs)
                    ->name("payroll-{$this->payrollPeriod->id}")
                    ->allowFailures()   // individual failures don't cancel entire batch
                    ->then(function (Batch $batch) use ($periodId, $userId) {
                        // All jobs completed (some may have failed — check inside FinalizePayrollJob)
                        $period = PayrollPeriod::find($periodId);
                        if ($period) {
                            FinalizePayrollJob::dispatch($period, $userId);
                        }
                    })
                    ->catch(function (Batch $batch, Throwable $e) use ($periodId) {
                        // Batch-level failure (e.g., worker crash) — mark period as failed
                        Log::error(
                            "Payroll batch failed for period {$periodId}",
                            ['batch_id' => $batch->id, 'error' => $e->getMessage()]
                        );
                        PayrollPeriod::whereKey($periodId)->update(['status' => 'cancelled']);
                    })
                    ->dispatch();

                // Store the batch ID so the frontend can poll it for progress monitoring
                $this->payrollPeriod->update(['calculation_batch_id' => $batch->id]);

                Log::info('Dispatched payroll batch', [
                    'batch_id' => $batch->id,
                    'period_id' => $this->payrollPeriod->id,
                    'job_count' => $dispatchedCount,
                ]);
            } else {
                // No employees to process; go straight to finalize
                Log::warning('No employees to process for payroll', [
                    'period_id' => $this->payrollPeriod->id,
                ]);
                FinalizePayrollJob::dispatch($this->payrollPeriod, $this->userId);
            }

        } catch (\Exception $e) {
            Log::error('Payroll calculation failed', [
                'period_id' => $this->payrollPeriod->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update period status to 'cancelled' ('failed' is not a valid enum value)
            $this->payrollPeriod->update([
                'status' => 'cancelled',
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

        // Update period status to 'cancelled' ('failed' is not a valid enum value)
        $this->payrollPeriod->update([
            'status' => 'cancelled',
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
