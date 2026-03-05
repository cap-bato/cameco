<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClearanceItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offboarding_case_id',
        'category',
        'item_name',
        'description',
        'priority',
        'status',
        'assigned_to',
        'approved_by',
        'approved_at',
        'has_issues',
        'issue_description',
        'resolution_notes',
        'proof_of_return_file_path',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'has_issues' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the offboarding case this item belongs to.
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * Get the user assigned to approve this item.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who approved this item.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get company assets related to this clearance item.
     */
    public function companyAssets(): HasMany
    {
        return $this->hasMany(CompanyAsset::class);
    }

    /**
     * Scope: Get pending clearances.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved clearances.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get high priority items.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['critical', 'high']);
    }

    /**
     * Scope: Get items with issues.
     */
    public function scopeWithIssues($query)
    {
        return $query->where('has_issues', true);
    }

    /**
     * Scope: Get items overdue.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['approved', 'waived']);
    }

    /**
     * Scope: Get items by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Approve this clearance item.
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ]);
    }

    /**
     * Waive this clearance item.
     */
    public function waive(User $user, string $reason): void
    {
        $this->update([
            'status' => 'waived',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'resolution_notes' => $reason,
        ]);
    }

    /**
     * Mark this item as having issues.
     */
    public function markAsHavingIssues(string $issueDescription): void
    {
        $this->update([
            'has_issues' => true,
            'issue_description' => $issueDescription,
            'status' => 'issues',
        ]);
    }

    /**
     * Resolve issues and mark as resolved.
     */
    public function resolveIssues(User $approver, string $resolutionNotes): void
    {
        $this->update([
            'has_issues' => false,
            'issue_description' => null,
            'resolution_notes' => $resolutionNotes,
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Check if this item is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isBefore(now()->toDateString())
            && !in_array($this->status, ['approved', 'waived']);
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'approved' => 'Approved',
            'waived' => 'Waived',
            'issues' => 'Has Issues',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the priority label for display.
     */
    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'critical' => 'Critical',
            'high' => 'High',
            'normal' => 'Normal',
            'low' => 'Low',
            default => ucfirst($this->priority),
        };
    }
}
