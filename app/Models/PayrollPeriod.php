<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PayrollPeriod Model
 *
 * Represents a payroll period (weekly, bi-weekly, semi-monthly, monthly).
 * Tracks the payroll processing for a specific time period including
 * dates, status, and payroll summaries.
 *
 * Fields:
 * - Period Info: name, period_type, start_date, end_date, cutoff_date, pay_date
 * - Status: status (draft, importing, calculating, calculated, reviewing, approved, bank_file_generated, paid, closed)
 * - Processing: processed_at, approved_at, finalized_at
 * - Users: created_by, updated_by, approved_by, finalized_by
 * - Summary: total_employees, total_gross_pay, total_deductions, total_net_pay, total_employer_cost
 *
 * Relationships:
 * - hasMany: EmployeePayrollCalculations, AdvanceDeductions
 * - belongsTo: Users (approved_by, finalized_by)
 *
 * Usage:
 * - Payroll Cycle: Created in PayrollPeriodController
 * - Calculations: PayrollCalculationService uses to organize calculations
 * - Reports: Reports use for filtering and summary data
 */
class PayrollPeriod extends Model
{
    use SoftDeletes;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'name',
        'period_type',
        'start_date',
        'end_date',
        'cutoff_date',
        'pay_date',
        'status',
        'processed_at',
        'approved_at',
        'approved_by',
        'finalized_at',
        'finalized_by',
        'total_employees',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'total_employer_cost',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cutoff_date' => 'date',
        'pay_date' => 'date',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'total_employees' => 'integer',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'total_employer_cost' => 'decimal:2',
    ];

    /**
     * Get all employee calculations for this period
     */
    public function employeePayrollCalculations(): HasMany
    {
        return $this->hasMany(EmployeePayrollCalculation::class, 'payroll_period_id');
    }

    /**
     * Get all advance deductions for this period
     */
    public function advanceDeductions(): HasMany
    {
        return $this->hasMany(AdvanceDeduction::class, 'payroll_period_id');
    }

    /**
     * Get the user who approved this period
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who finalized this period
     */
    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Get the user who created this period
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this period
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Get active periods (not draft or closed)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['draft', 'closed']);
    }

    /**
     * Scope: Get by period type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('period_type', $type);
    }

    /**
     * Scope: Get by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get past periods (pay_date before today)
     */
    public function scopePast($query)
    {
        return $query->where('pay_date', '<', now()->startOfDay());
    }

    /**
     * Scope: Get upcoming periods (pay_date from today onwards)
     */
    public function scopeUpcoming($query)
    {
        return $query->where('pay_date', '>=', now()->startOfDay());
    }

    /**
     * Accessor: Get formatted name with dates
     */
    public function getFormattedNameAttribute(): string
    {
        return $this->name . ' (' . $this->start_date->format('M d') . ' - ' . $this->end_date->format('M d, Y') . ')';
    }

    /**
     * Helper: Check if period is in calculating status
     */
    public function isCalculating(): bool
    {
        return $this->status === 'calculating';
    }

    /**
     * Helper: Check if period is calculated
     */
    public function isCalculated(): bool
    {
        return in_array($this->status, ['calculated', 'reviewing', 'approved', 'bank_file_generated', 'paid', 'closed']);
    }

    /**
     * Helper: Check if period is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'bank_file_generated', 'paid', 'closed']);
    }

    /**
     * Helper: Check if period is finalized/closed
     */
    public function isFinalized(): bool
    {
        return $this->status === 'closed' || $this->status === 'paid';
    }

    /**
     * Helper: Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        return match ($this->status) {
            'draft' => 0,
            'importing' => 10,
            'calculating' => 50,
            'calculated' => 60,
            'reviewing' => 70,
            'approved' => 80,
            'bank_file_generated' => 90,
            'paid' => 100,
            'closed' => 100,
            default => 0,
        };
    }

    /**
     * Helper: Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'importing' => 'blue',
            'calculating' => 'yellow',
            'calculated' => 'indigo',
            'reviewing' => 'orange',
            'approved' => 'green',
            'bank_file_generated' => 'teal',
            'paid' => 'emerald',
            'closed' => 'slate',
            default => 'gray',
        };
    }
}
