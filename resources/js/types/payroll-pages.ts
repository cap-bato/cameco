/**
 * Payroll Module Page Props and Interfaces
 * 
 * This file contains TypeScript interfaces for all Payroll module pages,
 * ensuring type-safe props when rendering Inertia pages from Laravel controllers.
 */

// ============================================================================
// DASHBOARD PAGE TYPES
// ============================================================================

/**
 * Main Dashboard Page Props (Single Page with All Widget Data)
 * Passed from DashboardController@index() via Inertia
 */
export interface PayrollDashboardProps {
    summary: DashboardSummary;            // Widget 1: Summary Cards
    pendingPeriods: PendingPeriod[];      // Widget 2: Pending Periods Table
    recentActivities: RecentActivity[];   // Widget 3: Recent Activities Feed
    criticalAlerts: CriticalAlert[];      // Widget 4: Critical Alerts Panel
    complianceStatus: GovernmentComplianceData; // Widget 5: Government Compliance Tracker
    quickActions: QuickAction[];          // Widget 6: Quick Actions Panel
}

/**
 * Widget 1: Dashboard Summary (4 metric cards)
 */
export interface DashboardSummary {
    current_period: CurrentPeriod;
    total_employees: EmployeeMetric;
    net_payroll: PayrollMetric;
    pending_actions: PendingActionsMetric;
}

export interface CurrentPeriod {
    id: number;
    name: string;                          // "November 2025 - 2nd Half"
    period_type: 'semi_monthly' | 'monthly';
    start_date: string;                    // ISO date
    end_date: string;
    cutoff_date: string;
    pay_date: string;
    status: 'draft' | 'calculating' | 'calculated' | 'reviewing' | 'approved';
    status_label: string;                  // "Calculating", "Reviewing", etc.
    status_color: 'blue' | 'yellow' | 'green';
    total_employees: number;
    progress_percentage: number;           // 0-100
    days_until_pay: number;
}

export interface EmployeeMetric {
    active: number;                        // 125
    new_hires_this_period: number;         // +3
    separations_this_period: number;       // -1
    on_leave: number;                      // 5
    change_from_previous: string;          // "+2"
    change_percentage: string;             // "+1.6%"
}

export interface PayrollMetric {
    current_period: number;                // 4250000.00
    previous_period: number;               // 4040000.00
    difference: number;                    // 210000.00
    percentage_change: string;             // "+5.2%"
    trend: 'up' | 'down' | 'stable';
    formatted_current: string;             // "₱4,250,000.00"
    formatted_previous: string;            // "₱4,040,000.00"
}

export interface PendingActionsMetric {
    total: number;                         // 8
    periods_to_calculate: number;          // 1
    periods_to_review: number;             // 1
    periods_to_approve: number;            // 1
    adjustments_pending: number;           // 3
    government_reports_due: number;        // 2
}

/**
 * Widget 2: Pending Payroll Period (table row)
 * Phase 3.3: Full field specification for payroll period tracking
 */
export interface PendingPeriod {
    id: number;
    name: string;                          // "November 2025 - 2nd Half"
    period_type: 'semi_monthly' | 'monthly';
    period_type_label: string;             // "Semi-monthly"
    start_date: string;                    // ISO date
    end_date: string;                      // ISO date
    pay_date: string;                      // ISO date
    date_range: string;                    // "Nov 16-30"
    status: 'draft' | 'calculating' | 'calculated' | 'reviewing' | 'approved';
    status_label: string;                  // "Draft", "Calculating", "Calculated", etc.
    status_color: 'blue' | 'yellow' | 'green';
    employee_count: number;                // Employees in this period
    net_pay: number;                       // Total net pay amount
    formatted_net_pay: string;             // "₱4,250,000.00"
    total_gross_pay: number;               // For reference
    total_deductions: number;              // For reference
    progress_percentage: number;           // 0-100 for progress bar
    available_actions: string[];           // ['calculate', 'review', 'approve', 'adjust', etc.]
}

/**
 * Widget 3: Recent Activity (timeline item)
 * Phase 3.4: Full field specification for activity tracking with enhanced user info
 */
export interface RecentActivity {
    id: number;
    type: 'period_calculated' | 'adjustment_created' | 'government_report_generated' | 'payroll_approved' | 'bank_file_generated' | 'payslips_generated' | 'remittance_paid' | 'attendance_imported' | 'component_updated' | 'period_created';
    icon: string;                          // 'calculator', 'edit', 'file-text', 'check-circle', etc.
    icon_color: 'blue' | 'yellow' | 'green' | 'purple' | 'gray';
    title: string;                         // "Payroll Calculated"
    description: string;                   // "November 2025 - 2nd Half calculated for 125 employees"
    user_name: string;                     // "Maria Santos"
    user_role: string;                     // "Payroll Officer"
    timestamp: string;                     // ISO datetime
    relative_time: string;                 // "2 hours ago"
    metadata?: {
        period_name?: string;              // "November 2025 - 2nd Half"
        employee_count?: number;           // 125
        amount?: number;                   // 4250000.00
        formatted_amount?: string;         // "₱4,250,000.00"
        period_id?: number;
        affected_count?: number;
        report_type?: string;              // "R3", "RF1", etc.
        file_name?: string;
        reference_number?: string;
    };
}

/**
 * Widget 4: Critical Alert (alert item)
 * Phase 3.5: Full field specification for system and compliance alerts
 */
export interface CriticalAlert {
    id: number;
    type: 'overdue_remittance' | 'calculation_error' | 'upcoming_deadline' | 'variance_alert' | 'bank_file_pending' | 'compliance_issue' | 'data_validation_error';
    severity: 'critical' | 'warning' | 'info';
    title: string;                         // "SSS Remittance Overdue"
    message: string;                       // Full alert description
    icon: string;                          // 'alert-circle', 'x-circle', 'clock', 'trending-up', etc.
    action: string;                        // "Pay Now", "Review Errors", "View Details"
    action_url?: string;                   // "/payroll/government/sss/pay"
    can_dismiss: boolean;                  // Whether user can dismiss the alert
    created_at: string;                    // ISO datetime
    metadata?: {
        amount?: number;                   // 145230.00
        formatted_amount?: string;         // "₱145,230.00"
        deadline?: string;                 // ISO date
        days_overdue?: number;             // How many days overdue
        days_until_due?: number;           // How many days until due
        period?: string;                   // "October 2025"
        affected_employees?: number;       // Number of employees affected
        agency?: string;                   // "SSS", "PhilHealth", etc.
        error_count?: number;              // For calculation errors
        variance_percentage?: string;      // "2.5%"
        variance_amount?: string;          // "₱50,000.00"
        pay_date?: string;                 // ISO date
        days_until_pay_date?: number;
        report_type?: string;              // "R3", "RF1", etc.
        file_name?: string;
        reference_number?: string;
    };
}

/**
 * Widget 5: Government Compliance Data (4 agency cards)
 * Phase 3.6: Full field specification for government agency remittance tracking
 */
export interface GovernmentComplianceData {
    sss: GovernmentAgencyStatus;
    philhealth: GovernmentAgencyStatus;
    pagibig: GovernmentAgencyStatus;
    bir: GovernmentAgencyStatus;
}

export interface GovernmentAgencyStatus {
    name: 'SSS' | 'PhilHealth' | 'Pag-IBIG' | 'BIR';
    full_name: string;                     // "Social Security System"
    period: string;                        // "October 2025"
    due_date: string;                      // ISO date
    status: 'paid' | 'overdue' | 'pending' | 'calculating';
    status_label: string;                  // "PAID", "OVERDUE", "DUE IN 3 DAYS", "CALCULATING"
    status_color: 'green' | 'red' | 'yellow' | 'blue';
    remittance_amount: number;             // 145230.00
    formatted_amount: string;              // "₱145,230.00"
    compliance_deadline: string;           // ISO date for compliance deadline
    days_overdue?: number;                 // Negative if pending, positive if overdue
    days_until_due?: number;               // Days remaining until due
    employee_share?: number;               // Employee contribution
    employer_share?: number;               // Employer contribution
    report_type: string;                   // "R3", "RF1", "MCRF", "1601C"
    report_status: 'not_generated' | 'generated' | 'filed';
    report_generated_date?: string;        // ISO date when report was generated
    payment_reference?: string | null;     // "PH-2025-11-001234" or null if not paid
    paid_date?: string;                    // ISO date when payment was made
    payment_method?: string;               // "Bank Transfer", "Check", "ACH", etc.
    actions: string[];                     // ['pay_now', 'view_report', 'view_receipt', 'prepare_payment']
    // Additional tracking fields
    last_remittance_date?: string;         // Previous remittance date
    consecutive_on_time: number;           // Number of consecutive on-time payments
    compliance_score: number;              // 0-100 compliance percentage
}

/**
 * Widget 6: Quick Action (action button)
 * Phase 3.7: Full field specification for quick action buttons
 * Actions are categorized for semantic grouping and color coding
 */
