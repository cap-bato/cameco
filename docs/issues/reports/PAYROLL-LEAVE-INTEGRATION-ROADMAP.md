# Payroll Module - Leave Integration Implementation Roadmap

**Issue:** Complete Payroll backend to consume Leave Management data  
**Status:** Planning  
**Priority:** MEDIUM (After Leave Management core fixes)  
**Created:** February 5, 2026  
**Dependencies:** Leave Management events system (from LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md)  
**Related Documents:**
- [LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md](./LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md)
- [LEAVE_MANAGEMENT_INTEGRATION_REPORT.md](../docs/LEAVE_MANAGEMENT_INTEGRATION_REPORT.md)

---

## Executive Summary

**Current State:** Payroll module has frontend pages and basic controllers, but lacks backend logic for:
- Leave-based deductions (unpaid leave)
- Attendance-based salary adjustments
- Integration with Leave Management events

**Goal:** Complete Payroll backend to automatically:
1. Deduct salary for unpaid leave days
2. Process leave-related payroll adjustments
3. Display leave deductions in payslips
4. Track leave impact on monthly salary

**Timeline:** 2-3 weeks (after Leave Management events implemented)

---

## Prerequisites (From Leave Integration Roadmap)

Before starting Payroll integration, these must be complete:

✅ **From Leave Management:**
- [ ] Events system created (`app/Events/HR/Leave/`)
- [ ] `LeaveRequestApproved` event dispatching
- [ ] `LeaveRequestCancelled` event dispatching
- [ ] Listener stub created (`app/Listeners/Payroll/CreateDeductionForUnpaidLeave.php`)

⏳ **Can Start Once:** Leave Management dispatches events (even if other listeners not complete)

---

## Phase 1: Database Schema (Week 1) 🗄️

### Task 1.1: Add Leave Integration to Payroll Deductions

**Objective:** Link payroll deductions to leave requests

#### Subtask 1.1.1: Create Migration for leave_request_id
**File:** `database/migrations/YYYY_MM_DD_add_leave_request_id_to_payroll_deductions.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            // Add leave request link
            $table->foreignId('leave_request_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('leave_requests')
                ->nullOnDelete();
            
            // Add metadata for leave deductions
            $table->integer('leave_days')
                ->nullable()
                ->after('amount')
                ->comment('Number of unpaid leave days');
            
            $table->date('leave_start_date')
                ->nullable()
                ->after('leave_days');
            
            $table->date('leave_end_date')
                ->nullable()
                ->after('leave_start_date');
            
            // Index for performance
            $table->index('leave_request_id');
            $table->index(['employee_id', 'payroll_period_id', 'deduction_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
            $table->dropColumn([
                'leave_request_id',
                'leave_days',
                'leave_start_date',
                'leave_end_date'
            ]);
        });
    }
};
```

#### Subtask 1.1.2: Create payroll_deductions Table (If Not Exists)
**File:** `database/migrations/YYYY_MM_DD_create_payroll_deductions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            
            // Deduction details
            $table->string('deduction_type', 50); // 'unpaid_leave', 'late', 'undertime', 'advance', 'loan', 'sss', 'philhealth', 'pagibig', 'tax'
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Leave-specific fields
            $table->integer('leave_days')->nullable();
            $table->date('leave_start_date')->nullable();
            $table->date('leave_end_date')->nullable();
            
            // Processing status
            $table->enum('status', ['pending', 'processed', 'cancelled'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index('deduction_type');
            $table->index('status');
            $table->index('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};
```

#### Subtask 1.1.3: Run Migrations
```bash
php artisan migrate
```

---

## Phase 2: Models & Relationships (Week 1) 📦

### Task 2.1: Create/Update PayrollDeduction Model

**Objective:** Eloquent model with relationships to Leave and Employee

