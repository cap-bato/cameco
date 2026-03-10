<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LedgerHealthLog Model
 * 
 * Tracks integrity checks, health metrics, and replay operations on the RFID ledger.
 * Used for monitoring ledger health and detecting tampering or processing issues.
 * 
 * Task 5.1.4: Database model for ledger_health_logs table
 * 
 * @property int $id
 * @property \Carbon\Carbon $check_timestamp When health check was performed
 * @property int $last_sequence_id Highest sequence_id at check time
 * @property bool $gaps_detected Whether sequence gaps were found
 * @property array|null $gap_details Details of detected gaps
 * @property bool $hash_failures Whether hash chain validation failed
 * @property array|null $hash_failure_details Details of failed hashes
 * @property bool $replay_triggered Whether automatic replay was triggered
 * @property int $total_unprocessed Count of unprocessed entries
 * @property int $processing_queue_size Current queue size
 * @property float|null $processing_lag_seconds Lag in seconds
 * @property string $status healthy, warning, or critical
 * @property int $gap_count Number of gaps detected
 * @property int $hash_failure_count Number of hash failures
 * @property int $duplicate_count Number of duplicates detected
 * @property string|null $notes Human-readable summary
 * @property string|null $recommendations Recommended actions
 * @property \Carbon\Carbon $created_at
 */
class LedgerHealthLog extends Model
{
    // Disable timestamps except created_at
    public $timestamps = false;

    // Table name
    protected $table = 'ledger_health_logs';

    // Mass assignable attributes
    protected $fillable = [
        'check_timestamp',
        'last_sequence_id',
        'gaps_detected',
        'gap_details',
        'hash_failures',
        'hash_failure_details',
        'replay_triggered',
        'total_unprocessed',
        'processing_queue_size',
        'processing_lag_seconds',
        'status',
        'gap_count',
        'hash_failure_count',
        'duplicate_count',
        'notes',
        'recommendations',
        'created_at',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'check_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'gaps_detected' => 'boolean',
        'gap_details' => 'array',
        'hash_failures' => 'boolean',
        'hash_failure_details' => 'array',
        'replay_triggered' => 'boolean',
        'total_unprocessed' => 'integer',
        'processing_queue_size' => 'integer',
        'processing_lag_seconds' => 'float',
        'gap_count' => 'integer',
        'hash_failure_count' => 'integer',
        'duplicate_count' => 'integer',
    ];

    // Scopes
    public function scopeHealthy($query)
    {
        return $query->where('status', 'healthy');
    }

    public function scopeWarning($query)
    {
        return $query->where('status', 'warning');
    }

    public function scopeCritical($query)
    {
        return $query->where('status', 'critical');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('check_timestamp', '>=', now()->subHours($hours));
    }

    public function scopeWithGaps($query)
    {
        return $query->where('gaps_detected', true);
    }

    public function scopeWithHashFailures($query)
    {
        return $query->where('hash_failures', true);
    }

    public function scopeReplayTriggered($query)
    {
        return $query->where('replay_triggered', true);
    }

    public function scopeOrderByLatest($query)
    {
        return $query->orderBy('check_timestamp', 'desc');
    }

    // Helper methods
    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    public function isCritical(): bool
    {
        return $this->status === 'critical';
    }

    public function hasIssues(): bool
    {
        return $this->gaps_detected || $this->hash_failures;
    }

    public function getIssueCount(): int
    {
        return $this->gap_count + $this->hash_failure_count;
    }

    public function getSummary(): string
    {
        $summary = [];

        if ($this->gaps_detected) {
            $summary[] = "{$this->gap_count} sequence gap(s)";
        }

        if ($this->hash_failures) {
            $summary[] = "{$this->hash_failure_count} hash failure(s)";
        }

        if ($this->duplicate_count > 0) {
            $summary[] = "{$this->duplicate_count} duplicate event(s)";
        }

        if ($this->total_unprocessed > 0) {
            $summary[] = "{$this->total_unprocessed} unprocessed entry(ies)";
        }

        if (empty($summary)) {
            return 'All checks passed';
        }

        return implode(', ', $summary);
    }

    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'gray',
        };
    }
}
