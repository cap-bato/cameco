<?php

namespace App\Events\HR\Leave;

use App\Models\LeaveRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a leave request is approved
 * 
 * This event is dispatched when:
 * - A leave request is auto-approved by the system
 * - HR Manager approves a leave request
 * - Office Admin gives final approval
 */
class LeaveRequestApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param LeaveRequest $leaveRequest
     * @param string $approvalType ('auto', 'manager', 'admin')
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $approvalType
    ) {}
}
