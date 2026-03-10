# Payroll Reports Module - Complete Implementation Plan

**Issue Type:** Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 3-4 weeks  
**Created:** February 6, 2026  
**Status:** Planning → Ready for Implementation

---

## 📚 Reference Documentation

This implementation plan references and aligns with:

### Core Specifications
- **[PAYROLL_MODULE_ARCHITECTURE.md](../PAYROLL_MODULE_ARCHITECTURE.md)** - Payroll formulas, government rates, report requirements
- **[payroll-processing.md](../workflows/processes/payroll-processing.md)** - Payroll processing workflow and reporting timeline
- **[PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md](./PAYROLL-TIMEKEEPING-INTEGRATION-ROADMAP.md)** - Timekeeping data integration (attendance summaries)
- **[PAYROLL-LEAVE-INTEGRATION-ROADMAP.md](./PAYROLL-LEAVE-INTEGRATION-ROADMAP.md)** - Leave deduction calculations

### Related Implementation Plans
- **Government Module:** SSS, PhilHealth, Pag-IBIG, BIR reports generation
- **PayrollProcessing Module:** Payroll calculations, approval workflow (data source for reports)
- **Payments Module:** Payment distribution tracking (data source for audit trail)
- **EmployeePayroll Module:** Salary configuration (data source for analytics)

### Integration Points
- **Timekeeping Module:** `daily_attendance_summaries` table for attendance-based reports
- **Leave Management Module:** `LeaveApproved` event for leave-based deduction reports
- **Government Module:** `government_contribution_rates`, `tax_brackets` tables for compliance reports
- **PayrollProcessing Module:** `payroll_periods`, `employee_payroll_calculations`, `payroll_approval_history` tables

---

## 📋 Executive Summary

**Current State:**
- ✅ Frontend pages complete with mock data (Analytics, Audit, Register, Government Reports)
- ✅ Controllers exist but return static mock data
- ❌ No real database queries
- ❌ No report generation services
- ❌ No export functionality (PDF, Excel, CSV)
- ❌ No government report file generation (R3, RF1, MCRF, 1601C, 2316, Alphalist)

**Goal:** Build comprehensive reporting system that:
1. **Payroll Register:** Detailed payroll by department with earnings/deductions breakdown
2. **Analytics Reports:** Labor cost trends, department comparisons, YoY analysis, forecasting
3. **Audit Trail:** Complete change history with before/after comparisons
4. **Government Reports:** Generate all required government reports (SSS R3, PhilHealth RF1, Pag-IBIG MCRF, BIR 1601C/2316/Alphalist)
5. **Export Capabilities:** PDF, Excel, CSV exports for all reports
6. **Real-time Data:** Query from completed payroll periods, timekeeping, and government modules

---

## 🗂️ Database Schema

### Phase 1: Report Metadata & Audit Tables

#### 1. `report_templates` Table
Stores report configurations and templates.

```sql
CREATE TABLE report_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    report_type ENUM('register', 'analytics', 'audit', 'government', 'custom') NOT NULL,
    description TEXT,
    
    -- Configuration
    query_builder JSON, -- Store filter/query configuration
    column_config JSON, -- Column visibility, order, formatting
    grouping_config JSON, -- How to group data (by dept, by period, etc.)
    sort_config JSON,
    
    -- Scheduling
    is_scheduled BOOLEAN DEFAULT false,
    schedule_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly'),
    schedule_config JSON, -- Cron expression, recipients, etc.
    
    -- Access Control
    accessible_roles JSON, -- Which roles can access this report
    created_by BIGINT,
    
    -- Metadata
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_report_type (report_type),
    INDEX idx_is_active (is_active)
);
```

#### 2. `generated_reports` Table
Tracks all generated reports with metadata and file paths.

```sql
CREATE TABLE generated_reports (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    report_template_id BIGINT,
    
    -- Report Details
    report_name VARCHAR(255) NOT NULL,
    report_type ENUM('register', 'analytics', 'audit', 'government', 'custom') NOT NULL,
    file_format ENUM('pdf', 'excel', 'csv', 'txt', 'dat') NOT NULL,
    file_path VARCHAR(500), -- Storage path
    file_size BIGINT, -- In bytes
    
    -- Period Context
    payroll_period_id BIGINT,
    period_start_date DATE,
    period_end_date DATE,
    
    -- Generation Details
    generated_by BIGINT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generation_duration INT, -- Seconds
    record_count INT, -- Number of records in report
    
    -- Filters Applied
    filters_applied JSON, -- Store all filter parameters
    
    -- Status
    status ENUM('generating', 'completed', 'failed', 'expired') DEFAULT 'generating',
    error_message TEXT,
    
    -- Audit Trail
    download_count INT DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL, -- Auto-delete after X days
    
    FOREIGN KEY (report_template_id) REFERENCES report_templates(id),
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
    FOREIGN KEY (generated_by) REFERENCES users(id),
    
    INDEX idx_report_type (report_type),
    INDEX idx_status (status),
    INDEX idx_generated_at (generated_at),
    INDEX idx_payroll_period (payroll_period_id)
);
```

#### 3. `government_report_submissions` Table
Tracks government report submissions with reference numbers and proof of filing.

```sql
CREATE TABLE government_report_submissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    generated_report_id BIGINT NOT NULL,
    
    -- Government Agency
    agency ENUM('SSS', 'PhilHealth', 'Pag-IBIG', 'BIR') NOT NULL,
    report_type VARCHAR(50) NOT NULL, -- R3, RF1, MCRF, 1601C, 2316, Alphalist
    
    -- Period Details
    payroll_period_id BIGINT NOT NULL,
    month TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    
    -- Submission Details
    submission_date DATE NOT NULL,
    due_date DATE NOT NULL,
    is_overdue BOOLEAN DEFAULT false,
    
    -- Reference Numbers
    reference_number VARCHAR(100), -- Government reference/PRN
    confirmation_number VARCHAR(100), -- Portal confirmation
    receipt_number VARCHAR(100), -- Payment receipt
    
    -- Filing Method
    filing_method ENUM('online', 'manual', 'agent_bank') NOT NULL,
    filed_by BIGINT NOT NULL,
    
    -- Payment Details
    total_amount DECIMAL(15,2) NOT NULL,
    payment_date DATE,
    payment_reference VARCHAR(100),
    
    -- Supporting Documents
    proof_of_filing_path VARCHAR(500), -- Screenshot/PDF of confirmation
    payment_proof_path VARCHAR(500), -- Payment receipt
    
    -- Status
    submission_status ENUM('draft', 'ready', 'submitted', 'accepted', 'rejected') DEFAULT 'draft',
    acceptance_date DATE,
    rejection_reason TEXT,
    
    -- Audit Trail
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (generated_report_id) REFERENCES generated_reports(id),
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id),
    FOREIGN KEY (filed_by) REFERENCES users(id),
    
    INDEX idx_agency_period (agency, payroll_period_id),
    INDEX idx_submission_status (submission_status),
    INDEX idx_due_date (due_date),
    UNIQUE KEY unique_submission (agency, report_type, payroll_period_id)
);
```

#### 4. `report_analytics_cache` Table
Caches computed analytics to improve performance.