#### Subtask 2.1.1: Create PayrollDeduction Model
**File:** `app/Models/PayrollDeduction.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDeduction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'leave_request_id',
        'deduction_type',
        'amount',
        'description',
        'notes',
        'leave_days',
        'leave_start_date',
        'leave_end_date',
        'status',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'leave_days' => 'integer',
        'leave_start_date' => 'date',
        'leave_end_date' => 'date',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeLeaveDeductions($query)
    {
        return $query->where('deduction_type', 'unpaid_leave');
    }

    // Helper methods
    public function isLeaveDeduction(): bool
    {
        return $this->deduction_type === 'unpaid_leave';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markProcessed(int $userId): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processed_by' => $userId,
        ]);
    }
}
```

#### Subtask 2.1.2: Update LeaveRequest Model
**File:** `app/Models/LeaveRequest.php`

Add relationship to PayrollDeduction:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add this method to LeaveRequest model
public function payrollDeductions(): HasMany
{
    return $this->hasMany(PayrollDeduction::class);
}

// Helper to check if deduction created
public function hasPayrollDeduction(): bool
{
    return $this->payrollDeductions()->exists();
}

// Get total deduction amount
public function getTotalDeductionAmount(): float
{
    return $this->payrollDeductions()
        ->where('status', '!=', 'cancelled')
        ->sum('amount');
}
```

#### Subtask 2.1.3: Update Employee Model
**File:** `app/Models/Employee.php`

Add relationship:

```php
public function payrollDeductions(): HasMany
{
    return $this->hasMany(PayrollDeduction::class);
}

// Get pending deductions for next payroll
public function getPendingDeductions()
{
    return $this->payrollDeductions()
        ->pending()
        ->whereNull('payroll_period_id')
        ->get();
}
```

---

## Phase 3: Core Services (Week 1-2) 🔧

### Task 3.1: Create LeaveDeductionService

**Objective:** Calculate unpaid leave deductions based on employee salary

#### Subtask 3.1.1: Create Service File
**File:** `app/Services/Payroll/LeaveDeductionService.php`

```php
<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollDeduction;
use App\Models\PayrollPeriod;
use Carbon\Carbon;

class LeaveDeductionService
{
    /**
     * Calculate deduction amount for unpaid leave
     */
    public function calculateDeduction(LeaveRequest $leaveRequest): float
    {
        $employee = $leaveRequest->employee;
        $leaveDays = $leaveRequest->days_requested;
        
        // Get daily salary rate
        $dailyRate = $this->calculateDailyRate($employee);
        
        // Calculate total deduction
        $deductionAmount = $dailyRate * $leaveDays;
        
        return round($deductionAmount, 2);
    }

    /**
     * Calculate daily salary rate based on pay frequency
     * 
     * Philippine standard:
     * - Monthly: Divide by 26 working days
     * - Semi-monthly: Divide by 13 working days per period
     */
    protected function calculateDailyRate(Employee $employee): float
    {
        $basicSalary = $employee->basic_salary;
        
        // Get pay frequency from system settings or employee record
        $payFrequency = $this->getPayFrequency($employee);
        
        return match($payFrequency) {
            'monthly' => $basicSalary / 26, // 26 working days per month
            'semi-monthly' => $basicSalary / 13, // 13 working days per pay period
            'weekly' => $basicSalary / 6, // 6 working days per week
            'daily' => $basicSalary, // Already daily rate
            default => $basicSalary / 26, // Default to monthly
        };
    }

    /**
     * Get pay frequency for employee
     */
    protected function getPayFrequency(Employee $employee): string
    {
        // TODO: Get from employee record or system settings
        // For now, default to semi-monthly (Philippine standard)
        return 'semi-monthly';
    }

