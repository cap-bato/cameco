<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'description',
        'requirements',
        'status',
        'posted_at',
        'closed_at',
        'created_by',
        // Facebook Integration Fields
        'facebook_post_id',
        'facebook_post_url',
        'facebook_posted_at',
        'auto_post_facebook',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'closed_at' => 'datetime',
        'facebook_posted_at' => 'datetime',
        'auto_post_facebook' => 'boolean',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    /**
     * Relationship: JobPosting belongs to a Department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship: JobPosting belongs to a User (creator)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: JobPosting has many Applications
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Relationship: JobPosting has many Facebook post logs
     */
    public function facebookPostLogs(): HasMany
    {
        return $this->hasMany(FacebookPostLog::class)->orderBy('created_at', 'desc');
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    /**
     * Check if job has been posted to Facebook
     */
    public function isPostedToFacebook(): bool
    {
        return !is_null($this->facebook_post_id);
    }

    /**
     * Get the latest successful Facebook post log
     */
    public function latestFacebookPost()
    {
        return $this->facebookPostLogs()
            ->where('status', 'posted')
            ->latest()
            ->first();
    }

    /**
     * Get the latest Facebook post log regardless of status
     */
    public function latestFacebookPostLog()
    {
        return $this->facebookPostLogs()->latest()->first();
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /**
     * Scope: Filter jobs with Facebook posts
     */
    public function scopePostedToFacebook($query)
    {
        return $query->whereNotNull('facebook_post_id');
    }

    /**
     * Scope: Filter jobs with auto-posting enabled
     */
    public function scopeAutoPostFacebook($query)
    {
        return $query->where('auto_post_facebook', true);
    }

    /**
     * Scope: Filter jobs pending Facebook posting
     */
    public function scopePendingFacebookPost($query)
    {
        return $query->where('auto_post_facebook', true)
            ->whereNull('facebook_post_id');
    }
}

