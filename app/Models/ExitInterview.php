<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitInterview extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offboarding_case_id',
        'employee_id',
        'interview_date',
        'conducted_by',
        'interview_method',
        'reason_for_leaving',
        'overall_satisfaction',
        'work_environment_rating',
        'management_rating',
        'compensation_rating',
        'career_growth_rating',
        'work_life_balance_rating',
        'liked_most',
        'liked_least',
        'suggestions_for_improvement',
        'would_recommend_company',
        'would_consider_returning',
        'questions_responses',
        'sentiment_score',
        'key_themes',
        'status',
        'completed_at',
        'confidential',
        'shared_with_manager',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'interview_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'would_recommend_company' => 'boolean',
        'would_consider_returning' => 'boolean',
        'confidential' => 'boolean',
        'shared_with_manager' => 'boolean',
        'questions_responses' => 'array',
        'key_themes' => 'array',
        'sentiment_score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the offboarding case this interview belongs to.
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * Get the employee who is being interviewed.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the HR staff who conducted the interview.
     */
    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    /**
     * Scope: Get completed interviews.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get pending interviews.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get interviews with negative sentiment.
     */
    public function scopeNegativeSentiment($query)
    {
        return $query->where('sentiment_score', '<', 0.4);
    }

    /**
     * Scope: Get interviews with positive sentiment.
     */
    public function scopePositiveSentiment($query)
    {
        return $query->where('sentiment_score', '>=', 0.6);
    }

    /**
     * Scope: Get interviews within a date range.
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('interview_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Get interviews by satisfaction level.
     */
    public function scopeByOverallSatisfaction($query, int $minRating, int $maxRating = 5)
    {
        return $query->whereBetween('overall_satisfaction', [$minRating, $maxRating]);
    }

    /**
     * Calculate average rating across all rating fields.
     */
    public function getAverageRating(): float
    {
        $ratings = collect([
            $this->overall_satisfaction,
            $this->work_environment_rating,
            $this->management_rating,
            $this->compensation_rating,
            $this->career_growth_rating,
            $this->work_life_balance_rating,
        ])->filter(fn($r) => $r !== null);

        return $ratings->count() > 0 ? $ratings->average() : 0;
    }

    /**
     * Get all ratings as an associative array.
     */
    public function getAllRatings(): array
    {
        return [
            'overall_satisfaction' => $this->overall_satisfaction,
            'work_environment_rating' => $this->work_environment_rating,
            'management_rating' => $this->management_rating,
            'compensation_rating' => $this->compensation_rating,
            'career_growth_rating' => $this->career_growth_rating,
            'work_life_balance_rating' => $this->work_life_balance_rating,
        ];
    }

    /**
     * Mark the interview as completed.
     */
    public function markAsCompleted(User $conductedBy): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'conducted_by' => $conductedBy->id,
        ]);
    }

    /**
     * Check if all required questions are answered.
     */
    public function isComplete(): bool
    {
        return $this->status === 'completed'
            && !empty($this->reason_for_leaving)
            && !empty($this->liked_most)
            && !empty($this->liked_least)
            && $this->overall_satisfaction !== null;
    }

    /**
     * Get recommendation status as text.
     */
    public function getRecommendationStatus(): string
    {
        if ($this->would_recommend_company === null) {
            return 'Not provided';
        }

        return $this->would_recommend_company ? 'Would recommend' : 'Would not recommend';
    }

    /**
     * Get rehire consideration status as text.
     */
    public function getRehireConsiderationStatus(): string
    {
        if ($this->would_consider_returning === null) {
            return 'Not provided';
        }

        return $this->would_consider_returning ? 'Would consider returning' : 'Would not return';
    }

    /**
     * Get the sentiment classification.
     */
    public function getSentimentClassification(): string
    {
        if ($this->sentiment_score === null) {
            return 'Not analyzed';
        }

        if ($this->sentiment_score >= 0.6) {
            return 'Positive';
        }

        if ($this->sentiment_score >= 0.4) {
            return 'Neutral';
        }

        return 'Negative';
    }
}
