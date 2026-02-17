# Payroll EmployeePayroll Feature - Complete Implementation Plan

**Feature:** Employee Payroll Information Management  
**Status:** Planning → Ready for Implementation  
**Priority:** HIGH  
**Created:** February 6, 2026  
**Estimated Duration:** 3-4 weeks  
**Target Completion:** March 6, 2026

---

## 📚 Reference Documentation

This implementation plan is based on the following specifications and documentation:

### Core Integration Documents
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](./PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Primary payroll-timekeeping integration strategy
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](./PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Leave-payroll integration (unpaid leave deductions)
- **[PAYROLL-ADVANCES-IMPLEMENTATION-PLAN.md](./PAYROLL-ADVANCES-IMPLEMENTATION-PLAN.md)** - Advances feature implementation (reference for structure)
- **[payroll-processing.md](../docs/workflows/processes/payroll-processing.md)** - Complete payroll processing workflow
- **[05-payroll-officer-workflow.md](../docs/workflows/05-payroll-officer-workflow.md)** - Payroll Officer calculation formulas and workflows
- **[PAYROLL_MODULE_ARCHITECTURE.md](../docs/PAYROLL_MODULE_ARCHITECTURE.md)** - Complete Philippine payroll architecture
- **[DATABASE_SCHEMA.md](../docs/DATABASE_SCHEMA.md)** - Database schema definitions

### Existing Code References
- **Frontend:** `resources/js/pages/Payroll/EmployeePayroll/*` (Info, Components, AllowancesDeductions, Loans)
- **Controllers:** `app/Http/Controllers/Payroll/EmployeePayroll/*` (all have mock data)
- **Components:** `resources/js/components/payroll/*` (employee-payroll-*, salary-components-*, loan-*)
- **Types:** `resources/js/types/payroll-pages.ts` (EmployeePayrollInfo, SalaryComponent interfaces)

---

## 🎯 Feature Overview

### What is EmployeePayroll Management?

**EmployeePayroll** is the foundational data structure for all payroll calculations, storing employee-specific salary information, government registration numbers, tax details, bank information, and benefit entitlements. This module manages:

1. **Employee Payroll Info** - Basic salary, payment method, tax status, government numbers
2. **Salary Components** - Reusable earning/deduction components for calculations (Basic Salary, OT, SSS, etc.)
3. **Allowances & Deductions** - Employee-specific recurring allowances (rice, COLA) and deductions (insurance, loans)
4. **Loans** - Long-term salary loans (SSS loans, Pag-IBIG loans, company loans)

### Key Business Rules

#### 1. Employee Payroll Info
- **Salary Types:** Monthly, Daily, Hourly, Contractual, Project-Based
- **Payment Methods:** Bank Transfer, Cash, Check (configurable by Office Admin)
- **Tax Status:** Philippine BIR tax statuses (Z, S, ME, S1-S4, ME1-ME4)
- **Government Numbers:** SSS, PhilHealth, Pag-IBIG, TIN (required for calculations)
- **De Minimis Benefits:** Rice, Uniform, Laundry, Medical allowances (tax-exempt up to limits)

#### 2. Salary Components
- **Component Types:** Earning, Deduction, Benefit, Tax, Contribution, Allowance
- **Categories:** Regular, Overtime, Holiday, Premium, Allowance, Deduction, Government
- **Calculation Methods:** Fixed Amount, Percentage of Basic, Percentage of Component, OT Multiplier
- **Tax Treatment:** Taxable, De Minimis, 13th Month, Other Benefits
- **Government Impact:** Affects SSS, PhilHealth, Pag-IBIG contributions

#### 3. Allowances & Deductions
- **Recurring Allowances:** Rice (₱2,000/month), COLA (₱1,000/month), Transportation, Meal
- **Recurring Deductions:** Insurance, Union Dues, Canteen, SSS Loan, Pag-IBIG Loan
- **Effective Dating:** Start/end dates for temporary allowances/deductions

#### 4. Loans
- **Loan Types:** SSS Loan, Pag-IBIG Loan, Company Loan
- **Repayment:** Monthly deductions, installment tracking, early payment support
- **Integration:** Auto-deduct during payroll calculation (similar to Advances)

### Integration Points

```
┌──────────────────────────────────────────────────────────────┐
│              EmployeePayroll Data Flow                        │
└──────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌──────────────────────────────────────────────────────────────┐
│  1. Office Admin → Configure Salary Components                │
│  2. Payroll Officer → Setup Employee Payroll Info             │
│  3. Payroll Officer → Assign Allowances & Deductions          │
│  4. Payroll Calculation → Fetch Employee Data                 │
│  5. Payroll Calculation → Apply Components                    │
│  6. Payroll Calculation → Calculate Net Pay                   │
│  7. Payslip Generation → Display Components                   │
└──────────────────────────────────────────────────────────────┘
                            │
                            ↓
┌──────────────────────────────────────────────────────────────┐
│         Integration with PayrollCalculationService            │
│                                                               │
│  PayrollCalculationService.calculateEmployee()                │
│    ├─ Fetch employee_payroll_info                            │
│    ├─ Get basic_salary, daily_rate, hourly_rate              │
│    ├─ Get tax_status, government_numbers                     │
│    ├─ Get assigned salary_components                         │
│    ├─ Get active allowances (rice, COLA, etc.)               │
│    ├─ Get active deductions (insurance, loans)               │
│    ├─ Calculate earnings (basic + OT + allowances)           │
│    ├─ Calculate deductions (tax, SSS, PhilHealth, Pag-IBIG)  │
│    ├─ Calculate loan deductions                              │
│    └─ Return employee_payroll_calculation record             │
└──────────────────────────────────────────────────────────────┘
```

---

## 🤔 Clarifications & Recommendations

### 📋 Questions for Confirmation

**Q1: Salary Component Management**
- **Q1.1:** Can Payroll Officer create custom salary components, or only Office Admin?  
  **Recommendation:** ✅ **Office Admin only** - Ensures consistency across company, prevents duplicate components
  
- **Q1.2:** Can system components (Basic Salary, SSS, PhilHealth) be edited or deleted?  
  **Recommendation:** ❌ **No** - System components are locked, only amounts can be adjusted per employee
  
- **Q1.3:** Maximum number of custom salary components allowed?  
  **Recommendation:** ✅ **No hard limit** - But recommend grouping similar components (e.g., combine all meal allowances into one)

**Q2: Employee Payroll Info Validation**
- **Q2.1:** Should government numbers (SSS, PhilHealth, Pag-IBIG, TIN) be validated for format?  
  **Recommendation:** ✅ **Yes** - Validate format:
  - SSS: `00-1234567-8` (10 digits with dashes)
  - PhilHealth: `00-123456789-0` (12 digits)
  - Pag-IBIG: `1234-5678-9012` (12 digits with dashes)
  - TIN: `123-456-789-000` (12 digits with dashes)
  
- **Q2.2:** Can employee have multiple payroll info records (salary history)?  
  **Recommendation:** ✅ **Yes** - Keep history with effective_date and end_date for salary adjustments
  
- **Q2.3:** Should basic salary be required for all salary types?  
  **Recommendation:**
  - Monthly: basic_salary required
  - Daily: daily_rate required (basic_salary optional)
  - Hourly: hourly_rate required (basic_salary optional)

**Q3: Allowances & Deductions**
- **Q3.1:** Maximum number of active allowances per employee?  
  **Recommendation:** ✅ **10-15 active allowances** (typical: rice, COLA, transportation, meal, housing, communication)
  
- **Q3.2:** Can allowances be prorated for mid-month hires?  
  **Recommendation:** ✅ **Yes** - Prorate allowances based on days worked
  
- **Q3.3:** Should allowances have expiration dates?  
  **Recommendation:** ✅ **Yes** - Use effective_date and end_date (e.g., temporary project allowance)

**Q4: Loan Management**
- **Q4.1:** Can employees have multiple loans simultaneously?  
  **Recommendation:** ✅ **Yes** - Max 1 loan per type (1 SSS + 1 Pag-IBIG + 1 Company Loan = 3 total)
  
- **Q4.2:** Maximum loan repayment period?  
  **Recommendation:**
  - SSS Loan: **24 months** (standard)
  - Pag-IBIG Loan: **60 months** (5 years for housing)
  - Company Loan: **12 months** (configurable)
  
- **Q4.3:** What happens if loan deduction exceeds net pay?  
  **Recommendation:** ✅ **Deduct maximum possible, carry forward balance** (same as Advances)

**Q5: Tax Calculation**
- **Q5.1:** Should tax calculation use annualized method or standard deduction tables?  
  **Recommendation:** ✅ **Annualized method** (BIR Tax Reform for Acceleration and Inclusion - TRAIN Law 2018)
  
- **Q5.2:** How to handle 13th month pay tax computation?  
  **Recommendation:** ✅ **First ₱90,000 tax-exempt, excess is taxable** (BIR Revenue Regulations)
  
- **Q5.3:** Should de minimis benefits be tracked separately?  
  **Recommendation:** ✅ **Yes** - Track to ensure annual limits not exceeded (₱10,000/year for rice, etc.)

**Q6: Government Contributions**
- **Q6.1:** Should SSS, PhilHealth, Pag-IBIG rates be manually entered or use lookup tables?  
  **Recommendation:** ✅ **Use lookup tables** - Create `government_contribution_rates` table (from PAYROLL_MODULE_ARCHITECTURE.md)
  
- **Q6.2:** How often are government contribution rates updated?  
  **Recommendation:** ✅ **Quarterly review** - Office Admin updates rates when government announces changes
  
- **Q6.3:** Should system auto-detect SSS bracket based on basic salary?  
  **Recommendation:** ✅ **Yes** - Auto-calculate SSS bracket, but allow manual override

---

## 📊 Suggested Implementation Approach

### ✅ Recommended Features (Must Have)

#### 1. Employee Payroll Info Management
- **Create/Edit Employee Payroll Info**
  - Salary information (basic, daily rate, hourly rate)
  - Tax status and withholding exemptions
  - Government registration numbers (SSS, PhilHealth, Pag-IBIG, TIN)
  - Bank account information
  - De minimis benefit entitlements
  
- **Salary History Tracking**
  - Maintain history of salary changes with effective dates
  - View salary adjustment timeline
  - Audit trail for all changes

- **Bulk Import/Export**
  - Import employee payroll info from CSV
  - Export for reporting and audits

#### 2. Salary Components Configuration
- **Component Library Management**
  - Create reusable salary components (earnings, deductions, allowances)
  - Define calculation methods (fixed, percentage, OT multiplier)
  - Configure tax treatment (taxable, de minimis, 13th month)
  - Set government contribution impact (SSS, PhilHealth, Pag-IBIG)
  
- **System Components Protection**
  - Lock system components (Basic Salary, SSS, PhilHealth, etc.)
  - Prevent deletion/modification of critical components

- **Component Assignment**
  - Assign components to employees
  - Define employee-specific amounts/percentages
  - Set effective dates

#### 3. Allowances & Deductions Management
- **Recurring Allowances**
  - Rice allowance (₱2,000/month standard)
  - COLA (Cost of Living Allowance)
  - Transportation allowance
  - Meal allowance
  - Housing allowance
  - Communication allowance
  
- **Recurring Deductions**
  - Company insurance premiums
  - Union dues
  - Canteen/cafeteria charges
  - Equipment/uniform charges
  - SSS loan deductions
  - Pag-IBIG loan deductions

- **Bulk Assignment**
  - Assign allowances to multiple employees (by department, position)
  - Temporary allowances (project-based, overtime incentives)

#### 4. Loan Management
- **Loan Types**
  - SSS Salary Loan
  - Pag-IBIG Multi-Purpose Loan
  - Pag-IBIG Housing Loan
  - Company Salary Loan
  
- **Loan Processing**
  - Create loan record with principal, interest, installments
  - Auto-schedule monthly deductions
  - Track remaining balance
  - Handle early repayments
  
- **Loan Deduction Integration**
  - Auto-deduct during payroll calculation
  - Update loan balance after each deduction
  - Mark loan as paid when completed

### ⚠️ Nice to Have (Phase 2)

1. **Government Contribution Calculator**
   - SSS contribution lookup table (bracket-based)
   - PhilHealth premium calculator (5% with min/max)
   - Pag-IBIG calculator (1% or 2% with ceiling)
   - Real-time contribution preview

2. **Tax Calculator**
   - BIR withholding tax calculator
   - Tax bracket lookup by status
   - Annualized tax computation
   - 13th month tax treatment

3. **Salary Comparison & Analytics**
   - Compare employee salaries by position/department
   - Salary distribution charts
   - Cost analysis (basic + allowances + employer contributions)

4. **Loan Eligibility Calculator**
   - Check employee eligibility for loans
   - Calculate maximum loan amount based on salary
   - Preview monthly deduction impact on net pay

---

## 🗄️ Database Schema Design

### Required Tables

#### 1. employee_payroll_info

```sql
CREATE TABLE employee_payroll_info (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Salary Information
    salary_type ENUM('monthly', 'daily', 'hourly', 'contractual', 'project_based') NOT NULL,
    basic_salary DECIMAL(10,2) NULL,
    daily_rate DECIMAL(8,2) NULL,
    hourly_rate DECIMAL(8,2) NULL,
    
    -- Payment Method
    payment_method ENUM('bank_transfer', 'cash', 'check') NOT NULL,
    
    -- Tax Information
    tax_status ENUM('Z', 'S', 'ME', 'S1', 'ME1', 'S2', 'ME2', 'S3', 'ME3', 'S4', 'ME4') NOT NULL,
    rdo_code VARCHAR(10) NULL,  -- BIR Revenue District Office
    withholding_tax_exemption DECIMAL(8,2) DEFAULT 0,
    is_tax_exempt BOOLEAN DEFAULT FALSE,
    is_substituted_filing BOOLEAN DEFAULT FALSE,  -- BIR substituted filing
    
    -- Government Numbers
    sss_number VARCHAR(20) NULL,
    philhealth_number VARCHAR(20) NULL,
    pagibig_number VARCHAR(20) NULL,
    tin_number VARCHAR(20) NULL,
    
    -- Government Contribution Settings
    sss_bracket VARCHAR(20) NULL,  -- E1, E2, E3, etc. (auto-calculated)
    is_sss_voluntary BOOLEAN DEFAULT FALSE,
    philhealth_is_indigent BOOLEAN DEFAULT FALSE,  -- Government-sponsored
    pagibig_employee_rate DECIMAL(4,2) DEFAULT 1.00,  -- 1% or 2%
    
    -- Bank Information
    bank_name VARCHAR(100) NULL,
    bank_code VARCHAR(20) NULL,  -- For bank file generation (e.g., "002" for BDO)
    bank_account_number VARCHAR(50) NULL,
    bank_account_name VARCHAR(100) NULL,
    
    -- De Minimis Benefits Entitlements
    is_entitled_to_rice BOOLEAN DEFAULT TRUE,
    is_entitled_to_uniform BOOLEAN DEFAULT TRUE,
    is_entitled_to_laundry BOOLEAN DEFAULT FALSE,
    is_entitled_to_medical BOOLEAN DEFAULT TRUE,
    
    -- Effective Dates (for salary history)
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_employee_active (employee_id, is_active),
    INDEX idx_salary_type (salary_type),
    INDEX idx_effective_date (effective_date),
    UNIQUE KEY unique_employee_active (employee_id, is_active) -- Only one active record per employee
);
```

#### 2. salary_components

```sql
CREATE TABLE salary_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Component Identification
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,  -- BASIC, OT_REG, RICE, SSS, TAX, etc.
    component_type ENUM('earning', 'deduction', 'benefit', 'tax', 'contribution', 'allowance') NOT NULL,
    category VARCHAR(50) NOT NULL,  -- regular, overtime, holiday, premium, allowance, deduction, government
    
    -- Calculation Settings
    calculation_method ENUM('fixed_amount', 'percentage_of_basic', 'percentage_of_component', 'ot_multiplier', 'lookup_table') NOT NULL,
    default_amount DECIMAL(10,2) NULL,  -- For fixed amount calculations
    default_percentage DECIMAL(6,2) NULL,  -- For percentage calculations (e.g., 125 for 125%)
    reference_component_id BIGINT UNSIGNED NULL,  -- For percentage_of_component calculations
    
    -- Overtime and Premium Settings
    ot_multiplier DECIMAL(4,2) NULL,  -- 1.25, 1.30, 1.50, 2.00, 2.60
    is_premium_pay BOOLEAN DEFAULT FALSE,
    
    -- Tax Treatment
    is_taxable BOOLEAN DEFAULT TRUE,
    is_deminimis BOOLEAN DEFAULT FALSE,
    deminimis_limit_monthly DECIMAL(8,2) NULL,
    deminimis_limit_annual DECIMAL(10,2) NULL,
    is_13th_month BOOLEAN DEFAULT FALSE,
    is_other_benefits BOOLEAN DEFAULT FALSE,
    
    -- Government Contribution Settings
    affects_sss BOOLEAN DEFAULT FALSE,
    affects_philhealth BOOLEAN DEFAULT FALSE,
    affects_pagibig BOOLEAN DEFAULT FALSE,
    affects_gross_compensation BOOLEAN DEFAULT TRUE,
    
    -- Display Settings
    display_order INT DEFAULT 0,
    is_displayed_on_payslip BOOLEAN DEFAULT TRUE,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    is_system_component BOOLEAN DEFAULT FALSE,  -- Cannot be deleted if true
    
    -- Audit
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (reference_component_id) REFERENCES salary_components(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_component_type (component_type),
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_system (is_system_component)
);
```

#### 3. employee_salary_components

```sql
CREATE TABLE employee_salary_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    salary_component_id BIGINT UNSIGNED NOT NULL,
    
    -- Employee-Specific Amount/Percentage
    amount DECIMAL(10,2) NULL,
    percentage DECIMAL(6,2) NULL,
    
    -- Effective Dating
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Notes
    notes TEXT NULL,
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (salary_component_id) REFERENCES salary_components(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_employee (employee_id),
    INDEX idx_component (salary_component_id),
    INDEX idx_active (is_active),
    INDEX idx_effective_date (effective_date)
);
```

#### 4. employee_allowances

```sql
CREATE TABLE employee_allowances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Allowance Details
    allowance_type VARCHAR(50) NOT NULL,  -- rice, cola, transportation, meal, housing, communication
    allowance_name VARCHAR(100) NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    
    -- Recurrence
    frequency ENUM('monthly', 'semi_monthly', 'one_time') DEFAULT 'monthly',
    
    -- Tax Treatment
    is_taxable BOOLEAN DEFAULT TRUE,
    is_deminimis BOOLEAN DEFAULT FALSE,
    
    -- Effective Dating
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Notes
    notes TEXT NULL,
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_employee_active (employee_id, is_active),
    INDEX idx_allowance_type (allowance_type),
    INDEX idx_effective_date (effective_date)
);
```

#### 5. employee_deductions

```sql
CREATE TABLE employee_deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Deduction Details
    deduction_type VARCHAR(50) NOT NULL,  -- insurance, union_dues, canteen, equipment, uniform
    deduction_name VARCHAR(100) NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    
    -- Recurrence
    frequency ENUM('monthly', 'semi_monthly', 'one_time') DEFAULT 'monthly',
    
    -- Effective Dating
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Notes
    notes TEXT NULL,
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_employee_active (employee_id, is_active),
    INDEX idx_deduction_type (deduction_type),
    INDEX idx_effective_date (effective_date)
);
```

#### 6. employee_loans

```sql
CREATE TABLE employee_loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_number VARCHAR(20) UNIQUE NOT NULL,  -- LOAN-2026-001
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Loan Details
    loan_type ENUM('sss_loan', 'pagibig_loan', 'company_loan') NOT NULL,
    loan_type_label VARCHAR(50) NOT NULL,
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(6,2) DEFAULT 0,  -- Annual interest rate (e.g., 5.00 for 5%)
    interest_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,  -- principal + interest
    
    -- Repayment Schedule
    number_of_installments INT NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,
    installments_paid INT DEFAULT 0,
    
    -- Balance Tracking
    total_paid DECIMAL(10,2) DEFAULT 0,
    remaining_balance DECIMAL(10,2) NOT NULL,
    
    -- Dates
    loan_date DATE NOT NULL,
    first_deduction_date DATE NOT NULL,
    last_deduction_date DATE NULL,
    
    -- Status
    loan_status ENUM('active', 'completed', 'cancelled', 'defaulted') DEFAULT 'active',
    completion_date DATE NULL,
    completion_reason VARCHAR(100) NULL,
    
    -- External Reference (for SSS/Pag-IBIG loans)
    external_loan_number VARCHAR(50) NULL,
    
    -- Notes
    notes TEXT NULL,
    
    -- Audit
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    
    INDEX idx_employee_status (employee_id, loan_status),
    INDEX idx_loan_type (loan_type),
    INDEX idx_loan_status (loan_status),
    INDEX idx_loan_date (loan_date)
);
```

#### 7. loan_deductions

```sql
CREATE TABLE loan_deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    payroll_period_id BIGINT UNSIGNED NOT NULL,
    employee_payroll_calculation_id BIGINT UNSIGNED NULL,
    
    -- Deduction Details
    installment_number INT NOT NULL,
    deduction_amount DECIMAL(10,2) NOT NULL,
    remaining_balance_after DECIMAL(10,2) NOT NULL,
    
    -- Status
    is_deducted BOOLEAN DEFAULT FALSE,
    deducted_at TIMESTAMP NULL,
    
    -- Notes
    deduction_notes TEXT NULL,
    
    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES employee_loans(id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
    FOREIGN KEY (employee_payroll_calculation_id) REFERENCES employee_payroll_calculations(id),
    
    INDEX idx_loan_period (loan_id, payroll_period_id),
    INDEX idx_deduction_status (is_deducted),
    UNIQUE KEY unique_loan_period (loan_id, payroll_period_id)
);
```

### Schema Alignment with Payroll Integration

**Integration with `employee_payroll_calculations` table (from PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md):**

```sql
-- From payroll_periods table (already defined in PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP)
-- From employee_payroll_calculations table (already defined)

-- Salary components will be integrated via:
employee_payroll_calculations.basic_pay DECIMAL(10,2) DEFAULT 0  -- ✅ Already in schema
employee_payroll_calculations.overtime_pay DECIMAL(8,2) DEFAULT 0  -- ✅ Already in schema
employee_payroll_calculations.rice_allowance DECIMAL(6,2) DEFAULT 0  -- ✅ Already in schema
employee_payroll_calculations.cola DECIMAL(6,2) DEFAULT 0  -- ✅ Already in schema

-- Government contributions already defined:
employee_payroll_calculations.sss_employee DECIMAL(8,2) DEFAULT 0
employee_payroll_calculations.philhealth_employee DECIMAL(8,2) DEFAULT 0
employee_payroll_calculations.pagibig_employee DECIMAL(8,2) DEFAULT 0
employee_payroll_calculations.withholding_tax DECIMAL(10,2) DEFAULT 0

-- Loan deductions already defined:
employee_payroll_calculations.sss_loan DECIMAL(8,2) DEFAULT 0
employee_payroll_calculations.pagibig_loan DECIMAL(8,2) DEFAULT 0
employee_payroll_calculations.company_loan DECIMAL(8,2) DEFAULT 0
```

---

## 🚀 Implementation Phases

### **Phase 1: Database Foundation (Week 1: Feb 6-12)**

#### Task 1.1: Create Database Migrations

**Subtask 1.1.1: Create employee_payroll_info migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000001_create_employee_payroll_info_table.php`
- **Action:** CREATE
- **Schema:** Full table structure with indexes and foreign keys
- **Validation:** Run migration, verify table structure
- **Status:** Migration executed successfully (Batch 46)
- **Completion Date:** February 17, 2026

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            
            // Salary Information
            $table->enum('salary_type', ['monthly', 'daily', 'hourly', 'contractual', 'project_based']);
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->decimal('daily_rate', 8, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            
            // Payment Method
            $table->enum('payment_method', ['bank_transfer', 'cash', 'check']);
            
            // Tax Information
            $table->enum('tax_status', ['Z', 'S', 'ME', 'S1', 'ME1', 'S2', 'ME2', 'S3', 'ME3', 'S4', 'ME4']);
            $table->string('rdo_code', 10)->nullable();
            $table->decimal('withholding_tax_exemption', 8, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('is_substituted_filing')->default(false);
            
            // Government Numbers
            $table->string('sss_number', 20)->nullable();
            $table->string('philhealth_number', 20)->nullable();
            $table->string('pagibig_number', 20)->nullable();
            $table->string('tin_number', 20)->nullable();
            
            // Government Contribution Settings
            $table->string('sss_bracket', 20)->nullable();
            $table->boolean('is_sss_voluntary')->default(false);
            $table->boolean('philhealth_is_indigent')->default(false);
            $table->decimal('pagibig_employee_rate', 4, 2)->default(1.00);
            
            // Bank Information
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            
            // De Minimis Benefits Entitlements
            $table->boolean('is_entitled_to_rice')->default(true);
            $table->boolean('is_entitled_to_uniform')->default(true);
            $table->boolean('is_entitled_to_laundry')->default(false);
            $table->boolean('is_entitled_to_medical')->default(true);
            
            // Effective Dates
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index('salary_type');
            $table->index('effective_date');
            $table->unique(['employee_id', 'is_active']); // Only one active record per employee
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_info');
    }
};
```

**Subtask 1.1.2: Create salary_components migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000002_create_salary_components_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 47)
- **Completion Date:** February 17, 2026

