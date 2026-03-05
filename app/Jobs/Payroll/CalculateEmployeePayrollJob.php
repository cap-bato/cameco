<?php

namespace App\Jobs\Payroll;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Models\Employee;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollCalculationLog;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateEmployeePayrollJob implements ShouldQueue
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
            Log::error('Employee payroll calculation failed', [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->full_name,
                'period_id' => $this->payrollPeriod->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Create exception calculation record for tracking
            try {
                EmployeePayrollCalculation::create([
                    'employee_id' => $this->employee->id,
                    'payroll_period_id' => $this->payrollPeriod->id,
                    'calculation_status' => 'exception',
                    'has_exceptions' => true,
                    'exception_flags' => [$e->getMessage()],
                    'basic_pay' => 0,
                    'gross_pay' => 0,
                    'total_deductions' => 0,
                    'net_pay' => 0,
                    'calculated_by' => $this->userId,
                ]);
            } catch (\Exception $createException) {
                Log::error('Failed to create failed calculation record', [
                    'employee_id' => $this->employee->id,
                    'error' => $createException->getMessage(),
                ]);
            }

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
}
