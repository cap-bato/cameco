# Payroll Module - Government Feature Implementation Plan

**Feature:** Government Contributions & Compliance Management  
**Status:** Planning → Implementation  
**Priority:** HIGH  
**Created:** February 6, 2026  
**Estimated Duration:** 4-5 weeks  
**Target Users:** Payroll Officer, Office Admin  
**Dependencies:** EmployeePayroll (payroll info, government numbers), PayrollProcessing (calculations), HR Module (employees)

---

## 📚 Reference Documentation

This implementation plan is based on the following specifications and documentation:

### Core Specifications
- **[PAYROLL_MODULE_ARCHITECTURE.md](../docs/PAYROLL_MODULE_ARCHITECTURE.md)** - Complete Philippine payroll architecture with government compliance
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Payroll calculation integration
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Leave integration for payroll
- **[PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md](./PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md)** - Employee payroll info foundation
- **[payroll-processing.md](../docs/workflows/processes/payroll-processing.md)** - Complete payroll workflow
- **[government-remittances.md](../docs/workflows/processes/government-remittances.md)** - Government remittance procedures
- **[05-payroll-officer-workflow.md](../docs/workflows/05-payroll-officer-workflow.md)** - Payroll officer responsibilities

### Existing Code References
- **Frontend:** `resources/js/pages/Payroll/Government/*` (BIR, SSS, PhilHealth, PagIbig, Remittances)
- **Controllers:** `app/Http/Controllers/Payroll/Government/*` (all have mock data)
- **Components:** `resources/js/components/payroll/government/*` and `resources/js/components/payroll/*` (contribution tables, report generators)
- **Routes:** `routes/payroll.php` (Government section)

### Philippine Government Regulations
- **SSS:** RA 11199 (Social Security Act of 2018), SSS Contribution Tables
- **PhilHealth:** RA 11223 (Universal Health Care Act), PhilHealth Contribution Tables
- **Pag-IBIG:** RA 9679 (Home Development Mutual Fund Law), Pag-IBIG Contribution Tables
- **BIR:** TRAIN Law (RA 10963), BIR Revenue Regulations, Tax Tables    

---

## 📋 Executive Summary

**Current State:**
- ✅ **Frontend Pages:** Complete with mock data (BIR, SSS, PhilHealth, PagIbig, Remittances)
- ✅ **Controllers:** Basic structure with mock data (BIRController, SSSController, PhilHealthController, PagIbigController, GovernmentRemittancesController)
- ✅ **Routes:** All routes registered in payroll.php
- ❌ **Database Schema:** No government contribution tables exist
- ❌ **Models:** No Eloquent models for contributions
- ❌ **Services:** No calculation services (SSS bracket lookup, PhilHealth premium, BIR tax, Pag-IBIG)
- ❌ **Integration:** No connection to EmployeePayroll or PayrollProcessing

**Goal:** Build complete government contributions and compliance system that:
1. Calculates SSS contributions based on salary brackets (13% total: 4.5% EE + 8.5% ER + 0% EC)
2. Calculates PhilHealth premiums (5% of basic salary with min/max limits)
3. Calculates Pag-IBIG contributions (1-2% with ₱100/month ceiling)
4. Calculates BIR withholding tax (progressive tax brackets based on tax status)
5. Generates government reports (SSS R3, PhilHealth RF-1, Pag-IBIG MCRF, BIR 1601C/2316/Alphalist)
6. Tracks remittances and deadlines
7. Integrates with PayrollProcessing for automatic deductions

**Timeline:** 4-5 weeks (February 6 - March 5, 2026)

---

## 🎯 Feature Overview

### What is Government Contributions Management?

The Government module manages Philippine statutory contributions and tax compliance:

