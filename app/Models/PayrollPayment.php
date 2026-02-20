<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'employee_payroll_calculation_id',
        'payment_method_id',
        'period_start',
        'period_end',
        'payment_date',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'sss_deduction',
        'philhealth_deduction',
        'pagibig_deduction',
        'tax_deduction',
        'loan_deduction',
        'advance_deduction',
        'leave_deduction',
        'attendance_deduction',
        'other_deductions',
        'payment_reference',
        'batch_number',
        'bank_account_number',
        'bank_name',
        'bank_transaction_id',
        'ewallet_account',
        'ewallet_transaction_id',
        'envelope_number',
        'claimed_at',
        'claimed_by_signature',
        'released_by',
        'status',
        'processed_at',
        'paid_at',
        'failed_at',
        'retry_count',
        'last_retry_at',
        'failure_reason',
        'provider_response',
        'confirmation_code',
        'notes',
        'prepared_by',
        'approved_by',
    ];

    protected $casts = [
        'period_start'       => 'date',
        'period_end'         => 'date',
        'payment_date'       => 'date',
        'gross_pay'          => 'decimal:2',
        'total_deductions'   => 'decimal:2',
        'net_pay'            => 'decimal:2',
        'sss_deduction'      => 'decimal:2',
        'philhealth_deduction' => 'decimal:2',
        'pagibig_deduction'  => 'decimal:2',
        'tax_deduction'      => 'decimal:2',
        'loan_deduction'     => 'decimal:2',
        'advance_deduction'  => 'decimal:2',
        'leave_deduction'    => 'decimal:2',
        'attendance_deduction' => 'decimal:2',
        'other_deductions'   => 'decimal:2',
        'claimed_at'         => 'datetime',
        'processed_at'       => 'datetime',
        'paid_at'            => 'datetime',
        'failed_at'          => 'datetime',
        'retry_count'        => 'integer',
        'last_retry_at'      => 'datetime',
        'provider_response'  => 'array',
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

    public function employeePayrollCalculation(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(PaymentAuditLog::class, 'auditable');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeUnclaimed($query)
    {
        return $query->where('status', 'unclaimed');
    }

    public function scopeByCash($query)
    {
        return $query->whereHas('paymentMethod', fn ($q) => $q->where('method_type', 'cash'));
    }

    public function scopeByBank($query)
    {
        return $query->whereHas('paymentMethod', fn ($q) => $q->where('method_type', 'bank'));
    }

    public function scopeByEwallet($query)
    {
        return $query->whereHas('paymentMethod', fn ($q) => $q->where('method_type', 'ewallet'));
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeByBatch($query, string $batchNumber)
    {
        return $query->where('batch_number', $batchNumber);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isUnclaimed(): bool
    {
        return $this->status === 'unclaimed';
    }

    public function isCash(): bool
    {
        return $this->paymentMethod?->isCash() ?? false;
    }

    public function isBank(): bool
    {
        return $this->paymentMethod?->isBank() ?? false;
    }

    public function isEwallet(): bool
    {
        return $this->paymentMethod?->isEwallet() ?? false;
    }

    /**
     * Determine if this payment can be retried (max 3 attempts per Decision #12).
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function markAsPaid(array $attributes = []): void
    {
        $this->update(array_merge([
            'status'  => 'paid',
            'paid_at' => now(),
        ], $attributes));
    }

    public function markAsFailed(string $reason, ?array $providerResponse = null): void
    {
        $this->update([
            'status'            => 'failed',
            'failed_at'         => now(),
            'failure_reason'    => $reason,
            'provider_response' => $providerResponse ?? $this->provider_response,
        ]);
    }

    public function getTotalDeductions(): float
    {
        return (float) (
            $this->sss_deduction +
            $this->philhealth_deduction +
            $this->pagibig_deduction +
            $this->tax_deduction +
            $this->loan_deduction +
            $this->advance_deduction +
            $this->leave_deduction +
            $this->attendance_deduction +
            $this->other_deductions
        );
    }
}
