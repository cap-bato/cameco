<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeIssueLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_uid',
        'employee_id',
        'issued_by',
        'issued_at',
        'action_type',
        'reason',
        'previous_card_uid',
        'replacement_fee',
        'acknowledgement_signature',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'replacement_fee' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee this log is for
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who performed the action
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Scope: Filter logs by action type
     */
    public function scopeByActionType($query, $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope: Get only 'issued' logs
     */
    public function scopeIssued($query)
    {
        return $query->where('action_type', 'issued');
    }

    /**
     * Scope: Get only 'replaced' logs
     */
    public function scopeReplaced($query)
    {
        return $query->where('action_type', 'replaced');
    }

    /**
     * Scope: Get only 'deactivated' logs
     */
    public function scopeDeactivated($query)
    {
        return $query->where('action_type', 'deactivated');
    }

    /**
     * Scope: Get only 'reactivated' logs
     */
    public function scopeReactivated($query)
    {
        return $query->where('action_type', 'reactivated');
    }

    /**
     * Scope: Get only 'expired' logs
     */
    public function scopeExpired($query)
    {
        return $query->where('action_type', 'expired');
    }
}
