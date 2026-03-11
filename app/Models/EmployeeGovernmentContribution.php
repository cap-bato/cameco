<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGovernmentContribution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'employee_payroll_calculation_id',
        // Period
        'period_start',
        'period_end',
        'period_month',
        // Compensation Basis
        'basic_salary',
        'gross_compensation',
        'taxable_income',
        // SSS
        'sss_number',
        'sss_bracket',
        'sss_monthly_salary_credit',
        'sss_employee_contribution',
        'sss_employer_contribution',
        'sss_ec_contribution',
        'sss_total_contribution',
        'is_sss_exempted',
        // PhilHealth
        'philhealth_number',
        'philhealth_premium_base',
        'philhealth_employee_contribution',
        'philhealth_employer_contribution',
        'philhealth_total_contribution',
        'is_philhealth_exempted',
        // Pag-IBIG
        'pagibig_number',
        'pagibig_compensation_base',
        'pagibig_employee_contribution',
        'pagibig_employer_contribution',
        'pagibig_total_contribution',
        'is_pagibig_exempted',
        // BIR
        'tin',
        'tax_status',
        'annualized_taxable_income',
        'tax_due',
        'withholding_tax',
        'tax_already_withheld_ytd',
        'is_minimum_wage_earner',
        'is_substituted_filing',
        // De minimis
        'deminimis_benefits',
        'thirteenth_month_pay',
        'other_tax_exempt_compensation',
        // Totals
        'total_employee_contributions',
        'total_employer_contributions',
        'total_statutory_deductions',
        // Status
        'status',
        'calculated_at',
        'calculated_by',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'calculated_at' => 'datetime',
        'processed_at' => 'datetime',
        // Decimals
        'basic_salary' => 'decimal:2',
        'gross_compensation' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'sss_monthly_salary_credit' => 'decimal:2',
        'sss_employee_contribution' => 'decimal:2',
        'sss_employer_contribution' => 'decimal:2',
        'sss_ec_contribution' => 'decimal:2',
        'sss_total_contribution' => 'decimal:2',
        'philhealth_premium_base' => 'decimal:2',
        'philhealth_employee_contribution' => 'decimal:2',
        'philhealth_employer_contribution' => 'decimal:2',
        'philhealth_total_contribution' => 'decimal:2',
        'pagibig_compensation_base' => 'decimal:2',
        'pagibig_employee_contribution' => 'decimal:2',
        'pagibig_employer_contribution' => 'decimal:2',
        'pagibig_total_contribution' => 'decimal:2',
        'annualized_taxable_income' => 'decimal:2',
        'tax_due' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'tax_already_withheld_ytd' => 'decimal:2',
        'deminimis_benefits' => 'decimal:2',
        'thirteenth_month_pay' => 'decimal:2',
        'other_tax_exempt_compensation' => 'decimal:2',
        'total_employee_contributions' => 'decimal:2',
        'total_employer_contributions' => 'decimal:2',
        'total_statutory_deductions' => 'decimal:2',
        // Booleans
        'is_sss_exempted' => 'boolean',
        'is_philhealth_exempted' => 'boolean',
        'is_pagibig_exempted' => 'boolean',
        'is_minimum_wage_earner' => 'boolean',
        'is_substituted_filing' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employeePayrollCalculation(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes

    public function scopeByPeriodMonth($query, string $month)
    {
        return $query->where('period_month', $month);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeRemitted($query)
    {
        return $query->where('status', 'remitted');
    }
}
