/**
 * ATS (Applicant Tracking System) Module Page Props and Interfaces
 * 
 * This file contains TypeScript interfaces for all ATS module pages,
 * ensuring type-safe props when rendering Inertia pages from Laravel controllers.
 */

import { PaginatedData, CommonFilters } from './hr-pages';

// ============================================================================
// SUPPORTING TYPES
// ============================================================================

/**
 * Job posting status types
 */
export type JobStatus = 'open' | 'closed' | 'draft';

/**
 * Application status workflow
 */
export type ApplicationStatus = 
    | 'submitted' 
    | 'shortlisted' 
    | 'interviewed' 
    | 'offered' 
    | 'hired' 
    | 'rejected' 
    | 'withdrawn';

/**
 * Interview status types
 */
export type InterviewStatus = 
    | 'scheduled' 
    | 'completed' 
    | 'cancelled' 
    | 'no_show';

/**
 * Candidate source tracking
 */
export type CandidateSource = 
    | 'referral' 
    | 'job_board' 
    | 'walk_in' 
    | 'agency' 
    | 'internal' 
    | 'facebook' 
    | 'other';

/**
 * Interview recommendation types
 */
export type InterviewRecommendation = 'hire' | 'pending' | 'reject';

/**
 * Interview location types
 */
export type InterviewLocationType = 'office' | 'video_call' | 'phone';

// ============================================================================
// CORE ENTITY INTERFACES
// ============================================================================

/**
 * Job Posting entity
 */
export interface JobPosting {
    id: number;
    title: string;
    department_id: number;
    department_name?: string;
    description: string;
    requirements: string;
    status: JobStatus;
    posted_at: string;
    closed_at?: string | null;
    applications_count?: number;
    created_by: number;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
    // Facebook Integration fields
    facebook_post_id?: string | null;
    facebook_post_url?: string | null;
    facebook_posted_at?: string | null;
    auto_post_facebook?: boolean;
}

/**
 * Candidate entity
 */
export interface Candidate {
    id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    email: string;
    phone: string;
    source: CandidateSource;
    status?: string;
    applied_at: string;
    resume_path?: string | null;
    cover_letter?: string | null;
    linkedin_url?: string | null;
    portfolio_url?: string | null;
    applications_count?: number;
    interviews_count?: number;
    notes_count?: number;
    created_at: string;
    updated_at: string;
}

/**
 * Application entity
 */
export interface Application {
    id: number;
    candidate_id: number;
    job_id: number;
    candidate_name?: string;
    candidate_email?: string;
    candidate_phone?: string;
    job_title?: string;
    status: ApplicationStatus;
    score?: number | null;
    applied_at: string;
    cover_letter?: string | null;
    resume_path?: string | null;
    interview_date?: string | null;
    interviewer_name?: string | null;
    rejection_reason?: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    candidate?: Candidate;
    job?: JobPosting;
    interviews?: Interview[];
    status_history?: ApplicationStatusHistory[];
}

/**
 * Interview entity
 */
export interface Interview {
    id: number;
    application_id: number;
    candidate_id: number;
    job_id: number;
    interviewer_id: number;
    scheduled_date: string;
    scheduled_time: string;
    duration_minutes: number;
    location_type: InterviewLocationType;
    location?: string | null;
    meeting_link?: string | null;
    status: InterviewStatus;
    score?: number | null;
    feedback?: string | null;
    recommendation?: InterviewRecommendation | null;
    strengths?: string | null;
    weaknesses?: string | null;
    technical_skills_score?: number | null;
    communication_score?: number | null;
    cultural_fit_score?: number | null;
    interviewer_notes?: string | null;
    notes?: string | null;
    completed_at?: string | null;
    cancelled_at?: string | null;
    cancellation_reason?: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    candidate?: Candidate;
    candidate_name?: string;
    job?: JobPosting;
    job_title?: string;
    interviewer_name?: string;
    application?: Application;
}

/**
 * Candidate Note entity
 */
