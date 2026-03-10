<?php

namespace App\Events\HR\Leave;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a leave request is cancelled
 * 
 * This event is dispatched when:
 * - Employee cancels their own leave request (via HR Staff)
 * - HR Staff cancels a leave request
 */
class LeaveRequestCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param LeaveRequest $leaveRequest
     * @param User $cancelledBy
     * @param string|null $reason
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $cancelledBy,
        public ?string $reason = null
    ) {}
}
