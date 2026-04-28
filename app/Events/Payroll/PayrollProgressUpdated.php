<?php

namespace App\Events\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PayrollPeriod $payrollPeriod;
    public float $progress;
    public int $processed;
    public int $total;
    public int $failed;
    public string $status;

    /**
     * Create a new event instance.
     */
    public function __construct(PayrollPeriod $payrollPeriod, float $progress, int $processed, int $total, int $failed, string $status)
    {
        $this->payrollPeriod = $payrollPeriod;
        $this->progress = $progress;
        $this->processed = $processed;
        $this->total = $total;
        $this->failed = $failed;
        $this->status = $status;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('payroll.progress.' . $this->payrollPeriod->id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'period_id' => $this->payrollPeriod->id,
            'progress' => $this->progress,
            'processed' => $this->processed,
            'total' => $this->total,
            'failed' => $this->failed,
            'status' => $this->status,
        ];
    }

    public function broadcastAs()
    {
        return 'PayrollProgressUpdated';
    }
}
