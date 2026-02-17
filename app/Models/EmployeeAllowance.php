<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAllowance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_allowances';

    protected $fillable = [
        'employee_id',
        'allowance_type',
        'allowance_name',
        'amount',
        'frequency',
        'is_taxable',
        'is_deminimis',
        'deminimis_limit_monthly',
        'deminimis_limit_annual',
        'effective_date',
        'end_date',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deminimis_limit_monthly' => 'decimal:2',
        'deminimis_limit_annual' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_deminimis' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the employee this allowance belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who created this allowance record
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this allowance record
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only active allowances
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get allowances for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get allowances by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('allowance_type', $type);
    }

    /**
     * Get currently active allowances (effective_date <= today AND (end_date is null OR end_date >= today))
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
     * Get taxable allowances only
     */
    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    /**
     * Get de minimis (tax-exempt) allowances only
     */
    public function scopeDeminimis($query)
    {
        return $query->where('is_deminimis', true);
    }

    /**
     * Get allowances by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Get allowances ordered by effective date (newest first)
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
     * Get the tax treatment label
     */
    public function getTaxTreatmentAttribute(): string
    {
        if ($this->is_deminimis) {
            return 'De Minimis (Tax-Exempt)';
        }

        return $this->is_taxable ? 'Taxable' : 'Non-Taxable';
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
     * Check if this allowance is currently active (within date range)
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
     * Check if allowance is de minimis and within annual limit
     */
    public function isWithinDeminimisLimit(): bool
    {
        if (!$this->is_deminimis || !$this->deminimis_limit_annual) {
            return true;
        }

        // This would need to aggregate actual deductions from payroll
        // For now, return true - actual implementation would query payroll_calculations
        return true;
    }

    /**
     * Get days remaining for this allowance (if it has an end_date)
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
     * Get the allowance type label in human-readable format
     */
    public function getTypeDisplayAttribute(): string
    {
        return match($this->allowance_type) {
            'rice' => 'Rice Allowance',
            'cola' => 'Cost of Living Allowance (COLA)',
            'transportation' => 'Transportation Allowance',
            'meal' => 'Meal Allowance',
            'housing' => 'Housing Allowance',
            'communication' => 'Communication Allowance',
            'utilities' => 'Utilities Allowance',
            'laundry' => 'Laundry Allowance',
            'uniform' => 'Uniform Allowance',
            'medical' => 'Medical Allowance',
            'educational' => 'Educational Allowance',
            'special_project' => 'Special Project Allowance',
            default => ucfirst(str_replace('_', ' ', $this->allowance_type)),
        };
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set created_by if not already set
        static::creating(function ($allowance) {
            if (!$allowance->created_by && auth()->check()) {
                $allowance->created_by = auth()->id();
            }
        });

        // Set updated_by on updates
        static::updating(function ($allowance) {
            if (auth()->check()) {
                $allowance->updated_by = auth()->id();
            }
        });
    }
}
