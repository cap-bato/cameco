<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCalculationLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'payroll_calculation_logs';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payroll_period_id',
        'log_type',
        'severity',
        'message',
        'details',
        'metadata',
        'employees_processed',
        'employees_success',
        'employees_failed',
        'exceptions_generated',
        'processing_time_seconds',
        'actor_type',
        'actor_id',
        'actor_name',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'employees_processed' => 'integer',
        'employees_success' => 'integer',
        'employees_failed' => 'integer',
        'exceptions_generated' => 'integer',
        'processing_time_seconds' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeByType($query, string $type)
    {
        return $query->where('log_type', $type);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeInfo($query)
    {
        return $query->where('severity', 'info');
    }

    public function scopeWarning($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeError($query)
    {
        return $query->where('severity', 'error');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeByActor($query, string $actorType, ?int $actorId = null)
    {
        $query->where('actor_type', $actorType);
        
        if ($actorId !== null) {
            $query->where('actor_id', $actorId);
        }
        
        return $query;
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isError(): bool
    {
        return in_array($this->severity, ['error', 'critical']);
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    public function isInfo(): bool
    {
        return $this->severity === 'info';
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function hasProcessingStats(): bool
    {
        return $this->employees_processed !== null;
    }

    public function getSuccessRate(): ?float
    {
        if (!$this->hasProcessingStats() || $this->employees_processed === 0) {
            return null;
        }

        return ($this->employees_success / $this->employees_processed) * 100;
    }

    public function getFailureRate(): ?float
    {
        if (!$this->hasProcessingStats() || $this->employees_processed === 0) {
            return null;
        }

        return ($this->employees_failed / $this->employees_processed) * 100;
    }

    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
            default => 'gray',
        };
    }

    public function getSeverityIcon(): string
    {
        return match ($this->severity) {
            'critical' => 'alert-octagon',
            'error' => 'alert-circle',
            'warning' => 'alert-triangle',
            'info' => 'info',
            default => 'circle',
        };
    }

    public function getFormattedProcessingTime(): string
    {
        if (!$this->processing_time_seconds) {
            return 'N/A';
        }

        $seconds = (float) $this->processing_time_seconds;

        if ($seconds < 1) {
            return number_format($seconds * 1000, 0) . 'ms';
        }

        if ($seconds < 60) {
            return number_format($seconds, 2) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $minutes . 'm ' . number_format($remainingSeconds, 0) . 's';
    }

    // ============================================================
    // Static Methods for Logging
    // ============================================================

    public static function logCalculationStarted(
        int $periodId,
        int $employeeCount,
        ?User $user = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'payroll_period_id' => $periodId,
            'log_type' => 'calculation_started',
            'severity' => 'info',
            'message' => "Payroll calculation started for {$employeeCount} employees",
            'metadata' => $metadata,
            'employees_processed' => 0,
            'actor_type' => $user ? 'user' : 'system',
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function logCalculationCompleted(
        int $periodId,
        int $processed,
        int $success,
        int $failed,
        int $exceptions,
        float $processingTime,
        ?User $user = null
    ): self {
        return self::create([
            'payroll_period_id' => $periodId,
            'log_type' => 'calculation_completed',
            'severity' => $failed > 0 ? 'warning' : 'info',
            'message' => "Payroll calculation completed: {$success}/{$processed} successful, {$failed} failed, {$exceptions} exceptions",
            'employees_processed' => $processed,
            'employees_success' => $success,
            'employees_failed' => $failed,
            'exceptions_generated' => $exceptions,
            'processing_time_seconds' => $processingTime,
            'actor_type' => $user ? 'user' : 'system',
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function logCalculationFailed(
        int $periodId,
        string $error,
        ?array $details = null,
        ?User $user = null
    ): self {
        return self::create([
            'payroll_period_id' => $periodId,
            'log_type' => 'calculation_failed',
            'severity' => 'critical',
            'message' => "Payroll calculation failed: {$error}",
            'details' => $details ? json_encode($details) : null,
            'actor_type' => $user ? 'user' : 'system',
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
