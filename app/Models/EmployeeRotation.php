<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeRotation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'pattern_type',
        'pattern_json',
        'department_id',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pattern_json' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the department that owns this rotation.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who created this rotation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the rotation assignments for this rotation.
     */
    public function rotationAssignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class, 'rotation_id');
    }

    /**
     * Scope to get active rotations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter rotations by pattern type.
     */
    public function scopeByPatternType($query, $type)
    {
        return $query->where('pattern_type', $type);
    }

    /**
     * Get the work days count from the pattern JSON.
     */
    public function getWorkDaysAttribute(): ?int
    {
        return $this->pattern_json['work_days'] ?? null;
    }

    /**
     * Get the rest days count from the pattern JSON.
     */
    public function getRestDaysAttribute(): ?int
    {
        return $this->pattern_json['rest_days'] ?? null;
    }

    /**
     * Get the pattern array from the pattern JSON.
     */
    public function getPatternArrayAttribute(): ?array
    {
        return $this->pattern_json['pattern'] ?? null;
    }

    /**
     * Calculate if a specific day is a work day using modulo arithmetic.
     * 
     * @param \Carbon\Carbon $startDate The start date of the rotation assignment
     * @param int $dayOffset The number of days offset from the start date
     * @return bool True if it's a work day, false if it's a rest day
     */
    public function calculateWorkDay($startDate, $dayOffset): bool
    {
        $pattern = $this->pattern_array;
        if (!$pattern) {
            return false;
        }

        $cycleLength = count($pattern);
        $cyclePosition = $dayOffset % $cycleLength;

        return $pattern[$cyclePosition] == 1;
    }

    /**
     * Generate a work/rest schedule for a date range.
     * 
     * @param \Carbon\Carbon $startDate The start date
     * @param int $numDays Number of days to generate
     * @return array Array with date => isWorkDay mapping
     */
    public function generateSchedule($startDate, $numDays): array
    {
        $schedule = [];
        
        for ($i = 0; $i < $numDays; $i++) {
            $date = $startDate->copy()->addDays($i);
            $isWorkDay = $this->calculateWorkDay($startDate, $i);
            $schedule[$date->format('Y-m-d')] = $isWorkDay;
        }

        return $schedule;
    }

    /**
     * Validate the pattern JSON structure.
     * Returns array with 'valid' flag and optional 'message'.
     */
    public function validatePatternStructure(): array
    {
        if (!isset($this->pattern_json['work_days']) || !isset($this->pattern_json['rest_days']) || !isset($this->pattern_json['pattern'])) {
            return [
                'valid' => false,
                'message' => 'Pattern JSON must contain work_days, rest_days, and pattern array',
            ];
        }

        $workDays = $this->pattern_json['work_days'];
        $restDays = $this->pattern_json['rest_days'];
        $pattern = $this->pattern_json['pattern'];

        if (!is_array($pattern)) {
            return [
                'valid' => false,
                'message' => 'Pattern must be an array',
            ];
        }

        $expectedLength = $workDays + $restDays;
        if (count($pattern) !== $expectedLength) {
            return [
                'valid' => false,
                'message' => "Pattern array length ({count($pattern)}) must equal work_days + rest_days ({$expectedLength})",
            ];
        }

        $actualWorkDays = count(array_filter($pattern, fn($v) => $v == 1));
        if ($actualWorkDays !== $workDays) {
            return [
                'valid' => false,
                'message' => "Pattern must contain exactly {$workDays} work days (1s), found {$actualWorkDays}",
            ];
        }

        $actualRestDays = count(array_filter($pattern, fn($v) => $v == 0));
        if ($actualRestDays !== $restDays) {
            return [
                'valid' => false,
                'message' => "Pattern must contain exactly {$restDays} rest days (0s), found {$actualRestDays}",
            ];
        }

        return [
            'valid' => true,
            'message' => 'Pattern is valid',
        ];
    }

    /**
     * Get the cycle length (pattern length).
     */
    public function getCycleLengthAttribute(): int
    {
        $pattern = $this->pattern_array;
        return $pattern ? count($pattern) : 0;
    }

    /**
     * Check if this rotation has any active assignments.
     */
    public function hasActiveAssignments(): bool
    {
        return $this->rotationAssignments()
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get count of employees assigned to this rotation.
     */
    public function getAssignedEmployeesCountAttribute(): int
    {
        return $this->rotationAssignments()
            ->where('is_active', true)
            ->distinct('employee_id')
            ->count();
    }

    /**
     * Generate a pattern array from pattern type.
     * Returns array with 'work_days', 'rest_days', and 'pattern'.
     */
    public static function generatePatternFromType(string $type): array
    {
        $patterns = [
            '4x2' => [
                'work_days' => 4,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 0, 0],
            ],
            '6x1' => [
                'work_days' => 6,
                'rest_days' => 1,
                'pattern' => [1, 1, 1, 1, 1, 1, 0],
            ],
            '5x2' => [
                'work_days' => 5,
                'rest_days' => 2,
                'pattern' => [1, 1, 1, 1, 1, 0, 0],
            ],
        ];

        return $patterns[$type] ?? null;
    }
}