**Subtask 1.1.3: Create employee_salary_components migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000003_create_employee_salary_components_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 50)
- **Completion Date:** February 17, 2026

**Subtask 1.1.4: Create employee_allowances migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000004_create_employee_allowances_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 51)
- **Completion Date:** February 17, 2026

**Subtask 1.1.5: Create employee_deductions migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000005_create_employee_deductions_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 52)
- **Completion Date:** February 17, 2026

**Subtask 1.1.6: Create employee_loans migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000006_create_employee_loans_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 53)
- **Completion Date:** February 17, 2026

**Subtask 1.1.7: Create loan_deductions migration** ✅ COMPLETED
- **File:** `database/migrations/2026_02_06_000007_create_loan_deductions_table.php`
- **Action:** CREATE
- **Status:** Migration executed successfully (Batch 54)
- **Completion Date:** February 17, 2026

**Subtask 1.1.8: Run all migrations** ✅ COMPLETED
```powershell
php artisan migrate
```
- **Status:** All 7 migrations executed successfully
- **Batches:** 46, 47, 50, 51, 52, 53, 54
- **Completion Date:** February 17, 2026
- **Result:** All tables created successfully in database

#### Task 1.2: Create Eloquent Models

**Subtask 1.2.1: Create EmployeePayrollInfo model** ✅ COMPLETED
- **File:** `app/Models/EmployeePayrollInfo.php`
- **Action:** CREATE
- **Status:** Model created successfully
- **Completion Date:** February 17, 2026
- **Relationships:** belongsTo(Employee), belongsTo(User), hasMany(EmployeeSalaryComponent), hasMany(EmployeeAllowance), hasMany(EmployeeDeduction), hasMany(EmployeeLoan)
- **Scopes:** active(), byEmployee(), currentActive()
- **Accessors:** formatted_basic_salary, formatted_daily_rate, formatted_hourly_rate
- **Validation:** Government number format validation (SSS, PhilHealth, Pag-IBIG, TIN)
- **Auto-calculations:** Daily rate calculation (basic_salary / 22), Hourly rate calculation (daily_rate / 8), SSS bracket detection
- **Features Implemented:**
  - Soft deletes enabled
  - Decimal precision casting for all monetary values
  - Boolean and date casting
  - Auto-calculate derived rates from basic_salary
  - Validate government numbers: SSS (XX-XXXXXXX-X), PhilHealth (12 digits), Pag-IBIG (XXXX-XXXX-XXXX), TIN (XXX-XXX-XXX-XXX)

