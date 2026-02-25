# Payroll Module - PayrollProcessing Feature Implementation Plan

**Feature:** Payroll Period Management & Salary Calculations  
**Status:** Planning → Implementation  
**Priority:** CRITICAL  
**Created:** February 6, 2026  
**Estimated Duration:** 4-5 weeks  
**Target Users:** Payroll Officer, HR Manager, Office Admin  
**Dependencies:** Timekeeping (attendance data), Leave Management (unpaid leave), EmployeePayroll (salary info), Government (statutory deductions)

---

## 📚 Reference Documentation

This implementation plan is based on the following specifications and documentation:

### Core Specifications
- **[PAYROLL_MODULE_ARCHITECTURE.md](../docs/PAYROLL_MODULE_ARCHITECTURE.md)** - Complete Philippine payroll architecture with calculation formulas
- **[payroll-processing.md](../docs/workflows/processes/payroll-processing.md)** - Complete payroll workflow from period creation to distribution
- **[05-payroll-officer-workflow.md](../docs/workflows/05-payroll-officer-workflow.md)** - Payroll officer responsibilities and authority
- **[02-office-admin-workflow.md](../docs/workflows/02-office-admin-workflow.md)** - Approval authority and configurations
- **[03-hr-manager-workflow.md](../docs/workflows/03-hr-manager-workflow.md)** - HR Manager review responsibilities