    /**
     * Create payroll deduction record for unpaid leave
     */
    public function createDeduction(LeaveRequest $leaveRequest): PayrollDeduction
    {
        $amount = $this->calculateDeduction($leaveRequest);
        $employee = $leaveRequest->employee;
        
        // Get next payroll period (or leave null if not yet created)
        $nextPeriod = $this->getNextPayrollPeriod($leaveRequest->end_date);
        
        $deduction = PayrollDeduction::create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $nextPeriod?->id,
            'leave_request_id' => $leaveRequest->id,
            'deduction_type' => 'unpaid_leave',
            'amount' => $amount,
            'description' => "Unpaid {$leaveRequest->leavePolicy->name}",
            'notes' => "Leave from {$leaveRequest->start_date->format('M d, Y')} to {$leaveRequest->end_date->format('M d, Y')}",
            'leave_days' => $leaveRequest->days_requested,
            'leave_start_date' => $leaveRequest->start_date,
            'leave_end_date' => $leaveRequest->end_date,
            'status' => 'pending',
        ]);
        
        return $deduction;
    }

    /**
     * Get next payroll period after leave end date
     */
    protected function getNextPayrollPeriod(Carbon $leaveEndDate): ?PayrollPeriod
    {
        return PayrollPeriod::where('period_start', '>=', $leaveEndDate)
            ->where('status', '!=', 'closed')
            ->orderBy('period_start')
            ->first();
    }

    /**
     * Cancel deduction (if leave request cancelled)
     */
    public function cancelDeduction(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->payrollDeductions()
            ->pending()
            ->update([
                'status' => 'cancelled',
                'notes' => 'Leave request cancelled by ' . auth()->user()->name,
            ]);
    }

    /**
     * Check if leave should trigger deduction
     */
    public function shouldCreateDeduction(LeaveRequest $leaveRequest): bool
    {
        // Only create deduction for unpaid leave
        if ($leaveRequest->leavePolicy->is_paid) {
            return false;
        }

        // Only create for approved leaves
        if ($leaveRequest->status !== 'approved') {
            return false;
        }

        // Don't create duplicate deductions
        if ($leaveRequest->hasPayrollDeduction()) {
            return false;
        }

        return true;
    }
}
```

### Task 3.2: Create PayrollCalculationService Enhancement

**Objective:** Integrate leave deductions into payroll calculation

#### Subtask 3.2.1: Update PayrollCalculationService
**File:** `app/Services/Payroll/PayrollCalculationService.php`

```php
<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollDeduction;

class PayrollCalculationService
{
    public function __construct(
        protected LeaveDeductionService $leaveDeductionService
    ) {}

    /**
     * Calculate net salary for employee in period
     */
    public function calculateNetSalary(Employee $employee, PayrollPeriod $period): array
    {
        // Get basic salary
        $basicSalary = $employee->basic_salary;
        
        // Get all allowances (existing logic)
        $allowances = $this->calculateAllowances($employee, $period);
        
        // Get all deductions including leave
        $deductions = $this->calculateDeductions($employee, $period);
        
        // Calculate gross pay
        $grossPay = $basicSalary + $allowances['total'];
        
        // Calculate net pay
        $netPay = $grossPay - $deductions['total'];
        
        return [
            'basic_salary' => $basicSalary,
            'allowances' => $allowances,
            'gross_pay' => $grossPay,
            'deductions' => $deductions,
            'net_pay' => $netPay,
        ];
    }