```sql
CREATE TABLE report_analytics_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Cache Key
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    report_type VARCHAR(50) NOT NULL,
    
    -- Period Context
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,
    
    -- Cached Data
    analytics_data JSON NOT NULL, -- Computed metrics
    
    -- Cache Management
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_valid BOOLEAN DEFAULT true,
    
    INDEX idx_cache_key (cache_key),
    INDEX idx_expires_at (expires_at),
    INDEX idx_period (period_start_date, period_end_date)
);
```

---

## 🏗️ Implementation Phases

---

## **PHASE 1: Database Foundation & Models**
**Duration:** 3 days  
**Dependencies:** None

### Task 1.1: Create Report Migrations
**Subtasks:**
1. Create migration for `report_templates` table
   - **File:** `database/migrations/2026_02_06_000001_create_report_templates_table.php`
   - **Fields:** As specified in schema
   - Add indexes for performance
   
2. Create migration for `generated_reports` table
   - **File:** `database/migrations/2026_02_06_000002_create_generated_reports_table.php`
   - Add foreign key constraints
   - Add indexes for common queries
   
3. Create migration for `government_report_submissions` table
   - **File:** `database/migrations/2026_02_06_000003_create_government_report_submissions_table.php`
   - Add unique constraint for agency+report_type+period
   
4. Create migration for `report_analytics_cache` table
   - **File:** `database/migrations/2026_02_06_000004_create_report_analytics_cache_table.php`
   - Add TTL mechanism for cache expiration

**Run migrations:**
```bash
php artisan migrate
```

### Task 1.2: Create Eloquent Models
**Subtasks:**
1. **Create `ReportTemplate` model**
   - **File:** `app/Models/ReportTemplate.php`
   - Cast JSON fields to arrays
   - Relationships: `generatedReports()`, `createdBy()`
   - Scopes: `active()`, `byType()`, `accessible()`
   - Methods: `isAccessibleBy($user)`, `generateReport($filters)`
   
2. **Create `GeneratedReport` model**
   - **File:** `app/Models/GeneratedReport.php`
   - Relationships: `template()`, `payrollPeriod()`, `generatedBy()`, `governmentSubmission()`
   - Scopes: `completed()`, `byType()`, `byPeriod()`
   - Methods: `getDownloadUrl()`, `incrementDownloadCount()`, `isExpired()`, `markAsCompleted()`, `markAsFailed()`
   - Mutators: `file_size` (human readable), `generation_duration` (formatted)
   
3. **Create `GovernmentReportSubmission` model**
   - **File:** `app/Models/GovernmentReportSubmission.php`
   - Relationships: `generatedReport()`, `payrollPeriod()`, `filedBy()`
   - Scopes: `byAgency()`, `overdue()`, `pending()`, `submitted()`
   - Methods: `isOverdue()`, `markAsSubmitted()`, `markAsAccepted()`, `markAsRejected()`
   
4. **Create `ReportAnalyticsCache` model**
   - **File:** `app/Models/ReportAnalyticsCache.php`
   - Methods: `isExpired()`, `invalidate()`, `refresh()`
   - Static methods: `get($key)`, `put($key, $data, $ttl)`, `forget($key)`, `flush()`

---

## **PHASE 2: Report Service Layer**
**Duration:** 5 days  
**Dependencies:** Phase 1 complete

### Task 2.1: Base Report Service
**Subtasks:**
1. **Create `BaseReportService` abstract class**
   - **File:** `app/Services/Payroll/Reports/BaseReportService.php`
   - Abstract methods: `generateData()`, `exportToPdf()`, `exportToExcel()`, `exportToCsv()`
   - Common methods:
     - `applyFilters($query, $filters)`
     - `formatCurrency($amount)`
     - `formatDate($date)`
     - `calculateTotals($data)`
     - `logReportGeneration($reportId)`

### Task 2.2: Payroll Register Service
**Subtasks:**
1. **Create `PayrollRegisterService`**
   - **File:** `app/Services/Payroll/Reports/PayrollRegisterService.php`
   - **Methods:**
     - `generateRegister($periodId, $filters)` - Main method
       - Query: `employee_payroll_calculations` JOIN `employees` JOIN `departments`
       - Apply filters: department, employee status, component filter, search
       - Calculate: gross pay, deductions, net pay per employee
       - Group by department
       - Calculate department totals and grand totals
     
     - `getRegisterSummary($periodId, $filters)`
       - Total employees
       - Total gross pay
       - Total deductions (SSS, PhilHealth, Pag-IBIG, tax, loans, etc.)
       - Total net pay
       - Average salary
     
     - `getDepartmentBreakdown($periodId, $filters)`
       - Per-department employee count
       - Per-department gross/net pay
       - Percentage of total payroll
     
     - `exportRegisterToPdf($periodId, $filters)`
       - Use `barryvdh/laravel-dompdf`
       - Format: Company header, period details, employee table
       - Page breaks per department (optional)
     
     - `exportRegisterToExcel($periodId, $filters)`
       - Use `maatwebsite/excel`
       - Multiple sheets: Summary, Register, Department Breakdown
       - Formulas for totals
     
     - `exportRegisterToCsv($periodId, $filters)`
       - Simple flat CSV
       - One row per employee

**Data Sources:**
- `payroll_periods` - Period details
- `employee_payroll_calculations` - Gross pay, deductions, net pay
- `employees` - Employee name, ID, position
- `departments` - Department name, code
- `employee_payment_preferences` - Payment method

### Task 2.3: Analytics Report Service
**Subtasks:**
1. **Create `PayrollAnalyticsService`**
   - **File:** `app/Services/Payroll/Reports/PayrollAnalyticsService.php`
   - **Methods:**
     - `getMonthlyLaborCostTrends($months = 12)`
       - Query last N months from `employee_payroll_calculations`
       - Aggregate: total gross, total deductions, total net by month
       - Calculate: month-over-month growth rate
       - Cache results (24 hours)
     
     - `getDepartmentComparisons($periodId)`
       - Group payroll by department
       - Calculate: total cost, average cost per employee, cost percentage
       - Rank departments by cost
     
     - `getComponentBreakdown($periodId)`
       - Break down payroll by component:
         - Basic salary (%)
         - Overtime (%)
         - Allowances (%)
         - Bonuses (%)
         - Government deductions (%)
         - Other deductions (%)
     
     - `getYearOverYearComparisons($currentPeriodId)`
       - Compare current period to same period last year
       - Metrics: total cost, employee count, avg salary
       - Calculate: YoY growth rate (%)
     
     - `getEmployeeCostAnalysis($periodId, $limit = 20)`
       - Top N highest-paid employees
       - Distribution by salary range (₱0-20k, ₱20k-40k, etc.)
       - New hires cost impact
       - Resigned employees cost savings
     
     - `getBudgetVarianceData($periodId, $budgetAmount)`
       - Actual vs. budgeted payroll
       - Variance amount and percentage
       - Breakdown by department
       - Identify over/under budget categories
     
     - `getForecastProjections($months = 6)`
       - Linear regression on historical data
       - Project future labor costs
       - Confidence intervals
       - Assumptions (new hires, attrition, raises)

**Data Sources:**
- `employee_payroll_calculations` - Historical payroll data
- `departments` - Department info
- `payroll_periods` - Period metadata
- `employees` - Headcount tracking
- **Cache:** `report_analytics_cache` table (TTL: 24 hours)

