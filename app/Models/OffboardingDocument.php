<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingDocument extends Model
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
        'document_type',
        'document_name',
        'file_path',
        'generated_by_system',
        'uploaded_by',
        'status',
        'approved_by',
        'approved_at',
        'issued_to_employee',
        'issued_at',
        'file_size',
        'mime_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'generated_by_system' => 'boolean',
        'issued_to_employee' => 'boolean',
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the offboarding case this document belongs to.
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
    }

    /**
     * Get the employee this document is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who approved the document.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope: Get draft documents.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Get pending approval documents.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope: Get approved documents.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get issued documents.
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope: Get documents by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope: Get system-generated documents.
     */
    public function scopeSystemGenerated($query)
    {
        return $query->where('generated_by_system', true);
    }

    /**
     * Scope: Get user-uploaded documents.
     */
    public function scopeUserUploaded($query)
    {
        return $query->where('generated_by_system', false);
    }

    /**
     * Approve the document.
     */
    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Issue the document to employee.
     */
    public function issueToEmployee(): void
    {
        $this->update([
            'status' => 'issued',
            'issued_to_employee' => true,
            'issued_at' => now(),
        ]);
    }

    /**
     * Check if document requires approval.
     */
    public function requiresApproval(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    /**
     * Check if document has been issued to employee.
     */
    public function isIssuedToEmployee(): bool
    {
        return $this->issued_to_employee === true;
    }

    /**
     * Get the document type label.
     */
    public function getDocumentTypeLabel(): string
    {
        return match($this->document_type) {
            'clearance_certificate' => 'Clearance Certificate',
            'certificate_of_employment' => 'Certificate of Employment',
            'final_pay_computation' => 'Final Pay Computation',
            'bir_form_2316' => 'BIR Form 2316',
            'resignation_letter' => 'Resignation Letter',
            'termination_letter' => 'Termination Letter',
            'exit_interview' => 'Exit Interview',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'issued' => 'Issued',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Get the file size formatted as human-readable string.
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $i < count($units) && $bytes >= 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the file extension from the mime type.
     */
    public function getFileExtension(): string
    {
        if (!$this->mime_type) {
            return 'unknown';
        }

        $extensions = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'text/plain' => 'txt',
        ];

        return $extensions[$this->mime_type] ?? 'unknown';
    }
}
