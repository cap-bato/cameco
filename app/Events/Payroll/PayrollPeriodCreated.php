<?php

namespace App\Events\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollPeriodCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PayrollPeriod $payrollPeriod,
        public int $userId
    ) {}
}
