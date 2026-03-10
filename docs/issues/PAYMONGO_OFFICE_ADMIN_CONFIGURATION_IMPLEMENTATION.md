# Payment Methods Configuration - Office Admin Implementation

**Feature:** Payment Methods Configuration (Banks & E-Wallets)  
**Role:** Office Admin  
**Module:** System Settings > Payment Methods  
**Priority:** HIGH  
**Estimated Duration:** 3-4 days  
**Current Status:** 🚧 IN PROGRESS - Phase 2 started (Tasks 1.1, 1.2, and 2.1 completed)

---

## 📋 Executive Summary

**Prerequisite:** Superadmin must complete PayMongo API integration first.

Implement payment methods configuration allowing Office Admin to:
- Select which banks to enable (BDO, BPI, Metrobank, UnionBank, etc.)
- Configure e-wallet options (GCash, Maya/PayMaya)
- Set default payment methods per department or employee level
- Configure payment processing policies
- View payment method analytics
- Manage payment fees and limits

This is **operational configuration** that determines which payment channels are available for payroll disbursement.

---

## 🎯 Goals & Requirements

### Primary Goals:
1. ✅ Enable/disable specific banks for payroll
2. ✅ Enable/disable e-wallet options
3. ✅ Set default payment method by department
4. ✅ Configure payment processing schedules
5. ✅ View payment method usage statistics
6. ✅ Manage payment method metadata (fees, limits)

### Business Requirements:
- ✅ Office Admin can configure without technical knowledge
- ✅ Changes take effect immediately or on next payroll cycle
- ✅ Audit trail for all configuration changes
- ✅ Integration with existing employee records
- ✅ Support bulk payment method assignment

---

## 📊 Current State Analysis

### ✅ Already Exists:
- ✅ Office Admin role and permissions
- ✅ Employee database with bank account information
- ✅ Department management system
- ✅ PayMongo API integration (by Superadmin)

### ⚠️ Needs Implementation:
- ✅ Payment methods configuration table
- ✅ Payment method policies table
- ❌ Office Admin payment methods page
- ✅ Payment method assignment to employees (schema ready)
- ❌ Payment analytics dashboard
- ❌ Default payment method rules

---

## Phase 1: Database Schema & Models

**Duration:** 0.5 days

### Task 1.1: Create Payment Methods Configuration Migration ✅ COMPLETED

**Goal:** Store available payment methods and their configuration.

**Completion Date:** March 4, 2026  
**Status:** ✅ Done (implemented with existing schema compatibility)

**Implementation Steps:**

1. **Create Migration:**
   ```bash
   php artisan make:migration create_payment_methods_configuration_table
   ```

2. **Migration Content:**

