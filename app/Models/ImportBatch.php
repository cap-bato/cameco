<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ImportBatch Model
 * 
 * Represents a bulk import of attendance data from CSV/Excel file.
 * Tracks processing status and error handling.
 * 
 * Task 5.1: Database model for import_batches table
 * 
 * @property int $id
 * @property string $file_name Name of imported file
 * @property string $file_path Path where file is stored
 * @property int $file_size Size in bytes
 * @property string $import_type attendance, schedule, correction
 * @property int $total_records Total records in file
 * @property int $processed_records Records processed
 * @property int $successful_records Records successfully imported
 * @property int $failed_records Records that failed
 * @property string $status uploaded, processing, completed, failed
 * @property \Carbon\Carbon|null $started_at When processing started
 * @property \Carbon\Carbon|null $completed_at When processing finished
 * @property string|null $error_log Error details
 * @property int $imported_by User who initiated import
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ImportBatch extends Model
{
    // Table name
    protected $table = 'import_batches';

    // Mass assignable attributes
    protected $fillable = [
        'file_name',
        'file_path',
        'file_size',
        'import_type',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'status',
        'started_at',
        'completed_at',
        'error_log',
        'imported_by',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'file_size' => 'integer',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'successful_records' => 'integer',
        'failed_records' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('import_type', $type);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'uploaded');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helper methods
    public function successRate(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }
        return ($this->successful_records / $this->total_records) * 100;
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorLog): void
    {
        $this->update([
            'status' => 'failed',
            'error_log' => $errorLog,
            'completed_at' => now(),
        ]);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_records', $count);
    }

    public function incrementSuccessful(int $count = 1): void
    {
        $this->increment('successful_records', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('failed_records', $count);
    }
}
