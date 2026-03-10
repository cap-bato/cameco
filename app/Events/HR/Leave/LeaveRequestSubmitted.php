<?php

namespace App\Events\HR\Leave;

use App\Models\LeaveRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a leave request is submitted
 * 
 * This event is dispatched when:
 * - HR Staff submits a leave request on behalf of an employee
 * - The system determines the approval route for the request
 */
class LeaveRequestSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param LeaveRequest $leaveRequest
     * @param array $approvalRoute
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public array $approvalRoute
    ) {}
}
