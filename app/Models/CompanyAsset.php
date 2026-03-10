<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAsset extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'asset_type',
        'asset_name',
        'serial_number',
        'brand',
        'model',
        'employee_id',
        'assigned_date',
        'assigned_by',
        'condition_at_issuance',
        'value_at_issuance',
        'photo_at_issuance',
        'status',
        'return_date',
        'condition_at_return',
        'return_notes',
        'photo_at_return',
        'received_by',
        'liability_amount',
        'deducted_from_final_pay',
        'offboarding_case_id',
        'clearance_item_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assigned_date' => 'date:Y-m-d',
        'return_date' => 'date:Y-m-d',
        'value_at_issuance' => 'float',
        'liability_amount' => 'float',
        'deducted_from_final_pay' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee this asset is assigned to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who assigned this asset.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the user who received the asset upon return.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get the offboarding case this asset is associated with.
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * Get the clearance item this asset is associated with.
     */
    public function clearanceItem(): BelongsTo
    {
        return $this->belongsTo(ClearanceItem::class);
    }

    /**
     * Scope: Get issued assets.
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope: Get returned assets.
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Scope: Get lost or damaged assets.
     */
    public function scopeLostOrDamaged($query)
    {
        return $query->whereIn('status', ['lost', 'damaged']);
    }

    /**
     * Scope: Get assets with liability.
     */
    public function scopeWithLiability($query)
    {
        return $query->where('liability_amount', '>', 0);
    }

    /**
     * Scope: Get assets by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('asset_type', $type);
    }

    /**
     * Scope: Get assets assigned to an employee.
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Mark asset as returned.
     */
    public function markAsReturned(User $receivedBy, string $condition, ?string $notes = null, ?string $photoPath = null): void
    {
        $this->update([
            'status' => 'returned',
            'return_date' => now()->toDateString(),
            'condition_at_return' => $condition,
            'return_notes' => $notes,
            'photo_at_return' => $photoPath,
            'received_by' => $receivedBy->id,
        ]);
    }

    /**
     * Mark asset as lost.
     */
    public function markAsLost(string $reason, float $liabilityAmount): void
    {
        $this->update([
            'status' => 'lost',
            'return_notes' => $reason,
            'return_date' => now()->toDateString(),
            'liability_amount' => $liabilityAmount,
        ]);
    }

    /**
     * Mark asset as damaged.
     */
    public function markAsDamaged(string $damageDescription, float $liabilityAmount): void
    {
        $this->update([
            'status' => 'damaged',
            'condition_at_return' => 'damaged',
            'return_notes' => $damageDescription,
            'return_date' => now()->toDateString(),
            'liability_amount' => $liabilityAmount,
        ]);
    }

    /**
     * Mark asset as written off.
     */
    public function markAsWrittenOff(string $reason): void
    {
        $this->update([
            'status' => 'written_off',
            'return_notes' => $reason,
            'return_date' => now()->toDateString(),
            'liability_amount' => 0,
        ]);
    }

    /**
     * Check if asset has been returned.
     */
    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    /**
     * Check if asset has liability.
     */
    public function hasLiability(): bool
    {
        return $this->liability_amount > 0;
    }

    /**
     * Get the asset description for display.
     */
    public function getDisplayName(): string
    {
        $parts = array_filter([
            $this->asset_name,
            $this->brand,
            $this->model,
            $this->serial_number ? "SN: {$this->serial_number}" : null,
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Get the current condition.
     */
    public function getCurrentCondition(): string
    {
        if ($this->status === 'returned') {
            return $this->condition_at_return ?? 'Not specified';
        }

        return $this->condition_at_issuance ?? 'Not specified';
    }

    /**
     * Get the asset status label.
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'issued' => 'Issued',
            'returned' => 'Returned',
            'lost' => 'Lost',
            'damaged' => 'Damaged',
            'written_off' => 'Written Off',
            default => ucfirst($this->status),
        };
    }
}