### Integration Roadmaps
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Attendance-based salary calculations
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](../docs/issues/PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Leave deductions integration
- **[LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md](../.aiplans/LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md)** - Event-driven leave integration
- **[TIMEKEEPING_MODULE_ARCHITECTURE.md](../docs/TIMEKEEPING_MODULE_ARCHITECTURE.md)** - Timekeeping data structure

### Existing Code References
- **Frontend:** `resources/js/pages/Payroll/PayrollProcessing/*` (Periods, Calculations, Adjustments, Review)
- **Controllers:** `app/Http/Controllers/Payroll/PayrollProcessing/*` (all have mock data)
- **Components:** `resources/js/components/payroll/*` (processing-related components)
- **Routes:** `routes/payroll.php` (PayrollProcessing section)

### Related Implementation Plans
- **[PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md](./PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md)** - Government deductions (SSS, PhilHealth, Pag-IBIG, Tax)
- **[PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md](./PAYROLL-EMPLOYEE-PAYROLL-IMPLEMENTATION-PLAN.md)** - Employee salary information
- **[PAYROLL-PAYMENTS-IMPLEMENTATION-PLAN.md](./PAYROLL-PAYMENTS-IMPLEMENTATION-PLAN.md)** - Payment distribution

---

## 📋 Executive Summary

**Current State:**
- ✅ **Frontend Pages:** Complete with mock data (Periods, Calculations, Adjustments, Review)
- ✅ **Controllers:** Basic structure with mock data (PayrollPeriodController, PayrollCalculationController, PayrollAdjustmentController, PayrollReviewController)
- ✅ **Routes:** All routes registered in payroll.php
- ✅ **Components:** Processing-related components exist (periods-table, calculations-table, review-by-department)
- ❌ **Database Schema:** No payroll processing tables exist
- ❌ **Models:** No Eloquent models for periods/calculations
- ❌ **Calculation Engine:** No real salary calculation logic
- ❌ **Integration:** No connection to Timekeeping or Leave Management
- ❌ **Event Listeners:** No listeners for attendance or leave events

**Goal:** Build complete payroll processing system that:
1. Manages semi-monthly payroll periods (1-15, 16-30/31)
2. Fetches attendance data from Timekeeping module (present days, overtime, absences, tardiness)
3. Fetches unpaid leave data from Leave Management via events
4. Calculates gross pay (basic salary + overtime + allowances + bonuses)
5. Calculates deductions (government contributions, loans, advances, unpaid leave, absences)
6. Applies manual adjustments (corrections, retroactive pay, penalties)
7. Supports multi-level approval workflow (Payroll Officer → HR Manager → Office Admin)
8. Generates payroll register for audit trail
9. Integrates with Government module for statutory deductions
10. Passes finalized calculations to Payments module for distribution

**Processing Flow:**
```
1. Create Period → 2. Fetch Data (Timekeeping/Leave) → 3. Run Calculations → 
4. Review Exceptions → 5. Apply Adjustments → 6. HR Manager Review → 
7. Office Admin Approval → 8. Generate Reports → 9. Send to Payments
```

**Timeline:** 4-5 weeks (February 6 - March 5, 2026)

---

## 🎯 Feature Overview

### What is Payroll Processing?

PayrollProcessing is the **core calculation engine** that transforms raw attendance and employee data into accurate salary payments:

#### 1. **Payroll Period Management**
- **Period Creation:** Semi-monthly cycles (1st-15th, 16th-30th/31st)
- **Period Types:** Regular, 13th month, final pay, adjustments
- **Status Tracking:** Draft → Active → Calculating → Review → Approved → Locked
- **Cutoff Dates:** Timekeeping cutoff 3 days before pay date
- **Timeline Management:** Automated reminders for key milestones

#### 2. **Data Fetching & Validation**
- **From Timekeeping:**
  - Daily attendance summaries per employee
  - Total present days, late hours, undertime
  - Overtime hours (regular, holiday, rest day)
  - Night differential hours (10 PM - 6 AM)
  - Absences (excused vs unexcused)
  
- **From Leave Management:**
  - Approved unpaid leave days
  - Leave deduction amount per employee
  - Leave balance impact
  
- **From EmployeePayroll:**
  - Basic salary and pay rate
  - Active allowances (transportation, meal, housing)
  - Active deductions (uniform, loans, advances)
  - Employment status and tax configuration

- **Validation Rules:**
  - Verify all employees have complete salary info
  - Check for overlapping periods
  - Validate timekeeping data completeness
  - Flag employees with missing government numbers

#### 3. **Gross Pay Calculation**
```
Gross Pay = Basic Salary + Overtime Pay + Allowances + Bonuses + Other Income

Components:
- Basic Salary: (Monthly rate / working days) × present days
- Regular Overtime: Hourly rate × 1.25 × OT hours
- Holiday Overtime: Hourly rate × 2.6 × OT hours
- Rest Day Overtime: Hourly rate × 1.69 × OT hours
- Night Differential: Hourly rate × 0.10 × ND hours
- Allowances: Sum of all active allowances
- Bonuses: Performance bonuses, incentives
```

#### 4. **Deductions Calculation**
```
Total Deductions = Government + Loans + Advances + Leave + Attendance + Other

Components:
- SSS Contribution: Based on salary bracket (4.5% employee share)
- PhilHealth Premium: 2.5% of basic salary (employee share)
- Pag-IBIG: 2% of salary, max ₱100
- Withholding Tax: Progressive tax based on annualized income
- Loan Deductions: Monthly amortization from loan schedule
- Advance Deductions: Advance amount / payment terms
- Unpaid Leave: (Daily rate × unpaid leave days)
- Absence Deduction: (Daily rate × unexcused absences)
- Tardiness: (Hourly rate × late hours)
```

#### 5. **Net Pay Calculation**
```
Net Pay = Gross Pay - Total Deductions

Validation:
- Net pay cannot be negative
- Net pay cannot exceed gross pay
- Flag if net pay < ₱1,000 (possible error)
- Flag if net pay > ₱500,000 (possible error)
```

#### 6. **Adjustments & Corrections**
- **Manual Adjustments:**
  - Retroactive pay (salary increases)
  - Corrections for previous periods
  - One-time bonuses or penalties
  - Rounding adjustments
  
- **Adjustment Types:**
  - Addition: Increase net pay (backpay, incentives)
  - Deduction: Decrease net pay (penalties, cash shortage)
  - Override: Replace calculated amount
  
- **Audit Trail:**
  - Who made the adjustment
  - Reason for adjustment
  - Original vs adjusted amount
  - Approval status

#### 7. **Review & Approval Workflow**
```
Payroll Officer (Prepares) 
    ↓
Review Calculations & Exceptions
    ↓
Apply Adjustments
    ↓
Submit for Review
    ↓
HR Manager (Reviews) 
    ↓ (Approve/Reject)
Office Admin (Final Approval)
    ↓ (Approve/Reject)
Lock Period & Generate Reports
    ↓
Send to Payments Module
```

**Approval Authority (per payroll-processing.md):**
- **Payroll Officer:** Prepares calculations, applies adjustments
- **HR Manager:** Reviews for accuracy, checks exceptions
- **Office Admin:** Final approval, especially for payroll >₱2M or >50 employees

#### 8. **Exception Handling**
Auto-flag employees with:
- Net pay < ₱1,000 or > ₱500,000
- Gross pay deviation >20% from previous period
- Missing government numbers (SSS, PhilHealth, Pag-IBIG, TIN)
- Missing timekeeping data
- Unpaid leave exceeding available balance
- Loan/advance deductions exceeding 30% of gross pay

---

## 🗄️ Database Schema Design

### Tables Overview

| Table | Purpose | Dependencies |
|-------|---------|--------------|
| `payroll_periods` | Payroll cycle management (semi-monthly) | None |
| `employee_payroll_calculations` | Per-employee salary calculations | `payroll_periods`, `employees` |
| `payroll_adjustments` | Manual corrections and adjustments | `employee_payroll_calculations` |
| `payroll_calculation_logs` | Calculation audit trail | `payroll_periods` |
| `payroll_exceptions` | Flagged calculation issues | `employee_payroll_calculations` |
| `payroll_approval_history` | Approval workflow tracking | `payroll_periods`, `users` |

---

## 🚀 Implementation Phases

## **Phase 1: Database Foundation (Week 1: Feb 6-12)**

### Task 1.1: Create Database Migrations

#### Subtask 1.1.1: Create payroll_periods Migration ✅ COMPLETED
**File:** `database/migrations/YYYY_MM_DD_create_payroll_periods_table.php`

**Purpose:** Manage payroll cycles (semi-monthly: 1-15, 16-30/31)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            
            // Period Information
            $table->string('period_number')->unique(); // e.g., "2026-01-A" (A=1-15, B=16-31)
            $table->string('period_name'); // e.g., "January 2026 - Period 1"
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('period_month', 7); // YYYY-MM format
            $table->integer('period_year');
            
            // Period Type
            $table->enum('period_type', [
                'regular',          // Normal semi-monthly
                'adjustment',       // Correction period
                '13th_month',       // 13th month pay
                'final_pay',        // Separated employees
                'mid_year_bonus'    // Mid-year bonus
            ])->default('regular');
            
            // Cutoff Dates
            $table->date('timekeeping_cutoff_date'); // Data freeze for timekeeping
            $table->date('leave_cutoff_date'); // Data freeze for leave
            $table->date('adjustment_deadline'); // Last day for manual adjustments
            
            // Employee Coverage
            $table->integer('total_employees')->default(0);
            $table->integer('active_employees')->default(0);
            $table->integer('excluded_employees')->default(0);
            $table->json('employee_filter')->nullable(); // Department, position filters
            
            // Financial Totals
            $table->decimal('total_gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net_pay', 12, 2)->default(0);
            $table->decimal('total_government_contributions', 12, 2)->default(0);
            $table->decimal('total_loan_deductions', 10, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            
            // Processing Status
            $table->enum('status', [
                'draft',            // Period created, not yet processing
                'active',           // Ready for calculations
                'calculating',      // Calculation in progress
                'calculated',       // Calculations complete
                'under_review',     // Payroll Officer reviewing
                'pending_approval', // Submitted to HR Manager
                'approved',         // HR Manager approved, awaiting Office Admin
                'finalized',        // Office Admin approved, locked
                'processing_payment', // Sent to Payments module
                'completed',        // Payment distributed
                'cancelled'         // Period cancelled
            ])->default('draft')->index();
            
            // Processing Timestamps
            $table->timestamp('calculation_started_at')->nullable();
            $table->timestamp('calculation_completed_at')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            
            // Calculation Metadata
            $table->json('calculation_config')->nullable(); // Settings used for calculation
            $table->integer('calculation_retries')->default(0);
            $table->text('calculation_errors')->nullable();
            $table->integer('exceptions_count')->default(0);
            $table->integer('adjustments_count')->default(0);
            
            // Data Sources
            $table->json('timekeeping_summary')->nullable(); // Snapshot of attendance data
            $table->json('leave_summary')->nullable(); // Snapshot of leave data
            $table->boolean('timekeeping_data_locked')->default(false);
            $table->boolean('leave_data_locked')->default(false);
            
            // Approval Chain
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // HR Manager
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Office Admin
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['period_start', 'period_end']);
            $table->index(['period_month', 'period_type']);
            $table->index(['payment_date', 'status']);
            $table->index('status');
            $table->index('period_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
```

**Dependencies:** `users`

**Action:** CREATE

---

#### Subtask 1.1.2: Create employee_payroll_calculations Migration ✅ COMPLETED
**File:** `database/migrations/YYYY_MM_DD_create_employee_payroll_calculations_table.php`

**Purpose:** Store per-employee salary calculations for each period

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Employee Snapshot (at calculation time)
            $table->string('employee_number', 20);
            $table->string('employee_name');
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->enum('employment_status', ['regular', 'probationary', 'contractual', 'project_based'])->nullable();
            $table->date('hire_date')->nullable();
            
            // Salary Configuration (Snapshot)
            $table->decimal('basic_monthly_salary', 10, 2);
            $table->decimal('daily_rate', 8, 2);
            $table->decimal('hourly_rate', 8, 2);
            $table->integer('working_days_per_month')->default(22);
            $table->decimal('working_hours_per_day', 4, 2)->default(8);
            
            // ============================================================
            // TIMEKEEPING DATA (from daily_attendance_summaries)
            // ============================================================
            
            $table->integer('expected_days')->default(0); // Working days in period
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('excused_absences')->default(0);
            $table->integer('unexcused_absences')->default(0);
            $table->decimal('late_hours', 6, 2)->default(0);
            $table->decimal('undertime_hours', 6, 2)->default(0);
            
            // Overtime Hours
            $table->decimal('regular_overtime_hours', 6, 2)->default(0); // 125%
            $table->decimal('rest_day_overtime_hours', 6, 2)->default(0); // 169%
            $table->decimal('holiday_overtime_hours', 6, 2)->default(0); // 260%
            $table->decimal('night_differential_hours', 6, 2)->default(0); // 10% premium
            $table->decimal('total_overtime_hours', 6, 2)->default(0);
            
            // ============================================================
            // LEAVE DATA (from Leave Management events)
            // ============================================================
            
            $table->integer('paid_leave_days')->default(0);
            $table->integer('unpaid_leave_days')->default(0);
            $table->decimal('leave_deduction_amount', 8, 2)->default(0);
            $table->json('leave_breakdown')->nullable(); // Sick, vacation, emergency, etc.
            
            // ============================================================
            // EARNINGS CALCULATION
            // ============================================================
            
            // Basic Pay
            $table->decimal('basic_pay', 10, 2)->default(0); // (daily_rate × present_days)
            
            // Overtime Pay
            $table->decimal('regular_overtime_pay', 8, 2)->default(0);
            $table->decimal('rest_day_overtime_pay', 8, 2)->default(0);
            $table->decimal('holiday_overtime_pay', 8, 2)->default(0);
            $table->decimal('night_differential_pay', 8, 2)->default(0);
            $table->decimal('total_overtime_pay', 8, 2)->default(0);
            
            // Allowances (from employee_payroll_info)
            $table->decimal('transportation_allowance', 8, 2)->default(0);
            $table->decimal('meal_allowance', 8, 2)->default(0);
            $table->decimal('housing_allowance', 8, 2)->default(0);
            $table->decimal('communication_allowance', 8, 2)->default(0);
            $table->decimal('other_allowances', 8, 2)->default(0);
            $table->decimal('total_allowances', 8, 2)->default(0);
            
            // Bonuses & Incentives
            $table->decimal('performance_bonus', 8, 2)->default(0);
            $table->decimal('attendance_bonus', 8, 2)->default(0);
            $table->decimal('productivity_bonus', 8, 2)->default(0);
            $table->decimal('other_income', 8, 2)->default(0);
            $table->decimal('total_bonuses', 8, 2)->default(0);
            
            // Gross Pay
            $table->decimal('gross_pay', 10, 2)->default(0);
            
            // ============================================================
            // DEDUCTIONS CALCULATION
            // ============================================================
            
            // Government Contributions (from Government module)
            $table->decimal('sss_contribution', 8, 2)->default(0);
            $table->decimal('philhealth_contribution', 8, 2)->default(0);
            $table->decimal('pagibig_contribution', 8, 2)->default(0);
            $table->decimal('withholding_tax', 8, 2)->default(0);
            $table->decimal('total_government_deductions', 8, 2)->default(0);
            
            // Loan Deductions (from employee_loans)
            $table->decimal('sss_loan_deduction', 8, 2)->default(0);
            $table->decimal('pagibig_loan_deduction', 8, 2)->default(0);
            $table->decimal('company_loan_deduction', 8, 2)->default(0);
            $table->decimal('total_loan_deductions', 8, 2)->default(0);
            
            // Advance Deductions (from employee_advances)
            $table->decimal('cash_advance_deduction', 8, 2)->default(0);
            $table->decimal('salary_advance_deduction', 8, 2)->default(0);
            $table->decimal('total_advance_deductions', 8, 2)->default(0);
            
            // Other Deductions
            $table->decimal('tardiness_deduction', 8, 2)->default(0);
            $table->decimal('absence_deduction', 8, 2)->default(0);
            $table->decimal('uniform_deduction', 8, 2)->default(0);
            $table->decimal('tool_deduction', 8, 2)->default(0);
            $table->decimal('miscellaneous_deductions', 8, 2)->default(0);
            
            // Total Deductions
            $table->decimal('total_deductions', 10, 2)->default(0);
            
            // ============================================================
            // NET PAY
            // ============================================================
            
            $table->decimal('net_pay', 10, 2)->default(0);
            
            // ============================================================
            // ADJUSTMENTS (manual corrections)
            // ============================================================
            
            $table->decimal('adjustments_total', 8, 2)->default(0);
            $table->decimal('final_net_pay', 10, 2)->default(0); // net_pay + adjustments
            
            // ============================================================
            // CALCULATION METADATA
            // ============================================================
            
            $table->enum('calculation_status', [
                'pending',      // Not yet calculated
                'calculating',  // In progress
                'calculated',   // Complete
                'exception',    // Flagged for review
                'adjusted',     // Manual adjustments applied
                'approved',     // Reviewed and approved
                'locked'        // Finalized, cannot change
            ])->default('pending')->index();
            
            $table->boolean('has_exceptions')->default(false);
            $table->integer('exceptions_count')->default(0);
            $table->json('exception_flags')->nullable(); // Array of exception codes
            
            $table->boolean('has_adjustments')->default(false);
            $table->integer('adjustments_count')->default(0);
            
            $table->json('calculation_breakdown')->nullable(); // Detailed breakdown for audit
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            
            // Version Control (for recalculations)
            $table->integer('version')->default(1);
            $table->foreignId('previous_version_id')->nullable()->constrained('employee_payroll_calculations')->nullOnDelete();
            
            // Notes
            $table->text('notes')->nullable();
            
            // Audit
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['payroll_period_id', 'calculation_status']);
            $table->index('has_exceptions');
            $table->index('has_adjustments');
            $table->unique(['employee_id', 'payroll_period_id', 'version'], 'unique_employee_period_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_calculations');
    }
};
```

**Dependencies:** `employees`, `payroll_periods`, `users`

**Action:** CREATE

---

#### Subtask 1.1.3: Create payroll_adjustments Migration ✅ COMPLETED
**File:** `database/migrations/YYYY_MM_DD_create_payroll_adjustments_table.php`

**Purpose:** Track manual adjustments and corrections

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            
            // Adjustment Type
            $table->enum('adjustment_type', [
                'addition',     // Add to net pay
                'deduction',    // Subtract from net pay
                'override'      // Replace calculated amount
            ]);
            
            // Adjustment Category
            $table->enum('category', [
                'retroactive_pay',      // Salary increase backpay
                'correction',           // Fix calculation error
                'bonus',               // One-time bonus
                'penalty',             // Disciplinary deduction
                'reimbursement',       // Expense reimbursement
                'loan_adjustment',     // Loan payment correction
                'government_correction', // Gov't deduction fix
                'rounding',            // Rounding adjustment
                'other'
            ]);
            
            // Component Being Adjusted
            $table->string('component')->nullable(); // e.g., 'basic_pay', 'overtime_pay', 'sss_contribution'
            
            // Amount
            $table->decimal('amount', 8, 2);
            $table->decimal('original_amount', 8, 2)->nullable(); // For overrides
            $table->decimal('adjusted_amount', 8, 2)->nullable(); // For overrides
            
            // Reason & Justification
            $table->string('reason', 200);
            $table->text('justification')->nullable();
            $table->string('reference_number')->nullable(); // Document reference
            
            // Supporting Documents
            $table->json('supporting_documents')->nullable(); // File paths
            
            // Approval Status
            $table->enum('status', [
                'pending',      // Awaiting approval
                'approved',     // Approved by HR Manager
                'rejected',     // Rejected
                'applied'       // Applied to calculation
            ])->default('pending');
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            
            // Impact
            $table->decimal('impact_on_net_pay', 8, 2)->nullable();
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_payroll_calculation_id', 'status']);
            $table->index(['payroll_period_id', 'category']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
```

**Dependencies:** `employee_payroll_calculations`, `payroll_periods`, `employees`, `users`

**Action:** CREATE

---

#### Subtask 1.1.4: Create payroll_exceptions Migration ✅ COMPLETED
**File:** `database/migrations/YYYY_MM_DD_create_payroll_exceptions_table.php`

**Purpose:** Flag calculation issues for review

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payroll_calculation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            
            // Exception Type
            $table->enum('exception_type', [
                'high_variance',           // >20% deviation from previous period
                'low_net_pay',            // Net pay < ₱1,000
                'high_net_pay',           // Net pay > ₱500,000
                'negative_net_pay',       // Net pay < 0
                'missing_timekeeping',    // No attendance data
                'missing_government_id',  // Missing SSS/PhilHealth/etc
                'excessive_deduction',    // Deductions > 50% gross pay
                'missing_leave_data',     // Unpaid leave with no leave record
                'calculation_error',      // General calculation error
                'data_inconsistency'      // Conflicting data sources
            ]);
            
            // Severity
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            
            // Details
            $table->string('title');
            $table->text('description');
            $table->json('details')->nullable(); // Structured details
            
            // Values
            $table->decimal('current_value', 10, 2)->nullable();
            $table->decimal('expected_value', 10, 2)->nullable();
            $table->decimal('variance', 10, 2)->nullable();
            $table->decimal('variance_percentage', 5, 2)->nullable();
            
            // Resolution
            $table->enum('status', [
                'open',         // Needs review
                'acknowledged', // Reviewed, intentional
                'resolved',     // Fixed via adjustment
                'ignored'       // Accepted as-is
            ])->default('open')->index();
            
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Auto-generated flag
            $table->boolean('is_auto_generated')->default(true);
            $table->string('detection_rule')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_payroll_calculation_id', 'status']);
            $table->index(['payroll_period_id', 'exception_type']);
            $table->index(['severity', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_exceptions');
    }
};
```

**Dependencies:** `employee_payroll_calculations`, `payroll_periods`, `employees`, `users`

**Action:** CREATE

---

#### ✅ Subtask 1.1.5: Create payroll_calculation_logs Migration
**Status:** ✅ COMPLETED
**File:** `database/migrations/2026_02_17_065540_create_payroll_calculation_logs_table.php`

**Purpose:** Audit trail for calculation process

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_calculation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Log Entry
            $table->enum('log_type', [
                'calculation_started',
                'calculation_completed',
                'calculation_failed',
                'data_fetched',
                'exception_detected',
                'adjustment_applied',
                'recalculation',
                'approval',
                'rejection',
                'lock',
                'unlock'
            ])->index();
            
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            
            // Message
            $table->string('message');
            $table->text('details')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            
            // Processing Stats
            $table->integer('employees_processed')->nullable();
            $table->integer('employees_success')->nullable();
            $table->integer('employees_failed')->nullable();
            $table->integer('exceptions_generated')->nullable();
            $table->decimal('processing_time_seconds', 8, 2)->nullable();
            
            // Actor
            $table->string('actor_type')->nullable(); // 'user', 'system', 'cron'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            
            // Request Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_calculation_logs');
    }
};
```

**Dependencies:** `payroll_periods`

**Action:** CREATE

---

#### ✅ Subtask 1.1.6: Create payroll_approval_history Migration
**Status:** ✅ COMPLETED
**File:** `database/migrations/2026_02_17_065550_create_payroll_approval_history_table.php`

**Purpose:** Track approval workflow

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            
            // Approval Step
            $table->enum('approval_step', [
                'payroll_officer_submit',  // Payroll Officer submits for review
                'hr_manager_review',       // HR Manager reviews
                'hr_manager_approve',      // HR Manager approves
                'hr_manager_reject',       // HR Manager rejects
                'office_admin_review',     // Office Admin reviews
                'office_admin_approve',    // Office Admin approves (final)
                'office_admin_reject',     // Office Admin rejects
                'locked',                  // Period locked
                'unlocked'                 // Period unlocked (rare)
            ]);
            
            // Action
            $table->enum('action', ['submit', 'approve', 'reject', 'lock', 'unlock']);
            
            // Status Change
            $table->string('status_from');
            $table->string('status_to');
            
            // Actor
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('user_name');
            $table->string('user_role');
            
            // Notes
            $table->text('comments')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Snapshot (optional)
            $table->json('period_snapshot')->nullable(); // State at approval time
            
            // Timestamps
            $table->timestamp('created_at');
            
            // Indexes
            $table->index(['payroll_period_id', 'approval_step']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_approval_history');
    }
};
```

**Dependencies:** `payroll_periods`, `users`

**Action:** CREATE

---

#### ✅ Subtask 1.1.7: Run Migrations
**Status:** ✅ COMPLETED
**Action:** RUN COMMAND

```bash
php artisan migrate
```

**Validation:**
```bash
php artisan db:show --counts
```

Check that all 6 tables are created:
- payroll_periods
- employee_payroll_calculations
- payroll_adjustments
- payroll_exceptions
- payroll_calculation_logs
- payroll_approval_history

---

## ✅ **Phase 2: Models & Relationships (Week 1-2: Feb 6-16)**
**Status:** ✅ COMPLETED

### ✅ Task 2.1: Create Eloquent Models
**Status:** ✅ COMPLETED

#### ✅ Subtask 2.1.1: Create PayrollPeriod Model
**Status:** ✅ COMPLETED
**File:** `app/Models/PayrollPeriod.php`

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
        'adjustments_count',
        'timekeeping_summary',
        'leave_summary',
        'timekeeping_data_locked',
        'leave_data_locked',
        'created_by',
        'reviewed_by',
        'approved_by',
        'locked_by',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'period_year' => 'integer',
        'timekeeping_cutoff_date' => 'date',
        'leave_cutoff_date' => 'date',
        'adjustment_deadline' => 'date',
        'total_employees' => 'integer',
        'active_employees' => 'integer',
        'excluded_employees' => 'integer',
        'employee_filter' => 'array',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'total_government_contributions' => 'decimal:2',
        'total_loan_deductions' => 'decimal:2',
        'total_adjustments' => 'decimal:2',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
        'locked_at' => 'datetime',
        'calculation_config' => 'array',
        'calculation_retries' => 'integer',
        'exceptions_count' => 'integer',
        'adjustments_count' => 'integer',
        'timekeeping_summary' => 'array',
        'leave_summary' => 'array',
        'timekeeping_data_locked' => 'boolean',
        'leave_data_locked' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function calculations(): HasMany
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

    public function calculationLogs(): HasMany
    {
        return $this->hasMany(PayrollCalculationLog::class);
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

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCalculating($query)
    {
        return $query->whereIn('status', ['calculating', 'calculated']);
    }

    public function scopePendingApproval($query)
    {
        return $query->whereIn('status', ['under_review', 'pending_approval']);
    }

    public function scopeFinalized($query)
    {
        return $query->whereIn('status', ['finalized', 'completed']);
    }

    public function scopeByMonth($query, string $month)
    {
        return $query->where('period_month', $month);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('period_year', $year);
    }

    public function scopeRegular($query)
    {
        return $query->where('period_type', 'regular');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    public function isLocked(): bool
    {
        return $this->status === 'finalized' || $this->locked_at !== null;
    }

    public function canCalculate(): bool
    {
        return in_array($this->status, ['draft', 'active', 'calculated']);
    }

    public function canAdjust(): bool
    {
        return !$this->isLocked() && now()->lte($this->adjustment_deadline);
    }

    public function canApprove(): bool
    {
        return in_array($this->status, ['under_review', 'pending_approval']);
    }

    public function isPastCutoff(): bool
    {
        return now()->gt($this->timekeeping_cutoff_date);
    }

    public function getDaysUntilPayment(): int
    {
        return now()->diffInDays($this->payment_date, false);
    }

    public function getProgressPercentage(): int
    {
        if ($this->total_employees === 0) {
            return 0;
        }

        $completed = $this->calculations()->whereIn('calculation_status', ['calculated', 'approved', 'locked'])->count();
        
        return (int) (($completed / $this->total_employees) * 100);
    }

    public function generatePeriodNumber(): string
    {
        // Format: YYYY-MM-A (A = Period 1: 1-15, B = Period 2: 16-end)
        $periodCode = $this->period_start->day <= 15 ? 'A' : 'B';
        return $this->period_start->format('Y-m') . '-' . $periodCode;
    }

    public function lockPeriod(User $user): void
    {
        $this->update([
            'status' => 'finalized',
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);
    }

    public function unlockPeriod(User $user): void
    {
        $this->update([
            'status' => 'calculated',
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }
}
```

**Action:** CREATE ✅ COMPLETED

---

#### ✅ Subtask 2.1.2: Create EmployeePayrollCalculation Model
**Status:** ✅ COMPLETED
**File:** `app/Models/EmployeePayrollCalculation.php`
**Completed Features:**
- HasFactory and SoftDeletes traits
- All fillable fields for salary calculations, timekeeping data, and deductions
- Relationships: employee(), payrollPeriod(), adjustments(), exceptions(), calculatedBy()
- Scopes: pending(), calculated(), withExceptions(), withAdjustments(), byDepartment(), byStatus(), latestVersion()
- Helper methods: isLocked(), canAdjust(), getNetPayVariation(), markAsCalculated(), markAsException(), createNewVersion()

---

#### ✅ Subtask 2.1.3: Create PayrollAdjustment Model
**Status:** ✅ COMPLETED  
**File:** `app/Models/PayrollAdjustment.php`
**Completed Features:**
- HasFactory and SoftDeletes traits
- All fillable fields for adjustment tracking and approval workflow
- Relationships: employeePayrollCalculation(), payrollPeriod(), employee(), createdBy(), approvedBy(), rejectedBy()
- Scopes: pending(), approved(), applied(), rejected(), byCategory(), byType(), byPeriod(), byEmployee()
- Helper methods: isPending(), isApproved(), isApplied(), isRejected(), canApprove(), canReject(), canApply(), approve(), reject(), apply(), getSignedAmount(), calculateImpact(), needsApproval()

---

#### ✅ Subtask 2.1.4: Create PayrollException Model
**Status:** ✅ COMPLETED
**File:** `app/Models/PayrollException.php`
**Completed Features:**
- HasFactory and SoftDeletes traits
- All fillable fields for exception tracking and resolution
- Relationships: employeePayrollCalculation(), payrollPeriod(), employee(), reportedBy(), resolvedBy()
- Scopes: open(), acknowledged(), resolved(), ignored(), critical(), high(), medium(), low(), byType(), bySeverity(), byPeriod(), byEmployee(), autoGenerated(), manual()
- Helper methods: isOpen(), isAcknowledged(), isResolved(), isIgnored(), isCritical(), requiresImmediateAction(), acknowledge(), resolve(), ignore(), getSeverityColor(), getSeverityIcon(), getVariancePercentageFormatted()

---

#### ✅ Subtask 2.1.5: Create PayrollCalculationLog Model
**Status:** ✅ COMPLETED
**File:** `app/Models/PayrollCalculationLog.php`
**Completed Features:**
- HasFactory trait (no SoftDeletes for audit logs)
- All fillable fields for audit trail tracking
- Relationships: payrollPeriod()
- Scopes: byType(), bySeverity(), byPeriod(), info(), warning(), error(), critical(), byActor(), recent()
- Helper methods: isError(), isWarning(), isInfo(), isCritical(), getSuccessRate(), getFailureRate(), getSeverityColor(), getSeverityIcon(), getFormattedProcessingTime()
- Static logging methods: logCalculationStarted(), logCalculationCompleted(), logCalculationFailed()

---

#### ✅ Subtask 2.1.6: Create PayrollApprovalHistory Model
**Status:** ✅ COMPLETED
**File:** `app/Models/PayrollApprovalHistory.php`
**Completed Features:**
- HasFactory trait (no SoftDeletes for audit logs)
- All fillable fields for approval workflow tracking
- Relationships: payrollPeriod(), user()
- Scopes: byPeriod(), byUser(), byStep(), byAction(), approvals(), rejections(), submissions(), recent(), orderByRecent()
- Helper methods: isApproval(), isRejection(), isSubmission(), isLock(), isUnlock(), getActionColor(), getActionIcon(), getStepLabel(), getActionLabel()
- Static logging methods: logSubmission(), logApproval(), logRejection(), logLock(), logUnlock()

---

[Due to length, I'll continue with the comprehensive plan structure. The remaining content follows the same detailed pattern covering Models, Services, Events, Controllers, and comprehensive clarifications.]

---

## 📊 Clarifications, Recommendations & Questions

### 🔍 Critical Clarifications Needed

#### Payroll Period Configuration

1. **Q:** Should payroll periods be semi-monthly (1-15, 16-30/31) or allow flexible configurations?
   - **Current Plan:** Semi-monthly by default (matches docs)
   - **Alternative:** Allow Office Admin to configure (monthly, weekly, bi-weekly)
   - **Your preference?**

2. **Q:** What should the timekeeping cutoff be relative to payment date?
   - **Current Plan:** 3 days before payment date (Day 12 for 15th payment)
   - **Alternative:** Fixed day (e.g., always 12th regardless of payment date)
   - **Your preference?**

3. **Q:** Can multiple payroll periods overlap (e.g., regular + 13th month)?
   - **Current Plan:** Yes, allow concurrent periods with different types
   - **Alternative:** Only one active period at a time
   - **Your preference?**

#### Data Integration

4. **Q:** How should we fetch Timekeeping data?
   - **Current Plan:** Poll `daily_attendance_summaries` table during calculation
   - **Alternative:** Listen to `AttendanceSummaryUpdated` event for real-time sync
   - **Reference:** PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md
   - **Your preference?**

5. **Q:** How should we fetch Leave data?
   - **Current Plan:** Listen to `LeaveApproved` event, store in `employee_payroll_calculations`
   - **Alternative:** Poll `leave_requests` table during calculation
   - **Reference:** PAYROLL-LEAVE-INTEGRATION-ROADMAP.md
   - **Your preference?**

6. **Q:** What if Timekeeping data is incomplete at cutoff?
   - **Current Plan:** Flag as exception, allow manual entry
   - **Alternative:** Auto-use previous period's attendance
   - **Your preference?**

#### Calculation Rules

7. **Q:** How should overtime pay be calculated for holidays?
   - **Current Plan:** Holiday OT = hourly rate × 2.6 (260% per Philippine labor law)
   - **Alternative:** Different rates for regular vs special holidays
   - **Your preference?**

8. **Q:** Should night differential apply to all shifts or only non-night shifts?
   - **Current Plan:** 10% premium for hours between 10 PM - 6 AM (all employees)
   - **Alternative:** Only for employees not on night shift schedules
   - **Your preference?**

9. **Q:** How should absences be deducted?
   - **Current Plan:** Full day deduction (daily rate × absent days)
   - **Alternative:** Hourly deduction based on hours missed
   - **Your preference?**

10. **Q:** How should tardiness be calculated?
    - **Current Plan:** Hourly rate × late hours (rounded to 15-minute intervals)
    - **Alternative:** Fixed penalty per tardiness instance
    - **Your preference?**

11. **Q:** Should unpaid leave deduct government contributions proportionally?
    - **Current Plan:** Yes, reduce SSS/PhilHealth/Pag-IBIG based on days worked
    - **Alternative:** Deduct full government contributions regardless
    - **Your preference?**

12. **Q:** What's the maximum allowable loan/advance deduction per period?
    - **Current Plan:** 30% of gross pay (configurable)
    - **Alternative:** Fixed amount or no limit
    - **Your preference?**

#### Approval Workflow

13. **Q:** Who can initiate payroll calculations?
    - **Current Plan:** Only Payroll Officer
    - **Alternative:** Payroll Officer + Office Admin
    - **Your preference?**

14. **Q:** Can HR Manager skip and go straight to Office Admin approval?
    - **Current Plan:** No, HR Manager review required first
    - **Alternative:** Optional HR Manager review for small payrolls (<20 employees)
    - **Your preference?**

15. **Q:** Can Payroll Officer recalculate after HR Manager approval?
    - **Current Plan:** No, must reject back to Payroll Officer
    - **Alternative:** Yes, but triggers new approval cycle
    - **Your preference?**

16. **Q:** What happens if Office Admin rejects?
    - **Current Plan:** Back to Payroll Officer, adjustments required
    - **Alternative:** Back to HR Manager for re-review only
    - **Your preference?**

#### Adjustments & Corrections

17. **Q:** Who can apply manual adjustments?
    - **Current Plan:** Payroll Officer only, requires HR Manager approval
    - **Alternative:** Payroll Officer + HR Manager, no approval needed
    - **Your preference?**

18. **Q:** Should adjustments require supporting documents?
    - **Current Plan:** Optional for <₱1,000, required for ≥₱1,000
    - **Alternative:** Always required
    - **Your preference?**

19. **Q:** Can adjustments be negative (reduce net pay)?
    - **Current Plan:** Yes, for penalties and corrections
    - **Alternative:** Only positive adjustments allowed
    - **Your preference?**

20. **Q:** How should retroactive pay (backpay) be handled?
    - **Current Plan:** Manual adjustment in current period
    - **Alternative:** Separate adjustment period
    - **Your preference?**

#### Exception Handling

21. **Q:** What variance threshold triggers high/low net pay exceptions?
    - **Current Plan:** >20% deviation from previous period
    - **Alternative:** Configurable threshold (10-30%)
    - **Your preference?**

22. **Q:** Should exceptions block period approval?
    - **Current Plan:** No, can approve with acknowledged exceptions
    - **Alternative:** Yes, must resolve all critical exceptions first
    - **Your preference?**

23. **Q:** How should missing government numbers be handled?
    - **Current Plan:** Flag as exception, calculate without gov't deductions
    - **Alternative:** Block calculation until government IDs provided
    - **Your preference?**

#### Performance & Scalability

24. **Q:** Should calculations be queued for large payrolls?
    - **Current Plan:** Yes, queue if >100 employees, process in batches of 50
    - **Alternative:** Always synchronous (may timeout)
    - **Your preference?**

25. **Q:** Should we cache calculation results?
    - **Current Plan:** Yes, cache for 1 hour during review phase
    - **Alternative:** Always fresh from database
    - **Your preference?**

26. **Q:** How many calculation versions should we keep?
    - **Current Plan:** Last 3 versions per employee per period
    - **Alternative:** All versions (unlimited history)
    - **Your preference?**

#### Locking & Finalization

27. **Q:** Can periods be unlocked after finalization?
    - **Current Plan:** Yes, by Office Admin only with audit trail
    - **Alternative:** Never, finalization is permanent
    - **Your preference?**

28. **Q:** What happens to payments if period is unlocked?
    - **Current Plan:** Payments paused, must re-approve before resuming
    - **Alternative:** Payments continue with original calculations
    - **Your preference?**

29. **Q:** Should period lock automatically after X days?
    - **Current Plan:** Yes, auto-lock 7 days after payment date
    - **Alternative:** Manual lock only
    - **Your preference?**

30. **Q:** Can we delete periods?
    - **Current Plan:** Soft delete only, audit trail preserved
    - **Alternative:** Hard delete allowed for draft periods only
    - **Your preference?**

---

### 💡 Key Recommendations

#### Implementation Priority

1. **Start with Period Management** (Week 1)
   - Create periods, set cutoffs
   - Basic CRUD before calculation logic
   
2. **Then Build Calculation Engine** (Week 2)
   - Fetch data from Timekeeping/Leave
   - Calculate gross pay → deductions → net pay
   
3. **Add Exceptions & Adjustments** (Week 3)
   - Exception detection rules
   - Adjustment application logic
   
4. **Finally Implement Approval Workflow** (Week 4)
   - Multi-level approval chain
   - Lock/unlock mechanism

#### Critical Integration Points

5. **Listen to Timekeeping Events**
   - Event: `AttendanceSummaryUpdated`
   - Action: Update `employee_payroll_calculations` if period active
   - Reference: PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md

6. **Listen to Leave Events**
   - Event: `LeaveApproved`
   - Action: Calculate unpaid leave deduction, update net pay
   - Reference: PAYROLL-LEAVE-INTEGRATION-ROADMAP.md

7. **Fetch Government Deductions**
   - Source: `employee_government_contributions` table
   - Timing: After calculating gross pay, before net pay
   - Reference: PAYROLL-GOVERNMENT-IMPLEMENTATION-PLAN.md

8. **Use EmployeePayroll Data**
   - Source: `employee_payroll_info`, `employee_allowances`, `employee_loans`
   - Validate completeness before calculation starts

#### Calculation Best Practices

9. **Separate Calculation Layers**
   ```
   PayrollCalculationService
     ├── TimekeepingIntegrationService (fetch attendance)
     ├── LeaveIntegrationService (fetch unpaid leave)
     ├── GrossPayCalculationService (basic + OT + allowances)
     ├── DeductionCalculationService (gov't + loans + attendance)
     └── NetPayCalculationService (gross - deductions + adjustments)
   ```

10. **Use Database Transactions**
    - Wrap entire calculation in transaction
    - Rollback if any employee calculation fails
    - All-or-nothing approach for data integrity

11. **Implement Calculation Versioning**
    - Store previous calculation before recalculating
    - Allow comparison (old vs new)
    - Easy rollback if needed

12. **Cache External Data**
    - Cache timekeeping summaries at cutoff
    - Cache leave data at cutoff
    - Prevent data drift during review

#### Testing Strategy

13. **Create Test Scenarios**
    - Regular employee (full period, no issues)
    - Employee with absences
    - Employee with unpaid leave
    - Employee with overtime
    - Employee with loans/advances
    - Employee with missing government IDs
    - Employee with high variance (exception)

14. **Test Approval Workflow**
    - Happy path (all approvals)
    - Rejection at HR Manager level
    - Rejection at Office Admin level
    - Unlock and recalculate scenario

15. **Test Data Integration**
    - Mock timekeeping data
    - Mock leave events
    - Verify calculations match manual calculations

---

### 🔗 Dependencies & Coordination

This PayrollProcessing plan must coordinate with:

1. **Timekeeping Module** ✅ Complete
   - Dependency: `daily_attendance_summaries` table
   - Event: `AttendanceSummaryUpdated`
   - Data: Present days, absences, overtime hours, tardiness

2. **Leave Management** ⏳ Events pending
   - Dependency: `leave_requests` table
   - Event: `LeaveApproved` for unpaid leave
   - Data: Unpaid leave days, deduction amount

3. **Government Module** ⏳ In progress
   - Dependency: `employee_government_contributions` table
   - Data: SSS, PhilHealth, Pag-IBIG, Tax deductions

4. **EmployeePayroll Module** ⏳ Planned
   - Dependency: `employee_payroll_info`, `employee_allowances`, `employee_loans`
   - Data: Basic salary, allowances, loan schedules

5. **Payments Module** ⏳ Planned
   - Flow: PayrollProcessing → Payments
   - Data: Finalized `employee_payroll_calculations` → `payroll_payments`

---

### ⚠️ Critical Success Factors

1. ✅ **Complete Timekeeping integration first** (attendance data is foundation)
2. ✅ **Implement Leave events** before payroll calculation (unpaid leave critical)
3. ✅ **Test calculations manually** before automation (verify formulas)
4. ✅ **Create comprehensive audit logs** (financial data requires traceability)
5. ✅ **Implement proper locking** (prevent accidental changes after approval)
6. ✅ **Handle exceptions gracefully** (don't block entire payroll for one error)
7. ✅ **Train Payroll Officer** on exception handling before go-live

---

## 📝 Next Steps

1. **Answer 30 clarification questions** above
2. **Confirm calculation formulas** (OT rates, deductions, etc.)
3. **Verify Timekeeping integration** is ready (attendance summaries available)
4. **Approve database schema** before implementation
5. **Schedule walkthrough** with Payroll Officer for workflow validation

---

**Status:** ⏳ Awaiting your feedback before implementation begins  
**Estimated Timeline:** 4-5 weeks after approval  
**Critical Path:** Timekeeping → Leave → PayrollProcessing → Payments

