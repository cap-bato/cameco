<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DailyAttendanceSummary Model
 * 
 * Aggregated daily attendance record with ledger integrity tracking.
 * Combines time tracking data with business rules (late, absent, overtime).
 * 
 * Task 5.1.3: Database model for daily_attendance_summary with ledger linking
 * 
 * @property int $id
 * @property int $employee_id Reference to employee
 * @property \Carbon\Carbon $attendance_date Date of attendance
 * @property int $work_schedule_id Applied work schedule
 * @property \Carbon\Carbon|null $time_in Clock-in timestamp
 * @property \Carbon\Carbon|null $time_out Clock-out timestamp
 * @property \Carbon\Carbon|null $break_start Break start time
 * @property \Carbon\Carbon|null $break_end Break end time
 * @property float|null $total_hours_worked Total hours for the day
 * @property float|null $regular_hours Regular (non-overtime) hours
 * @property float|null $overtime_hours Overtime hours
 * @property int|null $break_duration Total break duration in minutes
 * @property bool $is_present Employee was present
 * @property bool $is_late Employee clocked in late
 * @property bool $is_undertime Worked fewer hours than scheduled
 * @property bool $is_overtime Worked overtime
 * @property int|null $late_minutes Minutes late from scheduled start
 * @property int|null $undertime_minutes Minutes short of scheduled
 * @property int|null $leave_request_id Approved leave for this date
 * @property bool $is_on_leave On approved leave
 * @property int|null $ledger_sequence_start First rfid_ledger sequence_id
 * @property int|null $ledger_sequence_end Last rfid_ledger sequence_id
 * @property bool $ledger_verified All events verified (Task 5.1.3)
 * @property \Carbon\Carbon|null $calculated_at When summary was calculated
 * @property bool $is_finalized Locked for payroll
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DailyAttendanceSummary extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'daily_attendance_summary';

    // Mass assignable attributes
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'work_schedule_id',
        'time_in',
        'time_out',
        'break_start',
        'break_end',
        'total_hours_worked',
        'regular_hours',
        'overtime_hours',
        'break_duration',
        'is_present',
        'is_late',
        'is_undertime',
        'is_overtime',
        'late_minutes',
        'undertime_minutes',
        'leave_request_id',
        'is_on_leave',
        'ledger_sequence_start',
        'ledger_sequence_end',
        'ledger_verified',
        'calculated_at',
        'is_finalized',
        'correction_applied',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'attendance_date' => 'date',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'total_hours_worked' => 'float',
        'regular_hours' => 'float',
        'overtime_hours' => 'float',
        'break_duration' => 'integer',
        'late_minutes' => 'integer',
        'undertime_minutes' => 'integer',
        'is_present' => 'boolean',
        'is_late' => 'boolean',
        'is_undertime' => 'boolean',
        'is_overtime' => 'boolean',
        'is_on_leave' => 'boolean',
        'ledger_verified' => 'boolean',
        'is_finalized' => 'boolean',
        'correction_applied' => 'boolean',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    // Scopes
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeInDateRange($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    public function scopePresent($query)
    {
        return $query->where('is_present', true);
    }

    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    public function scopeUndertime($query)
    {
        return $query->where('is_undertime', true);
    }

    public function scopeOvertime($query)
    {
        return $query->where('is_overtime', true);
    }

    public function scopeOnLeave($query)
    {
        return $query->where('is_on_leave', true);
    }

    public function scopeFinalized($query)
    {
        return $query->where('is_finalized', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_finalized', false);
    }

    public function scopeLedgerVerified($query)
    {
        return $query->where('ledger_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('ledger_verified', false);
    }

    // Helper methods
    public function markFinalized(): void
    {
        $this->update(['is_finalized' => true]);
    }

    public function markUnfinalized(): void
    {
        $this->update(['is_finalized' => false]);
    }

    public function isAbsent(): bool
    {
        return !$this->is_present && !$this->is_on_leave;
    }

    public function getAttendanceStatus(): string
    {
        if ($this->is_on_leave) {
            return 'on_leave';
        }

        if (!$this->is_present) {
            return 'absent';
        }

        if ($this->is_late) {
            return 'late';
        }

        if ($this->is_undertime) {
            return 'undertime';
        }

        if ($this->is_overtime) {
            return 'overtime';
        }

        return 'present';
    }

    public function hasLedgerIssues(): bool
    {
        return !$this->ledger_verified;
    }
}
