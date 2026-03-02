# Payroll Advances Feature - Complete Implementation Plan

**Feature:** Cash Advances & Salary Advances Management  
**Status:** Planning → Ready for Implementation  
**Priority:** HIGH  
**Created:** February 6, 2026  
**Estimated Duration:** 3 weeks  
**Target Completion:** February 27, 2026

---

## 📚 Reference Documentation

This implementation plan is based on the following specifications and documentation:

### Core Specifications
- **[PAYROLL_MODULE_ARCHITECTURE.md](../docs/PAYROLL_MODULE_ARCHITECTURE.md)** - Complete payroll module architecture, advance deduction formulas, and integration points
- **[payroll-processing.md](../docs/workflows/processes/payroll-processing.md)** - Payroll processing workflow including advance deductions timing
- **[05-payroll-officer-workflow.md](../docs/workflows/05-payroll-officer-workflow.md)** - Advance eligibility rules, approval workflow, and deduction schedules

### Integration Requirements
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Integration with timekeeping for attendance-based salary calculations
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Integration with leave management for unpaid leave impact on advance deductions
- **[cash-salary-distribution.md](../docs/workflows/processes/cash-salary-distribution.md)** - Cash payment workflow and advance deduction tracking

### Existing Frontend Implementation
- **[resources/js/pages/Payroll/Advances/Index.tsx](../resources/js/pages/Payroll/Advances/Index.tsx)** - Complete frontend implementation with mock data
- **[app/Http/Controllers/Payroll/AdvancesController.php](../app/Http/Controllers/Payroll/AdvancesController.php)** - Controller with mock data (needs real implementation)

---

## 🎯 Feature Overview

### What are Cash Advances?

**Cash Advances** are short-term salary advances given to employees before their regular payday to address immediate financial needs (emergencies, medical expenses, etc.). The advance amount is deducted from the employee's future salary through single or multiple installments.

### Key Business Rules

1. **Eligibility:** Employees must be regular/permanent (configurable by company policy)
2. **Amount Limit:** Maximum advance = 50% of monthly basic salary (configurable)
3. **Active Limit:** Maximum 1-2 active advances per employee at a time
4. **Deduction:** Deducted automatically during payroll calculation
5. **Approval:** Requires HR Manager or Office Admin approval
6. **Types:** Cash Advance, Medical Advance, Travel Advance, Equipment Advance

### Integration Points

```
┌──────────────────────────────────────────────────────────────┐
│                    Cash Advances Flow                         │
└──────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌──────────────────────────────────────────────────────────────┐
│  1. Employee Request → Payroll Officer/HR Manager Approval    │
│  2. Approved Advance → Schedule Deductions                    │
│  3. Payroll Calculation → Deduct from Salary                  │
│  4. Update Advance Balance → Track Installments               │
│  5. Complete Deduction → Close Advance                        │
└──────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌──────────────────────────────────────────────────────────────┐
│              Integration with Payroll Calculation             │
│                                                               │
│  EmployeePayrollCalculation                                   │
│    ├─ Fetch active advances                                  │
│    ├─ Get pending deductions for period                      │
│    ├─ Calculate deduction amount                             │
│    ├─ Deduct from net pay                                    │
│    ├─ Update advance balance                                 │
│    └─ Mark deduction as complete                             │
│                                                               │
│  Timekeeping Integration:                                     │
│    └─ If unpaid leave days exist → Reduce deduction or defer │
│                                                               │
│  Leave Integration:                                           │
│    └─ Handle unpaid leave impact on advance eligibility      │
└──────────────────────────────────────────────────────────────┘
```

---

## 🤔 Clarifications & Recommendations

### 📋 Questions for Confirmation

**Q1: Eligibility Rules**
- **Q1.1:** Employment tenure requirement?  
  **Recommendation:** ✅ **Minimum 3 months employed** (as per workflow doc)
  
- **Q1.2:** Maximum number of active advances per employee?  
  **Recommendation:** ✅ **1 active advance at a time** (can request new one after full repayment)
  
- **Q1.3:** Maximum amount as percentage of monthly salary?  
  **Recommendation:** ✅ **50% of monthly basic salary** (as per workflow doc)
  
- **Q1.4:** Can probationary employees request advances?  
  **Recommendation:** ❌ **No** - Only regular/permanent employees

**Q2: Approval Workflow**
- **Q2.1:** Who can approve advances?  
  **Recommendation:**  
  - ✅ **HR Manager**: Full approval authority  
  - ✅ **Office Admin**: Full approval authority  
  - ✅ **Payroll Officer**: Can process but requires approval from above  
  
- **Q2.2:** Should approval amounts be tiered by amount?  
  **Recommendation:**  
  - ≤ ₱20,000: HR Manager approval
  - > ₱20,000: Office Admin approval required
  
- **Q2.3:** Can employees request advances while on leave (unpaid)?  
  **Recommendation:** ❌ **No** - Employee must be actively working

**Q3: Deduction Schedule**
- **Q3.1:** Maximum number of installments?  
  **Recommendation:** ✅ **Maximum 6 installments** (6 months max repayment)
  
- **Q3.2:** What happens if employee resigns before full repayment?  
  **Recommendation:** ✅ **Deduct full balance from final pay** (stated in advance agreement)
  
- **Q3.3:** Can employees make early repayments?  
  **Recommendation:** ✅ **Yes** - Allow manual repayment from payroll officer

**Q4: Integration with Payroll**
- **Q4.1:** When are deductions applied?  
  **Recommendation:** ✅ **During payroll calculation phase** (before final approval)
  
- **Q4.2:** What if net pay is insufficient to cover deduction?  
  **Recommendation:** ✅ **Deduct maximum possible, carry forward remaining balance** to next period
  
- **Q4.3:** Impact of unpaid leave on deduction?  
  **Recommendation:** ✅ **Skip deduction for that period if unpaid leave exists** (reschedule remaining installments)

**Q5: Reporting & Compliance**
- **Q5.1:** Should advances appear on payslips?  
  **Recommendation:** ✅ **Yes** - Show as "Cash Advance Deduction" line item
  
