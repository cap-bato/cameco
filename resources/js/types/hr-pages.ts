/**
 * HR Module Page Props and Interfaces
 * 
 * This file contains TypeScript interfaces for all HR module pages,
 * ensuring type-safe props when rendering Inertia pages from Laravel controllers.
 */

// ============================================================================
// COMMON TYPES & UTILITIES
// ============================================================================

/**
 * Generic paginated data structure matching Laravel paginated responses
 */
export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

/**
 * Common filter structure for list pages
 */
export interface CommonFilters {
    search?: string;
    status?: string;
    [key: string]: string | number | boolean | undefined;
}

/**
 * Common response structure for form submissions
 */
export interface FormResponse {
    success: boolean;
    message: string;
    data?: Record<string, unknown>;
    errors?: Record<string, string[]>;
}

// ============================================================================
// EMPLOYEE MANAGEMENT TYPES
// ============================================================================

export interface Employee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    middle_name?: string;
    full_name?: string;
    date_of_birth?: string;
    gender?: 'male' | 'female' | 'other';
    email: string;
    phone?: string;
    phone_alt?: string;
    national_id?: string;
    department_id: number;
    position_id: number;
    supervisor_id?: number | null;
    employment_type?: 'permanent' | 'contract' | 'temporary' | 'part-time';
    date_employed: string;
    salary_grade?: string;
    status: 'active' | 'inactive' | 'on_leave' | 'terminated' | 'retired';
    address?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
    profile?: EmployeeProfile;
    department?: Department;
    position?: Position;
    supervisor?: Employee;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface EmployeeProfile {
    id: number;
    employee_id: number;
    first_name: string;
    last_name: string;
    middle_name?: string;
    date_of_birth?: string;
    gender?: 'male' | 'female' | 'other';
    phone?: string;
    phone_alt?: string;
    address?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
    marital_status?: string;
    children_count?: number;
    next_of_kin?: string;
    next_of_kin_phone?: string;
    national_id?: string;
    passport_number?: string;
    created_at: string;
    updated_at: string;
}

