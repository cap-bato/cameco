<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollApprovalHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_approval_history';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payroll_period_id',
        'approval_step',
        'action',
        'status_from',
        'status_to',
        'user_id',
        'user_name',
        'user_role',
        'comments',
        'rejection_reason',
        'period_snapshot',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'period_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStep($query, string $step)
    {
        return $query->where('approval_step', $step);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeApprovals($query)
    {
        return $query->where('action', 'approve');
    }

    public function scopeRejections($query)
    {
        return $query->where('action', 'reject');
    }

    public function scopeSubmissions($query)
    {
        return $query->where('action', 'submit');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOrderByRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isApproval(): bool
    {
        return $this->action === 'approve';
    }

    public function isRejection(): bool
    {
        return $this->action === 'reject';
    }

    public function isSubmission(): bool
    {
        return $this->action === 'submit';
    }

    public function isLock(): bool
    {
        return $this->action === 'lock';
    }

    public function isUnlock(): bool
    {
        return $this->action === 'unlock';
    }

    public function hasComments(): bool
    {
        return !empty($this->comments);
    }

    public function hasRejectionReason(): bool
    {
        return !empty($this->rejection_reason);
    }

    public function hasSnapshot(): bool
    {
        return !empty($this->period_snapshot);
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'approve' => 'green',
            'reject' => 'red',
            'submit' => 'blue',
            'lock' => 'purple',
            'unlock' => 'orange',
            default => 'gray',
        };
    }

    public function getActionIcon(): string
    {
        return match ($this->action) {
            'approve' => 'check-circle',
            'reject' => 'x-circle',
            'submit' => 'send',
            'lock' => 'lock',
            'unlock' => 'unlock',
            default => 'circle',
        };
    }

    public function getStepLabel(): string
    {
        return match ($this->approval_step) {
            'payroll_officer_submit' => 'Payroll Officer Submission',
            'hr_manager_review' => 'HR Manager Review',
            'hr_manager_approve' => 'HR Manager Approval',
            'hr_manager_reject' => 'HR Manager Rejection',
            'office_admin_review' => 'Office Admin Review',
            'office_admin_approve' => 'Office Admin Approval',
            'office_admin_reject' => 'Office Admin Rejection',
            'locked' => 'Period Locked',
            'unlocked' => 'Period Unlocked',
            default => $this->approval_step,
        };
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'submit' => 'Submitted',
            'lock' => 'Locked',
            'unlock' => 'Unlocked',
            default => ucfirst($this->action),
        };
    }

    // ============================================================
    // Static Methods for Logging
    // ============================================================

    public static function logSubmission(
        PayrollPeriod $period,
        User $user,
        ?string $comments = null
    ): self {
        return self::create([
            'payroll_period_id' => $period->id,
            'approval_step' => 'payroll_officer_submit',
            'action' => 'submit',
            'status_from' => $period->getOriginal('status'),
            'status_to' => $period->status,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->getRoleNames()->first() ?? 'Payroll Officer',
            'comments' => $comments,
            'period_snapshot' => [
                'total_employees' => $period->total_employees,
                'total_gross_pay' => $period->total_gross_pay,
                'total_net_pay' => $period->total_net_pay,
                'exceptions_count' => $period->exceptions_count,
            ],
            'created_at' => now(),
        ]);
    }

    public static function logApproval(
        PayrollPeriod $period,
        User $user,
        string $approvalStep,
        ?string $comments = null
    ): self {
        return self::create([
            'payroll_period_id' => $period->id,
            'approval_step' => $approvalStep,
            'action' => 'approve',
            'status_from' => $period->getOriginal('status'),
            'status_to' => $period->status,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->getRoleNames()->first() ?? 'Unknown',
            'comments' => $comments,
            'period_snapshot' => [
                'total_employees' => $period->total_employees,
                'total_gross_pay' => $period->total_gross_pay,
                'total_net_pay' => $period->total_net_pay,
                'exceptions_count' => $period->exceptions_count,
            ],
            'created_at' => now(),
        ]);
    }

    public static function logRejection(
        PayrollPeriod $period,
        User $user,
        string $approvalStep,
        string $reason,
        ?string $comments = null
    ): self {
        return self::create([
            'payroll_period_id' => $period->id,
            'approval_step' => $approvalStep,
            'action' => 'reject',
            'status_from' => $period->getOriginal('status'),
            'status_to' => $period->status,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->getRoleNames()->first() ?? 'Unknown',
            'comments' => $comments,
            'rejection_reason' => $reason,
            'period_snapshot' => [
                'total_employees' => $period->total_employees,
                'total_gross_pay' => $period->total_gross_pay,
                'total_net_pay' => $period->total_net_pay,
                'exceptions_count' => $period->exceptions_count,
            ],
            'created_at' => now(),
        ]);
    }

    public static function logLock(
        PayrollPeriod $period,
        User $user
    ): self {
        return self::create([
            'payroll_period_id' => $period->id,
            'approval_step' => 'locked',
            'action' => 'lock',
            'status_from' => $period->getOriginal('status'),
            'status_to' => $period->status,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->getRoleNames()->first() ?? 'Unknown',
            'period_snapshot' => [
                'total_employees' => $period->total_employees,
                'total_gross_pay' => $period->total_gross_pay,
                'total_net_pay' => $period->total_net_pay,
                'locked_at' => $period->locked_at,
            ],
            'created_at' => now(),
        ]);
    }

    public static function logUnlock(
        PayrollPeriod $period,
        User $user,
        string $reason
    ): self {
        return self::create([
            'payroll_period_id' => $period->id,
            'approval_step' => 'unlocked',
            'action' => 'unlock',
            'status_from' => $period->getOriginal('status'),
            'status_to' => $period->status,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->getRoleNames()->first() ?? 'Unknown',
            'comments' => $reason,
            'created_at' => now(),
        ]);
    }
}
