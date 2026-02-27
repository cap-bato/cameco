<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'payroll_payment_id',
        'payslip_number',
        'period_start',
        'period_end',
        'payment_date',
        'employee_number',
        'employee_name',
        'department',
        'position',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin',
        'earnings_data',
        'total_earnings',
        'deductions_data',
        'total_deductions',
        'net_pay',
        'leave_summary',
        'attendance_summary',
        'ytd_gross',
        'ytd_tax',
        'ytd_sss',
        'ytd_philhealth',
        'ytd_pagibig',
        'ytd_net',
        'file_path',
        'file_format',
        'file_size',
        'file_hash',
        'distribution_method',
        'distributed_at',
        'is_viewed',
        'viewed_at',
        'signature_hash',
        'qr_code_data',
        'status',
        'notes',
        'generated_by',
    ];

    protected $casts = [
        'period_start'       => 'date',
        'period_end'         => 'date',
        'payment_date'       => 'date',
        'total_earnings'     => 'decimal:2',
        'total_deductions'   => 'decimal:2',
        'net_pay'            => 'decimal:2',
        'ytd_gross'          => 'decimal:2',
        'ytd_tax'            => 'decimal:2',
        'ytd_sss'            => 'decimal:2',
        'ytd_philhealth'     => 'decimal:2',
        'ytd_pagibig'        => 'decimal:2',
        'ytd_net'            => 'decimal:2',
        'file_size'          => 'integer',
        'earnings_data'      => 'array',
        'deductions_data'    => 'array',
        'leave_summary'      => 'array',
        'attendance_summary' => 'array',
        'is_viewed'          => 'boolean',
        'distributed_at'     => 'datetime',
        'viewed_at'          => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function payrollPayment(): BelongsTo
    {
        return $this->belongsTo(PayrollPayment::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeDistributed($query)
    {
        return $query->where('status', 'distributed');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeViewed($query)
    {
        return $query->where('is_viewed', true);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isGenerated(): bool
    {
        return $this->status === 'generated';
    }

    public function isDistributed(): bool
    {
        return in_array($this->status, ['distributed', 'acknowledged']);
    }

    public function isAcknowledged(): bool
    {
        return $this->status === 'acknowledged';
    }

    public function markAsViewed(): void
    {
        if (!$this->is_viewed) {
            $this->update([
                'is_viewed' => true,
                'viewed_at' => now(),
            ]);
        }
    }

    /**
     * Returns the structured earnings breakdown from the JSON column.
     */
    public function getEarningsBreakdown(): array
    {
        return $this->earnings_data ?? [];
    }

    /**
     * Returns the structured deductions breakdown from the JSON column.
     */
    public function getDeductionsBreakdown(): array
    {
        return $this->deductions_data ?? [];
    }

    /**
     * Build the QR code verification payload.
     * Encodes payslip_number + signature_hash for authenticity (Decision #15).
     */
    public function generateQrData(): string
    {
        return json_encode([
            'payslip_number' => $this->payslip_number,
            'signature_hash' => $this->signature_hash,
            'employee_number' => $this->employee_number,
            'payment_date'   => $this->payment_date?->toDateString(),
            'net_pay'        => $this->net_pay,
        ]);
    }

    /**
     * Returns year-to-date summary as an array.
     */
    public function getYtdSummary(): array
    {
        return [
            'gross'     => $this->ytd_gross,
            'tax'       => $this->ytd_tax,
            'sss'       => $this->ytd_sss,
            'philhealth'=> $this->ytd_philhealth,
            'pagibig'   => $this->ytd_pagibig,
            'net'       => $this->ytd_net,
        ];
    }
}
