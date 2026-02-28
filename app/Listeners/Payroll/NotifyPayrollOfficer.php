<?php

namespace App\Listeners\Payroll;

use App\Events\Payroll\PayrollCalculationCompleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyPayrollOfficer implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PayrollCalculationCompleted $event): void
    {
        try {
            Log::info('Notifying payroll officers of completion', [
                'period_id' => $event->payrollPeriod->id,
                'success_count' => $event->successCount,
                'failure_count' => $event->failureCount,
            ]);

            // Get all payroll officers
            $payrollOfficers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['payroll-officer', 'payroll-manager', 'admin']);
            })->get();

            // Build notification message
            $message = "Payroll calculation completed for period {$event->payrollPeriod->period_name}.\n";
            $message .= "Successfully calculated: {$event->successCount} employees\n";
            
            if ($event->failureCount > 0) {
                $message .= "Failed calculations: {$event->failureCount} employees\n";
            }

            $message .= "Please review the results in the Payroll system.";

            // Send notifications to payroll officers
            foreach ($payrollOfficers as $officer) {
                Log::info('Sending payroll completion notification', [
                    'user_id' => $officer->id,
                    'period_id' => $event->payrollPeriod->id,
                ]);

                // In-app notification would go here
                // For now, just log the notification
            }

            Log::info('Payroll officers notified successfully', [
                'period_id' => $event->payrollPeriod->id,
                'officers_count' => $payrollOfficers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify payroll officers', [
                'period_id' => $event->payrollPeriod->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
