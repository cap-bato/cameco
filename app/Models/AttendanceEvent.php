<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceEvent Model
 * 
 * Represents a processed attendance event derived from rfid_ledger.
 * Includes ledger traceability and hash verification flags.
 * 
 * Task 5.1.2: Database model for attendance_events table with ledger linking
 * 
 * @property int $id
 * @property int $employee_id Reference to employee
 * @property \Carbon\Carbon $event_date Date of event
 * @property \Carbon\Carbon $event_time Exact timestamp
 * @property string $event_type time_in, time_out, break_start, break_end, overtime_start, overtime_end
 * @property int|null $ledger_sequence_id Reference to rfid_ledger.sequence_id
 * @property bool $is_deduplicated Whether duplicate tap was detected/handled
 * @property bool $ledger_hash_verified Whether SHA-256 hash chain validation passed
 * @property string $source edge_machine, manual, imported
 * @property int|null $imported_batch_id Reference to import batch
 * @property bool $is_corrected Whether event has been corrected
 * @property \Carbon\Carbon|null $original_time Original timestamp before correction
 * @property string|null $correction_reason Reason for correction
 * @property int|null $corrected_by User who corrected the event
 * @property \Carbon\Carbon|null $corrected_at When correction was made
 * @property string|null $device_id RFID scanner identifier
 * @property string|null $location Physical location of event
 * @property string|null $notes Additional notes
 * @property array|null $ledger_raw_payload Copy of rfid_ledger raw_payload for audit
 * @property int|null $created_by User who created manual entry
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AttendanceEvent extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'attendance_events';

    // Mass assignable attributes
    protected $fillable = [
        'employee_id',
        'event_date',
        'event_time',
        'event_type',
        'ledger_sequence_id',
        'is_deduplicated',
        'ledger_hash_verified',
        'source',
        'imported_batch_id',
        'is_corrected',
        'original_time',
        'correction_reason',
        'corrected_by',
        'corrected_at',
        'device_id',
        'location',
        'notes',
        'ledger_raw_payload',
        'created_by',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'datetime',
        'original_time' => 'datetime',
        'corrected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_corrected' => 'boolean',
        'is_deduplicated' => 'boolean',
        'ledger_hash_verified' => 'boolean',
        'ledger_raw_payload' => 'array',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function correctedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeFromLedger($query)
    {
        return $query->whereNotNull('ledger_sequence_id');
    }

    public function scopeManualEntry($query)
    {
        return $query->where('source', 'manual');
    }

    public function scopeFromEdgeMachine($query)
    {
        return $query->where('source', 'edge_machine');
    }

    public function scopeImported($query)
    {
        return $query->where('source', 'imported');
    }

    public function scopeHashVerified($query)
    {
        return $query->where('ledger_hash_verified', true);
    }

    public function scopeNotCorrected($query)
    {
        return $query->where('is_corrected', false);
    }

    public function scopeDeduplicatedTaps($query)
    {
        return $query->where('is_deduplicated', true);
    }

    public function scopeInDateRange($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->whereBetween('event_date', [$from, $to]);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
