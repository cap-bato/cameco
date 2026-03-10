<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RfidDevice Model
 * 
 * Represents an RFID scanner device in the system.
 * Tracks device status, location, and health metrics.
 * 
 * @property int $id
 * @property string $device_id Unique device identifier (e.g., GATE-01)
 * @property string $device_name Human-readable device name
 * @property string $location Physical location of device
 * @property string $status online, offline, maintenance
 * @property \Carbon\Carbon|null $last_heartbeat Last heartbeat timestamp
 * @property array|null $config Device configuration JSON
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class RfidDevice extends Model
{
    use HasFactory;

    protected $table = 'rfid_devices';

    protected $fillable = [
        'device_id',
        'device_name',
        'location',
        'status',
        'last_heartbeat',
        'config',
    ];

    protected $casts = [
        'last_heartbeat' => 'datetime',
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship: Ledger entries from this device
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(RfidLedger::class, 'device_id', 'device_id');
    }

    // Scope: Get online devices
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    // Scope: Get offline devices
    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    // Check if device is online (heartbeat within last 10 minutes)
    public function isOnline(): bool
    {
        if (!$this->last_heartbeat) {
            return false;
        }
        return $this->last_heartbeat->gt(now()->subMinutes(10));
    }
}
