<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class PayrollPeriod extends Model
{
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
        'timekeeping_cutoff_date' => 'date',
        'leave_cutoff_date' => 'date',
        'adjustment_deadline' => 'date',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'locked_at' => 'datetime',
        'employee_filter' => 'array',
        'calculation_config' => 'array',
        'timekeeping_summary' => 'array',
        'leave_summary' => 'array',
        'timekeeping_data_locked' => 'boolean',
        'leave_data_locked' => 'boolean',
    ];

    /**
     * Get the user who created this payroll period.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who reviewed this payroll period.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who approved this payroll period.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who locked this payroll period.
     */
    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Get all payroll payments for this period.
     */
    public function payrollPayments()
    {
        return $this->hasMany(PayrollPayment::class);
    }

    /**
     * Get all bank file batches for this period.
     */
    public function bankFileBatches()
    {
        return $this->hasMany(BankFileBatch::class);
    }

    /**
     * Get all cash distribution batches for this period.
     */
    public function cashDistributionBatches()
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

    /**
     * Scope to filter active periods.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'calculating', 'calculated', 'under_review', 'pending_approval', 'approved']);
    }

    /**
     * Scope to filter completed periods.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter approved periods.
     */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'finalized', 'processing_payment', 'completed']);
    }
}
