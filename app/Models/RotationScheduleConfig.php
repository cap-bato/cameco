<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RotationScheduleConfig extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rotation_assignment_id',
        'work_schedule_id',
        'effective_date',
        'end_date',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Belongs to RotationAssignment
     *
     * @return BelongsTo
     */
    public function rotationAssignment(): BelongsTo
    {
        return $this->belongsTo(RotationAssignment::class, 'rotation_assignment_id');
    }

    /**
     * Relationship: Belongs to WorkSchedule
     *
     * @return BelongsTo
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    /**
     * Scope: Get only active configurations
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get configurations active on a specific date
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon|string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDate($query, $date)
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $query
            ->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Check if this configuration is valid for a specific date
     *
     * @param Carbon|string $date
     * @return bool
     */
    public function isValidForDate($date): bool
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        if (!$this->is_active) {
            return false;
        }

        if ($date->lt($this->effective_date)) {
            return false;
        }

        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Get the duration in days that this configuration is active
     *
     * @return int|null Null if no end_date (indefinite)
     */
    public function getDurationInDays(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        return $this->effective_date->diffInDays($this->end_date) + 1;
    }
}