```php
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

    // Validation
    public static function validateGovernmentNumber(string $type, ?string $number): bool
    {
        if (!$number) return true; // Nullable fields

        return match($type) {
            'sss' => preg_match('/^\d{2}-\d{7}-\d{1}$/', $number),
            'philhealth' => preg_match('/^\d{12}$/', $number),
            'pagibig' => preg_match('/^\d{4}-\d{4}-\d{4}$/', $number),
            'tin' => preg_match('/^\d{3}-\d{3}-\d{3}-\d{3}$/', $number),
            default => false,
        };
    }

    // Boot method for auto-calculations
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate daily_rate from monthly salary
        static::saving(function ($payrollInfo) {
            if ($payrollInfo->salary_type === 'monthly' && $payrollInfo->basic_salary && !$payrollInfo->daily_rate) {
                $payrollInfo->daily_rate = $payrollInfo->basic_salary / 22; // 22 working days
            }

            // Auto-calculate hourly_rate from daily_rate
            if ($payrollInfo->daily_rate && !$payrollInfo->hourly_rate) {
                $payrollInfo->hourly_rate = $payrollInfo->daily_rate / 8; // 8 hours per day
            }

            // Auto-detect SSS bracket based on basic_salary
            if ($payrollInfo->basic_salary && !$payrollInfo->sss_bracket) {
                $payrollInfo->sss_bracket = self::calculateSSSBracket($payrollInfo->basic_salary);
            }
        });
    }

    /**
     * Calculate SSS bracket based on monthly salary
     * @todo Replace with lookup table from government_contribution_rates
     */
    private static function calculateSSSBracket(float $salary): string
    {
        if ($salary < 4250) return 'E1';
        if ($salary < 30000) return 'E2';
        if ($salary < 40000) return 'E3';
        return 'E4';
    }
}
```

