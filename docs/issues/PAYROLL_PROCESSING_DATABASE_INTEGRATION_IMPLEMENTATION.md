# Payroll Processing - Database Integration Implementation

**Issue:** Payroll Processing module has complete frontend UI and database schema, but controllers are still using mock data instead of Eloquent models.

**Objective:** Complete the backend-database integration by replacing mock data with real database queries across all 4 controllers.

**Status:** Backend Infrastructure 60% Complete → Target: 100%

**Estimated Duration:** 3 days (3 phases, 12 tasks)

---

## Summary

### Problem Statement
- ✅ Database models created and deployed
- ✅ Database migrations ran successfully  
- ✅ Frontend pages fully implemented
- ❌ Controllers still use hard-coded mock data arrays
- ❌ No real data persistence
- ❌ Manual adjustments, calculations not saved to database

### Current State
```
PayrollPeriodController::index()
    ↓ (returns mock array with 5 fake payroll periods)
Frontend Table
    ↓ (displays but doesn't persist)
No Database Query
```

### Target State
```
PayrollPeriodController::index()
    ↓ (queries PayrollPeriod model)
Database Query
    ↓ (applies filters, pagination)
Frontend Table
    ↓ (displays & updates real data in database)
Database Records Updated
```

---

## Acceptance Criteria

- ✅ All 4 controllers use Eloquent models instead of mock data
- ✅ CRUD operations save/update to database
- ✅ All filtering works with real data
- ✅ Approval workflows persist to database
- ✅ No compilation errors in PHP models
- ✅ Frontend pages display real data from database
- ✅ All test cases pass with real data

---

## Phased Plan

### Phase 1: Fix Model Errors & Add Model Methods (0.75 days)

**Tasks:**
1. ✅ Fix PayrollPeriod model date casting
2. ✅ Add model query helper methods
3. ✅ Add model relationship helpers
4. ✅ Add model status transition methods

**Deliverables:**
- Fixed PayrollPeriod.php with proper date casts
- Query scope methods for filtering
- Helper methods for status changes

**Files to Modify:**
1. `app/Models/PayrollPeriod.php` - Add date casts, query methods
2. `app/Models/EmployeePayrollCalculation.php` - Add filter scopes
3. `app/Models/PayrollAdjustment.php` - Add approval methods
4. `app/Models/PayrollApprovalHistory.php` - Add query methods

---

### Phase 2: Update Payroll Periods Controller (0.75 days)

**Tasks:**
1. ✅ Import PayrollPeriod model
2. ✅ Replace mock data with database queries
3. ✅ Implement store() with model create
4. ✅ Implement update() method
5. ✅ Implement delete() method
6. ✅ Add calculate() and approve() methods

**Deliverables:**
- PayrollPeriodController using PayrollPeriod model
- Full CRUD operations
- Approval workflow

**Files to Modify:**
1. `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`

**Before:**
```php
public function index(Request $request)
{
    $allPeriods = [
        [ 'id' => 1, 'name' => 'Mock Period', ... ],
        [ 'id' => 2, 'name' => 'Another Mock', ... ],
    ];
    return Inertia::render('...', ['periods' => $allPeriods]);
}
```

**After:**
```php
public function index(Request $request)
{
    $periods = PayrollPeriod::query()
        ->when($request->search, fn($q) => $q->whereLike('period_name', $request->search))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->latest()
        ->paginate(15);
    return Inertia::render('...', ['periods' => $periods]);
}
```

---

### Phase 3: Update Remaining Controllers (1.5 days)

**3a. Payroll Calculations Controller**
- Replace calculation mock data with EmployeePayrollCalculation queries
- Implement store() to create calculation records
- Implement recalculate() method
- Implement approve() method

**3b. Payroll Adjustments Controller**
- Replace adjustment mock data with PayrollAdjustment queries
- Implement store() to create adjustments
- Implement approve() method
- Implement reject() method

**3c. Payroll Review Controller**
- Replace review data with actual period calculations
- Implement department breakdown queries
- Implement exceptions query
- Implement approval workflow

**Deliverables:**
- All 3 controllers using models
- Full data persistence
- Approval workflows

**Files to Modify:**
1. `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`
2. `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php`
3. `app/Http/Controllers/Payroll/PayrollProcessing/PayrollReviewController.php`