- **Q5.2:** Track advances for tax purposes?  
  **Recommendation:** ✅ **Yes** - Advances are salary advances, not loans, so they're part of taxable income calculation
  
- **Q5.3:** Generate advance reports?  
  **Recommendation:** ✅ **Yes** - Monthly advance report showing active advances, deductions, balances

### 💡 Recommendations

1. **Advance Agreement Form**: Create digital advance agreement with employee signature (future e-signature)
2. **Automatic Reminders**: Send reminders to employees X days before deduction
3. **Advance Limit Tracking**: Track annual advance limits (e.g., max 2 advances per year)
4. **Emergency Override**: Allow Office Admin to override eligibility rules for emergencies
5. **Audit Trail**: Log all advance request/approval/rejection/deduction actions
6. **Dashboard Widget**: Show "Pending Advances" count on Payroll Officer dashboard

---

## 🗄️ Database Schema Design

### Required Tables

#### 1. employee_cash_advances

**Purpose:** Store all cash advance requests and their status

```sql
CREATE TABLE employee_cash_advances (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    advance_number VARCHAR(20) UNIQUE NOT NULL,  -- ADV-2026-0001
    
    -- Employee Info
    employee_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED,
    
    -- Advance Details
    advance_type ENUM('cash_advance', 'medical_advance', 'travel_advance', 'equipment_advance') DEFAULT 'cash_advance',
    amount_requested DECIMAL(10,2) NOT NULL,
    amount_approved DECIMAL(10,2),
    purpose TEXT NOT NULL,
    priority_level ENUM('normal', 'urgent') DEFAULT 'normal',
    supporting_documents JSON,  -- Array of file paths
    requested_date DATE NOT NULL,
    
    -- Approval Workflow
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED,  -- User who approved
    approved_at TIMESTAMP,
    approval_notes TEXT,
    rejection_reason TEXT,
    
    -- Deduction Schedule
    deduction_status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    deduction_schedule ENUM('single_period', 'installments') DEFAULT 'installments',
    number_of_installments INT DEFAULT 1,
    installments_completed INT DEFAULT 0,
    deduction_amount_per_period DECIMAL(10,2),  -- Calculated: amount_approved / number_of_installments
    
    -- Balance Tracking
    total_deducted DECIMAL(10,2) DEFAULT 0,
    remaining_balance DECIMAL(10,2),  -- amount_approved - total_deducted
    
    -- Completion
    completed_at TIMESTAMP,
    completion_reason ENUM('fully_paid', 'employee_resignation', 'cancelled', 'written_off'),
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_employee (employee_id),
    INDEX idx_approval_status (approval_status),
    INDEX idx_deduction_status (deduction_status),
    INDEX idx_requested_date (requested_date),
    INDEX idx_advance_number (advance_number)
);
```

#### 2. advance_deductions

**Purpose:** Track individual deductions per payroll period

```sql
CREATE TABLE advance_deductions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Relationships
    cash_advance_id BIGINT UNSIGNED NOT NULL,
    payroll_period_id BIGINT UNSIGNED NOT NULL,
    employee_payroll_calculation_id BIGINT UNSIGNED,  -- Link to payroll calculation
    
    -- Deduction Details
    installment_number INT NOT NULL,  -- 1, 2, 3, etc.
    deduction_amount DECIMAL(10,2) NOT NULL,
    remaining_balance_after DECIMAL(10,2) NOT NULL,
    
    -- Status
    is_deducted BOOLEAN DEFAULT FALSE,
    deducted_at TIMESTAMP,
    deduction_notes TEXT,  -- e.g., "Partial deduction due to insufficient net pay"
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (cash_advance_id) REFERENCES employee_cash_advances(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE RESTRICT,
    FOREIGN KEY (employee_payroll_calculation_id) REFERENCES employee_payroll_calculations(id) ON DELETE SET NULL,
    
    INDEX idx_cash_advance (cash_advance_id),
    INDEX idx_payroll_period (payroll_period_id),
    INDEX idx_is_deducted (is_deducted),
    UNIQUE KEY unique_advance_period (cash_advance_id, payroll_period_id)
);
```

### Migration File Structure

- `2026_02_06_000001_create_employee_cash_advances_table.php`
- `2026_02_06_000002_create_advance_deductions_table.php`

---

## 🚀 Implementation Phases

### **Phase 1: Database Foundation (Week 1: Feb 6-12)**

#### Task 1.1: Create Database Migrations

**Subtask 1.1.1: Create employee_cash_advances migration**
- **File:** `database/migrations/2026_02_06_000001_create_employee_cash_advances_table.php`
- **Action:** CREATE
- **Schema:** Full table structure with all columns, indexes, and foreign keys
- **Validation:** Run `php artisan migrate` and verify table created in database

**Subtask 1.1.2: Create advance_deductions migration**
- **File:** `database/migrations/2026_02_06_000002_create_advance_deductions_table.php`
- **Action:** CREATE
- **Schema:** Full table structure with relationships to advances and payroll periods
- **Validation:** Run `php artisan migrate` and verify table created with constraints

---

#### Task 1.2: Create Eloquent Models