Create file: `database/migrations/2026_03_04_110000_create_payment_method_configuration_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // gcash, bdo, bpi, maya, etc.
            $table->string('name'); // GCash, BDO Unibank, etc.
            $table->string('type'); // bank, ewallet
            $table->string('category'); // local_bank, international_bank, ewallet
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_available')->default(true); // System-level availability
            $table->json('configuration')->nullable(); // Custom settings per method
            $table->decimal('transaction_fee', 10, 2)->default(0);
            $table->string('fee_type')->default('fixed'); // fixed, percentage
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->integer('daily_limit')->nullable();
            $table->integer('monthly_limit')->nullable();
            $table->integer('processing_time_hours')->default(24);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_method_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_level')->nullable(); // rank_and_file, supervisory, managerial, executive
            $table->foreignId('default_payment_method_id')->constrained('payment_methods');
            $table->json('allowed_payment_methods'); // Array of payment_method IDs
            $table->boolean('allow_employee_change')->default(true);
            $table->string('approval_required_for_change')->default('none'); // none, supervisor, office_admin
            $table->timestamps();
            
            $table->index(['department_id', 'employee_level']);
        });

        Schema::create('employee_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->string('account_number')->nullable(); // For banks
            $table->string('account_name')->nullable();
            $table->string('mobile_number')->nullable(); // For e-wallets
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->unique(['employee_id', 'payment_method_id']);
            $table->index('employee_id');
        });

        Schema::create('payment_method_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status'); // pending, processing, completed, failed
            $table->string('transaction_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['payment_method_id', 'created_at']);
            $table->index(['employee_id', 'payroll_id']);
        });

        // Seed default payment methods
        $this->seedDefaultPaymentMethods();
    }

    protected function seedDefaultPaymentMethods(): void
    {
        $methods = [
            // Banks
            ['code' => 'bdo', 'name' => 'BDO Unibank', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'bpi', 'name' => 'Bank of the Philippine Islands (BPI)', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'metrobank', 'name' => 'Metrobank', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'unionbank', 'name' => 'UnionBank', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'landbank', 'name' => 'Land Bank of the Philippines', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'pnb', 'name' => 'Philippine National Bank (PNB)', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'security_bank', 'name' => 'Security Bank', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            ['code' => 'rcbc', 'name' => 'Rizal Commercial Banking Corporation (RCBC)', 'type' => 'bank', 'category' => 'local_bank', 'is_available' => true],
            
            // E-Wallets
            ['code' => 'gcash', 'name' => 'GCash', 'type' => 'ewallet', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1],
            ['code' => 'maya', 'name' => 'Maya (PayMaya)', 'type' => 'ewallet', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1],
            ['code' => 'grabpay', 'name' => 'GrabPay', 'type' => 'ewallet', 'category' => 'ewallet', 'is_available' => true, 'processing_time_hours' => 1],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->insert(array_merge($method, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_usage_logs');
        Schema::dropIfExists('employee_payment_methods');
        Schema::dropIfExists('payment_method_policies');
        Schema::dropIfExists('payment_methods');
    }
};
```

**Files Created:**
- `database/migrations/2026_03_04_110000_create_payment_method_configuration_tables.php`

**Implemented Tables:**
- `payment_method_providers`
- `payment_method_policies`
- `employee_payment_methods`
- `payment_method_usage_logs`

**Run Migration:**
```bash
php artisan migrate --path=database/migrations/2026_03_04_110000_create_payment_method_configuration_tables.php
```

**Verification:**
- ✅ Migration applied successfully
- ✅ 11 default providers seeded (8 banks + 3 e-wallets)
- ✅ Existing payroll payment schema preserved (no breaking changes)

---

### Task 1.2: Create Payment Method Models ✅ COMPLETED

**Goal:** Create Eloquent models for payment methods configuration.

**Completion Date:** March 5, 2026  
**Status:** ✅ Done (implemented with provider-based schema compatibility)

**Implementation Steps:**

1. **Create Models:**
   ```bash
   php artisan make:model PaymentMethod
   php artisan make:model PaymentMethodPolicy
   php artisan make:model EmployeePaymentMethod
   php artisan make:model PaymentMethodUsageLog
   ```

2. **PaymentMethod Model:**

Create file: `app/Models/PaymentMethod.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'category',
        'description',
        'logo_url',
        'is_enabled',
        'is_available',
        'configuration',
        'transaction_fee',
        'fee_type',
        'min_amount',
        'max_amount',
        'daily_limit',
        'monthly_limit',
        'processing_time_hours',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_available' => 'boolean',
        'configuration' => 'array',
        'transaction_fee' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];

    public function usageLogs(): HasMany
    {
        return $this->hasMany(PaymentMethodUsageLog::class);
    }

    public function employeePaymentMethods(): HasMany
    {
        return $this->hasMany(EmployeePaymentMethod::class);
    }

    /**
     * Scope: Only enabled methods
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->where('is_available', true);
    }

    /**
     * Scope: Banks only
     */
    public function scopeBanks($query)
    {
        return $query->where('type', 'bank');
    }

    /**
     * Scope: E-wallets only
     */
    public function scopeEwallets($query)
    {
        return $query->where('type', 'ewallet');
    }

    /**
     * Get formatted fee display
     */
    public function getFormattedFeeAttribute(): string
    {
        if ($this->fee_type === 'percentage') {
            return $this->transaction_fee . '%';
        }
        return '₱' . number_format($this->transaction_fee, 2);
    }

    /**
     * Calculate fee for given amount
     */
    public function calculateFee(float $amount): float
    {
        if ($this->fee_type === 'percentage') {
            return ($amount * $this->transaction_fee) / 100;
        }
        return (float) $this->transaction_fee;
    }

    /**
     * Check if amount is within limits
     */
    public function isAmountValid(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }
        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }
        return true;
    }
}
```

