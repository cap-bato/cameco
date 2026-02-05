<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DocumentAuditLog extends Model
{
    // No timestamps except created_at (immutable audit log)
    public $timestamps = false;

    /**
     * Audit actions
     */
    const ACTIONS = [
        'uploaded' => 'Document Uploaded',
        'downloaded' => 'Document Downloaded',
        'approved' => 'Document Approved',
        'rejected' => 'Document Rejected',
        'deleted' => 'Document Deleted',
        'bulk_uploaded' => 'Bulk Uploaded',
        'reminder_sent' => 'Reminder Sent',
        'viewed' => 'Document Viewed',
        'restored' => 'Document Restored',
    ];

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship: Employee Document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    /**
     * Relationship: User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: By action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: By user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: By document
     */
    public function scopeByDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope: Recent logs
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days))->orderByDesc('created_at');
    }

    /**
     * Scope: Download logs
     */
    public function scopeDownloads($query)
    {
        return $query->where('action', 'downloaded');
    }

    /**
     * Scope: Approval logs
     */
    public function scopeApprovals($query)
    {
        return $query->where('action', 'approved');
    }

    /**
     * Scope: Rejection logs
     */
    public function scopeRejections($query)
    {
        return $query->where('action', 'rejected');
    }

    /**
     * Accessor: Action label
     */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Accessor: User name
     */
    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?? 'System';
    }

    /**
     * Accessor: Time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }

    /**
     * Static method: Log an action for a document
     *
     * @param EmployeeDocument $document Document being logged
     * @param string $action Action name
     * @param User|null $user User performing action
     * @param array|null $metadata Additional metadata
     * @param string|null $ipAddress Client IP address
     * @param string|null $userAgent Client user agent
     * @return self Created audit log
     */
    public static function log(
        EmployeeDocument $document,
        string $action,
        ?User $user = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'document_id' => $document->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Method: Get formatted action
     */
    public function getFormattedAction(): string
    {
        return $this->action_label;
    }

    /**
     * Method: Get formatted timestamp
     */
    public function getFormattedTimestamp(): string
    {
        return $this->created_at?->format('M d, Y H:i:s') ?? '';
    }

    /**
     * Method: Get formatted user
     */
    public function getFormattedUser(): string
    {
        if (!$this->user) {
            return 'System';
        }

        return $this->user->name;
    }
}
