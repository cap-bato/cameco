<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_policy_id',
        'year',
        'earned',
        'used',
        'carried_forward',
        'forfeited',
        'last_accrued_at',
    ];

    protected $casts = [
        'earned' => 'decimal:2',
        'used' => 'decimal:2',
        'carried_forward' => 'decimal:2',
        'forfeited' => 'decimal:2',
        'last_accrued_at' => 'datetime',
        'year' => 'integer',
    ];

    protected $appends = ['remaining'];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    // Accessors
    public function getRemainingAttribute(): float
    {
        return (float) ($this->earned + $this->carried_forward - $this->used);
    }

    // Scopes
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeWithPositiveBalance($query)
    {
        return $query->whereRaw('(earned + carried_forward - used) > 0');
    }
}

