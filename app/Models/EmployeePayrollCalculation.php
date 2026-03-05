<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EmployeePayrollCalculation Model
 *
 * Represents a single employee's payroll calculation for a specific payroll period.
 * Contains complete breakdown of earnings, deductions, and net pay.
 *
 * Relationships:
 * - belongsTo: PayrollPeriod, Employee
 * - hasMany: AdvanceDeductions (specific to this calculation)
 *
 * Usage:
 * - Payroll Calculation: PayrollCalculationService creates these records
 * - Payslip Generation: PayslipGenerationService reads these for payslip data
 * - Payroll Reports: Reports query these for register, analytics, audit reports
 */
class EmployeePayrollCalculation extends Model
{
    use SoftDeletes;

    protected $table = 'employee_payroll_calculations';

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'days_worked',
        'total_hours',
        'regular_hours',
        'overtime_hours',
        'late_minutes',
        'undertime_minutes',
        'basic_pay',
        'overtime_pay',
        'component_amount',
        'allowance_amount',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'deduction_amount',
        'loan_deduction',
        'late_deduction',
        'undertime_deduction',
        'advance_deduction',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'status',
        'calculated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'days_worked' => 'integer',
        'total_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'late_minutes' => 'integer',
        'undertime_minutes' => 'integer',
        'basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'component_amount' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'late_deduction' => 'decimal:2',
        'undertime_deduction' => 'decimal:2',
        'advance_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the payroll period this calculation belongs to
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * Get the employee this calculation is for
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get advance deductions for this calculation
     */
    public function advanceDeductions(): HasMany
    {
        return $this->hasMany(AdvanceDeduction::class, 'employee_payroll_calculation_id');
    }

    /**
     * Get creator user
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get updater user
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Get calculated (not draft) records
     */
    public function scopeCalculated($query)
    {
        return $query->where('status', '!=', 'draft');
    }

    /**
     * Scope: Get by period
     */
    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    /**
     * Scope: Get by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: Get by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Accessor: Get total hours worked (sum of regular + overtime)
     */
    public function getTotalHoursWorkedAttribute(): float
    {
        return (float) $this->regular_hours + (float) $this->overtime_hours;
    }

    /**
     * Accessor: Get formatted gross pay
     */
    public function getFormattedGrossPayAttribute(): string
    {
        return '₱' . number_format($this->gross_pay, 2);
    }

    /**
     * Accessor: Get formatted net pay
     */
    public function getFormattedNetPayAttribute(): string
    {
        return '₱' . number_format($this->net_pay, 2);
    }

    /**
     * Accessor: Get formatted advance deduction
     */
    public function getFormattedAdvanceDeductionAttribute(): string
    {
        return '₱' . number_format($this->advance_deduction, 2);
    }

    /**
     * Helper: Check if calculation is complete (not draft)
     */
    public function isComplete(): bool
    {
        return $this->status !== 'draft';
    }

    /**
     * Helper: Check if calculation is finalized
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized' || $this->status === 'approved' || $this->status === 'paid';
    }

    /**
     * Helper: Get total earnings
     */
    public function getTotalEarnings(): float
    {
        return (float) $this->gross_pay;
    }

    /**
     * Helper: Get total deductions breakdown
     */
    public function getDeductionsBreakdown(): array
    {
        return [
            'government' => (float) ($this->sss_contribution + $this->philhealth_contribution + $this->pagibig_contribution),
            'tax' => (float) $this->withholding_tax,
            'loans' => (float) $this->loan_deduction,
            'attendance' => (float) ($this->late_deduction + $this->undertime_deduction),
            'advances' => (float) $this->advance_deduction,
            'other' => (float) $this->deduction_amount,
            'total' => (float) $this->total_deductions,
        ];
    }
}
