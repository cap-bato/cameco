<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_payroll_calculation_id',
        'payroll_period_id',
        'employee_id',
        'adjustment_type',
        'category',
        'component',
        'amount',
        'original_amount',
        'adjusted_amount',
        'reason',
        'justification',
        'reference_number',
        'supporting_documents',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'applied_at',
        'approved_by',
        'rejected_by',
        'rejection_reason',
        'review_notes',
        'impact_on_net_pay',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'impact_on_net_pay' => 'decimal:2',
        'supporting_documents' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected $appends = [
        'employee_name',
        'employee_number',
        'department',
        'position',
        'requested_by',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function employeePayrollCalculation(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Aliases for controller compatibility
    public function approvedByUser(): BelongsTo
    {
        return $this->approvedBy();
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->rejectedBy();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->createdBy();
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getEmployeeNameAttribute(): string
    {
        return $this->employee?->profile?->full_name
            ?? $this->employee?->user?->name
            ?? 'Unknown';
    }

    public function getEmployeeNumberAttribute(): string
    {
        return $this->employee?->employee_number ?? '';
    }

    public function getDepartmentAttribute(): string
    {
        return $this->employee?->department?->name ?? '';
    }

    public function getPositionAttribute(): string
    {
        return $this->employee?->currentPosition?->title
            ?? $this->employee?->position?->title
            ?? '';
    }

    public function getRequestedByAttribute(): string
    {
        return $this->createdBy?->name ?? 'System';
    }

    public function getRequestedAtAttribute(): ?string
    {
        return ($this->submitted_at ?? $this->created_at)?->toIso8601String();
    }

    public function getReviewedByAttribute(): ?string
    {
        return $this->approvedBy?->name ?? $this->rejectedBy?->name;
    }

    public function getReviewedAtAttribute(): ?string
    {
        return ($this->approved_at ?? $this->rejected_at)?->toIso8601String();
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('adjustment_type', $type);
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function canApprove(): bool
    {
        return $this->status === 'pending';
    }

    public function canReject(): bool
    {
        return $this->status === 'pending';
    }

    public function canApply(): bool
    {
        return $this->status === 'approved' && !$this->applied_at;
    }

    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);
    }

    public function reject(User $user, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'rejection_reason' => $reason,
        ]);
    }

    public function apply(): void
    {
        $this->update([
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }

    public function getSignedAmount(): float
    {
        $amount = (float) $this->amount;
        
        // Earning, backpay, and refund adjustments are positive (add to pay)
        if (in_array($this->adjustment_type, ['earning', 'backpay', 'refund', 'addition'])) {
            return $amount;
        }
        
        // Deduction adjustments are negative (subtract from pay)
        if ($this->adjustment_type === 'deduction') {
            return -$amount;
        }
        
        // Correction adjustments use the difference if original and adjusted amounts are set
        if (in_array($this->adjustment_type, ['correction', 'override'])) {
            if ($this->original_amount && $this->adjusted_amount) {
                return (float) $this->adjusted_amount - (float) $this->original_amount;
            }
            // If no original/adjusted amounts, treat as positive
            return $amount;
        }
        
        return $amount;
    }

    public function calculateImpact(): float
    {
        return $this->getSignedAmount();
    }

    public function needsApproval(): bool
    {
        // Adjustments over ₱1,000 require approval
        return abs((float) $this->amount) >= 1000;
    }

    public function hasSupportingDocuments(): bool
    {
        return !empty($this->supporting_documents);
    }
}