export interface QuickAction {
    id: string | number;
    label: string;                         // "Create Payroll Period", "Upload Timesheet", "Generate Report"
    description: string;                   // "Start a new payroll period"
    icon: string;                          // 'plus-circle', 'upload', 'file-check', 'download', 'settings'
    category: 'processing' | 'compliance' | 'reporting' | 'admin';
    url: string;                           // "/payroll/periods/create", "/payroll/upload", "/payroll/reports"
    disabled?: boolean;                    // Optional: disable action if conditions not met
    badge?: string;                        // Optional: badge label like "New", "Beta", "Required"
    keyboard_shortcut?: string;            // Optional: for accessibility e.g., "Ctrl+K"
}

// ============================================================================
// PAYROLL PROCESSING PAGES TYPES (Phase 1-4)
// ============================================================================

/**
 * Phase 1.1 & 1.2: Payroll Periods Management
 * Complete payroll period entity for creation, editing, and display
 */
export interface PayrollPeriod {
    id: number;
    name: string;                          // "November 2025 - 1st Half", "Monthly - November 2025"
    period_type: 'weekly' | 'bi_weekly' | 'semi_monthly' | 'monthly';
    start_date: string;                    // ISO date
    end_date: string;                      // ISO date
    cutoff_date: string;                   // ISO date - DTR cutoff
    pay_date: string;                      // ISO date - when employees get paid
    status: 'draft' | 'importing' | 'calculating' | 'calculated' | 'reviewing' | 'approved' | 'bank_file_generated' | 'paid' | 'closed';
    total_employees: number;
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
    total_employer_cost: number;
    processed_at?: string;
    approved_by?: string | null;           // User name
    approved_at?: string;
    finalized_by?: string | null;
    finalized_at?: string;
    created_at?: string;
    updated_at?: string;
}

/**
 * Create/Edit Payroll Period Form Data
 */
export interface PayrollPeriodFormData {
    name: string;
    period_type: 'weekly' | 'bi_weekly' | 'semi_monthly' | 'monthly';
    start_date: string;                    // ISO date string
    end_date: string;                      // ISO date string
    cutoff_date: string;                   // ISO date string
    pay_date: string;                      // ISO date string
}

/**
 * Payroll Periods Listing Page Props
 */
export interface PayrollPeriodsPageProps {
    periods: PayrollPeriod[];
    filters: {
        status?: string;
        period_type?: string;
        search?: string;
        year?: number;
    };
}

// ============================================================================
// Phase 1.3: Payroll Calculations
// ============================================================================

/**
 * Payroll Calculation Entity
 * Represents a payroll calculation run for a specific period
 */
export interface PayrollCalculation {
    id: number;
    payroll_period_id: number;
    payroll_period: PayrollPeriod;
    calculation_type: 'regular' | 'adjustment' | 'final' | 're-calculation';
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';
    total_employees: number;
    processed_employees: number;
    failed_employees: number;
    progress_percentage: number;           // 0-100
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
    calculation_date: string;
    started_at?: string;
    completed_at?: string;
    error_message?: string;
    calculated_by?: string;                // User name
    created_at: string;
    updated_at: string;
}

/**
 * Employee Calculation Detail
 * Individual employee's payroll calculation breakdown
 */
export interface EmployeeCalculation {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    position: string;
    calculation_id: number;
    status: 'pending' | 'calculated' | 'failed' | 'adjusted';
    basic_pay: number;
    earnings: Record<string, number>;      // { "overtime": 5000, "holiday_pay": 2000 }
    deductions: Record<string, number>;    // { "sss": 1500, "philhealth": 800 }
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    error_message?: string;
    calculated_at?: string;
}

/**
 * Payroll Calculations Page Props
 */
export interface PayrollCalculationsPageProps {
    calculations: PayrollCalculation[];
    available_periods: PayrollPeriod[];
    filters: {
        period_id?: number;
        status?: string;
        calculation_type?: string;
    };
}

// ============================================================================
// Phase 1.4: Payroll Adjustments
// ============================================================================

/**
 * Payroll Adjustment Entity
 * Manual adjustments to employee payroll
 */
export interface PayrollAdjustment {
    id: number;
    payroll_period_id: number;
    payroll_period: PayrollPeriod;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    adjustment_type: 'earning' | 'deduction' | 'correction' | 'backpay' | 'refund';
    adjustment_category: string;           // e.g., "Overtime Correction", "Tax Refund"
    amount: number;
    reason: string;
    reference_number?: string;
    status: 'pending' | 'approved' | 'rejected' | 'applied' | 'cancelled';
    requested_by: string;                  // User name
    requested_at: string;
    reviewed_by?: string;
    reviewed_at?: string;
    review_notes?: string;
    applied_at?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Adjustment Form Data for Create/Edit
 */
export interface PayrollAdjustmentFormData {
    payroll_period_id: number;
    employee_id: number;
    adjustment_type: 'earning' | 'deduction' | 'correction' | 'backpay' | 'refund';
    adjustment_category: string;
    amount: number;
    reason: string;
    reference_number?: string;
}

/**
 * Payroll Adjustments Page Props
 */
export interface PayrollAdjustmentsPageProps {
    adjustments: PayrollAdjustment[];
    available_periods: PayrollPeriod[];
    available_employees: Array<{
        id: number;
        name: string;
        employee_number: string;
        department: string;
    }>;
    filters: {
        period_id?: number;
        employee_id?: number;
        status?: string;
        adjustment_type?: string;
    };
}

// ============================================================================
// Phase 2: EMPLOYEE PAYROLL MANAGEMENT
// ============================================================================

/**
 * Employee Payroll Info Entity
 * Stores salary information, tax details, government numbers, and bank info
 */
export interface EmployeePayrollInfo {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    position: string;
    
    // Salary Information
    salary_type: 'monthly' | 'daily' | 'hourly' | 'contractual' | 'project_based';
    salary_type_label: string;
    basic_salary: number;
    daily_rate?: number;
    hourly_rate?: number;
    payment_method: 'bank_transfer' | 'cash' | 'check';
    payment_method_label: string;
    
    // Tax Information
    tax_status: string;                    // Z, S, ME, S1, ME1, etc.
    tax_status_label: string;              // "Single", "Married Employee", etc.
    rdo_code?: string;                     // BIR Revenue District Office code
    withholding_tax_exemption: number;     // Exemption amount
    is_tax_exempt: boolean;
    is_substituted_filing: boolean;
    
    // Government Numbers
    sss_number?: string;
    philhealth_number?: string;
    pagibig_number?: string;
    tin_number?: string;
    
    // Government Contribution Settings
    sss_bracket?: string;
    is_sss_voluntary: boolean;
    philhealth_is_indigent: boolean;
    pagibig_employee_rate: number;         // 1 or 2 percent
    
    // Bank Information
    bank_name?: string;
    bank_code?: string;
    bank_account_number?: string;
    bank_account_name?: string;
    
    // De Minimis Benefits Entitlements
    is_entitled_to_rice: boolean;
    is_entitled_to_uniform: boolean;
    is_entitled_to_laundry: boolean;
    is_entitled_to_medical: boolean;
    
    // Status
    is_active: boolean;
    status_label: string;                  // "Active", "Inactive"
    
    // Effective Dates
    effective_date: string;                // ISO date
    end_date?: string;
    
    // Timestamps
    created_at: string;
    updated_at: string;
}

/**
 * Employee Payroll Info Form Data
 */
export interface EmployeePayrollInfoFormData {
    employee_id: number;
    salary_type: 'monthly' | 'daily' | 'hourly' | 'contractual' | 'project_based';
    basic_salary: number;
    daily_rate?: number;
    hourly_rate?: number;
    payment_method: 'bank_transfer' | 'cash' | 'check';
    tax_status: string;
    rdo_code?: string;
    withholding_tax_exemption?: number;
    is_tax_exempt: boolean;
    is_substituted_filing: boolean;
    sss_number?: string;
    philhealth_number?: string;
    pagibig_number?: string;
    tin_number?: string;
    sss_bracket?: string;
    is_sss_voluntary: boolean;
    philhealth_is_indigent: boolean;
    pagibig_employee_rate: number;
    bank_name?: string;
    bank_code?: string;
    bank_account_number?: string;
    bank_account_name?: string;
    is_entitled_to_rice: boolean;
    is_entitled_to_uniform: boolean;
    is_entitled_to_laundry: boolean;
    is_entitled_to_medical: boolean;
    effective_date: string;
    end_date?: string;
    is_active: boolean;
}

/**
 * Payroll Info Filter Criteria
 */
export interface EmployeePayrollInfoFilters {
    search?: string;                       // Search by name or employee number
    salary_type?: string;
    payment_method?: string;
    tax_status?: string;
    status?: 'active' | 'inactive' | 'all';
}

/**
 * Employee Payroll Info Page Props
 */
export interface EmployeePayrollInfoPageProps {
    employees: EmployeePayrollInfo[];
    filters: EmployeePayrollInfoFilters;
    available_salary_types: Array<{ value: string; label: string }>;
    available_payment_methods: Array<{ value: string; label: string }>;
    available_tax_statuses: Array<{ value: string; label: string }>;
    available_departments: Array<{ id: number; name: string }>;
}

// ============================================================================
// PHASE 2.2: SALARY COMPONENTS
// ============================================================================

/**
 * Salary Component Entity
 * Represents a reusable salary component for payroll calculations
 * Examples: Basic Salary, Overtime, Rice Allowance, SSS, Withholding Tax, etc.
 */
export interface SalaryComponent {
    id: number;
    name: string;                          // "Basic Salary", "Overtime Regular", "Rice Allowance"
    code: string;                          // "BASIC", "OT_REG", "RICE" (unique)
    component_type: 'earning' | 'deduction' | 'benefit' | 'tax' | 'contribution' | 'loan' | 'allowance';
    category: 'regular' | 'overtime' | 'holiday' | 'leave' | 'allowance' | 'deduction' | 'tax' | 'contribution' | 'loan' | 'adjustment';
    
