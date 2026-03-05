<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentContributionRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency',
        'rate_type',
        'bracket_code',
        'compensation_min',
        'compensation_max',
        'monthly_salary_credit',
        'employee_rate',
        'employer_rate',
        'total_rate',
        'employee_amount',
        'employer_amount',
        'ec_amount',
        'total_amount',
        'minimum_contribution',
        'maximum_contribution',
        'premium_ceiling',
        'contribution_ceiling',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'compensation_min' => 'decimal:2',
        'compensation_max' => 'decimal:2',
        'monthly_salary_credit' => 'decimal:2',
        'employee_rate' => 'decimal:2',
        'employer_rate' => 'decimal:2',
        'total_rate' => 'decimal:2',
        'employee_amount' => 'decimal:2',
        'employer_amount' => 'decimal:2',
        'ec_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'minimum_contribution' => 'decimal:2',
        'maximum_contribution' => 'decimal:2',
        'premium_ceiling' => 'decimal:2',
        'contribution_ceiling' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByAgency($query, string $agency)
    {
        return $query->where('agency', $agency);
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

    public function scopeSSS($query)
    {
        return $query->where('agency', 'sss');
    }

    public function scopePhilHealth($query)
    {
        return $query->where('agency', 'philhealth');
    }

    public function scopePagIbig($query)
    {
        return $query->where('agency', 'pagibig');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Find SSS bracket based on monthly compensation
     *
     * @param  \DateTimeInterface|string|null  $date  Effective date for the bracket lookup. Defaults to now().
     */
    public static function findSSSBracket(float $monthlyCompensation, \DateTimeInterface|string|null $date = null)
    {
        return self::active()
            ->sss()
            ->where('rate_type', 'bracket')
            ->effectiveOn($date)
            ->where('compensation_min', '<=', $monthlyCompensation)
            ->where(function ($q) use ($monthlyCompensation) {
                $q->whereNull('compensation_max')
                  ->orWhere('compensation_max', '>=', $monthlyCompensation);
            })
            ->orderBy('compensation_min', 'desc')
            ->first();
    }

    /**
     * Get PhilHealth premium rate
     *
     * @param  string|null  $date  Effective date (Y-m-d) to evaluate the rate for; if null, use latest effective rate.
     */
    public static function getPhilHealthRate(?string $date = null)
    {
        $query = self::active()
            ->philHealth()
            ->where('rate_type', 'premium_rate');

        if ($date !== null) {
            $query->where('effective_from', '<=', $date)
                  ->where(function ($q) use ($date) {
                      $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $date);
                  });
        } else {
            $query->orderBy('effective_from', 'desc');
        }

        return $query->first();
    }

    /**
     * Get Pag-IBIG rate based on salary
     *
     * @param  \DateTimeInterface|string|null  $date  Effective date for the rate lookup. Defaults to now().
     */
    public static function getPagIbigRate(float $salary, \DateTimeInterface|string|null $date = null)
    {
        $today = now()->toDateString();

        return self::active()
            ->pagIbig()
            ->where('rate_type', 'contribution_rate')
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            })
            ->where('compensation_min', '<=', $salary)
            ->where(function ($q) use ($salary) {
                $q->whereNull('compensation_max')
                  ->orWhere('compensation_max', '>=', $salary);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }
}