---

## Implementation Details

### Phase 1: Fix Models & Add Query Methods

#### 1.1 Update PayrollPeriod Model

**File:** `app/Models/PayrollPeriod.php`

**Changes:**
- Add date casting for `period_start` and `period_end`
- Add query scopes for filtering
- Add status transition methods
- Add calculation methods

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PayrollPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'period_number',
        'period_name',
        'period_start',
        'period_end',
        'payment_date',
        'period_month',
        'period_year',
        'period_type',
        'timekeeping_cutoff_date',
        'leave_cutoff_date',
        'adjustment_deadline',
        'total_employees',
        'active_employees',
        'excluded_employees',
        'employee_filter',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'total_government_contributions',
        'total_loan_deductions',
        'total_adjustments',
        'status',
        'calculation_started_at',
        'calculation_completed_at',
        'submitted_for_review_at',
        'reviewed_at',
        'approved_at',
        'finalized_at',
        'locked_at',
        'calculation_config',
        'calculation_retries',
        'calculation_errors',
        'exceptions_count',
        'created_by',
        'updated_by',
        'reviewed_by',
        'approved_by',
        'finalized_by',
    ];

    // ✅ FIX: Add date casting
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'timekeeping_cutoff_date' => 'date',
        'leave_cutoff_date' => 'date',
        'adjustment_deadline' => 'date',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'locked_at' => 'datetime',
        'calculation_config' => 'array',
        'employee_filter' => 'array',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'total_government_contributions' => 'decimal:2',
        'total_loan_deductions' => 'decimal:2',
        'total_adjustments' => 'decimal:2',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function employeeCalculations(): HasMany
    {
        return $this->hasMany(EmployeePayrollCalculation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(PayrollException::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function approvalHistory(): HasMany
    {
        return $this->hasMany(PayrollApprovalHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    // ============================================================
    // Query Scopes
    // ============================================================

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('period_type', $type);
    }

    public function scopeByMonth($query, $year, $month)
    {
        return $query->where('period_year', $year)
                     ->where(function ($q) use ($month) {
                         $q->whereRaw("MONTH(STR_TO_DATE(period_month, '%Y-%m')) = ?", [$month])
                           ->orWhere('period_month', 'like', "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT));
                     });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'calculating', 'calculated', 'under_review', 'pending_approval', 'approved']);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'processing_payment', 'finalized']);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('period_name', 'like', "%{$search}%")
                     ->orWhere('period_number', 'like', "%{$search}%");
    }

    // ============================================================
    // Status Transition Methods
    // ============================================================

    public function markAsCalculating(): bool
    {
        return $this->update([
            'status' => 'calculating',
            'calculation_started_at' => now(),
        ]);
    }

    public function markAsCalculated(): bool
    {
        return $this->update([
            'status' => 'calculated',
            'calculation_completed_at' => now(),
        ]);
    }

    public function submitForReview(): bool
    {
        if ($this->status !== 'calculated') {
            throw new \Exception('Only calculated periods can be submitted for review');
        }

        return $this->update([
            'status' => 'under_review',
            'submitted_for_review_at' => now(),
        ]);
    }

    public function approve($userId): bool
    {
        if ($this->status !== 'under_review') {
            throw new \Exception('Only periods under review can be approved');
        }

        return $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    public function finalize($userId): bool
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Only approved periods can be finalized');
        }

        return $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $userId,
            'locked_at' => now(),
        ]);
    }

    public function completePaymentProcessing(): bool
    {
        return $this->update([
            'status' => 'completed',
        ]);
    }

    // ============================================================
    // Calculation Methods
    // ============================================================

    public function calculateTotals(): void
    {
        $totals = $this->employeeCalculations()
            ->select(
                \DB::raw('SUM(gross_pay) as total_gross_pay'),
                \DB::raw('SUM(total_deductions) as total_deductions'),
                \DB::raw('SUM(net_pay) as total_net_pay'),
                \DB::raw('SUM(sss_contribution + philhealth_contribution + pagibig_contribution) as total_government_contributions'),
                \DB::raw('SUM(loan_deduction_amount) as total_loan_deductions')
            )
            ->first();

        $this->update([
            'total_gross_pay' => $totals->total_gross_pay ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net_pay' => $totals->total_net_pay ?? 0,
            'total_government_contributions' => $totals->total_government_contributions ?? 0,
            'total_loan_deductions' => $totals->total_loan_deductions ?? 0,
            'exceptions_count' => $this->exceptions()->count(),
        ]);
    }

    public function getPeriodCode(): string
    {
        $periodCode = $this->period_start->day <= 15 ? 'A' : 'B';
        return $this->period_start->format('Y-m') . '-' . $periodCode;
    }

    // ============================================================
    // Attribute Accessors
    // ============================================================

    public function getFormattedStatusAttribute(): string
    {
        $statuses = [
            'draft' => 'Draft',
            'active' => 'Active',
            'calculating' => 'Calculating',
            'calculated' => 'Calculated',
            'under_review' => 'Under Review',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'finalized' => 'Finalized',
            'processing_payment' => 'Processing Payment',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'gray',
            'active' => 'blue',
            'calculating' => 'yellow',
            'calculated' => 'green',
            'under_review' => 'blue',
            'pending_approval' => 'orange',
            'approved' => 'green',
            'finalized' => 'green',
            'processing_payment' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getStatusBadgeAttribute(): array
    {
        return [
            'label' => $this->formatted_status,
            'color' => $this->status_color,
        ];
    }
}
```

#### 1.2 Update EmployeePayrollCalculation Model

**File:** `app/Models/EmployeePayrollCalculation.php`

**Add to existing model:**

```php
// Add to EmployeePayrollCalculation class

// ============================================================
// Query Scopes
// ============================================================

public function scopeByPayrollPeriod($query, $payrollPeriodId)
{
    return $query->where('payroll_period_id', $payrollPeriodId);
}

public function scopeByEmployee($query, $employeeId)
{
    return $query->where('employee_id', $employeeId);
}

public function scopeByStatus($query, $status)
{
    return $query->where('status', $status);
}

public function scopeWithErrors($query)
{
    return $query->where('has_calculation_error', true)
                 ->whereNotNull('calculation_error_message');
}

public function scopeSearch($query, $search)
{
    return $query->where('employee_name', 'like', "%{$search}%")
                 ->orWhere('employee_number', 'like', "%{$search}%");
}

// ============================================================
// Relationships
// ============================================================

public function payrollPeriod(): BelongsTo
{
    return $this->belongsTo(PayrollPeriod::class);
}

public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class);
}

