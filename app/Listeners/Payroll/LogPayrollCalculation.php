<?php

namespace App\Listeners\Payroll;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Events\Payroll\PayrollCalculationCompleted;
use App\Events\Payroll\PayrollCalculationFailed;
use App\Events\Payroll\PayrollCalculationStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogPayrollCalculation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle PayrollCalculationStarted event.
     */
    public function handleStarted(PayrollCalculationStarted $event): void
    {
        Log::info('Payroll calculation started', [
            'period_id' => $event->payrollPeriod->id,
            'period_name' => $event->payrollPeriod->period_name,
            'initiated_by' => $event->userId,
            'timestamp' => now(),
        ]);
    }

    /**
     * Handle EmployeePayrollCalculated event.
     */
    public function handleEmployeeCalculated(EmployeePayrollCalculated $event): void
    {
        Log::info('Employee payroll calculated', [
            'period_id' => $event->payrollPeriod->id,
            'employee_id' => $event->employee->id,
            'employee_name' => $event->employee->user->full_name,
            'gross_pay' => $event->calculation->gross_pay,
            'deductions' => $event->calculation->total_deductions,
            'net_pay' => $event->calculation->net_pay,
            'status' => $event->calculation->status,
            'timestamp' => now(),
        ]);
    }

    /**
     * Handle PayrollCalculationCompleted event.
     */
    public function handleCompleted(PayrollCalculationCompleted $event): void
    {
        Log::info('Payroll calculation completed', [
            'period_id' => $event->payrollPeriod->id,
            'period_name' => $event->payrollPeriod->period_name,
            'success_count' => $event->successCount,
            'failure_count' => $event->failureCount,
            'total_count' => $event->successCount + $event->failureCount,
            'completed_by' => $event->userId,
            'period_status' => $event->payrollPeriod->status,
            'timestamp' => now(),
        ]);
    }

    /**
     * Handle PayrollCalculationFailed event.
     */
    public function handleFailed(PayrollCalculationFailed $event): void
    {
        Log::error('Payroll calculation failed', [
            'period_id' => $event->payrollPeriod->id,
            'period_name' => $event->payrollPeriod->period_name,
            'error_message' => $event->errorMessage,
            'failed_by' => $event->userId,
            'period_status' => $event->payrollPeriod->status,
            'timestamp' => now(),
        ]);
    }
}
