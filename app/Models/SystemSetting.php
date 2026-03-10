<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SystemSetting extends Model
{
    use LogsActivity;

    protected $table = 'system_settings';
    
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'category',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Configure activity logging options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value', 'type', 'category'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "System setting {$eventName}");
    }

    /**
     * Get a system setting value by key with type casting
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        // Type casting based on setting type
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'float', 'decimal' => (float) $setting->value,
            'json' => json_decode($setting->value, true),
            'array' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }
}
