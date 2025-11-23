/**
 * Appraisal Module Page Props and Interfaces
 *
 * This file contains TypeScript interfaces for all Appraisal module pages,
 * ensuring type-safe props when rendering Inertia pages from Laravel controllers.
 *
 * Module: Performance & Appraisal System
 * Role: HR Manager
 * Integrations: Timekeeping, Workforce Management
 */

import { CommonFilters, Employee } from './hr-pages';

// ============================================================================
// SUPPORTING TYPES & ENUMS
// ============================================================================

/**
 * Appraisal status workflow
 */
export type AppraisalStatus = 'draft' | 'in_progress' | 'completed' | 'acknowledged';

/**
 * Appraisal cycle status
 */
export type AppraisalCycleStatus = 'open' | 'closed';

/**
 * Rehire recommendation types
 */
export type RehireRecommendationType = 'eligible' | 'not_recommended' | 'review_required';

/**
 * Performance category based on score
 */
export type PerformanceCategory = 'high' | 'medium' | 'low';

/**
 * Performance trend indicator
 */
export type PerformanceTrend = 'improving' | 'stable' | 'declining';

/**
 * Status labels for display
 */
export const APPRAISAL_STATUS_LABELS: Record<AppraisalStatus, string> = {
  draft: 'Draft',
  in_progress: 'In Progress',
  completed: 'Completed',
  acknowledged: 'Acknowledged',
};

/**
 * Status colors for UI components (Tailwind classes)
 */
export const APPRAISAL_STATUS_COLORS: Record<AppraisalStatus, string> = {
  draft: 'bg-gray-100 text-gray-800',
  in_progress: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800',
  acknowledged: 'bg-teal-100 text-teal-800',
};

/**
 * Cycle status colors for UI components
 */
export const CYCLE_STATUS_COLORS: Record<AppraisalCycleStatus, string> = {
  open: 'bg-green-100 text-green-800',
  closed: 'bg-gray-100 text-gray-800',
};

/**
 * Recommendation labels for display
 */
export const RECOMMENDATION_LABELS: Record<RehireRecommendationType, string> = {
  eligible: 'Eligible for Rehire',
  not_recommended: 'Not Recommended',
  review_required: 'Requires Review',
};

/**
 * Recommendation colors for UI components
 */
export const RECOMMENDATION_COLORS: Record<RehireRecommendationType, string> = {
  eligible: 'bg-green-100 text-green-800',
  not_recommended: 'bg-red-100 text-red-800',
  review_required: 'bg-yellow-100 text-yellow-800',
};

/**
 * Performance category labels
 */
export const PERFORMANCE_CATEGORY_LABELS: Record<PerformanceCategory, string> = {
  high: 'High Performer',
  medium: 'Satisfactory',
  low: 'Needs Improvement',
};

/**
 * Performance category colors
 */
export const PERFORMANCE_CATEGORY_COLORS: Record<PerformanceCategory, string> = {
  high: 'text-green-600',
  medium: 'text-yellow-600',
  low: 'text-red-600',
};

// ============================================================================
// CORE ENTITY INTERFACES
// ============================================================================

/**
 * Appraisal Cycle entity
 * Represents a performance review period (e.g., Annual Review 2025, Mid-Year 2025)
 */
export interface AppraisalCycle {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  status: AppraisalCycleStatus;
  total_appraisals: number;
  completed_appraisals: number;
  average_score: number | null;
  created_by: string;
  created_at: string;
  updated_at: string;
}

/**
 * Single criterion score within an appraisal
 */