### Task 2.4: Audit Trail Service
**Subtasks:**
1. **Create `PayrollAuditService`**
   - **File:** `app/Services/Payroll/Reports/PayrollAuditService.php`
   - **Methods:**
     - `getAuditLogs($filters)`
       - Query: `payroll_calculation_logs`, `payroll_approval_history`, `payroll_adjustments`
       - Union all change logs
       - Apply filters: action, entity_type, user_id, date_range, search
       - Sort by timestamp descending
       - Paginate (50 per page)
     
     - `getChangeHistory($entityType, $entityId)`
       - Get all changes for specific entity
       - Show before/after values
       - Highlight changed fields
       - Track approval workflow progression
     
     - `getAuditStatistics($dateRange)`
       - Total audit logs
       - Changes per day
       - Most active users
       - Most modified entities
       - Action type distribution (created, updated, approved, etc.)
     
     - `exportAuditTrailToPdf($filters)`
       - Compliance-ready format
       - Include: timestamp, user, action, entity, changes, IP address
       - Tamper-evident (hash each entry)
     
     - `exportAuditTrailToExcel($filters)`
       - Filterable columns
       - Conditional formatting for action types

**Data Sources:**
- `payroll_calculation_logs` - Calculation change logs
- `payroll_approval_history` - Approval actions
- `payroll_adjustments` - Manual adjustments
- `users` - User details
- Laravel Activity Log (if implemented): `activity_log` table

### Task 2.5: Government Reports Service
**Subtasks:**
1. **Create `GovernmentReportsService`**
   - **File:** `app/Services/Payroll/Reports/GovernmentReportsService.php`
   - **Methods:**
     - `generateSSSR3Report($periodId)` - SSS R3 (Monthly Contribution)
       - Format: Fixed-width text file
       - Required fields: Employee SSS number, name, MSC, EE/ER/EC contributions
       - Aggregate: Total contributions
       - Validation: SSS number format, MSC bracket
       - **Reference:** SSS Contribution Table in PAYROLL_MODULE_ARCHITECTURE.md
     
     - `generatePhilHealthRF1Report($periodId)` - PhilHealth RF1 (Report Form 1)
       - Format: Excel template or CSV
       - Required fields: Employee PhilHealth number, name, basic salary, premium
       - Calculation: Monthly Basic × 5% (2.5% EE, 2.5% ER)
       - Max: ₱5,000 (₱2,500 each)
       - **Reference:** PhilHealth Premium Rates in PAYROLL_MODULE_ARCHITECTURE.md
     
     - `generatePagIbigMCRF($periodId)` - Pag-IBIG MCRF (Member Contribution Remittance Form)
       - Format: Excel or CSV
       - Required fields: Employee Pag-IBIG number, name, compensation, EE/ER contributions
       - Calculation: 1-2% EE, 2% ER, max ₱5,000 each
       - **Reference:** Pag-IBIG Contribution Rates in PAYROLL_MODULE_ARCHITECTURE.md
     
     - `generateBIR1601C($periodId)` - BIR 1601C (Monthly Withholding Tax)
       - Format: BIR-prescribed Excel template
       - Summary of all withholding tax for the month
       - Breakdown by employee category
       - Total tax withheld
       - **Reference:** BIR Tax Table in PAYROLL_MODULE_ARCHITECTURE.md
     
     - `generateBIR2316($year, $employeeId)` - BIR 2316 (Annual ITR)
       - Generate for each employee
       - Fields: Gross compensation, government contributions, taxable income, tax withheld
       - Must be issued by January 31 of following year
       - **Reference:** Annual Tax Reconciliation in PAYROLL_MODULE_ARCHITECTURE.md
     
     - `generateBIRAlphalist($year)` - BIR Alphalist (DAT file)
       - Format: Strict BIR DAT format
       - All employees for the year
       - Schedules: 7.1 (Compensation), 7.3 (Fringe Benefits), 7.5 (Other Income)
       - Submit with BIR Form 1604C
       - Validation: Field lengths, data types, totals match 1604C
     
     - `getGovernmentReportsSummary()`
       - Count: total reports generated, submitted, pending
       - Next deadlines
       - Compliance status per agency
     
     - `getUpcomingDeadlines()`
       - SSS: 30th of following month
       - PhilHealth: 30th of following month
       - Pag-IBIG: 30th of following month
       - BIR 1601C: 10th of following month
       - BIR 2316: January 31 of following year
       - BIR Alphalist: January 31 of following year

**Data Sources:**
- `employee_payroll_calculations` - Payroll data
- `employee_government_contributions` - Government deductions (from Government module)
- `government_contribution_rates` - Current rates (SSS brackets, PhilHealth %, etc.)
- `tax_brackets` - Tax computation
- `employees` - Employee government IDs (SSS, PhilHealth, Pag-IBIG, TIN)

---

## **PHASE 3: Update Controllers (Remove Mock Data)**
**Duration:** 3 days  
**Dependencies:** Phase 2 complete

### Task 3.1: Update PayrollRegisterController
**Subtasks:**
1. **Modify `PayrollRegisterController.php`**
   - **File:** `app/Http/Controllers/Payroll/Reports/PayrollRegisterController.php`
   - **Changes:**
     - Inject `PayrollRegisterService`
     - Replace mock data methods with service calls:
       ```php
       public function index(Request $request)
       {
           $periodId = $request->input('period_id', 'all');
           $filters = $request->only(['department_id', 'employee_status', 'component_filter', 'search']);
           
           $registerData = $this->registerService->generateRegister($periodId, $filters);
           $summary = $this->registerService->getRegisterSummary($periodId, $filters);
           $departmentBreakdown = $this->registerService->getDepartmentBreakdown($periodId, $filters);
           
           // Keep these as they're reference data:
           $periods = PayrollPeriod::select('id', 'name', 'period_type', 'start_date', 'end_date', 'pay_date')
               ->where('status', 'completed')
               ->orderBy('start_date', 'desc')
               ->get();
           
           $departments = Department::select('id', 'name')->get();
           // ... rest of the method
       }
       ```
     
     - Add export methods:
       ```php
       public function exportPdf(Request $request)
       {
           $periodId = $request->input('period_id');
           $filters = $request->only(['department_id', 'employee_status', 'component_filter', 'search']);
           
           $pdf = $this->registerService->exportRegisterToPdf($periodId, $filters);
           return $pdf->download('payroll-register-' . now()->format('Y-m-d') . '.pdf');
       }
       
       public function exportExcel(Request $request) { /* ... */ }
       public function exportCsv(Request $request) { /* ... */ }
       ```

### Task 3.2: Update PayrollAnalyticsController
**Subtasks:**
1. **Modify `PayrollAnalyticsController.php`**
   - **File:** `app/Http/Controllers/Payroll/Reports/PayrollAnalyticsController.php`
   - **Changes:**
     - Inject `PayrollAnalyticsService`
     - Replace all mock data methods:
       ```php
       public function index(Request $request)
       {
           $period = $request->query('period', 'current');
           
           $costTrendData = $this->analyticsService->getMonthlyLaborCostTrends(12);
           $departmentComparisons = $this->analyticsService->getDepartmentComparisons($period);
           $componentBreakdown = $this->analyticsService->getComponentBreakdown($period);
           $yoyComparisons = $this->analyticsService->getYearOverYearComparisons($period);
           $employeeCostAnalysis = $this->analyticsService->getEmployeeCostAnalysis($period, 20);
           // ... etc
       }
       ```
     
     - Add cache warming endpoint for admins:
       ```php
       public function refreshCache(Request $request)
       {
           $this->analyticsService->clearCache();
           return redirect()->back()->with('success', 'Analytics cache refreshed');
       }
       ```

