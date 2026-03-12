<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAttendanceCorrectionRejected implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Logs the rejection. Email notification to the requesting employee is
     * stubbed here for Phase 2 when the Notification mail classes are built.
     */
    public function handle(AttendanceCorrectionRejected $event): void
    {
        Log::info('Attendance correction rejected', [
            'correction_id'    => $event->correction->id,
            'rejection_reason' => $event->correction->rejection_reason,
        ]);

        // TODO Phase 2: notify requesting employee by email
        // $employee = $event->correction->requestedByUser->employee;
        // Mail::to($employee->email)->send(new AttendanceCorrectionRejectedMail($event->correction));
    }
}