1. **SSS (Social Security System)** - Mandatory social insurance (13% total contribution)
   - Employee share: 4.5% of monthly compensation
   - Employer share: 8.5% of monthly compensation
   - EC (Employees' Compensation): ₱10-30 based on bracket
   - Contribution brackets: ₱4,250 - ₱30,000+ (Range A-L)
   - Monthly remittance via SSS R3 Report

2. **PhilHealth (Philippine Health Insurance)** - Universal health coverage
   - Premium rate: 5% of basic salary (split 50-50 EE/ER)
   - Minimum: ₱500/month (₱10,000 basic salary)
   - Maximum: ₱5,000/month (₱100,000 basic salary)
   - Quarterly remittance via RF-1 Form

3. **Pag-IBIG (Home Development Mutual Fund)** - Housing fund
   - ≤ ₱1,500 salary: 1% EE + 2% ER
   - > ₱1,500 salary: 2% EE + 2% ER
   - Employee ceiling: ₱100/month
   - Monthly remittance via MCRF (Member Contribution Remittance Form)

4. **BIR (Bureau of Internal Revenue)** - Withholding tax
   - Progressive tax brackets (0%, 15%, 20%, 25%, 30%, 32%, 35%)
   - Based on tax status (Z, S, ME, S1-S4, ME1-ME4)
   - De minimis benefits exemption
   - 13th month pay exemption (up to ₱90,000)
   - Monthly remittance via 1601C, Annual via 2316 & Alphalist

---

## 🗄️ Database Schema Design

### Tables Overview

| Table | Purpose | Dependencies |
|-------|---------|--------------|
| `government_contribution_rates` | SSS/PhilHealth/PagIbig rate tables | None |
| `tax_brackets` | BIR tax brackets by status | None |
| `employee_government_contributions` | Per-period contributions per employee | `employee_payroll_info`, `payroll_periods` |
| `government_remittances` | Remittance tracking and payment | `payroll_periods` |
| `government_reports` | Generated reports (R3, RF1, 1601C, etc.) | `payroll_periods` |

---

## 🚀 Implementation Phases

## **Phase 1: Database Foundation (Week 1: Feb 6-12)**

### Task 1.1: Create Database Migrations

#### Subtask 1.1.1: Create government_contribution_rates Migration
**File:** `database/migrations/YYYY_MM_DD_create_government_contribution_rates_table.php`

**Purpose:** Store Philippine government contribution rate tables (SSS brackets, PhilHealth min/max, Pag-IBIG rates)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_contribution_rates', function (Blueprint $table) {
            $table->id();
            
            // Rate Type
            $table->enum('agency', ['sss', 'philhealth', 'pagibig'])->index();
            $table->string('rate_type', 50); // 'bracket', 'premium_rate', 'contribution_rate'
            
            // SSS Bracket Information
            $table->string('bracket_code', 10)->nullable(); // 'A', 'B', 'C', etc.
            $table->decimal('compensation_min', 10, 2)->nullable();
            $table->decimal('compensation_max', 10, 2)->nullable();
            $table->decimal('monthly_salary_credit', 10, 2)->nullable(); // MSC
            
            // Contribution Rates (as percentages)
            $table->decimal('employee_rate', 5, 2)->nullable(); // e.g., 4.50 for 4.5%
            $table->decimal('employer_rate', 5, 2)->nullable(); // e.g., 8.50 for 8.5%
            $table->decimal('total_rate', 5, 2)->nullable();
            
            // Fixed Amounts
            $table->decimal('employee_amount', 8, 2)->nullable();
            $table->decimal('employer_amount', 8, 2)->nullable();
            $table->decimal('ec_amount', 8, 2)->nullable(); // SSS EC contribution
            $table->decimal('total_amount', 8, 2)->nullable();
            
            // PhilHealth/Pag-IBIG Limits
            $table->decimal('minimum_contribution', 8, 2)->nullable();
            $table->decimal('maximum_contribution', 8, 2)->nullable();
            $table->decimal('premium_ceiling', 10, 2)->nullable(); // PhilHealth ₱100k
            $table->decimal('contribution_ceiling', 8, 2)->nullable(); // Pag-IBIG ₱100
            
            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['agency', 'is_active', 'effective_from']);
            $table->index(['bracket_code', 'agency']);
            $table->index(['compensation_min', 'compensation_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_contribution_rates');
    }
};
```

**Dependencies:** `users`

**Action:** CREATE

---

#### Subtask 1.1.2: Create tax_brackets Migration
**File:** `database/migrations/YYYY_MM_DD_create_tax_brackets_table.php`

**Purpose:** Store BIR tax brackets per tax status (Z, S, ME, S1-S4, ME1-ME4)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            
            // Tax Status
            $table->string('tax_status', 10)->index(); // Z, S, ME, S1, ME1, etc.
            $table->string('status_description', 100);
            
            // Bracket Information
            $table->integer('bracket_level')->default(1); // 1, 2, 3, 4, 5, 6, 7
            $table->decimal('income_from', 12, 2); // Annualized taxable income
            $table->decimal('income_to', 12, 2)->nullable(); // NULL = no upper limit
            
            // Tax Calculation
            $table->decimal('base_tax', 10, 2)->default(0); // Fixed tax for bracket
            $table->decimal('tax_rate', 5, 2)->default(0); // Percentage (0-35)
            $table->decimal('excess_over', 12, 2)->default(0); // Amount to subtract from income
            
            // Exemptions (TRAIN Law)
            $table->decimal('personal_exemption', 10, 2)->default(50000); // ₱50k standard
            $table->decimal('additional_exemption', 10, 2)->default(25000); // ₱25k per dependent
            $table->integer('max_dependents')->default(4);
            
            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tax_status', 'is_active', 'effective_from']);
            $table->index(['income_from', 'income_to']);
            $table->index('bracket_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
```