    // Calculation Settings
    calculation_method: 'fixed_amount' | 'percentage_of_basic' | 'percentage_of_gross' | 'per_hour' | 'per_day' | 'per_unit' | 'percentage_of_component';
    default_amount?: number;               // For fixed amount calculations
    default_percentage?: number;           // For percentage calculations
    reference_component_id?: number;       // For percentage of component calculations
    
    // Overtime and Premium Settings
    ot_multiplier?: number;                // 1.25, 1.30, 1.50, 2.00, 2.60
    is_premium_pay: boolean;
    
    // Tax Treatment
    is_taxable: boolean;
    is_deminimis: boolean;
    deminimis_limit_monthly?: number;
    deminimis_limit_annual?: number;
    is_13th_month: boolean;
    is_other_benefits: boolean;
    
    // Government Contribution Settings
    affects_sss: boolean;
    affects_philhealth: boolean;
    affects_pagibig: boolean;
    affects_gross_compensation: boolean;
    
    // Display Settings
    display_order: number;
    is_displayed_on_payslip: boolean;
    is_active: boolean;
    is_system_component: boolean;          // Cannot be deleted
    
    created_at: string;
    updated_at: string;
}

/**
 * Create/Edit Salary Component Form Data
 */
export interface SalaryComponentFormData {
    name: string;
    code: string;
    component_type: 'earning' | 'deduction' | 'benefit' | 'tax' | 'contribution' | 'loan' | 'allowance';
    category: 'regular' | 'overtime' | 'holiday' | 'leave' | 'allowance' | 'deduction' | 'tax' | 'contribution' | 'loan' | 'adjustment';
    
    calculation_method: 'fixed_amount' | 'percentage_of_basic' | 'percentage_of_gross' | 'per_hour' | 'per_day' | 'per_unit' | 'percentage_of_component';
    default_amount?: number;
    default_percentage?: number;
    reference_component_id?: number;
    
    ot_multiplier?: number;
    is_premium_pay: boolean;
    
    is_taxable: boolean;
    is_deminimis: boolean;
    deminimis_limit_monthly?: number;
    deminimis_limit_annual?: number;
    is_13th_month: boolean;
    is_other_benefits: boolean;
    
    affects_sss: boolean;
    affects_philhealth: boolean;
    affects_pagibig: boolean;
    affects_gross_compensation: boolean;
    
    display_order: number;
    is_displayed_on_payslip: boolean;
    is_active: boolean;
}

/**
 * Salary Component Filter Criteria
 */
export interface SalaryComponentFilters {
    search?: string;                       // Search by name or code
    component_type?: string;
    category?: string;
    is_active?: boolean | 'all';
    is_system?: boolean | 'all';
}

/**
 * Salary Components Listing Page Props
 */
export interface SalaryComponentsPageProps {
    components: SalaryComponent[];
    filters: SalaryComponentFilters;
    available_component_types: Array<{ value: string; label: string }>;
    available_categories: Array<{ value: string; label: string }>;
    reference_components: Array<{ id: number; name: string; code: string }>;
}

// ============================================================================
// SSS CONTRIBUTIONS PAGE TYPES (Phase 3.2)
// ============================================================================

/**
 * Main SSS Contributions Page Props
 * Passed from SSSController@index() via Inertia
 */
export interface SSSPageProps {
    contributions: SSSContribution[];
    periods: SSSPeriod[];
    summary: SSSSummary;
    remittances: SSSRemittance[];
    r3_reports: SSSR3Report[];
}

/**
 * SSS Period Selection
 */
export interface SSSPeriod {
    id: string | number;
    name: string;
    month: string; // YYYY-MM
    start_date: string; // YYYY-MM-DD
    end_date: string; // YYYY-MM-DD
    status: 'draft' | 'processed' | 'submitted' | 'approved';
}

/**
 * SSS Summary Metrics
 */
export interface SSSSummary {
    total_employees: number;
    total_monthly_compensation: number;
    total_employee_contribution: number;
    total_employer_contribution: number;
    total_ec_contribution: number;
    total_contribution: number;
    last_remittance_date: string | null;
    next_due_date: string;
    pending_remittances: number;
}

/**
 * Individual Employee SSS Contribution Record
 * Represents one employee's SSS contribution for a specific month
 */
export interface SSSContribution {
    id: string | number;
    employee_id: string | number;
    employee_name: string;
    employee_number: string;
    sss_number: string;
    period_id: string | number;
    month: string; // YYYY-MM
    
    // Compensation
    monthly_compensation: number; // Total monthly pay
    
    // SSS Bracket and Contributions
    sss_bracket: 'A' | 'B' | 'C' | 'D' | 'E' | 'F' | 'G' | 'H' | 'I' | 'J' | 'K' | 'L' | 'M';
    sss_bracket_range: {
        min: number;
        max: number;
    };
    
    // Employee Share (EE)
    employee_contribution: number;
    
    // Employer Share (ER)
    employer_contribution: number;
    
    // Employees' Compensation Insurance (EC)
    ec_contribution: number;
    
    // Total
    total_contribution: number;
    
    // Status
    is_processed: boolean;
    is_remitted: boolean;
    is_exempted: boolean;
    exemption_reason?: string;
    
    created_at: string;
    updated_at: string;
}

/**
 * SSS R3 Report (monthly contribution report for SSS portal)
 * Format: CSV for upload to SSS ERPS (Employer Registration and Payroll System)
 */
export interface SSSR3Report {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM
    file_name: string;
    file_path: string;
    file_size: number;
    
    // Summary
    total_employees: number;
    total_compensation: number;
    total_employee_share: number;
    total_employer_share: number;
    total_ec_share: number;
    total_amount: number;
    
    // Status
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    submission_status: string;
    submission_date: string | null;
    rejection_reason: string | null;
    
    created_at: string;
    updated_at: string;
}

/**
 * SSS Remittance Tracking
 * Tracks payment to SSS for processed contributions
 */
export interface SSSRemittance {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM
    
    // Remittance Details
    remittance_amount: number;
    due_date: string; // YYYY-MM-DD
    payment_date: string | null; // YYYY-MM-DD
    payment_reference: string | null;
    
    // Status
    status: 'pending' | 'paid' | 'partially_paid' | 'overdue';
    
    // Penalty (if applicable)
    has_penalty: boolean;
    penalty_amount: number;
    penalty_reason?: string;
    
    // Contributions included
    contributions: {
        employee_share: number;
        employer_share: number;
        ec_share: number;
    };
    
    created_at: string;
    updated_at: string;
}

/**
 * SSS Contribution Form Data (for creation/update)
 */
export interface SSSContributionFormData {
    employee_id: string | number;
    period_id: string | number;
    monthly_compensation: number;
    sss_number?: string;
    is_exempted?: boolean;
    exemption_reason?: string;
}

// ============================================================================
// Phase 3.3: PHILHEALTH CONTRIBUTIONS
// ============================================================================

/**
 * PhilHealth Contributions Page Props
 * Main page data for PhilHealth contributions management
 */
export interface PhilHealthPageProps {
    contributions: PhilHealthContribution[];
    periods: PhilHealthPeriod[];
    summary: PhilHealthSummary;
    remittances: PhilHealthRemittance[];
    rf1_reports: PhilHealthRF1Report[];
}

/**
 * PhilHealth Period (monthly period for contributions)
 */
export interface PhilHealthPeriod {
    id: string | number;
    name: string;
    month: string; // YYYY-MM
    start_date: string;
    end_date: string;
    status: string; // draft, pending, submitted, completed
}

/**
 * PhilHealth Summary Metrics
 */
export interface PhilHealthSummary {
    total_employees: number;
    total_monthly_basic: number;
    total_employee_premium: number; // 2.5%
    total_employer_premium: number; // 2.5%
    total_premium: number; // 5%
    last_remittance_date: string | null;
    next_due_date: string;
    pending_remittances: number;
    indigent_members: number;
}

/**
 * Individual employee PhilHealth contribution record
 * PhilHealth Rate: 5% of monthly basic (2.5% each, max 5,000)
 * Indigent: Fully sponsored by government, no deduction
 */
export interface PhilHealthContribution {
    id: string | number;
    employee_id: string | number;
    employee_name: string;
    employee_number: string;
    philhealth_number: string;
    period_id: string | number;
    month: string; // YYYY-MM

