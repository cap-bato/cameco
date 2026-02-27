<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'method_type',
        'display_name',
        'description',
        'is_enabled',
        'requires_employee_setup',
        'supports_bulk_payment',
        'transaction_fee',
        'min_amount',
        'max_amount',
        'settlement_speed',
        'processing_days',
        'cutoff_time',
        'bank_code',
        'bank_name',
        'file_format',
        'file_template',
        'provider_name',
        'api_endpoint',
        'api_credentials',
        'webhook_url',
        'sort_order',
        'icon',
        'color_hex',
        'configured_by',
        'last_used_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires_employee_setup' => 'boolean',
        'supports_bulk_payment' => 'boolean',
        'transaction_fee' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'processing_days' => 'integer',
        'cutoff_time' => 'datetime:H:i:s',
        'file_template' => 'array',
        'sort_order' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_credentials', // Sensitive data
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function employeePreferences(): HasMany
    {
        return $this->hasMany(EmployeePaymentPreference::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function bankFileBatches(): HasMany
    {
        return $this->hasMany(BankFileBatch::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeCash($query)
    {
        return $query->where('method_type', 'cash');
    }

    public function scopeBank($query)
    {
        return $query->where('method_type', 'bank');
    }

    public function scopeEwallet($query)
    {
        return $query->where('method_type', 'ewallet');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isCash(): bool
    {
        return $this->method_type === 'cash';
    }

    public function isBank(): bool
    {
        return $this->method_type === 'bank';
    }

    public function isEwallet(): bool
    {
        return $this->method_type === 'ewallet';
    }

    public function supportsAmount(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    public function calculateFee(float $amount): float
    {
        return $this->transaction_fee ?? 0;
    }

    public function isAvailableForPayment(\DateTime $paymentDate): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // Check cutoff time for same-day settlement
        if ($this->settlement_speed === 'same_day' && $this->cutoff_time) {
            $now = now();
            $cutoff = Carbon::parse($this->cutoff_time);

            if ($now->greaterThan($cutoff)) {
                return false;
            }
        }

        return true;
    }
}
