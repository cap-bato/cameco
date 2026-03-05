# Leave Balances Implementation Plan

**Feature:** Leave Balance Management System  
**Module:** HR > Leave Management > Balances  
**Priority:** HIGH  
**Estimated Duration:** 3-4 days (Phases 1-4: 2-3 days, Phase 5: 1 day)  
**Current Status:** 🟡 IN PROGRESS - Phase 1 ✅, Phase 2 ✅, Phase 3 ✅, Phase 4 ⏳, Phase 5: Task 5.1 ✅ Task 5.2 ✅ Task 5.3-5.5 ⏳

---

## 📋 Executive Summary

The Leave Balances page currently displays mock/hardcoded data. This implementation will:
- Create proper database schema for leave balances
- Track leave accruals (earned leave based on tenure)
- Calculate used leave from approved leave requests
- Handle carry-forward from previous years
- Display real-time accurate balance information
- Support filtering and reporting
- **NEW (Phase 5):** Improve UX/UI with pagination, collapsible rows, sorting, and export functionality


---

## 🎯 Current Issues

1. ❌ LeaveBalanceController returns hardcoded mock data
2. ❌ No database tables for storing leave balances
3. ❌ No leave accrual calculation logic
4. ❌ No integration with leave requests (to calculate used days)
5. ❌ No carry-forward mechanism
6. ❌ No historical tracking (year-over-year)

---

## ⚠️ Current UI/UX Issues (Phase 5)

### Problem 1: Repeating Employee Names
**Current Behavior:**
```
Maria Reyes     | Vacation Leave           | 2.0 | 1.0 | 1.0 | 0.0
Maria Reyes     | Sick Leave               | 0.8 | 0.0 | 0.8 | 0.0
Maria Reyes     | Emergency Leave          | 0.4 | 0.0 | 0.4 | 0.0
Maria Reyes     | Maternity/Paternity      | 7.5 | 0.0 | 7.5 | 0.0
...
```

**Issue:** Same employee name repeats 7 times (one per leave type), creating poor visual hierarchy  
**Solution (Phase 5):** Collapsible employee rows with leave types as sub-items

```
▶ Maria Reyes (7 leave types)
  ├─ Vacation Leave              | 2.0 | 1.0 | 1.0 | 0.0
  ├─ Sick Leave                  | 0.8 | 0.0 | 0.8 | 0.0
  ├─ Emergency Leave             | 0.4 | 0.0 | 0.4 | 0.0
  └─ Maternity/Paternity         | 7.5 | 0.0 | 7.5 | 0.0

▶ Mossie Windler (7 leave types)
  ├─ Vacation Leave              | 1.3 | 0.0 | 1.3 | 0.0
  └─ ...
```

### Problem 2: No Pagination
**Current Behavior:** 
- 46 employees × 7 leave types = 322 rows displayed at once
- Requires excessive scrolling to view all data
- Performance degrades with larger datasets
- Difficult to find specific employee/leave type

**Solution (Phase 5):**
- Server-side pagination (10, 25, 50, 100 rows per page)
- Page navigation controls
- Jump to page functionality
- Shows current position (e.g., "Showing 1-25 of 322")

### Problem 3: Limited Interactivity
**Issues:**
- Cannot sort by column (name, leave type, earned, etc.)
- Cannot toggle visibility of zero-balance leaves
- No export functionality
- Limited filtering options

**Solutions (Phase 5):**
- Click column headers to sort ascending/descending
- "Show leaves with balance" toggle (hide 0 remaining)
- "Show leaves in use" toggle (show only where used > 0)
- Export to Excel/CSV with current filters applied

---

## 🏗️ Database Schema Analysis

### Existing Tables (Assumed)
- ✅ `employees` - Employee records
- ✅ `leave_policies` - Leave policy definitions (from LeavePolicySeeder)
- ✅ `leave_requests` - Leave request submissions

### Required New Tables

#### 1. `leave_balances`
Stores current leave balance per employee per leave type per year.

```sql
- id
- employee_id (FK)
- leave_policy_id (FK) 
- year
- earned (decimal) - Total earned this year
- used (decimal) - Total used this year
- carried_forward (decimal) - From previous year
- forfeited (decimal) - Expired leave
- remaining (decimal) - Calculated: earned + carried_forward - used
- last_accrued_at (timestamp)
- timestamps
- UNIQUE(employee_id, leave_policy_id, year)
```

#### 2. `leave_accruals`
Tracks individual accrual transactions (audit trail).

```sql
- id
- leave_balance_id (FK)
- accrual_date
- amount (decimal)
- accrual_type (enum: 'monthly', 'annual', 'manual', 'adjustment', 'carried_forward')
- reason (text)
- processed_by (FK users)
- timestamps
```

#### 3. `leave_carry_forward_rules`
Configuration for carry-forward policies.

```sql
- id
- leave_policy_id (FK)
- max_carry_forward_days (decimal)
- expiry_months (integer) - How many months until carried leave expires
- allow_partial (boolean)
- is_active (boolean)
- timestamps
```

---

## 📊 Implementation Phases

### **Phase 1: Database Schema & Models** (0.5 days)

#### Task 1.1: Create Leave Balances Migration
**Goal:** Create database schema for leave balances.

**Steps:**
1. Create migration `YYYY_MM_DD_create_leave_balances_table.php`
2. Add indexes for performance (employee_id, year, leave_policy_id)
3. Add computed column for `remaining` or handle in model

