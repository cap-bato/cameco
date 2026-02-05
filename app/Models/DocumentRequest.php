<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DocumentRequest extends Model
{
    /**
     * Request sources
     */
    const SOURCES = [
        'employee_portal' => 'Employee Portal',
        'manual' => 'Manual Request',
        'email' => 'Email Request',
    ];

    /**
     * Request statuses
     */
    const STATUSES = [
        'pending' => 'Pending',
        'processed' => 'Processed',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'employee_id',
        'document_type',
        'purpose',
        'request_source',
        'requested_at',
        'status',
        'processed_by',
        'processed_at',
        'file_path',
        'notes',
        'rejection_reason',
        'employee_notified_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'employee_notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Processed by User
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope: Pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Processed requests
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope: Rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: For employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: By document type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope: By request source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('request_source', $source);
    }

    /**
     * Scope: Unnotified employees
     */
    public function scopeUnnotified($query)
    {
        return $query->whereNull('employee_notified_at');
    }

    /**
     * Scope: Recent requests
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('requested_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Accessor: Source label
     */
    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->request_source] ?? $this->request_source;
    }

    /**
     * Accessor: Status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Days since requested
     */
    public function getDaysSinceRequestedAttribute(): int
    {
        return $this->requested_at->diffInDays(Carbon::now());
    }

    /**
     * Method: Process request (mark as processed and generate document)
     *
     * @param User $user Processing user
     * @param string $filePath Path to generated document
     * @param string|null $notes Additional notes
     * @return bool Success status
     */
    public function process(User $user, string $filePath, ?string $notes = null): bool
    {
        $this->update([
            'status' => 'processed',
            'processed_by' => $user->id,
            'processed_at' => Carbon::now(),
            'file_path' => $filePath,
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Method: Reject request
     *
     * @param User|null $user Rejecting user
     * @param string $reason Rejection reason
     * @param string|null $notes Additional notes
     * @return bool Success status
     */
    public function reject(?User $user, string $reason, ?string $notes = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'notes' => $notes,
            'processed_by' => $user?->id,
            'processed_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Method: Mark employee as notified
     *
     * @return bool Success status
     */
    public function markEmployeeNotified(): bool
    {
        return $this->update(['employee_notified_at' => Carbon::now()]);
    }

    /**
     * Method: Check if request is pending
     *
     * @return bool True if pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Method: Check if request is processed
     *
     * @return bool True if processed
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Method: Check if employee has been notified
     *
     * @return bool True if notified
     */
    public function isEmployeeNotified(): bool
    {
        return $this->employee_notified_at !== null;
    }
}
