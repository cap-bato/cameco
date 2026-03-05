<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodUsageLog extends Model
{
    protected $fillable = [
        'payment_method_provider_id',
        'employee_id',
        'payroll_period_id',
        'amount',
        'fee',
        'status',
        'transaction_reference',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function paymentMethodProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodProvider::class, 'payment_method_provider_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->amount + (float) $this->fee;
    }
}
