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
        
        // Addition adjustments are positive
        if ($this->adjustment_type === 'addition') {
            return $amount;
        }
        
        // Deduction adjustments are negative
        if ($this->adjustment_type === 'deduction') {
            return -$amount;
        }
        
        // Override adjustments use the difference
        if ($this->adjustment_type === 'override' && $this->original_amount && $this->adjusted_amount) {
            return (float) $this->adjusted_amount - (float) $this->original_amount;
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