### Task 3.3: Update PayrollAuditController
**Subtasks:**
1. **Modify `PayrollAuditController.php`**
   - **File:** `app/Http/Controllers/Payroll/Reports/PayrollAuditController.php`
   - **Changes:**
     - Inject `PayrollAuditService`
     - Replace mock data:
       ```php
       public function index(Request $request)
       {
           $filters = $request->only(['action', 'entity_type', 'user_id', 'date_range', 'search']);
           
           $auditLogs = $this->auditService->getAuditLogs($filters);
           $statistics = $this->auditService->getAuditStatistics($filters['date_range'] ?? null);
           
           return Inertia::render('Payroll/Reports/Audit', [
               'auditLogs' => $auditLogs,
               'statistics' => $statistics,
               'filters' => $filters,
           ]);
       }
       ```
     
     - Add export endpoints

### Task 3.4: Update PayrollGovernmentReportsController
**Subtasks:**
1. **Modify `PayrollGovernmentReportsController.php`**
   - **File:** `app/Http/Controllers/Payroll/Reports/PayrollGovernmentReportsController.php`
   - **Changes:**
     - Inject `GovernmentReportsService`
     - Replace mock data with real queries:
       ```php
       public function index(Request $request)
       {
           $reportsSummary = $this->governmentService->getGovernmentReportsSummary();
           $sssReports = GovernmentReportSubmission::with('generatedReport', 'payrollPeriod')
               ->where('agency', 'SSS')
               ->orderBy('year', 'desc')
               ->orderBy('month', 'desc')
               ->take(6)
               ->get();
           // ... similar for PhilHealth, Pag-IBIG, BIR
           
           $upcomingDeadlines = $this->governmentService->getUpcomingDeadlines();
           $complianceStatus = $this->governmentService->getComplianceStatus();
           
           return Inertia::render('Payroll/Reports/Government/Index', [/* ... */]);
       }
       ```
     
     - Add generation methods:
       ```php
       public function generateSSSR3(Request $request)
       {
           $validated = $request->validate([
               'period_id' => 'required|exists:payroll_periods,id',
           ]);
           
           $report = $this->governmentService->generateSSSR3Report($validated['period_id']);
           
           return redirect()->back()->with('success', 'SSS R3 report generated successfully');
       }
       
       public function generatePhilHealthRF1(Request $request) { /* ... */ }
       public function generatePagIbigMCRF(Request $request) { /* ... */ }
       public function generateBIR1601C(Request $request) { /* ... */ }
       public function generateBIR2316(Request $request) { /* ... */ }
       public function generateBIRAlphalist(Request $request) { /* ... */ }
       ```
     
     - Add download methods:
       ```php
       public function downloadReport($reportId)
       {
           $report = GeneratedReport::findOrFail($reportId);
           $report->incrementDownloadCount();
           
           return Storage::download($report->file_path, $report->report_name);
       }
       ```
     
     - Add submission tracking methods:
       ```php
       public function markAsSubmitted(Request $request, $submissionId)
       {
           $validated = $request->validate([
               'submission_date' => 'required|date',
               'reference_number' => 'required|string',
               'filing_method' => 'required|in:online,manual,agent_bank',
               'proof_of_filing' => 'nullable|file|mimes:pdf,jpg,png',
           ]);
           
           $submission = GovernmentReportSubmission::findOrFail($submissionId);
           $submission->markAsSubmitted($validated);
           
           return redirect()->back()->with('success', 'Report marked as submitted');
       }
       ```

---

## **PHASE 4: Export Functionality (PDF, Excel, CSV)**
**Duration:** 4 days  
**Dependencies:** Phase 3 complete

### Task 4.1: Install Export Libraries
**Subtasks:**
1. **Install Laravel Excel**
   ```bash
   composer require maatwebsite/excel
   php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
   ```

2. **Install Laravel DomPDF**
   ```bash
   composer require barryvdh/laravel-dompdf
   php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
   ```

### Task 4.2: Create Export Classes
**Subtasks:**
1. **Create `PayrollRegisterExport` class**
   - **File:** `app/Exports/PayrollRegisterExport.php`
   - Implement `FromCollection`, `WithHeadings`, `WithStyles`, `WithTitle`
   - Format: Company header, period details, employee rows with formulas for totals
   - Multiple sheets: Summary, Register, Department Breakdown

2. **Create `PayrollAnalyticsExport` class**
   - **File:** `app/Exports/PayrollAnalyticsExport.php`
   - Sheets: Cost Trends, Department Comparison, Component Breakdown, YoY Analysis
   - Include charts (if possible with library)

3. **Create `AuditTrailExport` class**
   - **File:** `app/Exports/AuditTrailExport.php`
   - Columns: Timestamp, User, Action, Entity Type, Entity ID, Changes Summary, IP Address
   - Filterable by date range

### Task 4.3: Create PDF Templates
**Subtasks:**
1. **Create Payroll Register PDF template**
   - **File:** `resources/views/pdf/payroll-register.blade.php`
   - Layout: Company header, period details, employee table (HTML)
   - CSS: Professional styling, page breaks, landscape orientation
   - Footer: Generated date, page numbers

2. **Create Audit Trail PDF template**
   - **File:** `resources/views/pdf/audit-trail.blade.php`
   - Compliance-focused layout
   - Tamper-evident hashing (optional)

3. **Create Government Report templates** (if needed for viewing)
   - **Files:** `resources/views/pdf/government/sss-r3.blade.php`, etc.
   - May not be needed if reports are text/Excel files

### Task 4.4: Add Export Routes
**Subtasks:**
1. **Update `routes/payroll.php`**
   ```php
   // Payroll Register Exports
   Route::get('/reports/register/export/pdf', [PayrollRegisterController::class, 'exportPdf'])
       ->name('payroll.reports.register.export.pdf');
   Route::get('/reports/register/export/excel', [PayrollRegisterController::class, 'exportExcel'])
       ->name('payroll.reports.register.export.excel');
   Route::get('/reports/register/export/csv', [PayrollRegisterController::class, 'exportCsv'])
       ->name('payroll.reports.register.export.csv');
   
   // Analytics Exports
   Route::get('/reports/analytics/export/pdf', [PayrollAnalyticsController::class, 'exportPdf'])
       ->name('payroll.reports.analytics.export.pdf');
   Route::get('/reports/analytics/export/excel', [PayrollAnalyticsController::class, 'exportExcel'])
       ->name('payroll.reports.analytics.export.excel');
   
   // Audit Trail Exports
   Route::get('/reports/audit/export/pdf', [PayrollAuditController::class, 'exportPdf'])
       ->name('payroll.reports.audit.export.pdf');
   Route::get('/reports/audit/export/excel', [PayrollAuditController::class, 'exportExcel'])
       ->name('payroll.reports.audit.export.excel');
   
   // Government Reports
   Route::post('/reports/government/sss/generate', [PayrollGovernmentReportsController::class, 'generateSSSR3'])
       ->name('payroll.reports.government.sss.generate');
   Route::post('/reports/government/philhealth/generate', [PayrollGovernmentReportsController::class, 'generatePhilHealthRF1'])
       ->name('payroll.reports.government.philhealth.generate');
   Route::post('/reports/government/pagibig/generate', [PayrollGovernmentReportsController::class, 'generatePagIbigMCRF'])
       ->name('payroll.reports.government.pagibig.generate');
   Route::post('/reports/government/bir/1601c/generate', [PayrollGovernmentReportsController::class, 'generateBIR1601C'])
       ->name('payroll.reports.government.bir.1601c.generate');
   Route::post('/reports/government/bir/2316/generate', [PayrollGovernmentReportsController::class, 'generateBIR2316'])
       ->name('payroll.reports.government.bir.2316.generate');
   Route::post('/reports/government/bir/alphalist/generate', [PayrollGovernmentReportsController::class, 'generateBIRAlphalist'])
       ->name('payroll.reports.government.bir.alphalist.generate');
   
   Route::get('/reports/government/download/{reportId}', [PayrollGovernmentReportsController::class, 'downloadReport'])
       ->name('payroll.reports.government.download');
   Route::post('/reports/government/submission/{submissionId}/mark-submitted', [PayrollGovernmentReportsController::class, 'markAsSubmitted'])
       ->name('payroll.reports.government.submission.mark-submitted');
   ```