    // Base Information
    monthly_basic: number; // Monthly basic salary

    // Premium Calculation (5% total)
    employee_premium: number; // 2.5% deduction
    employer_premium: number; // 2.5% company cost
    total_premium: number; // 5% total

    // Status & Settings
    is_processed: boolean;
    is_remitted: boolean;
    is_indigent: boolean; // Government-sponsored member
    indigent_reason?: string;

    created_at: string;
    updated_at: string;
}

/**
 * PhilHealth RF1 Report (monthly contribution report)
 * Format: CSV for PhilHealth EPRS (Electronic Payroll Remittance System)
 */
export interface PhilHealthRF1Report {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM
    file_name: string;
    file_path: string;
    file_size: number;

    // Summary
    total_employees: number;
    total_basic_salary: number;
    total_employee_premium: number;
    total_employer_premium: number;
    total_premium: number;
    indigent_count: number;

    // Status
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    submission_status: string;
    submission_date: string | null;
    rejection_reason: string | null;

    created_at: string;
    updated_at: string;
}

/**
 * PhilHealth Remittance Tracking
 * Tracks monthly premium payment to PhilHealth
 */
export interface PhilHealthRemittance {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM

    // Remittance Details
    remittance_amount: number; // Total premium to remit
    due_date: string; // YYYY-MM-DD
    payment_date: string | null; // YYYY-MM-DD
    payment_reference: string | null;

    // Status
    status: 'pending' | 'paid' | 'partially_paid' | 'overdue';

    // Penalty (if applicable)
    has_penalty: boolean;
    penalty_amount: number;
    penalty_reason?: string;

    // Breakdown
    contributions: {
        employee_share: number;
        employer_share: number;
    };

    created_at: string;
    updated_at: string;
}

/**
 * PhilHealth Contribution Form Data (for creation/update)
 */
export interface PhilHealthContributionFormData {
    employee_id: string | number;
    period_id: string | number;
    monthly_basic: number;
    philhealth_number?: string;
    is_indigent?: boolean;
    indigent_reason?: string;
}

// ============================================================================
// Phase 3.4: PAG-IBIG CONTRIBUTIONS
// ============================================================================

/**
 * Pag-IBIG Contributions Page Props
 * Main page data for Pag-IBIG contributions management
 */
export interface PagIbigPageProps {
    contributions: PagIbigContribution[];
    periods: PagIbigPeriod[];
    summary: PagIbigSummary;
    remittances: PagIbigRemittance[];
    mcrf_reports: PagIbigMCRFReport[];
    loan_deductions: PagIbigLoanDeduction[];
}

/**
 * Pag-IBIG Period (monthly period for contributions)
 */
export interface PagIbigPeriod {
    id: string | number;
    name: string;
    month: string; // YYYY-MM
    start_date: string;
    end_date: string;
    status: string; // draft, pending, submitted, completed
}

/**
 * Pag-IBIG Summary Metrics
 */
export interface PagIbigSummary {
    total_employees: number;
    total_monthly_compensation: number;
    total_employee_contribution: number; // 1% or 2%
    total_employer_contribution: number; // 2%
    total_contribution: number; // 3-4% total
    total_loan_deductions: number;
    last_remittance_date: string | null;
    next_due_date: string;
    pending_remittances: number;
}

/**
 * Individual employee Pag-IBIG contribution record
 * Pag-IBIG Rate: 3-4% of monthly compensation
 * Employee: 1% or 2% (based on contribution type)
 * Employer: 2%
 * Maximum contribution: ₱100 per month (employee), ₱100 (employer)
 */
export interface PagIbigContribution {
    id: string | number;
    employee_id: string | number;
    employee_name: string;
    employee_number: string;
    pagibig_number: string;
    period_id: string | number;
    month: string; // YYYY-MM

    // Base Information
    monthly_compensation: number;

    // Contribution Calculation
    employee_rate: number; // 1% or 2%
    employee_contribution: number;
    employer_contribution: number; // Fixed 2%
    total_contribution: number;

    // Status & Settings
    is_processed: boolean;
    is_remitted: boolean;
    has_active_loan: boolean;

    created_at: string;
    updated_at: string;
}

/**
 * Pag-IBIG MCRF Report (monthly contribution remittance form)
 * Format: CSV for Pag-IBIG eSRS (Electronic Savings and Remittance System)
 */
export interface PagIbigMCRFReport {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM
    file_name: string;
    file_path: string;
    file_size: number;

    // Summary
    total_employees: number;
    total_employee_contribution: number;
    total_employer_contribution: number;
    total_contribution: number;
    employees_with_loans: number;

    // Status
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    submission_status: string;
    submission_date: string | null;
    rejection_reason: string | null;

    created_at: string;
    updated_at: string;
}

/**
 * Pag-IBIG Remittance Tracking
 * Tracks monthly contribution payment to Pag-IBIG
 */
export interface PagIbigRemittance {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM

    // Remittance Details
    remittance_amount: number;
    due_date: string; // YYYY-MM-DD
    payment_date: string | null; // YYYY-MM-DD
    payment_reference: string | null;

    // Status
    status: 'pending' | 'paid' | 'partially_paid' | 'overdue';

    // Penalty (if applicable)
    has_penalty: boolean;
    penalty_amount: number;
    penalty_reason?: string;

    // Breakdown
    contributions: {
        employee_share: number;
        employer_share: number;
    };

    created_at: string;
    updated_at: string;
}

/**
 * Pag-IBIG Loan Deduction
 * Tracks active Pag-IBIG loans and monthly deductions
 */
export interface PagIbigLoanDeduction {
    id: string | number;
    employee_id: string | number;
    employee_name: string;
    employee_number: string;

    // Loan Details
    loan_number: string;
    loan_type: 'calamity' | 'housing' | 'educational' | 'other';
    loan_amount: number;
    disbursement_date: string;

    // Amortization
    monthly_deduction: number;
    months_remaining: number;
    total_deducted_to_date: number;

    // Status
    is_active: boolean;
    maturity_date: string;

    created_at: string;
    updated_at: string;
}

/**
 * Pag-IBIG Contribution Form Data (for creation/update)
 */
export interface PagIbigContributionFormData {
    employee_id: string | number;
    period_id: string | number;
    monthly_compensation: number;
    pagibig_number?: string;
    employee_rate?: number; // 1 or 2
}

// ============================================================================
// Phase 3.5: GOVERNMENT REMITTANCES DASHBOARD
// ============================================================================

/**
 * Government Remittances Page Props
 * Main page data for all government agency remittance tracking
 */
export interface GovernmentRemittancesPageProps {
    remittances: GovernmentRemittance[];
    periods: RemittancePeriod[];
    summary: RemittanceSummary;
    calendarEvents: RemittanceCalendarEvent[];
}

/**
 * Remittance Period (monthly period for tracking)
 */
export interface RemittancePeriod {
    id: string | number;
    name: string; // "November 2025", "October 2025"
    month: string; // YYYY-MM
    start_date: string; // YYYY-MM-DD
    end_date: string; // YYYY-MM-DD
    status: string; // draft, pending, completed, closed
}

/**
 * Government Remittances Summary Metrics
 * Aggregated across all 4 agencies (BIR, SSS, PhilHealth, Pag-IBIG)
 */
export interface RemittanceSummary {
    // Total amounts
    total_to_remit: number; // Sum of all remittances
    pending_amount: number; // Amount waiting to be paid
    paid_amount: number; // Amount already paid this month
    overdue_amount: number; // Amount past due date

    // By Agency (4 agencies)
    bir_amount: number;
    sss_amount: number;
    philhealth_amount: number;
    pagibig_amount: number;

    // Counts
    total_remittances: number;
    pending_count: number;
    paid_count: number;
    overdue_count: number;

    // Dates
    next_due_date: string; // YYYY-MM-DD
    last_paid_date: string | null;
}

/**
 * Individual Government Remittance Record
 * Tracks payment to one government agency for one month
 */
export interface GovernmentRemittance {
    id: string | number;
    period_id: string | number;
    month: string; // YYYY-MM

    // Agency Information
    agency: 'BIR' | 'SSS' | 'PhilHealth' | 'Pag-IBIG';
    agency_full_name: string;

    // Remittance Details
    remittance_amount: number;
    report_type: string; // 1601C, R3, RF1, MCRF
    employees_covered: number;

    // Schedule
    due_date: string; // YYYY-MM-DD (10th of following month typically)
    payment_date: string | null; // YYYY-MM-DD
    payment_reference: string | null; // PRN, reference number

    // Status Tracking
    status: 'pending' | 'paid' | 'partially_paid' | 'overdue' | 'late';
    days_until_due: number; // Negative = overdue
    is_overdue: boolean;

    // Penalty Information
    has_penalty: boolean;
    penalty_amount: number; // For late payments
    penalty_rate: number; // Percentage (e.g., 5% per month for late payment)

    // Additional Info
    remittance_method: string; // 'eFPS', 'eR3', 'EPRS', 'eSRS', 'Bank'
    notes: string | null;

