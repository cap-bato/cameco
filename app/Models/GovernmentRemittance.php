<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?\Carbon\Carbon $period_start
 * @property ?\Carbon\Carbon $period_end
 * @property ?\Carbon\Carbon $due_date
 * @property ?\Carbon\Carbon $submission_date
 * @property ?\Carbon\Carbon $payment_date
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 */
class GovernmentRemittance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_period_id',
        // Agency
        'agency',
        'remittance_type',
        // Period
        'remittance_month',
        'period_start',
        'period_end',
        // Amounts
        'employee_share',
        'employer_share',
        'ec_share',
        'total_amount',
        // Employee counts
        'total_employees',
        'active_employees',
        'exempted_employees',
        // Deadlines
        'due_date',
        'submission_date',
        'payment_date',
        // Payment
        'payment_reference',
        'payment_method',
        'bank_name',
        'amount_paid',
        // Penalties
        'has_penalty',
        'penalty_amount',
        'penalty_reason',
        // Status
        'status',
        'is_late',
        'days_overdue',
        'notes',
        // Audit
        'prepared_by',
        'submitted_by',
        'paid_by',
    ];

    protected $casts = [
        'period_start'   => 'date',
        'period_end'     => 'date',
        'due_date'       => 'date',
        'submission_date' => 'date',
        'payment_date'   => 'date',
        // Decimals
        'employee_share'  => 'decimal:2',
        'employer_share'  => 'decimal:2',
        'ec_share'        => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'penalty_amount'  => 'decimal:2',
        // Booleans
        'has_penalty' => 'boolean',
        'is_late'     => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(GovernmentReport::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    public function scopeByAgency($query, string $agency)
    {
        return $query->where('agency', $agency);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'ready']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
                     ->orWhere(function ($q) {
                         $q->where('is_late', true)
                           ->whereNotIn('status', ['paid', 'submitted']);
                     });
    }
}