---

## **PHASE 5: Frontend Adjustments (If Necessary)**
**Duration:** 2 days  
**Dependencies:** Phase 4 complete

### Task 5.1: Update Frontend Export Buttons
**Subtasks:**
1. **Update Register/Index.tsx**
   - **File:** `resources/js/pages/Payroll/Reports/Register/Index.tsx`
   - Wire up export buttons to new routes:
     ```tsx
     const handleExportPdf = () => {
         window.location.href = route('payroll.reports.register.export.pdf', filters);
     };
     
     const handleExportExcel = () => {
         window.location.href = route('payroll.reports.register.export.excel', filters);
     };
     
     const handleExportCsv = () => {
         window.location.href = route('payroll.reports.register.export.csv', filters);
     };
     ```

2. **Update Analytics.tsx**
   - **File:** `resources/js/pages/Payroll/Reports/Analytics.tsx`
   - Wire up export buttons

3. **Update Audit.tsx**
   - **File:** `resources/js/pages/Payroll/Reports/Audit.tsx`
   - Wire up export buttons

4. **Update Government/Index.tsx**
   - **File:** `resources/js/pages/Payroll/Reports/Government/Index.tsx`
   - Add generate buttons:
     ```tsx
     const handleGenerateSSSR3 = (periodId: number) => {
         router.post(route('payroll.reports.government.sss.generate'), { period_id: periodId }, {
             onSuccess: () => toast.success('SSS R3 generated successfully'),
         });
     };
     ```
   - Add download buttons
   - Add submission marking modals

### Task 5.2: Add Loading States & Toasts
**Subtasks:**
1. **Update all report pages**
   - Show loading spinner during export generation
   - Use `sonner` for toast notifications
   - Handle errors gracefully

### Task 5.3: Update TypeScript Types
**Subtasks:**
1. **Update `resources/js/types/payroll-pages.d.ts`**
   - Add types for new data structures (if schema changed)
   - Ensure frontend/backend alignment

---

## **PHASE 6: Integration with Timekeeping & Leave Modules**
**Duration:** 3 days  
**Dependencies:** Phase 5 complete, Timekeeping/Leave modules must have events set up

### Task 6.1: Timekeeping Integration (Attendance-Based Reports)
**Subtasks:**
1. **Query `daily_attendance_summaries` in reports**
   - **In:** `PayrollRegisterService`, `PayrollAnalyticsService`
   - **Purpose:** Show attendance metrics alongside payroll data
   - **Example:**
     ```php
     // In PayrollRegisterService::generateRegister()
     $employees = Employee::with([
         'payrollCalculation' => fn($q) => $q->where('payroll_period_id', $periodId),
         'attendanceSummaries' => fn($q) => $q->whereBetween('attendance_date', [$startDate, $endDate])
     ])->get();
     
     foreach ($employees as $employee) {
         $totalDaysWorked = $employee->attendanceSummaries->where('is_present', true)->count();
         $totalLateMinutes = $employee->attendanceSummaries->sum('late_minutes');
         $totalOvertimeHours = $employee->attendanceSummaries->sum('overtime_hours');
         // Include in register data
     }
     ```

2. **Add attendance columns to Payroll Register**
   - Days worked
   - Late instances
   - Overtime hours
   - Undertime hours
   - Absent days

3. **Create Attendance Impact Analytics**
   - **In:** `PayrollAnalyticsService`
   - Metrics: Average overtime hours, attendance rate by department, tardiness trends

### Task 6.2: Leave Management Integration (Leave-Based Deductions)
**Subtasks:**
1. **Query leave deductions in reports**
   - **From:** `employee_payroll_calculations.unpaid_leave_deduction` field (populated by PayrollProcessing module via `LeaveApproved` event)
   - **In:** `PayrollRegisterService`, `PayrollAnalyticsService`
   - **Purpose:** Show leave deductions breakdown

2. **Add leave columns to Payroll Register**
   - Unpaid leave days
   - Unpaid leave deduction amount
   - Leave type breakdown (optional)

3. **Create Leave Impact Analytics**
   - **In:** `PayrollAnalyticsService`
   - Metrics: Total unpaid leave cost impact, departments with highest leave deductions, leave trends over time

### Task 6.3: Test Integration
**Subtasks:**
1. **Create test scenario**
   - Create payroll period with finalized timekeeping data
   - Create payroll period with leave deductions
   - Generate Payroll Register
   - Verify attendance and leave data appear correctly

2. **Document integration points**
   - **File:** `docs/issues/PAYROLL-REPORTS-INTEGRATION-NOTES.md`
   - List all tables queried from other modules
   - Note any assumptions about data availability

---

## **PHASE 7: Testing & Quality Assurance**
**Duration:** 3 days  
**Dependencies:** All phases complete

### Task 7.1: Unit Tests
**Subtasks:**
1. **Test Report Services**
   - **Files:**
     - `tests/Unit/Services/Payroll/Reports/PayrollRegisterServiceTest.php`
     - `tests/Unit/Services/Payroll/Reports/PayrollAnalyticsServiceTest.php`
     - `tests/Unit/Services/Payroll/Reports/PayrollAuditServiceTest.php`
     - `tests/Unit/Services/Payroll/Reports/GovernmentReportsServiceTest.php`
   
   - **Test cases:**
     - `testGenerateRegisterReturnsCorrectData()`
     - `testRegisterFiltersWorkCorrectly()`
     - `testRegisterCalculatesTotalsCorrectly()`
     - `testAnalyticsCachingWorks()`
     - `testGovernmentReportValidation()`
     - `testAuditTrailFilteringWorks()`

2. **Test Export Functionality**
   - **Files:** `tests/Unit/Exports/PayrollRegisterExportTest.php`, etc.
   - **Test cases:**
     - `testExportToPdfGeneratesFile()`
     - `testExportToExcelHasCorrectSheets()`
     - `testExportToCsvHasCorrectColumns()`

3. **Test Models**
   - **Files:** `tests/Unit/Models/GeneratedReportTest.php`, etc.
   - **Test cases:**
     - `testModelRelationships()`
     - `testModelScopes()`
     - `testModelMethods()`

