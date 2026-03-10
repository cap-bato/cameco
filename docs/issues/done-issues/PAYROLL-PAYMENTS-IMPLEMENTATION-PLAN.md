# Payroll Module - Payments Feature Implementation Plan

**Feature:** Payment Processing & Distribution Management  
**Status:** Planning → Implementation  
**Priority:** HIGH  
**Created:** February 6, 2026  
**Estimated Duration:** 3-4 weeks  
**Target Users:** Payroll Officer, Office Admin, Employees  
**Dependencies:** PayrollProcessing (approved calculations), EmployeePayroll (bank details), Leave Management (unpaid leave deductions), Timekeeping (attendance data)

---

## 📚 Reference Documentation

This implementation plan is based on the following specifications and documentation:

### Core Specifications
- **[PAYROLL_MODULE_ARCHITECTURE.md](../docs/PAYROLL_MODULE_ARCHITECTURE.md)** - Complete Philippine payroll architecture with payment methods
- **[payroll-processing.md](../docs/workflows/processes/payroll-processing.md)** - Complete payroll workflow including payment distribution
- **[cash-salary-distribution.md](../docs/workflows/processes/cash-salary-distribution.md)** - Current cash distribution process (primary method)
- **[digital-salary-distribution.md](../docs/workflows/processes/digital-salary-distribution.md)** - Future bank/e-wallet distribution
- **[05-payroll-officer-workflow.md](../docs/workflows/05-payroll-officer-workflow.md)** - Payroll officer responsibilities
- **[02-office-admin-workflow.md](../docs/workflows/02-office-admin-workflow.md)** - Payment method configuration

### Integration Roadmaps
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Leave deductions integration
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Attendance-based pay integration
- **[LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md](../.aiplans/LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md)** - Event-driven leave integration

### Existing Code References
- **Frontend:** `resources/js/pages/Payroll/Payments/*` (BankFiles, Cash, Payslips, Tracking)
- **Controllers:** `app/Http/Controllers/Payroll/Payments/*` (all have mock data)
- **Components:** `resources/js/components/payroll/*` (payment-related components)
- **Routes:** `routes/payroll.php` (Payments section)

### Related Implementation Plans
- **[PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md](./PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md)** - Government contributions (affects net pay)
- **[PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md](./PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md)** - Employee bank details

---

## 📋 Executive Summary

**Current State:**
- ✅ **Frontend Pages:** Complete with mock data (BankFiles, Cash, Payslips, Tracking)
- ✅ **Controllers:** Basic structure with mock data (BankFilesController, CashPaymentController, PayslipsController, PaymentTrackingController)
- ✅ **Routes:** All routes registered in payroll.php
- ✅ **Components:** Payment-related components exist (envelope-printer, payslip-generator, payment-tracking-table)
- ❌ **Database Schema:** No payment tracking tables exist
- ❌ **Models:** No Eloquent models for payments
- ❌ **PayMongo Integration:** No payment gateway integration
- ❌ **Bank File Generator:** No real bank file generation (Metrobank, BDO, BPI formats)
- ❌ **Cash Distribution:** No accountability tracking system
- ❌ **Integration:** No connection to Leave Management or Timekeeping

**Goal:** Build complete payment distribution system that:
1. Generates bank files for digital salary transfer (InstaPay, PESONet formats)
2. Manages cash distribution with accountability tracking (current primary method)
3. Generates accurate payslips with all deductions (government, loans, advances, leave)
4. Integrates PayMongo for future online banking and e-wallet disbursements
5. Tracks payment status across all methods (cash, bank, e-wallet)
6. Provides audit trail for Office Admin and Payroll Officer
7. Integrates with Leave Management (unpaid leave deductions) and Timekeeping (attendance-based pay)

**Payment Methods Priority:**
1. **Phase 1 (Current):** Cash distribution with envelope generation
2. **Phase 2 (Near-term):** Bank file generation (InstaPay/PESONet)
3. **Phase 3 (Future):** PayMongo integration for e-wallets (GCash, Maya, etc.)

**Timeline:** 3-4 weeks (February 6 - March 3, 2026)

---

## 🎯 Feature Overview

### What is Payment Processing & Distribution?

The Payments module handles the final step of payroll: delivering net pay to employees through various methods:

1. **Cash Distribution (Current Primary Method)** - Physical cash in envelopes
   - Withdraw total payroll from company vault/bank
   - Prepare denomination breakdown per employee
   - Print salary envelopes with breakdown
   - Track cash accountability (disbursement log, signatures)
   - Generate accountability report for Office Admin
   - Handle unclaimed salaries (re-deposit, rollover)

2. **Bank File Generation (Near-term)** - Digital bank transfers
   - Generate bank-specific formats (Metrobank CSV, BDO Excel, BPI Text)
   - Support InstaPay (real-time, ≤₱50k per transaction)
   - Support PESONet (batch, ₱1M per transaction, T+1 settlement)
   - Validate employee bank accounts
   - Track submission status and confirmation
   - Handle failed transfers and retries

3. **E-wallet/Online Banking (Future)** - PayMongo integration
   - GCash disbursements via PayMongo API
   - Maya (PayMaya) wallet transfers
   - Bank account transfers via PayMongo
   - Real-time status tracking
   - Webhook handling for payment confirmations
   - Retry logic for failed transactions

4. **Payslip Generation** - Detailed salary breakdown
   - Earnings breakdown (basic, overtime, allowances)
   - Deductions breakdown (SSS, PhilHealth, Pag-IBIG, tax, loans, advances)
   - Leave deductions (unpaid leave days)
   - Attendance adjustments (absences, tardiness)
   - Year-to-date summaries
   - Government numbers (SSS, PhilHealth, Pag-IBIG, TIN)
   - Digital signatures and QR codes for verification

5. **Payment Tracking** - Multi-method status dashboard
   - Real-time payment status (pending, processing, paid, failed)
   - Payment method breakdown (cash, bank, e-wallet)
   - Failed payment alerts and retry queue
   - Employee acknowledgment tracking (signatures, receipts)
   - Reconciliation tools (expected vs actual)
   - Audit trail for compliance

