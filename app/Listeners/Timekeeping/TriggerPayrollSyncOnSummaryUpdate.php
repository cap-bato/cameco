<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceSummaryUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class TriggerPayrollSyncOnSummaryUpdate implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Logs the summary update. When payroll recalculation-on-change is
     * implemented, dispatch the payroll recalc job here for the affected period.
     */
    public function handle(AttendanceSummaryUpdated $event): void
    {
        Log::info('Attendance summary updated — payroll sync hook fired', [
            'employee_id'     => $event->summary->employee_id,
            'attendance_date' => $event->summary->attendance_date,
            'is_new'          => $event->isNew,
        ]);

        // TODO: dispatch payroll recalculation job for the affected period
        // dispatch(new RecalculatePayrollForDateJob(
        //     employeeId: $event->summary->employee_id,
        //     date: $event->summary->attendance_date,
        // ));
    }
}
