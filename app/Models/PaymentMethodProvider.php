<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethodProvider extends Model
{
    protected $fillable = [
        'payment_method_id',
        'code',
        'name',
        'category',
        'description',
        'logo_url',
        'is_enabled',
        'is_available',
        'configuration',
        'transaction_fee',
        'fee_type',
        'min_amount',
        'max_amount',
        'daily_limit',
        'monthly_limit',
        'processing_time_hours',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_available' => 'boolean',
        'configuration' => 'array',
        'transaction_fee' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'processing_time_hours' => 'integer',
        'sort_order' => 'integer',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(PaymentMethodUsageLog::class, 'payment_method_provider_id');
    }

    public function employeePaymentMethods(): HasMany
    {
        return $this->hasMany(EmployeePaymentMethod::class, 'payment_method_provider_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->where('is_available', true);
    }

    public function scopeBanks($query)
    {
        return $query->whereHas('paymentMethod', fn ($subQuery) => $subQuery->where('method_type', 'bank'));
    }

    public function scopeEwallets($query)
    {
        return $query->whereHas('paymentMethod', fn ($subQuery) => $subQuery->where('method_type', 'ewallet'));
    }

    public function getFormattedFeeAttribute(): string
    {
        if ($this->fee_type === 'percentage') {
            return $this->transaction_fee . '%';
        }

        return '₱' . number_format((float) ($this->transaction_fee ?? 0), 2);
    }

    public function calculateFee(float $amount): float
    {
        if ($this->fee_type === 'percentage') {
            return ($amount * (float) $this->transaction_fee) / 100;
        }

        return (float) ($this->transaction_fee ?? 0);
    }

    public function isAmountValid(float $amount): bool
    {
        if ($this->min_amount !== null && $amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }
}