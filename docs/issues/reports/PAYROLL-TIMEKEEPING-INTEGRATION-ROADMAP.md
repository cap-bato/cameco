# Payroll-Timekeeping Integration Roadmap

**Issue:** Complete Payroll backend to consume Timekeeping data for accurate salary calculations  
**Status:** Planning  
**Priority:** HIGH  
**Created:** February 5, 2026  
**Estimated Duration:** 4-5 weeks  
**Dependencies:** Timekeeping Module Phase 1 Complete, Attendance Summary System  
**Related Documents:**
- [TIMEKEEPING_MODULE_STATUS_REPORT.md](../docs/TIMEKEEPING_MODULE_STATUS_REPORT.md)
- [TIMEKEEPING_MODULE_ARCHITECTURE.md](../docs/TIMEKEEPING_MODULE_ARCHITECTURE.md)
- [payroll-processing.md](../docs/workflows/processes/payroll-processing.md)
- [LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md](./LEAVE-MANAGEMENT-INTEGRATION-ROADMAP.md)

---

## 📋 Executive Summary

**Problem:** Payroll module currently has frontend pages and mock data in controllers, but lacks real backend logic to fetch and process timekeeping data for payroll calculations.

**Current State:**
- ✅ **Timekeeping Module:** Phase 1 complete with `daily_attendance_summary` table
- ✅ **Payroll Frontend:** All pages implemented with mock data
- ✅ **Payroll Controllers:** Basic structure exists but returns mock calculations
- ❌ **Integration Missing:** No connection between Timekeeping → Payroll
- ❌ **Calculation Service Missing:** No real payroll calculation engine

**Goal:** Build event-driven payroll calculation system that:
1. Fetches attendance data from `daily_attendance_summary` table
2. Calculates gross pay based on attendance (days worked, overtime, late deductions)
3. Computes all deductions (SSS, PhilHealth, Pag-IBIG, withholding tax)
4. Handles leave-related salary adjustments (paid leave, unpaid leave)
5. Processes manual adjustments (bonuses, penalties, advances, loans)
6. Generates accurate payslips with DOLE compliance

**Timeline:** 4-5 weeks (February 5 - March 7, 2026)

---

## 🔍 Integration Analysis

### Current Architecture Assessment

#### ✅ **What Exists (Timekeeping Side)**

**Database Schema:**
```sql
-- daily_attendance_summary table (complete)
CREATE TABLE daily_attendance_summary (
    id BIGINT PRIMARY KEY,
    employee_id BIGINT NOT NULL,
    attendance_date DATE NOT NULL,
    work_schedule_id BIGINT,
    
    -- Time data
    time_in TIMESTAMP,
    time_out TIMESTAMP,
    break_start TIMESTAMP,
    break_end TIMESTAMP,
    
    -- Hours calculation
    total_hours_worked DECIMAL(5,2),
    regular_hours DECIMAL(5,2),
    overtime_hours DECIMAL(5,2),
    break_duration INT,  -- minutes
    
    -- Attendance flags
    is_present BOOLEAN DEFAULT false,
    is_late BOOLEAN DEFAULT false,
    is_undertime BOOLEAN DEFAULT false,
    is_overtime BOOLEAN DEFAULT false,
    late_minutes INT,
    undertime_minutes INT,
    
    -- Leave integration
    leave_request_id BIGINT,
    is_on_leave BOOLEAN DEFAULT false,
    
    -- Finalization
    is_finalized BOOLEAN DEFAULT false,
    calculated_at TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (work_schedule_id) REFERENCES work_schedules(id),
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id)
);
```

**Existing Services:**
- `AttendanceSummaryService.php` - Computes daily summaries from RFID events
- `LedgerPollingService.php` - Pulls events from FastAPI RFID server
- Models: `DailyAttendanceSummary`, `AttendanceEvent`, `WorkSchedule`

**Data Available for Payroll:**
- ✅ Days worked per employee per period
- ✅ Total hours (regular + overtime)
- ✅ Late minutes and undertime minutes
- ✅ Leave days (approved leave with `leave_request_id`)
- ✅ Finalized flag (locked for payroll processing)

#### ❌ **What's Missing (Payroll Side)**

**Missing Services:**
```
❌ PayrollCalculationService (core calculation engine)
❌ SalaryCalculationService (basic pay, daily rate, hourly rate)
❌ OvertimeCalculationService (OT rates, holiday multipliers)
❌ DeductionCalculationService (SSS, PhilHealth, Pag-IBIG, tax)
❌ LeavePayrollService (leave pay, unpaid leave deductions)
❌ AdjustmentService (manual adjustments, bonuses, penalties)
```

**Missing Database Tables:**
```sql
❌ payroll_periods (to track payroll periods)
❌ payroll_calculations (calculation runs)
❌ employee_payroll_calculations (individual employee calculations)
❌ payroll_adjustments (manual adjustments)
❌ salary_components (configurable components)
❌ government_rate_tables (SSS, PhilHealth, tax brackets)
```

**Missing Events:**
```
❌ PayrollPeriodCreated
❌ PayrollCalculationStarted
❌ PayrollCalculationCompleted
❌ PayrollCalculationFailed
❌ PayslipGenerated
```

**Missing Data Flow:**
```
Timekeeping (daily_attendance_summary)
        ↓
        ❌ NO CONNECTION
        ↓
Payroll (employee_payroll_calculations)
```

---

## 🎯 Integration Strategy: Event-Driven + Service Layer

