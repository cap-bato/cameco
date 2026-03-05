<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceDeduction extends Model
{
    use HasFactory;

    protected $table = 'advance_deductions';

    protected $fillable = [
        'cash_advance_id',
        'payroll_period_id',
        'employee_payroll_calculation_id',
        'installment_number',
        'deduction_amount',
        'remaining_balance_after',
        'is_deducted',
        'deducted_at',
        'deduction_notes',
    ];

    protected $casts = [
        'deduction_amount' => 'decimal:2',
        'remaining_balance_after' => 'decimal:2',
        'is_deducted' => 'boolean',
        'deducted_at' => 'datetime',
        'installment_number' => 'integer',
    ];

    // ====== RELATIONSHIPS ======

    /**
     * The cash advance this deduction belongs to
     */
    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class, 'cash_advance_id');
    }

    /**
     * The payroll period this deduction is for
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * The payroll calculation this deduction was applied to
     */
    public function employeePayrollCalculation(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class, 'employee_payroll_calculation_id');
    }

    // ====== SCOPES ======

    /**
     * Get deductions that have been deducted
     */
    public function scopeDeducted($query)
    {
        return $query->where('is_deducted', true);
    }

    /**
     * Get deductions that are pending (not yet deducted)
     */
    public function scopePending($query)
    {
        return $query->where('is_deducted', false);
    }

    /**
     * Get deductions for a specific advance
     */
    public function scopeForAdvance($query, int $advanceId)
    {
        return $query->where('cash_advance_id', $advanceId);
    }

    /**
     * Get deductions for a specific payroll period
     */
    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    // ====== ACCESSORS ======

    /**
     * Get formatted deduction amount with currency symbol
     */
    public function getFormattedDeductionAmountAttribute(): string
    {
        return '₱' . number_format($this->deduction_amount, 2);
    }

    /**
     * Get formatted remaining balance with currency symbol
     */
    public function getFormattedRemainingBalanceAttribute(): string
    {
        return '₱' . number_format($this->remaining_balance_after, 2);
    }

    // ====== HELPER METHODS ======

    /**
     * Mark this deduction as deducted
     */
    public function markAsDeducted(): void
    {
        $this->update([
            'is_deducted' => true,
            'deducted_at' => now(),
        ]);
    }

    /**
     * Check if this is the last installment
     */
    public function isLastInstallment(): bool
    {
        $advance = $this->cashAdvance;
        return $this->installment_number >= $advance->number_of_installments;
    }
}