public function adjustments(): HasMany
{
    return $this->hasMany(PayrollAdjustment::class, 'employee_payroll_calculation_id');
}

public function exceptions(): HasMany
{
    return $this->hasMany(PayrollException::class, 'employee_payroll_calculation_id');
}

// ============================================================
// Helper Methods
// ============================================================

public function markAsCalculated(): bool
{
    return $this->update([
        'status' => 'calculated',
        'calculated_at' => now(),
    ]);
}

public function markAsError($errorMessage): bool
{
    return $this->update([
        'status' => 'failed',
        'has_calculation_error' => true,
        'calculation_error_message' => $errorMessage,
    ]);
}
```

#### 1.3 Update PayrollAdjustment Model

**File:** `app/Models/PayrollAdjustment.php`

**Add to existing model:**

```php
// Add to PayrollAdjustment class

// ============================================================
// Query Scopes
// ============================================================

public function scopeByStatus($query, $status)
{
    return $query->where('status', $status);
}

public function scopePending($query)
{
    return $query->where('status', 'pending');
}

public function scopeApproved($query)
{
    return $query->where('status', 'approved');
}

public function scopeRejected($query)
{
    return $query->where('status', 'rejected');
}

public function scopeByType($query, $type)
{
    return $query->where('adjustment_type', $type);
}

public function scopeByEmployee($query, $employeeId)
{
    return $query->where('employee_id', $employeeId);
}

public function scopeByPeriod($query, $payrollPeriodId)
{
    return $query->where('payroll_period_id', $payrollPeriodId);
}

// ============================================================
// Relationships
// ============================================================

public function payrollPeriod(): BelongsTo
{
    return $this->belongsTo(PayrollPeriod::class);
}

public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class);
}

public function approvedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'approved_by');
}

public function rejectedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'rejected_by');
}

public function createdByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}

// ============================================================
// Approval Methods
// ============================================================