### Core Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Payroll Officer Dashboard                     │
│                  "Start Payroll Calculation"                     │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│              PayrollCalculationController                        │
│  - Create payroll period (start date, end date, cutoff)         │
│  - Dispatch: PayrollCalculationStarted event                    │
│  - Queue: CalculatePayrollJob                                   │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│                  CalculatePayrollJob (Queue)                     │
│  - Fetch all active employees                                   │
│  - For each employee: dispatch CalculateEmployeePayrollJob      │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│           CalculateEmployeePayrollJob (Queue)                    │
│  1. Fetch attendance data from daily_attendance_summary          │
│  2. Call SalaryCalculationService                               │
│  3. Call OvertimeCalculationService                             │
│  4. Call LeavePayrollService                                    │
│  5. Call DeductionCalculationService                            │
│  6. Save to employee_payroll_calculations                       │
│  7. Dispatch: EmployeePayrollCalculated event                   │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│              SERVICE LAYER (Calculation Logic)                   │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ SalaryCalculationService                                    │ │
│  │ - Calculate basic pay (monthly, daily, hourly)             │ │
│  │ - Apply daily rate for days worked                         │ │
│  │ - Query: daily_attendance_summary WHERE is_present=true    │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ OvertimeCalculationService                                  │ │
│  │ - Calculate OT pay (regular OT, rest day OT, holiday OT)   │ │
│  │ - Query: SUM(overtime_hours) from daily_attendance_summary │ │
│  │ - Apply OT multipliers (1.25x, 1.3x, 2.0x)                │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ LeavePayrollService                                         │ │
│  │ - Count paid leave days (is_on_leave=true + approved)      │ │
│  │ - Count unpaid leave days                                  │ │
│  │ - Deduct salary for unpaid leave                           │ │
│  │ - Query: JOIN leave_requests to check leave type           │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ DeductionCalculationService                                 │ │
│  │ - SSS contribution (lookup SSS table)                      │ │
│  │ - PhilHealth premium (2.5% of gross)                       │ │
│  │ - Pag-IBIG contribution (1-2% of gross)                    │ │
│  │ - Withholding tax (BIR tax table)                          │ │
│  │ - Late deductions (late_minutes * hourly rate)             │ │
│  │ - Undertime deductions (undertime_minutes * hourly rate)   │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ AdjustmentService                                           │ │
│  │ - Apply manual adjustments (bonuses, penalties)            │ │
│  │ - Deduct loans and advances                                │ │
│  │ - Apply one-time deductions                                │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│                  EVENT LISTENERS                                 │
│                                                                  │
│  - UpdatePayrollProgress (update calculation progress %)        │
│  - GeneratePayslip (when all employees calculated)              │
│  - NotifyPayrollOfficer (calculation complete)                  │
│  - SendPayrollReviewNotification (to HR Manager, Office Admin)  │
└─────────────────────────────────────────────────────────────────┘
```

### Key Integration Points

**1. Attendance Data Fetch**
```php
// In CalculateEmployeePayrollJob
$attendanceSummaries = DailyAttendanceSummary::query()
    ->where('employee_id', $employeeId)
    ->whereBetween('attendance_date', [$periodStart, $periodEnd])
    ->where('is_finalized', true)  // Only process finalized records
    ->get();

