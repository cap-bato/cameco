/**
 * Payroll Review & Approval Page Types
 * Phase 2.4: Review and approval workflow for calculated payroll
 */

// ============================================================================
// PAYROLL REVIEW PAGE MAIN TYPES
// ============================================================================

/**
 * Main page props for Payroll Review & Approval
 */
export interface AvailablePeriod {
    id: number;
    label: string;
    status: string;
    period_start: string;
    period_end: string;
}

export interface PayrollReviewPageProps {
    payroll_period: PayrollPeriodSummary;
    summary: PayrollReviewSummary;
    departments: DepartmentBreakdown[];
    exceptions: PayrollException[];
    approval_workflow: ApprovalWorkflow;
    employee_calculations: EmployeeCalculationPreview[];
    available_periods: AvailablePeriod[];
}

/**
 * Payroll Period Summary for Review
 */
export interface PayrollPeriodSummary {
    id: number;
    name: string;
    period_type: 'semi_monthly' | 'monthly';
    start_date: string;
    end_date: string;
    pay_date: string;
    status: 'calculated' | 'approved';
    total_employees: number;
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
}

/**
 * Summary Cards Data
 */
export interface PayrollReviewSummary {
    total_employees_processed: number;
    total_gross_pay: number;
    total_statutory_deductions: number;
    total_other_deductions: number;
    total_deductions: number;
    total_net_pay: number;
    total_employer_cost: number;
    variance_from_previous: number;
    variance_percentage: string;
    previous_period_net_pay: number;
    formatted_gross_pay: string;
    formatted_deductions: string;
    formatted_net_pay: string;
    formatted_employer_cost: string;
    formatted_variance: string;
}

/**
 * Department Breakdown
 */
export interface DepartmentBreakdown {
    id: number;
    name: string;
    employee_count: number;
    total_gross_pay: number;
    total_deductions: number;
    total_net_pay: number;
    total_employer_cost: number;
    percentage_of_total: number;
    average_gross_per_employee: number;
    average_net_per_employee: number;
    formatted_gross_pay: string;
    formatted_net_pay: string;
    formatted_employer_cost: string;
}

/**
 * Payroll Exceptions/Anomalies
 */
export interface PayrollException {
    id: number;
    type: 'zero_net_pay' | 'negative_net_pay' | 'high_variance' | 'new_hire' | 'resign' | 'tax_anomaly' | 'missing_data';
    severity: 'critical' | 'warning' | 'info';
    title: string;
    description: string;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    affected_amount?: number;
    formatted_amount?: string;
    previous_value?: number;
    current_value?: number;
    variance_percentage?: string;
    action_required: boolean;
    action_description?: string;
}

/**
 * Approval Workflow Details
 */
export interface ApprovalWorkflow {
    id: number;
    payroll_period_id: number;
    current_step: number;
    total_steps: number;
    status: 'pending' | 'in_progress' | 'approved' | 'rejected';
    can_approve: boolean;
    can_reject: boolean;
    approver_role: string;
    steps: ApprovalStep[];
    rejection_reason?: string;
    rejection_date?: string;
    rejection_by?: string;
}

/**
 * Individual Approval Step
 */
export interface ApprovalStep {
    step_number: number;
    role: string;
    status: 'pending' | 'approved' | 'rejected' | 'skipped';
    status_label: string;
    status_color: 'gray' | 'yellow' | 'green' | 'red';
    description: string;
    approved_by?: string;
    approved_at?: string;
    comments?: string;
}

/**
 * Employee Calculation Preview (for listing)
 */
export interface EmployeeCalculationPreview {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    position: string;
    basic_salary: number;
    gross_pay: number;
    statutory_deductions: number;
    other_deductions: number;
    total_deductions: number;
    net_pay: number;
    has_adjustments: boolean;
    has_errors: boolean;
    error_message?: string;
    formatted_gross_pay: string;
    formatted_net_pay: string;
}

/**
 * Detailed Employee Calculation
 */
export interface EmployeeCalculationDetail {
    id: number;
    payroll_calculation_id: number;
    payroll_period_id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    position: string;
    employment_status: string;
    
    // Salary Information
    salary_type: string;
    basic_salary: number;
    daily_rate?: number;
    hourly_rate?: number;
    
    // Attendance
    scheduled_days: number;
    days_worked: number;
    days_absent: number;
    days_late: number;
    days_undertime: number;
    
    // Hours Breakdown
    regular_hours: number;
    overtime_regular_hours: number;
    overtime_restday_hours: number;
    overtime_special_holiday_hours: number;
    overtime_regular_holiday_hours: number;
    night_differential_hours: number;
    regular_holiday_hours: number;
    special_holiday_hours: number;
    
    // Earnings Breakdown
    basic_pay: number;
    overtime_pay: number;
    night_differential_pay: number;
    holiday_pay: number;
    leave_pay: number;
    allowances: Record<string, number>;
    bonuses: number;
    incentives: number;
    commissions: number;
    gross_pay: number;
    non_taxable_earnings: number;
    total_earnings: number;
    
    // Deductions
    sss_employee: number;
    philhealth_employee: number;
    pagibig_employee: number;
    withholding_tax: number;
    tardiness_deduction: number;
    undertime_deduction: number;
    absence_deduction: number;
    sss_loan: number;
    pagibig_loan: number;
    company_loan: number;
    cash_advance: number;
    other_deductions: number;
    total_statutory_deductions: number;
    total_other_deductions: number;
    total_deductions: number;
    
    // Net Pay
    net_pay: number;
    
    // Employer Costs
    sss_employer: number;
    philhealth_employer: number;
    pagibig_employer: number;
    employer_contributions: number;
    total_employer_cost: number;
    
    // YTD Totals
    ytd_gross: number;
    ytd_net_pay: number;
    ytd_tax: number;
    
    // Metadata
    has_adjustments: boolean;
    has_errors: boolean;
    error_messages?: string[];
    calculation_date: string;
    calculated_by: string;
}

/**
 * Payroll Lock Information
 */
export interface PayrollLockInfo {
    is_locked: boolean;
    locked_by?: string;
    locked_at?: string;
    lock_reason?: string;
    can_unlock: boolean;
}
