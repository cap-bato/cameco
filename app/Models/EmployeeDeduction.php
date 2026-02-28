<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDeduction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_deductions';

    protected $fillable = [
        'employee_id',
        'deduction_type',
        'deduction_name',
        'amount',
        'frequency',
        'effective_date',
        'end_date',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the employee this deduction belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who created this deduction record
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this deduction record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only active deductions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get deductions for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get deductions by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('deduction_type', $type);
    }

    /**
     * Get currently active deductions (effective_date <= today AND (end_date is null OR end_date >= today))
     */
    public function scopeCurrentlyActive($query)
    {
        $today = now()->toDateString();
        return $query->where('is_active', true)
                     ->where('effective_date', '<=', $today)
                     ->where(function ($q) use ($today) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', $today);
                     });
    }

    /**
     * Get deductions by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Get deductions ordered by effective date (newest first)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('effective_date', 'desc')->orderBy('created_at', 'desc');
    }

    // Accessors
    /**
     * Get formatted amount with currency symbol
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₱' . number_format($this->amount ?? 0, 2);
    }

    /**
     * Get the status label
     */
    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        $today = now()->toDateString();

        if ($this->effective_date > $today) {
            return 'Pending (Starts: ' . $this->effective_date->format('M d, Y') . ')';
        }

        if ($this->end_date && $this->end_date < $today) {
            return 'Ended (Ended: ' . $this->end_date->format('M d, Y') . ')';
        }

        return 'Active';
    }

    /**
     * Get the deduction type label in human-readable format
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->deduction_type) {
            'insurance' => 'Insurance Premium',
            'union_dues' => 'Union Dues',
            'canteen' => 'Canteen/Cafeteria',
            'utilities' => 'Utilities',
            'equipment' => 'Equipment Charges',
            'uniform' => 'Uniform Charges',
            'hmo' => 'HMO Premium',
            'professional_fee' => 'Professional Fees',
            'contribution' => 'Contributions',
            'tax_adjustment' => 'Tax Adjustment',
            'court_order' => 'Court Order',
            'loan_deduction' => 'Loan Deduction',
            'other' => 'Other Deduction',
            default => ucfirst(str_replace('_', ' ', $this->deduction_type)),
        };
    }

    // Methods
    /**
     * Check if this deduction is currently active (within date range)
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->effective_date > $today) {
            return false;
        }

        if ($this->end_date && $this->end_date < $today) {
            return false;
        }

        return true;
    }

    /**
     * Get days remaining for this deduction (if it has an end_date)
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $daysRemaining = now()->diffInDays($this->end_date, false);
        return max(0, $daysRemaining);
    }

    /**
     * Calculate total deduction for a given period (monthly, semi-monthly, etc.)
     */
    public function calculateDeductionForPeriod(string $periodType = 'monthly'): float
    {
        // If frequency matches period type, return full amount
        if ($this->frequency === $periodType || $this->frequency === 'per_payroll') {
            return floatval($this->amount);
        }

        // If one-time, return amount only once on effective_date
        if ($this->frequency === 'one_time') {
            return floatval($this->amount);
        }

        return 0.0;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set created_by if not already set
        static::creating(function ($deduction) {
            if (!$deduction->created_by && auth()->check()) {
                $deduction->created_by = auth()->id();
            }
        });

        // Set updated_by on updates
        static::updating(function ($deduction) {
            if (auth()->check()) {
                $deduction->updated_by = auth()->id();
            }
        });
    }
}
