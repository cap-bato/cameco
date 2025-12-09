<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WorkforceCoverageCache extends Model
{
    protected $table = 'workforce_coverage_cache';

    protected $fillable = [
        'department_id',
        'date',
        'coverage_percentage',
        'employees_available',
        'total_employees',
    ];

    protected $casts = [
        'date' => 'date',
        'coverage_percentage' => 'decimal:2',
        'employees_available' => 'integer',
        'total_employees' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Department relationship
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get coverage status badge based on percentage
     * 🟢 >= 90% (Optimal)
     * 🟡 75-89% (Adequate)
     * 🟠 60-74% (Low)
     * 🔴 < 60% (Critical)
     */
    public function getCoverageStatusAttribute(): string
    {
        if ($this->coverage_percentage >= 90) {
            return 'optimal';
        } elseif ($this->coverage_percentage >= 75) {
            return 'adequate';
        } elseif ($this->coverage_percentage >= 60) {
            return 'low';
        } else {
            return 'critical';
        }
    }

    /**
     * Get coverage status emoji
     */
    public function getCoverageEmojiAttribute(): string
    {
        return match ($this->coverage_status) {
            'optimal' => '🟢',
            'adequate' => '🟡',
            'low' => '🟠',
            'critical' => '🔴',
            default => '⚪',
        };
    }

    /**
     * Check if coverage is below threshold
     */
    public function isBelowThreshold(float $threshold = 75.0): bool
    {
        return $this->coverage_percentage < $threshold;
    }

    /**
     * Check if coverage is critical (requires manager override)
     */
    public function isCritical(): bool
    {
        return $this->coverage_percentage < 50;
    }

    /**
     * Scope: Get coverage for a specific department and date
     */
    public function scopeForDepartmentAndDate($query, int $departmentId, Carbon|string $date)
    {
        return $query->where('department_id', $departmentId)
            ->where('date', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    /**
     * Scope: Get coverage for a date range
     */
    public function scopeForDateRange($query, Carbon|string $startDate, Carbon|string $endDate)
    {
        return $query->whereBetween('date', [
            $startDate instanceof Carbon ? $startDate->toDateString() : $startDate,
            $endDate instanceof Carbon ? $endDate->toDateString() : $endDate,
        ]);
    }

    /**
     * Scope: Get low coverage days (below threshold)
     */
    public function scopeLowCoverage($query, float $threshold = 75.0)
    {
        return $query->where('coverage_percentage', '<', $threshold);
    }

    /**
     * Scope: Get critical coverage days (below 50%)
     */
    public function scopeCritical($query)
    {
        return $query->where('coverage_percentage', '<', 50);
    }

    /**
     * Scope: Get optimal coverage days (>= 90%)
     */
    public function scopeOptimal($query)
    {
        return $query->where('coverage_percentage', '>=', 90);
    }

    /**
     * Static: Get or create cache entry for department and date
     */
    public static function getOrCreateForDate(int $departmentId, Carbon|string $date): self
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : $date;
        
        return self::firstOrCreate(
            [
                'department_id' => $departmentId,
                'date' => $dateString,
            ],
            [
                'coverage_percentage' => 0,
                'employees_available' => 0,
                'total_employees' => 0,
            ]
        );
    }

    /**
     * Update coverage metrics
     */
    public function updateCoverage(int $employeesAvailable, int $totalEmployees): void
    {
        $this->employees_available = $employeesAvailable;
        $this->total_employees = $totalEmployees;
        $this->coverage_percentage = $totalEmployees > 0 
            ? round(($employeesAvailable / $totalEmployees) * 100, 2)
            : 0;
        $this->save();
    }
}
