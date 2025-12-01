/**
 * Workforce Management Module Page Props and Interfaces
 * 
 * This file contains TypeScript interfaces for all Workforce Management module pages,
 * ensuring type-safe props when rendering Inertia pages from Laravel controllers.
 */

import { PaginatedData, CommonFilters } from './hr-pages';

// ============================================================================
// SUPPORTING TYPES
// ============================================================================

/**
 * Shift type classifications
 */
export type ShiftType = 'morning' | 'afternoon' | 'night' | 'graveyard' | 'custom';

/**
 * Common rotation pattern types for manufacturing
 */
export type RotationPatternType = '4x2' | '5x2' | '6x1' | 'custom';

/**
 * Shift assignment status workflow
 */
export type AssignmentStatus = 'scheduled' | 'in_progress' | 'completed' | 'cancelled';

/**
 * Days of the week
 */
export type DayOfWeek = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday';

/**
 * Schedule status types
 */
export type ScheduleStatus = 'active' | 'expired' | 'draft' | 'archived';

/**
 * Coverage level indicators
 */
export type CoverageLevel = 'optimal' | 'adequate' | 'low' | 'critical';

// ============================================================================
// CORE ENTITY INTERFACES
// ============================================================================

/**
 * Work Schedule entity
 * Defines standard or custom work schedules for employees or departments
 */
export interface WorkSchedule {
    id: number;
    name: string;
    description?: string | null;
    effective_date: string;
    expires_at?: string | null;
    status?: ScheduleStatus;
    
    // Daily shift times (nullable if day is not worked)
    monday_start?: string | null;
    monday_end?: string | null;
    tuesday_start?: string | null;
    tuesday_end?: string | null;
    wednesday_start?: string | null;
    wednesday_end?: string | null;
    thursday_start?: string | null;
    thursday_end?: string | null;
    friday_start?: string | null;
    friday_end?: string | null;
    saturday_start?: string | null;
    saturday_end?: string | null;
    sunday_start?: string | null;
    sunday_end?: string | null;
    
    // Break durations (in minutes)
    lunch_break_duration?: number | null;
    morning_break_duration?: number | null;
    afternoon_break_duration?: number | null;
    
    // Overtime settings
    overtime_threshold?: number | null; // in hours
    overtime_rate_multiplier?: number; // e.g., 1.25 for 125%
    
    // Metadata
    department_id?: number | null;
    department_name?: string;
    assigned_employees_count?: number;
    is_template?: boolean;
    created_by: number;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Employee Rotation entity
 * Defines rotation patterns (e.g., 4 days work, 2 days rest)
 */
export interface EmployeeRotation {
    id: number;
    name: string;
    description?: string | null;
    pattern_type: RotationPatternType;
    
    // Pattern definition stored as JSON
    // Example: { "work_days": 4, "rest_days": 2, "pattern": [1,1,1,1,0,0] }
    pattern_json: RotationPattern;
    
    // Department assignment
    department_id?: number | null;
    department_name?: string;
    
    // Assignment tracking
    assigned_employees_count?: number;
    is_active?: boolean;
    
    // Metadata
    created_by: number;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Rotation Pattern structure (stored as JSON in database)
 */
export interface RotationPattern {
    work_days: number;
    rest_days: number;
    pattern: number[]; // Array of 1s (work) and 0s (rest), e.g., [1,1,1,1,0,0]
    cycle_length?: number; // Total pattern length in days
    description?: string;
}

/**
 * Shift Assignment entity
 * Daily assignment of employees to specific shifts
 */
export interface ShiftAssignment {
    id: number;
    employee_id: number;
    employee_name?: string;
    employee_number?: string;
    
    schedule_id: number;
    schedule_name?: string;
    
    // Assignment details
    date: string;
    shift_start: string; // Time format HH:MM:SS
    shift_end: string; // Time format HH:MM:SS
    shift_type?: ShiftType;
    
    // Location and metadata
    location?: string | null;
    department_id?: number;
    department_name?: string;
    
    // Overtime tracking
    is_overtime: boolean;
    overtime_hours?: number;
    
    // Status
    status: AssignmentStatus;
    
    // Notes
    notes?: string | null;
    
    // Conflict detection
    has_conflict?: boolean;
    conflict_reason?: string;
    conflicting_assignment_id?: number;
    conflicting_employee_name?: string;
    conflicting_shift_date?: string;
    conflicting_shift_start?: string;
    conflicting_shift_end?: string;
    