    created_at: string;
    updated_at: string;
}

/**
 * Calendar Event for Remittance Due Dates
 * Used for calendar visualization
 */
export interface RemittanceCalendarEvent {
    id: string | number;
    remittance_id: string | number;
    date: string; // YYYY-MM-DD
    agency: 'BIR' | 'SSS' | 'PhilHealth' | 'Pag-IBIG';
    status: 'pending' | 'paid' | 'overdue';
    amount: number;
    report_type: string;
}

// ============================================================================
// BANK FILES GENERATION PAGE TYPES - PHASE 4.1
// ============================================================================

/**
 * Main Bank Files Page Props
 * Passed from BankFilesController@index() via Inertia
 */
export interface BankFilesPageProps {
    bankFiles: BankPayrollFile[];
    periods: PayrollPeriod[];
    bankList: BankOption[];
    employeesCount: number;
}

/**
 * Individual Bank Payroll File Record
 */
export interface BankPayrollFile {
    id: number;
    payroll_period_id: number;
    period_name: string; // "November 2025 - 2nd Half"
    
    // Bank Details
    bank_name: string; // "BDO", "Metrobank" (only Phase 1 banks)
    bank_code: string | null;
    transfer_type: 'instapay' | 'pesonet' | 'internal' | null;
    
    // File Information
    file_name: string;
    file_path: string;
    file_format: 'csv' | 'txt' | 'excel' | 'fixed_width';
    file_size: number; // In bytes
    file_hash: string;
    is_validated: boolean;
    validated_at: string | null;
    validation_errors: Array<{ employee_id: number; message: string }> | null;
    
    // Payroll Summary
    total_employees: number;
    total_amount: number;
    successful_count: number | null;
    failed_count: number | null;
    total_fees: number;
    settlement_date: string | null;
    settlement_reference: string | null;
    
    // Status Tracking
    status: 'draft' | 'ready' | 'submitted' | 'processing' | 'completed' | 'partially_completed' | 'failed';
    status_label: string;
    status_color: 'gray' | 'blue' | 'yellow' | 'green' | 'orange' | 'red';
    
    // Timestamps
    submitted_at: string | null;
    submitted_by: string | null;
    bank_confirmation_number: string | null;
    
    created_at: string;
    updated_at: string;
}

/**
 * Bank Option for Selection
 * 
 * Phase 1 (Current): Only 'BDO' and 'Metrobank' are seeded in payment_methods.
 * The BankOption[] data returned by the controller must only contain these two banks.
 * Future phases can add more banks (BPI, PNB, RCBC, Unionbank, etc.).
 * 
 * Note: No union type restriction on id/name here — kept flexible for future expansion.
 * Bank validation occurs in the controller (see BankFilesController validation).
 */
export interface BankOption {
    id: string;
    name: string;
    code: string;
    icon?: string;
    supported_formats: Array<'csv' | 'txt' | 'excel' | 'fixed_width'>;
}

/**
 * File Format Option
 */
export interface FileFormatOption {
    id: 'csv' | 'txt' | 'excel' | 'fixed_width';
    name: string;
    description: string;
    extension: string;
}

/**
 * Employee for Bank File Preview
 */
export interface BankFileEmployee {
    id: number;
    employee_number: string;
    full_name: string;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
    net_pay: number;
    status: 'valid' | 'invalid' | 'warning';
    error_message?: string;
}

/**
 * Bank File Generation Request Payload
 */
export interface BankFileGenerationRequest {
    period_id: number;
    bank_name: string;
    file_format: 'csv' | 'txt' | 'excel' | 'fixed_width';
    include_employees?: number[];
    exclude_employees?: number[];
}

/**
 * Bank File Generation Response
 */
export interface BankFileGenerationResponse {
    success: boolean;
    message: string;
    file?: BankPayrollFile;
    download_url?: string;
    errors?: Array<{
        employee_id: number;
        message: string;
    }>;
}

/**
 * ============================================================================
 * PAYSLIPS MANAGEMENT - Phase 4.2
 * ============================================================================
 */

/**
 * Payslip (DOLE-Compliant)
 * Contains detailed breakdown of employee earnings, deductions, and net pay.
 * Earnings and deductions are stored as JSON in the DB for flexibility.
 */
export interface Payslip {
    id: string | number;
    payslip_number: string; // PS-2025-10-00123
    payroll_payment_id: string | number; // FK to payroll_payments.id
    employee_id: string | number;
    payroll_period_id: string | number;

    // Employee Information
    employee_number: string;
    employee_name: string;
    position: string;
    department: string;
    
    // Employee Identification (DOLE-compliant display)
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    tin: string | null;

    // Period Information
    period_name: string;
    period_start: string; // YYYY-MM-DD
    period_end: string; // YYYY-MM-DD
    pay_date: string; // YYYY-MM-DD

    // Earnings Breakdown (JSON structure for flexibility)
    earnings_data: Record<string, number>; // {"basic_salary": 15000, "overtime": 1200, ...}
    gross_pay: number;

    // Deductions Breakdown (JSON structure for flexibility)
    deductions_data: Record<string, number>; // {"sss": 1125, "philhealth": 450, ...}
    total_deductions: number;

    // Net Pay
    net_pay: number;

    // YTD (Year-to-Date) Totals — split by category
    ytd_gross: number;
    ytd_sss: number;
    ytd_philhealth: number;
    ytd_pagibig: number;
    ytd_tax: number;
    ytd_net: number;

    // File Information (stripped pdf_ prefix)
    file_path: string | null;
    file_size: number | null;
    file_hash: string | null;
    signature_hash: string | null; // digital signature
    qr_code_data: string | null;   // QR code payload

    // Distribution Tracking
    distribution_method: 'email' | 'portal' | 'print' | 'sms'; // changed 'printed' → 'print', added 'sms'
    distributed_at: string | null; // when distributed
    is_viewed: boolean;            // employee viewed via portal
    viewed_at: string | null;

    // Generation Status
    status: 'draft' | 'generated' | 'distributed' | 'acknowledged';
    status_label: string;
    status_color: string;

    created_at: string;
    updated_at: string;
}

/**
 * Payslips Page Props
 */
export interface PayslipsPageProps {
    payslips: Payslip[];
    summary: PayslipsSummary;
    filters: PayslipsFilters;
    periods: Array<{
        id: number;
        name: string;
        start_date: string;
        end_date: string;
        pay_date: string;
    }>;
    departments: Array<{
        id: number;
        name: string;
    }>;
    distributionMethods: Array<{
        id: string;
        name: string;
    }>;
}

/**
 * Payslips Summary Metrics
 * Updated to match payslips.status enum in DB: draft, generated, distributed, acknowledged
 */
export interface PayslipsSummary {
    total_payslips: number;
    draft: number;
    generated: number;
    distributed: number;   // was sent
    acknowledged: number;
    total_distribution_email: number;
    total_distribution_portal: number;
    total_distribution_print: number;   // was total_distribution_printed
    total_distribution_sms: number;     // new channel
}

/**
 * Payslips Filters
 */
export interface PayslipsFilters {
    search: string;
    period_id: number | null;
    department_id: number | null;
    status: string; // 'all' | 'draft' | 'generated' | 'distributed' | 'acknowledged'
    distribution_method: string; // 'all' | 'email' | 'portal' | 'print' | 'sms'
    date_from: string | null;
    date_to: string | null;
}

/**
 * Payslip Generation Request
 */
export interface PayslipGenerationRequest {
    period_id: number;
    employee_ids?: number[]; // If empty, generate for all employees
    regenerate?: boolean; // Force regenerate existing payslips
    distribution_method: 'email' | 'portal' | 'print' | 'sms';
}

/**
 * Payslip Generation Response
 */
export interface PayslipGenerationResponse {
    success: boolean;
    message: string;
    generated_count: number;
    failed_count: number;
    payslips?: Payslip[];
    errors?: Array<{
        employee_id: number;
        employee_name: string;
        message: string;
    }>;
}

/**
 * Payslip Distribution Request
 */
export interface PayslipDistributionRequest {
    payslip_ids: number[];
    distribution_method: 'email' | 'portal' | 'print' | 'sms';
    email_subject?: string;
    email_message?: string;
}

/**
 * Payslip Distribution Response
 */
export interface PayslipDistributionResponse {
    success: boolean;
    message: string;
    sent_count: number;
    failed_count: number;
    errors?: Array<{
        payslip_id: number;
        employee_name: string;
        message: string;
    }>;
}

/**
 * Payslip Preview Data (For modal preview before generation)
 */
export interface PayslipPreviewData {
    employee_id: number;
    employee_number: string;
    employee_name: string;
    position: string;
    department: string;

    period_name: string;
    period_start: string;
    period_end: string;
    pay_date: string;

    // Earnings
    earnings: Array<{
        name: string;
        amount: number;
    }>;
    gross_pay: number;

    // Deductions
    deductions: Array<{
        name: string;
        amount: number;
    }>;
    total_deductions: number;

    // Net Pay
    net_pay: number;

