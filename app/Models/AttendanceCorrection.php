<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceCorrection Model
 * 
 * Task 4.4: Manages manual corrections to attendance records with audit trail.
 * Corrections are stored separately from attendance_events to preserve ledger integrity.
 * 
 * @property int $id
 * @property int $attendance_event_id Reference to attendance event being corrected
 * @property int $requested_by_user_id User who requested the correction
 * @property int|null $approved_by_user_id User who approved the correction
 * @property string|null $original_time_in Original time in before correction
 * @property string|null $original_time_out Original time out before correction
 * @property string|null $original_break_start Original break start before correction
 * @property string|null $original_break_end Original break end before correction
 * @property string|null $corrected_time_in Corrected time in
 * @property string|null $corrected_time_out Corrected time out
 * @property string|null $corrected_break_start Corrected break start
 * @property string|null $corrected_break_end Corrected break end
 * @property float $hours_difference Difference in hours (corrected - original)
 * @property string $correction_reason Reason code for correction
 * @property string $justification Detailed justification
 * @property string|null $rejection_reason Reason for rejection if rejected
 * @property string $status pending, approved, rejected
 * @property \Carbon\Carbon $requested_at When correction was requested
 * @property \Carbon\Carbon|null $processed_at When correction was approved/rejected
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $table = 'attendance_corrections';

    protected $fillable = [
        'attendance_event_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'original_time_in',
        'original_time_out',
        'original_break_start',
        'original_break_end',
        'corrected_time_in',
        'corrected_time_out',
        'corrected_break_start',
        'corrected_break_end',
        'hours_difference',
        'correction_reason',
        'justification',
        'rejection_reason',
        'status',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'hours_difference' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the attendance event being corrected.
     */
    public function attendanceEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }

    /**
     * Get the user who requested the correction.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Get the user who approved the correction.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Scope to get pending corrections.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved corrections.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected corrections.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Check if correction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if correction is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if correction is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