**Subtask 1.2.1: Create CashAdvance model**
- **File:** `app/Models/CashAdvance.php`
- **Action:** CREATE
- **Relationships:** belongsTo(Employee), belongsTo(User approvedBy), hasMany(AdvanceDeduction)
- **Scopes:** active(), pending(), completed(), byEmployee()
- **Accessors:** formatted_amount_requested, formatted_remaining_balance
- **Mutators:** Auto-calculate remaining_balance, deduction_amount_per_period

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_cash_advances';

    protected $fillable = [
        'advance_number',
        'employee_id',
        'department_id',
        'advance_type',
        'amount_requested',
        'amount_approved',
        'purpose',
        'priority_level',
        'supporting_documents',
        'requested_date',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'deduction_status',
        'deduction_schedule',
        'number_of_installments',
        'installments_completed',
        'deduction_amount_per_period',
        'total_deducted',
        'remaining_balance',
        'completed_at',
        'completion_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'amount_approved' => 'decimal:2',
        'deduction_amount_per_period' => 'decimal:2',
        'total_deducted' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'requested_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'supporting_documents' => 'array',
        'number_of_installments' => 'integer',
        'installments_completed' => 'integer',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advanceDeductions(): HasMany
    {
        return $this->hasMany(AdvanceDeduction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('deduction_status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // Accessors
    public function getFormattedAmountRequestedAttribute(): string
    {
        return '₱' . number_format($this->amount_requested, 2);
    }

    public function getFormattedRemainingBalanceAttribute(): string
    {
        return '₱' . number_format($this->remaining_balance, 2);
    }

    // Mutators
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($advance) {
            if ($advance->amount_approved && $advance->number_of_installments) {
                $advance->deduction_amount_per_period = $advance->amount_approved / $advance->number_of_installments;
            }

            if ($advance->amount_approved) {
                $advance->remaining_balance = $advance->amount_approved - ($advance->total_deducted ?? 0);
            }
        });
    }
}
```

**Subtask 1.2.2: Create AdvanceDeduction model**
- **File:** `app/Models/AdvanceDeduction.php`
- **Action:** CREATE
- **Relationships:** belongsTo(CashAdvance), belongsTo(PayrollPeriod), belongsTo(EmployeePayrollCalculation)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceDeduction extends Model
{
    use HasFactory;

    protected $table = 'advance_deductions';

    protected $fillable = [
        'cash_advance_id',
        'payroll_period_id',
        'employee_payroll_calculation_id',
        'installment_number',
        'deduction_amount',
        'remaining_balance_after',
        'is_deducted',
        'deducted_at',
        'deduction_notes',
    ];

    protected $casts = [
        'deduction_amount' => 'decimal:2',
        'remaining_balance_after' => 'decimal:2',
        'is_deducted' => 'boolean',
        'deducted_at' => 'datetime',
        'installment_number' => 'integer',
    ];

    // Relationships
    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employeePayrollCalculation(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollCalculation::class);
    }

    // Scopes
    public function scopeDeducted($query)
    {
        return $query->where('is_deducted', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_deducted', false);
    }
}
```

**Subtask 1.2.3: Update Employee model**
- **File:** `app/Models/Employee.php`
- **Action:** MODIFY
- **Change:** Add hasMany(CashAdvance) relationship

```php
// Add to Employee model
public function cashAdvances(): HasMany
{
    return $this->hasMany(CashAdvance::class);
}

public function activeCashAdvances(): HasMany
{
    return $this->cashAdvances()->where('deduction_status', 'active');
}
```

---

### **Phase 2: Core Services & Business Logic (Week 2: Feb 13-19)**

#### Task 2.1: Create AdvanceManagementService

**File:** `app/Services/Payroll/AdvanceManagementService.php`
- **Action:** CREATE
- **Responsibility:** Core business logic for advance management
- **Methods:**
  - `createAdvanceRequest()` - Create new advance request
  - `approveAdvance()` - Approve advance with schedule
  - `rejectAdvance()` - Reject advance with reason
  - `cancelAdvance()` - Cancel advance (before or after approval)
  - `checkEmployeeEligibility()` - Validate if employee can request advance
  - `calculateMaxAdvanceAmount()` - Calculate max eligible amount
  - `generateAdvanceNumber()` - Auto-generate unique advance number
  - `scheduleDeductions()` - Create advance_deductions records

```php
<?php

namespace App\Services\Payroll;

use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\Employee;
use App\Models\User;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceManagementService
{
    /**
     * Create a new advance request
     */
    public function createAdvanceRequest(array $data, User $requestor): CashAdvance
    {
        // Validate eligibility
        $employee = Employee::findOrFail($data['employee_id']);
        $eligibility = $this->checkEmployeeEligibility($employee);
        
        if (!$eligibility['eligible']) {
            throw new \Exception($eligibility['reason']);
        }

        // Validate amount
        $maxAmount = $this->calculateMaxAdvanceAmount($employee);
        if ($data['amount_requested'] > $maxAmount) {
            throw new \Exception("Requested amount exceeds maximum allowed advance of ₱" . number_format($maxAmount, 2));
        }

        // Generate advance number
        $advanceNumber = $this->generateAdvanceNumber();

        $advance = CashAdvance::create([
            'advance_number' => $advanceNumber,
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'advance_type' => $data['advance_type'],
            'amount_requested' => $data['amount_requested'],
            'purpose' => $data['purpose'],
            'requested_date' => $data['requested_date'] ?? now()->toDateString(),
            'priority_level' => $data['priority_level'] ?? 'normal',
            'supporting_documents' => $data['supporting_documents'] ?? [],
            'approval_status' => 'pending',
            'deduction_status' => 'pending',
            'created_by' => $requestor->id,
        ]);

        Log::info("Cash advance request created", [
            'advance_number' => $advanceNumber,
            'employee_id' => $employee->id,
            'amount' => $data['amount_requested'],
        ]);

        return $advance;
    }

    /**
     * Approve cash advance and schedule deductions
     */
    public function approveAdvance(CashAdvance $advance, array $approvalData, User $approver): CashAdvance
    {
        DB::beginTransaction();
        try {
            // Update advance with approval details
            $advance->update([
                'approval_status' => 'approved',
                'amount_approved' => $approvalData['amount_approved'],
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $approvalData['approval_notes'] ?? null,
                'deduction_status' => 'active',
                'deduction_schedule' => $approvalData['deduction_schedule'],
                'number_of_installments' => $approvalData['number_of_installments'],
                'deduction_amount_per_period' => $approvalData['amount_approved'] / $approvalData['number_of_installments'],
                'remaining_balance' => $approvalData['amount_approved'],
                'updated_by' => $approver->id,
            ]);

            // Schedule deductions for future payroll periods
            $this->scheduleDeductions($advance);

            DB::commit();

            Log::info("Cash advance approved", [
                'advance_number' => $advance->advance_number,
                'approved_amount' => $approvalData['amount_approved'],
                'approver' => $approver->name,
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to approve cash advance", [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject cash advance
     */
    public function rejectAdvance(CashAdvance $advance, string $reason, User $rejector): CashAdvance
    {
        $advance->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'deduction_status' => 'cancelled',
            'updated_by' => $rejector->id,
        ]);

        Log::info("Cash advance rejected", [
            'advance_number' => $advance->advance_number,
            'reason' => $reason,
            'rejector' => $rejector->name,
        ]);

        return $advance;
    }

    /**
     * Check if employee is eligible for cash advance
     */
    public function checkEmployeeEligibility(Employee $employee): array
    {
        // Rule 1: Employee must be active
        if ($employee->employment_status !== 'active') {
            return ['eligible' => false, 'reason' => 'Employee is not actively employed'];
        }

        // Rule 2: Employee must be regular/permanent (not probationary)
        if ($employee->employee_type === 'probationary') {
            return ['eligible' => false, 'reason' => 'Probationary employees are not eligible for advances'];
        }

        // Rule 3: Minimum 3 months employment
        $employmentMonths = $employee->hire_date->diffInMonths(now());
        if ($employmentMonths < 3) {
            return ['eligible' => false, 'reason' => 'Minimum 3 months employment required'];
        }

        // Rule 4: No active advances
        $activeAdvances = $employee->cashAdvances()
            ->where('deduction_status', 'active')
            ->count();

        if ($activeAdvances > 0) {
            return ['eligible' => false, 'reason' => 'Employee has an active advance. Only 1 active advance allowed.'];
        }

        // Rule 5: Total deductions must be less than 40% of gross pay
        // (This check will be done in PayrollCalculationService)

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Calculate maximum advance amount for employee
     */
    public function calculateMaxAdvanceAmount(Employee $employee): float
    {
        // Max advance = 50% of monthly basic salary
        $basicSalary = $employee->payrollInfo->basic_salary ?? 0;
        return $basicSalary * 0.50;
    }

    /**
     * Generate unique advance number (ADV-2026-0001)
     */
    private function generateAdvanceNumber(): string
    {
        $year = now()->year;
        $prefix = "ADV-{$year}-";

        $lastAdvance = CashAdvance::where('advance_number', 'like', "{$prefix}%")
            ->orderBy('advance_number', 'desc')
            ->first();

        if ($lastAdvance) {
            $lastNumber = (int) substr($lastAdvance->advance_number, -4);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . $nextNumber;
    }

    /**
     * Schedule deductions for approved advance
     */
    private function scheduleDeductions(CashAdvance $advance): void
    {
        // Get next N payroll periods
        $upcomingPeriods = PayrollPeriod::where('pay_date', '>=', now())
            ->orderBy('pay_date', 'asc')
            ->take($advance->number_of_installments)
            ->get();

        if ($upcomingPeriods->count() < $advance->number_of_installments) {
            throw new \Exception("Not enough upcoming payroll periods to schedule deductions");
        }

        $remainingBalance = $advance->amount_approved;

        foreach ($upcomingPeriods as $index => $period) {
            $installmentNumber = $index + 1;
            $deductionAmount = $advance->deduction_amount_per_period;

            // Last installment might have rounding adjustment
            if ($installmentNumber === $advance->number_of_installments) {
                $deductionAmount = $remainingBalance;
            }

            $remainingBalance -= $deductionAmount;

            AdvanceDeduction::create([
                'cash_advance_id' => $advance->id,
                'payroll_period_id' => $period->id,
                'installment_number' => $installmentNumber,
                'deduction_amount' => $deductionAmount,
                'remaining_balance_after' => max(0, $remainingBalance),
                'is_deducted' => false,
            ]);
        }

        Log::info("Deductions scheduled for advance", [
            'advance_number' => $advance->advance_number,
            'installments' => $advance->number_of_installments,
        ]);
    }

    /**
     * Cancel cash advance (before or after approval)
     */
    public function cancelAdvance(CashAdvance $advance, string $reason, User $canceller): CashAdvance
    {
        DB::beginTransaction();
        try {
            $advance->update([
                'deduction_status' => 'cancelled',
                'completion_reason' => 'cancelled',
                'completed_at' => now(),
                'updated_by' => $canceller->id,
            ]);

            // Cancel pending deductions
            $advance->advanceDeductions()
                ->where('is_deducted', false)
                ->delete();

            DB::commit();

            Log::info("Cash advance cancelled", [
                'advance_number' => $advance->advance_number,
                'reason' => $reason,
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

---

#### Task 2.2: Create AdvanceDeductionService

**File:** `app/Services/Payroll/AdvanceDeductionService.php`
- **Action:** CREATE
- **Responsibility:** Handle advance deductions during payroll calculation
- **Methods:**
  - `getPendingDeductionsForEmployee()` - Get pending deductions for payroll period
  - `processDeductions()` - Process deductions for payroll calculation
  - `updateAdvanceBalance()` - Update advance balance after deduction
  - `handleInsufficientNetPay()` - Handle case when net pay is insufficient
  - `allowEarlyRepayment()` - Allow employee to make early repayment

```php
<?php

namespace App\Services\Payroll;

use App\Models\CashAdvance;
use App\Models\AdvanceDeduction;
use App\Models\PayrollPeriod;
use App\Models\EmployeePayrollCalculation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceDeductionService
{
    /**
     * Get pending deductions for employee in specific payroll period
     */
    public function getPendingDeductionsForEmployee(int $employeeId, int $payrollPeriodId): array
    {
        $deductions = AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('payroll_period_id', $payrollPeriodId)
            ->where('is_deducted', false)
            ->with('cashAdvance')
            ->orderBy('installment_number', 'asc')
            ->get();

        return $deductions->toArray();
    }

    /**
     * Process advance deductions for employee payroll calculation
     * 
     * @param int $employeeId
     * @param int $payrollPeriodId
     * @param float $availableNetPay Net pay before advance deductions
     * @param int|null $employeePayrollCalculationId
     * @return array ['total_deduction', 'deductions_applied', 'insufficient_pay']
     */
    public function processDeductions(
        int $employeeId,
        int $payrollPeriodId,
        float $availableNetPay,
        ?int $employeePayrollCalculationId = null
    ): array {
        $pendingDeductions = AdvanceDeduction::whereHas('cashAdvance', function ($query) use ($employeeId) {
                $query->where('employee_id', $employeeId)
                      ->where('deduction_status', 'active');
            })
            ->where('payroll_period_id', $payrollPeriodId)
            ->where('is_deducted', false)
            ->with('cashAdvance')
            ->get();

        if ($pendingDeductions->isEmpty()) {
            return [
                'total_deduction' => 0,
                'deductions_applied' => 0,
                'insufficient_pay' => false,
            ];
        }

        $totalDeduction = 0;
        $deductionsApplied = 0;
        $insufficientPay = false;

        DB::beginTransaction();
        try {
            foreach ($pendingDeductions as $deduction) {
                $advance = $deduction->cashAdvance;
                $deductionAmount = $deduction->deduction_amount;

                // Check if net pay is sufficient
                if ($availableNetPay < $deductionAmount) {
                    // Partial deduction or skip
                    $insufficientPay = true;
                    $deductionAmount = $availableNetPay > 0 ? $availableNetPay : 0;
                    
                    if ($deductionAmount === 0) {
                        // Skip this deduction, reschedule to next period
                        Log::warning("Insufficient net pay to deduct advance", [
                            'advance_number' => $advance->advance_number,
                            'deduction_amount' => $deduction->deduction_amount,
                            'available_net_pay' => $availableNetPay,
                        ]);
                        continue;
                    }
                }

                // Apply deduction
                $deduction->update([
                    'is_deducted' => true,
                    'deducted_at' => now(),
                    'deduction_amount' => $deductionAmount, // Update if partial
                    'employee_payroll_calculation_id' => $employeePayrollCalculationId,
                    'deduction_notes' => $insufficientPay ? 'Partial deduction due to insufficient net pay' : null,
                ]);

                // Update advance balance
                $this->updateAdvanceBalance($advance, $deductionAmount);

                $totalDeduction += $deductionAmount;
                $deductionsApplied++;
                $availableNetPay -= $deductionAmount;
            }

            DB::commit();

            return [
                'total_deduction' => $totalDeduction,
                'deductions_applied' => $deductionsApplied,
                'insufficient_pay' => $insufficientPay,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process advance deductions", [
                'employee_id' => $employeeId,
                'payroll_period_id' => $payrollPeriodId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update advance balance after deduction
     */
    private function updateAdvanceBalance(CashAdvance $advance, float $deductionAmount): void
    {
        $newTotalDeducted = $advance->total_deducted + $deductionAmount;
        $newRemainingBalance = $advance->amount_approved - $newTotalDeducted;
        $newInstallmentsCompleted = $advance->installments_completed + 1;

        $advance->update([
            'total_deducted' => $newTotalDeducted,
            'remaining_balance' => max(0, $newRemainingBalance),
            'installments_completed' => $newInstallmentsCompleted,
        ]);

        // Check if fully paid
        if ($newRemainingBalance <= 0.01) { // Allow 1 cent tolerance for rounding
            $advance->update([
                'deduction_status' => 'completed',
                'completion_reason' => 'fully_paid',
                'completed_at' => now(),
            ]);

            Log::info("Cash advance fully paid", [
                'advance_number' => $advance->advance_number,
                'total_deducted' => $newTotalDeducted,
            ]);
        }
    }

    /**
     * Allow early repayment of advance
     */
    public function allowEarlyRepayment(CashAdvance $advance, float $repaymentAmount): CashAdvance
    {
        if ($advance->deduction_status !== 'active') {
            throw new \Exception("Only active advances can be repaid early");
        }

        if ($repaymentAmount > $advance->remaining_balance) {
            throw new \Exception("Repayment amount exceeds remaining balance");
        }

        DB::beginTransaction();
        try {
            // Update advance balance
            $this->updateAdvanceBalance($advance, $repaymentAmount);

            // Cancel pending deductions if fully paid
            if ($advance->fresh()->deduction_status === 'completed') {
                $advance->advanceDeductions()
                    ->where('is_deducted', false)
                    ->delete();
            }

            DB::commit();

            Log::info("Early repayment made", [
                'advance_number' => $advance->advance_number,
                'repayment_amount' => $repaymentAmount,
            ]);

            return $advance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

---

### **Phase 3: Payroll Calculation Integration (Week 2-3: Feb 17-21)**

#### Task 3.1: Integrate with PayrollCalculationService

**File:** `app/Services/Payroll/PayrollCalculationService.php`
- **Action:** MODIFY
- **Change:** Add advance deduction calculation in `calculateEmployee()` method

```php
// In PayrollCalculationService::calculateEmployee()

// After calculating gross pay, allowances, government deductions, etc.

// --- ADVANCE DEDUCTIONS ---
$advanceDeductionService = app(AdvanceDeductionService::class);
$advanceResult = $advanceDeductionService->processDeductions(
    $employee->id,
    $payrollPeriodId,
    $netPayBeforeAdvances, // Net pay before advances
    $employeePayrollCalculation->id
);

$calculation['advance_deduction'] = $advanceResult['total_deduction'];
$calculation['net_pay'] -= $advanceResult['total_deduction'];

// Log if insufficient pay
if ($advanceResult['insufficient_pay']) {
    Log::warning("Insufficient net pay for full advance deduction", [
        'employee_id' => $employee->id,
        'payroll_period_id' => $payrollPeriodId,
        'advance_deduction_applied' => $advanceResult['total_deduction'],
    ]);
}
```

**Subtask 3.1.1: Update employee_payroll_calculations schema**
- **File:** `database/migrations/XXXX_XX_XX_XXXXXX_update_employee_payroll_calculations_add_advance_deduction.php`
- **Action:** CREATE
- **Change:** Add `advance_deduction` column to employee_payroll_calculations table

```php
Schema::table('employee_payroll_calculations', function (Blueprint $table) {
    $table->decimal('advance_deduction', 10, 2)->default(0)->after('other_deductions');
});
```

---

#### Task 3.2: Add Advance Deduction to Payslip

**File:** `app/Services/Payroll/PayslipGenerationService.php`
- **Action:** MODIFY
- **Change:** Add "Cash Advance" line item in payslip deductions section

```php
// In PayslipGenerationService::generatePayslip()

// Deductions section
$deductions = [
    ['description' => 'SSS Employee', 'amount' => $calculation->sss_employee],
    ['description' => 'PhilHealth Employee', 'amount' => $calculation->philhealth_employee],
    ['description' => 'Pag-IBIG Employee', 'amount' => $calculation->pagibig_employee],
    ['description' => 'Withholding Tax', 'amount' => $calculation->withholding_tax],
    ['description' => 'Tardiness/Undertime', 'amount' => $calculation->tardiness_deduction],
    ['description' => 'Absence', 'amount' => $calculation->absence_deduction],
    ['description' => 'SSS Loan', 'amount' => $calculation->sss_loan],
    ['description' => 'Pag-IBIG Loan', 'amount' => $calculation->pagibig_loan],
    ['description' => 'Company Loan', 'amount' => $calculation->company_loan],
    ['description' => 'Cash Advance', 'amount' => $calculation->advance_deduction], // NEW
    ['description' => 'Other Deductions', 'amount' => $calculation->other_deductions],
];
```

---

### **Phase 4: Controller & API Implementation (Week 3: Feb 20-25)**

#### Task 4.1: Update AdvancesController with Real Logic

**File:** `app/Http/Controllers/Payroll/AdvancesController.php`
- **Action:** MODIFY
- **Change:** Replace mock data with real database queries using services

```php
<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Services\Payroll\AdvanceManagementService;
use App\Services\Payroll\AdvanceDeductionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdvancesController extends Controller
{
    public function __construct(
        private AdvanceManagementService $advanceService,
        private AdvanceDeductionService $deductionService
    ) {}

    /**
     * Display a listing of cash advances
     */
    public function index(Request $request)
    {
        $query = CashAdvance::with(['employee', 'department', 'approvedBy', 'advanceDeductions'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $status = $request->status;
            if (in_array($status, ['pending', 'approved', 'rejected'])) {
                $query->where('approval_status', $status);
            } elseif (in_array($status, ['active', 'completed', 'cancelled'])) {
                $query->where('deduction_status', $status);
            }
        }

        if ($request->has('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->has('date_from')) {
            $query->where('requested_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('requested_date', '<=', $request->date_to);
        }

        $advances = $query->paginate(20)->withQueryString();

        // Transform for frontend
        $advancesData = $advances->through(function ($advance) {
            return [
                'id' => $advance->id,
                'advance_number' => $advance->advance_number,
                'employee_id' => $advance->employee_id,
                'employee_name' => $advance->employee->full_name,
                'employee_number' => $advance->employee->employee_number,
                'department_id' => $advance->department_id,
                'department_name' => $advance->department->name ?? 'N/A',
                'advance_type' => $advance->advance_type,
                'amount_requested' => $advance->amount_requested,
                'amount_approved' => $advance->amount_approved,
                'approval_status' => $advance->approval_status,
                'approval_status_label' => ucfirst($advance->approval_status),
                'approval_status_color' => $this->getStatusColor($advance->approval_status),
                'approved_by' => $advance->approvedBy->name ?? null,
                'approved_at' => $advance->approved_at?->toDateTimeString(),
                'approval_notes' => $advance->approval_notes,
                'deduction_status' => $advance->deduction_status,
                'deduction_status_label' => ucfirst($advance->deduction_status),
                'remaining_balance' => $advance->remaining_balance,
                'deduction_schedule' => $advance->deduction_schedule,
                'number_of_installments' => $advance->number_of_installments,
                'installments_completed' => $advance->installments_completed,
                'requested_date' => $advance->requested_date->toDateString(),
                'purpose' => $advance->purpose,
                'priority_level' => $advance->priority_level,
                'created_at' => $advance->created_at->toDateTimeString(),
            ];
        });

        $employees = Employee::select('id', 'full_name as name', 'employee_number', 'department_id')
            ->with('department:id,name')
            ->where('employment_status', 'active')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'employee_number' => $emp->employee_number,
                    'department' => $emp->department->name ?? 'N/A',
                ];
            });

        return Inertia::render('Payroll/Advances/Index', [
            'advances' => $advancesData,
            'filters' => $request->only(['search', 'status', 'department', 'date_from', 'date_to']),
            'employees' => $employees,
        ]);
    }

    /**
     * Store a newly created cash advance in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'advance_type' => 'required|in:cash_advance,medical_advance,travel_advance,equipment_advance',
            'amount_requested' => 'required|numeric|min:1000',
            'purpose' => 'required|string|min:10|max:500',
            'requested_date' => 'required|date',
            'priority_level' => 'required|in:normal,urgent',
        ]);

        try {
            $advance = $this->advanceService->createAdvanceRequest($validated, $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance request {$advance->advance_number} created successfully");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Approve a pending cash advance
     */
    public function approve(Request $request, int $id)
    {
        $advance = CashAdvance::findOrFail($id);

        if ($advance->approval_status !== 'pending') {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Only pending advances can be approved.']);
        }

        $validated = $request->validate([
            'amount_approved' => 'required|numeric|min:1000|max:' . $advance->amount_requested,
            'deduction_schedule' => 'required|in:single_period,installments',
            'number_of_installments' => 'required|integer|min:1|max:6',
            'approval_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->advanceService->approveAdvance($advance, $validated, $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} approved successfully");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a pending cash advance
     */
    public function reject(Request $request, int $id)
    {
        $advance = CashAdvance::findOrFail($id);

        if ($advance->approval_status !== 'pending') {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Only pending advances can be rejected.']);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->advanceService->rejectAdvance($advance, $validated['rejection_reason'], $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} rejected");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an active advance
     */
    public function cancel(Request $request, int $id)
    {
        $advance = CashAdvance::findOrFail($id);

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:10|max:500',
        ]);

        try {
            $this->advanceService->cancelAdvance($advance, $validated['cancellation_reason'], $request->user());

            return redirect()
                ->route('payroll.advances.index')
                ->with('success', "Advance {$advance->advance_number} cancelled");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get status color for badges
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'rejected' => 'red',
            'active' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
```

---

#### Task 4.2: Create Form Request Classes

**Subtask 4.2.1: Create StoreAdvanceRequest**
- **File:** `app/Http/Requests/Payroll/StoreAdvanceRequest.php`
- **Action:** CREATE

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_cash_advances');
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'advance_type' => 'required|in:cash_advance,medical_advance,travel_advance,equipment_advance',
            'amount_requested' => 'required|numeric|min:1000',
            'purpose' => 'required|string|min:10|max:500',
            'requested_date' => 'required|date',
            'priority_level' => 'required|in:normal,urgent',
            'supporting_documents' => 'nullable|array',
            'supporting_documents.*' => 'file|max:5120', // 5MB max per file
        ];
    }

    public function messages(): array
    {
        return [
            'amount_requested.min' => 'Minimum advance amount is ₱1,000',
            'purpose.min' => 'Purpose must be at least 10 characters',
        ];
    }
}
```

**Subtask 4.2.2: Create ApproveAdvanceRequest**
- **File:** `app/Http/Requests/Payroll/ApproveAdvanceRequest.php`
- **Action:** CREATE

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve_cash_advances');
    }

    public function rules(): array
    {
        $advance = $this->route('advance'); // Get advance from route

        return [
            'amount_approved' => [
                'required',
                'numeric',
                'min:1000',
                'max:' . $advance->amount_requested,
            ],
            'deduction_schedule' => 'required|in:single_period,installments',
            'number_of_installments' => [
                'required',
                'integer',
                'min:1',
                'max:6',
            ],
            'approval_notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'amount_approved.max' => 'Approved amount cannot exceed requested amount',
            'number_of_installments.max' => 'Maximum 6 installments allowed',
        ];
    }
}
```

---

#### Task 4.3: Add API Routes

**File:** `routes/payroll.php`
- **Action:** MODIFY
- **Change:** Add advance routes

```php
// Cash Advances
Route::prefix('advances')->name('advances.')->group(function () {
    Route::get('/', [AdvancesController::class, 'index'])->name('index');
    Route::post('/', [AdvancesController::class, 'store'])->name('store');
    Route::post('/{id}/approve', [AdvancesController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject', [AdvancesController::class, 'reject'])->name('reject');
    Route::post('/{id}/cancel', [AdvancesController::class, 'cancel'])->name('cancel');
    Route::get('/{id}/deductions', [AdvancesController::class, 'getDeductions'])->name('deductions');
});
```

---

### **Phase 5: Frontend Integration (Week 3: Feb 22-25)**

#### Task 5.1: Update Frontend to Use Real API

**File:** `resources/js/pages/Payroll/Advances/Index.tsx`
- **Action:** MODIFY (if necessary)
- **Change:** Ensure all API calls use Inertia router instead of console.log

```tsx
// Replace console.log with actual API calls
const handleApprove = (data: CashAdvanceApprovalData) => {
    router.post(`/payroll/advances/${data.advance_id}/approve`, {
        amount_approved: data.amount_approved,
        deduction_schedule: data.deduction_schedule,
        number_of_installments: data.number_of_installments,
        approval_notes: data.approval_notes,
    }, {
        onSuccess: () => {
            setIsApprovalModalOpen(false);
            // Success notification handled by backend
        },
        onError: (errors) => {
            console.error('Approval failed:', errors);
        },
    });
};

const handleReject = (advanceId: number, reason: string) => {
    router.post(`/payroll/advances/${advanceId}/reject`, {
        rejection_reason: reason,
    }, {
        onSuccess: () => {
            setIsApprovalModalOpen(false);
        },
    });
};

const handleSubmitRequest = (data: CashAdvanceFormData) => {
    router.post('/payroll/advances', data, {
        onSuccess: () => {
            setIsRequestFormOpen(false);
        },
    });
};
```

---

### **Phase 6: Testing & Validation (Week 3: Feb 24-27)**

#### Task 6.1: Unit Tests for Services

**Subtask 6.1.1: Test AdvanceManagementService**
- **File:** `tests/Unit/Services/Payroll/AdvanceManagementServiceTest.php`
- **Action:** CREATE

```php
<?php

namespace Tests\Unit\Services\Payroll;

use Tests\TestCase;
use App\Services\Payroll\AdvanceManagementService;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvanceManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdvanceManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdvanceManagementService();
    }

    /** @test */
    public function it_creates_advance_request_successfully()
    {
        $employee = Employee::factory()->create(['employee_type' => 'regular']);
        $user = User::factory()->create();

        $data = [
            'employee_id' => $employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 10000,
            'purpose' => 'Emergency medical expenses',
            'priority_level' => 'urgent',
        ];

        $advance = $this->service->createAdvanceRequest($data, $user);

        $this->assertInstanceOf(CashAdvance::class, $advance);
        $this->assertEquals('pending', $advance->approval_status);
        $this->assertStringStartsWith('ADV-', $advance->advance_number);
    }

    /** @test */
    public function it_rejects_advance_for_probationary_employee()
    {
        $employee = Employee::factory()->create(['employee_type' => 'probationary']);
        $user = User::factory()->create();

        $eligibility = $this->service->checkEmployeeEligibility($employee);

        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('Probationary', $eligibility['reason']);
    }

    /** @test */
    public function it_approves_advance_and_schedules_deductions()
    {
        $advance = CashAdvance::factory()->create(['approval_status' => 'pending']);
        $user = User::factory()->create();

        $approvalData = [
            'amount_approved' => 10000,
            'deduction_schedule' => 'installments',
            'number_of_installments' => 5,
            'approval_notes' => 'Approved',
        ];

        $approved = $this->service->approveAdvance($advance, $approvalData, $user);

        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals('active', $approved->deduction_status);
        $this->assertCount(5, $approved->advanceDeductions);
    }
}
```

**Subtask 6.1.2: Test AdvanceDeductionService**
- **File:** `tests/Unit/Services/Payroll/AdvanceDeductionServiceTest.php`
- **Action:** CREATE

---

#### Task 6.2: Feature Tests for Controller

**File:** `tests/Feature/Payroll/AdvancesControllerTest.php`
- **Action:** CREATE

```php
<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\CashAdvance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class AdvancesControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_advances_index_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('payroll.advances.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Advances/Index')
            ->has('advances')
            ->has('employees')
        );
    }

    /** @test */
    public function it_creates_advance_request()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user);

        $data = [
            'employee_id' => $employee->id,
            'advance_type' => 'cash_advance',
            'amount_requested' => 15000,
            'purpose' => 'Emergency home repair expenses',
            'requested_date' => now()->toDateString(),
            'priority_level' => 'urgent',
        ];

        $response = $this->post(route('payroll.advances.store'), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'employee_id' => $employee->id,
            'amount_requested' => 15000,
        ]);
    }

    /** @test */
    public function it_approves_pending_advance()
    {
        $user = User::factory()->create();
        $advance = CashAdvance::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($user);

        $data = [
            'amount_approved' => 10000,
            'deduction_schedule' => 'installments',
            'number_of_installments' => 3,
            'approval_notes' => 'Approved for employee',
        ];

        $response = $this->post(route('payroll.advances.approve', $advance->id), $data);

        $response->assertRedirect(route('payroll.advances.index'));
        $this->assertDatabaseHas('employee_cash_advances', [
            'id' => $advance->id,
            'approval_status' => 'approved',
        ]);
    }
}
```

---

#### Task 6.3: Manual Testing Scenarios

**Scenario 1: Employee Requests Advance**
- Login as HR Staff or Employee (future)
- Navigate to Advances page
- Click "Request Advance"
- Fill form with valid data
- Submit → Verify advance created with "pending" status

**Scenario 2: HR Manager Approves Advance**
- Login as HR Manager
- Navigate to Advances page
- Filter by "Pending"
- Click "Approve" on advance
- Set approval amount, schedule, installments
- Submit → Verify advance status changes to "approved" and "active"
- Verify advance_deductions records created

**Scenario 3: Payroll Deduction**
- Create approved advance with single installment
- Run payroll calculation for period
- Verify employee_payroll_calculations.cash_advance = deduction amount
- Verify net_pay reduced by deduction
- Verify advance_deductions.is_deducted = true
- Verify cash_advances.remaining_balance updated

**Scenario 4: Insufficient Net Pay**
- Create approved advance with ₱5,000 deduction
- Employee has net pay of ₱3,000 only
- Run payroll calculation
- Verify partial deduction of ₱3,000 applied
- Verify remaining ₱2,000 carried forward to next period

**Scenario 5: Complete Advance**
- Create advance with 3 installments
- Run payroll for 3 consecutive periods
- Verify all 3 deductions applied
- Verify advance status changes to "completed"
- Verify remaining_balance = 0

---

## 📊 Implementation Timeline

### Week 1: Feb 6-12 (Database Foundation)
- **Day 1-2:** Create migrations and models
- **Day 3:** Test database schema with migrations
- **Day 4:** Create model factories and seeders for testing
- **Day 5:** Review and validate database design

### Week 2: Feb 13-19 (Services & Business Logic)
- **Day 1-2:** Build AdvanceManagementService (eligibility, approval, schedule)
- **Day 3-4:** Build AdvanceDeductionService (deduction processing, balance tracking)
- **Day 5:** Integrate with PayrollCalculationService

### Week 3: Feb 20-27 (Controllers, Testing & Validation)
- **Day 1-2:** Update AdvancesController with real logic
- **Day 3:** Create form request classes and routes
- **Day 4-5:** Write unit tests and feature tests
- **Day 6-7:** Manual testing and bug fixes

---

## ✅ Success Criteria

1. ✅ **Database tables created** with proper schema and relationships
2. ✅ **Eloquent models** with relationships, scopes, and accessors
3. ✅ **AdvanceManagementService** handles advance lifecycle (create, approve, reject, cancel)
4. ✅ **AdvanceDeductionService** processes deductions during payroll calculation
5. ✅ **PayrollCalculationService integration** automatically deducts advances from net pay
6. ✅ **Controller updated** with real database queries (no mock data)
7. ✅ **Payslips show advance deductions** as line item
8. ✅ **Frontend works** with real API (create, approve, reject, track)
9. ✅ **Unit tests pass** for all services
10. ✅ **Feature tests pass** for controller endpoints
11. ✅ **Manual testing complete** with all scenarios validated

---

## 📋 Dependencies

### Required Before Implementation
- ✅ **PayrollPeriod model** exists (for scheduling deductions)
- ✅ **EmployeePayrollCalculation model** exists (for linking deductions)
- ✅ **Employee model** with payrollInfo relationship
- ✅ **Department model** for employee departments
- ✅ **User model** for approvers

### Integration Requirements
- **PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md** - For attendance-based salary calculations
- **PAYROLL-LEAVE-INTEGRATION-ROADMAP.md** - For unpaid leave impact on deductions

### Future Enhancements
- 📧 **Email notifications** when advance is approved/rejected
- 📱 **SMS reminders** before deduction
- 📄 **Advance agreement e-signature** (digital signing)
- 📊 **Advanced reporting** (monthly advance report, annual summary)
- 🤖 **Automatic eligibility checks** with real-time validation

---

## 🚨 Risk Mitigation

### Potential Issues
1. **Insufficient net pay for deduction**  
   → **Mitigation:** Allow partial deductions, reschedule remaining balance

2. **Employee resigns before full repayment**  
   → **Mitigation:** Deduct full balance from final pay, document in clearance

3. **Rounding errors in installments**  
   → **Mitigation:** Adjust last installment to match exact remaining balance

4. **Payroll period not available for scheduling**  
   → **Mitigation:** Create payroll periods in advance, validate before approval

5. **Concurrent advance requests**  
   → **Mitigation:** Database transaction locks, unique constraints

---

## 📝 Notes

- **Advance vs Loan:** Advances are salary advances (not loans), so they're tax-neutral (already part of salary)
- **Compliance:** Ensure advances comply with DOLE regulations (max 40% total deductions)
- **Audit Trail:** All advance actions should be logged for audit purposes
- **Documentation:** Advance agreement form should be digitized and stored
- **Integration:** Coordinate with Timekeeping and Leave modules for accurate deductions

---

**END OF IMPLEMENTATION PLAN**