3. **PaymentMethodPolicy Model:**

Create file: `app/Models/PaymentMethodPolicy.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodPolicy extends Model
{
    protected $fillable = [
        'department_id',
        'employee_level',
        'default_payment_method_id',
        'allowed_payment_methods',
        'allow_employee_change',
        'approval_required_for_change',
    ];

    protected $casts = [
        'allowed_payment_methods' => 'array',
        'allow_employee_change' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    /**
     * Get allowed payment methods as collection
     */
    public function getAllowedMethodsAttribute()
    {
        return PaymentMethod::whereIn('id', $this->allowed_payment_methods)->get();
    }

    /**
     * Check if payment method is allowed
     */
    public function isMethodAllowed(int $paymentMethodId): bool
    {
        return in_array($paymentMethodId, $this->allowed_payment_methods);
    }
}
```

4. **EmployeePaymentMethod Model:**

Create file: `app/Models/EmployeePaymentMethod.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaymentMethod extends Model
{
    protected $fillable = [
        'employee_id',
        'payment_method_id',
        'account_number',
        'account_name',
        'mobile_number',
        'is_default',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get masked account number
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        if (!$this->account_number) return 'N/A';
        
        $length = strlen($this->account_number);
        if ($length <= 4) return $this->account_number;
        
        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    /**
     * Get masked mobile number
     */
    public function getMaskedMobileNumberAttribute(): string
    {
        if (!$this->mobile_number) return 'N/A';
        
        return substr($this->mobile_number, 0, 4) . '***' . substr($this->mobile_number, -4);
    }
}
```

5. **PaymentMethodUsageLog Model:**

Create file: `app/Models/PaymentMethodUsageLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodUsageLog extends Model
{
    protected $fillable = [
        'payment_method_id',
        'employee_id',
        'payroll_id',
        'amount',
        'fee',
        'status',
        'transaction_reference',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * Scope: Completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get total amount including fee
     */
    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->amount + $this->fee);
    }
}
```

**Files Created/Updated:**
- `app/Models/PaymentMethodProvider.php` (new)
- `app/Models/PaymentMethodPolicy.php` (new)
- `app/Models/EmployeePaymentMethod.php` (new)
- `app/Models/PaymentMethodUsageLog.php` (new)
- `app/Models/PaymentMethod.php` (updated: added `providers()` relationship)

**Verification:**
- ✅ Models have proper relationships
- ✅ Methods for fee calculation
- ✅ Validation for amount limits
- ✅ Masked display for sensitive data
- ✅ Uses implemented schema fields (`payment_method_provider_id`, `default_payment_method_provider_id`, `payroll_period_id`)
- ✅ No model diagnostics/errors

---

## Phase 2: Backend - Controller & Routes

**Duration:** 1 day

### Task 2.1: Create Payment Methods Configuration Controller ✅ COMPLETED

**Goal:** Create controller for Office Admin to manage payment methods.

**Completion Date:** March 5, 2026  
**Status:** ✅ Done (implemented with provider-based schema compatibility)

**Implementation Steps:**

