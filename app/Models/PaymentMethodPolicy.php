<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodPolicy extends Model
{
    protected $fillable = [
        'department_id',
        'employee_level',
        'default_payment_method_provider_id',
        'allowed_payment_method_providers',
        'allow_employee_change',
        'approval_required_for_change',
    ];

    protected $casts = [
        'allowed_payment_method_providers' => 'array',
        'allow_employee_change' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function defaultPaymentMethodProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodProvider::class, 'default_payment_method_provider_id');
    }

    public function getAllowedMethodsAttribute()
    {
        $ids = $this->allowed_payment_method_providers ?? [];

        return PaymentMethodProvider::whereIn('id', $ids)->get();
    }

    public function isMethodAllowed(int $paymentMethodProviderId): bool
    {
        return in_array($paymentMethodProviderId, $this->allowed_payment_method_providers ?? [], true);
    }
}
