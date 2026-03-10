<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salary_components';

    protected $fillable = [
        'name',
        'code',
        'component_type',
        'category',
        'calculation_method',
        'default_amount',
        'default_percentage',
        'reference_component_id',
        'ot_multiplier',
        'is_premium_pay',
        'is_taxable',
        'is_deminimis',
        'deminimis_limit_monthly',
        'deminimis_limit_annual',
        'is_13th_month',
        'is_other_benefits',
        'affects_sss',
        'affects_philhealth',
        'affects_pagibig',
        'affects_gross_compensation',
        'display_order',
        'is_displayed_on_payslip',
        'is_active',
        'is_system_component',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'default_percentage' => 'decimal:2',
        'ot_multiplier' => 'decimal:2',
        'deminimis_limit_monthly' => 'decimal:2',
        'deminimis_limit_annual' => 'decimal:2',
        'is_premium_pay' => 'boolean',
        'is_taxable' => 'boolean',
        'is_deminimis' => 'boolean',
        'is_13th_month' => 'boolean',
        'is_other_benefits' => 'boolean',
        'affects_sss' => 'boolean',
        'affects_philhealth' => 'boolean',
        'affects_pagibig' => 'boolean',
        'affects_gross_compensation' => 'boolean',
        'is_active' => 'boolean',
        'is_displayed_on_payslip' => 'boolean',
        'is_system_component' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    /**
     * Get the component this component references (for percentage calculations)
     */
    public function referenceComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'reference_component_id');
    }

    /**
     * Get all components that reference this component
     */
    public function referencedByComponents(): HasMany
    {
        return $this->hasMany(SalaryComponent::class, 'reference_component_id');
    }

    /**
     * Get all employee assignments for this component
     */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'salary_component_id');
    }

    /**
     * Get the user who created this component
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this component
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    /**
     * Get only active components
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get components by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('component_type', $type);
    }

    /**
     * Get components by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get system components (cannot be deleted)
     */
    public function scopeSystemComponents($query)
    {
        return $query->where('is_system_component', true);
    }

    /**
     * Get custom components (can be deleted)
     */
    public function scopeCustomComponents($query)
    {
        return $query->where('is_system_component', false);
    }

    /**
     * Get components displayed on payslip
     */
    public function scopeDisplayedOnPayslip($query)
    {
        return $query->where('is_displayed_on_payslip', true);
    }

    /**
     * Get components in display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc')->orderBy('name', 'asc');
    }

    // Accessors
    /**
     * Get formatted component label
     */
    public function getFormattedLabelAttribute(): string
    {
        $label = $this->name;

        if ($this->is_system_component) {
            $label .= ' (System)';
        }

        if (!$this->is_active) {
            $label .= ' (Inactive)';
        }

        return $label;
    }

    // Validation Methods
    /**
     * Validate calculation method based on required fields
     *
     * @return bool
     */
    public function isValidCalculationMethod(): bool
    {
        return match($this->calculation_method) {
            'fixed_amount' => !is_null($this->default_amount),
            'percentage_of_basic' => !is_null($this->default_percentage),
            'percentage_of_component' => !is_null($this->reference_component_id) && !is_null($this->default_percentage),
            'ot_multiplier' => !is_null($this->ot_multiplier),
            'lookup_table' => true, // No default needed, will be looked up during calculation
            default => false,
        };
    }

    // Boot method for auto-calculations
    protected static function boot()
    {
        parent::boot();

        // Prevent deletion of system components
        static::deleting(function ($component) {
            if ($component->is_system_component) {
                throw new \Exception("System components cannot be deleted. Code: {$component->code}");
            }
        });

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
