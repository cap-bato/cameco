<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class DocumentTemplate extends Model
{
    use HasFactory;
    /**
     * Template types
     */
    const TYPES = [
        'contract' => 'Employment Contract',
        'offer_letter' => 'Job Offer Letter',
        'coe' => 'Certificate of Employment',
        'memo' => 'Memorandum',
        'warning' => 'Warning Letter',
        'clearance' => 'Clearance Form',
        'resignation' => 'Resignation Acceptance',
        'termination' => 'Termination Letter',
        'other' => 'Other',
    ];

    /**
     * Template statuses
     */
    const STATUSES = [
        'draft' => 'Draft',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'name',
        'description',
        'template_type',
        'file_path',
        'variables',
        'created_by',
        'approved_by',
        'approved_at',
        'version',
        'is_locked',
        'is_active',
        'status',
    ];

    protected $casts = [
        'variables' => 'json',
        'approved_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'version' => 'integer',
    ];

    /**
     * Relationship: Created by User
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Approved by User
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relationship: Generated documents
     */
    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'document_type', 'template_type');
    }

    /**
     * Scope: Active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Approved templates
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->where('is_active', true);
    }

    /**
     * Scope: Pending approval
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope: By type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Scope: Locked templates
     */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    /**
     * Accessor: Type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->template_type] ?? $this->template_type;
    }

    /**
     * Accessor: Status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Method: Generate document with variable substitution
     *
     * @param array $variables Variables to substitute in template
     * @return string Processed document content
     */
    public function generateDocument(array $variables): string
    {
        // Read template file
        $templatePath = storage_path('app/document-templates/' . $this->file_path);
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: {$this->file_path}");
        }

        $content = file_get_contents($templatePath);

        // Substitute variables
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value ?? '', $content);
        }

        return $content;
    }

    /**
     * Method: Increment version
     *
     * @return int New version number
     */
    public function incrementVersion(): int
    {
        $newVersion = ($this->version ?? 0) + 1;
        $this->update(['version' => $newVersion]);

        return $newVersion;
    }

    /**
     * Method: Approve template
     *
     * @param User $user Approving user
     * @return bool Success status
     */
    public function approve(User $user): bool
    {
        return $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'is_locked' => true,
        ]);
    }

    /**
     * Method: Reject template
     *
     * @return bool Success status
     */
    public function reject(): bool
    {
        return $this->update([
            'status' => 'draft',
        ]);
    }

    /**
     * Method: Archive template
     *
     * @return bool Success status
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => 'archived',
            'is_active' => false,
        ]);
    }

    /**
     * Method: Restore template from archive
     *
     * @return bool Success status
     */
    public function restore(): bool
    {
        return $this->update([
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    /**
     * Method: Unlock template for editing
     *
     * @return bool Success status
     */
    public function unlock(): bool
    {
        return $this->update(['is_locked' => false]);
    }

    /**
     * Method: Lock template from editing
     *
     * @return bool Success status
     */
    public function lock(): bool
    {
        return $this->update(['is_locked' => true]);
    }
}
