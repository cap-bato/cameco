<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePayrollCalculation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'employee_number',
        'employee_name',
        'department',
        'position',
        'employment_status',
        'hire_date',
        'basic_monthly_salary',
        'daily_rate',
        'hourly_rate',
        'working_days_per_month',
        'working_hours_per_day',
        'expected_days',
        'present_days',
        'absent_days',
        'excused_absences',
        'unexcused_absences',
        'late_hours',
        'undertime_hours',
        'regular_overtime_hours',
        'rest_day_overtime_hours',
        'holiday_overtime_hours',
        'night_differential_hours',
        'total_overtime_hours',
        'paid_leave_days',
        'unpaid_leave_days',
        'leave_deduction_amount',
        'leave_breakdown',
        'basic_pay',
        'regular_overtime_pay',
        'rest_day_overtime_pay',
        'holiday_overtime_pay',
        'night_differential_pay',
        'total_overtime_pay',
        'transportation_allowance',
        'meal_allowance',
        'housing_allowance',
        'communication_allowance',
        'other_allowances',
        'total_allowances',
        'performance_bonus',
        'attendance_bonus',
        'productivity_bonus',
        'other_income',
        'total_bonuses',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'total_government_deductions',
        'sss_loan_deduction',
        'pagibig_loan_deduction',
        'company_loan_deduction',
        'total_loan_deductions',
        'cash_advance_deduction',
        'salary_advance_deduction',
        'total_advance_deductions',
        'tardiness_deduction',
        'absence_deduction',
        'uniform_deduction',
        'tool_deduction',
        'miscellaneous_deductions',
        'total_deductions',
        'net_pay',
        'adjustments_total',
        'final_net_pay',
        'calculation_status',
        'has_exceptions',
        'exceptions_count',
        'exception_flags',
        'has_adjustments',
        'adjustments_count',
        'calculation_breakdown',
        'calculated_at',
        'reviewed_at',
        'approved_at',
        'locked_at',
        'version',
        'previous_version_id',
        'notes',
        'calculated_by',
        'reviewed_by',
        'approved_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'basic_monthly_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'working_days_per_month' => 'integer',
        'working_hours_per_day' => 'decimal:2',
        'expected_days' => 'integer',
        'present_days' => 'integer',
        'absent_days' => 'integer',
        'excused_absences' => 'integer',
        'unexcused_absences' => 'integer',
        'late_hours' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'regular_overtime_hours' => 'decimal:2',
        'rest_day_overtime_hours' => 'decimal:2',
        'holiday_overtime_hours' => 'decimal:2',
        'night_differential_hours' => 'decimal:2',
        'total_overtime_hours' => 'decimal:2',
        'paid_leave_days' => 'integer',
        'unpaid_leave_days' => 'integer',
        'leave_deduction_amount' => 'decimal:2',
        'leave_breakdown' => 'array',
        'basic_pay' => 'decimal:2',
        'regular_overtime_pay' => 'decimal:2',
        'rest_day_overtime_pay' => 'decimal:2',
        'holiday_overtime_pay' => 'decimal:2',
        'night_differential_pay' => 'decimal:2',
        'total_overtime_pay' => 'decimal:2',
        'transportation_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'communication_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'performance_bonus' => 'decimal:2',
        'attendance_bonus' => 'decimal:2',
        'productivity_bonus' => 'decimal:2',
        'other_income' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'total_government_deductions' => 'decimal:2',
        'sss_loan_deduction' => 'decimal:2',
        'pagibig_loan_deduction' => 'decimal:2',
        'company_loan_deduction' => 'decimal:2',
        'total_loan_deductions' => 'decimal:2',
        'cash_advance_deduction' => 'decimal:2',
        'salary_advance_deduction' => 'decimal:2',
        'total_advance_deductions' => 'decimal:2',
        'tardiness_deduction' => 'decimal:2',
        'absence_deduction' => 'decimal:2',
        'uniform_deduction' => 'decimal:2',
        'tool_deduction' => 'decimal:2',
        'miscellaneous_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'adjustments_total' => 'decimal:2',
        'final_net_pay' => 'decimal:2',
        'has_exceptions' => 'boolean',
        'exceptions_count' => 'integer',
        'exception_flags' => 'array',
        'has_adjustments' => 'boolean',
        'adjustments_count' => 'integer',
        'calculation_breakdown' => 'array',
        'calculated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'version' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class, 'previous_version_id');
    }

    public function nextVersions(): HasMany
    {
        return $this->hasMany(EmployeePayrollCalculation::class, 'previous_version_id');
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(PayrollException::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('calculation_status', 'pending');
    }

    public function scopeCalculated($query)
    {
        return $query->whereIn('calculation_status', ['calculated', 'approved', 'locked']);
    }

    public function scopeWithExceptions($query)
    {
        return $query->where('has_exceptions', true);
    }

    public function scopeWithAdjustments($query)
    {
        return $query->where('has_adjustments', true);
    }

    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('calculation_status', $status);
    }

    public function scopeLatestVersion($query)
    {
        return $query->whereNull('previous_version_id')->orWhere(function ($q) {
            $q->whereNotIn('id', function ($subquery) {
                $subquery->select('previous_version_id')
                    ->from('employee_payroll_calculations')
                    ->whereNotNull('previous_version_id');
            });
        });
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isLocked(): bool
    {
        return $this->calculation_status === 'locked' || $this->locked_at !== null;
    }

    public function canAdjust(): bool
    {
        return !$this->isLocked() && in_array($this->calculation_status, ['calculated', 'exception', 'adjusted']);
    }

    public function hasExceptions(): bool
    {
        return $this->has_exceptions && $this->exceptions_count > 0;
    }

    public function getNetPayVariation(): ?float
    {
        if (!$this->previous_version_id) {
            return null;
        }

        $previousVersion = $this->previousVersion;
        if (!$previousVersion) {
            return null;
        }

        $difference = $this->final_net_pay - $previousVersion->final_net_pay;
        $percentage = ($difference / $previousVersion->final_net_pay) * 100;

        return round($percentage, 2);
    }

    public function calculateTotalEarnings(): float
    {
        return $this->basic_pay 
            + $this->total_overtime_pay 
            + $this->total_allowances 
            + $this->total_bonuses;
    }

    public function calculateTotalDeductions(): float
    {
        return $this->total_government_deductions 
            + $this->total_loan_deductions 
            + $this->total_advance_deductions 
            + $this->tardiness_deduction 
            + $this->absence_deduction 
            + $this->leave_deduction_amount
            + $this->uniform_deduction 
            + $this->tool_deduction 
            + $this->miscellaneous_deductions;
    }

    public function markAsCalculated(User $user): void
    {
        $this->update([
            'calculation_status' => 'calculated',
            'calculated_at' => now(),
            'calculated_by' => $user->id,
        ]);
    }

    public function markAsException(array $flags): void
    {
        $this->update([
            'calculation_status' => 'exception',
            'has_exceptions' => true,
            'exceptions_count' => count($flags),
            'exception_flags' => $flags,
        ]);
    }

    public function lock(User $user): void
    {
        $this->update([
            'calculation_status' => 'locked',
            'locked_at' => now(),
            'approved_by' => $user->id,
        ]);
    }

    public function createNewVersion(): self
    {
        $newVersion = $this->replicate();
        $newVersion->previous_version_id = $this->id;
        $newVersion->version = $this->version + 1;
        $newVersion->calculated_at = null;
        $newVersion->reviewed_at = null;
        $newVersion->approved_at = null;
        $newVersion->locked_at = null;
        $newVersion->calculation_status = 'pending';
        $newVersion->save();

        return $newVersion;
    }
}