**Subtask 1.2.2: Create SalaryComponent model** ✅ COMPLETED
- **File:** `app/Models/SalaryComponent.php`
- **Action:** CREATE
- **Status:** Model created successfully
- **Completion Date:** February 17, 2026
- **Relationships:** belongsTo(SalaryComponent) for reference_component_id, hasMany(SalaryComponent) referencedByComponents, hasMany(EmployeeSalaryComponent) employeeAssignments, belongsTo(User) createdBy/updatedBy
- **Scopes:** active(), byType(type), byCategory(category), systemComponents(), customComponents(), displayedOnPayslip(), ordered()
- **Accessors:** formatted_label (includes system/inactive status)
- **Validation:** isValidCalculationMethod() - validates required fields based on calculation_method
- **Protection:** Prevents deletion of system_component = true components via boot method
- **Audit:** Auto-sets created_by and updated_by via boot method
- **Features Implemented:**
  - Soft deletes enabled
  - Decimal precision casting for amounts/percentages
  - Component hierarchy support (percentage_of_component calculations)
  - OT multiplier support for overtime calculations
  - Tax treatment flags (taxable, deminimis, 13th_month, other_benefits)
  - Government contribution tracking (affects_sss, affects_philhealth, affects_pagibig)
  - Display ordering and payslip control

