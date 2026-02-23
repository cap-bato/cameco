<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_loans';

    protected $fillable = [
        'employee_id',
        'loan_type',
        'loan_type_label',
        'loan_number',
        'principal_amount',
        'interest_rate',
        'interest_amount',
        'total_loan_amount',
        'number_of_installments',
        'installment_amount',
        'installments_paid',
        'total_paid',
        'remaining_balance',
        'loan_date',
        'first_deduction_date',
        'last_deduction_date',
        'status',
        'completion_date',
        'completion_reason',
        'external_loan_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_loan_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'loan_date' => 'date',
        'first_deduction_date' => 'date',
        'last_deduction_date' => 'date',
        'completion_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the employee this loan belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get all deductions for this loan
     */
    public function loanDeductions(): HasMany
    {
        return $this->hasMany(LoanDeduction::class);
    }

    /**
     * Get the user who created this loan record
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this loan record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only active loans
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get loans for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get loans by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('loan_type', $type);
    }

    /**
     * Get loans by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get completed loans
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get defaulted loans
     */
    public function scopeDefaulted($query)
    {
        return $query->where('status', 'defaulted');
    }

    /**
     * Get loans ordered by loan_date (newest first)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('loan_date', 'desc')->orderBy('created_at', 'desc');
    }

    // Accessors
    /**
     * Get formatted principal amount with currency symbol
     */
    public function getFormattedPrincipalAttribute(): string
    {
        return '₱' . number_format($this->principal_amount ?? 0, 2);
    }

    /**
     * Get formatted total loan amount with currency symbol
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format($this->total_loan_amount ?? 0, 2);
    }

    /**
     * Get formatted installment amount with currency symbol
     */
    public function getFormattedInstallmentAttribute(): string
    {
        return '₱' . number_format($this->installment_amount ?? 0, 2);
    }

    /**
     * Get formatted remaining balance with currency symbol
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '₱' . number_format($this->remaining_balance ?? 0, 2);
    }

    /**
     * Get formatted total paid with currency symbol
     */
    public function getFormattedTotalPaidAttribute(): string
    {
        return '₱' . number_format($this->total_paid ?? 0, 2);
    }

    /**
     * Get loan type label in human-readable format
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->loan_type) {
            'sss_loan' => 'SSS Salary Loan',
            'pagibig_loan' => 'Pag-IBIG Multi-Purpose Loan',
            'pagibig_housing_loan' => 'Pag-IBIG Housing Loan',
            'company_loan' => 'Company Salary Loan',
            'personal_loan' => 'Personal Loan',
            'emergency_loan' => 'Emergency Loan',
            default => ucfirst(str_replace('_', ' ', $this->loan_type)),
        };
    }

    /**
     * Get status label with styling information
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'active' => 'Active - Being Deducted',
            'completed' => 'Completed - Fully Paid',
            'defaulted' => 'Defaulted - In Arrears',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get repayment progress percentage
     */
    public function getRepaymentProgressAttribute(): float
    {
        if ($this->total_loan_amount == 0) {
            return 0;
        }

        return ($this->total_paid / $this->total_loan_amount) * 100;
    }

    /**
     * Get remaining installments count
     */
    public function getRemainingInstallmentsAttribute(): int
    {
        return max(0, $this->number_of_installments - $this->installments_paid);
    }

    // Methods
    /**
     * Check if loan is still active for deductions
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if loan has been fully paid
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if loan is in default
     */
    public function isDefaulted(): bool
    {
        return $this->status === 'defaulted';
    }

    /**
     * Get the next installment number to be deducted
     */
    public function getNextInstallmentNumber(): int
    {
        return $this->installments_paid + 1;
    }

    /**
     * Record a deduction for the next installment
     */
    public function recordDeduction(float $amountDeducted): void
    {
        $this->installments_paid++;
        $this->total_paid += $amountDeducted;
        $this->remaining_balance -= $amountDeducted;
        $this->last_deduction_date = now()->toDateString();

        // Mark as completed if all installments paid
        if ($this->installments_paid >= $this->number_of_installments) {
            $this->status = 'completed';
            $this->completion_date = now()->toDateString();
            $this->completion_reason = 'All installments completed';
        }

        $this->save();
    }

    /**
     * Calculate the outstanding balance (should match remaining_balance)
     */
    public function calculateOutstandingBalance(): float
    {
        return $this->total_loan_amount - $this->total_paid;
    }

    /**
     * Get months remaining for this loan (based on number of remaining installments)
     */
    public function getMonthsRemaining(): int
    {
        return $this->remaining_installments;
    }

    /**
     * Mark loan as defaulted with reason
     */
    public function markAsDefaulted(string $reason = 'Payment default'): void
    {
        $this->status = 'defaulted';
        $this->completion_reason = $reason;
        $this->save();
    }

    /**
     * Mark loan as completed manually (for early payments or adjustments)
     */
    public function markAsCompleted(string $reason = 'Manual completion'): void
    {
        $this->status = 'completed';
        $this->completion_date = now()->toDateString();
        $this->completion_reason = $reason;
        $this->save();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set created_by if not already set
        static::creating(function ($loan) {
            if (!$loan->created_by && auth()->check()) {
                $loan->created_by = auth()->id();
            }
        });

        // Set updated_by on updates
        static::updating(function ($loan) {
            if (auth()->check()) {
                $loan->updated_by = auth()->id();
            }
        });
    }
}