export interface AppraisalScore {
  id: number;
  appraisal_id: number;
  criterion: string;
  score: number;
  weight: number;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Individual appraisal entity
 * Represents a performance review for a specific employee in a cycle
 */
export interface Appraisal {
  id: number;
  employee_id: number;
  employee_name: string;
  employee_number: string;
  department_id: number;
  department_name: string;
  cycle_id: number;
  cycle_name: string;
  status: AppraisalStatus;
  status_label: string;
  status_color: string;
  overall_score: number | null;
  feedback: string | null;
  scores: AppraisalScore[];
  attendance_rate: number;
  lateness_count: number;
  violation_count: number;
  created_by: string;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Rehire recommendation entity
 * Represents the rehire eligibility determination for an employee
 */
export interface RehireRecommendation {
  id: number;
  employee_id: number;
  employee_name: string;
  employee_number: string;
  department_name: string;
  appraisal_id: number;
  cycle_name: string;
  recommendation: RehireRecommendationType;
  recommendation_label: string;
  recommendation_color: string;
  overall_score: number;
  attendance_rate: number;
  violation_count: number;
  notes: string | null;
  is_overridden: boolean;
  overridden_by: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Performance metric for an employee
 * Used in analytics and performance comparison dashboards
 */
export interface PerformanceMetric {
  employee_id: number;
  employee_name: string;
  employee_number: string;
  department_name: string;
  overall_score: number;
  attendance_rate: number;
  behavior_score: number;
  productivity_score: number;
  performance_category: PerformanceCategory;
  trend: PerformanceTrend;
}

/**
 * Department summary with statistics
 * Used in comparison and analytics views
 */
export interface DepartmentPerformanceSummary {
  id: number;
  name: string;
  average_score: number;
  total_employees: number;
  appraised_employees: number;
}

/**
 * Cycle analytics data
 * Aggregated metrics for a specific appraisal cycle
 */
export interface CycleAnalytics {
  cycle_id: number;
  total_appraisals: number;
  completed_appraisals: number;
  completion_rate: number;
  average_score: number;
  high_performers: number;
  medium_performers: number;
  low_performers: number;
  department_breakdown: DepartmentPerformanceSummary[];
}

// ============================================================================
// PAGE PROPS INTERFACES
// ============================================================================

/**
 * Props for Appraisal Cycles Index page
 * /hr/appraisals/cycles
 */
export interface AppraisalCyclesIndexProps {
  cycles: AppraisalCycle[];
  filters: {
    status: string;
    year: string;
  };
}

/**
 * Props for Appraisals Index page
 * /hr/appraisals
 */
export interface AppraisalsIndexProps {
  appraisals: Appraisal[];
  cycles: AppraisalCycle[];
  departments: Array<{ id: number; name: string }>;
  filters: {
    cycle_id: string;
    status: string;
    department_id: string;
    search: string;
  };
}

/**
 * Props for Appraisal Show/Detail page
 * /hr/appraisals/{id}
 */
export interface AppraisalShowProps {
  appraisal: Appraisal;
  employee: Employee;
  cycle: AppraisalCycle;
  attendanceData?: {
    attendance_rate: number;
    lateness_count: number;
    violation_count: number;
    monthly_attendance?: Array<{
      month: string;
      rate: number;
    }>;
  };
}

/**
 * Props for Performance Metrics Index page
 * /hr/performance-metrics
 */
export interface PerformanceMetricsIndexProps {
  metrics: PerformanceMetric[];
  departments: Array<{ id: number; name: string }>;
  summary: {
    average_score: number;
    high_performers: number;
    low_performers: number;
    completion_rate: number;
  };
  filters: {
    department_id: string;
    performance_category: string;
    date_from: string;
    date_to: string;
  };
}

/**
 * Props for Employee Performance Detail page
 * /hr/performance-metrics/{employeeId}
 */
export interface PerformanceDetailProps {
  employee: Employee;
  currentMetric: PerformanceMetric;
  historicalMetrics: Array<{
    cycle_name: string;
    overall_score: number;
    cycle_date: string;
  }>;
  departmentAverage: number;
  trend: PerformanceTrend;
  attendanceCorrelation?: {
    months: string[];
    appraisalScores: number[];
    attendanceRates: number[];
  };
}

/**
 * Props for Rehire Recommendations Index page
 * /hr/rehire-recommendations
 */
export interface RehireRecommendationsIndexProps {
  recommendations: RehireRecommendation[];
  departments: Array<{ id: number; name: string }>;
  filters: {
    recommendation: string;
    department_id: string;
    search: string;
  };
}

/**
 * Props for Rehire Recommendation Detail/Show page
 * /hr/rehire-recommendations/{id}
 */
export interface RehireRecommendationDetailProps {
  recommendation: RehireRecommendation;
  appraisal: Appraisal;
  employee: Employee;
  attendanceMetrics: {
    attendance_rate: number;
    lateness_count: number;
    violation_count: number;
  };
  overrideHistory?: Array<{
    previous_recommendation: RehireRecommendationType;
    new_recommendation: RehireRecommendationType;
    overridden_by: string;
    notes: string;
    overridden_at: string;
  }>;
}

// ============================================================================
// FORM DATA INTERFACES
// ============================================================================

/**
 * Form data for creating/editing an appraisal cycle
 */
export interface AppraisalCycleFormData {
  name: string;
  start_date: string;
  end_date: string;
  criteria: Array<{
    name: string;
    weight: number;
  }>;
}

/**
 * Form data for employee assignment to a cycle
 */
export interface EmployeeAssignmentFormData {
  cycle_id: number;
  employee_ids: number[];
  due_date: string;
  notes?: string;
}

/**
 * Form data for updating appraisal scores
 */
export interface AppraisalScoreFormData {
  criterion: string;
  score: number;
  notes: string;
}

/**
 * Form data for updating appraisal status
 */
export interface AppraisalStatusFormData {
  status: AppraisalStatus;
  notes?: string;
}

/**
 * Form data for submitting appraisal feedback
 */
export interface AppraisalFeedbackFormData {
  overall_score: number;
  feedback: string;
  scores: AppraisalScoreFormData[];
}

/**
 * Form data for overriding rehire recommendation
 */
export interface RehireOverrideFormData {
  recommendation: RehireRecommendationType;
  notes: string;
}

/**
 * Form data for bulk assignment
 */
export interface BulkAssignmentFormData {
  recommendation_ids: number[];
  action: 'approve' | 'bulk_assign';
}

// ============================================================================
// FILTER & SEARCH INTERFACES
// ============================================================================

/**
 * Filter options for appraisal lists
 */
export interface AppraisalFilters extends CommonFilters {
  cycle_id?: string;
  status?: AppraisalStatus;
  department_id?: string;
  search?: string;
}

/**
 * Filter options for performance metrics
 */
export interface PerformanceMetricsFilters extends CommonFilters {
  department_id?: string;
  performance_category?: PerformanceCategory;
  date_from?: string;
  date_to?: string;
}

/**
 * Filter options for rehire recommendations
 */
export interface RehireRecommendationFilters extends CommonFilters {
  recommendation?: RehireRecommendationType;
  department_id?: string;
  search?: string;
  is_overridden?: boolean;
}

// ============================================================================
// RESPONSE & SUMMARY INTERFACES
// ============================================================================

/**
 * Summary statistics for appraisal cycles
 */
export interface CycleSummary {
  total_cycles: number;
  open_cycles: number;
  closed_cycles: number;
  pending_appraisals: number;
  completed_appraisals: number;
}

/**
 * Summary statistics for performance metrics
 */
export interface PerformanceSummary {
  average_score: number;
  high_performers: number;
  medium_performers: number;
  low_performers: number;
  completion_rate: number;
  department_count: number;
}

/**
 * Summary statistics for rehire recommendations
 */
export interface RehireRecommendationSummary {
  total_recommendations: number;
  eligible_count: number;
  not_recommended_count: number;
  review_required_count: number;
  overridden_count: number;
}

/**
 * Standard response structure for appraisal operations
 */
export interface AppraisalResponse {
  success: boolean;
  message: string;
  data?: Record<string, unknown>;
  errors?: Record<string, string[]>;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Calculate overall score from individual criterion scores
 */
export function calculateOverallScore(scores: AppraisalScore[]): number {
  if (scores.length === 0) return 0;
  const totalWeight = scores.reduce((sum, s) => sum + s.weight, 0);
  const weightedSum = scores.reduce((sum, s) => sum + s.score * s.weight, 0);
  return totalWeight > 0 ? Math.round((weightedSum / totalWeight) * 100) / 100 : 0;
}

/**
 * Get performance category based on score
 */
export function getPerformanceCategory(score: number): PerformanceCategory {
  if (score >= 8) return 'high';
  if (score >= 5) return 'medium';
  return 'low';
}

/**
 * Get score color based on value
 */
export function getScoreColor(score: number): string {
  if (score >= 8) return 'text-green-600';
  if (score >= 5) return 'text-yellow-600';
  return 'text-red-600';
}

/**
 * Get rehire recommendation based on score and metrics
 */
export function getRecommendationFromScore(
  score: number,
  attendanceRate: number,
  violationCount: number
): RehireRecommendationType {
  if (score >= 7.5 && attendanceRate >= 90 && violationCount === 0) {
    return 'eligible';
  }
  if (score < 5 || violationCount > 3) {
    return 'not_recommended';
  }
  return 'review_required';
}

/**
 * Format performance metric for display
 */
export function formatPerformanceMetric(metric: PerformanceMetric): {
  displayScore: string;
  displayCategory: string;
  displayTrend: string;
} {
  return {
    displayScore: metric.overall_score.toFixed(2),
    displayCategory: PERFORMANCE_CATEGORY_LABELS[metric.performance_category],
    displayTrend: metric.trend.charAt(0).toUpperCase() + metric.trend.slice(1),
  };
}

/**
 * Format status for display
 */
export function formatAppraisalStatus(status: AppraisalStatus): string {
  return APPRAISAL_STATUS_LABELS[status];
}

/**
 * Format recommendation for display
 */
export function formatRecommendation(recommendation: RehireRecommendationType): string {
  return RECOMMENDATION_LABELS[recommendation];
}

/**
 * Calculate completion percentage for a cycle
 */
export function calculateCompletionPercentage(
  completed: number,
  total: number
): number {
  if (total === 0) return 0;
  return Math.round((completed / total) * 100);
}
