<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxBracket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tax_status',
        'status_description',
        'bracket_level',
        'income_from',
        'income_to',
        'base_tax',
        'tax_rate',
        'excess_over',
        'personal_exemption',
        'additional_exemption',
        'max_dependents',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'bracket_level' => 'integer',
        'income_from' => 'decimal:2',
        'income_to' => 'decimal:2',
        'base_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'excess_over' => 'decimal:2',
        'personal_exemption' => 'decimal:2',
        'additional_exemption' => 'decimal:2',
        'max_dependents' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStatus($query, string $taxStatus)
    {
        return $query->where('tax_status', $taxStatus);
    }

    public function scopeEffectiveOn($query, $date)
    {
        $date = $date ?? now();
        
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Find tax bracket for given income and tax status
     */
    public static function findBracket(float $annualizedIncome, string $taxStatus)
    {
        return self::active()
            ->byStatus($taxStatus)
            ->where('income_from', '<=', $annualizedIncome)
            ->where(function ($q) use ($annualizedIncome) {
                $q->whereNull('income_to')
                  ->orWhere('income_to', '>=', $annualizedIncome);
            })
            ->orderBy('bracket_level', 'desc')
            ->first();
    }

    /**
     * Get all brackets for a tax status
     */
    public static function getBracketsForStatus(string $taxStatus)
    {
        return self::active()
            ->byStatus($taxStatus)
            ->orderBy('bracket_level')
            ->get();
    }

    /**
     * Calculate tax for given income
     */
    public function calculateTax(float $annualizedIncome): float
    {
        if ($annualizedIncome <= $this->income_from) {
            return 0;
        }

        $excessIncome = $annualizedIncome - $this->excess_over;
        $taxOnExcess = ($excessIncome * ($this->tax_rate / 100));
        
        return (float) ($this->base_tax + $taxOnExcess);
    }
}
