<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class EmployeeDocument extends Model
{
    use SoftDeletes, HasFactory;

    /**
     * Document categories
     */
    const CATEGORIES = [
        'personal' => 'Personal Documents',
        'educational' => 'Educational Documents',
        'employment' => 'Employment Documents',
        'medical' => 'Medical Documents',
        'contracts' => 'Contracts',
        'benefits' => 'Benefits Documents',
        'performance' => 'Performance Documents',
        'separation' => 'Separation Documents',
        'government' => 'Government Documents',
        'special' => 'Special Documents',
    ];

    /**
     * Document statuses
     */
    const STATUSES = [
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'auto_approved' => 'Auto-Approved',
    ];

    /**
     * Document sources
     */
    const SOURCES = [
        'manual' => 'Manual Upload',
        'bulk' => 'Bulk Upload',
        'employee_portal' => 'Employee Portal',
    ];

    protected $fillable = [
        'employee_id',
        'document_category',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
        'uploaded_at',
        'expires_at',
        'status',
        'requires_approval',
        'is_critical',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'reminder_sent_at',
        'bulk_upload_batch_id',
        'source',
        'retention_expires_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'expires_at' => 'date',
        'approved_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'retention_expires_at' => 'date',
        'requires_approval' => 'boolean',
        'is_critical' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: Employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relationship: Uploaded by User
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Relationship: Approved by User
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Bulk Upload Batch
     */
    public function bulkUploadBatch(): BelongsTo
    {
        return $this->belongsTo(BulkUploadBatch::class);
    }

    /**
     * Relationship: Audit Logs
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(DocumentAuditLog::class, 'document_id');
    }

    /**
     * Scope: Active documents (not deleted)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope: Pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Approved documents
     */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'auto_approved']);
    }

    /**
     * Scope: Documents requiring approval
     */
    public function scopeRequiringApproval($query)
    {
        return $query->where('requires_approval', true)->where('status', 'pending');
    }

    /**
     * Scope: Critical documents
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    /**
     * Scope: Expired documents
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->whereDate('expires_at', '<', Carbon::today());
    }

    /**
     * Scope: Expiring soon (within 30 days)
     */
    public function scopeExpiringWithin($query, $days = 30)
    {
        return $query
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', Carbon::today()->addDays($days))
            ->whereDate('expires_at', '>', Carbon::today());
    }

    /**
     * Scope: For employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('document_category', $category);
    }

    /**
     * Scope: By document type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Accessor: File URL for download
     */
    public function getFileUrlAttribute(): string
    {
        return storage_path('app/employee-documents/' . $this->file_path);
    }

    /**
     * Accessor: Formatted file size
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size ?? 0;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Accessor: Check if document is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return Carbon::parse($this->expires_at)->isPast();
    }

    /**
     * Accessor: Days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        $daysUntil = Carbon::today()->diffInDays($this->expires_at, false);
        return $daysUntil >= 0 ? $daysUntil : 0;
    }

    /**
     * Accessor: Status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->document_category] ?? $this->document_category;
    }

    /**
     * Method: Approve document
     */
    public function approve(?User $user, ?string $notes = null): bool
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $user?->id,
            'approved_at' => Carbon::now(),
            'notes' => $notes,
        ]);

        // Log approval action
        DocumentAuditLog::log($this, 'approved', $user);

        return true;
    }

    /**
     * Method: Reject document
     */
    public function reject(?User $user, string $reason, ?string $notes = null): bool
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'notes' => $notes,
        ]);

        // Log rejection action
        DocumentAuditLog::log($this, 'rejected', $user);

        return true;
    }

    /**
     * Method: Mark reminder sent
     */
    public function markReminderSent(?User $user = null): bool
    {
        $this->update(['reminder_sent_at' => Carbon::now()]);

        // Log reminder action
        if ($user) {
            DocumentAuditLog::log($this, 'reminder_sent', $user);
        }

        return true;
    }

    /**
     * Method: Auto-approve document
     */
    public function autoApprove(): bool
    {
        $this->update([
            'status' => 'auto_approved',
            'approved_at' => Carbon::now(),
        ]);

        // Log auto-approval action
        DocumentAuditLog::log($this, 'approved', null);

        return true;
    }

    /**
     * Method: Soft delete with retention expiry
     */
    public function softDeleteWithRetention(?Carbon $retentionExpiry = null): bool
    {
        $expiry = $retentionExpiry ?? Carbon::now()->addYears(5);

        $this->update([
            'retention_expires_at' => $expiry->toDateString(),
            'deleted_at' => Carbon::now(),
        ]);

        return true;
    }
}