    // YTD
    ytd_gross: number;
    ytd_deductions: number;
    ytd_net: number;
}

// ============================================================================
// PAYMENT TRACKING PAGE TYPES (PHASE 4.3)
// ============================================================================

/**
 * Payment Tracking Index Page Props
 */
export interface PaymentTrackingPageProps {
    payments: PaymentTracking[];
    summary: PaymentStatusSummary;
    payroll_periods: PayrollPeriod[];
    departments: Array<{ id: number; name: string }>;
    payment_methods: Array<{
        id: number;
        method_type: 'bank' | 'cash' | 'ewallet';
        display_name: string;
        is_enabled: boolean;
    }>;
    payment_statuses: string[];
    failed_payments: FailedPayment[];
}

/**
 * Payment Status Summary (4 cards: total, paid, pending, failed)
 */
export interface PaymentStatusSummary {
    total_employees: number;              // All employees in payroll
    paid_count: number;                   // Successfully paid
    pending_count: number;                // Not yet paid
    failed_count: number;                 // Failed transactions
    total_amount: number;                 // Total net pay to be disbursed
    total_paid_amount: number;            // Total amount already paid
    total_pending_amount: number;         // Total amount pending
    total_failed_amount: number;          // Total amount in failed payments
    formatted_total: string;              // "₱4,250,000.00"
    formatted_paid: string;               // "₱3,200,000.00"
    formatted_pending: string;            // "₱800,000.00"
    formatted_failed: string;             // "₱250,000.00"
    paid_percentage: number;              // 0-100
    pending_percentage: number;           // 0-100
    failed_percentage: number;            // 0-100
}

/**
 * Payment Tracking Record (table row)
 */
export interface PaymentTracking {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    department: string;
    position: string;
    
    // Payment Details
    payroll_period_id: number;
    period_name: string;
    net_pay: number;
    formatted_net_pay: string;            // "₱25,000.00"
    
    // Payment Method and Status
    payment_method: 'bank' | 'cash' | 'ewallet';
    payment_method_label: string;         // "Bank Transfer"
    payment_method_icon: 'bank' | 'cash' | 'ewallet';
    
    payment_status: 'pending' | 'processing' | 'paid' | 'partially_paid' | 'failed' | 'cancelled' | 'unclaimed';
    payment_status_label: string;         // "Pending", "Processing", "Paid", "Partially Paid", "Failed", "Cancelled", "Unclaimed"
    payment_status_color: string;         // 'yellow', 'blue', 'green', 'orange', 'red', 'gray', 'red'
    
    // Bank Details (if applicable)
    bank_name?: string;                   // "BPI", "BDO"
    account_number?: string;              // Last 4 digits: "****5678"
    
    // Payment Tracking
    payment_date?: string;                // ISO date, nullable
    payment_reference?: string;           // Transaction ID / Check number
    payment_confirmation_file?: string;   // File path to confirmation/receipt
    
    // Failure Information
    failure_reason?: string;              // Why payment failed
    
    // UI Actions
    can_mark_paid: boolean;
    can_retry: boolean;
    can_confirm: boolean;
}

/**
 * Failed Payment Details
 */
export interface FailedPayment {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    
    payroll_period_id: number;
    period_name: string;
    
    net_pay: number;
    formatted_net_pay: string;
    
    current_payment_method: 'bank' | 'cash' | 'ewallet';
    payment_method_label: string;
    
    failure_reason: string;               // "Insufficient balance", "Invalid account", etc.
    failed_at: string;                    // ISO datetime (was failure_timestamp)
    
    retry_count: number;                  // Number of retry attempts
    // Note: max_retries is a system constant (3), not per-record
    
    // Alternative Actions
    alternative_methods: Array<{
        method: 'bank' | 'cash' | 'ewallet';
        label: string;
        available: boolean;
    }>;
    
    // Memo/Notes
    notes?: string;
}

// ============================================================================
// CASH PAYMENTS PAGE TYPES
// ============================================================================

/**
 * Cash Payments Page Props
 * Passed from CashPaymentController@index() via Inertia
 */
export interface CashPaymentPageProps {
    cash_employees: CashEmployee[];
    summary: CashPaymentSummary;
    payroll_periods: PayrollPeriod[];
    batches: CashDistributionBatch[];         // Renamed from distributions (batch-level, not per-employee)
    unclaimed_cash: UnclaimedCash[];
    departments: Array<{ id: number; name: string }>; // Needed for filters
    query_params: {
        period_id: string | number;
        department_id: string | number;
        status: string;
        search: string;
    };
}

/**
 * Cash Payment Summary (Overview Cards)
 */
export interface CashPaymentSummary {
    total_cash_employees: number;
    total_cash_amount: number;
    formatted_total_cash: string;
    envelopes_prepared: number;       // was envelopes_printed
    envelopes_distributed: number;    // was envelopes_pending (renamed conceptually)
    distributed_count: number;
    pending_distribution: number;
    unclaimed_count: number;
    formatted_unclaimed_amount: string;
}

/**
 * Employee eligible for cash payment
 * Status (payment_status) is derived from payroll_payments.status.
 * Note: Envelope printing/preparedness is tracked at batch level (CashDistributionBatch),
 *       not per-employee. Use batch.envelopes_prepared, batch.envelopes_distributed for envelope state.
 */
export interface CashEmployee {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    department: string;
    position: string;
    
    payroll_period_id: number;
    period_name: string;
    
    net_pay: number;
    formatted_net_pay: string;
    
    payment_method: 'cash';
    payment_method_label: string;
    
    // Payment Status (consolidated from payroll_payments.status)
    payment_status: 'pending' | 'processing' | 'paid' | 'failed' | 'unclaimed';
    payment_status_label: string;
    payment_status_color: 'gray' | 'blue' | 'orange' | 'green' | 'red';
    
    // Cash Distribution & Accountability
    distributed_at?: string;         // When cash was handed over (payroll_payments.paid_at)
    distributed_by?: string;         // User name who distributed the cash
    claimed_at?: string;             // When employee acknowledged receipt
    claimed_by?: string;             // Can be tracked via payment_audit_logs
    
    // Signature & Notes
    signature_capture_url?: string;  // Employee signature on receipt (claimed_by_signature)
    distribution_notes?: string;
    contact_number?: string;
    email?: string;
}

/**
 * Envelope Data for Printing
 */
export interface EnvelopeData {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    position: string;
    
    period_name: string;
    period_start_date: string;
    period_end_date: string;
    
    net_pay: number;
    formatted_net_pay: string;
    
    barcode: string;  // Unique barcode/reference
    qr_code: string;  // QR code data URL
    
    department: string;
    print_date: string;
    page_number: number;
    total_pages: number;
}

/**
 * Cash Distribution Batch (Batch-Level Distribution Records)
 * Represents a batch of cash distributions during a single occurrence.
 * This is distinct from CashEmployee which represents individual employee cash payments.
 * All envelopes, countdowns, and distribution logistics are tracked at batch level.
 */
export interface CashDistributionBatch {
    id: number;
    payroll_period_id: number;
    period_name: string;                     // joined from payroll_periods

    batch_number: string;                    // e.g. "CDB-2025-10-001"
    distribution_date: string;               // date only
    distribution_time?: string;              // time of distribution
    distribution_location: string | null;

    total_cash_amount: number;
    total_employees: number;
    formatted_total_cash: string;

    denomination_breakdown: Record<string, number> | null; // {"1000": 50, "500": 30, ...}

    envelopes_prepared: number;
    envelopes_distributed: number;
    envelopes_unclaimed: number;
    amount_distributed: number;
    amount_unclaimed: number;

    prepared_by: string | null;              // joined user name
    counted_by: string | null;               // joined user name
    witnessed_by: string | null;             // joined user name
    distributed_by: string | null;           // joined user name
    verification_at: string | null;

    status: 'pending' | 'counting' | 'verified' | 'distributing' | 'completed' | 'partially_completed';
    status_label: string;
    status_color: string;

    notes: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Unclaimed Cash Tracking
 * Records cash payments that remain unclaimed by employees.
 * Removes computed fields (days_until_returned, contact_attempts, last_contact_attempt)
 * that are not stored in the database; compute them in controller if needed from company policy.
 */
export interface UnclaimedCash {
    id: number;
    payroll_payment_id: number;             // FK to payroll_payments
    employee_id: number;
    employee_number: string;
    employee_name: string;
    period_name: string;
    amount: number;
    formatted_amount: string;
    days_unclaimed: number;                 // computed: today - envelope_prepared_at
    envelope_prepared_at: string;
    distribution_scheduled_for: string | null;
    status: 'pending_distribution' | 'pending_collection' | 'escalated' | 'returned';
    status_label: string;
    status_color: string;
    notes: string | null;
}

// ============================================================================
// PHASE 5: REPORTS & ANALYTICS - Payroll Register
// ============================================================================

/**
 * Payroll Register Employee Record
 * Detailed employee payroll information for reporting
 */
export interface PayrollRegisterEmployee {
    id: number;
    employee_id: number;
    period_id: number;
    employee_number: string;
    full_name: string;
    department_id: number;
    department_name: string;
    position: string;
    status: 'active' | 'inactive' | 'on_leave';
    
