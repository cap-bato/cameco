<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalaryComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_id',
        'salary_component_id',
        'amount',
        'percentage',
        'units',
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
        'percentage' => 'decimal:2',
        'units' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the employee this component assignment belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the salary component being assigned
     */
    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    /**
     * Get the user who created this assignment
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this assignment
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only active component assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get component assignments for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get component assignments for a specific component
     */
    public function scopeForComponent($query, int $componentId)
    {
        return $query->where('salary_component_id', $componentId);
    }

    /**
     * Get currently active assignments (effective_date <= today AND (end_date is null OR end_date >= today))
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
     * Get assignments by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Get assignments ordered by effective date (newest first)
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
     * Get the calculation value (amount or percentage with component name)
     */
    public function getCalculationValueAttribute(): string
    {
        if ($this->amount !== null) {
            return $this->formatted_amount;
        }

        if ($this->percentage !== null) {
            return $this->percentage . '% of ' . ($this->salaryComponent?->name ?? 'Component');
        }

        if ($this->units !== null) {
            return $this->units . ' units';
        }

        return 'Not configured';
    }

    /**
     * Check if this assignment is currently active (within date range)
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
     * Get days remaining for this assignment (if it has an end_date)
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $daysRemaining = now()->diffInDays($this->end_date, false);
        return max(0, $daysRemaining);
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        // Set created_by if not already set
        static::creating(function ($component) {
            if (!$component->created_by && auth()->check()) {
                $component->created_by = auth()->id();
            }
        });

        // Set updated_by on updates
        static::updating(function ($component) {
            if (auth()->check()) {
                $component->updated_by = auth()->id();
            }
        });
    }
}
