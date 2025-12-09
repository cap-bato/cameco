<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Position extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'department_id',
        'reports_to',
        'level',
        'min_salary',
        'max_salary',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'min_salary' => 'integer',
        'max_salary' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Department this position belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Position this reports to
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to');
    }

    /**
     * Positions that report to this one
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to');
    }

    /**
     * Get the organizational hierarchy up to the root
     */
    public function getHierarchy()
    {
        $path = [$this->title];
        $current = $this;

        while ($current->reports_to) {
            $current = $current->reportsTo;
            array_unshift($path, $current->title);
        }

        return implode(' > ', $path);
    }

    /**
     * Scope: Active positions only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get positions by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope: Get positions for a specific department
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope: Get positions with salary in range
     */
    public function scopeSalaryRange($query, $min, $max)
    {
        return $query->whereBetween('min_salary', [$min, $max])
            ->orWhereBetween('max_salary', [$min, $max]);
    }

    /**
     * Configure activity logging options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'department_id', 'reports_to', 'level', 'min_salary', 'max_salary', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Position '{$this->title}' {$eventName}");
    }
}
