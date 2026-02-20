<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BankFileBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        'payment_method_id',
        'batch_number',
        'batch_name',
        'payment_date',
        'bank_code',
        'bank_name',
        'transfer_type',
        'file_name',
        'file_path',
        'file_format',
        'file_size',
        'file_hash',
        'total_employees',
        'total_amount',
        'successful_count',
        'failed_count',
        'total_fees',
        'settlement_date',
        'settlement_reference',
        'status',
        'submitted_at',
        'completed_at',
        'is_validated',
        'validated_at',
        'validation_errors',
        'bank_response',
        'bank_confirmation_number',
        'notes',
        'generated_by',
        'submitted_by',
    ];

    protected $casts = [
        'payment_date'      => 'date',
        'settlement_date'   => 'date',
        'total_amount'      => 'decimal:2',
        'total_fees'        => 'decimal:2',
        'total_employees'   => 'integer',
        'successful_count'  => 'integer',
        'failed_count'      => 'integer',
        'file_size'         => 'integer',
        'is_validated'      => 'boolean',
        'validated_at'      => 'datetime',
        'submitted_at'      => 'datetime',
        'completed_at'      => 'datetime',
        'validation_errors' => 'array',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Payments linked to this batch via the shared batch_number field.
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

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partially_completed']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByBank($query, string $bankCode)
    {
        return $query->where('bank_code', $bankCode);
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'processing']);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'partially_completed']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInstapay(): bool
    {
        return $this->transfer_type === 'instapay';
    }

    public function isPesonet(): bool
    {
        return $this->transfer_type === 'pesonet';
    }

    /**
     * A batch can be submitted once it is validated and in 'ready' status.
     */
    public function canSubmit(): bool
    {
        return $this->is_validated && $this->status === 'ready';
    }

    public function hasValidationErrors(): bool
    {
        return !empty($this->validation_errors);
    }

    /**
     * Percentage of successfully processed transactions.
     */
    public function getSuccessRate(): float
    {
        if ($this->total_employees === 0) {
            return 0.0;
        }

        return round(($this->successful_count / $this->total_employees) * 100, 2);
    }
}