**Subtask 1.2.3: Create EmployeeSalaryComponent model**
- **File:** `app/Models/EmployeeSalaryComponent.php`
- **Action:** CREATE

**Subtask 1.2.4: Create EmployeeAllowance model**
- **File:** `app/Models/EmployeeAllowance.php`
- **Action:** CREATE

**Subtask 1.2.5: Create EmployeeDeduction model**
- **File:** `app/Models/EmployeeDeduction.php`
- **Action:** CREATE

**Subtask 1.2.6: Create EmployeeLoan model**
- **File:** `app/Models/EmployeeLoan.php`
- **Action:** CREATE

**Subtask 1.2.7: Create LoanDeduction model**
- **File:** `app/Models/LoanDeduction.php`
- **Action:** CREATE

**Subtask 1.2.8: Update Employee model** ✅ COMPLETED
- **File:** `app/Models/Employee.php`
- **Action:** MODIFY
- **Status:** Model updated successfully
- **Completion Date:** February 17, 2026
- **Changes Added:**
  - payrollInfo(): HasOne - Get current active payroll information
  - payrollHistory(): HasMany - Get all payroll information history
  - employeeSalaryComponents(): HasMany - Get salary component assignments
  - allowances(): HasMany - Get active allowances (is_active = true)
  - deductions(): HasMany - Get active deductions (is_active = true)
  - loans(): HasMany - Get active loans (status = 'active')

```php
// Add to Employee model
public function payrollInfo(): HasOne
{
    return $this->hasOne(EmployeePayrollInfo::class)->where('is_active', true);
}

public function payrollHistory(): HasMany
{
    return $this->hasMany(EmployeePayrollInfo::class);
}

public function activeAllowances(): HasMany
{
    return $this->hasMany(EmployeeAllowance::class)->where('is_active', true);
}

public function activeDeductions(): HasMany
{
    return $this->hasMany(EmployeeDeduction::class)->where('is_active', true);
}

public function activeLoans(): HasMany
{
    return $this->hasMany(EmployeeLoan::class)->where('loan_status', 'active');
}
```

#### Task 1.3: Seed System Salary Components

**Subtask 1.3.1: Create SalaryComponentSeeder**
- **File:** `database/seeders/SalaryComponentSeeder.php`
- **Action:** CREATE
- **Purpose:** Seed system components (Basic Salary, OT, SSS, PhilHealth, Pag-IBIG, Tax, etc.)

```php
<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    public function run(): void
    {
        $systemComponents = [
            // Earnings - Regular
            [
                'name' => 'Basic Salary',
                'code' => 'BASIC',
                'component_type' => 'earning',
                'category' => 'regular',
                'calculation_method' => 'fixed_amount',
                'is_taxable' => true,
                'affects_sss' => true,
                'affects_philhealth' => true,
                'affects_pagibig' => true,
                'affects_gross_compensation' => true,
                'display_order' => 1,
                'is_system_component' => true,
            ],
            
            // Earnings - Overtime
            [
                'name' => 'Overtime Regular',
                'code' => 'OT_REG',
                'component_type' => 'earning',
                'category' => 'overtime',
                'calculation_method' => 'ot_multiplier',
                'ot_multiplier' => 1.25,
                'is_premium_pay' => true,
                'is_taxable' => true,
                'display_order' => 5,
                'is_system_component' => true,
            ],
            
            // ... Add all system components (OT types, holiday pay, allowances, etc.)
            
            // Contributions
            [
                'name' => 'SSS Contribution',
                'code' => 'SSS',
                'component_type' => 'contribution',
                'category' => 'government',
                'calculation_method' => 'lookup_table',
                'affects_sss' => false,
                'display_order' => 20,
                'is_system_component' => true,
            ],
            
            [
                'name' => 'PhilHealth Contribution',
                'code' => 'PHILHEALTH',
                'component_type' => 'contribution',
                'category' => 'government',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 2.50, // 2.5% employee share
                'affects_philhealth' => false,
                'display_order' => 21,
                'is_system_component' => true,
            ],
            
            [
                'name' => 'Pag-IBIG Contribution',
                'code' => 'PAGIBIG',
                'component_type' => 'contribution',
                'category' => 'government',
                'calculation_method' => 'percentage_of_basic',
                'default_percentage' => 1.00, // 1% or 2% based on employee setting
                'affects_pagibig' => false,
                'display_order' => 22,
                'is_system_component' => true,
            ],
            
            // Tax
            [
                'name' => 'Withholding Tax',
                'code' => 'TAX',
                'component_type' => 'tax',
                'category' => 'tax',
                'calculation_method' => 'lookup_table',
                'display_order' => 25,
                'is_system_component' => true,
            ],
        ];

        foreach ($systemComponents as $component) {
            SalaryComponent::updateOrCreate(
                ['code' => $component['code']],
                $component
            );
        }
    }
}
```

---

### **Phase 2: Core Services & Business Logic (Week 2: Feb 13-19)**

#### Task 2.1: Create EmployeePayrollInfoService

