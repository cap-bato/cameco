<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * FacebookPostLog Model - Tracks all Facebook posting activity
 *
 * @property int $id
 * @property int $job_posting_id Reference to job posting
 * @property int $posted_by User who triggered the post
 * @property string|null $facebook_post_id Facebook post ID from API
 * @property string|null $facebook_post_url Direct URL to Facebook post
 * @property string $post_type How post was triggered (manual/auto)
 * @property string $status Post status (pending/posted/failed)
 * @property string|null $error_message Error message if posting failed
 * @property array|null $engagement_metrics Likes, shares, comments, reach
 * @property \Carbon\Carbon|null $metrics_updated_at Last metrics update
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read JobPosting $jobPosting
 * @property-read User $postedBy
 */
class FacebookPostLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'facebook_post_id',
        'facebook_post_url',
        'post_type',
        'status',
        'error_message',
        'engagement_metrics',
        'metrics_updated_at',
        'posted_by',
    ];

    protected $casts = [
        'engagement_metrics' => 'array',
        'metrics_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: FacebookPostLog belongs to a JobPosting
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Relationship: FacebookPostLog belongs to a User (who posted it)
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // ==================== Helper Methods ====================

    /**
     * Check if post was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Alias for isSuccessful() for backwards compatibility
     */
    public function isPosted(): bool
    {
        return $this->isSuccessful();
    }

    /**
     * Check if post failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if post is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if post was auto-posted
     */
    public function isAutoPosted(): bool
    {
        return $this->post_type === 'auto';
    }

    /**
     * Check if post was manually posted
     */
    public function isManualPosted(): bool
    {
        return $this->post_type === 'manual';
    }

    /**
     * Get total engagement count (likes + shares + comments)
     */
    public function getEngagementCount(): int
    {
        if (!$this->engagement_metrics) {
            return 0;
        }

        return ($this->engagement_metrics['likes'] ?? 0) +
               ($this->engagement_metrics['shares'] ?? 0) +
               ($this->engagement_metrics['comments'] ?? 0);
    }

    /**
     * Get engagement metric by type
     */
    public function getEngagement(string $type, int $default = 0): int
    {
        return $this->engagement_metrics[$type] ?? $default;
    }

    // ==================== Query Scopes ====================

    /**
     * Scope: Filter posts with 'posted' status
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope: Filter posts with 'failed' status
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Filter posts with 'pending' status
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Filter posts by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter auto-posted entries
     */
    public function scopeAuto(Builder $query): Builder
    {
        return $query->where('post_type', 'auto');
    }

    /**
     * Scope: Filter manually-posted entries
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('post_type', 'manual');
    }

    /**
     * Scope: Filter posts with engagement metrics
     */
    public function scopeWithEngagement(Builder $query): Builder
    {
        return $query->whereNotNull('engagement_metrics');
    }

    /**
     * Scope: Filter posts posted by specific user
     */
    public function scopePostedBy(Builder $query, int $userId): Builder
    {
        return $query->where('posted_by', $userId);
    }

    /**
     * Scope: Filter posts by job posting ID
     */
    public function scopeForJobPosting(Builder $query, int $jobPostingId): Builder
    {
        return $query->where('job_posting_id', $jobPostingId);
    }

    /**
     * Scope: Get recent posts (newest first)
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
