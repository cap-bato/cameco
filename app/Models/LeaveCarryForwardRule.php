<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCarryForwardRule extends Model
{
    protected $fillable = [
        'leave_policy_id',
        'max_carry_forward_days',
        'expiry_months',
        'allow_partial',
        'is_active',
    ];

    protected $casts = [
        'max_carry_forward_days' => 'decimal:2',
        'expiry_months' => 'integer',
        'allow_partial' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }
}