Create file: `app/Http/Controllers/Admin/PaymentMethodsController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodPolicy;
use App\Models\PaymentMethodUsageLog;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class PaymentMethodsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Office Admin|Superadmin']);
    }

    /**
     * Display payment methods configuration page
     */
    public function index(): Response
    {
        $paymentMethods = PaymentMethod::orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($method) => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'type' => $method->type,
                'category' => $method->category,
                'description' => $method->description,
                'is_enabled' => $method->is_enabled,
                'is_available' => $method->is_available,
                'formatted_fee' => $method->formatted_fee,
                'processing_time_hours' => $method->processing_time_hours,
                'usage_count' => $method->usageLogs()->count(),
                'active_employees_count' => $method->employeePaymentMethods()->count(),
            ]);

        // Get statistics
        $statistics = [
            'total_methods' => $paymentMethods->count(),
            'enabled_methods' => $paymentMethods->where('is_enabled', true)->count(),
            'total_banks' => $paymentMethods->where('type', 'bank')->count(),
            'total_ewallets' => $paymentMethods->where('type', 'ewallet')->count(),
        ];

        // Get policies
        $policies = PaymentMethodPolicy::with(['department', 'defaultPaymentMethod'])
            ->get()
            ->map(fn($policy) => [
                'id' => $policy->id,
                'department_name' => $policy->department?->name ?? 'All Departments',
                'employee_level' => $policy->employee_level ?? 'All Levels',
                'default_method' => $policy->defaultPaymentMethod->name,
                'allowed_methods_count' => count($policy->allowed_payment_methods),
                'allow_employee_change' => $policy->allow_employee_change,
            ]);

        return Inertia::render('Admin/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
            'statistics' => $statistics,
            'policies' => $policies,
        ]);
    }

    /**
     * Update payment method configuration
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'is_enabled' => 'boolean',
            'transaction_fee' => 'nullable|numeric|min:0',
            'fee_type' => 'in:fixed,percentage',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:0',
            'monthly_limit' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->update($validated);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($paymentMethod)
            ->withProperties(['changes' => $validated])
            ->log('Payment method configuration updated');

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully.',
        ]);
    }

    /**
     * Bulk enable/disable payment methods
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'payment_method_ids' => 'required|array',
            'payment_method_ids.*' => 'exists:payment_methods,id',
            'is_enabled' => 'required|boolean',
        ]);

        PaymentMethod::whereIn('id', $validated['payment_method_ids'])
            ->update(['is_enabled' => $validated['is_enabled']]);

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'payment_methods' => $validated['payment_method_ids'],
                'is_enabled' => $validated['is_enabled'],
            ])
            ->log('Bulk payment methods update');

        return response()->json([
            'success' => true,
            'message' => 'Payment methods updated successfully.',
        ]);
    }

    /**
     * Get payment method analytics
     */
    public function analytics()
    {
        $usageByMethod = PaymentMethodUsageLog::with('paymentMethod')
            ->completed()
            ->select('payment_method_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method_id')
            ->get()
            ->map(fn($log) => [
                'method_name' => $log->paymentMethod->name,
                'transaction_count' => $log->count,
                'total_amount' => number_format($log->total_amount, 2),
            ]);

        $monthlyTrends = PaymentMethodUsageLog::completed()
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'usage_by_method' => $usageByMethod,
            'monthly_trends' => $monthlyTrends,
        ]);
    }

    /**
     * Store payment method policy
     */
    public function storePolicy(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'employee_level' => 'nullable|in:rank_and_file,supervisory,managerial,executive',
            'default_payment_method_id' => 'required|exists:payment_methods,id',
            'allowed_payment_methods' => 'required|array',
            'allowed_payment_methods.*' => 'exists:payment_methods,id',
            'allow_employee_change' => 'boolean',
            'approval_required_for_change' => 'in:none,supervisor,office_admin',
        ]);

        $policy = PaymentMethodPolicy::create($validated);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($policy)
            ->log('Payment method policy created');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy created successfully.',
            'policy' => $policy->load(['department', 'defaultPaymentMethod']),
        ]);
    }

    /**
     * Update payment method policy
     */
    public function updatePolicy(Request $request, int $id)
    {
        $validated = $request->validate([
            'default_payment_method_id' => 'required|exists:payment_methods,id',
            'allowed_payment_methods' => 'required|array',
            'allowed_payment_methods.*' => 'exists:payment_methods,id',
            'allow_employee_change' => 'boolean',
            'approval_required_for_change' => 'in:none,supervisor,office_admin',
        ]);

        $policy = PaymentMethodPolicy::findOrFail($id);
        $policy->update($validated);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($policy)
            ->log('Payment method policy updated');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy updated successfully.',
        ]);
    }

    /**
     * Delete payment method policy
     */
    public function destroyPolicy(int $id)
    {
        $policy = PaymentMethodPolicy::findOrFail($id);
        $policy->delete();

        activity()
            ->causedBy(auth()->user())
            ->log('Payment method policy deleted');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy deleted successfully.',
        ]);
    }
}
```