    // Earnings
    basic_salary: number;
    overtime: number;
    rice_allowance: number;
    cola: number;
    gross_pay: number;
    
    // Deductions
    sss: number;
    philhealth: number;
    pagibig: number;
    withholding_tax: number;
    total_deductions: number;
    
    // Net Pay
    net_pay: number;
    
    // Components breakdown
    components: Array<{
        code: string;
        name: string;
        type: 'earning' | 'deduction' | 'benefit' | 'tax' | 'allowance';
        amount: number;
    }>;
}

/**
 * Payroll Register Summary
 * Overall summary statistics
 */
export interface PayrollRegisterSummary {
    total_employees: number;
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
    formatted_total_gross_pay: string;
    formatted_total_deductions: string;
    formatted_total_net_pay: string;
}

/**
 * Department Breakdown Record
 * Department-level payroll summary
 */
export interface DepartmentBreakdown {
    department_id: number;
    department_name: string;
    employee_count: number;
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
    average_net_pay: number;
    formatted_gross_pay: string;
    formatted_deductions: string;
    formatted_net_pay: string;
    formatted_average_net_pay: string;
}

/**
 * Payroll Register Report Page Props
 */
export interface PayrollRegisterPageProps {
    register_data: PayrollRegisterEmployee[];
    summary: PayrollRegisterSummary;
    department_breakdown: DepartmentBreakdown[];
    periods: Array<{
        id: number;
        name: string;
        period_type: string;
        start_date: string;
        end_date: string;
        pay_date: string;
    }>;
    departments: Array<{
        id: number;
        name: string;
    }>;
    employee_statuses: Array<{
        id: string;
        label: string;
    }>;
    salary_components: Array<{
        id: number;
        code: string;
        name: string;
        type: string;
    }>;
    filters: {
        period_id: string;
        department_id: string;
        employee_status: string;
        component_filter: string;
        search: string;
    };
}

// ============================================================================
// GOVERNMENT REPORTS PAGE TYPES
// ============================================================================

/**
 * Government Reports Summary Page Props
 * Displays all government reports (SSS, PhilHealth, Pag-IBIG, BIR) in one view
 */
export interface GovernmentReportsPageProps {
    reports_summary: GovernmentReportsSummary;
    sss_reports: SSSReportCard[];
    philhealth_reports: PhilHealthReportCard[];
    pagibig_reports: PagIbigReportCard[];
    bir_reports: BIRReportCard[];
    upcoming_deadlines: GovernmentReportDeadline[];
    compliance_status: GovernmentReportCompliance;
}

export interface GovernmentReportsSummary {
    total_reports_generated: number;
    total_reports_submitted: number;
    reports_pending_submission: number;
    total_contributions: number;
    next_deadline: string | null;
    overdue_reports: number;
}

export interface SSSReportCard {
    id: number;
    report_type: 'R3' | 'Monthly';
    period_id: number;
    period_name: string;
    month: string;
    year: number;
    total_employees: number;
    total_compensation: number;
    employee_share: number;
    employer_share: number;
    ec_share: number;
    total_contribution: number;
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    status_label: string;
    status_color: 'blue' | 'yellow' | 'green' | 'red';
    submission_date: string | null;
    due_date: string;
    is_overdue: boolean;
    file_name: string | null;
    file_path: string | null;
    action_required: boolean;
    error_message: string | null;
}

export interface PhilHealthReportCard {
    id: number;
    report_type: 'RF1' | 'Monthly';
    period_id: number;
    period_name: string;
    month: string;
    year: number;
    total_employees: number;
    total_compensation: number;
    employee_share: number;
    employer_share: number;
    total_contribution: number;
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    status_label: string;
    status_color: 'blue' | 'yellow' | 'green' | 'red';
    submission_date: string | null;
    due_date: string;
    is_overdue: boolean;
    file_name: string | null;
    file_path: string | null;
    action_required: boolean;
    error_message: string | null;
}

export interface PagIbigReportCard {
    id: number;
    report_type: 'MCRF' | 'Monthly';
    period_id: number;
    period_name: string;
    month: string;
    year: number;
    total_employees: number;
    total_compensation: number;
    employee_share: number;
    employer_share: number;
    total_contribution: number;
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    status_label: string;
    status_color: 'blue' | 'yellow' | 'green' | 'red';
    submission_date: string | null;
    due_date: string;
    is_overdue: boolean;
    file_name: string | null;
    file_path: string | null;
    action_required: boolean;
    error_message: string | null;
}

export interface BIRReportCard {
    id: number;
    report_type: '1601C' | '2316' | 'Alphalist' | 'Monthly';
    period_id: number;
    period_name: string;
    month: string;
    year: number;
    total_employees: number;
    total_compensation: number;
    total_tax_withheld: number;
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    status_label: string;
    status_color: 'blue' | 'yellow' | 'green' | 'red';
    submission_date: string | null;
    due_date: string;
    is_overdue: boolean;
    file_name: string | null;
    file_path: string | null;
    action_required: boolean;
    error_message: string | null;
}

export interface GovernmentReportDeadline {
    id: number;
    report_type: string;
    agency: 'SSS' | 'PhilHealth' | 'Pag-IBIG' | 'BIR';
    agency_label: string;
    due_date: string;
    days_until_due: number;
    is_overdue: boolean;
    related_period_id: number;
    related_period_name: string;
    action_url: string;
}

export interface GovernmentReportCompliance {
    total_required_reports: number;
    total_submitted_reports: number;
    submission_percentage: number;
    submission_status: 'on_track' | 'at_risk' | 'non_compliant';
    submission_status_label: string;
    last_submission_date: string | null;
    next_due_date: string | null;
    agencies: {
        sss: AgencyComplianceStatus;
        philhealth: AgencyComplianceStatus;
        pagibig: AgencyComplianceStatus;
        bir: AgencyComplianceStatus;
    };
}

export interface AgencyComplianceStatus {
    agency: string;
    total_reports_required: number;
    total_reports_submitted: number;
    submission_percentage: number;
    compliance_status: 'compliant' | 'at_risk' | 'non_compliant';
    compliance_status_label: string;
    last_submission_date: string | null;
    next_due_date: string | null;
}

// ============================================================
// PHASE 5.3: LABOR COST ANALYTICS
// ============================================================

export interface LaborCostAnalyticsPageProps {
    cost_trend_data: MonthlyLaborCostTrend[];
    department_comparisons: DepartmentCostComparison[];
    component_breakdown: ComponentCostBreakdown[];
    yoy_comparisons: YearOverYearComparison[];
    employee_cost_analysis: EmployeeCostAnalysis[];
    budget_variance_data: BudgetVarianceData[];
    forecast_projections: ForecastProjection[];
    analytics_summary: AnalyticsSummary;
    selected_period: string;
    available_periods: string[];
    available_departments: Department[];
}

export interface MonthlyLaborCostTrend {
    month: string;
    month_label: string;
    total_labor_cost: number;
    total_basic_salary: number;
    total_allowances: number;
    total_overtime: number;
    total_benefits: number;
    total_contributions: number;
    total_taxes: number;
    employee_count: number;
    cost_per_employee: number;
}

export interface DepartmentCostComparison {
    department_id: number;
    department_name: string;
    total_employees: number;
    total_labor_cost: number;
    basic_salary_cost: number;
    allowances_cost: number;
    overtime_cost: number;
    benefits_cost: number;
    contributions_cost: number;
    tax_cost: number;
    cost_percentage: number;
    average_cost_per_employee: number;
    trend: 'up' | 'down' | 'stable';
    trend_percentage: number;
}

export interface ComponentCostBreakdown {
    component_id: number;
    component_name: string;
    component_code: string;
    component_type: 'earning' | 'deduction' | 'benefit' | 'tax' | 'contribution';
    total_amount: number;
    percentage_of_gross: number;
    affected_employees: number;
    average_per_employee: number;
    trend: 'up' | 'down' | 'stable';
    trend_percentage: number;
}

export interface YearOverYearComparison {
    month: string;
    current_year_cost: number;
    previous_year_cost: number;
    difference: number;
    percentage_change: number;
    current_year_employees: number;
    previous_year_employees: number;
    cost_per_employee_current: number;
    cost_per_employee_previous: number;
}

export interface EmployeeCostAnalysis {
    employee_id: number;
    employee_name: string;
    employee_code: string;
    department_id: number;
    department_name: string;
    position: string;
    basic_salary: number;
    total_gross_pay: number;
    total_deductions: number;
    net_pay: number;
    cost_to_company: number;
    component_breakdown: EmployeeComponentBreakdown[];
    vs_department_average: number;
    vs_position_average: number;
}

export interface EmployeeComponentBreakdown {
    component_name: string;
    component_type: string;
    amount: number;
}

export interface BudgetVarianceData {
    department_id: number;
    department_name: string;
    component_name: string;
    budgeted_amount: number;
    actual_amount: number;
    variance: number;
    variance_percentage: number;
    variance_status: 'favorable' | 'unfavorable' | 'on_target';
    remaining_budget: number;
}

export interface ForecastProjection {
    month: string;
    month_label: string;
    projected_labor_cost: number;
    projected_basic_salary: number;
    projected_allowances: number;
    projected_overtime: number;
    projected_benefits: number;
    projected_contributions: number;
    projected_taxes: number;
    projected_employee_count: number;
    confidence_level: 'high' | 'medium' | 'low';
}

export interface AnalyticsSummary {
    current_period: string;
    total_labor_cost: number;
    average_monthly_cost: number;
    total_employees: number;
    average_cost_per_employee: number;
    largest_cost_component: string;
    largest_cost_component_amount: number;
    largest_cost_component_percentage: number;
    highest_cost_department: string;
    highest_cost_department_amount: number;
    trend_vs_last_period: number;
    trend_vs_last_year: number;
}

export interface Department {
    id: number;
    name: string;
}

// ============================================================================
// AUDIT TRAIL & HISTORY PAGE TYPES (Phase 5.4)
// ============================================================================

/**
 * Main Audit Trail Page Props
 * Passed from PayrollAuditController@index() via Inertia
 */
export interface PayrollAuditPageProps {
    auditLogs: PayrollAuditLog[];
    changeHistory: ChangeHistory[];
    filters: AuditFilters;
}

/**
 * Audit Log Entry - Comprehensive action tracking
 * Based on payroll_audit_logs table schema
 */
export interface PayrollAuditLog {
    id: number;
    action: string;                                // "created", "calculated", "adjusted", "approved", "finalized"
    action_label: string;                          // Formatted label for display
    action_color: 'blue' | 'green' | 'yellow' | 'red' | 'purple';
    entity_type: string;                           // "PayrollPeriod", "PayrollCalculation", "PayrollAdjustment"
    entity_id: number;
    entity_name: string;                           // Display name of entity (period name, etc.)
    user_id: number;
    user_name: string;                             // Full name of user who performed action
    user_email: string;
    timestamp: string;                             // ISO datetime
    formatted_date: string;                        // "November 22, 2025"
    formatted_time: string;                        // "2:30 PM"
    relative_time: string;                         // "2 hours ago"
    changes_summary: string | null;                // "Basic salary: ₱25,000 → ₱26,500"
    old_values: Record<string, string | number | boolean> | null;       // Previous field values
    new_values: Record<string, string | number | boolean> | null;       // New field values
    ip_address: string | null;
    has_changes: boolean;                          // Whether old_values and new_values are populated
}

/**
 * Change History - Detailed record of all modifications
 * Used for drill-down and comparison views
 */
export interface ChangeHistory {
    id: number;
    log_id: number;                                // Reference to PayrollAuditLog
    entity_type: string;                           // "PayrollPeriod", "PayrollCalculation", etc.
    entity_id: number;
    field_name: string;                            // "status", "total_gross_pay", "approved_by"
    field_label: string;                           // "Status", "Total Gross Pay", "Approved By"
    old_value: string | number | boolean | null;
    new_value: string | number | boolean | null;
    formatted_old_value: string;                   // Formatted for display
    formatted_new_value: string;                   // Formatted for display
    value_type: 'string' | 'number' | 'currency' | 'date' | 'boolean';
    user_id: number;
    user_name: string;
    timestamp: string;                             // ISO datetime
    formatted_timestamp: string;                   // "November 22, 2025 2:30 PM"
}

/**
 * Audit Filters - Used for filtering audit logs
 */
export interface AuditFilters {
    action?: string[];                             // Filter by action types
    entity_type?: string[];                        // Filter by entity types (period, calculation, adjustment)
    user_id?: number[];                            // Filter by user IDs
    date_range?: {
        from: string;
        to: string;
    };
    search?: string;                               // Search in entity names, users, changes summary
}

/**
 * Audit Summary Statistics
 */
export interface AuditSummary {
    total_logs: number;
    total_changes: number;
    logs_today: number;
    logs_this_week: number;
    logs_this_month: number;
    most_active_user: string;
    most_modified_entity: string;
}

// ============================================================================
// LOANS & ADVANCES PAGE TYPES (Phase 1.5 & 1.5b)
// ============================================================================

/**
 * Employee Loan - Structured loan product for employees
 * Loan types: SSS, Pag-IBIG (government), Company, Cash Advance
 * Based on employee_loans table schema
 */
export interface EmployeeLoan {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_id: number;
    department_name: string;
    loan_type: 'sss' | 'pagibig' | 'company' | 'cash_advance';
    loan_type_label: string;                       // "SSS Loan", "Pag-IBIG Loan", etc.
    loan_type_color: string;                       // Badge color
    loan_number: string;                           // Unique identifier
    principal_amount: number;
    interest_rate: number | null;                  // Nullable for government loans
    total_amount: number;                          // Principal + interest
    monthly_amortization: number;
    number_of_installments: number;
    installments_paid: number;
    remaining_balance: number;
    loan_date: string;                             // ISO date
    start_date: string;                            // ISO date (when deductions start)
    maturity_date: string;                         // ISO date (calculated)
    status: 'active' | 'completed' | 'cancelled' | 'restructured';
    status_label: string;
    status_color: 'green' | 'blue' | 'red' | 'yellow';
    is_active: boolean;
    approved_by: string | null;
    approved_at: string | null;
    created_by: string;
    created_at: string;
    updated_by: string | null;
    updated_at: string;
}

/**
 * Employee Loan Form Data
 */
export interface EmployeeLoanFormData {
    employee_id: number;
    loan_type: 'sss' | 'pagibig' | 'company' | 'cash_advance';
    principal_amount: number;
    interest_rate?: number;
    monthly_amortization: number;
    number_of_installments: number;
    loan_date: string;
    start_date: string;
    approved_by?: number;
}

/**
 * Loan Payment - Individual payment record
 * Tracks each deduction made against a loan
 */
export interface LoanPayment {
    id: number;
    employee_loan_id: number;
    payroll_calculation_id: number;
    payroll_period_id: number;
    payroll_period_name: string;
    payment_amount: number;
    principal_payment: number;
    interest_payment: number;
    balance_after_payment: number;
    created_at: string;
    is_paid: boolean;
}

/**
 * Loans Page Props
 */
export interface PayrollLoansPageProps {
    loans: EmployeeLoan[];
    filters: {
        loan_type?: string[];
        status?: string[];
        employee_id?: number;
        department_id?: number;
    };
}

/**
 * Cash Advance - Short-term advance on paycheck
 * Based on employee_loans table (with advance-specific fields)
 */
export interface CashAdvance {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_id: number;
    department_name: string;
    advance_type: string;                          // "cash_advance", "equipment", etc.
    amount_requested: number;
    amount_approved: number | null;
    approval_status: 'pending' | 'approved' | 'rejected';
    approval_status_label: string;
    approval_status_color: string;
    approved_by: string | null;
    approved_at: string | null;
    approval_notes: string | null;
    deduction_status: 'active' | 'completed' | 'cancelled';
    deduction_status_label: string;
    remaining_balance: number;
    deduction_schedule: 'single_period' | 'installments' | 'custom';
    deduction_schedule_label: string;
    number_of_installments: number;
    installments_completed: number;
    requested_date: string;
    purpose: string;
    priority_level: 'normal' | 'urgent';
    supporting_documents?: string[];              // File paths or URLs
    created_by: string;
    created_at: string;
    updated_by: string | null;
    updated_at: string;
}

/**
 * Cash Advance Form Data
 */
export interface CashAdvanceFormData {
    employee_id: number;
    advance_type: string;
    amount_requested: number;
    purpose: string;
    requested_date: string;
    priority_level: 'normal' | 'urgent';
    supporting_documents?: File[];
}

/**
 * Cash Advance Approval Data
 */
export interface CashAdvanceApprovalData {
    advance_id: number;
    approval_status: 'approved' | 'rejected';
    amount_approved?: number;                      // If approved but for less amount
    deduction_schedule: 'single_period' | 'installments' | 'custom';
    number_of_installments?: number;
    approval_notes: string;
}

/**
 * Advance Deduction - Individual deduction per payroll period
 */
export interface AdvanceDeduction {
    id: number;
    cash_advance_id: number;
    payroll_period_id: number;
    payroll_period_name: string;
    deduction_amount: number;
    remaining_balance_after: number;
    is_deducted: boolean;
    deducted_at?: string;
}

/**
 * Advances Page Props
 */
export interface PayrollAdvancesPageProps {
    advances: CashAdvance[];
    filters: {
        approval_status?: string[];
        deduction_status?: string[];
        employee_id?: number;
        department_id?: number;
    };
}

/**
 * Loans & Advances Summary Dashboard
 */
export interface LoansAdvancesSummary {
    total_active_loans: number;
    total_loan_balance: number;
    total_loan_deductions_this_period: number;
    total_pending_advances: number;
    total_approved_advances: number;
    total_advance_deductions_this_period: number;
    highest_borrower_balance: number;
    highest_borrower_name: string;
}

