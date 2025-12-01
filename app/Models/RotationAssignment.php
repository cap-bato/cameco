<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class RotationAssignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'rotation_id',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee that owns this rotation assignment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the rotation for this assignment.
     */
    public function rotation(): BelongsTo
    {
        return $this->belongsTo(EmployeeRotation::class, 'rotation_id');
    }

    /**
     * Get the user who created this assignment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the schedule configuration for this rotation assignment.
     * 
     * Phase 2: Configuration Layer
     * This relationship links rotation assignment to its work schedule configuration.
     * An assignment can have multiple configs over time (schedule change after 90 days, etc).
     * Use the scope methods to find the active config for a specific date.
     */
    public function scheduleConfigs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RotationScheduleConfig::class, 'rotation_assignment_id');
    }

    /**
     * Get the most recently created schedule configuration.
     * 
     * This is a convenience accessor when you need "the current schedule config"
     * but should be validated with isValidForDate() before use.
     */
    public function scheduleConfig(): HasOne
    {
        return $this->hasOne(RotationScheduleConfig::class, 'rotation_assignment_id')
            ->latest('created_at');
    }

    /**
     * Scope to get active rotation assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rotation assignments valid for a specific date.
     * Returns assignments where start_date <= $date AND (end_date IS NULL OR end_date >= $date)
     */
    public function scopeForDate($query, $date)
    {
        return $query
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $date);
            });
    }

    /**
     * Check if this assignment is currently active (today is within the date range).
     */
    public function isCurrent(): bool
    {
        $today = \Carbon\Carbon::now();
        $isActive = $today->gte($this->start_date);
        $isNotEnded = is_null($this->end_date) || $today->lte($this->end_date);

        return $this->is_active && $isActive && $isNotEnded;
    }

    /**
     * End the rotation assignment.
     */
    public function end(): self
    {
        $this->update([
            'end_date' => \Carbon\Carbon::now(),
            'is_active' => false,
        ]);
        return $this;
    }

    /**
     * Extend the rotation assignment.
     */
    public function extend(?\Carbon\Carbon $newEndDate = null): self
    {
        if (is_null($newEndDate)) {
            $this->update(['end_date' => null]);
        } else {
            $this->update(['end_date' => $newEndDate]);
        }
        return $this;
    }

    /**
     * Get the duration of this assignment in days.
     */
    public function getDurationInDaysAttribute(): int
    {
        $endDate = $this->end_date ?? \Carbon\Carbon::now();
        return $this->start_date->diffInDays($endDate) + 1;
    }

    /**
     * Check if a specific date falls on a work day for this rotation.
     */
    public function isWorkDay(\Carbon\Carbon $date): bool
    {
        if (!$this->isCurrent()) {
            return false;
        }

        return $this->rotation->calculateWorkDay($this->start_date, $date->diffInDays($this->start_date));
    }

    /**
     * Get the active schedule configuration for a specific date.
     * 
     * Phase 2 Method: Returns the work schedule that applies to this rotation assignment
     * on a specific date.
     * 
     * Usage:
     *   $config = $assignment->getCurrentSchedule(Carbon::parse('2025-12-01'));
     *   if ($config) {
     *       $schedule = $config->workSchedule;
     *   }
     * 
     * @param Carbon|string $date The date to check schedule for
     * @return RotationScheduleConfig|null The active schedule config, or null if none found
     */
    public function getCurrentSchedule($date): ?RotationScheduleConfig
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $this->scheduleConfigs()
            ->active()
            ->forDate($date)
            ->first();
    }
}
