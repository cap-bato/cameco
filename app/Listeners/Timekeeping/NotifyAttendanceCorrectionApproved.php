<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAttendanceCorrectionApproved implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Logs the approval. Email notification to the requesting employee is
     * stubbed here for Phase 2 when the Notification mail classes are built.
     */
    public function handle(AttendanceCorrectionApproved $event): void
    {
        Log::info('Attendance correction approved', [
            'correction_id' => $event->correction->id,
            'approved_by'   => $event->correction->approved_by_user_id,
        ]);

        // TODO Phase 2: notify requesting employee by email
        // $employee = $event->correction->requestedByUser->employee;
        // Mail::to($employee->email)->send(new AttendanceCorrectionApprovedMail($event->correction));
    }
}
