<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePayrollInfo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_payroll_info';

    protected $fillable = [
        'employee_id',
        'salary_type',
        'basic_salary',
        'daily_rate',
        'hourly_rate',
        'payment_method',
        'tax_status',
        'rdo_code',
        'withholding_tax_exemption',
        'is_tax_exempt',
        'is_substituted_filing',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'sss_bracket',
        'is_sss_voluntary',
        'philhealth_is_indigent',
        'pagibig_employee_rate',
        'bank_name',
        'bank_code',
        'bank_account_number',
        'bank_account_name',
        'is_entitled_to_rice',
        'is_entitled_to_uniform',
        'is_entitled_to_laundry',
        'is_entitled_to_medical',
        'effective_date',
        'end_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'withholding_tax_exemption' => 'decimal:2',
        'pagibig_employee_rate' => 'decimal:2',
        'is_tax_exempt' => 'boolean',
        'is_substituted_filing' => 'boolean',
        'is_sss_voluntary' => 'boolean',
        'philhealth_is_indigent' => 'boolean',
        'is_entitled_to_rice' => 'boolean',
        'is_entitled_to_uniform' => 'boolean',
        'is_entitled_to_laundry' => 'boolean',
        'is_entitled_to_medical' => 'boolean',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class, 'employee_id', 'employee_id');
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class, 'employee_id', 'employee_id');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class, 'employee_id', 'employee_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class, 'employee_id', 'employee_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeCurrentActive($query)
    {
        return $query->where('is_active', true)
                     ->whereNull('end_date');
    }

    // Accessors
    public function getFormattedBasicSalaryAttribute(): string
    {
        return '₱' . number_format($this->basic_salary ?? 0, 2);
    }

    public function getFormattedDailyRateAttribute(): string
    {
        return '₱' . number_format($this->daily_rate ?? 0, 2);
    }

    public function getFormattedHourlyRateAttribute(): string
    {
        return '₱' . number_format($this->hourly_rate ?? 0, 2);
    }

    // Validation Methods
    /**
     * Validate government numbers format
     *
     * @param string $type Type of government number: 'sss', 'philhealth', 'pagibig', 'tin'
     * @param string|null $number The government number to validate
     * @return bool
     */
    public static function validateGovernmentNumber(string $type, ?string $number): bool
    {
        if (!$number) {
            return true; // Nullable fields are valid
        }

        return match($type) {
            'sss' => preg_match('/^\d{2}-\d{7}-\d{1}$/', $number) === 1,
            'philhealth' => preg_match('/^\d{12}$/', $number) === 1,
            'pagibig' => preg_match('/^\d{4}-\d{4}-\d{4}$/', $number) === 1,
            'tin' => preg_match('/^\d{3}-\d{3}-\d{3}-\d{3}$/', $number) === 1,
            default => false,
        };
    }

    /**
     * Calculate SSS bracket based on monthly salary
     * Uses standard SSS brackets as of 2024
     *
     * @param float $salary Monthly basic salary
     * @return string SSS bracket (E1, E2, E3, E4)
     */
    public static function calculateSSSBracket(float $salary): string
    {
        if ($salary < 4250) {
            return 'E1';
        } elseif ($salary < 8750) {
            return 'E2';
        } elseif ($salary < 13750) {
            return 'E3';
        } else {
            return 'E4';
        }
    }

    // Boot method for auto-calculations
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate daily_rate from monthly salary
        static::saving(function ($payrollInfo) {
            if ($payrollInfo->salary_type === 'monthly' && $payrollInfo->basic_salary && !$payrollInfo->daily_rate) {
                $payrollInfo->daily_rate = round($payrollInfo->basic_salary / 22, 2); // 22 working days
            }

            // Auto-calculate hourly_rate from daily_rate
            if ($payrollInfo->daily_rate && !$payrollInfo->hourly_rate) {
                $payrollInfo->hourly_rate = round($payrollInfo->daily_rate / 8, 2); // 8 hours per day
            }

            // Auto-detect SSS bracket based on basic_salary
            if ($payrollInfo->basic_salary && !$payrollInfo->sss_bracket) {
                $payrollInfo->sss_bracket = self::calculateSSSBracket($payrollInfo->basic_salary);
            }
        });
    }
}