public function approve($userId, $notes = null): bool
{
    if ($this->status !== 'pending') {
        throw new \Exception('Only pending adjustments can be approved');
    }

    return $this->update([
        'status' => 'approved',
        'approved_at' => now(),
        'approved_by' => $userId,
        'review_notes' => $notes,
    ]);
}

public function reject($userId, $reason): bool
{
    if ($this->status !== 'pending') {
        throw new \Exception('Only pending adjustments can be rejected');
    }

    return $this->update([
        'status' => 'rejected',
        'rejected_at' => now(),
        'rejected_by' => $userId,
        'rejection_reason' => $reason,
    ]);
}

public function apply(): bool
{
    if ($this->status !== 'approved') {
        throw new \Exception('Only approved adjustments can be applied');
    }

    return $this->update([
        'status' => 'applied',
        'applied_at' => now(),
    ]);
}
```

---

### Phase 2: Update PayrollPeriodController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`

**Complete Replacement:**

```php
<?php

namespace App\Http\Controllers\Payroll\PayrollProcessing;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PayrollPeriodController extends Controller
{
    /**
     * Display a listing of payroll periods with filtering
     */
    public function index(Request $request)
    {
        $query = PayrollPeriod::query();

        // Search filter
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->byStatus($request->input('status'));
        }

        // Period type filter
        if ($request->filled('period_type') && $request->input('period_type') !== 'all') {
            $query->byType($request->input('period_type'));
        }

        // Year filter
        if ($request->filled('year')) {
            $query->whereYear('period_start', $request->input('year'));
        }

        // Get paginated results
        $periods = $query->latest('period_start')
                         ->paginate(15)
                         ->appends($request->query());

        // Collect filter values for display in component
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'period_type' => $request->input('period_type'),
            'year' => $request->input('year', date('Y')),
        ];

        return Inertia::render('Payroll/PayrollProcessing/Periods/Index', [
            'periods' => $periods,
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created payroll period
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255|unique:payroll_periods',
            'period_type' => 'required|in:regular,adjustment,13th_month,final_pay,mid_year_bonus',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'timekeeping_cutoff_date' => 'required|date',
            'payment_date' => 'required|date|after:period_end',
            'leave_cutoff_date' => 'required|date',
            'adjustment_deadline' => 'required|date|before:timekeeping_cutoff_date',
        ]);

        try {
            $startDate = Carbon::parse($validated['period_start']);
            
            // Generate period number (e.g., "2026-03-A" for first half)
            $periodCode = $startDate->day <= 15 ? 'A' : 'B';
            $periodNumber = $startDate->format('Y-m') . '-' . $periodCode;

            $period = PayrollPeriod::create([
                ...$validated,
                'period_number' => $periodNumber,
                'period_month' => $startDate->format('Y-m'),
                'period_year' => $startDate->year,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('payroll.periods.index')
                ->with('success', "Payroll period '{$validated['period_name']}' created successfully.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors('error', "Failed to create payroll period: {$e->getMessage()}");
        }
    }

    /**
     * Display the specified payroll period
     */
    public function show($id)
    {
        $period = PayrollPeriod::findOrFail($id);

        return Inertia::render('Payroll/PayrollProcessing/Periods/Show', [
            'period' => $period->load(['employeeCalculations', 'adjustments', 'exceptions']),
        ]);
    }

    /**
     * Show the form for editing the specified payroll period
     */
    public function edit($id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'draft') {
            return redirect()
                ->route('payroll.periods.index')
                ->with('error', 'Only draft periods can be edited.');
        }

        return Inertia::render('Payroll/PayrollProcessing/Periods/Edit', [
            'period' => $period,
        ]);
    }

    /**
     * Update the specified payroll period
     */
    public function update(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'draft') {
            return redirect()
                ->back()
                ->with('error', 'Only draft periods can be edited.');
        }

        $validated = $request->validate([
            'period_name' => 'required|string|max:255|unique:payroll_periods,period_name,' . $id,
            'period_type' => 'required|in:regular,adjustment,13th_month,final_pay,mid_year_bonus',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'timekeeping_cutoff_date' => 'required|date',
            'payment_date' => 'required|date|after:period_end',
            'leave_cutoff_date' => 'required|date',
            'adjustment_deadline' => 'required|date|before:timekeeping_cutoff_date',
        ]);

        try {
            $period->update([
                ...$validated,
                'updated_by' => auth()->id(),
            ]);

            return redirect()
                ->route('payroll.periods.index')
                ->with('success', "Payroll period '{$validated['period_name']}' updated successfully.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors('error', "Failed to update payroll period: {$e->getMessage()}");
        }
    }

    /**
     * Delete the specified payroll period
     */
    public function destroy($id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'draft') {
            return redirect()
                ->back()
                ->with('error', 'Only draft periods can be deleted.');
        }

        try {
            $period->delete();

            return redirect()
                ->route('payroll.periods.index')
                ->with('success', 'Payroll period deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors('error', "Failed to delete payroll period: {$e->getMessage()}");
        }
    }

    /**
     * Calculate payroll for the specified period
     */
    public function calculate(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if (!in_array($period->status, ['draft', 'active'])) {
            return redirect()
                ->back()
                ->with('error', 'Period must be draft or active to calculate.');
        }

        try {
            $period->markAsCalculating();

            // TODO: Dispatch CalculatePayrollJob::dispatch($period)
            // For now, this would trigger background calculation

            return redirect()
                ->back()
                ->with('success', 'Payroll calculation started. This may take a few moments.');
        } catch (\Exception $e) {
            $period->update(['status' => 'draft']);
            return redirect()
                ->back()
                ->withErrors('error', "Failed to start calculation: {$e->getMessage()}");
        }
    }

    /**
     * Submit period for review
     */
    public function submitForReview(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'calculated') {
            return redirect()
                ->back()
                ->with('error', 'Only calculated periods can be submitted for review.');
        }

        try {
            $period->submitForReview();

            return redirect()
                ->back()
                ->with('success', 'Payroll submitted for review successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors('error', $e->getMessage());
        }
    }

    /**
     * Approve payroll for the specified period
     */
    public function approve(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'under_review') {
            return redirect()
                ->back()
                ->with('error', 'Only periods under review can be approved.');
        }

        try {
            $period->approve(auth()->id());

            return redirect()
                ->back()
                ->with('success', 'Payroll period approved successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors('error', $e->getMessage());
        }
    }

    /**
     * Finalize payroll for the specified period
     */
    public function finalize(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status !== 'approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved periods can be finalized.');
        }

        try {
            $period->finalize(auth()->id());

            return redirect()
                ->back()
                ->with('success', 'Payroll period finalized successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors('error', $e->getMessage());
        }
    }
}
```