### Task 7.2: Feature Tests
**Subtasks:**
1. **Test Report Generation Endpoints**
   - **File:** `tests/Feature/Payroll/Reports/ReportGenerationTest.php`
   - **Test cases:**
     - `testPayrollOfficerCanAccessRegister()`
     - `testPayrollOfficerCanExportRegister()`
     - `testOnlyAuthorizedUsersCanAccessAuditTrail()`
     - `testGovernmentReportGenerationRequiresPeriod()`

2. **Test Government Report Workflow**
   - **File:** `tests/Feature/Payroll/Reports/GovernmentReportsWorkflowTest.php`
   - **Test cases:**
     - `testSSSR3GenerationCreatesFile()`
     - `testGovernmentReportSubmissionTracking()`
     - `testOverdueReportsAreFlagged()`

3. **Test Integration with Other Modules**
   - **File:** `tests/Feature/Payroll/Reports/ReportIntegrationTest.php`
   - **Test cases:**
     - `testRegisterIncludesTimekeepingData()`
     - `testRegisterIncludesLeaveDeductions()`
     - `testAnalyticsUsesCachedData()`

### Task 7.3: Manual Testing
**Subtasks:**
1. **Test all report pages in browser**
   - Payroll Register: Apply filters, verify data, test exports
   - Analytics: Check charts, verify calculations, test date ranges
   - Audit Trail: Filter logs, verify change history, test exports
   - Government Reports: Generate all report types, download, mark as submitted

2. **Test government report file formats**
   - SSS R3: Verify fixed-width format matches SSS spec
   - PhilHealth RF1: Verify Excel/CSV columns
   - Pag-IBIG MCRF: Verify format
   - BIR reports: Verify BIR compliance

3. **Test performance with large datasets**
   - Generate register for 500+ employees
   - Generate analytics for 24 months
   - Verify caching improves load times

### Task 7.4: User Acceptance Testing (UAT)
**Subtasks:**
1. **Create test data**
   - Complete payroll period with real calculations
   - Employees from multiple departments
   - Various salary levels, allowances, deductions

2. **Payroll Officer UAT**
   - Generate Payroll Register for current period
   - Verify all calculations match expected values
   - Export to Excel and validate formulas
   - Generate government reports
   - Verify compliance with government formats

3. **HR Manager UAT**
   - Review Analytics reports
   - Verify department comparisons
   - Check YoY trends

4. **Office Admin UAT**
   - Review Audit Trail
   - Verify all actions are logged
   - Export audit report for compliance

---

## **PHASE 8: Documentation & Deployment**
**Duration:** 2 days  
**Dependencies:** Phase 7 complete

### Task 8.1: Technical Documentation
**Subtasks:**
1. **Create Report Generation Guide**
   - **File:** `docs/guides/PAYROLL-REPORTS-GENERATION-GUIDE.md`
   - How to generate each report type
   - Filter options explained
   - Export formats comparison
   - Government report deadlines and submission process

2. **Create Developer Documentation**
   - **File:** `docs/technical/PAYROLL-REPORTS-TECHNICAL.md`
   - Service architecture
   - Caching strategy
   - Adding new report types
   - Extending export formats

3. **Update API Documentation**
   - Document all new endpoints
   - Request/response examples
   - Error codes

### Task 8.2: User Training Materials
**Subtasks:**
1. **Create Payroll Officer guide**
   - **File:** `docs/user-guides/PAYROLL-OFFICER-REPORTS-GUIDE.md`
   - Step-by-step: Generate Payroll Register
   - Step-by-step: Generate Government Reports
   - Step-by-step: Submit reports to agencies
   - Troubleshooting common issues

2. **Create screenshots/videos** (optional)
   - Screen recordings of generating reports
   - Government report submission workflow

### Task 8.3: Deployment Checklist
**Subtasks:**
1. **Pre-deployment checklist**
   - [ ] All migrations run successfully
   - [ ] All tests passing (unit + feature)
   - [ ] Export libraries installed
   - [ ] Storage directory writable (`storage/app/reports/`)
   - [ ] Cron jobs configured (for cache expiration cleanup)
   - [ ] Government report templates validated
   - [ ] Performance tested with production-size data

2. **Deployment steps**
   ```bash
   # 1. Pull latest code
   git pull origin main
   
   # 2. Install dependencies
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   
   # 3. Run migrations
   php artisan migrate --force
   
   # 4. Clear caches
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   
   # 5. Restart queue workers (if using queues for report generation)
   php artisan queue:restart
   
   # 6. Set permissions
   chmod -R 775 storage/app/reports
   ```

3. **Post-deployment verification**
   - [ ] Visit all report pages and verify data loads
   - [ ] Generate sample Payroll Register and export
   - [ ] Generate sample government report
   - [ ] Verify audit trail is logging actions
   - [ ] Check analytics cache is working

---

## 📊 Summary of Files to Create/Modify

### Files to CREATE:
```
Migrations (4):
database/migrations/2026_02_06_000001_create_report_templates_table.php
database/migrations/2026_02_06_000002_create_generated_reports_table.php
database/migrations/2026_02_06_000003_create_government_report_submissions_table.php
database/migrations/2026_02_06_000004_create_report_analytics_cache_table.php

Models (4):
app/Models/ReportTemplate.php
app/Models/GeneratedReport.php
app/Models/GovernmentReportSubmission.php
app/Models/ReportAnalyticsCache.php

Services (5):
app/Services/Payroll/Reports/BaseReportService.php
app/Services/Payroll/Reports/PayrollRegisterService.php
app/Services/Payroll/Reports/PayrollAnalyticsService.php
app/Services/Payroll/Reports/PayrollAuditService.php
app/Services/Payroll/Reports/GovernmentReportsService.php

Exports (3):
app/Exports/PayrollRegisterExport.php
app/Exports/PayrollAnalyticsExport.php
app/Exports/AuditTrailExport.php

PDF Templates (2):
resources/views/pdf/payroll-register.blade.php
resources/views/pdf/audit-trail.blade.php

Unit Tests (8):
tests/Unit/Services/Payroll/Reports/PayrollRegisterServiceTest.php
tests/Unit/Services/Payroll/Reports/PayrollAnalyticsServiceTest.php
tests/Unit/Services/Payroll/Reports/PayrollAuditServiceTest.php
tests/Unit/Services/Payroll/Reports/GovernmentReportsServiceTest.php
tests/Unit/Exports/PayrollRegisterExportTest.php
tests/Unit/Models/GeneratedReportTest.php

Feature Tests (3):
tests/Feature/Payroll/Reports/ReportGenerationTest.php
tests/Feature/Payroll/Reports/GovernmentReportsWorkflowTest.php
tests/Feature/Payroll/Reports/ReportIntegrationTest.php

Documentation (4):
docs/guides/PAYROLL-REPORTS-GENERATION-GUIDE.md
docs/technical/PAYROLL-REPORTS-TECHNICAL.md
docs/user-guides/PAYROLL-OFFICER-REPORTS-GUIDE.md
docs/issues/PAYROLL-REPORTS-INTEGRATION-NOTES.md
```