**Files Created:**
- `app/Http/Controllers/Admin/PaymentMethodsController.php`

**Verification:**
- ✅ Controller created with Office Admin/Superadmin access middleware
- ✅ CRUD methods implemented for provider-based payment methods
- ✅ Policy management implemented using `default_payment_method_provider_id` and `allowed_payment_method_providers`
- ✅ Analytics uses provider usage logs and PostgreSQL-compatible monthly grouping
- ✅ No controller diagnostics/errors

---

### Task 2.2: Add Routes

**Goal:** Configure routes for payment methods configuration.

**Implementation Steps:**

Update `routes/admin.php`:

```php
<?php

use App\Http\Controllers\Admin\PaymentMethodsController;

// Payment Methods Configuration (Office Admin)
Route::middleware(['auth', 'role:Office Admin|Superadmin'])
    ->prefix('payment-methods')
    ->name('admin.payment-methods.')
    ->group(function () {
        Route::get('/', [PaymentMethodsController::class, 'index'])->name('index');
        Route::put('/{id}', [PaymentMethodsController::class, 'update'])->name('update');
        Route::post('/bulk-update', [PaymentMethodsController::class, 'bulkUpdate'])->name('bulk-update');
        Route::get('/analytics', [PaymentMethodsController::class, 'analytics'])->name('analytics');
        
        // Policies
        Route::post('/policies', [PaymentMethodsController::class, 'storePolicy'])->name('policies.store');
        Route::put('/policies/{id}', [PaymentMethodsController::class, 'updatePolicy'])->name('policies.update');
        Route::delete('/policies/{id}', [PaymentMethodsController::class, 'destroyPolicy'])->name('policies.destroy');
    });
```

**Files to Modify:**
- `routes/admin.php`

**Verification:**
- ✅ Routes require Office Admin or Superadmin role
- ✅ CRUD operations for payment methods
- ✅ Policy management routes
- ✅ Analytics endpoint

---

## Phase 3: Frontend - Office Admin Configuration Page

**Duration:** 2 days

### Task 3.1: Create Payment Methods Configuration Page

**Goal:** Build Office Admin UI for payment methods configuration.

**Implementation Steps:**

1. **Create Directory:**
   ```bash
   mkdir -p resources/js/pages/Admin/PaymentMethods
   ```

2. **Create Index Page:**

Create file: `resources/js/pages/Admin/PaymentMethods/Index.tsx`

