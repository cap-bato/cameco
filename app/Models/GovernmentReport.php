<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?\Carbon\Carbon $submitted_at
 * @property ?\Carbon\Carbon $validated_at
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 */
class GovernmentReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        'government_remittance_id',
        // Report Information
        'agency',
        'report_type',
        'report_name',
        'report_period',
        // File Information
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        // Summary
        'total_employees',
        'total_compensation',
        'total_employee_share',
        'total_employer_share',
        'total_amount',
        // BIR-specific
        'rdo_code',
        'total_tax_withheld',
        // Submission
        'status',
        'submitted_at',
        'submission_reference',
        'rejection_reason',
        // Validation
        'is_validated',
        'validated_at',
        'validation_errors',
        // Notes / Audit
        'notes',
        'generated_by',
        'submitted_by',
    ];

    protected $casts = [
        'submitted_at'  => 'datetime',
        'validated_at'  => 'datetime',
        // Decimals
        'total_compensation'   => 'decimal:2',
        'total_employee_share' => 'decimal:2',
        'total_employer_share' => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'total_tax_withheld'   => 'decimal:2',
        // Booleans
        'is_validated' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function governmentRemittance(): BelongsTo
    {
        return $this->belongsTo(GovernmentRemittance::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    public function scopeByAgency($query, string $agency)
    {
        return $query->where('agency', $agency);
    }

    public function scopeByReportType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
}