export interface CandidateNote {
    id: number;
    candidate_id: number;
    user_id: number;
    note: string;
    is_private: boolean;
    created_by_name?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Application Status History entity
 */
export interface ApplicationStatusHistory {
    id: number;
    application_id: number;
    status: ApplicationStatus;
    changed_by: number;
    changed_by_name?: string;
    notes?: string | null;
    created_at: string;
}

/**
 * Department entity (simplified for dropdowns)
 */
export interface Department {
    id: number;
    name: string;
    code?: string;
}

/**
 * User entity (simplified for interviewer dropdowns)
 */
export interface User {
    id: number;
    name: string;
    email?: string;
}

// ============================================================================
// FILTER INTERFACES
// ============================================================================

/**
 * Job Postings filter structure
 */
export interface JobPostingFilters extends CommonFilters {
    status?: JobStatus | '';
    department_id?: number | '';
    date_from?: string;
    date_to?: string;
}

/**
 * Candidates filter structure
 */
export interface CandidateFilters extends CommonFilters {
    source?: CandidateSource | '';
    status?: string;
    date_from?: string;
    date_to?: string;
}

/**
 * Applications filter structure
 */
export interface ApplicationFilters extends CommonFilters {
    status?: ApplicationStatus | '';
    job_id?: number | '';
    source?: CandidateSource | '';
    score_min?: number;
    score_max?: number;
    date_from?: string;
    date_to?: string;
}

/**
 * Interviews filter structure
 */
export interface InterviewFilters extends CommonFilters {
    status?: InterviewStatus | '';
    job_id?: number | '';
    interviewer_id?: number | '';
    date_from?: string;
    date_to?: string;
}

/**
 * Hiring Pipeline filter structure
 */
export interface HiringPipelineFilters extends CommonFilters {
    job_id?: number | '';
    source?: CandidateSource | '';
    date_from?: string;
    date_to?: string;
}

// ============================================================================
// SUMMARY & STATISTICS INTERFACES
// ============================================================================

/**
 * Job Postings summary statistics
 */
export interface JobPostingSummary {
    total_jobs: number;
    open_jobs: number;
    closed_jobs: number;
    draft_jobs: number;
    total_applications: number;
}

/**
 * Candidates summary statistics
 */
export interface CandidateSummary {
    total_candidates: number;
    new_candidates: number;
    in_process: number;
    interviewed: number;
    hired: number;
}

/**
 * Applications summary statistics
 */
export interface ApplicationSummary {
    total_applications: number;
    submitted: number;
    shortlisted: number;
    interviewed: number;
    offered: number;
    hired: number;
    rejected: number;
}

/**
 * Interviews summary statistics
 */
export interface InterviewSummary {
    total_interviews: number;
    scheduled: number;
    completed: number;
    today: number;
    this_week: number;
    cancelled: number;
}

/**
 * Hiring Pipeline summary statistics
 */
export interface HiringPipelineSummary {
    total_candidates: number;
    active_applications: number;
    interviews_this_week: number;
    offers_pending: number;
    hires_this_month: number;
}

/**
 * Pipeline stage data for Kanban view
 */
export interface PipelineStage {
    status: ApplicationStatus;
    count: number;
    applications: Application[];
}

// ============================================================================
// PAGE PROPS INTERFACES
// ============================================================================

/**
 * Job Postings Index page props
 */
export interface JobPostingsIndexProps {
    jobPostings: PaginatedData<JobPosting>;
    filters: JobPostingFilters;
    summary: JobPostingSummary;
    departments: Department[];
}

/**
 * Job Posting Create page props
 */
export interface JobPostingCreateProps {
    departments: Department[];
}

/**
 * Job Posting Edit page props
 */
export interface JobPostingEditProps extends JobPostingCreateProps {
    jobPosting: JobPosting;
}

/**
 * Candidates Index page props
 */
export interface CandidatesIndexProps {
    candidates: PaginatedData<Candidate>;
    filters: CandidateFilters;
    summary: CandidateSummary;
}

/**
 * Candidate Show page props
 */
export interface CandidateShowProps {
    candidate: Candidate;
    applications: Application[];
    interviews: Interview[];
    notes: CandidateNote[];
    stats: {
        total_applications: number;
        total_interviews: number;
        average_score?: number;
    };
}

/**
 * Applications Index page props
 */
export interface ApplicationsIndexProps {
    applications: PaginatedData<Application>;
    filters: ApplicationFilters;
    summary: ApplicationSummary;
    jobPostings: JobPosting[];
}

/**
 * Application Show page props
 */
export interface ApplicationShowProps {
    application: Application;
    candidate: Candidate;
    job: JobPosting;
    interviews: Interview[];
    statusHistory: ApplicationStatusHistory[];
    notes: CandidateNote[];
}

/**
 * Interviews Index page props
 */
export interface InterviewsIndexProps {
    interviews: PaginatedData<Interview>;
    filters: InterviewFilters;
    summary: InterviewSummary;
    jobPostings: JobPosting[];
    interviewers: User[];
}

/**
 * Hiring Pipeline page props
 */
export interface HiringPipelineProps {
    pipeline: PipelineStage[];
    summary: HiringPipelineSummary;
    filters: HiringPipelineFilters;
    jobPostings: JobPosting[];
    view?: 'kanban' | 'list';
}

// ============================================================================
// FORM DATA INTERFACES
// ============================================================================

/**
 * Job Posting form data
 */
export interface JobPostingFormData {
    title: string;
    department_id: number | null;
    description: string;
    requirements: string;
    status: JobStatus;
    closed_at?: string | null;
    auto_post_facebook?: boolean;
}

/**
 * Candidate form data
 */
export interface CandidateFormData {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    source: CandidateSource;
    resume?: File | null;
    resume_path?: string | null;
    cover_letter?: string;
    linkedin_url?: string;
    portfolio_url?: string;
}

/**
 * Interview schedule form data
 */
export interface InterviewScheduleFormData {
    application_id: number;
    candidate_id: number;
    job_id: number;
    interviewer_id: number;
    scheduled_date: string;
    scheduled_time: string;
    duration_minutes: number;
    location_type: InterviewLocationType;
    location?: string;
    meeting_link?: string;
    notes?: string;
    sync_google_calendar?: boolean;
}

/**
 * Interview feedback form data
 */
export interface InterviewFeedbackFormData {
    score: number;
    recommendation: InterviewRecommendation;
    feedback: string;
    strengths?: string;
    weaknesses?: string;
    technical_skills_score?: number;
    communication_score?: number;
    cultural_fit_score?: number;
    interviewer_notes?: string;
}

/**
 * Application status update form data
 */
export interface ApplicationStatusUpdateFormData {
    status: ApplicationStatus;
    notes?: string;
}

/**
 * Application rejection form data
 */
export interface ApplicationRejectionFormData {
    rejection_reason: string;
}

/**
 * Candidate note form data
 */
export interface CandidateNoteFormData {
    note: string;
    is_private: boolean;
}

// ============================================================================
// CALENDAR VIEW INTERFACES
// ============================================================================

/**
 * Calendar event for interview display
 */
export interface CalendarEvent {
    id: number;
    interview_id: number;
    title: string;
    candidate_name: string;
    job_title: string;
    start: Date;
    end: Date;
    status: InterviewStatus;
    location_type: InterviewLocationType;
    meeting_link?: string;
}

/**
 * Calendar view type
 */
export type CalendarView = 'month' | 'week' | 'day';

/**
 * Calendar date range
 */
export interface CalendarDateRange {
    start: Date;
    end: Date;
}
