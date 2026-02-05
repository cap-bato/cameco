<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Roles relationship
     */
    /**
     * Check if user has superadmin role.
     */
    public function isSuperadmin(): bool
    {
        // Use Spatie's hasRole helper for a consistent check (case-insensitive support can be added if needed)
        return $this->hasRole('Superadmin');
    }

    /**
     * One-to-one relation to Profile
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * One-to-one relation to Employee
     * 
     * Links user accounts to employee records for Employee Portal access.
     * Used by employees to view their personal data, submit leave requests, etc.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * One-to-many relation to SecurityAuditLog
     */
    public function auditLogs()
    {
        return $this->hasMany(SecurityAuditLog::class);
    }

    /**
     * Accessor for full_name which prefers profile values and falls back to users.name or username
     */
    public function getFullNameAttribute(): string
    {
        // Try profile first
        try {
            $p = $this->profile;
            if ($p) {
                $first = trim((string) ($p->first_name ?? ''));
                $last = trim((string) ($p->last_name ?? ''));
                $full = trim($first . ' ' . $last);
                if ($full !== '') {
                    return $full;
                }
            }
        } catch (\Throwable $e) {
            // If profile relation fails for any reason, fall back to users.name below
        }

        // Fallbacks
        if (!empty($this->name)) {
            return (string) $this->name;
        }

        return (string) ($this->username ?? '');
    }
}