export interface EmployeeIndexProps {
    employees: PaginatedData<Employee>;
    filters: {
        search?: string;
        department_id?: number | null;
        status?: string;
        employment_type?: string;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
    employment_types: Array<{ value: string; label: string }>;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

export interface EmployeeCreateProps {
    departments: Array<{ id: number; name: string }>;
    positions: Array<{ id: number; name: string; department_id?: number }>;
    supervisors: Array<{
        id: number;
        employee_number: string;
        full_name: string;
    }>;
    employment_types: Array<{ value: string; label: string }>;
    canCreate: boolean;
}

export interface EmployeeEditProps extends EmployeeCreateProps {
    employee: Employee;
    canUpdate: boolean;
    canDelete: boolean;
}

export interface EmployeeShowProps {
    employee: Employee & {
        profile?: EmployeeProfile;
        dependents?: EmployeeDependent[];
        remarks?: EmployeeRemark[];
        leave_requests?: LeaveRequest[];
    };
    canEdit: boolean;
    canDelete: boolean;
    canViewFamily: boolean;
    canViewDocuments: boolean;
}

export interface EmployeeDependent {
    id: number;
    employee_id: number;
    name: string;
    relationship: string;
    date_of_birth?: string;
    created_at: string;
    updated_at: string;
}

export interface EmployeeRemark {
    id: number;
    employee_id: number;
    remark: string;
    marked_by: number;
    created_at: string;
    updated_at: string;
    marked_by_user?: User;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    [key: string]: string | number | undefined;
}

// ============================================================================
// DEPARTMENT MANAGEMENT TYPES
// ============================================================================

export interface Department {
    id: number;
    name: string;
    code?: string;
    description?: string;
    parent_id?: number | null;
    manager_id?: number | null;
    status: 'active' | 'inactive' | 'archived';
    employee_count?: number;
    parent?: Department;
    manager?: Employee;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface DepartmentIndexProps {
    departments: PaginatedData<Department>;
    filters: {
        search?: string;
        status?: string;
    };
    statuses: Array<{ value: string; label: string }>;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

export interface DepartmentCreateProps {
    managers: Array<{
        id: number;
        employee_number: string;
        full_name: string;
    }>;
    parent_departments: Array<{ id: number; name: string }>;
    canCreate: boolean;
}

export interface DepartmentEditProps extends DepartmentCreateProps {
    department: Department;
    canUpdate: boolean;
    canDelete: boolean;
}

// ============================================================================
// POSITION MANAGEMENT TYPES
// ============================================================================

export interface Position {
    id: number;
    title: string;
    code?: string;
    description?: string;
    department_id: number;
    level?: string;
    salary_band?: string;
    reports_to_position_id?: number | null;
    employee_count?: number;
    status: 'active' | 'inactive' | 'archived';
    department?: Department;
    parent_position?: Position;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export interface PositionIndexProps {
    positions: PaginatedData<Position>;
    filters: {
        search?: string;
        department_id?: number | null;
        status?: string;
    };
    departments: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

export interface PositionCreateProps {
    departments: Array<{ id: number; name: string }>;
    positions: Array<{ id: number; title: string }>;
    salary_bands: string[];
    levels: string[];
    canCreate: boolean;
}

export interface PositionEditProps extends PositionCreateProps {
    position: Position;
    canUpdate: boolean;
    canDelete: boolean;
}

// ============================================================================
// LEAVE MANAGEMENT TYPES (ISSUE-5)
// ============================================================================

export type LeaveType = 'VL' | 'SL' | 'EL' | 'ML' | 'PL' | 'BL' | 'SP' | 'HAM' | 'HPM';
export type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';
export type PartialType = 'none' | 'first_half' | 'second_half';

export interface LeaveRequest {
    id: number;
    employee_id: number;
    leave_type: LeaveType;
    start_date: string;
    end_date: string;
    partial_day?: PartialType;
    number_of_days: number;
    reason?: string;
    status: LeaveStatus;
    approved_by?: number | null;
    approval_date?: string | null;
    rejection_reason?: string | null;
    created_at: string;
    updated_at: string;
    employee?: Employee;
    approved_by_user?: User;
}

export interface LeaveBalance {
    id: number;
    employee_id: number;
    leave_type: LeaveType;
    year: number;
    opening_balance: number;
    earned: number;
    used: number;
    carried_forward: number;
    remaining: number;
    created_at: string;
    updated_at: string;
    employee?: Employee;
}

export interface LeavePolicy {
    id: number;
    leave_type: LeaveType;
    description?: string;
    annual_entitlement: number;
    carry_forward_allowed: boolean;
    max_carry_forward: number;
    requires_approval: boolean;
    applies_to_all: boolean;
    created_at: string;
    updated_at: string;
}

export interface LeaveFilters {
    search?: string;
    status?: LeaveStatus;
    leave_type?: LeaveType;
    employee_id?: number;
    start_date?: string;
    end_date?: string;
    approval_status?: 'pending' | 'approved' | 'rejected';
}

export interface LeaveStatistics {
    total_pending: number;
    total_approved: number;
    total_rejected: number;
    employees_on_leave: number;
    leave_days_used: number;
    leave_days_remaining: number;
}

export interface LeaveRequestsPageProps {
    requests: PaginatedData<LeaveRequest>;
    filters: LeaveFilters;
    statistics: LeaveStatistics;
    leave_types: Array<{ value: LeaveType; label: string }>;
    statuses: Array<{ value: LeaveStatus; label: string }>;
    employees?: Array<{ id: number; full_name: string }>;
    canApprove: boolean;
    canReject: boolean;
}

export interface EmployeeLeaveBalance {
    employee: Employee;
    balances: Array<{
        leave_type: LeaveType;
        year: number;
        opening_balance: number;
        earned: number;
        used: number;
        remaining: number;
        carried_forward: number;
    }>;
}

export interface LeaveBalancesPageProps {
    balances: EmployeeLeaveBalance[];
    employees: Array<{ id: number; full_name: string }>;
    years: number[];
    selected_year: number;
    selected_employee_id?: number;
    leave_types: Array<{ value: LeaveType; label: string }>;
}

export interface LeavePoliciesPageProps {
    policies: LeavePolicy[];
    leave_types: Array<{ value: LeaveType; label: string }>;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

// ============================================================================
// REPORTS & ANALYTICS TYPES
// ============================================================================

export interface EmployeeSummary {
    total_employees: number;
    active_employees: number;
    inactive_employees: number;
    on_leave_employees: number;
    terminated_employees: number;
    average_tenure_years: number;
}

export interface DepartmentStats {
    department_id: number;
    department_name: string;
    employee_count: number;
    percentage: number;
}

export interface EmployeeReportsPageProps {
    summary: EmployeeSummary;
    by_department: DepartmentStats[];
    by_status: Array<{
        status: string;
        count: number;
        percentage: number;
    }>;
    recent_hires: Employee[];
    headcount_trend?: Array<{
        month: string;
        count: number;
    }>;
    can_export: boolean;
}

export interface LeaveSummary {
    total_pending_requests: number;
    total_approved_requests: number;
    total_rejected_requests: number;
    employees_on_leave: number;
    leave_days_used_this_year: number;
    leave_days_remaining_average: number;
}

export interface LeaveReportsPageProps {
    summary: LeaveSummary;
    by_type: Array<{
        leave_type: LeaveType;
        count: number;
        percentage: number;
    }>;
    by_status: Array<{
        status: LeaveStatus;
        count: number;
        percentage: number;
    }>;
    by_month?: Array<{
        month: string;
        approved: number;
        pending: number;
        rejected: number;
    }>;
    top_users?: Array<{
        employee: Employee;
        days_used: number;
    }>;
    can_export: boolean;
}

export interface ChartData {
    labels: string[];
    datasets: Array<{
        label: string;
        data: number[];
        backgroundColor?: string;
        borderColor?: string;
        fill?: boolean;
    }>;
}

export interface DashboardMetrics {
    total_headcount: number;
    monthly_headcount_change: number;
    headcount_change_percentage: number;
    pending_leaves: number;
    employees_on_leave: number;
    pending_approvals: number;
    documents_generated: number;
    new_hires_this_month: number;
    terminations_this_month: number;
}

export interface AnalyticsPageProps {
    dashboard_metrics: DashboardMetrics;
    charts: {
        employees_by_department: ChartData;
        leave_usage_by_month: ChartData;
        approval_trends: ChartData;
        headcount_trend: ChartData;
    };
    date_range?: {
        start_date: string;
        end_date: string;
    };
    can_export: boolean;
    last_updated: string;
}

// ============================================================================
// DOCUMENT MANAGEMENT TYPES (ISSUE-6)
// ============================================================================

export type DocumentType =
    | 'employment_contract'
    | 'certificate_of_employment'
    | 'leave_slip'
    | 'memorandum'
    | 'job_offer'
    | 'excuse_slip';

export type DocumentStatus = 'draft' | 'generated' | 'sent' | 'archived';

export interface DocumentTemplate {
    id: number;
    name: string;
    document_type: DocumentType;
    content: string;
    variables?: string[]; // e.g., ['{{employee.name}}', '{{employee.id}}']
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

export interface GeneratedDocument {
    id: number;
    template_id: number;
    employee_id: number;
    file_name: string;
    file_path: string;
    document_type: DocumentType;
    status: DocumentStatus;
    generated_at: string;
    sent_at?: string | null;
    generated_by: number;
    created_at: string;
    updated_at: string;
    template?: DocumentTemplate;
    employee?: Employee;
    generated_by_user?: User;
}

export interface DocumentTemplateIndexProps {
    templates: PaginatedData<DocumentTemplate>;
    filters: {
        search?: string;
        document_type?: DocumentType;
        status?: string;
    };
    document_types: Array<{ value: DocumentType; label: string }>;
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
}

export interface DocumentTemplateCreateProps {
    document_types: Array<{ value: DocumentType; label: string }>;
    available_variables: Record<DocumentType, string[]>;
    canCreate: boolean;
}

export interface DocumentTemplateEditProps extends DocumentTemplateCreateProps {
    template: DocumentTemplate;
    canUpdate: boolean;
    canDelete: boolean;
}

export interface DocumentGeneratePageProps {
    template: DocumentTemplate;
    employees: Array<{
        id: number;
        employee_number: string;
        full_name: string;
    }>;
    template_variables: string[];
    can_generate: boolean;
}

export interface DocumentListPageProps {
    documents: PaginatedData<GeneratedDocument>;
    filters: {
        search?: string;
        document_type?: DocumentType;
        status?: DocumentStatus;
        employee_id?: number;
    };
    document_types: Array<{ value: DocumentType; label: string }>;
    statuses: Array<{ value: DocumentStatus; label: string }>;
    employees: Array<{ id: number; full_name: string }>;
    can_download: boolean;
    can_delete: boolean;
}

// ============================================================================
// PAGE-SPECIFIC COMPOSITE TYPES
// ============================================================================

/**
 * Composite type for pages that show employee details with related data
 */
export interface EmployeeDetailPageData {
    employee: Employee;
    profile: EmployeeProfile;
    dependents: EmployeeDependent[];
    remarks: EmployeeRemark[];
    leave_balances: LeaveBalance[];
    leave_requests: LeaveRequest[];
    documents: GeneratedDocument[];
}

/**
 * Composite type for dashboard showing HR overview
 */
export interface HRDashboardPageProps {
    metrics: {
        total_employees: number;
        departments: number;
        positions: number;
        pending_approvals: number;
        employees_on_leave: number;
    };
    recent_activities: Array<{
        id: number;
        type: string;
        description: string;
        created_at: string;
    }>;
    charts: ChartData[];
    quick_actions: Array<{
        label: string;
        href: string;
        icon: string;
    }>;
}

// ============================================================================
// SHARED COMPONENT PROPS
// ============================================================================

/**
 * Common breadcrumb structure for all HR pages
 */
export interface HRPageBreadcrumb {
    label: string;
    href?: string;
}

/**
 * Common page header props
 */
export interface HRPageHeaderProps {
    title: string;
    description?: string;
    breadcrumbs: HRPageBreadcrumb[];
    action?: {
        label: string;
        href: string;
        variant?: 'default' | 'outline' | 'secondary';
    };
}

/**
 * Common table props for list pages
 */
export interface HRTableProps<T> {
    columns: Array<{
        key: keyof T;
        label: string;
        sortable?: boolean;
        render?: (value: unknown, row: T) => React.ReactNode;
    }>;
    data: T[];
    isLoading?: boolean;
    onSort?: (key: keyof T, direction: 'asc' | 'desc') => void;
    onRowClick?: (row: T) => void;
    actions?: Array<{
        label: string;
        icon?: string;
        href?: string;
        onClick?: (row: T) => void;
        variant?: 'default' | 'outline' | 'destructive';
    }>;
}

/**
 * Common filter props for list pages
 */
export interface HRFilterProps {
    filters: CommonFilters;
    onFilterChange: (filters: CommonFilters) => void;
    onReset?: () => void;
    showAdvanced?: boolean;
}

/**
 * Export request for reports
 */
export interface ExportRequest {
    format: 'csv' | 'excel' | 'pdf';
    filters?: CommonFilters;
    columns?: string[];
}
