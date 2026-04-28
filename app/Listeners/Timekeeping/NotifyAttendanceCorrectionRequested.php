<?php

namespace App\Listeners\Timekeeping;

use App\Events\Timekeeping\AttendanceCorrectionRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyAttendanceCorrectionRequested implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * Logs the correction request. Email notification to the HR manager is
     * stubbed here for Phase 2 when the Notification mail classes are built.
     */
    public function handle(AttendanceCorrectionRequested $event): void
    {
        Log::info('Attendance correction requested', [
            'correction_id' => $event->correction->id,
            'employee_id'   => $event->correction->requested_by_user_id,
            'reason'        => $event->correction->correction_reason ?? null,
        ]);

        // TODO Phase 2: send email to HR manager
        // Notification::route('mail', config('mail.hr_address'))
        //     ->notify(new AttendanceCorrectionRequestedNotification($event->correction));
    }
}
