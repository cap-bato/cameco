<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * OvertimeRequest Model
 * 
 * @property int $id
 * @property int $employee_id
 * @property Carbon $request_date
 * @property Carbon $planned_start_time
 * @property Carbon $planned_end_time
 * @property float $planned_hours
 * @property string $reason
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property Carbon|null $actual_start_time
 * @property Carbon|null $actual_end_time
 * @property float|null $actual_hours
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property-read Employee $employee
 * @property-read User $approver
 * @property-read User $creator
 */
class OvertimeRequest extends Model
{
    use HasFactory;
    
    protected $table = 'overtime_requests';
    
    protected $fillable = [
        'employee_id',
        'request_date',
        'planned_start_time',
        'planned_end_time',
        'planned_hours',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'actual_start_time',
        'actual_end_time',
        'actual_hours',
        'created_by',
    ];
    
    protected $casts = [
        'request_date' => 'date',
        'planned_start_time' => 'datetime',
        'planned_end_time' => 'datetime',
        'planned_hours' => 'decimal:2',
        'approved_at' => 'datetime',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'actual_hours' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Relationship: Employee who requested overtime
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
    
    /**
     * Relationship: User who approved/rejected the request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Relationship: User who created the request
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope: Filter by employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
    
    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('request_date', [$startDate, $endDate]);
    }
    
    /**
     * Check if request is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    
    /**
     * Check if request is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    
    /**
     * Approve the overtime request
     */
    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }
    
    /**
     * Reject the overtime request
     */
    public function reject(int $approvedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
    
    /**
     * Mark as completed with actual hours
     */
    public function complete(float $actualHours, ?Carbon $actualStart = null, ?Carbon $actualEnd = null): void
    {
        $this->update([
            'status' => 'completed',
            'actual_hours' => $actualHours,
            'actual_start_time' => $actualStart,
            'actual_end_time' => $actualEnd,
        ]);
    }
}