$daysWorked = $attendanceSummaries->where('is_present', true)->count();
$totalOvertimeHours = $attendanceSummaries->sum('overtime_hours');
$totalLateMinutes = $attendanceSummaries->sum('late_minutes');
$totalUndertimeMinutes = $attendanceSummaries->sum('undertime_minutes');
$leaveDays = $attendanceSummaries->where('is_on_leave', true)->count();
```

**2. Basic Pay Calculation**
```php
// SalaryCalculationService
public function calculateBasicPay(Employee $employee, int $daysWorked, Carbon $periodStart, Carbon $periodEnd): float
{
    $payrollInfo = $employee->payrollInfo;
    
    if ($payrollInfo->salary_type === 'monthly') {
        // Monthly salary: pro-rate based on days worked
        $workingDaysInMonth = $this->getWorkingDaysInPeriod($periodStart, $periodEnd);
        return ($payrollInfo->basic_salary / $workingDaysInMonth) * $daysWorked;
    }
    
    if ($payrollInfo->salary_type === 'daily') {
        // Daily rate: multiply by days worked
        return $payrollInfo->daily_rate * $daysWorked;
    }
    
    // Hourly rate calculation would be more complex
    // Need to sum actual hours worked from attendance
}
```

**3. Overtime Calculation**
```php
// OvertimeCalculationService
public function calculateOvertimePay(Employee $employee, float $overtimeHours, array $overtimeBreakdown): float
{
    $hourlyRate = $this->getHourlyRate($employee);
    $overtimePay = 0;
    
    // Regular OT: 1.25x (after 8 hours on normal working day)
    $regularOT = $overtimeBreakdown['regular_overtime_hours'] ?? 0;
    $overtimePay += $regularOT * $hourlyRate * 1.25;
    
    // Rest day OT: 1.3x
    $restDayOT = $overtimeBreakdown['rest_day_overtime_hours'] ?? 0;
    $overtimePay += $restDayOT * $hourlyRate * 1.3;
    
    // Holiday OT: 2.0x
    $holidayOT = $overtimeBreakdown['holiday_overtime_hours'] ?? 0;
    $overtimePay += $holidayOT * $hourlyRate * 2.0;
    
    return $overtimePay;
}
```

**4. Leave Impact on Payroll**
```php
// LeavePayrollService
public function calculateLeaveImpact(Employee $employee, Collection $attendanceSummaries): array
{
    $paidLeaveDays = 0;
    $unpaidLeaveDays = 0;
    
    foreach ($attendanceSummaries->where('is_on_leave', true) as $summary) {
        $leaveRequest = $summary->leaveRequest;
        
        if ($leaveRequest && $leaveRequest->is_paid) {
            $paidLeaveDays++;
        } else {
            $unpaidLeaveDays++;
        }
    }
    
    // Paid leave: Pay full salary (no deduction)
    $paidLeaveAmount = $paidLeaveDays * $employee->payrollInfo->daily_rate;
    
    // Unpaid leave: Deduct from salary
    $unpaidLeaveDeduction = $unpaidLeaveDays * $employee->payrollInfo->daily_rate;
    
    return [
        'paid_leave_days' => $paidLeaveDays,
        'unpaid_leave_days' => $unpaidLeaveDays,
        'paid_leave_amount' => $paidLeaveAmount,
        'unpaid_leave_deduction' => $unpaidLeaveDeduction,
    ];
}
```

**5. Late and Undertime Deductions**
```php
// DeductionCalculationService
public function calculateAttendanceDeductions(Employee $employee, int $lateMinutes, int $undertimeMinutes): array
{
    $hourlyRate = $this->getHourlyRate($employee);
    $minuteRate = $hourlyRate / 60;
    
    $lateDeduction = $lateMinutes * $minuteRate;
    $undertimeDeduction = $undertimeMinutes * $minuteRate;
    
    return [
        'late_deduction' => round($lateDeduction, 2),
        'undertime_deduction' => round($undertimeDeduction, 2),
        'total_attendance_deduction' => round($lateDeduction + $undertimeDeduction, 2),
    ];
}
```

---

## 📊 Integration Gaps Analysis

### Gap 1: Missing Payroll Database Schema

**Current State:** ❌ No payroll calculation tables exist

**Required Tables:**

#### payroll_periods
```sql
CREATE TABLE payroll_periods (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,  -- "November 1-15, 2025"
    period_type ENUM('semi_monthly', 'monthly', 'weekly') DEFAULT 'semi_monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    cutoff_date DATE NOT NULL,  -- When timekeeping data is locked
    pay_date DATE NOT NULL,
    status ENUM('draft', 'calculating', 'review', 'approved', 'paid', 'archived') DEFAULT 'draft',
    total_employees INT DEFAULT 0,
    total_gross_pay DECIMAL(12,2) DEFAULT 0,
    total_deductions DECIMAL(12,2) DEFAULT 0,
    total_net_pay DECIMAL(12,2) DEFAULT 0,
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### employee_payroll_calculations
```sql
CREATE TABLE employee_payroll_calculations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payroll_period_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    
    -- Attendance-based data (from Timekeeping)
    scheduled_days INT DEFAULT 0,
    days_worked INT DEFAULT 0,
    days_absent INT DEFAULT 0,
    paid_leave_days INT DEFAULT 0,
    unpaid_leave_days INT DEFAULT 0,
    late_minutes INT DEFAULT 0,
    undertime_minutes INT DEFAULT 0,
    regular_hours DECIMAL(6,2) DEFAULT 0,
    overtime_hours DECIMAL(6,2) DEFAULT 0,
    rest_day_overtime_hours DECIMAL(6,2) DEFAULT 0,
    holiday_overtime_hours DECIMAL(6,2) DEFAULT 0,
    night_differential_hours DECIMAL(6,2) DEFAULT 0,
    
    -- Earnings
    basic_pay DECIMAL(10,2) NOT NULL,
    overtime_pay DECIMAL(10,2) DEFAULT 0,
    night_differential_pay DECIMAL(10,2) DEFAULT 0,
    holiday_pay DECIMAL(10,2) DEFAULT 0,
    paid_leave_pay DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    bonuses DECIMAL(10,2) DEFAULT 0,
    other_earnings DECIMAL(10,2) DEFAULT 0,
    gross_pay DECIMAL(10,2) NOT NULL,
    
    -- Deductions (Statutory)
    sss_contribution DECIMAL(8,2) DEFAULT 0,
    philhealth_contribution DECIMAL(8,2) DEFAULT 0,
    pagibig_contribution DECIMAL(8,2) DEFAULT 0,
    withholding_tax DECIMAL(10,2) DEFAULT 0,
    
    -- Deductions (Attendance-based)
    late_deduction DECIMAL(8,2) DEFAULT 0,
    undertime_deduction DECIMAL(8,2) DEFAULT 0,
    absence_deduction DECIMAL(8,2) DEFAULT 0,
    unpaid_leave_deduction DECIMAL(8,2) DEFAULT 0,
    
    -- Deductions (Loans & Advances)
    sss_loan DECIMAL(8,2) DEFAULT 0,
    pagibig_loan DECIMAL(8,2) DEFAULT 0,
    company_loan DECIMAL(8,2) DEFAULT 0,
    cash_advance DECIMAL(8,2) DEFAULT 0,
    
    -- Deductions (Other)
    other_deductions DECIMAL(8,2) DEFAULT 0,
    total_deductions DECIMAL(10,2) NOT NULL,
    
    -- Net Pay
    net_pay DECIMAL(10,2) NOT NULL,
    
    -- Year-to-Date Totals
    ytd_gross DECIMAL(12,2) DEFAULT 0,
    ytd_tax DECIMAL(12,2) DEFAULT 0,
    ytd_net_pay DECIMAL(12,2) DEFAULT 0,
    
    -- Metadata
    status ENUM('pending', 'calculated', 'reviewed', 'approved', 'paid') DEFAULT 'pending',
    calculation_errors JSON,  -- Array of error messages
    calculated_at TIMESTAMP,
    calculated_by BIGINT,
    reviewed_at TIMESTAMP,
    reviewed_by BIGINT,
    approved_at TIMESTAMP,
    approved_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (calculated_by) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_period_employee (payroll_period_id, employee_id),
    INDEX idx_employee_period (employee_id, payroll_period_id)
);
```

#### payroll_adjustments
```sql
CREATE TABLE payroll_adjustments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payroll_period_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    adjustment_type ENUM('bonus', 'penalty', 'allowance', 'deduction', 'correction') NOT NULL,
    component_name VARCHAR(100) NOT NULL,  -- "Performance Bonus", "Uniform Penalty"
    amount DECIMAL(10,2) NOT NULL,
    is_taxable BOOLEAN DEFAULT true,
    reason TEXT,
    created_by BIGINT NOT NULL,
    approved_by BIGINT,
    approved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);
```

### Gap 2: Missing Payroll Calculation Services

**Current State:** ❌ Controllers have mock data, no calculation logic

**Required Services:**

1. **PayrollCalculationService** - Orchestrates entire calculation
2. **SalaryCalculationService** - Basic pay, daily rate, hourly rate
3. **OvertimeCalculationService** - OT calculations with multipliers
4. **DeductionCalculationService** - SSS, PhilHealth, Pag-IBIG, tax, late deductions
5. **LeavePayrollService** - Leave pay and unpaid leave deductions
6. **AdjustmentService** - Manual adjustments, bonuses, penalties
7. **GovernmentContributionService** - SSS, PhilHealth, Pag-IBIG lookups
8. **TaxCalculationService** - BIR tax table withholding tax

### Gap 3: Missing Event System

**Current State:** ❌ No payroll events

**Required Events:**

```php
// Payroll Period Events
PayrollPeriodCreated($payrollPeriod)
PayrollPeriodLocked($payrollPeriod)
PayrollPeriodApproved($payrollPeriod)

// Calculation Events
PayrollCalculationStarted($payrollPeriod)
EmployeePayrollCalculated($employeeCalculation)
PayrollCalculationCompleted($payrollPeriod)
PayrollCalculationFailed($payrollPeriod, $errors)

// Payslip Events
PayslipGenerated($payslip)
PayslipsDistributed($payrollPeriod)
```

### Gap 4: Missing Government Rate Tables

**Current State:** ❌ Hardcoded contribution rates

**Required:**
- SSS contribution table (2026 rates)
- PhilHealth premium table
- Pag-IBIG contribution rates
- BIR tax table (TRAIN law)

---

## 🚀 Implementation Phases

### **Phase 1: Database Foundation (Week 1: Feb 5-11)**

#### Task 1.1: Create Payroll Database Migrations

**Subtask 1.1.1: Create payroll_periods migration**
- File: `database/migrations/YYYY_MM_DD_create_payroll_periods_table.php`
- Fields: name, period_type, start_date, end_date, cutoff_date, pay_date, status, totals
- Indexes: (status), (pay_date), (start_date, end_date)

**Subtask 1.1.2: Create employee_payroll_calculations migration**
- File: `database/migrations/YYYY_MM_DD_create_employee_payroll_calculations_table.php`
- Fields: All earnings, deductions, attendance data, YTD totals
- Indexes: (payroll_period_id, employee_id), (employee_id, payroll_period_id), (status)
- Foreign keys: payroll_periods, employees

**Subtask 1.1.3: Create payroll_adjustments migration**
- File: `database/migrations/YYYY_MM_DD_create_payroll_adjustments_table.php`
- Fields: adjustment_type, component_name, amount, is_taxable, reason
- Indexes: (payroll_period_id, employee_id)

**Subtask 1.1.4: Create government_rate_tables migration**
- File: `database/migrations/YYYY_MM_DD_create_government_rate_tables.php`
- Tables: sss_contribution_table, philhealth_premium_table, tax_brackets
- Populate with 2026 Philippine rates

**Subtask 1.1.5: Run migrations**
```bash
php artisan migrate
```

#### Task 1.2: Create Eloquent Models

**Subtask 1.2.1: Create PayrollPeriod model**
- File: `app/Models/PayrollPeriod.php`
- Relationships: hasMany(EmployeePayrollCalculation), hasMany(PayrollAdjustment)
- Scopes: active(), completed(), forPayDate()
- Accessors: formatted_total_net_pay, progress_percentage

**Subtask 1.2.2: Create EmployeePayrollCalculation model**
- File: `app/Models/EmployeePayrollCalculation.php`
- Relationships: belongsTo(PayrollPeriod), belongsTo(Employee)
- Scopes: byEmployee(), byPeriod(), calculated(), pending()
- Accessors: formatted_gross_pay, formatted_net_pay

**Subtask 1.2.3: Create PayrollAdjustment model**
- File: `app/Models/PayrollAdjustment.php`
- Relationships: belongsTo(PayrollPeriod), belongsTo(Employee)
- Scopes: bonuses(), penalties(), forPeriod()

**Subtask 1.2.4: Add relationships to existing models**
- Modify `app/Models/Employee.php`: hasMany(EmployeePayrollCalculation)
- Modify `app/Models/DailyAttendanceSummary.php`: Already has employee relationship

#### Task 1.3: Create Model Factories and Seeders

**Subtask 1.3.1: Create factories**
- `database/factories/PayrollPeriodFactory.php`
- `database/factories/EmployeePayrollCalculationFactory.php`
- `database/factories/PayrollAdjustmentFactory.php`

**Subtask 1.3.2: Create seeders**
- `database/seeders/PayrollPeriodSeeder.php` - Seed current and past periods
- `database/seeders/GovernmentRateSeeder.php` - Populate SSS, PhilHealth, tax tables

---

### **Phase 2: Core Calculation Services (Week 2: Feb 12-18)**

#### Task 2.1: Create SalaryCalculationService

**File:** `app/Services/Payroll/SalaryCalculationService.php`

**Methods:**
```php
public function calculateBasicPay(Employee $employee, PayrollPeriod $period, Collection $attendanceSummaries): float
public function getHourlyRate(Employee $employee): float
public function getDailyRate(Employee $employee): float
public function getWorkingDaysInPeriod(Carbon $start, Carbon $end): int
```

**Logic:**
- Handle monthly, daily, hourly salary types
- Pro-rate based on days worked
- Account for Philippine labor laws (minimum wage, 8-hour day)

#### Task 2.2: Create OvertimeCalculationService

**File:** `app/Services/Payroll/OvertimeCalculationService.php`

**Methods:**
```php
public function calculateOvertimePay(Employee $employee, float $overtimeHours, array $breakdown): float
public function getOvertimeMultiplier(string $overtimeType): float
public function categorizeOvertimeHours(Collection $attendanceSummaries): array
```

**OT Categories:**
- Regular OT: 1.25x (after 8 hours, normal working day)
- Rest day OT: 1.3x (Sunday or designated rest day)
- Special holiday OT: 1.3x (special non-working holiday)
- Regular holiday OT: 2.0x (regular holiday like Christmas, New Year)
- Double holiday OT: 2.6x (holiday + rest day)

#### Task 2.3: Create LeavePayrollService

**File:** `app/Services/Payroll/LeavePayrollService.php`

**Methods:**
```php
public function calculateLeaveImpact(Employee $employee, Collection $attendanceSummaries): array
public function getPaidLeaveDays(Collection $attendanceSummaries): int
public function getUnpaidLeaveDays(Collection $attendanceSummaries): int
public function calculateUnpaidLeaveDeduction(Employee $employee, int $unpaidDays): float
```

**Logic:**
- Join with `leave_requests` table to determine leave type
- Paid leave (VL, SL): Full salary, no deduction
- Unpaid leave: Deduct daily rate × unpaid days

#### Task 2.4: Create DeductionCalculationService

**File:** `app/Services/Payroll/DeductionCalculationService.php`

**Methods:**
```php
// Statutory deductions
public function calculateSSSContribution(float $grossPay): float
public function calculatePhilHealthPremium(float $grossPay): float
public function calculatePagIBIGContribution(float $grossPay, float $employeeRate): float
public function calculateWithholdingTax(Employee $employee, float $taxableIncome): float

// Attendance-based deductions
public function calculateLateDeduction(Employee $employee, int $lateMinutes): float
public function calculateUndertimeDeduction(Employee $employee, int $undertimeMinutes): float
public function calculateAbsenceDeduction(Employee $employee, int $absentDays): float
```

**Logic:**
- Query `sss_contribution_table` for SSS bracket
- Apply 2.5% PhilHealth premium (2026 rate)
- Apply 1-2% Pag-IBIG contribution (configurable)
- Use BIR tax table for withholding tax (TRAIN law)
- Convert late minutes to deduction (late_minutes × minute_rate)

#### Task 2.5: Create GovernmentContributionService

**File:** `app/Services/Payroll/GovernmentContributionService.php`

**Methods:**
```php
public function getSSSContributionBracket(float $monthlySalary): array
public function getPhilHealthPremium(float $monthlySalary): float
public function getPagIBIGContribution(float $monthlySalary, float $rate): float
```

**Data Sources:**
- Query from `sss_contribution_table`
- Query from `philhealth_premium_table`
- Use fixed Pag-IBIG rates (1% or 2%)

#### Task 2.6: Create TaxCalculationService

**File:** `app/Services/Payroll/TaxCalculationService.php`

**Methods:**
```php
public function calculateWithholdingTax(float $taxableIncome, string $taxStatus): float
public function getTaxBracket(float $annualizedIncome): array
public function calculateAnnualizedTax(float $semiMonthlyIncome): float
```

**Logic:**
- Use BIR tax table (TRAIN law, 2026 rates)
- Apply graduated tax rates (0%, 15%, 20%, 25%, 30%, 35%)
- Account for tax exemptions (P250,000 annual basic exemption)
- Handle different tax statuses (Single, Married, ME1, ME2, etc.)

#### Task 2.7: Create AdjustmentService

**File:** `app/Services/Payroll/AdjustmentService.php`

**Methods:**
```php
public function applyAdjustments(int $employeeId, int $periodId): array
public function addAdjustment(array $data): PayrollAdjustment
public function removeAdjustment(int $adjustmentId): bool
```

**Logic:**
- Fetch all adjustments for employee + period
- Apply bonuses (add to earnings)
- Apply penalties (add to deductions)
- Track adjustments for audit trail

---

### **Phase 3: Payroll Calculation Orchestration (Week 3: Feb 19-25)**

#### Task 3.1: Create PayrollCalculationService (Main Orchestrator)

**File:** `app/Services/Payroll/PayrollCalculationService.php`

**Methods:**
```php
public function startCalculation(PayrollPeriod $period): void
public function calculateEmployee(Employee $employee, PayrollPeriod $period): EmployeePayrollCalculation
public function recalculateEmployee(int $employeeId, int $periodId): EmployeePayrollCalculation
public function finalizeCalculation(PayrollPeriod $period): void
```

**Main Calculation Flow:**
```php
public function calculateEmployee(Employee $employee, PayrollPeriod $period): EmployeePayrollCalculation
{
    // Step 1: Fetch attendance data from Timekeeping
    $attendanceSummaries = DailyAttendanceSummary::query()
        ->where('employee_id', $employee->id)
        ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
        ->where('is_finalized', true)
        ->get();
    
    // Step 2: Calculate basic pay
    $basicPay = $this->salaryService->calculateBasicPay($employee, $period, $attendanceSummaries);
    
    // Step 3: Calculate overtime pay
    $overtimeData = $this->overtimeService->calculateOvertimePay($employee, $attendanceSummaries);
    
    // Step 4: Calculate leave impact
    $leaveData = $this->leaveService->calculateLeaveImpact($employee, $attendanceSummaries);
    
    // Step 5: Calculate gross pay
    $grossPay = $basicPay 
        + $overtimeData['overtime_pay'] 
        + $leaveData['paid_leave_amount'] 
        + $employee->allowances;
    
    // Step 6: Calculate deductions
    $deductions = $this->deductionService->calculateAllDeductions($employee, $grossPay, $attendanceSummaries);
    
    // Step 7: Apply adjustments
    $adjustments = $this->adjustmentService->applyAdjustments($employee->id, $period->id);
    
    // Step 8: Calculate net pay
    $netPay = $grossPay - $deductions['total_deductions'] + $adjustments['total_adjustments'];
    
    // Step 9: Save calculation
    return EmployeePayrollCalculation::create([
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'days_worked' => $attendanceSummaries->where('is_present', true)->count(),
        'overtime_hours' => $attendanceSummaries->sum('overtime_hours'),
        'late_minutes' => $attendanceSummaries->sum('late_minutes'),
        'undertime_minutes' => $attendanceSummaries->sum('undertime_minutes'),
        'basic_pay' => $basicPay,
        'overtime_pay' => $overtimeData['overtime_pay'],
        'paid_leave_pay' => $leaveData['paid_leave_amount'],
        'gross_pay' => $grossPay,
        'sss_contribution' => $deductions['sss'],
        'philhealth_contribution' => $deductions['philhealth'],
        'pagibig_contribution' => $deductions['pagibig'],
        'withholding_tax' => $deductions['tax'],
        'late_deduction' => $deductions['late_deduction'],
        'undertime_deduction' => $deductions['undertime_deduction'],
        'unpaid_leave_deduction' => $leaveData['unpaid_leave_deduction'],
        'total_deductions' => $deductions['total_deductions'],
        'net_pay' => $netPay,
        'status' => 'calculated',
        'calculated_at' => now(),
    ]);
}
```

#### Task 3.2: Create Payroll Calculation Jobs

**Subtask 3.2.1: Create CalculatePayrollJob**
- File: `app/Jobs/Payroll/CalculatePayrollJob.php`
- Responsibility: Orchestrate entire payroll calculation
- Logic: Fetch all active employees, dispatch CalculateEmployeePayrollJob for each

**Subtask 3.2.2: Create CalculateEmployeePayrollJob**
- File: `app/Jobs/Payroll/CalculateEmployeePayrollJob.php`
- Responsibility: Calculate payroll for single employee
- Logic: Call PayrollCalculationService::calculateEmployee()
- Error handling: Log failures, update calculation status

**Subtask 3.2.3: Create FinalizePayrollJob**
- File: `app/Jobs/Payroll/FinalizePayrollJob.php`
- Responsibility: Finalize payroll period after all calculations complete
- Logic: Update period status, calculate totals, dispatch PayrollCalculationCompleted event

#### Task 3.3: Create Payroll Events

**Subtask 3.3.1: Create PayrollPeriodCreated event**
- File: `app/Events/Payroll/PayrollPeriodCreated.php`

**Subtask 3.3.2: Create PayrollCalculationStarted event**
- File: `app/Events/Payroll/PayrollCalculationStarted.php`

**Subtask 3.3.3: Create EmployeePayrollCalculated event**
- File: `app/Events/Payroll/EmployeePayrollCalculated.php`

**Subtask 3.3.4: Create PayrollCalculationCompleted event**
- File: `app/Events/Payroll/PayrollCalculationCompleted.php`

**Subtask 3.3.5: Create PayrollCalculationFailed event**
- File: `app/Events/Payroll/PayrollCalculationFailed.php`

#### Task 3.4: Create Event Listeners

**Subtask 3.4.1: Create UpdatePayrollProgress listener**
- File: `app/Listeners/Payroll/UpdatePayrollProgress.php`
- Listen to: EmployeePayrollCalculated
- Action: Update payroll_period progress percentage

**Subtask 3.4.2: Create NotifyPayrollOfficer listener**
- File: `app/Listeners/Payroll/NotifyPayrollOfficer.php`
- Listen to: PayrollCalculationCompleted
- Action: Send notification to Payroll Officer for review

**Subtask 3.4.3: Create LogPayrollCalculation listener**
- File: `app/Listeners/Payroll/LogPayrollCalculation.php`
- Listen to: PayrollCalculationStarted, PayrollCalculationCompleted, PayrollCalculationFailed
- Action: Log calculation events to audit trail

---

### **Phase 4: Controller Integration & API Endpoints (Week 4: Feb 26 - Mar 4)**

#### Task 4.1: Update PayrollCalculationController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollCalculationController.php`

**Remove mock data, implement real logic:**

```php
public function index(Request $request): Response
{
    $periodId = $request->input('period_id');
    $status = $request->input('status');
    
    // Query real calculations from database
    $calculations = EmployeePayrollCalculation::query()
        ->with(['employee', 'payrollPeriod'])
        ->when($periodId, fn($q) => $q->where('payroll_period_id', $periodId))
        ->when($status, fn($q) => $q->where('status', $status))
        ->latest()
        ->paginate(50);
    
    $periods = PayrollPeriod::latest()->take(10)->get();
    
    return Inertia::render('Payroll/PayrollProcessing/Calculations/Index', [
        'calculations' => $calculations,
        'available_periods' => $periods,
    ]);
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'payroll_period_id' => 'required|exists:payroll_periods,id',
    ]);
    
    $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
    
    // Dispatch calculation job
    CalculatePayrollJob::dispatch($period);
    
    // Dispatch event
    event(new PayrollCalculationStarted($period));
    
    return redirect()
        ->route('payroll.calculations.index')
        ->with('success', 'Payroll calculation started. Processing in background.');
}

public function show(int $employeeId, int $periodId): Response
{
    $calculation = EmployeePayrollCalculation::query()
        ->with(['employee', 'payrollPeriod'])
        ->where('employee_id', $employeeId)
        ->where('payroll_period_id', $periodId)
        ->firstOrFail();
    
    // Fetch attendance breakdown
    $attendanceBreakdown = DailyAttendanceSummary::query()
        ->where('employee_id', $employeeId)
        ->whereBetween('attendance_date', [
            $calculation->payrollPeriod->start_date,
            $calculation->payrollPeriod->end_date,
        ])
        ->where('is_finalized', true)
        ->get();
    
    return Inertia::render('Payroll/PayrollProcessing/Calculations/Show', [
        'calculation' => $calculation,
        'attendance_breakdown' => $attendanceBreakdown,
    ]);
}
```

#### Task 4.2: Update PayrollPeriodController

**File:** `app/Http/Controllers/Payroll/PayrollProcessing/PayrollPeriodController.php`

**Implement real CRUD operations:**

```php
public function index(Request $request): Response
{
    $periods = PayrollPeriod::query()
        ->latest('start_date')
        ->paginate(20);
    
    return Inertia::render('Payroll/PayrollProcessing/Periods/Index', [
        'periods' => $periods,
    ]);
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'period_type' => 'required|in:semi_monthly,monthly,weekly',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'cutoff_date' => 'required|date',
        'pay_date' => 'required|date|after:cutoff_date',
    ]);
    
    $period = PayrollPeriod::create([
        ...$validated,
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);
    
    event(new PayrollPeriodCreated($period));
    
    return redirect()
        ->route('payroll.periods.index')
        ->with('success', 'Payroll period created successfully.');
}
```

#### Task 4.3: Create PayrollReportController

**File:** `app/Http/Controllers/Payroll/Reports/PayrollReportController.php`

**Methods:**
```php
public function attendanceSummary(int $periodId): Response
public function payrollRegister(int $periodId): Response
public function departmentBreakdown(int $periodId): Response
public function exportToExcel(int $periodId): BinaryFileResponse
```

---

### **Phase 5: Testing & Validation (Week 5: Mar 5-7)**

#### Task 5.1: Unit Tests

**Subtask 5.1.1: Test SalaryCalculationService**
- File: `tests/Unit/Services/Payroll/SalaryCalculationServiceTest.php`
- Test cases: monthly salary, daily rate, hourly rate, pro-rating

**Subtask 5.1.2: Test OvertimeCalculationService**
- File: `tests/Unit/Services/Payroll/OvertimeCalculationServiceTest.php`
- Test cases: regular OT, rest day OT, holiday OT, multipliers

**Subtask 5.1.3: Test DeductionCalculationService**
- File: `tests/Unit/Services/Payroll/DeductionCalculationServiceTest.php`
- Test cases: SSS, PhilHealth, Pag-IBIG, tax, late deductions

**Subtask 5.1.4: Test LeavePayrollService**
- File: `tests/Unit/Services/Payroll/LeavePayrollServiceTest.php`
- Test cases: paid leave, unpaid leave, mixed leave types

#### Task 5.2: Integration Tests

**Subtask 5.2.1: Test complete payroll calculation flow**
- File: `tests/Feature/Payroll/PayrollCalculationTest.php`
- Test: Create period → Start calculation → Verify results

**Subtask 5.2.2: Test Timekeeping-Payroll integration**
- File: `tests/Feature/Payroll/TimekeepingIntegrationTest.php`
- Test: Create attendance data → Calculate payroll → Verify attendance impact

**Subtask 5.2.3: Test Leave-Payroll integration**
- File: `tests/Feature/Payroll/LeavePayrollIntegrationTest.php`
- Test: Create leave requests → Calculate payroll → Verify leave deductions

#### Task 5.3: Manual Testing Scenarios

**Scenario 1: Regular Employee (Monthly Salary)**
- Create employee with ₱30,000 monthly salary
- Create attendance records (all present, 22 days)
- Calculate payroll → Verify full salary

**Scenario 2: Employee with Overtime**
- Create employee with ₱25,000 monthly salary
- Create attendance with 10 hours overtime
- Calculate payroll → Verify OT pay (1.25x hourly rate)

**Scenario 3: Employee with Late and Undertime**
- Create employee with ₱20,000 monthly salary
- Create attendance with 60 minutes late, 30 minutes undertime
- Calculate payroll → Verify deductions

**Scenario 4: Employee with Unpaid Leave**
- Create employee with ₱35,000 monthly salary
- Create 3 days unpaid leave
- Calculate payroll → Verify leave deduction (3 × daily rate)

**Scenario 5: Employee with Bonuses and Penalties**
- Create employee with ₱40,000 monthly salary
- Add ₱5,000 performance bonus
- Add ₱1,000 uniform penalty
- Calculate payroll → Verify adjustments

#### Task 5.4: Edge Cases Testing

**Test Case 1: Mid-month hire**
- Employee hired on 10th of month
- Payroll period: 1st-15th
- Expected: Pro-rated salary (5 days only)

**Test Case 2: End-of-month resignation**
- Employee resigned on 20th of month
- Payroll period: 1st-31st
- Expected: Pro-rated salary (20 days only)

**Test Case 3: Zero attendance**
- Employee absent entire period
- Expected: Zero pay (unless approved leave)

**Test Case 4: Holiday overtime**
- Employee worked 8 hours on Christmas Day
- Expected: Holiday pay (2.0x) + basic pay

---

## 📋 Definition of Done

### Phase 1: Database Foundation
- ✅ All migrations created and run successfully
- ✅ All models created with relationships
- ✅ Factories and seeders functional
- ✅ Government rate tables populated with 2026 Philippine rates

### Phase 2: Core Calculation Services
- ✅ All 7 calculation services implemented
- ✅ Unit tests pass for each service
- ✅ Services handle edge cases (zero hours, negative values)
- ✅ Tax calculations comply with BIR TRAIN law

### Phase 3: Payroll Calculation Orchestration
- ✅ PayrollCalculationService orchestrates all sub-services correctly
- ✅ Calculation jobs process in background without blocking UI
- ✅ Events dispatched at appropriate times
- ✅ Event listeners update progress and send notifications
- ✅ Error handling for failed calculations

### Phase 4: Controller Integration
- ✅ Controllers fetch real data from database (no mock data)
- ✅ Payroll calculation triggered from UI and processes correctly
- ✅ Frontend displays real payroll data
- ✅ Pagination, filtering, sorting work correctly

### Phase 5: Testing & Validation
- ✅ All unit tests pass (80%+ code coverage for services)
- ✅ All integration tests pass
- ✅ Manual test scenarios completed successfully
- ✅ Edge cases handled correctly
- ✅ Payroll calculations match manual verification

### Overall Integration Success Criteria
- ✅ **Timekeeping → Payroll:** Attendance data flows correctly
- ✅ **Leave → Payroll:** Leave requests impact payroll correctly
- ✅ **Accurate Calculations:** All calculations match Philippine labor law
- ✅ **DOLE Compliance:** Payslips meet DOLE requirements
- ✅ **Audit Trail:** All calculations logged with timestamps and user IDs
- ✅ **Performance:** Payroll calculation for 150 employees completes in < 5 minutes

---

## 🔗 Integration Dependencies

### Ready Now (Can Start Immediately)
- ✅ **Timekeeping Module:** `daily_attendance_summary` table complete
- ✅ **Employee Module:** `employees` table and payroll info ready
- ✅ **Leave Module:** `leave_requests` table ready for integration

### Dependencies for Future Phases
- ⏳ **Leave Management Events:** Wait for Leave module event system (Phase 2 of Leave Integration)
- ⏳ **Payroll Execution History:** For tracking payment distribution
- ⏳ **Bank Integration:** For future digital payment methods

---

## 📊 Success Metrics

### Calculation Accuracy
- **Target:** 100% accuracy (zero discrepancies vs manual calculation)
- **Validation:** Compare 20 random employee calculations with manual Excel calculations

### Performance
- **Target:** Calculate payroll for 150 employees in < 5 minutes
- **Current:** N/A (not implemented yet)
- **Measurement:** Time from "Start Calculation" button to "Calculation Complete" status

### Integration Completeness
- **Target:** 100% of attendance data used in payroll
- **Current:** 0% (no integration)
- **Validation:** Verify every `is_finalized=true` attendance record reflected in payroll

### Error Rate
- **Target:** < 1% calculation failures
- **Measurement:** (Failed calculations / Total calculations) × 100

---

## 🚨 Known Challenges & Mitigation

### Challenge 1: Complex Tax Calculations (BIR TRAIN Law)
**Issue:** Philippine tax table has graduated rates with annualization requirements

**Mitigation:**
- Create comprehensive TaxCalculationService with BIR table lookup
- Add unit tests for all tax brackets
- Validate against BIR calculator online

### Challenge 2: Handling Mid-Month Hires/Resignations
**Issue:** Pro-rating salary for partial periods

**Mitigation:**
- Add `getWorkingDaysInPeriod()` method
- Pro-rate based on actual days worked (from attendance)
- Test edge cases thoroughly

### Challenge 3: Race Conditions in Concurrent Calculations
**Issue:** Multiple jobs updating same payroll period totals

**Mitigation:**
- Use database locks when updating payroll_period totals
- Implement retry logic for failed updates
- Use queue jobs with proper job chaining

### Challenge 4: Leave Request Timing
**Issue:** Leave approved after payroll calculation started

**Mitigation:**
- Lock payroll period when calculation starts (`cutoff_date`)
- Finalize all attendance records before payroll calculation
- Implement recalculation workflow for late changes

---

## 📝 Post-Implementation Tasks

### Task 1: Documentation
- Update API documentation with new payroll endpoints
- Create payroll calculation algorithm documentation
- Document troubleshooting procedures

### Task 2: Training
- Train Payroll Officers on new system
- Create user guide for payroll calculation workflow
- Document common issues and solutions

### Task 3: Monitoring
- Set up alerts for calculation failures
- Monitor calculation performance
- Track discrepancy reports

### Task 4: Future Enhancements
- Implement payroll analytics dashboard
- Add payroll forecasting (predict next period costs)
- Integrate with government reporting systems (SSS, PhilHealth, BIR)

---

## 🎯 Timeline Summary

| Phase | Duration | Dates | Deliverable |
|-------|----------|-------|-------------|
| **Phase 1** | 1 week | Feb 5-11 | Database schema, models, seeders |
| **Phase 2** | 1 week | Feb 12-18 | All calculation services implemented |
| **Phase 3** | 1 week | Feb 19-25 | Jobs, events, orchestration complete |
| **Phase 4** | 1 week | Feb 26 - Mar 4 | Controllers updated, frontend integrated |
| **Phase 5** | 3 days | Mar 5-7 | Testing complete, validation passed |

**Total Duration:** 4.5 weeks (February 5 - March 7, 2026)

---

## ✅ Next Steps

1. **Review this roadmap** with team and stakeholders
2. **Confirm government rate tables** are accurate for 2026
3. **Verify timekeeping data quality** - ensure attendance records are finalized
4. **Start Phase 1** - Create database migrations and models
5. **Set up testing environment** - Seed with test employees and attendance data

---

**Document Version:** 1.0  
**Last Updated:** February 5, 2026  
**Next Review:** After Phase 1 completion (February 11, 2026)