```tsx
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  CreditCard,
  Wallet,
  Building2,
  TrendingUp,
  Users,
  Settings,
  Plus,
  Edit,
  Trash2,
} from 'lucide-react';
import { PageProps } from '@/types';
import axios from 'axios';

interface PaymentMethod {
  id: number;
  code: string;
  name: string;
  type: string;
  category: string;
  description: string;
  is_enabled: boolean;
  is_available: boolean;
  formatted_fee: string;
  processing_time_hours: number;
  usage_count: number;
  active_employees_count: number;
}

interface PaymentMethodPolicy {
  id: number;
  department_name: string;
  employee_level: string;
  default_method: string;
  allowed_methods_count: number;
  allow_employee_change: boolean;
}

interface Statistics {
  total_methods: number;
  enabled_methods: number;
  total_banks: number;
  total_ewallets: number;
}

interface PaymentMethodsPageProps extends PageProps {
  paymentMethods: PaymentMethod[];
  policies: PaymentMethodPolicy[];
  statistics: Statistics;
}

export default function PaymentMethodsIndex({
  paymentMethods,
  policies,
  statistics,
}: PaymentMethodsPageProps) {
  const [selectedMethods, setSelectedMethods] = useState<number[]>([]);

  const handleToggleMethod = async (methodId: number, currentState: boolean) => {
    try {
      await axios.put(route('admin.payment-methods.update', methodId), {
        is_enabled: !currentState,
      });
      
      router.reload({ only: ['paymentMethods', 'statistics'] });
    } catch (error) {
      console.error('Failed to update payment method', error);
    }
  };

  const handleBulkEnable = async (enable: boolean) => {
    if (selectedMethods.length === 0) return;

    try {
      await axios.post(route('admin.payment-methods.bulk-update'), {
        payment_method_ids: selectedMethods,
        is_enabled: enable,
      });
      
      setSelectedMethods([]);
      router.reload({ only: ['paymentMethods', 'statistics'] });
    } catch (error) {
      console.error('Failed to bulk update', error);
    }
  };

  const getMethodIcon = (type: string) => {
    return type === 'bank' ? Building2 : Wallet;
  };

  const bankMethods = paymentMethods.filter(m => m.type === 'bank');
  const ewalletMethods = paymentMethods.filter(m => m.type === 'ewallet');

  return (
    <AppLayout>
      <Head title="Payment Methods Configuration" />

      <div className="p-6 max-w-7xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Payment Methods</h1>
            <p className="text-gray-600 mt-1">
              Configure available payment methods for payroll disbursement
            </p>
          </div>
          <Button className="gap-2">
            <Plus className="h-4 w-4" />
            Add Policy
          </Button>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Total Methods
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{statistics.total_methods}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Enabled
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600">
                {statistics.enabled_methods}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                Banks
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{statistics.total_banks}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-gray-600">
                E-Wallets
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{statistics.total_ewallets}</div>
            </CardContent>
          </Card>
        </div>

        {/* Bulk Actions */}
        {selectedMethods.length > 0 && (
          <Card className="border-blue-200 bg-blue-50">
            <CardContent className="py-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">
                  {selectedMethods.length} method(s) selected
                </span>
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => handleBulkEnable(true)}>
                    Enable Selected
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleBulkEnable(false)}
                  >
                    Disable Selected
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setSelectedMethods([])}
                  >
                    Clear
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        <Tabs defaultValue="banks" className="space-y-6">
          <TabsList>
            <TabsTrigger value="banks">Banks ({bankMethods.length})</TabsTrigger>
            <TabsTrigger value="ewallets">E-Wallets ({ewalletMethods.length})</TabsTrigger>
            <TabsTrigger value="policies">Policies ({policies.length})</TabsTrigger>
            <TabsTrigger value="analytics">Analytics</TabsTrigger>
          </TabsList>

          {/* Banks Tab */}
          <TabsContent value="banks">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Building2 className="h-5 w-5" />
                  Bank Accounts
                </CardTitle>
                <CardDescription>
                  Enable or disable bank transfer options for payroll
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {bankMethods.map((method) => (
                    <div
                      key={method.id}
                      className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex items-center gap-4">
                        <input
                          type="checkbox"
                          checked={selectedMethods.includes(method.id)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setSelectedMethods([...selectedMethods, method.id]);
                            } else {
                              setSelectedMethods(
                                selectedMethods.filter((id) => id !== method.id)
                              );
                            }
                          }}
                          className="h-4 w-4"
                        />
                        
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h3 className="font-semibold">{method.name}</h3>
                            {method.is_enabled ? (
                              <Badge className="bg-green-100 text-green-800">
                                Enabled
                              </Badge>
                            ) : (
                              <Badge variant="secondary">Disabled</Badge>
                            )}
                          </div>
                          
                          <div className="flex items-center gap-4 text-sm text-gray-600">
                            <span>Fee: {method.formatted_fee}</span>
                            <span>•</span>
                            <span>
                              Processing: {method.processing_time_hours}h
                            </span>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                              <Users className="h-3 w-3" />
                              {method.active_employees_count} employees
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <Switch
                          checked={method.is_enabled}
                          onCheckedChange={() =>
                            handleToggleMethod(method.id, method.is_enabled)
                          }
                          disabled={!method.is_available}
                        />
                        
                        <Button variant="ghost" size="icon">
                          <Settings className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* E-Wallets Tab */}
          <TabsContent value="ewallets">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Wallet className="h-5 w-5" />
                  E-Wallets
                </CardTitle>
                <CardDescription>
                  Enable or disable e-wallet options for instant transfers
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {ewalletMethods.map((method) => (
                    <div
                      key={method.id}
                      className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                    >
                      <div className="flex items-center gap-4">
                        <input
                          type="checkbox"
                          checked={selectedMethods.includes(method.id)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setSelectedMethods([...selectedMethods, method.id]);
                            } else {
                              setSelectedMethods(
                                selectedMethods.filter((id) => id !== method.id)
                              );
                            }
                          }}
                          className="h-4 w-4"
                        />
                        
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h3 className="font-semibold">{method.name}</h3>
                            {method.is_enabled ? (
                              <Badge className="bg-green-100 text-green-800">
                                Enabled
                              </Badge>
                            ) : (
                              <Badge variant="secondary">Disabled</Badge>
                            )}
                            <Badge className="bg-blue-100 text-blue-800">
                              Instant
                            </Badge>
                          </div>
                          
                          <div className="flex items-center gap-4 text-sm text-gray-600">
                            <span>Fee: {method.formatted_fee}</span>
                            <span>•</span>
                            <span>
                              Processing: {method.processing_time_hours}h
                            </span>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                              <Users className="h-3 w-3" />
                              {method.active_employees_count} employees
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <Switch
                          checked={method.is_enabled}
                          onCheckedChange={() =>
                            handleToggleMethod(method.id, method.is_enabled)
                          }
                          disabled={!method.is_available}
                        />
                        
                        <Button variant="ghost" size="icon">
                          <Settings className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Policies Tab */}
          <TabsContent value="policies">
            <Card>
              <CardHeader>
                <CardTitle>Payment Method Policies</CardTitle>
                <CardDescription>
                  Set default payment methods by department or employee level
                </CardDescription>
              </CardHeader>
              <CardContent>
                {policies.length === 0 ? (
                  <div className="text-center py-8 text-gray-500">
                    No policies configured. Create a policy to set defaults.
                  </div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Department</TableHead>
                        <TableHead>Employee Level</TableHead>
                        <TableHead>Default Method</TableHead>
                        <TableHead>Allowed Methods</TableHead>
                        <TableHead>Employee Can Change</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {policies.map((policy) => (
                        <TableRow key={policy.id}>
                          <TableCell>{policy.department_name}</TableCell>
                          <TableCell>{policy.employee_level}</TableCell>
                          <TableCell>
                            <Badge variant="outline">{policy.default_method}</Badge>
                          </TableCell>
                          <TableCell>
                            {policy.allowed_methods_count} methods
                          </TableCell>
                          <TableCell>
                            {policy.allow_employee_change ? (
                              <Badge className="bg-green-100 text-green-800">
                                Yes
                              </Badge>
                            ) : (
                              <Badge variant="secondary">No</Badge>
                            )}
                          </TableCell>
                          <TableCell>
                            <div className="flex gap-2">
                              <Button variant="ghost" size="icon">
                                <Edit className="h-4 w-4" />
                              </Button>
                              <Button variant="ghost" size="icon">
                                <Trash2 className="h-4 w-4 text-red-600" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          {/* Analytics Tab */}
          <TabsContent value="analytics">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5" />
                  Payment Method Analytics
                </CardTitle>
                <CardDescription>
                  Usage statistics and trends for payment methods
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8 text-gray-500">
                  Analytics data will be displayed here
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
```

