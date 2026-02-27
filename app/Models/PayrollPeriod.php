<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PayrollPeriod extends Model
{
    use HasFactory, SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payroll_periods';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'period_number',
        'period_name',
        'period_start',
        'period_end',
        'payment_date',
        'period_month',
        'period_year',
        'period_type',
        'timekeeping_cutoff_date',
        'leave_cutoff_date',
        'adjustment_deadline',
        'total_employees',
        'active_employees',
        'excluded_employees',
        'employee_filter',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'total_government_contributions',
        'total_loan_deductions',
        'total_adjustments',
        'status',
        'calculation_started_at',
        'calculation_completed_at',
        'submitted_for_review_at',
        'reviewed_at',
        'approved_at',
        'finalized_at',
        'locked_at',
        'calculation_config',
        'calculation_retries',
        'calculation_errors',
        'exceptions_count',
        'adjustments_count',
        'timekeeping_summary',
        'leave_summary',
        'timekeeping_data_locked',
        'leave_data_locked',
        'created_by',
        'reviewed_by',
        'approved_by',
        'locked_by',
        'notes',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'period_year' => 'integer',
        'timekeeping_cutoff_date' => 'date',
        'leave_cutoff_date' => 'date',
        'adjustment_deadline' => 'date',
        'total_employees' => 'integer',
        'active_employees' => 'integer',
        'excluded_employees' => 'integer',
        'employee_filter' => 'array',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'total_government_contributions' => 'decimal:2',
        'total_loan_deductions' => 'decimal:2',
        'total_adjustments' => 'decimal:2',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'locked_at' => 'datetime',
        'calculation_config' => 'array',
        'calculation_retries' => 'integer',
        'exceptions_count' => 'integer',
        'adjustments_count' => 'integer',
        'timekeeping_summary' => 'array',
        'leave_summary' => 'array',
        'timekeeping_data_locked' => 'boolean',
        'leave_data_locked' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    /**
     * Get all employee payroll calculations for this period.
     */
    public function calculations(): HasMany
    {
        return $this->hasMany(EmployeePayrollCalculation::class);
    }

    /**
     * Get all payroll adjustments for this period.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * Get all payroll exceptions for this period.
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(PayrollException::class);
    }

    /**
     * Get all calculation logs for this period.
     */
    public function calculationLogs(): HasMany
    {
        return $this->hasMany(PayrollCalculationLog::class);
    }

    /**
     * Get all approval history entries for this period.
     */
    public function approvalHistory(): HasMany
    {
        return $this->hasMany(PayrollApprovalHistory::class);
    }

    /**
     * Get the user who created this payroll period.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who reviewed this payroll period.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who approved this payroll period.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who locked this payroll period.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get all payroll payments for this period.
     */
    public function payrollPayments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    /**
     * Get all bank file batches for this period.
     */
    public function bankFileBatches(): HasMany
    {
        return $this->hasMany(BankFileBatch::class);
    }

    /**
     * Get all cash distribution batches for this period.
     */
    public function cashDistributionBatches(): HasMany
    {
        return $this->hasMany(CashDistributionBatch::class);
    }

    /**
     * Get all payslips for this period.
     */
    public function payslips()
    {
        return $this->hasManyThrough(Payslip::class, PayrollPayment::class, 'payroll_period_id', 'payroll_payment_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * Scope a query to only include active periods.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to include periods that are calculating or calculated.
     */
    public function scopeCalculating($query)
    {
        return $query->whereIn('status', ['calculating', 'calculated']);
    }

    /**
     * Scope a query to include periods pending approval.
     */
    public function scopePendingApproval($query)
    {
        return $query->whereIn('status', ['under_review', 'pending_approval']);
    }

    /**
     * Scope a query to include finalized periods.
     */
    public function scopeFinalized($query)
    {
        return $query->whereIn('status', ['finalized', 'completed']);
    }

    /**
     * Scope a query to filter by month.
     */
    public function scopeByMonth($query, string $month)
    {
        return $query->where('period_month', $month);
    }

    /**
     * Scope a query to filter by year.
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('period_year', $year);
    }

    /**
     * Scope a query to only include regular periods.
     */
    public function scopeRegular($query)
    {
        return $query->where('period_type', 'regular');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Check if the payroll period is locked.
     */
    public function isLocked(): bool
    {
        return $this->status === 'finalized' || $this->locked_at !== null;
    }

    /**
     * Check if calculations can be run on this period.
     */
    public function canCalculate(): bool
    {
        return in_array($this->status, ['draft', 'active', 'calculated']);
    }

    /**
     * Check if adjustments can be made to this period.
     */
    public function canAdjust(): bool
    {
        return !$this->isLocked() && now()->lte($this->adjustment_deadline);
    }

    /**
     * Check if this period can be approved.
     */
    public function canApprove(): bool
    {
        return in_array($this->status, ['under_review', 'pending_approval']);
    }

    /**
     * Check if the timekeeping cutoff has passed.
     */
    public function isPastCutoff(): bool
    {
        return now()->gt($this->timekeeping_cutoff_date);
    }

    /**
     * Get the number of days until payment date.
     */
    public function getDaysUntilPayment(): int
    {
        return now()->diffInDays($this->payment_date, false);
    }

    /**
     * Get the calculation progress percentage.
     */
    public function getProgressPercentage(): int
    {
        if ($this->total_employees === 0) {
            return 0;
        }

        $completed = $this->calculations()
            ->whereIn('calculation_status', ['calculated', 'approved', 'locked'])
            ->count();
        
        return (int) (($completed / $this->total_employees) * 100);
    }

    /**
     * Generate a unique period number.
     */
    public function generatePeriodNumber(): string
    {
        // Format: YYYY-MM-A (A = Period 1: 1-15, B = Period 2: 16-end)
        $periodCode = $this->period_start->day <= 15 ? 'A' : 'B';
        return $this->period_start->format('Y-m') . '-' . $periodCode;
    }

    /**
     * Lock the payroll period.
     */
    public function lockPeriod(User $user): void
    {
        $this->update([
            'status' => 'finalized',
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);
    }

    /**
     * Unlock the payroll period.
     */
    public function unlockPeriod(User $user): void
    {
        $this->update([
            'status' => 'calculated',
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }
}
