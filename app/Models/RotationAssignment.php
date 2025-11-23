<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