**Dependencies:** None

**Action:** CREATE

---

#### Subtask 1.1.3: Create employee_government_contributions Migration
**File:** `database/migrations/YYYY_MM_DD_create_employee_government_contributions_table.php`

**Purpose:** Store per-employee per-period government contributions (SSS, PhilHealth, Pag-IBIG, Tax)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_government_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_payroll_calculation_id')->nullable()->constrained()->nullOnDelete();
            
            // Period Information
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_month', 7); // YYYY-MM format
            
            // Compensation Basis
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('gross_compensation', 10, 2); // For PhilHealth/SSS
            $table->decimal('taxable_income', 10, 2); // For BIR
            
            // SSS Contribution
            $table->string('sss_number')->nullable();
            $table->string('sss_bracket', 10)->nullable(); // A, B, C, etc.
            $table->decimal('sss_monthly_salary_credit', 10, 2)->nullable();
            $table->decimal('sss_employee_contribution', 8, 2)->default(0);
            $table->decimal('sss_employer_contribution', 8, 2)->default(0);
            $table->decimal('sss_ec_contribution', 8, 2)->default(0);
            $table->decimal('sss_total_contribution', 8, 2)->default(0);
            $table->boolean('is_sss_exempted')->default(false);
            
            // PhilHealth Contribution
            $table->string('philhealth_number')->nullable();
            $table->decimal('philhealth_premium_base', 10, 2)->nullable();
            $table->decimal('philhealth_employee_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_employer_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_total_contribution', 8, 2)->default(0);
            $table->boolean('is_philhealth_exempted')->default(false);
            
            // Pag-IBIG Contribution
            $table->string('pagibig_number')->nullable();
            $table->decimal('pagibig_compensation_base', 10, 2)->nullable();
            $table->decimal('pagibig_employee_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_employer_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_total_contribution', 8, 2)->default(0);
            $table->boolean('is_pagibig_exempted')->default(false);
            
            // BIR Withholding Tax
            $table->string('tin')->nullable();
            $table->string('tax_status', 10)->nullable();
            $table->decimal('annualized_taxable_income', 12, 2)->nullable();
            $table->decimal('tax_due', 10, 2)->default(0);
            $table->decimal('withholding_tax', 10, 2)->default(0);
            $table->decimal('tax_already_withheld_ytd', 10, 2)->default(0);
            $table->boolean('is_minimum_wage_earner')->default(false);
            $table->boolean('is_substituted_filing')->default(false);
            
            // De minimis and Exemptions
            $table->decimal('deminimis_benefits', 8, 2)->default(0);
            $table->decimal('thirteenth_month_pay', 10, 2)->default(0);
            $table->decimal('other_tax_exempt_compensation', 10, 2)->default(0);
            
            // Totals
            $table->decimal('total_employee_contributions', 10, 2)->default(0);
            $table->decimal('total_employer_contributions', 10, 2)->default(0);
            $table->decimal('total_statutory_deductions', 10, 2)->default(0);
            
            // Processing Status
            $table->enum('status', ['pending', 'calculated', 'processed', 'remitted'])->default('pending');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['period_month', 'status']);
            $table->index('sss_number');
            $table->index('philhealth_number');
            $table->index('pagibig_number');
            $table->index('tin');
            $table->unique(['employee_id', 'payroll_period_id'], 'unique_employee_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_government_contributions');
    }
};
```

**Dependencies:** `employees`, `payroll_periods`, `employee_payroll_calculations`, `users`

**Action:** CREATE

---

#### Subtask 1.1.4: Create government_remittances Migration
**File:** `database/migrations/YYYY_MM_DD_create_government_remittances_table.php`

**Purpose:** Track government remittances and payment status

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Agency Information
            $table->enum('agency', ['sss', 'philhealth', 'pagibig', 'bir'])->index();
            $table->string('remittance_type', 50); // 'monthly', 'quarterly', 'annual'
            
            // Period Information
            $table->string('remittance_month', 7); // YYYY-MM format
            $table->date('period_start');
            $table->date('period_end');
            
            // Amounts
            $table->decimal('employee_share', 12, 2)->default(0);
            $table->decimal('employer_share', 12, 2)->default(0);
            $table->decimal('ec_share', 8, 2)->default(0); // SSS EC
            $table->decimal('total_amount', 12, 2);
            
            // Employee Count
            $table->integer('total_employees')->default(0);
            $table->integer('active_employees')->default(0);
            $table->integer('exempted_employees')->default(0);
            
            // Deadlines
            $table->date('due_date');
            $table->date('submission_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Payment Information
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable(); // 'bank', 'online', 'otc'
            $table->string('bank_name')->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            
            // Penalties
            $table->boolean('has_penalty')->default(false);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->text('penalty_reason')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'ready', 'submitted', 'paid', 'partially_paid', 'overdue'])->default('pending');
            $table->boolean('is_late')->default(false);
            $table->integer('days_overdue')->default(0);
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['agency', 'remittance_month']);
            $table->index(['agency', 'status']);
            $table->index('due_date');
            $table->index(['is_late', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_remittances');
    }
};
```

