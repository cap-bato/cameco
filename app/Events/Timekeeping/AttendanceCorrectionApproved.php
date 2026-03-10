<?php

namespace App\Events\Timekeeping;

use App\Models\AttendanceCorrection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AttendanceCorrectionApproved Event
 * 
 * Task 4.4.2: Dispatched when an attendance correction is approved by HR Manager.
 * Triggers recomputation of summaries and notifies downstream modules.
 * 
 * Integration points:
 * - Payroll module: Trigger recalculation of affected payroll period
 * - Notification module: Notify requester of approval
 * - Daily summary: Recompute daily_attendance_summary with corrected values
 * - Audit logging: Record approval action for compliance
 */
class AttendanceCorrectionApproved
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