### Files to MODIFY:
```
Controllers (4):
app/Http/Controllers/Payroll/Reports/PayrollRegisterController.php
app/Http/Controllers/Payroll/Reports/PayrollAnalyticsController.php
app/Http/Controllers/Payroll/Reports/PayrollAuditController.php
app/Http/Controllers/Payroll/Reports/PayrollGovernmentReportsController.php

Routes (1):
routes/payroll.php

Frontend (4):
resources/js/pages/Payroll/Reports/Register/Index.tsx
resources/js/pages/Payroll/Reports/Analytics.tsx
resources/js/pages/Payroll/Reports/Audit.tsx
resources/js/pages/Payroll/Reports/Government/Index.tsx

TypeScript Types (1):
resources/js/types/payroll-pages.d.ts
```

---

## 🔍 Clarifications & Questions

### Critical Questions (Must Answer Before Implementation)

#### 1. Report Storage & Retention
- **Q:** How long should generated reports be stored before auto-deletion?
  - Payroll Register: 7 years (DOLE requirement)?
  - Government reports: 10 years (BIR requirement)?
  - Analytics/Audit: 5 years?
- **Q:** Should reports be stored in database (`storage/app/reports/`) or cloud (S3/Google Cloud)?
- **Q:** Maximum report file size limit before switching to queue processing?

#### 2. Government Report Formats
- **Q:** Do you have the exact SSS R3 file format specification (fixed-width positions)?
- **Q:** Do you have BIR Alphalist DAT format specification (field lengths, schedules)?
- **Q:** Are government report templates provided by agencies or do we design them?
- **Q:** Should we validate reports against agency schemas before allowing download?

#### 3. Audit Trail Scope
- **Q:** Should audit trail track ALL payroll changes (including calculations) or only approval/manual adjustments?
- **Q:** Should audit trail include IP address, browser user-agent, session ID?
- **Q:** Should deleted records be hard-deleted or soft-deleted with audit trail?
- **Q:** Should we implement digital signatures or hashing for tamper-proof audit logs?

#### 4. Analytics Caching
- **Q:** What is acceptable staleness for analytics data? (1 hour, 24 hours, 1 week?)
- **Q:** Should cache be auto-refreshed on a schedule or only on-demand?
- **Q:** Should cache be per-user or global?
- **Q:** Should we cache at service layer or use Laravel's built-in cache?

#### 5. Export Performance
- **Q:** Expected maximum number of employees per payroll period? (For sizing exports)
- **Q:** Should large exports (>1000 employees) be queued as background jobs?
- **Q:** Should exports have download expiration (e.g., expire after 24 hours)?
- **Q:** Should we send email notifications when large exports are ready?

#### 6. Government Report Submission Tracking
- **Q:** Should system send reminders for upcoming government report deadlines?
- **Q:** Should system block payroll finalization if previous government reports are overdue?
- **Q:** Who should receive notifications for overdue reports? (Payroll Officer, HR Manager, Office Admin?)
- **Q:** Should we integrate with government portals via API (e.g., BIR eFPS API) or manual upload only?

#### 7. Payroll Register Configuration
- **Q:** Should Payroll Register show gross/deductions/net in separate columns or collapsed?
- **Q:** Should it include year-to-date (YTD) totals per employee?
- **Q:** Should it show payment method (cash, bank, e-wallet) per employee?
- **Q:** Should it group by department by default or show all employees in one list?

#### 8. Analytics Features
- **Q:** Should analytics include budget vs. actual variance tracking? (Requires budget data)
- **Q:** Should analytics forecast future labor costs using historical trends?
- **Q:** Should we show headcount analytics (new hires, resignations, turnover rate)?
- **Q:** Should analytics drill down to employee level or stop at department level?

#### 9. Access Control
- **Q:** Can Payroll Officer export reports for all departments or only their assigned departments?
- **Q:** Can HR Manager access Audit Trail or only Office Admin/Superadmin?
- **Q:** Can employees view their own payslip from Reports module or only from EmployeePayroll module?
- **Q:** Should report exports be logged in audit trail?

#### 10. Integration Timing
- **Q:** When should reports pull timekeeping data? (Only from finalized attendance summaries or live data?)
- **Q:** When should reports pull leave data? (Only after leave deductions are calculated in PayrollProcessing?)
- **Q:** Should reports show "draft" calculations before payroll is approved?
- **Q:** Should government reports only be generated after payroll is locked/finalized?

---

## 💡 Recommendations

### 1. Report Storage Strategy
**Recommendation:** Use **local storage (`storage/app/reports/`)** for development, **cloud storage (S3/Google Cloud)** for production.
- **Reason:** Compliance requires long-term retention (7-10 years), local storage may fill up
- **Implementation:** Use Laravel Filesystem abstraction, switch driver via `.env`

### 2. Government Report Validation
**Recommendation:** Implement **strict validation** before allowing download.
- Validate SSS numbers (11 digits, format: XX-XXXXXXX-X)
- Validate PhilHealth numbers (12 digits)
- Validate Pag-IBIG numbers (12 digits)
- Validate TIN (9 digits + 3-digit branch code)
- Validate file formats (fixed-width positions, totals match)
- **Reason:** Prevent rejection by government agencies due to format errors

### 3. Audit Trail Implementation
**Recommendation:** Use **Laravel Activity Log** package (`spatie/laravel-activitylog`) instead of custom solution.
- **Reason:** Battle-tested, supports before/after values, integrates with models
- **Alternative:** Custom implementation if you need specialized features (digital signatures, blockchain hashing)

### 4. Analytics Caching
**Recommendation:** Cache analytics data for **24 hours**, refresh on-demand via "Refresh" button.
- Cache key: `payroll_analytics_{report_type}_{start_date}_{end_date}_{filters_hash}`
- Store in `report_analytics_cache` table (not Redis) for persistence across deploys
- **Reason:** Analytics queries are expensive (aggregate across months), but data doesn't change retroactively

### 5. Export Queue Processing
**Recommendation:** Queue exports with **>500 employees** as background jobs.
- Use Laravel Queues (`php artisan queue:work`)
- Send email notification when export is ready
- Store generated files for 7 days, then auto-delete
- **Reason:** Large exports can timeout HTTP requests, queuing improves UX

### 6. Government Report Deadlines
**Recommendation:** Implement **automated deadline reminders**.
- Send email 7 days before deadline
- Send email 3 days before deadline
- Send email on deadline day
- Flag overdue reports in red on dashboard
- **Reason:** Late submission penalties are costly (₱1,000-₱25,000 per late filing)

### 7. Payroll Register Layout
**Recommendation:** Show **expandable/collapsible sections** for deductions.
- Summary row: Gross, Total Deductions, Net Pay
- Click to expand: SSS, PhilHealth, Pag-IBIG, Tax, Loans, Advances breakdown
- **Reason:** Too many columns make register hard to read, especially on smaller screens

### 8. Analytics Forecasting
**Recommendation:** Use **simple linear regression** for cost forecasting, don't over-engineer.
- Formula: `y = mx + b` (linear trend line)
- Show forecast with ±10% confidence interval
- Include disclaimer: "Based on historical trends, actual may vary"
- **Reason:** Complex ML models require significant data science expertise, simple regression is sufficient

### 9. Access Control
**Recommendation:** Follow **principle of least privilege**:
- Payroll Officer: Can generate/export Register, Government Reports (their scope)
- HR Manager: Can view Analytics (all departments), cannot access Audit Trail
- Office Admin: Can view Audit Trail, all reports (full scope)
- Superadmin: Full access
- **Reason:** Sensitive data (salaries, government IDs) should be restricted