**Dependencies:** `payroll_periods`, `users`

**Action:** CREATE

---

#### Subtask 1.1.5: Create government_reports Migration
**File:** `database/migrations/YYYY_MM_DD_create_government_reports_table.php`

**Purpose:** Track generated government reports (R3, RF-1, MCRF, 1601C, 2316, Alphalist)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_remittance_id')->nullable()->constrained()->nullOnDelete();
            
            // Report Information
            $table->enum('agency', ['sss', 'philhealth', 'pagibig', 'bir'])->index();
            $table->string('report_type', 50); // 'r3', 'rf1', 'mcrf', '1601c', '2316', 'alphalist'
            $table->string('report_name', 100);
            $table->string('report_period', 50); // e.g., "January 2026"
            
            // File Information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 10); // 'csv', 'dat', 'pdf', 'excel'
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->string('file_hash')->nullable(); // SHA256 hash
            
            // Report Data Summary
            $table->integer('total_employees')->default(0);
            $table->decimal('total_compensation', 12, 2)->default(0);
            $table->decimal('total_employee_share', 12, 2)->default(0);
            $table->decimal('total_employer_share', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            
            // BIR-specific
            $table->string('rdo_code')->nullable();
            $table->decimal('total_tax_withheld', 12, 2)->nullable();
            
            // Submission Information
            $table->enum('status', ['draft', 'ready', 'submitted', 'accepted', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_reference')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Validation
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_errors')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['agency', 'report_type']);
            $table->index(['payroll_period_id', 'agency']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_reports');
    }
};
```

**Dependencies:** `payroll_periods`, `government_remittances`, `users`

**Action:** CREATE

---

#### Subtask 1.1.6: Run Migrations
**Action:** RUN COMMAND

```bash
php artisan migrate
```

**Validation:**
```bash
php artisan db:show --counts
```

Check that all 5 tables are created:
- government_contribution_rates
- tax_brackets
- employee_government_contributions
- government_remittances
- government_reports

---

### Task 1.2: Create Database Seeders

#### Subtask 1.2.1: Create GovernmentContributionRatesSeeder
**File:** `database/seeders/GovernmentContributionRatesSeeder.php`

**Purpose:** Populate SSS brackets, PhilHealth rates, Pag-IBIG rates per Philippine law

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GovernmentContributionRatesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $effectiveFrom = Carbon::parse('2024-01-01');
        
        // ============================================================
        // SSS CONTRIBUTION BRACKETS (2024 Rates - RA 11199)
        // Total: 13% (4.5% EE + 8.5% ER + EC)
        // ============================================================
        
        $sssRates = [
            // Format: [bracket_code, min, max, MSC, EE_amount, ER_amount, EC_amount]
            ['A', 0, 4249.99, 4000, 180, 340, 10],
            ['B', 4250, 4749.99, 4500, 202.50, 382.50, 10],
            ['C', 4750, 5249.99, 5000, 225, 425, 10],
            ['D', 5250, 5749.99, 5500, 247.50, 467.50, 10],
            ['E', 5750, 6249.99, 6000, 270, 510, 10],
            ['F', 6250, 6749.99, 6500, 292.50, 552.50, 10],
            ['G', 6750, 7249.99, 7000, 315, 595, 10],
            ['H', 7250, 7749.99, 7500, 337.50, 637.50, 10],
            ['I', 7750, 8249.99, 8000, 360, 680, 10],
            ['J', 8250, 8749.99, 8500, 382.50, 722.50, 10],
            ['K', 8750, 9249.99, 9000, 405, 765, 10],
            ['L', 9250, 9749.99, 9500, 427.50, 807.50, 10],
            ['M', 9750, 10249.99, 10000, 450, 850, 10],
            ['N', 10250, 10749.99, 10500, 472.50, 892.50, 10],
            ['O', 10750, 11249.99, 11000, 495, 935, 10],
            ['P', 11250, 11749.99, 11500, 517.50, 977.50, 10],
            ['Q', 11750, 12249.99, 12000, 540, 1020, 10],
            ['R', 12250, 12749.99, 12500, 562.50, 1062.50, 20],
            ['S', 12750, 13249.99, 13000, 585, 1105, 20],
            ['T', 13250, 13749.99, 13500, 607.50, 1147.50, 20],
            ['U', 13750, 14249.99, 14000, 630, 1190, 20],
            ['V', 14250, 14749.99, 14500, 652.50, 1232.50, 20],
            ['W', 14750, 15249.99, 15000, 675, 1275, 20],
            ['X', 15250, 15749.99, 15500, 697.50, 1317.50, 20],
            ['Y', 15750, 16249.99, 16000, 720, 1360, 20],
            ['Z', 16250, 16749.99, 16500, 742.50, 1402.50, 30],
            ['AA', 16750, 17249.99, 17000, 765, 1445, 30],
            ['AB', 17250, 17749.99, 17500, 787.50, 1487.50, 30],
            ['AC', 17750, 18249.99, 18000, 810, 1530, 30],
            ['AD', 18250, 18749.99, 18500, 832.50, 1572.50, 30],
            ['AE', 18750, 19249.99, 19000, 855, 1615, 30],
            ['AF', 19250, 19749.99, 19500, 877.50, 1657.50, 30],
            ['AG', 19750, 20249.99, 20000, 900, 1700, 30],
            ['AH', 20250, 20749.99, 20500, 922.50, 1742.50, 30],
            ['AI', 20750, 21249.99, 21000, 945, 1785, 30],
            ['AJ', 21250, 21749.99, 21500, 967.50, 1827.50, 30],
            ['AK', 21750, 22249.99, 22000, 990, 1870, 30],
            ['AL', 22250, 22749.99, 22500, 1012.50, 1912.50, 30],
            ['AM', 22750, 23249.99, 23000, 1035, 1955, 30],
            ['AN', 23250, 23749.99, 23500, 1057.50, 1997.50, 30],
            ['AO', 23750, 24249.99, 24000, 1080, 2040, 30],
            ['AP', 24250, 24749.99, 24500, 1102.50, 2082.50, 30],
            ['AQ', 24750, 29999.99, 27500, 1237.50, 2337.50, 30],
            ['AR', 30000, null, 30000, 1350, 2550, 30], // ₱30,000+ (no upper limit)
        ];
        
        foreach ($sssRates as $rate) {
            DB::table('government_contribution_rates')->insert([
                'agency' => 'sss',
                'rate_type' => 'bracket',
                'bracket_code' => $rate[0],
                'compensation_min' => $rate[1],
                'compensation_max' => $rate[2],
                'monthly_salary_credit' => $rate[3],
                'employee_rate' => 4.5,
                'employer_rate' => 8.5,
                'total_rate' => 13.0,
                'employee_amount' => $rate[4],
                'employer_amount' => $rate[5],
                'ec_amount' => $rate[6],
                'total_amount' => $rate[4] + $rate[5] + $rate[6],
                'effective_from' => $effectiveFrom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        
        // ============================================================
        // PHILHEALTH PREMIUM RATES (2024 - RA 11223)
        // Premium: 5% of basic salary (2.5% EE + 2.5% ER)
        // Minimum: ₱500/month (₱10,000 basic salary)
        // Maximum: ₱5,000/month (₱100,000 basic salary)
        // ============================================================
        
        DB::table('government_contribution_rates')->insert([
            'agency' => 'philhealth',
            'rate_type' => 'premium_rate',
            'bracket_code' => null,
            'compensation_min' => 10000,
            'compensation_max' => 100000,
            'employee_rate' => 2.5,
            'employer_rate' => 2.5,
            'total_rate' => 5.0,
            'minimum_contribution' => 500, // ₱500 total (₱250 EE + ₱250 ER)
            'maximum_contribution' => 5000, // ₱5,000 total (₱2,500 EE + ₱2,500 ER)
            'premium_ceiling' => 100000,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'PhilHealth premium is 5% of basic salary with ₱10k-₱100k range. Minimum ₱500/month, Maximum ₱5,000/month.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // ============================================================
        // PAG-IBIG CONTRIBUTION RATES (2024 - RA 9679)
        // ≤ ₱1,500: 1% EE + 2% ER
        // > ₱1,500: 2% EE + 2% ER
        // Employee ceiling: ₱100/month
        // ============================================================
        
        // Low earners (≤ ₱1,500)
        DB::table('government_contribution_rates')->insert([
            'agency' => 'pagibig',
            'rate_type' => 'contribution_rate',
            'bracket_code' => 'LOW',
            'compensation_min' => 0,
            'compensation_max' => 1500,
            'employee_rate' => 1.0,
            'employer_rate' => 2.0,
            'total_rate' => 3.0,
            'contribution_ceiling' => 100, // Employee max ₱100
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'For salary ≤ ₱1,500: 1% employee + 2% employer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        // Regular earners (> ₱1,500)
        DB::table('government_contribution_rates')->insert([
            'agency' => 'pagibig',
            'rate_type' => 'contribution_rate',
            'bracket_code' => 'REGULAR',
            'compensation_min' => 1500.01,
            'compensation_max' => null,
            'employee_rate' => 2.0,
            'employer_rate' => 2.0,
            'total_rate' => 4.0,
            'contribution_ceiling' => 100, // Employee max ₱100
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'notes' => 'For salary > ₱1,500: 2% employee + 2% employer. Employee contribution capped at ₱100.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        
        $this->command->info('Government contribution rates seeded successfully!');
        $this->command->info('- SSS: 42 brackets');
        $this->command->info('- PhilHealth: 1 rate');
        $this->command->info('- Pag-IBIG: 2 rates');
    }
}
```

**Dependencies:** `government_contribution_rates` table

**Action:** CREATE

---

#### Subtask 1.2.2: Create TaxBracketsSeeder
**File:** `database/seeders/TaxBracketsSeeder.php`

**Purpose:** Populate BIR tax brackets per TRAIN Law (RA 10963)

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaxBracketsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $effectiveFrom = Carbon::parse('2018-01-01'); // TRAIN Law effective date
        
        // Tax statuses to populate
        $taxStatuses = [
            'Z' => 'Zero/Exempt',
            'S' => 'Single',
            'ME' => 'Married Employee',
            'S1' => 'Single with 1 Dependent',
            'ME1' => 'Married with 1 Dependent',
            'S2' => 'Single with 2 Dependents',
            'ME2' => 'Married with 2 Dependents',
            'S3' => 'Single with 3 Dependents',
            'ME3' => 'Married with 3 Dependents',
            'S4' => 'Single with 4 Dependents',
            'ME4' => 'Married with 4 Dependents',
        ];
        
        // ============================================================
        // TRAIN LAW TAX BRACKETS (2018 onwards)
        // Annualized Progressive Tax Table
        // ============================================================
        
        $brackets = [
            // Bracket 1: ₱0 - ₱250,000 (0%)
            [
                'level' => 1,
                'income_from' => 0,
                'income_to' => 250000,
                'base_tax' => 0,
                'tax_rate' => 0,
                'excess_over' => 0,
            ],
            // Bracket 2: ₱250,001 - ₱400,000 (15%)
            [
                'level' => 2,
                'income_from' => 250000.01,
                'income_to' => 400000,
                'base_tax' => 0,
                'tax_rate' => 15,
                'excess_over' => 250000,
            ],
            // Bracket 3: ₱400,001 - ₱800,000 (20%)
            [
                'level' => 3,
                'income_from' => 400000.01,
                'income_to' => 800000,
                'base_tax' => 22500, // ₱150k × 15%
                'tax_rate' => 20,
                'excess_over' => 400000,
            ],
            // Bracket 4: ₱800,001 - ₱2,000,000 (25%)
            [
                'level' => 4,
                'income_from' => 800000.01,
                'income_to' => 2000000,
                'base_tax' => 102500, // ₱22,500 + (₱400k × 20%)
                'tax_rate' => 25,
                'excess_over' => 800000,
            ],
            // Bracket 5: ₱2,000,001 - ₱8,000,000 (30%)
            [
                'level' => 5,
                'income_from' => 2000000.01,
                'income_to' => 8000000,
                'base_tax' => 402500, // ₱102,500 + (₱1.2M × 25%)
                'tax_rate' => 30,
                'excess_over' => 2000000,
            ],
            // Bracket 6: ₱8,000,001+ (35%)
            [
                'level' => 6,
                'income_from' => 8000000.01,
                'income_to' => null,
                'base_tax' => 2202500, // ₱402,500 + (₱6M × 30%)
                'tax_rate' => 35,
                'excess_over' => 8000000,
            ],
        ];
        
        // Insert tax brackets for each status
        foreach ($taxStatuses as $status => $description) {
            // Calculate exemptions based on status
            $personalExemption = 50000; // Standard personal exemption (TRAIN)
            $additionalExemption = 25000; // Per dependent (TRAIN)
            
            // Extract number of dependents from status code
            $dependents = 0;
            if (preg_match('/\d+/', $status, $matches)) {
                $dependents = (int) $matches[0];
            }
            
            foreach ($brackets as $bracket) {
                DB::table('tax_brackets')->insert([
                    'tax_status' => $status,
                    'status_description' => $description,
                    'bracket_level' => $bracket['level'],
                    'income_from' => $bracket['income_from'],
                    'income_to' => $bracket['income_to'],
                    'base_tax' => $bracket['base_tax'],
                    'tax_rate' => $bracket['tax_rate'],
                    'excess_over' => $bracket['excess_over'],
                    'personal_exemption' => $personalExemption,
                    'additional_exemption' => $additionalExemption,
                    'max_dependents' => 4,
                    'effective_from' => $effectiveFrom,
                    'is_active' => true,
                    'notes' => sprintf(
                        'TRAIN Law tax bracket for %s. Exemptions: ₱%s personal + ₱%s × %d dependents',
                        $description,
                        number_format($personalExemption),
                        number_format($additionalExemption),
                        $dependents
                    ),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        
        $this->command->info('Tax brackets seeded successfully!');
        $this->command->info('- Tax statuses: 11 (Z, S, ME, S1-S4, ME1-ME4)');
        $this->command->info('- Brackets per status: 6');
        $this->command->info('- Total records: 66');
    }
}
```

**Dependencies:** `tax_brackets` table

**Action:** CREATE

---

#### Subtask 1.2.3: Run Seeders
**Action:** RUN COMMAND

```bash
php artisan db:seed --class=GovernmentContributionRatesSeeder
php artisan db:seed --class=TaxBracketsSeeder
```

**Validation:**
```bash
php artisan tinker
>>> \DB::table('government_contribution_rates')->count()
# Should return 45 (42 SSS + 1 PhilHealth + 2 Pag-IBIG)
>>> \DB::table('tax_brackets')->count()
# Should return 66 (11 statuses × 6 brackets)
```

---

## **Phase 2: Models & Relationships (Week 1-2: Feb 6-16)**

### Task 2.1: Create Eloquent Models

#### Subtask 2.1.1: Create GovernmentContributionRate Model
**File:** `app/Models/GovernmentContributionRate.php`

```php
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
     */
    public static function findSSSBracket(float $monthlyCompensation)
    {
        return self::active()
            ->sss()
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
     */
    public static function getPhilHealthRate()
    {
        return self::active()->philHealth()->first();
    }

    /**
     * Get Pag-IBIG rate based on salary
     */
    public static function getPagIbigRate(float $salary)
    {
        return self::active()
            ->pagIbig()
            ->where('compensation_min', '<=', $salary)
            ->where(function ($q) use ($salary) {
                $q->whereNull('compensation_max')
                  ->orWhere('compensation_max', '>=', $salary);
            })
            ->first();
    }
}
```

**Action:** CREATE

---

#### Subtask 2.1.2: Create TaxBracket Model
**File:** `app/Models/TaxBracket.php`

```php
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
        
        return $this->base_tax + $taxOnExcess;
    }
}
```

**Action:** CREATE

---

## 📝 Implementation Plan Status

### ✅ Completed Sections

**Phase 1: Database Foundation** - COMPLETE
- ✅ All 5 database migrations defined
- ✅ All 2 database seeders defined with complete Philippine government rates

**Phase 2: Models & Relationships** - PARTIAL (2/5 models)
- ✅ GovernmentContributionRate model with SSS/PhilHealth/PagIbig lookup helpers
- ✅ TaxBracket model with BIR tax calculation logic

---

### ⏳ Remaining Implementation (To Be Added)

#### **Phase 2: Models & Relationships** (Remaining 3 models)
- ❌ **Subtask 2.1.3:** EmployeeGovernmentContribution model
  - Relationships to Employee, PayrollPeriod, EmployeePayrollCalculation
  - Scopes for filtering by period, agency, status
  - Methods for status transitions (pending → calculated → processed → remitted)
  
- ❌ **Subtask 2.1.4:** GovernmentRemittance model
  - Relationships to PayrollPeriod, EmployeeGovernmentContributions
  - Scopes for overdue, pending, status filtering
  - Methods for deadline calculation, penalty computation
  
- ❌ **Subtask 2.1.5:** GovernmentReport model
  - Relationships to PayrollPeriod, GovernmentRemittance
  - File storage and hash verification
  - Report validation logic

#### **Phase 3: Calculation Services** (Week 2: Feb 13-19)
- ❌ **SSSContributionService** - Bracket lookup and contribution calculation (13% split)
- ❌ **PhilHealthContributionService** - Premium calculation with ₱500-₱5,000 limits
- ❌ **PagIbigContributionService** - Rate selection and ₱100 ceiling enforcement
- ❌ **BIRTaxCalculationService** - Annualized income, tax bracket lookup, withholding computation
- ❌ **GovernmentContributionCalculationService** - Orchestrator service that calls all 4 services
- ❌ **GovernmentRemittanceService** - Deadline tracking, payment recording, penalty computation
- ❌ **GovernmentReportGeneratorService** - R3, RF-1, MCRF, 1601C, 2316, Alphalist generation

#### **Phase 4: Controllers & Backend** (Week 3: Feb 20-26)
- ❌ Update **SSSController** - Replace mock data with real calculations
- ❌ Update **PhilHealthController** - Replace mock data with real calculations
- ❌ Update **PagIbigController** - Replace mock data with real calculations
- ❌ Update **BIRController** - Replace mock data with real calculations
- ❌ Update **GovernmentRemittancesController** - Replace mock data with real tracking

#### **Phase 5: Frontend Integration** (Week 3-4: Feb 20-Mar 2)
- ❌ Update contribution table components to consume real data
- ❌ Update report generation modals with validation
- ❌ Update remittance tracking with deadline alerts
- ❌ Add file upload for remittance proof
- ❌ Add export functionality (Excel, CSV, PDF, DAT files)

#### **Phase 6: Integration & Testing** (Week 4-5: Feb 27-Mar 5)
- ❌ Integrate with PayrollProcessing module (auto-deductions)
- ❌ Integrate with EmployeePayroll module (government numbers)
- ❌ Event listeners for PayrollCalculated → trigger government contributions
- ❌ Unit tests for all calculation services
- ❌ Integration tests for remittance workflows
- ❌ E2E tests for report generation

---

### 🎯 Next Steps

1. **Complete Phase 2 Models** - Add remaining 3 Eloquent models
2. **Implement Phase 3 Services** - Build all 7 calculation services following Philippine law
3. **Update Phase 4 Controllers** - Connect existing controllers to real services
4. **Enhance Phase 5 Frontend** - Update React components to use real API data
5. **Execute Phase 6 Testing** - Ensure accuracy of all contribution calculations

---

### 📌 Implementation Notes

- All calculation services must follow **exact Philippine government rates** as defined in RA 11199 (SSS), RA 11223 (PhilHealth), RA 9679 (Pag-IBIG), and TRAIN Law (BIR)
- Services should handle **edge cases**: minimum wage earners (tax-exempt), 13th month exemption (₱90k), de minimis benefits
- Report generators must produce **government-compliant file formats**: SSS R3 (CSV), PhilHealth RF-1 (Excel), Pag-IBIG MCRF (Excel), BIR 1601C/2316/Alphalist (DAT/Excel)
- All monetary calculations must use **Decimal type** to avoid floating-point errors
- Remittance deadlines: SSS (10th of month), PhilHealth (quarterly), Pag-IBIG (10th of month), BIR (10th/15th of month)

---

*This implementation plan will be expanded with full code examples for all remaining phases following the same detailed pattern established in Phases 1-2.*