    /**
     * Calculate all deductions including leave
     */
    protected function calculateDeductions(Employee $employee, PayrollPeriod $period): array
    {
        $deductions = [
            'unpaid_leave' => 0,
            'late' => 0,
            'undertime' => 0,
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'tax' => 0,
            'loans' => 0,
            'advances' => 0,
            'others' => 0,
        ];

        // Get all deductions for this period
        $payrollDeductions = PayrollDeduction::where('employee_id', $employee->id)
            ->where(function($query) use ($period) {
                $query->where('payroll_period_id', $period->id)
                    ->orWhere(function($q) use ($period) {
                        // Include pending deductions that fall within period
                        $q->whereNull('payroll_period_id')
                          ->where('status', 'pending')
                          ->whereBetween('leave_start_date', [
                              $period->period_start,
                              $period->period_end
                          ]);
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->get();

        // Group by deduction type
        foreach ($payrollDeductions as $deduction) {
            $type = $deduction->deduction_type;
            
            if (isset($deductions[$type])) {
                $deductions[$type] += $deduction->amount;
            } else {
                $deductions['others'] += $deduction->amount;
            }
        }

        // Calculate total
        $deductions['total'] = array_sum($deductions);

        return $deductions;
    }

    /**
     * Calculate allowances (existing logic)
     */
    protected function calculateAllowances(Employee $employee, PayrollPeriod $period): array
    {
        // TODO: Implement allowances calculation
        return [
            'rice' => 1500,
            'transportation' => 1000,
            'communication' => 500,
            'total' => 3000,
        ];
    }
}
```

---

## Phase 4: Event Listener Implementation (Week 2) 🎧

### Task 4.1: Implement CreateDeductionForUnpaidLeave Listener

**Objective:** Listen to LeaveRequestApproved event and create deduction

#### Subtask 4.1.1: Complete Listener Implementation
**File:** `app/Listeners/Payroll/CreateDeductionForUnpaidLeave.php`

```php
<?php

namespace App\Listeners\Payroll;

use App\Events\HR\Leave\LeaveRequestApproved;
use App\Services\Payroll\LeaveDeductionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateDeductionForUnpaidLeave implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected LeaveDeductionService $deductionService
    ) {}

    /**
     * Handle the event
     */
    public function handle(LeaveRequestApproved $event): void
    {
        $leaveRequest = $event->leaveRequest;

        // Check if should create deduction
        if (!$this->deductionService->shouldCreateDeduction($leaveRequest)) {
            Log::info('Leave deduction not needed', [
                'leave_request_id' => $leaveRequest->id,
                'reason' => $leaveRequest->leavePolicy->is_paid ? 'paid_leave' : 'other',
            ]);
            return;
        }

        try {
            // Create deduction
            $deduction = $this->deductionService->createDeduction($leaveRequest);

            Log::info('Leave deduction created', [
                'leave_request_id' => $leaveRequest->id,
                'deduction_id' => $deduction->id,
                'amount' => $deduction->amount,
                'employee_id' => $leaveRequest->employee_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create leave deduction', [
                'leave_request_id' => $leaveRequest->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to retry (since we implement ShouldQueue)
            throw $e;
        }
    }

    /**
     * Handle failed job
     */
    public function failed(LeaveRequestApproved $event, \Throwable $exception): void
    {
        Log::error('Leave deduction creation failed permanently', [
            'leave_request_id' => $event->leaveRequest->id,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Send notification to payroll officer
    }
}
```

### Task 4.2: Create Cancellation Listener

**Objective:** Remove deduction when leave cancelled

#### Subtask 4.2.1: Create RemoveDeductionForCancelledLeave Listener
**File:** `app/Listeners/Payroll/RemoveDeductionForCancelledLeave.php`

```php
<?php

namespace App\Listeners\Payroll;

use App\Events\HR\Leave\LeaveRequestCancelled;
use App\Services\Payroll\LeaveDeductionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RemoveDeductionForCancelledLeave implements ShouldQueue
{
    public function __construct(
        protected LeaveDeductionService $deductionService
    ) {}

    public function handle(LeaveRequestCancelled $event): void
    {
        $leaveRequest = $event->leaveRequest;

        if (!$leaveRequest->hasPayrollDeduction()) {
            return; // No deduction to cancel
        }

        try {
            $this->deductionService->cancelDeduction($leaveRequest);

            Log::info('Leave deduction cancelled', [
                'leave_request_id' => $leaveRequest->id,
                'cancelled_by' => $event->cancelledBy,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel leave deduction', [
                'leave_request_id' => $leaveRequest->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

### Task 4.3: Register Listeners

**Objective:** Register listeners in EventServiceProvider

#### Subtask 4.3.1: Update EventServiceProvider
**File:** `app/Providers/EventServiceProvider.php`

```php
use App\Events\HR\Leave\{LeaveRequestApproved, LeaveRequestCancelled};
use App\Listeners\Payroll\{
    CreateDeductionForUnpaidLeave,
    RemoveDeductionForCancelledLeave
};

protected $listen = [
    LeaveRequestApproved::class => [
        CreateDeductionForUnpaidLeave::class, // ✅ Activate this
    ],
    LeaveRequestCancelled::class => [
        RemoveDeductionForCancelledLeave::class, // ✅ Add this
    ],
];
```

---

## Phase 5: Controller & API Updates (Week 2) 🎮

### Task 5.1: Update PayrollCalculationController

**Objective:** Use new calculation service with leave deductions

#### Subtask 5.1.1: Update calculate() Method
**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`

```php
use App\Services\Payroll\PayrollCalculationService;

public function calculate(Request $request)
{
    $periodId = $request->input('period_id');
    $period = PayrollPeriod::findOrFail($periodId);

    $calculationService = app(PayrollCalculationService::class);

    // Get all employees for calculation
    $employees = Employee::active()->get();

    $calculations = [];
    foreach ($employees as $employee) {
        $calculations[] = [
            'employee' => $employee,
            'calculation' => $calculationService->calculateNetSalary($employee, $period),
        ];
    }

    return Inertia::render('Payroll/PayrollProcessing/Calculations/Index', [
        'period' => $period,
        'calculations' => $calculations,
    ]);
}
```

### Task 5.2: Create DeductionsController

**Objective:** Manage payroll deductions (view, edit, approve)

#### Subtask 5.2.1: Create Controller
**File:** `app/Http/Controllers/Payroll/DeductionsController.php`

```php
<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollDeduction;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeductionsController extends Controller
{
    /**
     * Display deductions for a period
     */
    public function index(Request $request): Response
    {
        $periodId = $request->input('period_id');
        $type = $request->input('type', 'all');

        $query = PayrollDeduction::with(['employee.profile', 'leaveRequest.leavePolicy', 'payrollPeriod'])
            ->when($periodId, fn($q) => $q->forPeriod($periodId))
            ->when($type !== 'all', fn($q) => $q->where('deduction_type', $type));

        $deductions = $query->latest()->paginate(50);

        $periods = PayrollPeriod::orderByDesc('period_start')->get();

        return Inertia::render('Payroll/Deductions/Index', [
            'deductions' => $deductions,
            'periods' => $periods,
            'filters' => [
                'period_id' => $periodId,
                'type' => $type,
            ],
        ]);
    }

    /**
     * Display leave-based deductions
     */
    public function leaveDeductions(): Response
    {
        $deductions = PayrollDeduction::with(['employee.profile', 'leaveRequest.leavePolicy'])
            ->leaveDeductions()
            ->pending()
            ->latest()
            ->paginate(50);

        return Inertia::render('Payroll/Deductions/LeaveDeductions', [
            'deductions' => $deductions,
        ]);
    }

    /**
     * Approve pending deduction
     */
    public function approve(int $id)
    {
        $deduction = PayrollDeduction::findOrFail($id);

        $this->authorize('approve', $deduction);

        $deduction->markProcessed(auth()->id());

        return back()->with('success', 'Deduction approved successfully.');
    }

    /**
     * Cancel pending deduction
     */
    public function cancel(int $id, Request $request)
    {
        $deduction = PayrollDeduction::findOrFail($id);

        $this->authorize('cancel', $deduction);

        $deduction->update([
            'status' => 'cancelled',
            'notes' => $request->input('reason', 'Cancelled by payroll officer'),
        ]);

        return back()->with('success', 'Deduction cancelled.');
    }
}
```

### Task 5.3: Add Routes

**Objective:** Register payroll deduction routes

#### Subtask 5.3.1: Update routes/payroll.php
**File:** `routes/payroll.php`

```php
use App\Http\Controllers\Payroll\DeductionsController;

Route::prefix('payroll')->middleware(['auth', 'verified', EnsurePayrollOfficer::class])->group(function () {
    Route::name('payroll.')->group(function () {
        
        // Deductions Management
        Route::prefix('deductions')->name('deductions.')->group(function () {
            Route::get('/', [DeductionsController::class, 'index'])->name('index');
            Route::get('/leave', [DeductionsController::class, 'leaveDeductions'])->name('leave');
            Route::post('/{id}/approve', [DeductionsController::class, 'approve'])->name('approve');
            Route::post('/{id}/cancel', [DeductionsController::class, 'cancel'])->name('cancel');
        });

        // ... existing routes ...
    });
});
```

---

## Phase 6: Frontend Integration (Week 2-3) 🎨

### Task 6.1: Create Deductions Management Page

**Objective:** Display and manage leave deductions

#### Subtask 6.1.1: Create Deductions Index Page
**File:** `resources/js/pages/Payroll/Deductions/Index.tsx`

```tsx
import React from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '@/components/app-shell';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatCurrency, formatDate } from '@/lib/utils';

interface Deduction {
    id: number;
    employee: {
        id: number;
        employee_number: string;
        profile: {
            full_name: string;
        };
    };
    deduction_type: string;
    amount: number;
    description: string;
    leave_days?: number;
    leave_start_date?: string;
    leave_end_date?: string;
    status: 'pending' | 'processed' | 'cancelled';
    leave_request?: {
        id: number;
        leave_policy: {
            name: string;
        };
    };
}

export default function DeductionsIndex({ deductions, periods, filters }: any) {
    return (
        <AppShell>
            <Head title="Payroll Deductions" />
            
            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">Payroll Deductions</h1>
                    <p className="text-muted-foreground">
                        Manage leave-based and other deductions
                    </p>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* TODO: Add period selector, type filter */}
                    </CardContent>
                </Card>

                {/* Deductions Table */}
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-4">Employee</th>
                                    <th className="text-left p-4">Type</th>
                                    <th className="text-left p-4">Description</th>
                                    <th className="text-right p-4">Amount</th>
                                    <th className="text-center p-4">Status</th>
                                    <th className="text-center p-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {deductions.data.map((deduction: Deduction) => (
                                    <tr key={deduction.id} className="border-b hover:bg-muted/50">
                                        <td className="p-4">
                                            <div>
                                                <div className="font-medium">
                                                    {deduction.employee.profile.full_name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {deduction.employee.employee_number}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="p-4">
                                            <Badge variant={deduction.deduction_type === 'unpaid_leave' ? 'secondary' : 'outline'}>
                                                {deduction.deduction_type.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td className="p-4">
                                            <div>
                                                <div>{deduction.description}</div>
                                                {deduction.leave_days && (
                                                    <div className="text-sm text-muted-foreground">
                                                        {deduction.leave_days} days ({formatDate(deduction.leave_start_date!)} - {formatDate(deduction.leave_end_date!)})
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="p-4 text-right font-medium">
                                            {formatCurrency(deduction.amount)}
                                        </td>
                                        <td className="p-4 text-center">
                                            <Badge variant={
                                                deduction.status === 'processed' ? 'default' :
                                                deduction.status === 'pending' ? 'secondary' :
                                                'destructive'
                                            }>
                                                {deduction.status}
                                            </Badge>
                                        </td>
                                        <td className="p-4 text-center">
                                            {deduction.status === 'pending' && (
                                                <div className="flex gap-2 justify-center">
                                                    <Button size="sm" variant="default">
                                                        Approve
                                                    </Button>
                                                    <Button size="sm" variant="outline">
                                                        Cancel
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppShell>
    );
}
```

### Task 6.2: Update Payslip Display

**Objective:** Show leave deductions in payslip

#### Subtask 6.2.1: Update Payslip Component
**File:** `resources/js/components/payroll/payslip-preview.tsx`

Add leave deductions section:

```tsx
// In deductions section
<div className="space-y-2">
    <h3 className="font-semibold">Deductions</h3>
    
    {/* Leave Deductions */}
    {payslip.deductions.unpaid_leave > 0 && (
        <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">
                Unpaid Leave ({payslip.leave_days} days)
            </span>
            <span className="text-red-600">
                -{formatCurrency(payslip.deductions.unpaid_leave)}
            </span>
        </div>
    )}
    
    {/* Other deductions... */}
</div>
```

---

## Phase 7: Testing & Validation (Week 3) 🧪

### Task 7.1: Unit Tests

#### Subtask 7.1.1: Test LeaveDeductionService
**File:** `tests/Unit/Services/LeaveDeductionServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeavePolicy;
use App\Services\Payroll\LeaveDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeaveDeductionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeaveDeductionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaveDeductionService::class);
    }

    /** @test */
    public function it_calculates_deduction_for_monthly_employee()
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 26000, // Monthly salary
        ]);

        $leavePolicy = LeavePolicy::factory()->create([
            'is_paid' => false,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_policy_id' => $leavePolicy->id,
            'days_requested' => 3,
            'status' => 'approved',
        ]);

        // Daily rate = 26000 / 26 = 1000
        // 3 days = 3000
        $deduction = $this->service->calculateDeduction($leaveRequest);

        $this->assertEquals(3000.00, $deduction);
    }

    /** @test */
    public function it_creates_deduction_record()
    {
        $employee = Employee::factory()->create(['basic_salary' => 26000]);
        $leavePolicy = LeavePolicy::factory()->create(['is_paid' => false]);
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_policy_id' => $leavePolicy->id,
            'days_requested' => 2,
        ]);

        $deduction = $this->service->createDeduction($leaveRequest);

        $this->assertDatabaseHas('payroll_deductions', [
            'employee_id' => $employee->id,
            'leave_request_id' => $leaveRequest->id,
            'deduction_type' => 'unpaid_leave',
            'amount' => 2000.00,
        ]);
    }

    /** @test */
    public function it_does_not_create_deduction_for_paid_leave()
    {
        $leavePolicy = LeavePolicy::factory()->create(['is_paid' => true]);
        $leaveRequest = LeaveRequest::factory()->create([
            'leave_policy_id' => $leavePolicy->id,
        ]);

        $shouldCreate = $this->service->shouldCreateDeduction($leaveRequest);

        $this->assertFalse($shouldCreate);
    }
}
```

### Task 7.2: Integration Tests

#### Subtask 7.2.1: Test Event to Deduction Flow
**File:** `tests/Feature/Payroll/LeaveDeductionIntegrationTest.php`

```php
<?php

namespace Tests\Feature\Payroll;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeavePolicy;
use App\Models\PayrollDeduction;
use App\Events\HR\Leave\LeaveRequestApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeaveDeductionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function leave_approval_triggers_deduction_creation()
    {
        $employee = Employee::factory()->create(['basic_salary' => 26000]);
        $leavePolicy = LeavePolicy::factory()->create([
            'name' => 'Unpaid Leave',
            'is_paid' => false,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_policy_id' => $leavePolicy->id,
            'days_requested' => 5,
            'status' => 'pending',
        ]);

        // Dispatch event (simulating approval)
        event(new LeaveRequestApproved($leaveRequest, 'manager'));

        // Assert deduction created
        $this->assertDatabaseHas('payroll_deductions', [
            'employee_id' => $employee->id,
            'leave_request_id' => $leaveRequest->id,
            'deduction_type' => 'unpaid_leave',
            'leave_days' => 5,
        ]);

        $deduction = PayrollDeduction::where('leave_request_id', $leaveRequest->id)->first();
        $this->assertEquals(5000.00, $deduction->amount); // 5 days * 1000/day
    }

    /** @test */
    public function paid_leave_does_not_create_deduction()
    {
        $employee = Employee::factory()->create();
        $leavePolicy = LeavePolicy::factory()->create(['is_paid' => true]);

        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_policy_id' => $leavePolicy->id,
            'status' => 'approved',
        ]);

        event(new LeaveRequestApproved($leaveRequest, 'system'));

        $this->assertDatabaseMissing('payroll_deductions', [
            'leave_request_id' => $leaveRequest->id,
        ]);
    }
}
```

---

## Phase 8: Documentation & Deployment (Week 3) 📚

### Task 8.1: Update Documentation

#### Subtask 8.1.1: Document Payroll-Leave Integration
**File:** `docs/integrations/PAYROLL_LEAVE_INTEGRATION.md`

Create comprehensive documentation showing:
- How leave deductions are calculated
- Event flow diagram
- Database schema
- API endpoints
- Frontend components
- Testing examples

### Task 8.2: Create User Guide

#### Subtask 8.2.1: Payroll Officer Guide
**File:** `docs/user-guides/PAYROLL_OFFICER_LEAVE_DEDUCTIONS.md`

Document:
- How to view pending leave deductions
- How to approve/reject deductions
- How deductions appear in payslips
- How to handle edge cases (cancelled leaves, errors)

---

## Implementation Checklist

### Week 1: Database & Models ✅
- [ ] Create `payroll_deductions` migration
- [ ] Add `leave_request_id` column to deductions
- [ ] Create `PayrollDeduction` model
- [ ] Update `LeaveRequest` model with relationships
- [ ] Update `Employee` model with deduction methods
- [ ] Run migrations successfully

### Week 2: Services & Listeners ✅
- [ ] Create `LeaveDeductionService`
- [ ] Implement calculation logic (daily rate, deduction amount)
- [ ] Update `PayrollCalculationService`
- [ ] Complete `CreateDeductionForUnpaidLeave` listener
- [ ] Create `RemoveDeductionForCancelledLeave` listener
- [ ] Register listeners in `EventServiceProvider`
- [ ] Test event dispatching

### Week 3: Controllers & Frontend ✅
- [ ] Create `DeductionsController`
- [ ] Add deduction routes
- [ ] Create deductions index page
- [ ] Update payslip preview with leave deductions
- [ ] Write unit tests
- [ ] Write integration tests
- [ ] Update documentation

---

## Acceptance Criteria

### Must Have ✅
- [ ] Unpaid leave automatically creates payroll deduction
- [ ] Deduction amount calculated correctly (daily rate × days)
- [ ] Deduction linked to leave request and payroll period
- [ ] Cancelled leaves remove deduction
- [ ] Payslip displays leave deductions clearly
- [ ] Payroll officer can view pending deductions
- [ ] Integration tests pass (event → listener → deduction)

### Should Have 📋
- [ ] Payroll officer can approve/cancel deductions manually
- [ ] Leave deduction audit trail maintained
- [ ] Deductions grouped by type in payroll calculation
- [ ] Failed deduction jobs logged and retried
- [ ] Email notification to payroll officer for new deductions

### Nice to Have 🎁
- [ ] Bulk approve/reject deductions
- [ ] Deduction preview before approval
- [ ] Export deductions report
- [ ] Dashboard widget showing pending leave deductions

---

## Quick Start Guide

Once Leave Management events are ready:

**Day 1:**
```bash
# Create migrations
php artisan make:migration create_payroll_deductions_table
php artisan make:migration add_leave_request_id_to_payroll_deductions
php artisan migrate
```

**Day 2-3:**
```bash
# Create models and services
php artisan make:model PayrollDeduction
# Create service: app/Services/Payroll/LeaveDeductionService.php
# Update service: app/Services/Payroll/PayrollCalculationService.php
```

**Day 4-5:**
```bash
# Complete listeners
# File: app/Listeners/Payroll/CreateDeductionForUnpaidLeave.php
# Register in EventServiceProvider
php artisan queue:work # Start queue worker
```

**Day 6-7:**
```bash
# Create controllers and routes
php artisan make:controller Payroll/DeductionsController
# Add routes to routes/payroll.php
```

**Day 8-10:**
```bash
# Create frontend pages
# File: resources/js/pages/Payroll/Deductions/Index.tsx
# Update: resources/js/components/payroll/payslip-preview.tsx
```

**Day 11-15:**
```bash
# Write tests
php artisan make:test Services/LeaveDeductionServiceTest --unit
php artisan make:test Payroll/LeaveDeductionIntegrationTest
php artisan test
```

---

**Estimated Total Time:** 2-3 weeks (10-15 working days)

**Priority:** MEDIUM (start after Leave Management events system complete)

**Next Step:** Wait for `.aiplans/LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md` Phase 1-2 completion, then begin Phase 1 of this roadmap! 🚀