**Files to Create:**
- `resources/js/pages/Admin/PaymentMethods/Index.tsx`

**Verification:**
- ✅ Display all payment methods (banks and e-wallets)
- ✅ Enable/disable toggle for each method
- ✅ Bulk selection and actions
- ✅ Statistics dashboard
- ✅ Policies management
- ✅ Responsive design

---

## Phase 4: Permissions & Testing

**Duration:** 0.5 days

### Task 4.1: Create Permissions

**Goal:** Set up RBAC permissions for payment methods configuration.

**Implementation Steps:**

1. **Create Permissions Seeder:**

Create file: `database/seeders/PaymentMethodsPermissionsSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PaymentMethodsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'payments.methods.configure' => 'Configure payment methods (enable/disable)',
            'payments.methods.manage' => 'Manage payment method settings',
            'payments.policies.manage' => 'Manage payment method policies',
            'payments.analytics.view' => 'View payment analytics',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web', 'description' => $description]
            );
        }

        // Assign to Office Admin
        $officeAdmin = Role::where('name', 'Office Admin')->first();
        $superadmin = Role::where('name', 'Superadmin')->first();
        
        if ($officeAdmin) {
            $officeAdmin->givePermissionTo(array_keys($permissions));
        }
        
        if ($superadmin) {
            $superadmin->givePermissionTo(array_keys($permissions));
        }
    }
}
```