---

### Phase 3: Update Remaining Controllers

#### 3.1 Update PayrollCalculationController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`

**Key Changes:**
- Replace `getMockCalculations()` with `EmployeePayrollCalculation::query()`
- Replace `getMockPeriods()` with `PayrollPeriod::all()`
- Implement `store()` to create calculation record
- Implement `recalculate()` to update calculation
- Implement `approve()` to approve calculation

**Summary of Changes:**
```php
// Before
private function getMockCalculations(): array
{
    return [ [ 'id' => 1, ... ], ... ];
}

// After
public function index(Request $request)
{
    $calculations = EmployeePayrollCalculation::query()
        ->when($request->period_id, fn($q) => $q->byPayrollPeriod($request->period_id))
        ->when($request->status, fn($q) => $q->byStatus($request->status))
        ->with('payrollPeriod')
        ->latest()
        ->paginate(15);
}
```

#### 3.2 Update PayrollAdjustmentController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php`

**Key Changes:**
- Replace mock adjustments with `PayrollAdjustment::query()`
- Replace mock employees with `Employee::query()`
- Implement `store()` to create adjustment
- Implement `approve()` to approve adjustment
- Implement `reject()` to reject with reason

#### 3.3 Update PayrollReviewController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollReviewController.php`

**Key Changes:**
- Query actual `PayrollPeriod` record
- Query actual `EmployeePayrollCalculation` records for summary
- Query actual `PayrollException` records for exceptions
- Query actual `PayrollApprovalHistory` for workflow

---

## Implementation Checklist