### 10. Integration Safety
**Recommendation:** Always query **finalized data** only (not draft calculations).
- Payroll Register: `payroll_periods.status = 'completed'`
- Timekeeping: `daily_attendance_summaries.is_finalized = true`
- Leave: Only approved leave (`leave_requests.status = 'approved'`)
- **Reason:** Reports are official documents, must reflect locked/approved data only

### 11. Government Report File Naming
**Recommendation:** Use **government-prescribed naming conventions**:
- SSS R3: `R3_[CompanyID]_[YYYYMM].txt`
- PhilHealth RF1: `RF1_[EmployerNo]_[YYYYMM].xlsx`
- Pag-IBIG MCRF: `MCRF_[EmployerID]_[YYYYMM].xlsx`
- BIR 1601C: `1601C_[TIN]_[YYYYMM].xlsx`
- **Reason:** Some agencies reject files with incorrect naming

### 12. Export File Formats
**Recommendation:** Prioritize **Excel** over PDF for data manipulation.
- Excel: Allows users to filter, sort, create pivot tables
- PDF: Good for printing, official distribution
- CSV: Good for data import to other systems
- **Reason:** Payroll Officers often need to analyze data in Excel

### 13. Report Versioning
**Recommendation:** Track **report versions** if re-generated after corrections.
- `generated_reports.version` field (1, 2, 3...)
- Store reason for re-generation
- Keep old versions for audit trail
- **Reason:** Government may audit original vs. corrected reports

### 14. Performance Optimization
**Recommendation:** Implement **database indexes** on all foreign keys and date fields.
- Index: `payroll_periods.status`, `payroll_periods.start_date`, `payroll_periods.end_date`
- Index: `employee_payroll_calculations.payroll_period_id`, `employee_payroll_calculations.employee_id`
- Index: `daily_attendance_summaries.employee_id`, `daily_attendance_summaries.attendance_date`
- **Reason:** Reports query large datasets, indexes drastically improve performance

### 15. Testing Priority
**Recommendation:** Focus testing on **government report validation** (highest risk).
- Unit test: SSS R3 format validation (fixed-width positions)
- Unit test: BIR Alphalist DAT format validation
- Unit test: PhilHealth RF1 calculations (5% premium, max ₱5,000)
- Feature test: End-to-end government report generation workflow
- **Reason:** Government report errors cause compliance issues and penalties

---

## 🎯 Suggestions for Future Enhancements (Post-MVP)

### 1. Automated Government Portal Integration
- Integrate with BIR eFPS API for direct submission
- Integrate with SSS eR3 API
- Integrate with PhilHealth EPRS API
- Integrate with Pag-IBIG eSRS API
- **Benefit:** Eliminate manual upload, reduce errors

### 2. Report Scheduling & Distribution
- Allow Payroll Officer to schedule recurring reports
- Auto-generate and email Payroll Register on pay date
- Auto-generate government reports 1 week before deadline
- **Benefit:** Reduce manual tasks, ensure timely compliance

### 3. Interactive Dashboards
- Use Chart.js or Recharts for interactive analytics charts
- Allow drill-down from department to employee level
- Real-time cost tracking dashboard
- **Benefit:** Better data visualization, faster insights

### 4. Custom Report Builder
- Allow users to create custom reports with drag-and-drop
- Select columns, filters, grouping, sorting
- Save as report template
- **Benefit:** Flexibility for ad-hoc reporting needs

### 5. Payroll Comparison Tool
- Compare two payroll periods side-by-side
- Highlight differences (new hires, resignations, salary changes)
- Variance analysis (% change per employee)
- **Benefit:** Quickly identify anomalies

### 6. Mobile-Friendly Reports
- Responsive design for viewing reports on mobile
- Simplified mobile-first analytics dashboard
- **Benefit:** HR Managers can review on-the-go

### 7. AI-Powered Insights
- Use GPT API to generate natural language insights
- Example: "Department X has 15% higher labor costs this month due to overtime surge"
- **Benefit:** Non-technical users can understand analytics

### 8. Multi-Company Support
- If system expands to support multiple companies
- Separate reports per company
- Consolidated group reports
- **Benefit:** Scalability for corporate groups

---

## ✅ Acceptance Criteria

### Payroll Register
- [ ] Payroll Officer can view register for any completed payroll period
- [ ] Register shows: Employee name, ID, department, gross pay, all deductions, net pay
- [ ] Register can be filtered by: department, employee status, salary component
- [ ] Register shows accurate totals (department subtotals, grand totals)
- [ ] Register can be exported to PDF, Excel, CSV
- [ ] Exported files are formatted professionally (company header, period details)

### Analytics Reports
- [ ] Analytics page shows: Monthly cost trends (12 months), department comparisons, component breakdown, YoY analysis
- [ ] Charts are clear and accurate
- [ ] Analytics data is cached for performance (24-hour TTL)
- [ ] "Refresh" button clears cache and recomputes
- [ ] Analytics can be exported to PDF, Excel

### Audit Trail
- [ ] Audit Trail logs all payroll changes (calculations, adjustments, approvals)
- [ ] Logs show: timestamp, user, action, entity type, entity ID, before/after values
- [ ] Audit Trail can be filtered by: action type, entity type, user, date range
- [ ] Audit Trail can be searched by keyword
- [ ] Audit Trail can be exported to PDF, Excel

### Government Reports
- [ ] Payroll Officer can generate: SSS R3, PhilHealth RF1, Pag-IBIG MCRF, BIR 1601C, BIR 2316, BIR Alphalist
- [ ] Generated reports are validated against government format specifications
- [ ] Generated reports can be downloaded (TXT, Excel, DAT formats)
- [ ] System tracks submission status (draft, ready, submitted, accepted, rejected)
- [ ] System flags overdue reports
- [ ] System shows upcoming deadlines (next 30 days)

### Integration
- [ ] Payroll Register pulls attendance data from `daily_attendance_summaries`
- [ ] Payroll Register pulls leave deductions from `employee_payroll_calculations.unpaid_leave_deduction`
- [ ] Government Reports pull contribution data from `employee_government_contributions`
- [ ] All reports query ONLY finalized/completed data

### Performance
- [ ] Payroll Register for 500 employees loads in <5 seconds
- [ ] Analytics dashboard loads in <3 seconds (with caching)
- [ ] Exports for 500 employees complete in <30 seconds (or queued if larger)

### Security
- [ ] Only Payroll Officers can access Reports module
- [ ] HR Managers can view Analytics only (no access to Register/Audit)
- [ ] Office Admins can access all reports
- [ ] Sensitive data (salaries, government IDs) is not exposed to unauthorized roles

---

## 📝 Notes

- **Government report formats change periodically:** Monitor SSS, PhilHealth, Pag-IBIG, BIR websites for format updates. Last checked: February 2026.
- **Payroll Register is a DOLE requirement:** Must be retained for 7 years for labor inspections.
- **BIR Alphalist format is STRICT:** Even one character misalignment will cause file rejection. Test thoroughly.
- **Analytics caching is critical:** Without caching, analytics page may timeout with large datasets.
- **Export libraries have memory limits:** For >1000 employees, use chunking or queue processing.
- **Government report deadlines are NON-NEGOTIABLE:** Late filing incurs penalties. Implement reminders ASAP.

---

## 🚀 Ready to Implement?

Once you've answered the clarifications, we can proceed with Phase 1 (Database Foundation). Let me know which clarifications need discussion before we start coding!
