<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
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
        'effective_date',
        'expires_at',
        'status',
        'monday_start',
        'monday_end',
        'tuesday_start',
        'tuesday_end',
        'wednesday_start',
        'wednesday_end',
        'thursday_start',
        'thursday_end',
        'friday_start',
        'friday_end',
        'saturday_start',
        'saturday_end',
        'sunday_start',
        'sunday_end',
        'lunch_break_duration',
        'morning_break_duration',
        'afternoon_break_duration',
        'overtime_threshold',
        'overtime_rate_multiplier',
        'department_id',
        'is_template',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'effective_date' => 'date',
        'expires_at' => 'date',
        'lunch_break_duration' => 'integer',
        'morning_break_duration' => 'integer',
        'afternoon_break_duration' => 'integer',
        'overtime_threshold' => 'integer',
        'overtime_rate_multiplier' => 'decimal:2',
        'is_template' => 'boolean',
    ];

    /**
     * Get the department that owns the work schedule.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who created the work schedule.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the employee schedules for this work schedule.
     */
    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class, 'work_schedule_id');
    }

    /**
     * Get the shift assignments for this work schedule.
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'schedule_id');
    }

    /**
     * Scope to get active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get expired schedules.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to get template schedules.
     */
    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    /**
     * Get the working days for this schedule.
     * Returns array of day names that have working hours.
     */
    public function getWorkDaysAttribute(): array
    {
        $workDays = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if ($this->{$day . '_start'} && $this->{$day . '_end'}) {
                $workDays[] = ucfirst($day);
            }
        }

        return $workDays;
    }

    /**
     * Get the rest days for this schedule.
     * Returns array of day names that have no working hours.
     */
    public function getRestDaysAttribute(): array
    {
        $restDays = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if (!$this->{$day . '_start'} && !$this->{$day . '_end'}) {
                $restDays[] = ucfirst($day);
            }
        }

        return $restDays;
    }

    /**
     * Activate the work schedule.
     * Set status to 'active' if within validity period.
     */
    public function activate(): self
    {
        $this->update(['status' => 'active']);
        return $this;
    }

    /**
     * Expire the work schedule.
     * Set status to 'expired'.
     */
    public function expire(): self
    {
        $this->update(['status' => 'expired']);
        return $this;
    }

    /**
     * Assign this schedule to an employee.
     */
    public function assignToEmployee(Employee $employee, $effectiveDate, $endDate = null): EmployeeSchedule
    {
        return EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $this->id,
            'effective_date' => $effectiveDate,
            'end_date' => $endDate,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Calculate total work hours per week.
     */
    public function getTotalWeeklyHoursAttribute(): float
    {
        $totalHours = 0;
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $start = $this->{$day . '_start'};
            $end = $this->{$day . '_end'};

            if ($start && $end) {
                $startTime = \Carbon\Carbon::createFromFormat('H:i:s', $start);
                $endTime = \Carbon\Carbon::createFromFormat('H:i:s', $end);

                if ($endTime->lessThan($startTime)) {
                    $endTime->addDay();
                }

                $hours = $endTime->diffInMinutes($startTime) / 60;
                $totalHours += $hours;
            }
        }

        return $totalHours;
    }

    /**
     * Check if at least one working day is defined.
     */
    public function hasWorkingDay(): bool
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if ($this->{$day . '_start'} && $this->{$day . '_end'}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get schedule times for a specific day of the week.
     * 
     * @param string $day Day name (monday, tuesday, etc.)
     * @return array|null Array with 'start' and 'end' keys, or null if not a working day
     */
    public function getDaySchedule(string $day): ?array
    {
        $day = strtolower($day);
        $start = $this->{$day . '_start'};
        $end = $this->{$day . '_end'};

        if ($start && $end) {
            return [
                'start' => $start,
                'end' => $end,
            ];
        }

        return null;
    }

    /**
     * Get all day schedules as an array.
     */
    public function getAllDaySchedules(): array
    {
        $schedules = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $daySchedule = $this->getDaySchedule($day);
            if ($daySchedule) {
                $schedules[$day] = $daySchedule;
            }
        }

        return $schedules;
    }

    /**
     * Check if schedule is currently within validity period.
     */
    public function isWithinValidityPeriod(): bool
    {
        $today = \Carbon\Carbon::now();
        $isEffective = $today->gte($this->effective_date);
        $isNotExpired = is_null($this->expires_at) || $today->lte($this->expires_at);

        return $isEffective && $isNotExpired;
    }

    /**
     * Auto-activate schedule if within validity period.
     */
    public function autoActivate(): bool
    {
        if ($this->isWithinValidityPeriod() && $this->status === 'draft') {
            $this->activate();
            return true;
        }

        return false;
    }

    /**
     * Auto-expire schedule if past expires_at date.
     */
    public function autoExpire(): bool
    {
        if (!is_null($this->expires_at) && \Carbon\Carbon::now()->gt($this->expires_at) && $this->status === 'active') {
            $this->expire();
            return true;
        }

        return false;
    }

    /**
     * Check if this schedule has any active employee assignments.
     */
    public function hasActiveAssignments(): bool
    {
        return $this->employeeSchedules()
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get count of active employees assigned to this schedule.
     */
    public function getActiveEmployeeCountAttribute(): int
    {
        return $this->employeeSchedules()
            ->where('is_active', true)
            ->distinct('employee_id')
            ->count();
    }
}
