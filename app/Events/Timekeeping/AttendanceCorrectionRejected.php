<?php

namespace App\Events\Timekeeping;

use App\Models\AttendanceCorrection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AttendanceCorrectionRejected Event
 * 
 * Task 4.4.3: Dispatched when an attendance correction is rejected by HR Manager.
 * Notifies requester and closes the correction workflow.
 * 
 * Integration points:
 * - Notification module: Notify requester of rejection with reason
 * - Audit logging: Record rejection action for compliance
 */
class AttendanceCorrectionRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AttendanceCorrection $correction;

    /**
     * Create a new event instance.
     */
    public function __construct(AttendanceCorrection $correction)
    {
        $this->correction = $correction;
    }
}
