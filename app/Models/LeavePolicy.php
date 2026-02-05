<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LeavePolicy extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'annual_entitlement',
        'max_carryover',
        'can_carry_forward',
        'is_paid',
        'is_active',
        'effective_date',
    ];

    protected $casts = [
        'annual_entitlement' => 'decimal:1',
        'max_carryover' => 'decimal:1',
        'can_carry_forward' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
    ];

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Configure activity logging options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'annual_entitlement', 'max_carryover', 'can_carry_forward', 'is_paid', 'is_active', 'effective_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Leave policy '{$this->name}' {$eventName}");
    }
}
