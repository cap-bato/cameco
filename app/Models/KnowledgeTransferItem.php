<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeTransferItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offboarding_case_id',
        'item_type',
        'title',
        'description',
        'transferred_to',
        'status',
        'priority',
        'documentation_location',
        'handover_notes',
        'completed_by',
        'completed_at',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
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
     * Get the employee receiving the knowledge transfer.
     */
    public function transferredTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'transferred_to');
    }

    /**
     * Get the user who completed the transfer.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Scope: Get pending transfers.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get in-progress transfers.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope: Get completed transfers.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get high priority items.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['critical', 'high']);
    }

    /**
     * Scope: Get items overdue.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope: Get items by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Start the knowledge transfer.
     */
    public function start(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    /**
     * Complete the knowledge transfer.
     */
    public function complete(User $completedBy, ?string $handoverNotes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by' => $completedBy->id,
            'completed_at' => now(),
            'handover_notes' => $handoverNotes ?? $this->handover_notes,
        ]);
    }

    /**
     * Mark as not applicable.
     */
    public function markAsNotApplicable(): void
    {
        $this->update(['status' => 'not_applicable']);
    }

    /**
     * Check if this item is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isBefore(now()->toDateString())
            && !in_array($this->status, ['completed', 'not_applicable']);
    }

    /**
     * Get the item type label.
     */
    public function getItemTypeLabel(): string
    {
        return match($this->item_type) {
            'project' => 'Project',
            'client' => 'Client',
            'process' => 'Process',
            'documentation' => 'Documentation',
            'credentials' => 'Credentials',
            'contacts' => 'Contacts',
            'other' => 'Other',
            default => ucfirst($this->item_type),
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'not_applicable' => 'Not Applicable',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the priority label.
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
