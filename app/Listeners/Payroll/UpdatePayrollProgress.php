<?php

namespace App\Listeners\Payroll;

use App\Events\Payroll\EmployeePayrollCalculated;
use App\Models\PayrollPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
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

            // Get total employee payroll calculations for this period
            $totalEmployees = $event->payrollPeriod->employeePayrollCalculations()
                ->count();

            // Get completed calculations
            $completedCount = $event->payrollPeriod->employeePayrollCalculations()
                ->where('status', 'completed')
                ->count();

            // Calculate progress percentage
            $progressPercentage = $totalEmployees > 0
                ? round(($completedCount / $totalEmployees) * 100, 2)
                : 0;

            // Update period progress if the column exists
            if (DB::getSchemaBuilder()->hasColumn($event->payrollPeriod->getTable(), 'progress_percentage')) {
                $event->payrollPeriod->update([
                    'progress_percentage' => $progressPercentage,
                ]);
            } else {
                Log::warning('progress_percentage column does not exist on payroll periods table; skipping update', [
                    'period_id' => $event->payrollPeriod->id,
                    'progress' => $progressPercentage,
                ]);
            }

            Log::info('Payroll progress updated', [
                'period_id' => $event->payrollPeriod->id,
                'progress' => $progressPercentage,
                'completed' => $completedCount,
                'total' => $totalEmployees,
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