**File:** `app/Services/Payroll/EmployeePayrollInfoService.php`
- **Action:** CREATE
- **Responsibility:** Manage employee payroll information
- **Methods:**
  - `createPayrollInfo()` - Create new employee payroll info
  - `updatePayrollInfo()` - Update payroll info (with history tracking)
  - `getActivePayrollInfo()` - Get current active payroll info for employee
  - `getPayrollHistory()` - Get salary history for employee
  - `validateGovernmentNumbers()` - Validate SSS, PhilHealth, Pag-IBIG, TIN formats
  - `calculateDerivedRates()` - Auto-calculate daily_rate, hourly_rate from basic_salary
  - `autoDetectSSSBracket()` - Calculate SSS bracket based on salary

```php
<?php

namespace App\Services\Payroll;

use App\Models\EmployeePayrollInfo;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmployeePayrollInfoService
{
    /**
     * Create new employee payroll information
     */
    public function createPayrollInfo(array $data, User $creator): EmployeePayrollInfo
    {
        // Validate government numbers
        $this->validateGovernmentNumbers($data);

        // Auto-calculate derived rates
        $data = $this->calculateDerivedRates($data);

        // Auto-detect SSS bracket
        if (!isset($data['sss_bracket']) && isset($data['basic_salary'])) {
            $data['sss_bracket'] = $this->autoDetectSSSBracket($data['basic_salary']);
        }

        // Set effective date to today if not provided
        if (!isset($data['effective_date'])) {
            $data['effective_date'] = Carbon::now()->toDateString();
        }

        // Deactivate existing payroll info for this employee
        if (isset($data['employee_id'])) {
            EmployeePayrollInfo::where('employee_id', $data['employee_id'])
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => Carbon::now()->toDateString(),
                ]);
        }

        // Create new payroll info
        $payrollInfo = EmployeePayrollInfo::create([
            ...$data,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        Log::info("Employee payroll info created", [
            'employee_id' => $payrollInfo->employee_id,
            'salary_type' => $payrollInfo->salary_type,
            'basic_salary' => $payrollInfo->basic_salary,
        ]);

        return $payrollInfo;
    }

    /**
     * Update employee payroll information (with history tracking)
     */
    public function updatePayrollInfo(EmployeePayrollInfo $payrollInfo, array $data, User $updater): EmployeePayrollInfo
    {
        DB::beginTransaction();
        try {
            // Validate government numbers
            $this->validateGovernmentNumbers($data);

            // Auto-calculate derived rates
            $data = $this->calculateDerivedRates($data);

            // Auto-detect SSS bracket if basic_salary changed
            if (isset($data['basic_salary']) && $data['basic_salary'] != $payrollInfo->basic_salary) {
                $data['sss_bracket'] = $this->autoDetectSSSBracket($data['basic_salary']);
            }

            // If salary changed, create new record with history
            if ($this->isSalaryChange($payrollInfo, $data)) {
                // End current payroll info
                $payrollInfo->update([
                    'is_active' => false,
                    'end_date' => Carbon::now()->toDateString(),
                    'updated_by' => $updater->id,
                ]);

                // Create new payroll info record
                $newPayrollInfo = EmployeePayrollInfo::create([
                    'employee_id' => $payrollInfo->employee_id,
                    ...$data,
                    'effective_date' => $data['effective_date'] ?? Carbon::now()->toDateString(),
                    'is_active' => true,
                    'created_by' => $updater->id,
                ]);

                DB::commit();

                Log::info("Employee payroll info updated with history", [
                    'employee_id' => $payrollInfo->employee_id,
                    'old_salary' => $payrollInfo->basic_salary,
                    'new_salary' => $data['basic_salary'] ?? $payrollInfo->basic_salary,
                ]);

                return $newPayrollInfo;
            } else {
                // Just update non-salary fields
                $payrollInfo->update([
                    ...$data,
                    'updated_by' => $updater->id,
                ]);

                DB::commit();

                Log::info("Employee payroll info updated", [
                    'employee_id' => $payrollInfo->employee_id,
                ]);

                return $payrollInfo;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update employee payroll info", [
                'employee_id' => $payrollInfo->employee_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get active payroll info for employee
     */
    public function getActivePayrollInfo(Employee $employee): ?EmployeePayrollInfo
    {
        return EmployeePayrollInfo::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereNull('end_date')
            ->first();
    }

    /**
     * Get payroll history for employee
     */
    public function getPayrollHistory(Employee $employee): array
    {
        $history = EmployeePayrollInfo::where('employee_id', $employee->id)
            ->orderBy('effective_date', 'desc')
            ->get();

        return $history->map(function ($record) {
            return [
                'id' => $record->id,
                'effective_date' => $record->effective_date,
                'end_date' => $record->end_date,
                'salary_type' => $record->salary_type,
                'basic_salary' => $record->basic_salary,
                'daily_rate' => $record->daily_rate,
                'is_active' => $record->is_active,
            ];
        })->toArray();
    }

    /**
     * Validate government number formats
     */
    private function validateGovernmentNumbers(array $data): void
    {
        $errors = [];

        if (isset($data['sss_number']) && !EmployeePayrollInfo::validateGovernmentNumber('sss', $data['sss_number'])) {
            $errors['sss_number'] = 'Invalid SSS number format. Expected: 00-1234567-8';
        }

        if (isset($data['philhealth_number']) && !EmployeePayrollInfo::validateGovernmentNumber('philhealth', $data['philhealth_number'])) {
            $errors['philhealth_number'] = 'Invalid PhilHealth number format. Expected: 12 digits';
        }

        if (isset($data['pagibig_number']) && !EmployeePayrollInfo::validateGovernmentNumber('pagibig', $data['pagibig_number'])) {
            $errors['pagibig_number'] = 'Invalid Pag-IBIG number format. Expected: 1234-5678-9012';
        }

        if (isset($data['tin_number']) && !EmployeePayrollInfo::validateGovernmentNumber('tin', $data['tin_number'])) {
            $errors['tin_number'] = 'Invalid TIN format. Expected: 123-456-789-000';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Calculate derived rates (daily_rate, hourly_rate)
     */
    private function calculateDerivedRates(array $data): array
    {
        // Calculate daily_rate from basic_salary if not provided
        if (isset($data['salary_type']) && $data['salary_type'] === 'monthly' && isset($data['basic_salary']) && !isset($data['daily_rate'])) {
            $data['daily_rate'] = round($data['basic_salary'] / 22, 2); // 22 working days per month
        }

        // Calculate hourly_rate from daily_rate if not provided
        if (isset($data['daily_rate']) && !isset($data['hourly_rate'])) {
            $data['hourly_rate'] = round($data['daily_rate'] / 8, 2); // 8 hours per day
        }

        return $data;
    }

    /**
     * Auto-detect SSS bracket based on monthly salary
     * @todo Replace with lookup from government_contribution_rates table
     */
    private function autoDetectSSSBracket(float $salary): string
    {
        if ($salary < 4250) return 'E1';
        if ($salary < 30000) return 'E2';
        if ($salary < 40000) return 'E3';
        return 'E4';
    }

    /**
     * Check if data contains salary changes (requires history)
     */
    private function isSalaryChange(EmployeePayrollInfo $current, array $data): bool
    {
        $salaryFields = ['basic_salary', 'daily_rate', 'hourly_rate', 'salary_type'];

        foreach ($salaryFields as $field) {
            if (isset($data[$field]) && $data[$field] != $current->{$field}) {
                return true;
            }
        }

        return false;
    }
}
```

