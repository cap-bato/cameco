<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit log for all payment-related actions.
 *
 * No SoftDeletes — records are retained for 7 years per BIR requirement
 * (Decision #21). Archival is handled by the artisan command:
 *   php artisan payroll:archive-audit-logs --before=YYYY-MM-DD
 */
class PaymentAuditLog extends Model
{
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'actor_type',
        'actor_id',
        'actor_name',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'request_id',
        'notes',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'actor_id'   => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByActor($query, string $actorType, int $actorId)
    {
        return $query->where('actor_type', $actorType)->where('actor_id', $actorId);
    }

    public function scopeByAuditable($query, string $type, int $id)
    {
        return $query->where('auditable_type', $type)->where('auditable_id', $id);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeForAuditTrail($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isSystemAction(): bool
    {
        return $this->actor_type === 'system';
    }

    public function isWebhookAction(): bool
    {
        return $this->actor_type === 'webhook';
    }

    public function isUserAction(): bool
    {
        return $this->actor_type === 'user';
    }

    public function getActorLabel(): string
    {
        return match ($this->actor_type) {
            'system'  => 'System',
            'webhook' => 'Webhook',
            'user'    => $this->actor_name ?? "User #{$this->actor_id}",
            default   => $this->actor_name ?? 'Unknown',
        };
    }

    // ============================================================
    // Static Helpers
    // ============================================================

    /**
     * Convenience method to record an audit entry for any auditable model.
     *
     * Usage:
     *   PaymentAuditLog::record($payment, 'paid', 'user', auth()->id(), auth()->user()->name, $old, $new);
     */
    public static function record(
        Model $model,
        string $action,
        string $actorType,
        ?int $actorId = null,
        ?string $actorName = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $notes = null
    ): static {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'actor_type'     => $actorType,
            'actor_id'       => $actorId,
            'actor_name'     => $actorName,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'metadata'       => $metadata,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'request_id'     => request()->header('X-Request-ID'),
            'notes'          => $notes,
        ]);
    }
}
