<?php

namespace App\Events\Timekeeping;

use App\Models\AttendanceCorrection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * AttendanceCorrectionRequested Event
 * 
 * Task 4.4.1: Dispatched when a new attendance correction request is submitted.
 * Used to notify stakeholders and trigger downstream processing.
 * 
 * Integration points:
 * - Notification module: Send notification to HR Manager for approval
 * - Audit logging: Record correction request for compliance
 * - Workflow gating: Mark attendance record as "under correction"
 */
class AttendanceCorrectionRequested
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