#### Task 2.2: Create SalaryComponentService

**File:** `app/Services/Payroll/SalaryComponentService.php`
- **Action:** CREATE
- **Responsibility:** Manage salary components and employee assignments
- **Methods:**
  - `createComponent()` - Create new salary component
  - `updateComponent()` - Update salary component
  - `deleteComponent()` - Delete component (if not system component)
  - `assignComponentToEmployee()` - Assign component to employee with custom amount
  - `removeComponentFromEmployee()` - Remove component assignment
  - `getComponentsByType()` - Get components by type (earning, deduction, etc.)
  - `getEmployeeComponents()` - Get all assigned components for employee

#### Task 2.3: Create AllowanceDeductionService

**File:** `app/Services/Payroll/AllowanceDeductionService.php`
- **Action:** CREATE
- **Responsibility:** Manage employee allowances and deductions
- **Methods:**
  - `addAllowance()` - Add recurring allowance to employee
  - `removeAllowance()` - Remove allowance
  - `addDeduction()` - Add recurring deduction to employee
  - `removeDeduction()` - Remove deduction
  - `bulkAssignAllowances()` - Assign allowance to multiple employees (by department/position)
  - `getActiveAllowances()` - Get all active allowances for employee
  - `getActiveDeductions()` - Get all active deductions for employee
  - `getTotalMonthlyAllowances()` - Calculate total monthly allowances
  - `getTotalMonthlyDeductions()` - Calculate total monthly deductions

#### Task 2.4: Create LoanManagementService

**File:** `app/Services/Payroll/LoanManagementService.php`
- **Action:** CREATE
- **Responsibility:** Manage employee loans and repayments
- **Methods:**
  - `createLoan()` - Create new loan with installment schedule
  - `scheduleLoanDeductions()` - Create loan_deductions records for payroll periods
  - `processLoanDeduction()` - Process loan deduction during payroll (similar to AdvanceDeductionService)
  - `makeEarlyPayment()` - Handle early loan repayment
  - `completeLoan()` - Mark loan as completed when fully paid
  - `checkLoanEligibility()` - Check if employee can take loan
  - `getActiveLoansByType()` - Get active loans by type (SSS, Pag-IBIG, Company)

---

### **Phase 3: Integration with Payroll Calculation (Week 2: Feb 13-19)**

#### Task 3.1: Integrate EmployeePayroll into PayrollCalculationService

**File:** `app/Services/Payroll/PayrollCalculationService.php`
- **Action:** MODIFY (this service will be created in PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 3)
- **Integration Point:** Fetch employee payroll info and apply salary components

```php
// In PayrollCalculationService::calculateEmployee() method
// This code will be added to the main payroll calculation flow

use App\Services\Payroll\EmployeePayrollInfoService;
use App\Services\Payroll\AllowanceDeductionService;
use App\Services\Payroll\LoanManagementService;

public function calculateEmployee(Employee $employee, PayrollPeriod $period): EmployeePayrollCalculation
{
    // Step 1: Fetch employee payroll info
    $payrollInfo = $this->employeePayrollInfoService->getActivePayrollInfo($employee);
    
    if (!$payrollInfo) {
        throw new \Exception("Employee {$employee->id} has no active payroll information");
    }

    // Step 2: Calculate basic pay (from timekeeping data)
    $basicPay = $this->calculateBasicPay($employee, $period, $payrollInfo);

    // Step 3: Calculate overtime pay (from timekeeping data)
    $overtimePay = $this->calculateOvertimePay($employee, $period, $payrollInfo);

    // Step 4: Calculate allowances
    $allowances = $this->allowanceDeductionService->getActiveAllowances($employee);
    $totalAllowances = $allowances->sum('amount');

    // Step 5: Calculate gross pay
    $grossPay = $basicPay + $overtimePay + $totalAllowances;

    // Step 6: Calculate government contributions
    $sssEmployee = $this->calculateSSSContribution($payrollInfo);
    $philhealthEmployee = $this->calculatePhilHealthContribution($payrollInfo);
    $pagibigEmployee = $this->calculatePagIBIGContribution($payrollInfo);

    // Step 7: Calculate withholding tax
    $taxableIncome = $grossPay - $sssEmployee - $philhealthEmployee - $pagibigEmployee;
    $withholdingTax = $this->calculateWithholdingTax($taxableIncome, $payrollInfo->tax_status);

    // Step 8: Calculate other deductions
    $otherDeductions = $this->allowanceDeductionService->getActiveDeductions($employee);
    $totalOtherDeductions = $otherDeductions->sum('amount');

    // Step 9: Calculate loan deductions
    $loanDeductions = $this->loanManagementService->processLoanDeduction($employee, $period);

    // Step 10: Calculate advance deductions (from PAYROLL-ADVANCES-IMPLEMENTATION-PLAN)
    $advanceDeductions = $this->advanceDeductionService->processDeduction($employee, $period);

    // Step 11: Calculate net pay
    $totalDeductions = $sssEmployee + $philhealthEmployee + $pagibigEmployee + $withholdingTax 
                     + $totalOtherDeductions + $loanDeductions + $advanceDeductions;
    $netPay = $grossPay - $totalDeductions;

    // Step 12: Save calculation
    return EmployeePayrollCalculation::create([
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'basic_pay' => $basicPay,
        'overtime_pay' => $overtimePay,
        'rice_allowance' => $allowances->where('allowance_type', 'rice')->sum('amount'),
        'cola' => $allowances->where('allowance_type', 'cola')->sum('amount'),
        'gross_pay' => $grossPay,
        'sss_employee' => $sssEmployee,
        'philhealth_employee' => $philhealthEmployee,
        'pagibig_employee' => $pagibigEmployee,
        'withholding_tax' => $withholdingTax,
        'sss_loan' => $loanDeductions['sss_loan'] ?? 0,
        'pagibig_loan' => $loanDeductions['pagibig_loan'] ?? 0,
        'company_loan' => $loanDeductions['company_loan'] ?? 0,
        'cash_advance' => $advanceDeductions,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
    ]);
}
```

**Dependency:** This task requires `PayrollCalculationService` to be created first (from PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 3).

---

### **Phase 4: Controller & API Implementation (Week 3: Feb 20-26)**

#### Task 4.1: Update EmployeePayrollInfoController

**File:** `app/Http/Controllers/Payroll/EmployeePayroll/EmployeePayrollInfoController.php`
- **Action:** MODIFY
- **Change:** Replace mock data with real database queries

