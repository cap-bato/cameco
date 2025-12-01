<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ShiftAssignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'schedule_id',
        'rotation_assignment_id',
        'date',
        'shift_start',
        'shift_end',
        'shift_type',
        'location',
        'department_id',
        'is_overtime',
        'overtime_hours',
        'status',
        'has_conflict',
        'conflict_reason',
        'assignment_source',
        'source_details',
        'conflict_detected',
        'generated_at',
        'generated_by_user_id',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'is_overtime' => 'boolean',
        'has_conflict' => 'boolean',
        'overtime_hours' => 'decimal:2',
        'source_details' => 'array',
        'conflict_detected' => 'boolean',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the employee for this shift assignment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the work schedule for this shift assignment.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'schedule_id');
    }

    /**
     * Get the department for this shift assignment.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who created this shift assignment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the rotation assignment that generated this shift (if from rotation).
     */
    public function rotationAssignment(): BelongsTo
    {
        return $this->belongsTo(RotationAssignment::class, 'rotation_assignment_id');
    }

    /**
     * Get the user who generated this shift (if system-generated).
     *
     * Phase 4: Audit Trail
     * Tracks which user initiated the shift generation process.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Scope to filter shift assignments by date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope to filter shift assignments by employee.
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter shift assignments by department.
     */
    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to get scheduled assignments.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get overtime assignments.
     */
    public function scopeOvertime($query)
    {
        return $query->where('is_overtime', true);
    }

    /**
     * Scope to get conflicted assignments.
     */
    public function scopeConflicted($query)
    {
        return $query->where('has_conflict', true);
    }

    /**
     * Phase 4 Scopes: Enhanced tracking and filtering
     */

    /**
     * Scope to get shifts generated from rotation patterns.
     *
     * Useful for: "Show all shifts that came from rotation patterns"
     */
    public function scopeGeneratedFromRotation($query)
    {
        return $query->where('assignment_source', 'rotation')
            ->whereNotNull('rotation_assignment_id');
    }

    /**
     * Scope to get shifts with conflicts detected during generation.
     *
     * Useful for: "Find all shifts generated despite conflicts"
     */
    public function scopeWithConflicts($query)
    {
        return $query->where('conflict_detected', true);
    }

    /**
     * Scope to get shifts by assignment source (rotation, schedule, manual).
     *
     * @param  $query
     * @param  string  $source  One of: 'rotation', 'schedule', 'manual'
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('assignment_source', $source);
    }

    /**
     * Scope to get shifts generated after a specific date.
     *
     * Useful for: "Find all shifts generated in the last 7 days"
     */
    public function scopeGeneratedAfter($query, $date)
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        return $query->where('generated_at', '>=', $date);
    }

    /**
     * Scope to get shifts generated by a specific user.
     *
     * Useful for: "Audit trail - show what manager X generated"
     */
    public function scopeGeneratedBy($query, $userId)
    {
        return $query->where('generated_by_user_id', $userId);
    }

    /**
     * Get the shift duration in hours.
     */
    public function getShiftDurationAttribute(): float
    {
        if (!$this->shift_start || !$this->shift_end) {
            return 0;
        }

        try {
            $start = Carbon::createFromFormat('H:i:s', $this->shift_start);
            $end = Carbon::createFromFormat('H:i:s', $this->shift_end);

            // If end time is before start time, assume it's next day
            if ($end->lessThan($start)) {
                $end->addDay();
            }

            return round(abs($end->diffInMinutes($start)) / 60, 2);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Phase 4 Methods: Enhanced tracking, conflict details, and generation tracking
     */

    /**
     * Get formatted conflict details for display/reporting.
     *
     * @return array Formatted conflict information with details
     */
    public function getConflictDetails(): array
    {
        if (!$this->source_details) {
            return [];
        }

        $details = $this->source_details;

        return [
            'rotation_name' => $details['rotation_name'] ?? null,
            'schedule_name' => $details['schedule_name'] ?? null,
            'rotation_id' => $details['rotation_id'] ?? null,
            'schedule_id' => $details['schedule_id'] ?? null,
            'was_detected' => $this->conflict_detected,
            'generated_at' => $this->generated_at?->format('Y-m-d H:i:s'),
            'generated_by_user' => $this->generatedBy?->name,
        ];
    }

    /**
     * Mark this shift as generated from system (set generation metadata).
     *
     * Should be called when shift is created by generateShiftAssignments() or similar.
     *
     * @param  User  $user  The user who initiated the generation
     * @param  bool  $hadConflicts  Whether conflicts were detected but overridden
     * @return self
     */
    public function markAsGenerated(User $user, bool $hadConflicts = false): self
    {
        $this->generated_by_user_id = $user->id;
        $this->generated_at = Carbon::now();
        $this->conflict_detected = $hadConflicts;
        $this->save();

        return $this;
    }

    /**
     * Check if this shift was generated from a rotation pattern.
     *
     * @return bool
     */
    public function isFromRotation(): bool
    {
        return $this->assignment_source === 'rotation' && $this->rotation_assignment_id !== null;
    }

    /**
     * Check if this shift was generated from a schedule.
     *
     * @return bool
     */
    public function isFromSchedule(): bool
    {
        return $this->assignment_source === 'schedule';
    }

    /**
     * Check if this shift was created manually.
     *
     * @return bool
     */
    public function isManuallyCreated(): bool
    {
        return $this->assignment_source === 'manual';
    }

    /**
     * Check if this shift was generated with conflicts detected.
     *
     * @return bool
     */
    public function hadConflictsDetected(): bool
    {
        return $this->conflict_detected === true;
    }

    /**
     * Get the user profile for the person who generated this shift.
     *
     * @return string The user's name or email
     */
    public function getGeneratorNameAttribute(): ?string
    {
        return $this->generatedBy?->name ?? $this->generatedBy?->email;
    }

    /**
     * Calculate time since shift was generated (e.g., "2 hours ago").
     *
     * @return string Human-readable time difference
     */
    public function getTimeSinceGeneratedAttribute(): ?string
    {
        if (!$this->generated_at) {
            return null;
        }

        return $this->generated_at->diffForHumans();
    }

    /**
     * Calculate overtime hours based on schedule threshold.
     */
    public function calculateOvertimeHours(WorkSchedule $schedule): float
    {
        $threshold = $schedule->overtime_threshold ?? 8; // Default 8 hours
        $shiftDuration = $this->shift_duration;

        return max(0, $shiftDuration - $threshold);
    }

    /**
     * Detect if this shift has conflicts with other assignments for the same employee on the same date.
     */
    public function detectConflict(): array
    {
        $conflicts = ShiftAssignment::where('employee_id', $this->employee_id)
            ->whereDate('date', $this->date)
            ->where('id', '!=', $this->id ?? 0)
            ->where('deleted_at', null)
            ->get();

        $hasConflict = false;
        $conflictReason = null;

        foreach ($conflicts as $conflict) {
            // Check for time overlap: (start1 < end2 AND end1 > start2)
            if ($this->shift_start < $conflict->shift_end && $this->shift_end > $conflict->shift_start) {
                $hasConflict = true;
                $conflictReason = "Overlaps with shift {$conflict->shift_start} - {$conflict->shift_end}";
                break;
            }
        }

        return [
            'hasConflict' => $hasConflict,
            'reason' => $conflictReason,
        ];
    }

    /**
     * Mark this shift assignment as completed.
     */
    public function markAsCompleted(): self
    {
        $this->update(['status' => 'completed']);
        return $this;
    }

    /**
     * Update conflict status and reason.
     */
    public function updateConflictStatus(bool $hasConflict, ?string $reason = null): self
    {
        $this->update([
            'has_conflict' => $hasConflict,
            'conflict_reason' => $reason,
        ]);
        return $this;
    }

    /**
     * Check if this assignment has overtime.
     */
    public function hasOvertimeAttribute(): bool
    {
        return $this->is_overtime;
    }

    /**
     * Check if this shift is today or in the past.
     */
    public function isPastAttribute(): bool
    {
        return $this->date->lte(\Carbon\Carbon::now());
    }

    /**
     * Check if this shift is in the future.
     */
    public function isFutureAttribute(): bool
    {
        return $this->date->gt(\Carbon\Carbon::now());
    }

    /**
     * Get shift date as string.
     */
    public function getDateStringAttribute(): string
    {
        return $this->date->format('Y-m-d');
    }

    /**
     * Get full shift time as string (e.g., "08:00 - 17:00").
     */
    public function getShiftTimeStringAttribute(): string
    {
        return "{$this->shift_start} - {$this->shift_end}";
    }

    /**
     * Automatically detect and update conflict status.
     */
    public function resolveConflict(): self
    {
        $conflictData = $this->detectConflict();
        $this->updateConflictStatus($conflictData['hasConflict'], $conflictData['reason']);
        return $this;
    }

    /**
     * Mark as overtime with hours.
     */
    public function markAsOvertime(float $overtimeHours): self
    {
        $this->update([
            'is_overtime' => true,
            'overtime_hours' => $overtimeHours,
        ]);
        return $this;
    }

    /**
     * Get shift status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get shift type label for display.
     */
    public function getShiftTypeLabelAttribute(): ?string
    {
        if (!$this->shift_type) {
            return null;
        }

        $labels = [
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
            'night' => 'Night',
            'split' => 'Split',
            'custom' => 'Custom',
        ];

        return $labels[$this->shift_type] ?? $this->shift_type;
    }

    /**
     * Check if this assignment overlaps with another.
     */
    public function overlapsWith(ShiftAssignment $other): bool
    {
        if ($this->employee_id !== $other->employee_id || $this->date->ne($other->date)) {
            return false;
        }

        // Check if (start1 < end2 AND end1 > start2)
        return $this->shift_start < $other->shift_end && $this->shift_end > $other->shift_start;
    }

    /**
     * Get all shift assignments for the same employee on the same day.
     */
    public function getSameDayAssignments(): \Illuminate\Database\Eloquent\Collection
    {
        return ShiftAssignment::where('employee_id', $this->employee_id)
            ->whereDate('date', $this->date)
            ->where('id', '!=', $this->id ?? 0)
            ->where('deleted_at', null)
            ->get();
    }
}
