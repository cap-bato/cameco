<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaymentMethod extends Model
{
    protected $fillable = [
        'employee_id',
        'payment_method_provider_id',
        'account_number',
        'account_name',
        'mobile_number',
        'is_default',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethodProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodProvider::class, 'payment_method_provider_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getMaskedAccountNumberAttribute(): string
    {
        if (!$this->account_number) {
            return 'N/A';
        }

        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    public function getMaskedMobileNumberAttribute(): string
    {
        if (!$this->mobile_number) {
            return 'N/A';
        }

        return substr($this->mobile_number, 0, 4) . '***' . substr($this->mobile_number, -4);
    }
}
