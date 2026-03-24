<?php

namespace App\Listeners\Payroll;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Models\EmployeePayrollCalculation;
use App\Models\PayrollPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdatePayrollProgress implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(EmployeePayrollCalculated $event): void
    {
        try {
            Log::info('Updating payroll progress', [
                'employee_id' => $event->employee->id,
                'period_id' => $event->payrollPeriod->id,
            ]);

            // Get total employees for this period (all calculation records dispatched)
            $totalEmployees = EmployeePayrollCalculation::where('payroll_period_id', $event->payrollPeriod->id)
                ->count();

            // Get completed calculations
            $completedCount = EmployeePayrollCalculation::where('payroll_period_id', $event->payrollPeriod->id)
                ->where('calculation_status', 'calculated')
                ->count();

            // Get failed calculations
            $failedCount = EmployeePayrollCalculation::where('payroll_period_id', $event->payrollPeriod->id)
                ->where('calculation_status', 'exception')
                ->count();

            // Calculate progress percentage
            $progressPercentage = $totalEmployees > 0 
                ? round(($completedCount / $totalEmployees) * 100, 2)
                : 0;

            // Update period progress
            $event->payrollPeriod->update([
                'progress_percentage' => $progressPercentage,
            ]);

            // Broadcast real-time progress update
            event(new \App\Events\Payroll\PayrollProgressUpdated(
                $event->payrollPeriod,
                $progressPercentage,
                $completedCount,
                $totalEmployees,
                $failedCount,
                $event->payrollPeriod->status
            ));

            Log::info('Payroll progress updated', [
                'period_id' => $event->payrollPeriod->id,
                'progress' => $progressPercentage,
                'completed' => $completedCount,
                'total' => $totalEmployees,
                'failed' => $failedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update payroll progress', [
                'period_id' => $event->payrollPeriod->id,
                'employee_id' => $event->employee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
