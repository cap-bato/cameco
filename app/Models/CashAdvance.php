<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_cash_advances';

    protected $fillable = [
        'advance_number',
        'employee_id',
        'department_id',
        'advance_type',
        'amount_requested',
        'amount_approved',
        'purpose',
        'priority_level',
        'supporting_documents',
        'requested_date',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'deduction_status',
        'deduction_schedule',
        'number_of_installments',
        'installments_completed',
        'deduction_amount_per_period',
        'total_deducted',
        'remaining_balance',
        'completed_at',
        'completion_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'amount_approved' => 'decimal:2',
        'deduction_amount_per_period' => 'decimal:2',
        'total_deducted' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'requested_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'supporting_documents' => 'array',
        'number_of_installments' => 'integer',
        'installments_completed' => 'integer',
    ];

    // ====== RELATIONSHIPS ======

    /**
     * Employee who requested the advance
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Department of the employee
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * User who approved the advance
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who created the advance request
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated the advance
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Deductions scheduled for this advance
     */
    public function advanceDeductions(): HasMany
    {
        return $this->hasMany(AdvanceDeduction::class, 'cash_advance_id');
    }

    // ====== SCOPES ======

    /**
     * Get active advances (currently being deducted)
     */
    public function scopeActive($query)
    {
        return $query->where('deduction_status', 'active');
    }

    /**
     * Get pending advances (awaiting approval)
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Get approved advances
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Get completed advances
     */
    public function scopeCompleted($query)
    {
        return $query->where('deduction_status', 'completed');
    }

    /**
     * Get advances for a specific employee
     */
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get advances by approval status
     */
    public function scopeByApprovalStatus($query, string $status)
    {
        return $query->where('approval_status', $status);
    }

    /**
     * Get advances by deduction status
     */
    public function scopeByDeductionStatus($query, string $status)
    {
        return $query->where('deduction_status', $status);
    }

    // ====== ACCESSORS ======

    /**
     * Get formatted amount requested with currency symbol
     */
    public function getFormattedAmountRequestedAttribute(): string
    {
        return '₱' . number_format($this->amount_requested, 2);
    }

    /**
     * Get formatted amount approved with currency symbol
     */
    public function getFormattedAmountApprovedAttribute(): string
    {
        return '₱' . number_format($this->amount_approved ?? 0, 2);
    }

    /**
     * Get formatted remaining balance with currency symbol
     */
    public function getFormattedRemainingBalanceAttribute(): string
    {
        return '₱' . number_format($this->remaining_balance ?? 0, 2);
    }

    /**
     * Get formatted total deducted with currency symbol
     */
    public function getFormattedTotalDeductedAttribute(): string
    {
        return '₱' . number_format($this->total_deducted ?? 0, 2);
    }

    // ====== MUTATORS ======

    /**
     * Auto-calculate deduction amount per period and remaining balance on save
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($advance) {
            // Calculate deduction amount per period
            if ($advance->amount_approved && $advance->number_of_installments) {
                $advance->deduction_amount_per_period = $advance->amount_approved / $advance->number_of_installments;
            }

            // Calculate remaining balance
            if ($advance->amount_approved) {
                $advance->remaining_balance = $advance->amount_approved - ($advance->total_deducted ?? 0);
            }
        });
    }

    // ====== HELPER METHODS ======

    /**
     * Check if advance is eligible for deduction
     */
    public function isEligibleForDeduction(): bool
    {
        return $this->approval_status === 'approved' 
            && $this->deduction_status === 'active' 
            && $this->remaining_balance > 0;
    }

    /**
     * Mark advance as completed
     */
    public function markAsCompleted(string $reason = 'fully_paid'): void
    {
        $this->update([
            'deduction_status' => 'completed',
            'completion_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the advance
     */
    public function cancel(): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'deduction_status' => 'cancelled',
        ]);
    }

    /**
     * Get deduction progress percentage
     */
    public function getProgressPercentage(): int
    {
        if (!$this->amount_approved || $this->amount_approved == 0) {
            return 0;
        }

        $percentage = ($this->total_deducted / $this->amount_approved) * 100;
        return (int) min(100, round($percentage));
    }
}