---

## 🗄️ Database Schema Design

### Tables Overview

| Table | Purpose | Dependencies |
|-------|---------|--------------|
| `payment_methods` | Company-configured payment methods | None |
| `employee_payment_preferences` | Employee bank/wallet details | `employees` |
| `payroll_payments` | Per-employee payment records | `payroll_periods`, `employee_payroll_calculations` |
| `bank_file_batches` | Bank file generation tracking | `payroll_periods` |
| `cash_distribution_batches` | Cash disbursement tracking | `payroll_periods` |
| `payslips` | Generated payslip records | `payroll_payments` |
| `payment_audit_logs` | Payment action audit trail | `users` |

---

## 🚀 Implementation Phases

## **Phase 1: Database Foundation (Week 1: Feb 6-12)**

### Task 1.1: Create Database Migrations

#### ✅ Subtask 1.1.1: Create payment_methods Migration (COMPLETED)
**File:** `database/migrations/2026_02_17_053226_create_payment_methods_table.php`

**Purpose:** Store company-configured payment methods (cash, bank, e-wallet)

**Status:** ✅ **COMPLETED**
- Migration file created
- Table successfully created in database
- All columns and indexes implemented as specified

**Action:** COMPLETED

---

#### ✅ Subtask 1.1.2: Create employee_payment_preferences Migration (COMPLETED)
**File:** `database/migrations/2026_02_17_053319_create_employee_payment_preferences_table.php`

**Purpose:** Store employee bank account and e-wallet details for digital payments

**Status:** ✅ **COMPLETED**
- Migration file created
- Table successfully created in database
- All columns and indexes implemented as specified

**Action:** COMPLETED

---

#### ✅ Subtask 1.1.3: Create payroll_payments Migration (COMPLETED)
**File:** `database/migrations/2026_02_17_065531_create_payroll_payments_table.php`

**Purpose:** Track individual employee payments per payroll period

**Status:** ✅ **COMPLETED**
- Migration file exists with correct specifications
- Table successfully created in database
- All columns, foreign keys, and indexes implemented as specified
- Dependencies (employees, payroll_periods, employee_payroll_calculations, payment_methods, users) verified

**Action:** COMPLETED

---

#### ✅ Subtask 1.1.4: Create bank_file_batches Migration (COMPLETED)
**File:** `database/migrations/2026_02_17_065540_create_bank_file_batches_table.php`

**Purpose:** Track bank file generation and submission

**Status:** ✅ **COMPLETED**
- Migration file exists with correct specifications
- Table successfully created in database
- All columns, foreign keys, and indexes implemented as specified
- Dependencies (payroll_periods, payment_methods, users) verified

**Action:** COMPLETED

---

#### Subtask 1.1.5: Create cash_distribution_batches Migration ✅ COMPLETED
**File:** `database/migrations/2026_02_17_065550_create_cash_distribution_batches_table.php`

