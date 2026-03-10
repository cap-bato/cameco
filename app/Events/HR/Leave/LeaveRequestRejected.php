<?php

namespace App\Events\HR\Leave;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a leave request is rejected
 * 
 * This event is dispatched when:
 * - HR Manager rejects a leave request
 * - Office Admin rejects a leave request
 */
class LeaveRequestRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param LeaveRequest $leaveRequest
     * @param User $rejectedBy
     * @param string|null $reason
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $rejectedBy,
        public ?string $reason = null
    ) {}
}
