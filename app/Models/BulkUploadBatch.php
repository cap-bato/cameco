<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class BulkUploadBatch extends Model
{
    use HasFactory;
    /**
     * Batch statuses
     */
    const STATUSES = [
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'partially_completed' => 'Partially Completed',
    ];

    protected $fillable = [
        'uploaded_by',
        'status',
        'total_count',
        'success_count',
        'error_count',
        'csv_file_path',
        'error_log',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_log' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_count' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
    ];

    /**
     * Relationship: Uploaded by User
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Relationship: Employee Documents
     */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'bulk_upload_batch_id');
    }

    /**
     * Scope: Completed batches
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partially_completed']);
    }

    /**
     * Scope: Failed batches
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Processing batches
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope: By uploader
     */
    public function scopeByUploader($query, $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Scope: Recent batches
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('started_at', '>=', Carbon::now()->subDays($days))->orderByDesc('started_at');
    }

    /**
     * Accessor: Status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_count === 0) {
            return 0;
        }

        return round(($this->success_count / $this->total_count) * 100, 2);
    }

    /**
     * Accessor: Error rate percentage
     */
    public function getErrorRateAttribute(): float
    {
        if ($this->total_count === 0) {
            return 0;
        }

        return round(($this->error_count / $this->total_count) * 100, 2);
    }

    /**
     * Accessor: Processing duration (in minutes)
     */
    public function getProcessingDurationAttribute(): ?float
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInMinutes($this->started_at);
    }

    /**
     * Accessor: Is processing
     */
    public function getIsProcessingAttribute(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Accessor: Is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'partially_completed']);
    }

    /**
     * Accessor: Is failed
     */
    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Method: Mark as processing
     */
    public function markProcessing(): bool
    {
        return $this->update([
            'status' => 'processing',
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Method: Mark as completed
     */
    public function markCompleted(): bool
    {
        $status = $this->error_count > 0 ? 'partially_completed' : 'completed';

        return $this->update([
            'status' => $status,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Method: Mark as failed
     */
    public function markFailed(string $reason): bool
    {
        return $this->update([
            'status' => 'failed',
            'error_log' => [
                'general_error' => $reason,
            ],
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Method: Add error
     */
    public function addError(int $rowNumber, string $error, ?array $rowData = null): bool
    {
        $errorLog = $this->error_log ?? [];

        if (!isset($errorLog['rows'])) {
            $errorLog['rows'] = [];
        }

        $errorLog['rows'][$rowNumber] = [
            'error' => $error,
            'data' => $rowData,
        ];

        $this->error_count = count($errorLog['rows']);

        return $this->update([
            'error_log' => $errorLog,
            'error_count' => $this->error_count,
        ]);
    }

    /**
     * Method: Increment success count
     */
    public function incrementSuccess(): bool
    {
        return $this->increment('success_count');
    }

    /**
     * Method: Get error details for a row
     */
    public function getRowError(int $rowNumber): ?array
    {
        return $this->error_log['rows'][$rowNumber] ?? null;
    }

    /**
     * Method: Get all row errors
     */
    public function getRowErrors(): array
    {
        return $this->error_log['rows'] ?? [];
    }

    /**
     * Method: Get summary statistics
     */
    public function getSummary(): array
    {
        return [
            'status' => $this->status_label,
            'total' => $this->total_count,
            'success' => $this->success_count,
            'errors' => $this->error_count,
            'success_rate' => $this->success_rate . '%',
            'error_rate' => $this->error_rate . '%',
            'duration' => $this->processing_duration ? $this->processing_duration . ' minutes' : 'In progress',
            'started_at' => $this->started_at?->format('M d, Y H:i:s'),
            'completed_at' => $this->completed_at?->format('M d, Y H:i:s'),
        ];
    }
}