```php
<?php

namespace App\Http\Controllers\Payroll\EmployeePayroll;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayrollInfo;
use App\Models\Employee;
use App\Models\Department;
use App\Services\Payroll\EmployeePayrollInfoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeePayrollInfoController extends Controller
{
    public function __construct(
        private EmployeePayrollInfoService $payrollInfoService
    ) {}

    /**
     * Display a listing of employee payroll information
     */
    public function index(Request $request)
    {
        $query = EmployeePayrollInfo::query()
            ->with(['employee.department', 'employee.position'])
            ->where('is_active', true);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('salary_type')) {
            $query->where('salary_type', $request->input('salary_type'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->has('tax_status')) {
            $query->where('tax_status', $request->input('tax_status'));
        }

        $employees = $query->paginate(50);

        // Get available options
        $departments = Department::all(['id', 'name']);

        return Inertia::render('Payroll/EmployeePayroll/Info/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'salary_type', 'payment_method', 'tax_status']),
            'available_salary_types' => $this->getSalaryTypes(),
            'available_payment_methods' => $this->getPaymentMethods(),
            'available_tax_statuses' => $this->getTaxStatuses(),
            'available_departments' => $departments,
        ]);
    }

    /**
     * Store a newly created employee payroll info
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'salary_type' => 'required|in:monthly,daily,hourly,contractual,project_based',
            'basic_salary' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:bank_transfer,cash,check',
            'tax_status' => 'required|in:Z,S,ME,S1,ME1,S2,ME2,S3,ME3,S4,ME4',
            'sss_number' => 'nullable|string',
            'philhealth_number' => 'nullable|string',
            'pagibig_number' => 'nullable|string',
            'tin_number' => 'nullable|string',
            // ... other fields
        ]);

        try {
            $payrollInfo = $this->payrollInfoService->createPayrollInfo($validated, auth()->user());

            return redirect()
                ->route('payroll.employee-payroll-info.index')
                ->with('success', 'Employee payroll information created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    // ... other methods (update, show, destroy)
}
```

#### Task 4.2: Update SalaryComponentController

**File:** `app/Http/Controllers/Payroll/EmployeePayroll/SalaryComponentController.php`
- **Action:** MODIFY
- **Change:** Replace mock data with real database queries

#### Task 4.3: Update AllowancesDeductionsController

**File:** `app/Http/Controllers/Payroll/EmployeePayroll/AllowancesDeductionsController.php`
- **Action:** MODIFY
- **Change:** Replace mock data with real database queries

#### Task 4.4: Update LoansController

**File:** `app/Http/Controllers/Payroll/EmployeePayroll/LoansController.php`
- **Action:** MODIFY
- **Change:** Replace mock data with real database queries

---

### **Phase 5: Frontend Integration & Polish (Week 3: Feb 20-26)**

#### Task 5.1: Verify Frontend Components

**Files to Review:**
- `resources/js/pages/Payroll/EmployeePayroll/Info/Index.tsx` - **VERIFY** if handles real data correctly
- `resources/js/pages/Payroll/EmployeePayroll/Components/Index.tsx` - **VERIFY** component management
- `resources/js/pages/Payroll/EmployeePayroll/AllowancesDeductions/Index.tsx` - **VERIFY** allowance/deduction management
- `resources/js/pages/Payroll/EmployeePayroll/Loans/Index.tsx` - **VERIFY** loan management

**Action:** Review existing frontend components and update only if necessary to handle real backend data.

---

### **Phase 6: Testing & Validation (Week 4: Feb 27 - Mar 6)**

#### Task 6.1: Unit Tests

**Subtask 6.1.1: Test EmployeePayrollInfoService**
- **File:** `tests/Unit/Services/Payroll/EmployeePayrollInfoServiceTest.php`
- **Action:** CREATE
- **Test Cases:**
  - Test payroll info creation
  - Test salary history tracking
  - Test government number validation
  - Test derived rate calculations (daily_rate, hourly_rate)
  - Test SSS bracket auto-detection

**Subtask 6.1.2: Test LoanManagementService**
- **File:** `tests/Unit/Services/Payroll/LoanManagementServiceTest.php`
- **Action:** CREATE

#### Task 6.2: Integration Tests

**Subtask 6.2.1: Test EmployeePayroll-Payroll Integration**
- **File:** `tests/Feature/Payroll/EmployeePayrollIntegrationTest.php`
- **Action:** CREATE
- **Test Scenario:**
  - Create employee with payroll info
  - Assign allowances and deductions
  - Create loan
  - Run payroll calculation
  - Verify all components reflected in calculation

---

## 📋 Definition of Done

### Phase 1: Database Foundation
- ✅ All 7 tables created with migrations
- ✅ All 7 models created with relationships
- ✅ System salary components seeded
- ✅ Employee model updated with relationships

### Phase 2: Core Services
- ✅ EmployeePayrollInfoService implements all methods
- ✅ SalaryComponentService implements component management
- ✅ AllowanceDeductionService implements allowance/deduction logic
- ✅ LoanManagementService implements loan processing

### Phase 3: Payroll Integration
- ✅ PayrollCalculationService fetches employee payroll info
- ✅ Salary components applied in calculations
- ✅ Allowances added to gross pay
- ✅ Deductions and loans deducted from net pay

### Phase 4: Controller & API
- ✅ All controllers use real database queries (no mock data)
- ✅ CRUD operations functional
- ✅ Form validation working

### Phase 5: Frontend
- ✅ Frontend displays real data from backend
- ✅ All forms submit correctly
- ✅ All workflows functional

### Phase 6: Testing
- ✅ All unit tests pass
- ✅ Integration tests pass
- ✅ Manual testing complete

---

## 🔗 Integration Dependencies

### Dependencies on Other Modules (Must Wait For)

1. **Payroll Periods Table** (from PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 1)
   - Status: ⏳ **BLOCKING** - Need payroll_periods table for loan deduction scheduling

2. **EmployeePayrollCalculation Table** (from PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 1)
   - Status: ⏳ **BLOCKING** - Need employee_payroll_calculations table for integration

3. **PayrollCalculationService** (from PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 3)
   - Status: ⏳ **BLOCKING** - Need to integrate employee payroll data into calculation flow

### Can Be Implemented Independently

✅ **Phase 1 (Database)** - Can start immediately after employee base schema exists  
✅ **Phase 2 (Services)** - Can implement business logic independently  
⏳ **Phase 3 (Integration)** - Requires PayrollCalculationService to exist  
✅ **Phase 4 (Controller)** - Can implement independently  
✅ **Phase 5 (Frontend)** - Can verify with existing frontend  
✅ **Phase 6 (Testing)** - Can write tests alongside development  

---

## 📊 Timeline Summary

| Phase | Duration | Dates | Dependencies | Deliverable |
|-------|----------|-------|--------------|-------------|
| **Phase 1** | 3 days | Feb 6-8 | Employee base schema | 7 tables, 7 models, seeder |
| **Phase 2** | 4 days | Feb 9-12 | None | 4 services implemented |
| **Phase 3** | 2 days | Feb 13-14 | PayrollCalculationService | Payroll integration |
| **Phase 4** | 5 days | Feb 15-19 | None | 4 controllers with real data |
| **Phase 5** | 3 days | Feb 20-22 | None | Frontend verification |
| **Phase 6** | 5 days | Feb 23-27 | None | Testing complete |
| **Buffer** | 7 days | Feb 28-Mar 6 | None | Documentation, polish |

**Total Duration:** 22 days (4 weeks)  
**Target Completion:** March 6, 2026

---

## ✅ Next Steps

1. **Review and approve this implementation plan** with team
2. **Confirm all clarifications** (Q1-Q6) with stakeholders
3. **Verify PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP Phase 1 complete** (payroll base schema)
4. **Start Phase 1** - Create database migrations and models
5. **Set up testing environment** - Seed with test employees and payroll data

---

**Document Version:** 1.0  
**Last Updated:** February 6, 2026  
**Next Review:** After Phase 1 completion (February 8, 2026)
