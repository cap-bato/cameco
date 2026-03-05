<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanDeduction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'loan_deductions';

    protected $fillable = [
        'employee_loan_id',
        'installment_number',
        'due_date',
        'paid_date',
        'principal_deduction',
        'interest_deduction',
        'total_deduction',
        'penalty_amount',
        'amount_deducted',
        'amount_paid',
        'balance_after_payment',
        'status',
        'deducted_at',
        'reference_number',
        'payroll_calculation_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_deduction' => 'decimal:2',
        'interest_deduction' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'amount_deducted' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_after_payment' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'deducted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the employee loan this deduction belongs to
     */
    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }

    /**
     * Get the payroll calculation record (if deducted in payroll)
     */
    public function payrollCalculation(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculation::class, 'payroll_calculation_id');
    }

    /**
     * Get the user who created this deduction record
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this deduction record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only deducted installments
     */
    public function scopeDeducted($query)
    {
        return $query->where('status', 'deducted');
    }

    /**
     * Get pending deductions (not yet deducted)
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get paid installments
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Get overdue installments
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
                     ->where('due_date', '<', now()->toDateString());
    }

    /**
     * Get partial paid installments
     */
    public function scopePartialPaid($query)
    {
        return $query->where('status', 'partial_paid');
    }

    /**
     * Get deductions for a specific loan
     */
    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('employee_loan_id', $loanId);
    }

    /**
     * Get deductions by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get deductions ordered by installment number
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('installment_number', 'asc');
    }

    // Accessors
    /**
     * Get formatted principal deduction with currency symbol
     */
    public function getFormattedPrincipalAttribute(): string
    {
        return '₱' . number_format($this->principal_deduction ?? 0, 2);
    }

    /**
     * Get formatted interest deduction with currency symbol
     */
    public function getFormattedInterestAttribute(): string
    {
        return '₱' . number_format($this->interest_deduction ?? 0, 2);
    }

    /**
     * Get formatted total deduction with currency symbol
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format($this->total_deduction ?? 0, 2);
    }

    /**
     * Get formatted penalty amount with currency symbol
     */
    public function getFormattedPenaltyAttribute(): string
    {
        return '₱' . number_format($this->penalty_amount ?? 0, 2);
    }

    /**
     * Get formatted amount deducted with currency symbol
     */
    public function getFormattedDeductedAttribute(): string
    {
        return '₱' . number_format($this->amount_deducted ?? 0, 2);
    }

    /**
     * Get formatted balance after payment with currency symbol
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '₱' . number_format($this->balance_after_payment ?? 0, 2);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending - Awaiting Deduction',
            'deducted' => 'Deducted - Applied in Payroll',
            'paid' => 'Paid - Fully Settled',
            'overdue' => 'Overdue - Past Due Date',
            'partial_paid' => 'Partial Paid - Installment Incomplete',
            'waived' => 'Waived - Forgiven',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    // Methods
    /**
     * Check if this installment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date < now()->toDateString() && !in_array($this->status, ['paid', 'deducted']);
    }

    /**
     * Check if this installment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if this installment has been deducted
     */
    public function isDeducted(): bool
    {
        return $this->status === 'deducted';
    }

    /**
     * Check if this installment is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Mark this deduction as deducted in payroll
     */
    public function markAsDeducted(?int $payrollCalculationId = null): void
    {
        $this->status = 'deducted';
        $this->deducted_at = now();
        $this->amount_deducted = $this->total_deduction + ($this->penalty_amount ?? 0);
        
        if ($payrollCalculationId) {
            $this->payroll_calculation_id = $payrollCalculationId;
        }

        if (auth()->check()) {
            $this->updated_by = auth()->id();
        }

        $this->save();
    }

    /**
     * Mark this deduction as paid
     */
    public function markAsPaid(float $amountPaid): void
    {
        $this->status = 'paid';
        $this->paid_date = now()->toDateString();
        $this->amount_paid = $amountPaid;
        $this->balance_after_payment = $this->total_deduction - $amountPaid;

        if (auth()->check()) {
            $this->updated_by = auth()->id();
        }

        $this->save();
    }

    /**
     * Mark this deduction as partial paid
     */
    public function markAsPartialPaid(float $amountPaid): void
    {
        $this->status = 'partial_paid';
        $this->paid_date = now()->toDateString();
        $this->amount_paid = $amountPaid;
        $this->balance_after_payment = max(0, $this->total_deduction - $amountPaid);

        if (auth()->check()) {
            $this->updated_by = auth()->id();
        }

        $this->save();
    }

    /**
     * Mark this deduction as overdue
     */
    public function markAsOverdue(): void
    {
        $this->status = 'overdue';

        if (auth()->check()) {
            $this->updated_by = auth()->id();
        }

        $this->save();
    }

    /**
     * Waive this installment (forgive the debt)
     */
    public function waive(?string $reason = null): void
    {
        $this->status = 'waived';
        
        if ($reason) {
            // Store reason in reference_number or notes field if available
            $this->reference_number = 'WAIVED: ' . $reason;
        }

        if (auth()->check()) {
            $this->updated_by = auth()->id();
        }

        $this->save();
    }

    /**
     * Calculate the outstanding amount on this installment
     */
    public function getOutstandingAmount(): float
    {
        return max(0, $this->total_deduction + ($this->penalty_amount ?? 0) - ($this->amount_paid ?? 0));
    }

    /**
     * Get days overdue (if applicable)
     */
    public function getDaysOverdue(): ?int
    {
        if (!$this->isOverdue()) {
            return null;
        }

        return now()->diffInDays($this->due_date);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set created_by if not already set
        static::creating(function ($deduction) {
            if (!$deduction->created_by && auth()->check()) {
                $deduction->created_by = auth()->id();
            }
        });

        // Set updated_by on updates
        static::updating(function ($deduction) {
            if (auth()->check()) {
                $deduction->updated_by = auth()->id();
            }
        });
    }
}