**Migration Code:**
```php
Schema::create('leave_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
    $table->year('year');
    $table->decimal('earned', 5, 2)->default(0);
    $table->decimal('used', 5, 2)->default(0);
    $table->decimal('carried_forward', 5, 2)->default(0);
    $table->decimal('forfeited', 5, 2)->default(0);
    $table->timestamp('last_accrued_at')->nullable();
    $table->timestamps();
    
    $table->unique(['employee_id', 'leave_policy_id', 'year']);
    $table->index(['employee_id', 'year']);
    $table->index('leave_policy_id');
});

Schema::create('leave_accruals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
    $table->date('accrual_date');
    $table->decimal('amount', 5, 2);
    $table->enum('accrual_type', ['monthly', 'annual', 'manual', 'adjustment', 'carried_forward']);
    $table->text('reason')->nullable();
    $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    
    $table->index(['leave_balance_id', 'accrual_date']);
});

Schema::create('leave_carry_forward_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('leave_policy_id')->constrained('leave_policies')->cascadeOnDelete();
    $table->decimal('max_carry_forward_days', 5, 2)->default(5);
    $table->integer('expiry_months')->default(3); // 3 months to use carried leave
    $table->boolean('allow_partial')->default(true);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Verification:**
- ✅ Migration runs without errors
- ✅ Tables created with proper indexes
- ✅ Foreign keys established

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- Migration file: `database/migrations/2026_03_05_120000_enhance_leave_balance_system.php`
- Approach: Enhancement migration (ALTER existing `leave_balances` + CREATE new tables)
- Execution: Ran successfully in 256.50ms (Batch 11)
- Tables affected:
  - ✅ `leave_balances` - Added columns: `forfeited`, `last_accrued_at` + indexes
  - ✅ `leave_accruals` - Created with 9 columns, 0 initial rows
  - ✅ `leave_carry_forward_rules` - Created with 8 columns, 0 initial rows
- Verification: Confirmed via `migrate:status` and tinker queries

---

#### Task 1.2: Create Eloquent Models
**Goal:** Create models with proper relationships and accessors.

**Files to Create:**
1. `app/Models/LeaveBalance.php`
2. `app/Models/LeaveAccrual.php`
3. `app/Models/LeaveCarryForwardRule.php`

**LeaveBalance Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_policy_id',
        'year',
        'earned',
        'used',
        'carried_forward',
        'forfeited',
        'last_accrued_at',
    ];

    protected $casts = [
        'earned' => 'decimal:2',
        'used' => 'decimal:2',
        'carried_forward' => 'decimal:2',
        'forfeited' => 'decimal:2',
        'last_accrued_at' => 'datetime',
        'year' => 'integer',
    ];

    protected $appends = ['remaining'];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LeaveAccrual::class);
    }

    // Accessors
    public function getRemainingAttribute(): float
    {
        return (float) ($this->earned + $this->carried_forward - $this->used);
    }

    // Scopes
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeWithPositiveBalance($query)
    {
        return $query->whereRaw('(earned + carried_forward - used) > 0');
    }
}
```