### Phase 1: Models & Methods
- [ ] Fix `PayrollPeriod.php` date casts
- [ ] Add query scopes to `PayrollPeriod`
- [ ] Add status methods to `PayrollPeriod`
- [ ] Add scopes to `EmployeePayrollCalculation`
- [ ] Add scopes to `PayrollAdjustment`
- [ ] Verify no PHP compilation errors
- [ ] Test model relationships in tinker

### Phase 2: PayrollPeriodController
- [ ] Update controller with PayrollPeriod model
- [ ] Implement `index()` with filters
- [ ] Implement `store()` with validation
- [ ] Implement `update()` with validation
- [ ] Implement `destroy()` with check
- [ ] Implement `calculate()` method
- [ ] Implement `submitForReview()` method
- [ ] Implement `approve()` method
- [ ] Implement `finalize()` method

### Phase 3: Other Controllers
- [ ] Update `PayrollCalculationController`
- [ ] Update `PayrollAdjustmentController`
- [ ] Update `PayrollReviewController`
- [ ] Verify all crud operations work
- [ ] Test with frontend pages
- [ ] Verify data persists to database

---

## Testing Plan

### Unit Tests
- Test PayrollPeriod model scopes
- Test status transition methods
- Test calculation methods
- Test adjustment approval workflow

### Integration Tests
- Test controller store() methods persist to database
- Test filtering returns correct records
- Test approval workflow updates database
- Test frontend pages display real data

### Manual Testing Checklist
- [ ] Create new payroll period - data saved to DB
- [ ] Edit payroll period - changes persisted
- [ ] Filter by status - returns correct periods
- [ ] Filter by type - returns correct types
- [ ] Approve period - status changes to approved
- [ ] Create adjustment - saved and awaits approval
- [ ] Approve adjustment - status changes to applied
- [ ] Review page displays real calculation data
- [ ] Exception list shows actual errors
- [ ] No PHP errors in logs

---

## Database Integrity Checks

After implementation, verify:

```sql
-- Check payroll periods created
SELECT COUNT(*) FROM payroll_periods;

-- Check employee calculations created
SELECT COUNT(*) FROM employee_payroll_calculations;

-- Check adjustments created
SELECT COUNT(*) FROM payroll_adjustments;

-- Verify foreign keys work
SELECT * FROM payroll_periods WHERE id = 1;
SELECT * FROM employee_payroll_calculations WHERE payroll_period_id = 1;
```

---

## Rollback Plan

If issues occur:

1. **Git revert to before changes**
   ```bash
   git revert HEAD~[num_commits]
   ```

2. **Return to mock data** - Previous controller code still in git history

3. **Database rollback**
   ```bash
   php artisan migrate:rollback
   php artisan migrate
   ```

---

## Success Criteria

✅ All tasks completed when:
- All PHP models have no compilation errors
- All 4 controllers use Eloquent models, not mock data
- Frontend displays real data from database
- CRUD operations persist to database
- No console errors or warnings
- All relationships work correctly
- Filtering returns correct subset of data
- Approval workflows update database status

---

## Related Files

**Models:**
- `app/Models/PayrollPeriod.php`
- `app/Models/EmployeePayrollCalculation.php`
- `app/Models/PayrollAdjustment.php`
- `app/Models/PayrollApprovalHistory.php`
- `app/Models/PayrollException.php`

**Controllers:**
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollAdjustmentController.php`
- `app/Http/Controllers/Payroll/PayrollProcessing/PayrollReviewController.php`

**Migrations:**
- `database/migrations/2026_02_17_065500_create_payroll_periods_table.php`
- `database/migrations/2026_02_17_065510_create_employee_payroll_calculations_table.php`
- `database/migrations/2026_02_17_065520_create_payroll_adjustments_table.php`

**Frontend Pages:**
- `resources/js/pages/Payroll/PayrollProcessing/Periods/Index.tsx`
- `resources/js/pages/Payroll/PayrollProcessing/Calculations/Index.tsx`
- `resources/js/pages/Payroll/PayrollProcessing/Adjustments/Index.tsx`
- `resources/js/pages/Payroll/PayrollProcessing/Review/Index.tsx`

**Routes:**
- `routes/payroll.php` (lines 40-115)

---

## Notes

- All code examples use Laravel best practices
- Proper error handling and validation included
- Database relationships properly defined
- Query scopes for reusability
- Status transition methods prevent invalid states
- Helper methods for formatting and calculations