    // Metadata
    created_by: number;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Rotation Assignment entity
 * Links employees to rotation patterns
 */
export interface RotationAssignment {
    id: number;
    employee_id: number;
    employee_name?: string;
    employee_number?: string;
    
    rotation_id: number;
    rotation_name?: string;
    rotation_pattern?: RotationPattern;
    
    start_date: string;
    end_date?: string | null;
    is_active: boolean;
    
    created_by: number;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Schedule Template entity
 * Predefined schedule templates for quick assignment
 */
export interface ScheduleTemplate {
    id: number;
    name: string;
    description?: string | null;
    shift_type: ShiftType;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
    usage_count?: number;
    shift_pattern?: ShiftPattern;
    work_days?: string[];
    rest_days?: string[];
}

/**
 * Shift Pattern structure for templates
 */
export interface ShiftPattern {
    [day: string]: {
        start_time: string;
        end_time: string;
    };
}

/**
 * Coverage Report data structure
 * Analytics for shift coverage and staffing levels
 */
export interface CoverageReport {
    date: string;
    total_shifts: number;
    assigned_shifts: number;
    unassigned_shifts: number;
    coverage_percentage: number;
    coverage_level: CoverageLevel;
    department_breakdown?: DepartmentCoverage[];
    shift_type_breakdown?: ShiftTypeCoverage[];
}

/**
 * Department-specific coverage data
 */
export interface DepartmentCoverage {
    department_id: number;
    department_name: string;
    total_shifts: number;
    assigned_shifts: number;
    coverage_percentage: number;
}

/**
 * Shift type coverage breakdown
 */
export interface ShiftTypeCoverage {
    shift_type: ShiftType;
    total_shifts: number;
    assigned_shifts: number;
    coverage_percentage: number;
}

/**
 * Employee reference for assignment modals
 */
export interface EmployeeReference {
    id: number;
    employee_number: string;
    full_name: string;
    department_id?: number;
    department_name?: string;
    position_name?: string;
    current_assignments_count?: number;
}

/**
 * Department reference
 */
export interface Department {
    id: number;
    name: string;
    code?: string;
    employee_count?: number;
}

// ============================================================================
// FILTER INTERFACES
// ============================================================================

/**
 * Schedule filters
 */
export interface ScheduleFilters extends CommonFilters {
    department_id?: number;
    status?: ScheduleStatus;
    date_from?: string;
    date_to?: string;
    is_template?: boolean;
}

/**
 * Rotation filters
 */
export interface RotationFilters extends CommonFilters {
    pattern_type?: RotationPatternType;
    department_id?: number;
    is_active?: boolean;
}

/**
 * Assignment filters
 */
export interface AssignmentFilters extends CommonFilters {
    employee_id?: number;
    department_id?: number;
    date_from?: string;
    date_to?: string;
    shift_type?: ShiftType;
    status?: AssignmentStatus;
    is_overtime?: boolean;
    has_conflict?: boolean;
}

// ============================================================================
// SUMMARY/STATISTICS INTERFACES
// ============================================================================

/**
 * Schedule summary statistics
 */
export interface ScheduleSummary {
    total_schedules: number;
    active_schedules: number;
    expired_schedules: number;
    draft_schedules: number;
    employees_assigned: number;
    templates_available: number;
}

/**
 * Rotation summary statistics
 */
export interface RotationSummary {
    total_rotations: number;
    active_patterns: number;
    employees_in_rotation: number;
    coverage_percentage: number;
}

/**
 * Assignment summary statistics
 */
export interface AssignmentSummary {
    total_assignments: number;
    todays_shifts: number;
    coverage_percentage: number;
    overtime_hours: number;
    conflicts_count: number;
    understaffed_days: number;
}

// ============================================================================
// PAGE PROPS INTERFACES
// ============================================================================

/**
 * Schedules Index page props
 */
export interface SchedulesIndexProps {
    schedules: PaginatedData<WorkSchedule> | WorkSchedule[];
    summary: ScheduleSummary;
    departments: Department[];
    filters: ScheduleFilters;
    templates?: ScheduleTemplate[];
}

/**
 * Schedule Create page props
 */
export interface ScheduleCreateProps {
    departments: Department[];
    employees: EmployeeReference[];
    templates: ScheduleTemplate[];
}

/**
 * Schedule Edit page props
 */
export interface ScheduleEditProps extends ScheduleCreateProps {
    schedule: WorkSchedule;
    assigned_employees?: EmployeeReference[];
}

/**
 * Rotations Index page props
 */
export interface RotationsIndexProps {
    rotations: PaginatedData<EmployeeRotation> | EmployeeRotation[];
    summary: RotationSummary;
    departments: Department[];
    filters: RotationFilters;
    pattern_templates?: { pattern_type: RotationPatternType; name: string; pattern: RotationPattern }[];
}

/**
 * Rotation Create page props
 */
export interface RotationCreateProps {
    departments: Department[];
    employees: EmployeeReference[];
    pattern_templates: { pattern_type: RotationPatternType; name: string; pattern: RotationPattern }[];
}

/**
 * Rotation Edit page props
 */
export interface RotationEditProps extends RotationCreateProps {
    rotation: EmployeeRotation;
    assigned_employees?: RotationAssignment[];
}

/**
 * Assignments Index page props
 */
export interface AssignmentsIndexProps {
    assignments: PaginatedData<ShiftAssignment> | ShiftAssignment[];
    summary: AssignmentSummary;
    departments: Department[];
    employees: EmployeeReference[];
    schedules: WorkSchedule[];
    filters: AssignmentFilters;
    coverage_report?: CoverageReport[];
    view_mode?: 'calendar' | 'list' | 'analytics';
}

/**
 * Assignment Create page props
 */
export interface AssignmentCreateProps {
    employees: EmployeeReference[];
    schedules: WorkSchedule[];
    departments: Department[];
    shift_templates: ScheduleTemplate[];
}

/**
 * Assignment Edit page props
 */
export interface AssignmentEditProps extends AssignmentCreateProps {
    assignment: ShiftAssignment;
}

/**
 * Bulk Assignment props
 */
export interface BulkAssignmentProps {
    employees: EmployeeReference[];
    schedules: WorkSchedule[];
    departments: Department[];
    shift_templates: ScheduleTemplate[];
    date_range?: { start: string; end: string };
}

/**
 * Coverage Analytics page props
 */
export interface CoverageAnalyticsProps {
    coverage_report: CoverageReport[];
    date_range: { start: string; end: string };
    departments: Department[];
    summary: AssignmentSummary;
}

// ============================================================================
// FORM DATA INTERFACES
// ============================================================================

/**
 * Schedule form data for create/update
 */
export interface ScheduleFormData {
    name: string;
    description?: string;
    effective_date: string;
    expires_at?: string;
    department_id?: number;
    
