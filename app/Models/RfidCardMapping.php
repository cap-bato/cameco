<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class RfidCardMapping extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'card_uid',
        'employee_id',
        'card_type',
        'issued_at',
        'issued_by',
        'expires_at',
        'is_active',
        'last_used_at',
        'usage_count',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the employee this badge is assigned to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who issued this badge
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the user who deactivated this badge
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Get all issue logs for this badge (by card_uid)
     */
    public function issueLogs(): HasMany
    {
        return $this->hasMany(BadgeIssueLog::class, 'card_uid', 'card_uid');
    }

    /**
     * Scope: Get only active badges
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Get only inactive badges
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Get only expired badges
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope: Get badges expiring within specified days
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    /**
     * Get the current status of the badge
     * Returns: 'active', 'expired', 'lost', 'stolen', or 'inactive'
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) {
            if ($this->deactivation_reason && str_contains(strtolower($this->deactivation_reason), 'lost')) {
                return 'lost';
            }
            if ($this->deactivation_reason && str_contains(strtolower($this->deactivation_reason), 'stolen')) {
                return 'stolen';
            }
            return 'inactive';
        }

        if ($this->expires_at && $this->expires_at <= now()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Get the number of days until expiration
     * Returns null if badge has no expiration date
     */
    public function getDaysUntilExpirationAttribute()
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'card_uid',
                'employee_id',
                'card_type',
                'issued_at',
                'issued_by',
                'expires_at',
                'is_active',
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Badge (UID: {$this->card_uid}) {$eventName}");
    }
}
