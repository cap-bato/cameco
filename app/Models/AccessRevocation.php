<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRevocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offboarding_case_id',
        'employee_id',
        'system_name',
        'system_category',
        'account_identifier',
        'access_level',
        'granted_date',
        'status',
        'revoked_by',
        'revoked_at',
        'data_backed_up',
        'backup_location',
        'backup_completed_by',
        'backup_completed_at',
        'revocation_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'granted_date' => 'date:Y-m-d',
        'revoked_at' => 'datetime',
        'backup_completed_at' => 'datetime',
        'data_backed_up' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the offboarding case this revocation belongs to.
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * Get the employee whose access is being revoked.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who revoked the access.
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Get the user who completed the data backup.
     */
    public function backupCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'backup_completed_by');
    }

    /**
     * Scope: Get active access.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Get revoked access.
     */
    public function scopeRevoked($query)
    {
        return $query->where('status', 'revoked');
    }

    /**
     * Scope: Get disabled access.
     */
    public function scopeDisabled($query)
    {
        return $query->where('status', 'disabled');
    }

    /**
     * Scope: Get pending revocations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get access needing backup.
     */
    public function scopeNeedingBackup($query)
    {
        return $query->where('data_backed_up', false)
            ->whereIn('status', ['active', 'pending']);
    }

    /**
     * Scope: Get by system category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('system_category', $category);
    }

    /**
     * Scope: Get by system name.
     */
    public function scopeBySystem($query, string $system)
    {
        return $query->where('system_name', $system);
    }

    /**
     * Revoke the access.
     */
    public function revoke(User $revokedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_by' => $revokedBy->id,
            'revoked_at' => now(),
            'revocation_notes' => $notes ?? $this->revocation_notes,
        ]);
    }

    /**
     * Disable the access.
     */
    public function disable(User $disabledBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'disabled',
            'revoked_by' => $disabledBy->id,
            'revoked_at' => now(),
            'revocation_notes' => $notes ?? $this->revocation_notes,
        ]);
    }

    /**
     * Mark data as backed up.
     */
    public function markDataAsBackedUp(User $completedBy, string $backupLocation): void
    {
        $this->update([
            'data_backed_up' => true,
            'backup_location' => $backupLocation,
            'backup_completed_by' => $completedBy->id,
            'backup_completed_at' => now(),
        ]);
    }

    /**
     * Check if this access needs backup.
     */
    public function needsBackup(): bool
    {
        return !$this->data_backed_up && in_array($this->status, ['active', 'pending']);
    }

    /**
     * Check if this access has been revoked.
     */
    public function isRevoked(): bool
    {
        return in_array($this->status, ['revoked', 'disabled', 'archived']);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'active' => 'Active',
            'disabled' => 'Disabled',
            'revoked' => 'Revoked',
            'archived' => 'Archived',
            'pending' => 'Pending',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the system category label.
     */
    public function getSystemCategoryLabel(): string
    {
        return match($this->system_category) {
            'email' => 'Email',
            'network' => 'Network',
            'application' => 'Application',
            'physical_access' => 'Physical Access',
            'cloud_service' => 'Cloud Service',
            'other' => 'Other',
            default => ucfirst($this->system_category),
        };
    }

    /**
     * Get a summary of the access revocation.
     */
    public function getSummary(): string
    {
        $emoji = match($this->system_category) {
            'email' => '📧',
            'network' => '🌐',
            'application' => '💻',
            'physical_access' => '🔐',
            'cloud_service' => '☁️',
            default => '⚙️',
        };

        return "{$emoji} {$this->system_name} ({$this->getStatusLabel()})";
    }
}