    // Daily shift times
    monday_start?: string;
    monday_end?: string;
    tuesday_start?: string;
    tuesday_end?: string;
    wednesday_start?: string;
    wednesday_end?: string;
    thursday_start?: string;
    thursday_end?: string;
    friday_start?: string;
    friday_end?: string;
    saturday_start?: string;
    saturday_end?: string;
    sunday_start?: string;
    sunday_end?: string;
    
    // Breaks
    lunch_break_duration?: number;
    morning_break_duration?: number;
    afternoon_break_duration?: number;
    
    // Overtime
    overtime_threshold?: number;
    overtime_rate_multiplier?: number;
    
    // Template
    is_template?: boolean;
    
    // Employee assignments
    employee_ids?: number[];
}

/**
 * Rotation form data for create/update
 */
export interface RotationFormData {
    name: string;
    description?: string;
    pattern_type: RotationPatternType;
    pattern_json: RotationPattern;
    department_id?: number;
    start_date?: string;
    end_date?: string;
}

/**
 * Assignment form data for create/update
 */
export interface AssignmentFormData {
    employee_id: number;
    schedule_id: number;
    date: string;
    shift_start: string;
    shift_end: string;
    shift_type?: ShiftType;
    location?: string;
    is_overtime?: boolean;
    notes?: string;
    override_conflict?: boolean;
    override_justification?: string;
}

/**
 * Bulk assignment form data
 */
export interface BulkAssignmentFormData {
    employee_ids: number[];
    schedule_id: number;
    date_from: string;
    date_to: string;
    shift_start: string;
    shift_end: string;
    shift_type?: ShiftType;
    location?: string;
    is_overtime?: boolean;
    notes?: string;
}
