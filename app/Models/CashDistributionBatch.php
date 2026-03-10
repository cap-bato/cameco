<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CashDistributionBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        'batch_number',
        'distribution_date',
        'distribution_location',
        'total_cash_amount',
        'total_employees',
        'denomination_breakdown',
        'withdrawal_source',
        'withdrawal_reference',
        'withdrawal_date',
        'withdrawn_by',
        'counted_by',
        'witnessed_by',
        'verification_at',
        'verification_notes',
        'envelopes_prepared',
        'envelopes_distributed',
        'envelopes_unclaimed',
        'amount_distributed',
        'amount_unclaimed',
        'log_sheet_path',
        'distribution_started_at',
        'distribution_completed_at',
        'unclaimed_deadline',
        'unclaimed_disposition',
        'redeposit_date',
        'redeposit_reference',
        'status',
        'accountability_report_path',
        'report_generated_at',
        'report_approved_by',
        'notes',
        'prepared_by',
    ];

    protected $casts = [
        'distribution_date'          => 'date',
        'withdrawal_date'            => 'date',
        'unclaimed_deadline'         => 'date',
        'redeposit_date'             => 'date',
        'total_cash_amount'          => 'decimal:2',
        'amount_distributed'         => 'decimal:2',
        'amount_unclaimed'           => 'decimal:2',
        'total_employees'            => 'integer',
        'envelopes_prepared'         => 'integer',
        'envelopes_distributed'      => 'integer',
        'envelopes_unclaimed'        => 'integer',
        'denomination_breakdown'     => 'array',
        'verification_at'            => 'datetime',
        'distribution_started_at'    => 'datetime',
        'distribution_completed_at'  => 'datetime',
        'report_generated_at'        => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reportApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_approved_by');
    }

    /**
     * Cash payments linked via the shared batch_number field.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class, 'batch_number', 'batch_number');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(PaymentAuditLog::class, 'auditable');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePreparing($query)
    {
        return $query->where('status', 'preparing');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeDistributing($query)
    {
        return $query->where('status', 'distributing');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partially_completed']);
    }

    public function scopeReconciled($query)
    {
        return $query->where('status', 'reconciled');
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Dual verification: both Payroll Officer (counted_by) AND
     * HR Manager/Office Admin (witnessed_by) must sign off (Decision #8).
     */
    public function isVerified(): bool
    {
        return $this->counted_by !== null && $this->witnessed_by !== null;
    }

    public function isPreparing(): bool
    {
        return $this->status === 'preparing';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isDistributing(): bool
    {
        return $this->status === 'distributing';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'partially_completed']);
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    /**
     * Distribution can begin only after dual verification is complete.
     */
    public function canStartDistribution(): bool
    {
        return $this->isVerified() && $this->status === 'ready';
    }

    public function hasUnclaimed(): bool
    {
        return $this->envelopes_unclaimed > 0;
    }

    public function getUnclaimedAmount(): float
    {
        return (float) $this->amount_unclaimed;
    }

    public function isUnclaimedDeadlinePassed(): bool
    {
        return $this->unclaimed_deadline !== null
            && now()->isAfter($this->unclaimed_deadline);
    }
}
