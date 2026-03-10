<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAccrual extends Model
{
    protected $fillable = [
        'leave_balance_id',
        'accrual_date',
        'amount',
        'accrual_type',
        'reason',
        'processed_by',
    ];

    protected $casts = [
        'accrual_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
