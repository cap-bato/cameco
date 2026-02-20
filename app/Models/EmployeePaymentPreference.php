<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EmployeePaymentPreference extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payment_method_id',
        'is_primary',
        'priority',
        'bank_code',
        'bank_name',
        'branch_code',
        'branch_name',
        'account_number',
        'account_name',
        'account_type',
        'ewallet_provider',
        'ewallet_account_number',
        'ewallet_account_name',
        'verification_status',
        'verified_at',
        'verified_by',
        'verification_notes',
        'document_type',
        'document_path',
        'document_uploaded_at',
        'is_active',
        'last_used_at',
        'successful_payments',
        'failed_payments',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority' => 'integer',
        'verified_at' => 'datetime',
        'document_uploaded_at' => 'datetime',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'successful_payments' => 'integer',
        'failed_payments' => 'integer',
    ];

    protected $hidden = [
        'account_number', // Sensitive data
        'ewallet_account_number', // Sensitive data
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }

    // ============================================================
    // Accessors & Mutators
    // ============================================================

    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    protected function ewalletAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function maskAccountNumber(): string
    {
        if (!$this->account_number) {
            return 'N/A';
        }

        $account = $this->account_number;
        $length = strlen($account);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($account, -4);
    }

    public function getDisplayName(): string
    {
        $method = $this->paymentMethod->display_name;
        $masked = $this->maskAccountNumber();

        return "{$method} - {$masked}";
    }

    public function recordSuccess(): void
    {
        $this->increment('successful_payments');
        $this->update(['last_used_at' => now()]);
    }

    public function recordFailure(): void
    {
        $this->increment('failed_payments');
    }
}
