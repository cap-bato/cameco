<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RfidLedger Model
 * 
 * Represents an immutable RFID scan event in the append-only ledger.
 * Populated by FastAPI RFID server, never modified by Laravel.
 * 
 * Task 5.1.1: Database model for rfid_ledger table
 * 
 * @property int $id
 * @property int $sequence_id Unique sequential ID for ordering
 * @property string $employee_rfid RFID card identifier
 * @property string $device_id RFID scanner identifier
 * @property \Carbon\Carbon $scan_timestamp Exact scan timestamp
 * @property string $event_type time_in, time_out, break_start, break_end
 * @property array $raw_payload Complete event JSON payload
 * @property string $hash_chain SHA-256 hash of (prev_hash || payload)
 * @property string|null $hash_previous Hash of previous entry
 * @property string|null $device_signature Optional Ed25519 signature
 * @property int|null $latency_ms Processing latency in milliseconds
 * @property bool $processed Whether entry has been processed
 * @property \Carbon\Carbon|null $processed_at When entry was processed
 * @property \Carbon\Carbon $created_at Server creation timestamp
 * 
 * @method static \Illuminate\Database\Eloquent\Builder whereProcessed(bool $processed)
 * @method static \Illuminate\Database\Eloquent\Builder orderBySequence()
 */
class RfidLedger extends Model
{
    use HasFactory;

    // Disable timestamps since we manage created_at directly
    public $timestamps = false;

    // Table name
    protected $table = 'rfid_ledger';

    // Mass assignable attributes
    protected $fillable = [
        'sequence_id',
        'employee_rfid',
        'device_id',
        'scan_timestamp',
        'event_type',
        'raw_payload',
        'hash_chain',
        'hash_previous',
        'device_signature',
        'latency_ms',
        'processed',
        'processed_at',
        'created_at',
    ];

    // Cast attributes to appropriate types
    protected $casts = [
        'raw_payload' => 'array',
        'scan_timestamp' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'processed' => 'boolean',
        'latency_ms' => 'integer',
    ];

    // Scope: Get unprocessed entries
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    // Scope: Get entries by employee RFID
    public function scopeByEmployeeRfid($query, string $rfid)
    {
        return $query->where('employee_rfid', $rfid);
    }

    // Scope: Get entries by device
    public function scopeByDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    // Scope: Get entries within date range
    public function scopeInDateRange($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->whereBetween('scan_timestamp', [$from, $to]);
    }

    // Scope: Order by sequence ID (ascending)
    public function scopeOrderBySequence($query)
    {
        return $query->orderBy('sequence_id', 'asc');
    }

    // Relationship: Related attendance events (processed ledger entries)
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'ledger_sequence_id', 'sequence_id');
    }

    // Relationship: Employee (via RfidCardMapping)
    // Note: This is accessed via eager loading of RfidCardMapping first
    public function rfidCardMapping()
    {
        return $this->belongsTo(\App\Models\RfidCardMapping::class, 'employee_rfid', 'card_uid');
    }

    // Relationship: RFID Device
    public function device()
    {
        return $this->belongsTo(\App\Models\RfidDevice::class, 'device_id', 'device_id');
    }
}