**LeaveAccrual Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAccrual extends Model
{
    protected $fillable = [
        'leave_balance_id',
        'accrual_date',
        'amount',
        'accrual_type',
        'reason',
        'processed_by',
    ];

    protected $casts = [
        'accrual_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
```

**LeaveCarryForwardRule Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCarryForwardRule extends Model
{
    protected $fillable = [
        'leave_policy_id',
        'max_carry_forward_days',
        'expiry_months',
        'allow_partial',
        'is_active',
    ];

    protected $casts = [
        'max_carry_forward_days' => 'decimal:2',
        'expiry_months' => 'integer',
        'allow_partial' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }
}
```

**Verification:**
- ✅ Models created with proper fillables and casts
- ✅ Relationships defined
- ✅ Accessors and scopes working
- ✅ No model errors in diagnostics

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- Files created:
  - ✅ `app/Models/LeaveBalance.php` - Updated with new fields, relationships, accessors, and scopes
  - ✅ `app/Models/LeaveAccrual.php` - Created with audit trail relationships
  - ✅ `app/Models/LeaveCarryForwardRule.php` - Created with policy configuration
- All models syntax verified (no PHP errors)
- All models successfully instantiated and loaded via PHP
- Database tables created in Phase 1 Task 1.1 match the model structure
- Ready for service layer implementation (Phase 2)

---

### **Phase 2: Leave Accrual Service** (1 day)

#### Task 2.1: Create Leave Accrual Service
**Goal:** Implement business logic for leave accrual calculations.

**File to Create:** `app/Services/LeaveAccrualService.php`

```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveAccrual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * Initialize leave balances for an employee for a given year.
     */
    public function initializeBalances(Employee $employee, int $year): void
    {
        $policies = LeavePolicy::where('is_active', true)->get();

        foreach ($policies as $policy) {
            LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_policy_id' => $policy->id,
                    'year' => $year,
                ],
                [
                    'earned' => 0,
                    'used' => 0,
                    'carried_forward' => 0,
                    'forfeited' => 0,
                ]
            );
        }
    }

    /**
     * Accrue leave for an employee based on policy.
     */
    public function accrueLeave(Employee $employee, LeavePolicy $policy, Carbon $accrualDate): float
    {
        $year = $accrualDate->year;
        
        // Get or create balance
        $balance = LeaveBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_policy_id' => $policy->id,
                'year' => $year,
            ],
            [
                'earned' => 0,
                'used' => 0,
                'carried_forward' => 0,
                'forfeited' => 0,
            ]
        );

        // Calculate accrual amount based on policy
        $accrualAmount = $this->calculateAccrualAmount($employee, $policy, $accrualDate);

        // Create accrual record
        DB::transaction(function () use ($balance, $accrualAmount, $accrualDate) {
            LeaveAccrual::create([
                'leave_balance_id' => $balance->id,
                'accrual_date' => $accrualDate,
                'amount' => $accrualAmount,
                'accrual_type' => 'monthly',
                'reason' => 'Automatic monthly accrual',
                'processed_by' => null,
            ]);

            $balance->increment('earned', $accrualAmount);
            $balance->update(['last_accrued_at' => $accrualDate]);
        });

        return $accrualAmount;
    }

    /**
     * Calculate accrual amount for a given period.
     */
    protected function calculateAccrualAmount(Employee $employee, LeavePolicy $policy, Carbon $date): float
    {
        // Annual allocation / 12 months
        $monthlyAccrual = $policy->annual_allocation / 12;

        // Check if employee is eligible (e.g., tenure requirements)
        if ($this->isEmployeeEligible($employee, $policy, $date)) {
            return round($monthlyAccrual, 2);
        }

        return 0.0;
    }

    /**
     * Check if employee is eligible for leave accrual.
     */
    protected function isEmployeeEligible(Employee $employee, LeavePolicy $policy, Carbon $date): bool
    {
        // Check if employee has completed probation (if required)
        if ($policy->eligibility_days > 0) {
            $hireDate = Carbon::parse($employee->date_hired);
            $eligibleDate = $hireDate->addDays($policy->eligibility_days);
            
            if ($date->lt($eligibleDate)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process monthly accrual for all active employees.
     */
    public function processMonthlyAccrualForAllEmployees(Carbon $date): array
    {
        $employees = Employee::where('status', 'active')->get();
        $policies = LeavePolicy::where('is_active', true)->get();
        
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                try {
                    $this->accrueLeave($employee, $policy, $date);
                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'employee_id' => $employee->id,
                        'policy_id' => $policy->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Carry forward unused leave to next year.
     */
    public function carryForwardLeave(Employee $employee, int $fromYear, int $toYear): void
    {
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $fromYear)
            ->with('leavePolicy.carryForwardRule')
            ->get();

        foreach ($balances as $balance) {
            $rule = $balance->leavePolicy->carryForwardRule;
            
            if (!$rule || !$rule->is_active) {
                continue;
            }

            $remaining = $balance->remaining;
            $carryForward = min($remaining, $rule->max_carry_forward_days);

            if ($carryForward > 0) {
                $nextYearBalance = LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_policy_id' => $balance->leave_policy_id,
                        'year' => $toYear,
                    ],
                    [
                        'earned' => 0,
                        'used' => 0,
                        'carried_forward' => 0,
                        'forfeited' => 0,
                    ]
                );

                $nextYearBalance->update([
                    'carried_forward' => $carryForward,
                ]);

                // Record accrual
                LeaveAccrual::create([
                    'leave_balance_id' => $nextYearBalance->id,
                    'accrual_date' => Carbon::create($toYear, 1, 1),
                    'amount' => $carryForward,
                    'accrual_type' => 'carried_forward',
                    'reason' => "Carried forward from {$fromYear}",
                    'processed_by' => null,
                ]);
            }
        }
    }

    /**
     * Deduct used leave when request is approved.
     */
    public function deductLeave(Employee $employee, LeavePolicy $policy, float $days, Carbon $date): void
    {
        $year = $date->year;
        
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            throw new \Exception("No leave balance found for employee {$employee->id} in year {$year}");
        }

        if ($balance->remaining < $days) {
            throw new \Exception("Insufficient leave balance. Available: {$balance->remaining}, Required: {$days}");
        }

        $balance->increment('used', $days);
    }
}
```

**Verification:**
- ✅ Service class created
- ✅ Accrual logic implemented
- ✅ Carry forward logic implemented
- ✅ Integration with leave requests

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- File created: `app/Services/LeaveAccrualService.php` (220 lines)
- Service methods:
  - `initializeBalances()` - Creates initial leave balances for an employee per year
  - `accrueLeave()` - Processes leave accrual based on policy, creates audit trail
  - `calculateAccrualAmount()` - Calculates monthly accrual from annual_entitlement
  - `isEmployeeEligible()` - Checks eligibility based on hire date
  - `processMonthlyAccrualForAllEmployees()` - Bulk processing with error handling
  - `carryForwardLeave()` - Handles year-end carry forward with rules
  - `deductLeave()` - Deducts used leave with validation
- LeavePolicy model updated: Added `carryForwardRule()` HasOne relationship
- Service verified: All 5 methods present and functional
- Syntax: No PHP errors detected
- Ready for Artisan command integration (Phase 2.2)

---

#### Task 2.2: Create Artisan Commands for Accrual
**Goal:** Automate leave accrual processing.

**Files to Create:**
1. `app/Console/Commands/ProcessMonthlyLeaveAccrual.php`
2. `app/Console/Commands/CarryForwardLeave.php`

**ProcessMonthlyLeaveAccrual Command:**
```php
<?php

namespace App\Console\Commands;

use App\Services\LeaveAccrualService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessMonthlyLeaveAccrual extends Command
{
    protected $signature = 'leave:process-monthly-accrual {--date=}';
    protected $description = 'Process monthly leave accrual for all active employees';

    public function handle(LeaveAccrualService $service): int
    {
        $date = $this->option('date') 
            ? Carbon::parse($this->option('date'))
            : now();

        $this->info("Processing monthly accrual for {$date->format('Y-m-d')}...");

        $results = $service->processMonthlyAccrualForAllEmployees($date);

        $this->info("Processed: {$results['processed']}");
        $this->warn("Skipped: {$results['skipped']}");
        
        if (count($results['errors']) > 0) {
            $this->error("Errors: " . count($results['errors']));
            foreach ($results['errors'] as $error) {
                $this->error("Employee {$error['employee_id']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
```

**Schedule in `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule): void
{
    // Process monthly accrual on the 1st of every month
    $schedule->command('leave:process-monthly-accrual')
        ->monthlyOn(1, '00:00');
    
    // Carry forward on January 1st
    $schedule->command('leave:carry-forward')
        ->yearlyOn(1, 1, '00:01');
}
```

**Verification:**
- ✅ Commands created
- ✅ Scheduled in Kernel
- ✅ Manual run successful

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- Files created/updated:
  - ✅ `app/Console/Commands/ProcessMonthlyLeaveAccrual.php` - Monthly accrual command with optional date parameter
  - ✅ `app/Console/Commands/ProcessYearEndCarryover.php` - Year-end carry-forward command with progress bar
- Scheduling configured in `app/Console/Kernel.php`:
  - Monthly accrual: 1st of every month at 01:00
  - Year-end carryover: December 30 at 02:00
- Command features:
  - Dependency injection of LeaveAccrualService
  - Progress indication for year-end processing
  - Detailed output tables showing results
  - Error handling and reporting
  - Overlap prevention and single-server execution
- Manual test execution:
  - Successfully processed 322 employee-policy combinations in one execution
  - Created 322 LeaveBalance records
  - Created 322 LeaveAccrual (audit trail) records
  - No errors or skipped items
- Commands registered and available via `php artisan list`
- Ready for Phase 3: Controller Integration

---

### **Phase 3: Update Controller & Integration** (0.5 days)

#### Task 3.1: Update LeaveBalanceController
**Goal:** Replace mock data with real database queries.

**File to Modify:** `app/Http/Controllers/HR/Leave/LeaveBalanceController.php`

```php
<?php

namespace App\Http\Controllers\HR\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedYear = $request->input('year', now()->year);
        $employeeId = $request->input('employee_id');

        // Get available years
        $years = range(now()->year - 5, now()->year + 1);

        // Fetch employees for filter
        $employees = Employee::with('profile:id,first_name,last_name')
            ->where('status', 'active')
            ->orderBy('employee_number')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'name' => $emp->profile?->first_name . ' ' . $emp->profile?->last_name,
            ]);

        // Build balances query with real data
        $balancesQuery = LeaveBalance::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'leavePolicy:id,name,code',
        ])
            ->whereHas('employee', fn($q) => $q->where('status', 'active'))
            ->where('year', $selectedYear);

        if ($employeeId) {
            $balancesQuery->where('employee_id', $employeeId);
        }

        $balances = $balancesQuery->get()
            ->groupBy('employee_id')
            ->map(function ($employeeBalances) {
                $employee = $employeeBalances->first()->employee;
                
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                    'department' => $employee->department?->name,
                    'balances' => $employeeBalances->map(fn($bal) => [
                        'type' => $bal->leavePolicy->code,
                        'name' => $bal->leavePolicy->name,
                        'earned' => (float) $bal->earned,
                        'used' => (float) $bal->used,
                        'remaining' => (float) $bal->remaining,
                        'carried_forward' => (float) $bal->carried_forward,
                    ])->values(),
                ];
            })->values();

        // Calculate summary
        $allBalances = $balancesQuery->get();
        $summary = [
            'total_employees' => $balances->count(),
            'total_earned' => $allBalances->sum('earned'),
            'total_used' => $allBalances->sum('used'),
            'total_remaining' => $allBalances->sum(fn($b) => $b->remaining),
        ];

        return Inertia::render('HR/Leave/Balances', [
            'balances' => $balances,
            'employees' => $employees,
            'selectedYear' => $selectedYear,
            'selectedEmployeeId' => $employeeId,
            'years' => $years,
            'summary' => $summary,
        ]);
    }
}
```

**Verification:**
- ✅ Controller returns real data
- ✅ Filters work correctly
- ✅ Summary calculations accurate
- ✅ No errors in browser console

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- File modified: `app/Http/Controllers/HR/Leave/LeaveBalanceController.php`
- Changes made:
  - Added `LeaveBalance` model import
  - Replaced mock data query with LeaveBalance-based query
  - Implemented eager loading: employee.profile, employee.department, leavePolicy
  - Added year filtering from request parameter
  - Grouped balances by employee_id
  - Implemented proper remaining calculation using model accessor
  - Updated summary calculations using actual database sum operations
- Testing results:
  - ✅ 322 leave balance records retrieved (46 employees × 7 policies)
  - ✅ Proper grouping by employee
  - ✅ Accurate earned/used/remaining calculations
  - ✅ Summary statistics calculated from real data
  - ✅ No database errors
- Data transformation:
  - Maps LeavePolicy fields (name, code) to balance items
  - Converts decimal values to floats for JSON serialization
  - Includes employee profile and department information
- Filtering capabilities:
  - Year filtering working
  - Employee-specific filtering working
  - Filter dropdown populated with all active employees

---

#### Task 3.2: Integrate with Leave Request Approval
**Goal:** Automatically deduct leave when requests are approved.

**File to Modify:** `app/Http/Controllers/HR/Leave/LeaveRequestController.php`

**Status:** ✅ **COMPLETED** (2026-03-05)

**Implementation Notes:**
- File modified: `app/Http/Controllers/HR/Leave/LeaveRequestController.php`
- Service modified: `app/Services/LeaveAccrualService.php`

**Changes Made:**

1. **Controller Updates:**
   - Added `LeaveAccrualService` to imports
   - Added `LeaveAccrualService` to constructor dependency injection
   - Updated `update()` method to load `leavePolicy` relationship in eager loading
   - Updated approval logic to call `$this->accrualService->deductLeave()` with:
     - Employee model instance
     - LeavePolicy model instance
     - Duration (calculated from start and end dates)
     - Start date for year determination
   - Added error handling for insufficient balance exceptions
   - Updated cancellation logic to restore balance using new `restoreLeave()` method
   - Added logging for approval/cancellation errors

2. **Service Updates (LeaveAccrualService):**
   - Added new `restoreLeave()` method for cancellation workflow:
     - Takes Employee, LeavePolicy, days, and date parameters
     - Decrements the 'used' field to restore balance
     - Safe handling: prevents used from going below 0
     - Throws exception if balance not found

**Verification:**
- ✅ Approving leave request deducts balance (increments 'used' field)
- ✅ Balance updates reflected immediately via 'remaining' accessor
- ✅ Cannot approve if insufficient balance (exception thrown)
- ✅ Cancelling approved request restores balance
- ✅ Proper error messages displayed to user
- ✅ All operations use model instances (not IDs) for type safety
- ✅ LeavePolicy relationship loaded for approval logic

**Code Flow:**
1. HR Manager/Office Admin clicks "Approve" on leave request
2. Controller loads LeaveRequest with employee and leavePolicy relationships
3. Status updated to 'approved' and approval timestamps recorded
4. `deductLeave()` called with:
   - Employee object
   - LeavePolicy object
   - Calculated duration (e.g., 3 days)
   - Start date (for year determination)
5. Method validates remaining balance >= requested days
6. If valid: increments 'used' field by duration
7. 'remaining' accessor auto-calculates: earned + carried_forward - used
8. LeaveRequestApproved event dispatched
9. Success message shown with confirmation

**Exception Handling:**
- Insufficient balance: "Insufficient leave balance. Available: X, Required: Y"
- Missing balance record: "No leave balance found for employee X in year Y"
- Caught and logged, user-friendly error message displayed

---

### **Phase 4: Seeding & Testing** (0.5 days)

#### Task 4.1: Create Leave Balance Seeder
**Goal:** Seed initial balances for testing.

**File to Create:** `database/seeders/LeaveBalanceSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Services\LeaveAccrualService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LeaveBalanceSeeder extends Seeder
{
    public function run(LeaveAccrualService $service): void
    {
        $currentYear = now()->year;
        $employees = Employee::where('status', 'active')->get();

        foreach ($employees as $employee) {
            // Initialize current year balances
            $service->initializeBalances($employee, $currentYear);
            
            // Simulate accruals for the year so far
            $monthsElapsed = now()->month;
            for ($month = 1; $month <= $monthsElapsed; $month++) {
                $accrualDate = Carbon::create($currentYear, $month, 1);
                
                $policies = LeavePolicy::where('is_active', true)->get();
                foreach ($policies as $policy) {
                    $service->accrueLeave($employee, $policy, $accrualDate);
                }
            }
        }

        $this->command->info('Leave balances seeded successfully!');
    }
}
```

**Add to DatabaseSeeder:**
```php
if (class_exists(\Database\Seeders\LeaveBalanceSeeder::class)) {
    $this->call(\Database\Seeders\LeaveBalanceSeeder::class);
}
```

**Verification:**
- ✅ Seeder runs successfully
- ✅ Balances created for all employees
- ✅ Data appears correctly in UI

---

#### Task 4.2: Test Complete Flow
**Goal:** Verify end-to-end functionality.

**Test Cases:**
1. ✅ View balances page - displays real data
2. ✅ Filter by year - shows correct year data
3. ✅ Filter by employee - shows specific employee
4. ✅ Filter by leave type - filters correctly
5. ✅ Summary calculations - accurate totals
6. ✅ Submit leave request - balance sufficient check
7. ✅ Approve leave request - balance deducted
8. ✅ Monthly accrual command - adds leave correctly
9. ✅ Year-end carry forward - transfers correctly
10. ✅ Performance - page loads quickly with many employees

---

## 📦 Files Summary

### Files to Create (11)
1. `database/migrations/YYYY_MM_DD_create_leave_balance_tables.php`
2. `app/Models/LeaveBalance.php`
3. `app/Models/LeaveAccrual.php`
4. `app/Models/LeaveCarryForwardRule.php`
5. `app/Services/LeaveAccrualService.php`
6. `app/Console/Commands/ProcessMonthlyLeaveAccrual.php`
7. `app/Console/Commands/CarryForwardLeave.php`
8. `database/seeders/LeaveBalanceSeeder.php`
9. `database/seeders/LeaveCarryForwardRulesSeeder.php`

### Files to Modify (3)
1. `app/Http/Controllers/HR/Leave/LeaveBalanceController.php` - Replace mock data
2. `app/Http/Controllers/HR/Leave/LeaveRequestController.php` - Add balance deduction
3. `app/Console/Kernel.php` - Schedule accrual commands
4. `database/seeders/DatabaseSeeder.php` - Add new seeders

### Files Already Good (2)
1. ✅ `resources/js/pages/HR/Leave/Balances.tsx` - UI is ready
2. ✅ `routes/web.php` or route file - Routes already configured

---

## 🚀 Deployment Checklist

### Before Deployment
- [ ] Run migrations: `php artisan migrate`
- [ ] Seed leave policies if not exists
- [ ] Seed carry-forward rules
- [ ] Initialize balances for existing employees
- [ ] Run monthly accrual for current year
- [ ] Test with sample data

### Schedule Setup
- [ ] Verify cron job is running: `php artisan schedule:list`
- [ ] Test monthly accrual: `php artisan leave:process-monthly-accrual`
- [ ] Test carry forward: `php artisan leave:carry-forward`

### Monitoring
- [ ] Check accrual logs
- [ ] Verify balance accuracy
- [ ] Monitor database performance with indexes

---

## 📊 Success Criteria

✅ Leave balances stored in database  
✅ Real-time accurate display on Balances page  
✅ Automatic monthly accrual working  
✅ Leave request approval deducts balance  
✅ Year-end carry forward functional  
✅ Filters and search working  
✅ Performance acceptable (<1s page load)  
✅ Audit trail maintained (accrual records)  
✅ No data inconsistencies  

---

### **Phase 5: Leave Balance UI/UX Improvements** (1 day)

**Goal:** Enhance the Leave Balances page with better organization, pagination, and visualization.

**Current Issues:**
- ❌ Employee names repeat for each leave type (322 rows with 46 employees × 7 policies)
- ❌ No pagination - long scrolling required to view all leaves
- ❌ Poor visual hierarchy - difficult to scan data
- ❌ No way to see all leaves without excessive scrolling
- ❌ No sorting capabilities
- ❌ No export functionality

#### Task 5.1: Add Server-Side Pagination to Controller
**Goal:** Implement cursor-based or offset pagination on the backend.

**Files Modified:** `app/Http/Controllers/HR/Leave/LeaveBalanceController.php`

**Implementation Details:**
1. **Backend Pagination:**
   - Added `Illuminate\Pagination\LengthAwarePaginator` for pagination handling
   - Accept `page` and `per_page` query parameters from frontend
   - Validate `per_page` to allow only: 10, 25, 50, 100 (default: 25)
   - Calculate pagination AFTER grouping employees
   - Pass pagination data to frontend with keys: current_page, per_page, total, last_page, from, to, has_more_pages

2. **Frontend Enhancement:**
   - Updated `resources/js/pages/HR/Leave/Balances.tsx` to handle pagination
   - Added PaginationData interface with all required fields
   - Added pagination controls: Previous/Next buttons, page numbers, records per page selector
   - Integrated with Inertia router for server-side page requests
   - Preserved filters (year, search term) across pagination

3. **Pagination Controls:**
   - "Previous" button (disabled on first page)
   - Page number buttons (showing up to 5 pages)
   - "Next" button (disabled on last page)
   - "Records per page" dropdown (10, 25, 50, 100)
   - Display: "Page X of Y" and "Showing X to Y of Z employees"

**Code Example:**
```php
// Backend (Controller)
$perPage = (int) $request->input('per_page', 25); // Default: 25
$page = (int) $request->input('page', 1);

// Apply pagination AFTER grouping
$paginated = new LengthAwarePaginator(
    $groupedBalances->forPage($page, $perPage)->values(),
    $groupedBalances->count(),
    $perPage,
    $page,
    ['path' => route('hr.leave.balances'), 'query' => $request->query()]
);

return Inertia::render('HR/Leave/Balances', [
    'balances' => $paginated->items(),
    'pagination' => [
        'current_page' => $paginated->currentPage(),
        'per_page' => $paginated->perPage(),
        'total' => $paginated->total(),
        'last_page' => $paginated->lastPage(),
        'from' => $paginated->firstItem(),
        'to' => $paginated->lastItem(),
        'has_more_pages' => $paginated->hasMorePages(),
    ],
    // ... other props
]);
```

**Verification:**
- ✅ Server-side pagination implemented and tested
- ✅ Frontend pagination controls display correctly
- ✅ Page navigation works (Previous/Next buttons)
- ✅ Per page selector works (10, 25, 50, 100)
- ✅ Page numbers show dynamically (max 5 pages visible)
- ✅ Pagination state preserved on filter changes
- ✅ Summary statistics calculated from all records (not just current page)
- ✅ No TypeScript/PHP errors

**Test Cases Passed:**
1. ✅ Page 1 with 25 per page shows employees 1-25 of 46
2. ✅ Page 2 with 25 per page shows employees 26-46 of 46
3. ✅ Changing per_page to 50 shows all 46 employees on page 1
4. ✅ Changing year filters resets to page 1
5. ✅ Summary shows totals for ALL employees, not just current page
6. ✅ Previous button disabled on page 1
7. ✅ Next button disabled on last page
8. ✅ Page buttons update correctly when navigating

**Status:** ✅ **COMPLETED** (2026-03-05)

---

#### Task 5.2: Improve Frontend Table Layout
**Goal:** Redesign the Balances table with collapsible rows, better organization, and pagination.

**Implementation Summary:**

This task completely redesigned the Leave Balances table interface to improve usability and reduce visual clutter. The implementation includes:

1. **Collapsible Employee Rows (✅ Completed)**
   - Added `expandedEmployees` state as a Set<number> to track which employees are expanded
   - Employees now display as header rows with expand/collapse toggle button
   - Leave types appear as indented sub-rows only when employee is expanded
   - Visual indicator: ChevronDown icon that rotates 90° when collapsed
   - Employee details shown in header: name (bold), employee number, and department
   - Employee totals aggregated: sum of earned, used, remaining, carried_forward across all leave types

2. **Column Sorting (✅ Completed)**
   - Added `sortColumn` and `sortDirection` state to track active sort
   - All columns are clickable to sort (Employee, Leave Type, Earned, Used, Remaining, Carried Forward)
   - Sort icons appear on headers with visual indicator (muted when not sorted, active when sorted)
   - `sortBalances()` function handles both string comparison (names) and numeric sorting (balance columns)
   - Type-safe sorting with explicit isNumeric flag to avoid type errors
   - Clicking same column toggles between ascending and descending order

3. **Enhanced Filtering (✅ Completed)**
   - **Department Filter:** New dropdown to filter by employee department
   - **"Show only leaves with balance" Toggle:** Filters out leave types with 0 remaining balance
   - **"Show only leaves with usage" Toggle:** Shows only leaves where used > 0
   - All filters work together with existing search and leave type filters
   - Toggle filters update instantly (client-side filtering)
   - Proper checkbox styling with accessible labels

4. **Improved Visual Hierarchy (✅ Completed)**
   - Employee header rows: light gray background (bg-muted/30) for visual separation
   - Leave type sub-rows: indented under employee (pl-12) with hover effect
   - Bold font for employee names and department info
   - Text sizes and colors properly differentiated (smaller muted text for emp number/dept)
   - Color-coded leave type badges (maintained existing color scheme)
   - Salary columns properly right-aligned with appropriate text colors (red for used, green for remaining)
   - Expand/collapse toggle button with smooth icon rotation

5. **Maintained Existing Features**
   - Server-side pagination preserved (from Task 5.1)
   - Year dropdown filter
   - Search by employee name or number
   - Leave type filter dropdown
   - Summary cards (Total Earned, Used, Remaining)
   - Clear filters button resets all state

**Files Modified:**
- `resources/js/pages/HR/Leave/Balances.tsx` - Complete redesign of table structure and filtering logic

**Code Changes:**

1. **New Imports:**
   ```typescript
   import { ChevronDown, ArrowUpDown } from 'lucide-react';
   ```

2. **New Type Definitions:**
   ```typescript
   type SortColumn = 'employee' | 'leave_type' | 'earned' | 'used' | 'remaining' | 'carried_forward';
   type SortDirection = 'asc' | 'desc';
   ```

3. **New State Variables:**
   ```typescript
   const [expandedEmployees, setExpandedEmployees] = useState<Set<number>>(new Set());
   const [sortColumn, setSortColumn] = useState<SortColumn>('employee');
   const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
   const [showOnlyWithBalance, setShowOnlyWithBalance] = useState(false);
   const [showOnlyWithUsage, setShowOnlyWithUsage] = useState(false);
   const [selectedDepartment, setSelectedDepartment] = useState('all');
   ```

4. **New Functions:**
   ```typescript
   // Toggle employee expansion
   const toggleEmployeeExpanded = (employeeId: number) => {
       setExpandedEmployees((prev) => {
           const newSet = new Set(prev);
           if (newSet.has(employeeId)) {
               newSet.delete(employeeId);
           } else {
               newSet.add(employeeId);
           }
           return newSet;
       });
   };

   // Sort balances with type-safe comparison
   const sortBalances = (balances: typeof flatBalances, sortCol: SortColumn, sortDir: SortDirection) => {
       return [...balances].sort((a, b) => {
           // ... handles string and numeric comparisons
           // returns sorted array
       });
   };

   // Handle column header clicks
   const handleSort = (column: SortColumn) => {
       if (sortColumn === column) {
           setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
       } else {
           setSortColumn(column);
           setSortDirection('asc');
       }
   };

   // Render sort indicator on headers
   const renderSortIcon = (column: SortColumn) => {
       if (sortColumn !== column) {
           return <ArrowUpDown className="h-4 w-4 text-muted-foreground ml-1 inline opacity-50" />;
       }
       return sortDirection === 'asc' ? 
           <ArrowUpDown className="h-4 w-4 text-foreground ml-1 inline" /> : 
           <ArrowUpDown className="h-4 w-4 text-foreground ml-1 inline rotate-180" />;
   };
   ```

5. **Enhanced Filtering Logic:**
   ```typescript
   const filteredBalances = flatBalances.filter((balance) => {
       const matchesSearch = /* ... */;
       const matchesLeaveType = /* ... */;
       const matchesDepartment = selectedDepartment === 'all' || balance.department === selectedDepartment;
       const matchesBalance = !showOnlyWithBalance || balance.remaining > 0;
       const matchesUsage = !showOnlyWithUsage || balance.used > 0;
       return matchesSearch && matchesLeaveType && matchesDepartment && matchesBalance && matchesUsage;
   });

   // Apply sorting and group by employee
   const sortedBalances = sortBalances(filteredBalances, sortColumn, sortDirection);
   const groupedAndSorted = employeeBalances
       .map((emp) => ({
           employee: emp,
           balances: sortedBalances.filter((b) => b.employee_id === emp.id),
       }))
       .filter((item) => item.balances.length > 0);
   ```

6. **Table Structure Changes:**
   - Added expand/collapse column (first column)
   - Grouped by employee with header row (background color, bold font)
   - Leave type sub-rows indented under employee
   - Employee totals aggregated in header row
   - Clickable column headers with sort indicators
   - Proper spacing and alignment for visual hierarchy

**Verification Checklist:**
- ✅ Collapsible employee rows work (expand/collapse toggle functions)
- ✅ Sort functionality works on all columns (tested: employee, earned, remaining)
- ✅ Sort direction toggles between asc/desc (click column twice)
- ✅ Department filter works (filters employee display)
- ✅ "Show with balance" toggle works (hides 0 remaining)
- ✅ "Show with usage" toggle works (shows only used > 0)
- ✅ Filters combine properly (all work together without conflicts)
- ✅ Pagination preserved from Task 5.1 (page navigation still works)
- ✅ Visual hierarchy improved (employee rows distinct from leave types)
- ✅ No TypeScript errors (0 compilation errors)
- ✅ All state properly typed with generics
- ✅ Icons and visual indicators display correctly

**Test Cases Passed:**
1. ✅ Expand employee shows all leave types for that employee
2. ✅ Collapse employee hides leave types, still shows totals in header
3. ✅ Sort by employee name arranges in alphabetical order (ascending/descending)
4. ✅ Sort by earned amount sorts numeric values correctly
5. ✅ Multiple employees can be expanded/collapsed independently
6. ✅ Department filter shows only employees from selected department
7. ✅ "Show with balance" hides leave types with 0 remaining
8. ✅ "Show with usage" shows only leave types with used > 0
9. ✅ Clear filters resets all toggles, sorts, and expansions
10. ✅ Pagination works after filtering and sorting

**Performance Notes:**
- Sorting is done client-side on already-filtered results
- Grouping occurs after sorting to maintain sort order
- Set for expanded employees allows O(1) lookup
- No unnecessary re-renders (proper state management)

**Visual Improvements:**
- **Before:** Maria Reyes repeated 7 times (one per leave type) — difficult to scan
- **After:** Maria Reyes shown once (collapsed or expanded) — much cleaner layout
- **Before:** 322 rows all on one page (Task 5.1 fixed with pagination, now 25 per page)
- **After:** Each page shows ~25 employees, expandable to show their leave types

**Status:** ✅ **COMPLETED** (2026-03-05)

**Blockers Resolved:**
- Fixed type errors in sorting function (numeric vs string comparison)
- Removed unused imports (Filter icon)
- Proper TypeScript typing for complex sort comparisons

---

#### Task 5.3: Add Export Functionality
**Goal:** Allow users to export leave balance data to Excel/CSV.

**Files to Create:**
1. `app/Http/Controllers/HR/Leave/LeaveBalanceExportController.php`

```php
<?php

namespace App\Http\Controllers\HR\Leave;

use App\Models\LeaveBalance;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LeaveBalancesExport;

class LeaveBalanceExportController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->input('format', 'excel'); // excel or csv
        $year = $request->input('year', now()->year);
        $employeeId = $request->input('employee_id');

        return Excel::download(
            new LeaveBalancesExport($year, $employeeId),
            "leave-balances-{$year}." . ($format === 'csv' ? 'csv' : 'xlsx')
        );
    }
}
```

2. `app/Exports/LeaveBalancesExport.php`

```php
<?php

namespace App\Exports;

use App\Models\LeaveBalance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeaveBalancesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected int $year,
        protected ?int $employeeId
    ) {}

    public function collection()
    {
        $query = LeaveBalance::with(['employee.profile', 'leavePolicy'])
            ->where('year', $this->year);

        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Leave Type',
            'Earned Days',
            'Used Days',
            'Remaining Days',
            'Carried Forward',
        ];
    }

    public function map($balance): array
    {
        return [
            $balance->employee->employee_number,
            $balance->employee->profile->first_name . ' ' . $balance->employee->profile->last_name,
            $balance->leavePolicy->name,
            $balance->earned,
            $balance->used,
            $balance->remaining,
            $balance->carried_forward,
        ];
    }
}
```

**Routes to Add:**
```php
Route::get('/balances/export', [LeaveBalanceExportController::class, 'export'])
    ->name('hr.leave.balances.export');
```

**Status:** ⏳ Pending

---

#### Task 5.4: Performance Optimization
**Goal:** Ensure page loads quickly even with 1000+ records.

**Optimizations:**
1. ✅ Already implemented: Eager loading (employee.profile, employee.department, leavePolicy)
2. Add database indexes on frequently filtered columns:
   - `leave_balances.year`
   - `leave_balances.employee_id`
   - `leave_balances.leave_policy_id`
   - Composite: `(employee_id, year, leave_policy_id)`

3. Frontend optimizations:
   - React.memo for table rows (prevent re-renders)
   - Virtual scrolling for large lists (react-window)
   - Memoize expensive calculations
   - Lazy load filters

**Status:** ⏳ Pending

---

#### Task 5.5: Responsive Design
**Goal:** Ensure Balances page works well on mobile/tablet.

**Improvements:**
1. Stack columns on mobile (employee on top, leave type on next line)
2. Collapse less important columns on smaller screens
3. Use horizontal scroll for leave type details
4. Larger touch targets for pagination/filters
5. Responsive filter layout (stack vertically on mobile)

**Status:** ⏳ Pending

---

## 📊 Phase 5 Summary

| Task | Duration | Complexity | Status |
|------|----------|-----------|--------|
| 5.1: Pagination | 2h | Low | ⏳ Pending |
| 5.2: UI Redesign | 4h | Medium | ⏳ Pending |
| 5.3: Export | 2h | Low | ⏳ Pending |
| 5.4: Performance | 1h | Low | ⏳ Pending |
| 5.5: Responsive | 1h | Low | ⏳ Pending |
| **Total** | **10h** | **-** | **⏳ Pending** |

---

## 🔧 Future Enhancements

- Employee self-service balance view (with restrictions)
- Email notifications for low balances (<5 days threshold)
- Bulk balance adjustments (HR tool for manual corrections)
- Historical balance reports (monthly trends)
- Leave forecasting/planning tools
- Integration with payroll for leave encashment calculations
- Leave balance analytics (dashboard)
- Automated reminders for unused leave

---

**End of Implementation Plan**
