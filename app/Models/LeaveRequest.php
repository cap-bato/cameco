<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'leave_policy_id',
        'start_date',
        'end_date',
        'days_requested',
        'reason',
        'status',
        'supervisor_id',
        'supervisor_approved_at',
        'supervisor_comments',
        'manager_id',
        'manager_approved_at',
        'manager_comments',
        'approved_by_manager_id',
        'approved_by_admin_id',
        'auto_approved',
        'coverage_percentage',
        'admin_approved_at',
        'hr_processed_by',
        'hr_processed_at',
        'hr_notes',
        'submitted_at',
        'submitted_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'days_requested' => 'decimal:1',
        'coverage_percentage' => 'decimal:2',
        'auto_approved' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'supervisor_approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'hr_processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function hrProcessedBy()
    {
        return $this->belongsTo(User::class, 'hr_processed_by');
    }

    public function approvedByManager()
    {
        return $this->belongsTo(User::class, 'approved_by_manager_id');
    }

    public function approvedByAdmin()
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    /**
     * Check if leave request is eligible for auto-approval (1-2 days)
     */
    public function isAutoApprovable(): bool
    {
        return $this->days_requested <= 2;
    }

    /**
     * Check if leave request requires HR Manager approval (3+ days)
     */
    public function requiresManagerApproval(): bool
    {
        return $this->days_requested >= 3;
    }

    /**
     * Check if leave request requires Office Admin approval (6+ days)
     */
    public function requiresAdminApproval(): bool
    {
        return $this->days_requested >= 6;
    }

    /**
     * Check if the requestor is an HR Manager
     */
    public function isRequestorManager(): bool
    {
        return $this->employee->user?->hasRole('HR Manager') ?? false;
    }

    /**
     * Check if leave request is pending HR Manager approval
     */
    public function isPendingManagerApproval(): bool
    {
        return $this->status === 'pending' 
            && $this->manager_approved_at === null 
            && $this->requiresManagerApproval();
    }

    /**
     * Check if leave request is pending Office Admin approval
     */
    public function isPendingAdminApproval(): bool
    {
        return $this->status === 'pending'
            && $this->manager_approved_at !== null
            && $this->admin_approved_at === null
            && $this->requiresAdminApproval();
    }
}