2. **Run Seeder:**
   ```bash
   php artisan db:seed --class=PaymentMethodsPermissionsSeeder
   ```

**Files to Create:**
- `database/seeders/PaymentMethodsPermissionsSeeder.php`

---

### Task 4.2: Manual Testing Checklist

**Payment Methods:**
- ✅ Can view all payment methods
- ✅ Can enable/disable individual methods
- ✅ Bulk enable/disable works
- ✅ Statistics display correctly
- ✅ Only available methods can be enabled

**Policies:**
- ✅ Can create policy for department
- ✅ Can create policy for employee level
- ✅ Can set default payment method
- ✅ Can configure allowed methods
- ✅ Can update existing policy
- ✅ Can delete policy

**Analytics:**
- ✅ Usage statistics display correctly
- ✅ Monthly trends show data
- ✅ Payment method breakdown works

**Security:**
- ✅ Only Office Admin can access
- ✅ Superadmin also has access
- ✅ Other roles get 403 forbidden
- ✅ Configuration changes are audit logged

---

## Summary

### Implementation Breakdown

| Phase | Duration | Tasks | Status |
|-------|----------|-------|--------|
| **Phase 1** | 0.5 days | Database Schema & Models | ✅ Complete (Tasks 1.1 & 1.2) |
| **Phase 2** | 1 day | Backend Controller & Routes | 🚧 In Progress (Task 2.1 complete) |
| **Phase 3** | 2 days | Frontend Configuration Page | ⏳ Pending |
| **Phase 4** | 0.5 days | Permissions & Testing | ⏳ Pending |
| **Total** | **4 days** | 10 tasks | 🚧 In Progress |

### Key Files Summary

**Files to Create (9):**
1. `database/migrations/YYYY_MM_DD_create_payment_methods_configuration_table.php`
2. `app/Models/PaymentMethod.php`
3. `app/Models/PaymentMethodPolicy.php`
4. `app/Models/EmployeePaymentMethod.php`
5. `app/Models/PaymentMethodUsageLog.php`
6. `app/Http/Controllers/Admin/PaymentMethodsController.php`
7. `resources/js/pages/Admin/PaymentMethods/Index.tsx`
8. `database/seeders/PaymentMethodsPermissionsSeeder.php`

**Files to Modify (1):**
1. `routes/admin.php` - Add payment methods routes

### Success Criteria

✅ Office Admin can view all payment methods  
✅ Can enable/disable banks and e-wallets  
✅ Can configure payment method fees and limits  
✅ Can create payment policies by department/level  
✅ Can set default payment methods  
✅ Statistics and analytics display correctly  
✅ Bulk actions work for multiple methods  
✅ Only Office Admin/Superadmin have access  
✅ Configuration changes are audit logged  
✅ Integration with PayMongo API works  

---

## Quick Start Commands

```bash
# Phase 1: Create Migration & Models
php artisan make:migration create_payment_methods_configuration_table
php artisan make:model PaymentMethod
php artisan make:model PaymentMethodPolicy
php artisan make:model EmployeePaymentMethod
php artisan make:model PaymentMethodUsageLog
php artisan migrate

# Phase 2: Create Controller
php artisan make:controller Admin/PaymentMethodsController

# Phase 3: Create Frontend Directory
mkdir -p resources/js/pages/Admin/PaymentMethods

# Phase 4: Seed Permissions
php artisan make:seeder PaymentMethodsPermissionsSeeder
php artisan db:seed --class=PaymentMethodsPermissionsSeeder

# Build Frontend
npm run build

# Clear Caches
php artisan optimize:clear
```

---

**End of Implementation Plan**