**Purpose:** Track cash disbursement accountability

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_distribution_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Batch Information
            $table->string('batch_number')->unique();
            $table->date('distribution_date');
            $table->string('distribution_location')->nullable();
            
            // Cash Preparation
            $table->decimal('total_cash_amount', 12, 2);
            $table->integer('total_employees');
            $table->json('denomination_breakdown')->nullable(); // {1000: 10, 500: 5, 100: 20, etc}
            
            // Withdrawal Details
            $table->string('withdrawal_source')->nullable(); // 'vault', 'bank_branch'
            $table->string('withdrawal_reference')->nullable();
            $table->date('withdrawal_date')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Verification
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete(); // Payroll Officer
            $table->foreignId('witnessed_by')->nullable()->constrained('users')->nullOnDelete(); // HR Manager/Office Admin
            $table->timestamp('verification_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Distribution Tracking
            $table->integer('envelopes_prepared')->default(0);
            $table->integer('envelopes_distributed')->default(0);
            $table->integer('envelopes_unclaimed')->default(0);
            $table->decimal('amount_distributed', 12, 2)->default(0);
            $table->decimal('amount_unclaimed', 12, 2)->default(0);
            
            // Disbursement Log
            $table->string('log_sheet_path')->nullable(); // Scanned signature log
            $table->timestamp('distribution_started_at')->nullable();
            $table->timestamp('distribution_completed_at')->nullable();
            
            // Unclaimed Handling
            $table->date('unclaimed_deadline')->nullable(); // 30 days after distribution
            $table->string('unclaimed_disposition')->nullable(); // 're-deposited', 'held', 'next_period'
            $table->date('redeposit_date')->nullable();
            $table->string('redeposit_reference')->nullable();
            
            // Status
            $table->enum('status', ['preparing', 'ready', 'distributing', 'completed', 'partially_completed', 'reconciled'])->default('preparing');
            
            // Accountability Report
            $table->string('accountability_report_path')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->foreignId('report_approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['payroll_period_id', 'distribution_date']);
            $table->index(['status', 'distribution_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_distribution_batches');
    }
};
```

**Dependencies:** `payroll_periods`, `users`

**Action:** CREATE

---

#### Subtask 1.1.6: Create payslips Migration ✅ COMPLETED
**File:** `database/migrations/2026_02_17_065600_create_payslips_table.php`

**Purpose:** Store generated payslip records

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_payment_id')->constrained()->cascadeOnDelete();
            
            // Payslip Details
            $table->string('payslip_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            
            // Employee Information (snapshot)
            $table->string('employee_number', 20);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            
            // Government Numbers (snapshot)
            $table->string('sss_number')->nullable();
            $table->string('philhealth_number')->nullable();
            $table->string('pagibig_number')->nullable();
            $table->string('tin')->nullable();
            
            // Earnings Breakdown
            $table->json('earnings_data'); // {basic_salary, overtime, allowances, etc}
            $table->decimal('total_earnings', 10, 2);
            
            // Deductions Breakdown
            $table->json('deductions_data'); // {sss, philhealth, pagibig, tax, loans, etc}
            $table->decimal('total_deductions', 10, 2);
            
            // Net Pay
            $table->decimal('net_pay', 10, 2);
            
            // Leave Information
            $table->json('leave_summary')->nullable(); // {used_days, unpaid_days, deduction_amount}
            
            // Attendance Information
            $table->json('attendance_summary')->nullable(); // {present_days, absences, tardiness}
            
            // Year-to-Date Summaries
            $table->decimal('ytd_gross', 12, 2)->nullable();
            $table->decimal('ytd_tax', 10, 2)->nullable();
            $table->decimal('ytd_sss', 10, 2)->nullable();
            $table->decimal('ytd_philhealth', 10, 2)->nullable();
            $table->decimal('ytd_pagibig', 10, 2)->nullable();
            $table->decimal('ytd_net', 12, 2)->nullable();
            
            // File Details
            $table->string('file_path');
            $table->string('file_format', 10)->default('pdf');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            
            // Distribution
            $table->enum('distribution_method', ['email', 'portal', 'print', 'sms'])->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->boolean('is_viewed')->default(false);
            $table->timestamp('viewed_at')->nullable();
            
            // Digital Signature
            $table->string('signature_hash')->nullable(); // For authenticity verification
            $table->string('qr_code_data')->nullable(); // QR code for quick verification
            
            // Status
            $table->enum('status', ['draft', 'generated', 'distributed', 'acknowledged'])->default('draft');
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index('payslip_number');
            $table->index(['payment_date', 'status']);
            $table->index('distributed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
```

**Dependencies:** `employees`, `payroll_periods`, `payroll_payments`, `users`

**Action:** CREATE

---

#### Subtask 1.1.7: Create payment_audit_logs Migration ✅ COMPLETED
**File:** `database/migrations/2026_02_17_065610_create_payment_audit_logs_table.php`

**Purpose:** Comprehensive audit trail for all payment actions

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Related Entity
            $table->string('auditable_type'); // PayrollPayment, BankFileBatch, etc
            $table->unsignedBigInteger('auditable_id');
            $table->index(['auditable_type', 'auditable_id']);
            
            // Action Details
            $table->string('action', 50); // 'created', 'processed', 'paid', 'failed', 'retried', 'cancelled'
            $table->string('actor_type')->nullable(); // 'user', 'system', 'webhook'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            
            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            
            // Request Information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('action');
            $table->index('created_at');
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
```

**Dependencies:** None (polymorphic)

**Action:** CREATE

---

#### Subtask 1.1.8: Run Migrations ✅ COMPLETED
**Action:** RUN COMMAND

```bash
php artisan migrate
```

**Result:** All 3 new migrations ran in batch 2.

**Validation:** All 7 payment module tables confirmed in database:
- ✅ payment_methods (batch 1)
- ✅ employee_payment_preferences (batch 1)
- ✅ payroll_payments (batch 1)
- ✅ bank_file_batches (batch 1)
- ✅ cash_distribution_batches (batch 2)
- ✅ payslips (batch 2)
- ✅ payment_audit_logs (batch 2)

**Note:** An additional fix migration `2026_02_17_065620_fix_payment_methods_unique_constraint` was added to replace the single-column unique on `method_type` with a composite unique on `(method_type, display_name)`, allowing multiple bank and ewallet records.

---

### Task 1.2: Create Database Seeders

#### Subtask 1.2.1: Create PaymentMethodsSeeder ✅ COMPLETED
**File:** `database/seeders/PaymentMethodsSeeder.php`

**Purpose:** Populate default payment methods (cash, bank, e-wallet)

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        
        $methods = [
            // Cash (Enabled by default - current primary method)
            [
                'method_type' => 'cash',
                'display_name' => 'Cash Payment',
                'description' => 'Physical cash distribution via salary envelopes',
                'is_enabled' => true,
                'requires_employee_setup' => false,
                'supports_bulk_payment' => false,
                'transaction_fee' => 0,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'sort_order' => 1,
                'icon' => 'banknotes',
                'color_hex' => '#10b981',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Metrobank - InstaPay
            [
                'method_type' => 'bank',
                'display_name' => 'Metrobank (InstaPay)',
                'description' => 'Real-time bank transfer via InstaPay network',
                'is_enabled' => false, // Disabled by default, enabled by Office Admin
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 10,
                'min_amount' => 1,
                'max_amount' => 50000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'cutoff_time' => '17:00:00',
                'bank_code' => 'MBTC',
                'bank_name' => 'Metropolitan Bank & Trust Company',
                'file_format' => 'csv',
                'file_template' => json_encode([
                    'columns' => [
                        'Account Number',
                        'Account Name',
                        'Amount',
                        'Reference',
                    ],
                    'delimiter' => ',',
                    'has_header' => true,
                ]),
                'sort_order' => 2,
                'icon' => 'building-library',
                'color_hex' => '#ef4444',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // BDO - PESONet
            [
                'method_type' => 'bank',
                'display_name' => 'BDO Unibank (PESONet)',
                'description' => 'Batch bank transfer via PESONet network (T+1 settlement)',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 25,
                'min_amount' => 1,
                'max_amount' => 1000000,
                'settlement_speed' => 'next_day',
                'processing_days' => 1,
                'cutoff_time' => '14:00:00',
                'bank_code' => 'BDO',
                'bank_name' => 'Banco de Oro',
                'file_format' => 'xlsx',
                'file_template' => json_encode([
                    'columns' => [
                        'Account Number',
                        'Account Name',
                        'Amount',
                        'Particulars',
                    ],
                    'sheet_name' => 'Payroll',
                    'has_header' => true,
                ]),
                'sort_order' => 3,
                'icon' => 'building-library',
                'color_hex' => '#3b82f6',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // GCash via PayMongo
            [
                'method_type' => 'ewallet',
                'display_name' => 'GCash',
                'description' => 'E-wallet transfer via PayMongo API',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 15,
                'min_amount' => 1,
                'max_amount' => 100000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'provider_name' => 'PayMongo',
                'api_endpoint' => 'https://api.paymongo.com/v1',
                'sort_order' => 4,
                'icon' => 'device-mobile',
                'color_hex' => '#0066ff',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // Maya (PayMaya) via PayMongo
            [
                'method_type' => 'ewallet',
                'display_name' => 'Maya (PayMaya)',
                'description' => 'E-wallet transfer via PayMongo API',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 15,
                'min_amount' => 1,
                'max_amount' => 100000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'provider_name' => 'PayMongo',
                'api_endpoint' => 'https://api.paymongo.com/v1',
                'sort_order' => 5,
                'icon' => 'device-mobile',
                'color_hex' => '#00d632',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        
        foreach ($methods as $method) {
            DB::table('payment_methods')->insert($method);
        }
        
        $this->command->info('Payment methods seeded successfully!');
        $this->command->info('- Cash: Enabled (current method)');
        $this->command->info('- Banks: 2 (Metrobank, BDO) - Disabled by default');
        $this->command->info('- E-wallets: 2 (GCash, Maya) - Disabled by default');
    }
}
```

**Dependencies:** `payment_methods` table

**Action:** CREATE

---

#### Subtask 1.2.2: Run Seeders ✅ COMPLETED
**Action:** RUN COMMAND

```bash
php artisan db:seed --class=PaymentMethodsSeeder
```

**Result:**
- 5 records inserted (1 cash enabled, 2 banks disabled, 2 ewallets disabled)
- `payment_methods` count: 5 ✅
- Enabled count: 1 (cash only) ✅
- Disabled count: 4 (Metrobank, BDO, GCash, Maya) ✅

---

## **Phase 2: Models & Relationships (Week 1-2: Feb 6-16)** ✅ COMPLETED

### Task 2.1: Create Eloquent Models ✅ COMPLETED

#### Subtask 2.1.1: Create PaymentMethod Model ✅ COMPLETED
**File:** `app/Models/PaymentMethod.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'method_type',
        'display_name',
        'description',
        'is_enabled',
        'requires_employee_setup',
        'supports_bulk_payment',
        'transaction_fee',
        'min_amount',
        'max_amount',
        'settlement_speed',
        'processing_days',
        'cutoff_time',
        'bank_code',
        'bank_name',
        'file_format',
        'file_template',
        'provider_name',
        'api_endpoint',
        'api_credentials',
        'webhook_url',
        'sort_order',
        'icon',
        'color_hex',
        'configured_by',
        'last_used_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'requires_employee_setup' => 'boolean',
        'supports_bulk_payment' => 'boolean',
        'transaction_fee' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'processing_days' => 'integer',
        'cutoff_time' => 'datetime:H:i:s',
        'file_template' => 'array',
        'sort_order' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'api_credentials', // Sensitive data
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function employeePreferences(): HasMany
    {
        return $this->hasMany(EmployeePaymentPreference::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function bankFileBatches(): HasMany
    {
        return $this->hasMany(BankFileBatch::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeCash($query)
    {
        return $query->where('method_type', 'cash');
    }

    public function scopeBank($query)
    {
        return $query->where('method_type', 'bank');
    }

    public function scopeEwallet($query)
    {
        return $query->where('method_type', 'ewallet');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isCash(): bool
    {
        return $this->method_type === 'cash';
    }

    public function isBank(): bool
    {
        return $this->method_type === 'bank';
    }

    public function isEwallet(): bool
    {
        return $this->method_type === 'ewallet';
    }

    public function supportsAmount(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    public function calculateFee(float $amount): float
    {
        return $this->transaction_fee ?? 0;
    }

    public function isAvailableForPayment(\DateTime $paymentDate): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // Check cutoff time for same-day settlement
        if ($this->settlement_speed === 'same_day' && $this->cutoff_time) {
            $now = now();
            $cutoff = Carbon::parse($this->cutoff_time);
            
            if ($now->greaterThan($cutoff)) {
                return false;
            }
        }

        return true;
    }
}
```

**Result:** Model created successfully with all relationships, scopes, and helper methods.

**Action:** COMPLETED

---

#### Subtask 2.1.2: Create EmployeePaymentPreference Model ✅ COMPLETED
**File:** `app/Models/EmployeePaymentPreference.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EmployeePaymentPreference extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'payment_method_id',
        'is_primary',
        'priority',
        'bank_code',
        'bank_name',
        'branch_code',
        'branch_name',
        'account_number',
        'account_name',
        'account_type',
        'ewallet_provider',
        'ewallet_account_number',
        'ewallet_account_name',
        'verification_status',
        'verified_at',
        'verified_by',
        'verification_notes',
        'document_type',
        'document_path',
        'document_uploaded_at',
        'is_active',
        'last_used_at',
        'successful_payments',
        'failed_payments',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'priority' => 'integer',
        'verified_at' => 'datetime',
        'document_uploaded_at' => 'datetime',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'successful_payments' => 'integer',
        'failed_payments' => 'integer',
    ];

    protected $hidden = [
        'account_number', // Sensitive data
        'ewallet_account_number', // Sensitive data
    ];

    // ============================================================
    // Relationships
    // ============================================================

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }

    // ============================================================
    // Accessors & Mutators
    // ============================================================

    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    protected function ewalletAccountNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function maskAccountNumber(): string
    {
        if (!$this->account_number) {
            return 'N/A';
        }

        $account = $this->account_number;
        $length = strlen($account);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($account, -4);
    }

    public function getDisplayName(): string
    {
        $method = $this->paymentMethod->display_name;
        $masked = $this->maskAccountNumber();

        return "{$method} - {$masked}";
    }

    public function recordSuccess(): void
    {
        $this->increment('successful_payments');
        $this->update(['last_used_at' => now()]);
    }

    public function recordFailure(): void
    {
        $this->increment('failed_payments');
    }
}
```

**Result:** Model created successfully with encrypted account number fields, scopes, and verification helper methods.

**Action:** COMPLETED

---

#### Subtask 2.1.3: Create PayrollPayment Model ✅ COMPLETED
**File:** `app/Models/PayrollPayment.php`

**Purpose:** Central payment record — one row per employee per payroll period across all payment methods.

**Key Features:**
- Fillable: employee_id, payroll_period_id, employee_payroll_calculation_id, payment_method_id, period/payment dates, gross_pay, net_pay, all deduction breakdowns (sss/philhealth/pagibig/tax/loan/advance/leave/attendance/other), payment_reference, batch_number, bank fields, ewallet fields, cash/envelope fields, status, retry fields, provider_response, audit FKs
- Casts: all decimals:2, dates, integer(retry_count), array(provider_response), datetimes
- Relationships: employee, payrollPeriod, employeePayrollCalculation, paymentMethod, preparedBy, approvedBy, releasedBy (User), payslip (HasOne), auditLogs (MorphMany)
- Scopes: pending, processing, paid, failed, unclaimed, byCash, byBank, byEwallet, byPeriod, byBatch
- Methods: isPaid(), isFailed(), isPending(), isProcessing(), isUnclaimed(), isCash(), isBank(), isEwallet(), canRetry() (max 3 per Decision #12), markAsPaid(), markAsFailed(), getTotalDeductions()

**Result:** Model created successfully.

**Action:** COMPLETED

---

#### Subtask 2.1.4: Create BankFileBatch Model ✅ COMPLETED
**File:** `app/Models/BankFileBatch.php`

**Purpose:** Tracks bank file generation, validation, and submission for Metrobank (InstaPay) and BDO (PESONet) payrolls.

**Key Features:**
- Fillable: payroll_period_id, payment_method_id, batch_number, batch_name, payment_date, bank_code, bank_name, transfer_type (instapay/pesonet/internal), file fields (name/path/format/size/hash), totals (employees/amount/successful/failed/fees), settlement fields, status, timestamps (submitted_at/completed_at/validated_at), is_validated, validation_errors, bank_response, bank_confirmation_number, audit FKs
- Casts: dates, decimals:2, integers, boolean(is_validated), array(validation_errors), datetimes
- Relationships: payrollPeriod, paymentMethod, generatedBy, submittedBy (User), payments (HasMany via batch_number), auditLogs (MorphMany)
- Scopes: draft, ready, submitted, completed, failed, byBank, byPeriod
- Methods: isDraft(), isReady(), isSubmitted(), isCompleted(), isFailed(), isInstapay(), isPesonet(), canSubmit() (requires is_validated + ready status), hasValidationErrors(), getSuccessRate()

**Result:** Model created successfully.

**Action:** COMPLETED

---

#### Subtask 2.1.5: Create CashDistributionBatch Model ✅ COMPLETED
**File:** `app/Models/CashDistributionBatch.php`

**Purpose:** Accountability tracker for physical cash distribution — envelopes, dual verification, distribution log, unclaimed handling.

**Key Features:**
- Fillable: payroll_period_id, batch_number, distribution_date, distribution_location, total_cash_amount, total_employees, denomination_breakdown, withdrawal fields (source/reference/date/withdrawn_by), dual-verification fields (counted_by/witnessed_by/verification_at/notes), distribution tracking (envelopes_prepared/distributed/unclaimed, amount_distributed/unclaimed), log_sheet_path, distribution timestamps, unclaimed handling (deadline/disposition/redeposit fields), status, accountability_report fields, notes, prepared_by
- Casts: dates, decimals:2, integers, array(denomination_breakdown), datetimes
- Relationships: payrollPeriod, withdrawnBy, countedBy, witnessedBy, preparedBy, reportApprovedBy (User), payments (HasMany via batch_number), auditLogs (MorphMany)
- Scopes: preparing, ready, distributing, completed, reconciled, byPeriod
- Methods: isVerified() (both counted_by AND witnessed_by set — Decision #8), isPreparing(), isReady(), isDistributing(), isCompleted(), isReconciled(), canStartDistribution(), hasUnclaimed(), getUnclaimedAmount(), isUnclaimedDeadlinePassed()

**Result:** Model created successfully.

**Action:** COMPLETED

---

#### Subtask 2.1.6: Create Payslip Model ✅ COMPLETED
**File:** `app/Models/Payslip.php`

**Purpose:** DOLE-compliant payslip records with JSON earnings/deductions, YTD summaries, QR verification, and digital distribution tracking.

**Key Features:**
- Fillable: employee_id, payroll_period_id, payroll_payment_id, payslip_number, period/payment dates, employee snapshot (number/name/department/position), gov numbers snapshot (sss/philhealth/pagibig/tin), earnings_data, total_earnings, deductions_data, total_deductions, net_pay, leave_summary, attendance_summary, YTD fields (gross/tax/sss/philhealth/pagibig/net), file fields (path/format/size/hash), distribution_method (email/portal/print/sms), distributed_at, is_viewed, viewed_at, signature_hash, qr_code_data, status, notes, generated_by
- Casts: dates, decimals:2, integer(file_size), array (earnings_data/deductions_data/leave_summary/attendance_summary), boolean(is_viewed), datetimes
- Relationships: employee, payrollPeriod, payrollPayment, generatedBy (User)
- Scopes: draft, generated, distributed, acknowledged, viewed, byEmployee, byPeriod
- Methods: isDraft(), isGenerated(), isDistributed(), isAcknowledged(), markAsViewed(), getEarningsBreakdown(), getDeductionsBreakdown(), generateQrData() (encodes payslip_number + signature_hash per Decision #15), getYtdSummary()

**Result:** Model created successfully.

**Action:** COMPLETED

---

#### Subtask 2.1.7: Create PaymentAuditLog Model ✅ COMPLETED
**File:** `app/Models/PaymentAuditLog.php`

**Purpose:** Immutable audit trail for all payment actions. No SoftDeletes — 7-year BIR retention (Decision #21). Archival via `php artisan payroll:archive-audit-logs`.

**Key Features:**
- Fillable: auditable_type, auditable_id (polymorphic), action (50 chars), actor_type (user/system/webhook), actor_id, actor_name, old_values, new_values, metadata, ip_address, user_agent, request_id, notes
- Casts: array (old_values/new_values/metadata), integer(actor_id)
- NO SoftDeletes (intentional — audit records must not be deleted)
- Relationships: auditable (MorphTo)
- Scopes: byAction, byActor, byAuditable, recent, forAuditTrail
- Methods: isSystemAction(), isWebhookAction(), isUserAction(), getActorLabel()
- Static: `PaymentAuditLog::record($model, $action, $actorType, $actorId, $actorName, $oldValues, $newValues, $metadata, $notes)` — convenience factory method

**Result:** Model created successfully.

**Action:** COMPLETED

---

## 📊 Decisions, Architecture & Implementation Notes

> **Status:** All clarifications resolved ✅ — Implementation proceeding based on confirmed decisions below.

### ✅ Confirmed Decisions

#### Payment Methods & Configuration

1. **Default Payment Methods**
   - **Decision:** Cash enabled by default (current primary). GCash and Maya also registered in the seeder but disabled with `is_enabled = false` and `requires_employee_setup = true`. Banks (Metrobank, BDO) remain disabled. Office Admin must flip `is_enabled = true` and employee must complete verification before any digital method can process payments.
   - **Implementation Impact:** Seeder must register GCash and Maya. `PaymentMethod` validation must check `is_enabled` AND `employee_payment_preferences.verification_status = 'verified'` before allowing payout.

2. **Check Payment Support**
   - **Decision:** Deferred to Phase 4. Not in scope for Phases 1–3. Track as enhancement if client demand arises.

3. **Supported Banks Beyond Metrobank & BDO**
   - **Decision:** Phase 2 supports Metrobank (InstaPay) and BDO (PESONet). GCash and Maya are covered by PayMongo in Phase 3. BPI, UnionBank, LandBank, and Security Bank can be added in Phase 4 if needed by extending the bank file generator with their specific formats.

#### PayMongo Integration

4. **PayMongo Account**
   - **Decision:** No existing account. Use PayMongo test mode (test API keys) throughout development. Switch to production keys only after manual QA sign-off. Store credentials in `.env` — never hardcoded.
   - **Config keys:** `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET`

5. **PayMongo Features to Implement**
   - **Decision:** Phase 3 implements Disbursements API for GCash and Maya payouts only. Bank transfers via PayMongo deferred to Phase 4 pending evaluation of direct bank file ROI.

6. **PayMongo Webhook Verification**
   - **Decision:** Strict verification — reject unsigned webhooks. Log all failed verification attempts (IP, timestamp, payload hash) to `payment_audit_logs` for monitoring. Do not silently accept unsigned webhooks.

#### Cash Distribution

7. **Unclaimed Salary Handling (after 30 days)**
   - **Decision:** Manual disposition — Payroll Officer selects one of: `re-deposited`, `held`, or `added_to_next_period`. `cash_distribution_batches.unclaimed_disposition` stores the choice. Automation may be added later based on volume.

8. **Cash Distribution Verification**
   - **Decision:** Dual verification required — Payroll Officer counts cash, Office Admin witnesses. Both must sign off before `status` advances to `distributing`. This is enforced at the application layer, not just a UI convention.

9. **Envelope Photos**
   - **Decision:** Phase 1 — employee number, name, department, net amount, denomination breakdown only. Photos deferred to Phase 4 after basic functionality is stable.

#### Bank File Generation

10. **Bank File Format Versions**
    - **Decision:** Use latest 2024 formats for Metrobank and BDO. Start with current formats and only add legacy support if a specific client request warrants it.

11. **Bank File Validation Before Generation**
    - **Decision:** Mandatory pre-generation validation — validate account numbers, account names, and amounts. Validation errors must surface to the UI before any file is written to disk.

12. **Failed Bank Transfer Handling**
    - **Decision:** Auto-retry up to 3 times with exponential backoff. After 3 failures, mark as `failed` and alert Payroll Officer for manual intervention. Log each retry attempt in `payment_audit_logs`.

#### Payslip Generation

13. **Year-to-Date Summaries**
    - **Decision:** Include YTD in all payslips — gross pay, withholding tax, SSS, PhilHealth, Pag-IBIG, and net pay. Required for BIR Form 2316 compliance.

14. **Payslip Format**
    - **Decision:** PDF generation (primary) using a DOLE-compliant template. Excel export added in Phase 4 as an optional feature per employee/admin request.

15. **QR Codes on Payslips**
    - **Decision:** QR code included on all generated payslips. Encodes a verification URL linking to `payslip_number` + `signature_hash` for authenticity check. Made toggleable via system settings later.

16. **Payslip Distribution**
    - **Decision:** Hybrid delivery — email PDF to employees with a registered email who opt in, otherwise generate print-ready PDF. Distribution method stored in `payslips.distribution_method`. Employee portal access deferred to Phase 4.

#### Leave Integration

17. **Unpaid Leave Deduction Calculation**
    - **Decision:** `(basic_salary / working_days_per_month) × unpaid_leave_days`. Basic salary only — not gross. Aligned with Philippine DOLE standard practice. Configurable constant for `working_days_per_month` (default: 22). Pro-rated gross method deferred for future evaluation.
    - **Reference:** PAYROLL-LEAVE-INTEGRATION-ROADMAP.md

18. **Government Contributions During Leave**
    - **Decision:** Full-rate government contributions (SSS, PhilHealth, Pag-IBIG) by default regardless of leave type. Rate adjustability per leave type and per company policy is configurable via `system_settings`. This ensures compliance with minimum statutory requirements while allowing flexibility.

#### Timekeeping Integration

19. **Tardiness Deduction Calculation**
    - **Decision:** `hourly_rate × hours_late` rounded to 15-minute intervals. This is the equitable and transparent approach — directly proportional to time lost. Fixed penalties deferred unless client specifically requests.
    - **Reference:** PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md

20. **Absence Deduction**
    - **Decision:** Full day deduction for unexcused absences in Phase 1. `daily_rate × absent_days`. Hourly deduction based on shift length deferred to Phase 4 after operational feedback on edge cases.

#### Payment Tracking & Audit

21. **Audit Log Retention**
    - **Decision:** Retain payment audit logs for **7 years** (aligned with BIR requirement). After 7 years, archive to cold storage or anonymize. Implement an artisan command `payroll:archive-audit-logs --before=YYYY-MM-DD` for scheduled archival.

22. **Payment Status Change Notifications**
    - **Decision:** Notify on critical status changes only — payment failures, approvals required, unclaimed salary deadlines. Payroll Officer and Office Admin are notified by default. Users can customize notification preferences via their profile settings.

23. **Employee Payment History View**
    - **Decision:** Deferred to Phase 4 (employee portal). Phase 1–3 is admin/payroll-officer-facing only. Employee payslip access via the existing `Employee/Payslips/Index.tsx` portal page will be wired up in Phase 4.

#### Security & Compliance

24. **Bank Account Number Encryption**
    - **Decision:** Use Laravel's built-in `encrypt()` / `decrypt()` (AES-256-CBC) via `Attribute` casts in the model. Application key (APP_KEY) must be rotated on compromise. All access to encrypted fields logged in `payment_audit_logs`.

25. **Payment Method Configuration Approval**
    - **Decision:** Office Admin configures payment methods; Superadmin must approve activation. This prevents unauthorized financial method changes. Approval workflow integrated with existing `ApprovalWorkflow` system.

26. **Bulk Payment Batch Approval**
    - **Decision:** HR Manager reviews, Office Admin approves before any batch payment executes. This matches the existing payroll approval matrix. Exception: cash batches under ₱500,000 can be approved by the Office Admin alone (configurable threshold in `system_settings`).

#### Performance & Scalability

27. **Bank File Generation — Queued**
    - **Decision:** Queue bank file generation using **Laravel Queue** for ALL payrolls (not just >100 employees). User receives a progress indicator and a notification when the file is ready for download. Use `BankFileGenerationJob` dispatched from the controller.

28. **Payslip Generation — Batched**
    - **Decision:** Generate payslips in chunks of **50** using `Laravel Bus::batch()`. Show real-time progress bar in the UI via polling or server-sent events. Chunk size is configurable via `system_settings`.

29. **Payment Tracking — Caching**
    - **Decision:** Cache payment status summary for 5 minutes using `Cache::remember()`. Show "last updated" timestamp in the UI. Provide a manual refresh button that busts the cache. Individual payment details always query the database directly.

30. **Payment Method Failover**
    - **Decision:** Implement failover — if a bank/e-wallet transfer fails after 3 retries, the payment is flagged for **manual cash fallback**. Payroll Officer receives an alert with a list of affected employees. No automatic conversion to cash — human decision required for audit trail integrity.

---

### ✅ Architecture & Implementation Directives

> All items below are **adopted** — implementation must follow these decisions.

#### Rollout Strategy

1. **Phased Rollout — CONFIRMED**
   - **Phase 1 (Week 1-2):** Database schema + Cash distribution (current primary)
   - **Phase 2 (Week 2-3):** Bank file generation (Metrobank InstaPay, BDO PESONet)
   - **Phase 3 (Week 3-4):** PayMongo integration (GCash, Maya via Disbursements API, test mode)
   - **Phase 4 (Post-MVP):** Excel payslips, employee portal, BPI/UnionBank support, check payments, envelope photos, hourly absence deductions

2. **Cash Distribution First** — Build and stabilize cash flow before implementing digital channels. No feature gates that require digital to be set up first.

3. **Bank Files: Small Batch Testing** — Generate and validate format with 5–10 employees against actual bank portal before enabling full payroll run.

4. **PayMongo Test Mode** — Use `PAYMONGO_ENV=sandbox` until manual QA sign-off. Controlled environment switch via `.env` only.

#### Technical Architecture (Adopted)

5. **Event-Driven Payment Updates** — Fire `PaymentProcessed`, `PaymentFailed`, `PaymentRetried` events. Listeners handle audit logging, notifications, and retry queuing. Implemented in `app/Events/Payment/` and `app/Listeners/Payment/`.

6. **Queue All Bank & E-wallet Transactions** — No synchronous external API calls. All PayMongo and bank file operations dispatched as Laravel jobs. Use `database` driver in development, `redis` in production.

7. **Payment Reconciliation Tool** — Phase 2 deliverable. UI in `Payments/BankFiles/` with batch-level comparison between expected amounts and bank confirmation figures. Flag discrepancies with an actionable alert.

8. **Laravel Batch for Bulk Payments** — Use `Bus::batch()` for processing 100+ employee payments. Expose batch progress via the existing `payment-tracking-table` component. Each employee payment is an individual `ProcessEmployeePaymentJob`.

#### Security (Adopted)

9. **Encrypt Sensitive Fields** — `account_number` and `ewallet_account_number` in `EmployeePaymentPreference` model use `Attribute` casts with `encrypt()`/`decrypt()`. API keys stored only in `.env`, never the database.

10. **Payment Approval Workflow** — Required for all non-cash payments. Payroll Officer prepares → HR Manager reviews → Office Admin approves. Cash batches under the configured `payroll.cash_approval_threshold` (default ₱500,000) skip Office Admin. Enforced at `PaymentBatchApprovalService`.

11. **Payment Amount Limits** — Enforced at application validation layer:
    - Per employee max: `₱100,000` (configurable via `system_settings`)
    - Per batch max: `₱5,000,000` (configurable)
    - Daily total max: `₱10,000,000` (configurable)

12. **2FA for High-Value Approvals** — Office Admin and Superadmin must pass 2FA challenge when approving payments over ₱1,000,000 or enabling new payment methods. Leverages the existing Fortify 2FA stack already in the application.

#### Integration (Adopted)

13. **Leave Events** — Listen to `LeaveApproved`, `LeaveRejected`, `LeaveCancelled` events. Trigger `RecalculateNetPayJob` if the leave period overlaps the current open payroll period. Reference: `PAYROLL-LEAVE-INTEGRATION-ROADMAP.md`.

14. **Timekeeping Daily Poll** — Schedule `SyncAttendanceDeductionsJob` daily at 23:59 via Laravel Scheduler. Source: `daily_attendance_summaries` table. Reference: `PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md`.

15. **Government Contributions Sync** — Pull finalized SSS/PhilHealth/Pag-IBIG/BIR deductions from the government module after `payroll_period.status = 'finalized'`. Deductions are immutable after this point unless a correction period is opened.

#### User Experience (Adopted)

16. **Payment Status Dashboard** — Existing `Payments/Tracking/Index.tsx` wired to real data. Show: total to pay, method breakdown (cash/bank/e-wallet), failed count, unclaimed count, and last-updated cache timestamp.

17. **Bulk Payment Actions** — Multi-select checkboxes in `payment-tracking-table` component. Bulk actions: retry failed, cancel pending, mark as paid manually (with required note). Handled via existing `payment-action-modals.tsx`.

18. **Employee Search & Export in Payment Tracking** — Filter by employee number, name, department, status, method, date range. Export filtered results to CSV/Excel via `PaymentTrackingController@export`.

#### Testing (Adopted)

19. **Payment Test Scenarios** — The following scenarios must be covered before Phase completion sign-off:
    1. Full cash distribution cycle (prepare → distribute → sign → close)
    2. Bank file generation → validation → download
    3. (Phase 3) PayMongo GCash disbursement success
    4. (Phase 3) PayMongo disbursement failure → 3 retries → manual intervention
    5. Unclaimed salary after 30 days → manual disposition
    Each scenario tested with ≥10 mock employees.

20. **Mock-First Development** — Keep existing mock controller responses working throughout development. Replace mock data with real service layer incrementally, controller-by-controller. This prevents frontend breakage during backend implementation.

---

### 🎨 Post-MVP Enhancements (Phase 4+)

| Enhancement | Description | Target Phase |
|------------|-------------|--------------|
| Payslip Email Templates | HTML email + PDF attachment, DOLE-compliant layout | Phase 4 |
| SMS Payment Notifications | "Salary deposited to [bank]" via SMS API | Phase 4 |
| Excel Payslip Export | Optional Excel download of payslip data | Phase 4 |
| Employee Payment History Portal | Employees view payment records via self-service | Phase 4 |
| Envelope Employee Photos | Photos on salary envelopes for identification | Phase 4 |
| Hourly Absence Deductions | Deduct based on shift hours rather than full day | Phase 4 |
| Check Payment Support | Check disbursement + reconciliation module | Phase 4 (if demand) |
| BPI / UnionBank / LandBank / SecBank | Additional bank file format support | Phase 4 |
| PayMongo Bank Transfers | Bank account transfers via PayMongo API | Phase 4 |
| Payment Analytics Dashboard | Processing time, failure rate, cost per transaction | Phase 5 |
| Payment Scheduling | Auto-process payments on scheduled pay date | Phase 5 |
| Multi-Currency Support | Forex, international employees | Phase 6+ |

---

### 🔗 Related Implementation Plans

This Payments plan must coordinate with:

1. **[PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md](./PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md)**
   - **Dependency:** Government deductions must be calculated before net pay
   - **Data flow:** Government contributions → Total deductions → Net pay → Payment

2. **[PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md](./PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md)**
   - **Dependency:** Employee bank details stored in `employee_payroll_info`
   - **Data flow:** Bank account details → Payment preferences → Payment execution

3. **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)**
   - **Event:** Listen to `LeaveApproved` event for unpaid leave deductions
   - **Calculation:** Deduct (daily rate × unpaid days) from net pay

4. **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)**
   - **Event:** Listen to `AttendanceSummaryUpdated` event
   - **Calculation:** Deduct absences and tardiness from net pay

---

### ⚠️ Critical Success Factors

For this implementation to succeed, we must:

1. ✅ **Complete Government module first** (for accurate deductions)
2. ✅ **Verify bank file formats** with actual banks before full rollout
3. ✅ **Test PayMongo webhooks** thoroughly (payment confirmations critical)
4. ✅ **Implement robust error handling** (payment failures can't be ignored)
5. ✅ **Add comprehensive audit logging** (financial transactions require full traceability)
6. ✅ **Train Payroll Officer** on all payment methods before go-live
7. ✅ **Prepare rollback plan** if digital payments fail (fall back to cash)

---

## 📝 Next Steps

1. ✅ ~~Review this plan and answer clarification questions~~ — All 30 decisions confirmed
2. ✅ ~~Confirm payment method priority~~ — Cash → Bank → E-wallet (GCash/Maya via PayMongo)
3. 🔄 **Create PayMongo test account** — Register at [paymongo.com](https://paymongo.com), obtain sandbox keys, add to `.env`
4. ✅ ~~Approve Phase 1 database schema~~ — All 7 payment tables created, constraint fix applied, seeder ran (5 records)
5. ✅ ~~Execute remaining Phase 1 subtasks~~ — Subtasks 1.1.5–1.1.8, 1.2.1–1.2.2 all COMPLETE
6. ✅ ~~Implement `PaymentMethodsSeeder`~~ — 5 records seeded: Cash (enabled), Metrobank/BDO/GCash/Maya (disabled)
7. ✅ ~~**Phase 2: Create 7 Eloquent models**~~ — PaymentMethod, EmployeePaymentPreference, PayrollPayment, BankFileBatch, CashDistributionBatch, Payslip, PaymentAuditLog — ALL COMPLETE
8. 🔄 **Phase 3: Services & Business Logic** — PaymentService, BankFileGeneratorService, CashDistributionService, PayslipGeneratorService

---

**Plan Status:** 🟢 **ACTIVE — Phase 1 & 2 complete. Phase 3 (Services) is next.**

**Last Updated:** February 19, 2026  
**Phase 1:** ✅ COMPLETE — All 7 migrations ran, constraint fix applied, seeder successful (5 payment methods)  
**Phase 2:** ✅ COMPLETE — All 7 Eloquent models created (PaymentMethod, EmployeePaymentPreference, PayrollPayment, BankFileBatch, CashDistributionBatch, Payslip, PaymentAuditLog)  
**Current Focus:** Phase 3 — Services & Business Logic

