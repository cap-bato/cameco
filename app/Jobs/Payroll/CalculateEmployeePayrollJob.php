<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Models\Employee;
use App\Models\EmployeePayrollCalculation;
use App\Models\EmployeePayrollInfo;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateEmployeePayrollJob implements ShouldQueue
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
        private Employee $employee,
        private PayrollPeriod $payrollPeriod,
        private int $userId
    ) {
        $this->onQueue('payroll');
    }

    /**
     * Execute the job - calculate payroll for single employee
     */
    public function handle(PayrollCalculationService $calculationService): void
    {
        // === PHASE 2.3: Business Logic Failure Checks (Non-Retryable) ===
        // Check for non-retryable conditions before attempting calculation.
        // If found, write 'failed' record and return (don't retry).

        // Check 1: Employee must exist and be active
        if (!$this->employee || $this->employee->trashed()) {
            Log::warning('Employee not found or is inactive for payroll calculation', [
                'employee_id' => $this->employee?->id ?? 'null',
                'payroll_period_id' => $this->payrollPeriod->id,
            ]);
            $this->recordBusinessLogicFailure('Employee not found or is inactive');
            return; // Don't retry — this is a data problem
        }

        // Check 2: Employee must have active payroll information
        $payrollInfo = EmployeePayrollInfo::where('employee_id', $this->employee->id)
            ->where('is_active', true)
            ->whereNull('end_date')
            ->first();

        if (!$payrollInfo) {
            Log::warning('No active EmployeePayrollInfo found for employee', [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->full_name,
                'payroll_period_id' => $this->payrollPeriod->id,
            ]);
            $this->recordBusinessLogicFailure('No active EmployeePayrollInfo found');
            return; // Don't retry — this is a configuration problem
        }

        // === Normal Calculation Flow (Retryable Exceptions) ===
        try {
            Log::info('Starting employee payroll calculation', [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->full_name,
                'period_id' => $this->payrollPeriod->id,
                'period_name' => $this->payrollPeriod->period_name,
            ]);

            // Call the payroll calculation service
            $calculation = $calculationService->calculateEmployee(
                $this->employee,
                $this->payrollPeriod
            );

            Log::info('Employee payroll calculated successfully', [
                'employee_id' => $this->employee->id,
                'calculation_id' => $calculation->id,
                'gross_pay' => $calculation->gross_pay,
                'net_pay' => $calculation->net_pay,
                'total_deductions' => $calculation->total_deductions,
            ]);

            // Dispatch event - employee calculation completed
            EmployeePayrollCalculated::dispatch(
                $this->employee,
                $this->payrollPeriod,
                $calculation
            );

        } catch (\Exception $e) {
            // Log for debugging — do NOT create exception record here.
            // The record is written once and only once in failed() after all retries are exhausted.
            Log::warning(
                'CalculateEmployeePayrollJob attempt failed (will retry)',
                [
                    'employee_id' => $this->employee->id,
                    'employee_name' => $this->employee->full_name,
                    'payroll_period_id' => $this->payrollPeriod->id,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Employee payroll calculation failed permanently', [
            'employee_id' => $this->employee->id,
            'period_id' => $this->payrollPeriod->id,
            'error' => $exception->getMessage(),
        ]);

        // Try to create an exception calculation record
        try {
            EmployeePayrollCalculation::create([
                'employee_id' => $this->employee->id,
                'payroll_period_id' => $this->payrollPeriod->id,
                'calculation_status' => 'exception',
                'has_exceptions' => true,
                'exception_flags' => [$exception->getMessage()],
                'basic_pay' => 0,
                'gross_pay' => 0,
                'total_deductions' => 0,
                'net_pay' => 0,
                'calculated_by' => $this->userId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record failed calculation', [
                'error' => $e->getMessage(),
            ]);
        }

        // Write permanent failure to DB audit log
        try {
            PayrollCalculationLog::logCalculationFailed(
                $this->payrollPeriod->id,
                "Employee #{$this->employee->id} ({$this->employee->full_name}) payroll calculation failed permanently: {$exception->getMessage()}",
                ['employee_id' => $this->employee->id, 'trace' => $exception->getTraceAsString()],
            );
        } catch (\Exception $e) {
            Log::error('Failed to write calculation failure log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle business logic failures (non-retryable)
     *
     * Write a 'failed' status record without throwing an exception.
     * This keeps the exception queue clean from expected failures.
     *
     * @param string $reason
     * @return void
     */
    private function recordBusinessLogicFailure(string $reason): void
    {
        try {
            EmployeePayrollCalculation::create([
                'employee_id' => $this->employee->id,
                'payroll_period_id' => $this->payrollPeriod->id,
                'calculation_status' => 'failed',
                'has_exceptions' => true,
                'exception_flags' => [$reason],
                'basic_pay' => 0,
                'gross_pay' => 0,
                'total_deductions' => 0,
                'net_pay' => 0,
                'calculated_by' => $this->userId,
            ]);

            Log::info('Business logic failure recorded for employee', [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->full_name,
                'payroll_period_id' => $this->payrollPeriod->id,
                'reason' => $reason,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record business logic failure', [
                'employee_id' => $this->employee->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
