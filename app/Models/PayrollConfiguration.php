<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollConfiguration extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'config_value' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * Scope a query to only include active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to include configurations effective on a given date.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string|null $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now();
        
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Get a configuration value by key.
     * Returns the active configuration effective on the current date.
     * 
     * @param string $key Configuration key to retrieve
     * @param mixed $default Default value if configuration not found
     * @return mixed Configuration value or default
     */
    public static function get(string $key, $default = null)
    {
        $config = self::active()
            ->effectiveOn()
            ->where('config_key', $key)
            ->first();

        return $config?->config_value ?? $default;
    }

    /**
     * Set a configuration value.
     * Creates or updates configuration with the given key.
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value (will be JSON encoded)
     * @param string|null $description Optional description
     * @return self
     */
    public static function set(string $key, $value, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $value,
                'description' => $description,
                'effective_from' => now(),
                'is_active' => true,
                'updated_by' => auth()->id(),
            ]
        );
    }

    // ============================================================
    // Relationships
    // ============================================================

    /**
     * Get the user who created this configuration.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this configuration.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
