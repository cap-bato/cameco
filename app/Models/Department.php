<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Department extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'manager_id',
        'code',
        'budget',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'budget' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Parent department relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Child departments relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * Manager relationship
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Positions in this department
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Employees in this department
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all positions in this department and child departments
     */
    public function allPositions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Get department hierarchy as array
     */
    public function getHierarchy()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'children' => $this->children->map->getHierarchy(),
        ];
    }

    /**
     * Get full department path (e.g., "Company > Engineering > Backend")
     */
    public function getFullPath()
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent_id) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode(' > ', $path);
    }

    /**
     * Scope: Active departments only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Root departments (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: Get department count per level
     */
    public function scopeWithPositionCount($query)
    {
        return $query->withCount('positions');
    }

    /**
     * Configure activity logging options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'parent_id', 'manager_id', 'code', 'budget', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Department '{$this->name}' {$eventName}");
    }
}
