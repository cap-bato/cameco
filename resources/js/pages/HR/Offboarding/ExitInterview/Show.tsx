import React, { useState, useMemo, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    AlertCircle,
    CheckCircle,
    Star,
    ChevronUp,
    ChevronDown,
    Save,
    Send,
} from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

interface EmployeeInfo {
    id: number;
    employee_number: string;
    name: string;
    position: string;
    department: string;
    email: string;
}

interface CaseData {
    id: number;
    case_number: string;
    status: string;
    last_working_day: string;
    employee: EmployeeInfo;
}

interface InterviewData {
    id: number;
    status: string;
    reason_for_leaving: string | null;
    overall_satisfaction: number | null;
    work_environment_rating: number | null;
    management_rating: number | null;
    compensation_rating: number | null;
    career_growth_rating: number | null;
    work_life_balance_rating: number | null;
    liked_most: string | null;
    liked_least: string | null;
    suggestions_for_improvement: string | null;
    would_recommend_company: boolean | null;
    would_consider_returning: boolean | null;
    questions_responses?: Record<string, unknown>;
}

interface ExitInterviewShowProps {
    case: CaseData;
    interview: InterviewData;
    isCompleted: boolean;
}

interface ExitInterviewFormData {
    reason_for_leaving: string;
    overall_satisfaction: number | null;
    work_environment_rating: number | null;
    management_rating: number | null;
    compensation_rating: number | null;
    career_growth_rating: number | null;
    work_life_balance_rating: number | null;
    liked_most: string;
    liked_least: string;
    suggestions_for_improvement: string;
    would_recommend_company: boolean | null;
    would_consider_returning: boolean | null;
}

// ============================================================================
// Helper Functions
// ============================================================================

const getRatingLabel = (rating: number | null): string => {
    if (!rating) return 'No rating';
    const labels: Record<number, string> = {
        1: 'Very Dissatisfied',
        2: 'Dissatisfied',
        3: 'Neutral',
        4: 'Satisfied',
        5: 'Very Satisfied',
    };
    return labels[rating] || '';
};



const calculateProgress = (formData: ExitInterviewFormData): number => {
    const requiredFields = [
        'reason_for_leaving',
        'overall_satisfaction',
        'work_environment_rating',
        'management_rating',
        'compensation_rating',
        'career_growth_rating',
        'work_life_balance_rating',
        'liked_most',
        'liked_least',
        'would_recommend_company',
        'would_consider_returning',
    ];

    const filledFields = requiredFields.filter((field) => {
        const value = formData[field as keyof ExitInterviewFormData];
        return value !== null && value !== undefined && value !== '';
    }).length;

    return Math.round((filledFields / requiredFields.length) * 100);
};

// ============================================================================
// Star Rating Component
// ============================================================================

interface StarRatingProps {
    value: number | null;
    onChange: (value: number) => void;
    disabled?: boolean;
}

function StarRating({ value, onChange, disabled = false }: StarRatingProps) {
    const [hoverValue, setHoverValue] = useState<number | null>(null);

    return (
        <div className="flex items-center gap-2">
            <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        disabled={disabled}
                        className={`transition-colors ${
                            disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:scale-110'
                        }`}
                        onClick={() => !disabled && onChange(star)}
                        onMouseEnter={() => !disabled && setHoverValue(star)}
                        onMouseLeave={() => setHoverValue(null)}
                    >
                        <Star
                            size={24}
                            className={`${
                                (hoverValue || value || 0) >= star
                                    ? 'fill-yellow-400 text-yellow-400'
                                    : 'text-gray-300'
                            }`}
                        />
                    </button>
                ))}
            </div>
            {value && (
                <Badge variant="secondary">{getRatingLabel(value)}</Badge>
            )}
        </div>
    );
}

// ============================================================================
// Section Component
// ============================================================================

interface SectionProps {
    title: string;
    isExpanded: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}

function Section({ title, isExpanded, onToggle, children }: SectionProps) {
    return (
        <div className="border border-gray-200 rounded-lg overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
            >
                <div className="flex items-center gap-3">
                    {isExpanded ? (
                        <ChevronUp size={20} className="text-gray-600" />
                    ) : (
                        <ChevronDown size={20} className="text-gray-600" />
                    )}
                    <h3 className="font-semibold text-gray-900">{title}</h3>
                </div>
            </button>
            {isExpanded && (
                <div className="p-4 border-t border-gray-200 space-y-4 bg-white">
                    {children}
                </div>
            )}
        </div>
    );
}

// ============================================================================
// Main Component
// ============================================================================

export default function ExitInterviewShow({
    case: caseData,
    interview: initialInterview,
    isCompleted,
}: ExitInterviewShowProps) {
    // Form state
    const [formData, setFormData] = useState<ExitInterviewFormData>({
        reason_for_leaving: initialInterview.reason_for_leaving || '',
        overall_satisfaction: initialInterview.overall_satisfaction || null,
        work_environment_rating: initialInterview.work_environment_rating || null,
        management_rating: initialInterview.management_rating || null,
        compensation_rating: initialInterview.compensation_rating || null,
        career_growth_rating: initialInterview.career_growth_rating || null,
        work_life_balance_rating: initialInterview.work_life_balance_rating || null,
        liked_most: initialInterview.liked_most || '',
        liked_least: initialInterview.liked_least || '',
        suggestions_for_improvement: initialInterview.suggestions_for_improvement || '',
        would_recommend_company: initialInterview.would_recommend_company,
        would_consider_returning: initialInterview.would_consider_returning,
    });

    const [expandedSections, setExpandedSections] = useState<Set<string>>(
        new Set(['welcome', 'basic-info', 'ratings'])
    );
    const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
    const [submitConfirm, setSubmitConfirm] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Calculate progress
    const progress = useMemo(() => calculateProgress(formData), [formData]);

    // Toggle section (readonly type for Inertia props)
    const pageProps = usePage().props as unknown as { csrf_token?: string };

    const toggleSection = (section: string) => {
        const newSections = new Set(expandedSections);
        if (newSections.has(section)) {
            newSections.delete(section);
        } else {
            newSections.add(section);
        }
        setExpandedSections(newSections);
    };

    // Update form field
    const handleFieldChange = useCallback(
        (field: keyof ExitInterviewFormData, value: string | number | boolean | null) => {
            setFormData((prev) => ({
                ...prev,
                [field]: value,
            }));
            // Clear error for this field when user starts editing
            if (errors[field]) {
                setErrors((prev) => {
                    const newErrors = { ...prev };
                    delete newErrors[field];
                    return newErrors;
                });
            }
        },
        [errors]
    );

    // Validate form
    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!formData.reason_for_leaving || formData.reason_for_leaving.length < 10) {
            newErrors.reason_for_leaving = 'Reason for leaving must be at least 10 characters';
        }
        if (!formData.overall_satisfaction) {
            newErrors.overall_satisfaction = 'Overall satisfaction rating is required';
        }
        if (!formData.work_environment_rating) {
            newErrors.work_environment_rating = 'Work environment rating is required';
        }
        if (!formData.management_rating) {
            newErrors.management_rating = 'Management rating is required';
        }
        if (!formData.compensation_rating) {
            newErrors.compensation_rating = 'Compensation rating is required';
        }
        if (!formData.career_growth_rating) {
            newErrors.career_growth_rating = 'Career growth rating is required';
        }
        if (!formData.work_life_balance_rating) {
            newErrors.work_life_balance_rating = 'Work-life balance rating is required';
        }
        if (!formData.liked_most || formData.liked_most.length < 10) {
            newErrors.liked_most = 'Please mention what you liked most (minimum 10 characters)';
        }
        if (!formData.liked_least || formData.liked_least.length < 10) {
            newErrors.liked_least = 'Please mention what you liked least (minimum 10 characters)';
        }
        if (formData.would_recommend_company === null) {
            newErrors.would_recommend_company = 'Please indicate if you would recommend the company';
        }
        if (formData.would_consider_returning === null) {
            newErrors.would_consider_returning = 'Please indicate if you would consider returning';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Save draft
    const handleSaveDraft = useCallback(async () => {
        setSaveStatus('saving');
        try {
            // Save draft via AJAX (without submitting)
            const response = await fetch(`/hr/exit-interview/${caseData.id}/draft`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': pageProps.csrf_token || '',
                },
                body: JSON.stringify(formData),
            });

            if (response.ok) {
                setSaveStatus('saved');
                setTimeout(() => setSaveStatus('idle'), 2000);
            }
        } catch {
            setSaveStatus('idle');
        }
    }, [formData, caseData.id, pageProps]);

    // Handle submit
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            // Expand sections with errors
            const newSections = new Set(expandedSections);
            if (errors.reason_for_leaving) newSections.add('basic-info');
            if (errors.overall_satisfaction) newSections.add('ratings');
            if (errors.liked_most || errors.liked_least) newSections.add('feedback');
            if (errors.would_recommend_company) newSections.add('intentions');
            setExpandedSections(newSections);
            return;
        }

        // Submit via Inertia router
        // @ts-expect-error - Inertia type compatibility
        router.post(`/hr/exit-interview/${caseData.id}/submit`, formData, {
            onFinish: () => {
                setSubmitConfirm(false);
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`Exit Interview - ${caseData.employee.name}`} />

            <div className="max-w-4xl mx-auto py-8 px-4 space-y-8">
                {/* Completed Status */}
                {isCompleted && (
                    <div className="bg-green-50 border border-green-200 rounded-lg p-4 flex items-start gap-3">
                        <CheckCircle className="text-green-600 mt-1 flex-shrink-0" size={24} />
                        <div>
                            <h3 className="font-semibold text-green-900">Exit Interview Completed</h3>
                            <p className="text-green-800 text-sm">Your exit interview has been successfully submitted.</p>
                        </div>
                    </div>
                )}

                {/* Welcome Section */}
                <Section
                    title="📋 Exit Interview"
                    isExpanded={expandedSections.has('welcome')}
                    onToggle={() => toggleSection('welcome')}
                >
                    <div className="space-y-4">
                        <p className="text-gray-700">
                            Thank you for your service at our organization. We would like to gather your feedback through this exit interview. 
                            Your honest responses will help us improve our workplace environment and support systems.
                        </p>
                        <p className="text-gray-700">
                            Your responses are confidential and will only be reviewed by HR management. Estimated time to complete: 10-15 minutes.
                        </p>
                        <div className="pt-4 border-t border-gray-200">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-gray-700">Progress</span>
                                <span className="text-sm font-semibold text-gray-900">{progress}%</span>
                            </div>
                            <div className="mt-2 w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div
                                    className="bg-blue-600 h-full transition-all duration-300"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </Section>

                {/* Employee Information */}
                <Section
                    title="👤 Your Information"
                    isExpanded={expandedSections.has('employee-info')}
                    onToggle={() => toggleSection('employee-info')}
                >
                    <div className="grid grid-cols-2 gap-6">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Employee Number</label>
                            <input
                                type="text"
                                value={caseData.employee.employee_number}
                                disabled
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input
                                type="text"
                                value={caseData.employee.name}
                                disabled
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Position</label>
                            <input
                                type="text"
                                value={caseData.employee.position}
                                disabled
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <input
                                type="text"
                                value={caseData.employee.department}
                                disabled
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                            />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-1">Last Working Day</label>
                            <input
                                type="text"
                                value={new Date(caseData.last_working_day).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                                disabled
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500"
                            />
                        </div>
                    </div>
                </Section>

                {!isCompleted && (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Basic Information Section */}
                        <Section
                            title="📝 Reason for Leaving"
                            isExpanded={expandedSections.has('basic-info')}
                            onToggle={() => toggleSection('basic-info')}
                        >
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Why are you leaving? <span className="text-red-600">*</span>
                                    </label>
                                    <textarea
                                        value={formData.reason_for_leaving}
                                        onChange={(e) => handleFieldChange('reason_for_leaving', e.target.value)}
                                        disabled={isCompleted}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                                            errors.reason_for_leaving
                                                ? 'border-red-500 focus:ring-red-500'
                                                : 'border-gray-300 focus:ring-blue-500'
                                        }`}
                                        rows={4}
                                        placeholder="Please provide details about your reason for leaving..."
                                    />
                                    <div className="flex justify-between mt-2">
                                        <p className="text-xs text-gray-500">Minimum 10 characters required</p>
                                        <p className={`text-xs font-medium ${
                                            formData.reason_for_leaving.length >= 1000 ? 'text-red-600' : 'text-gray-500'
                                        }`}>
                                            {formData.reason_for_leaving.length}/1000
                                        </p>
                                    </div>
                                    {errors.reason_for_leaving && (
                                        <p className="text-sm text-red-600 mt-1 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.reason_for_leaving}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Section>

                        {/* Ratings Section */}
                        <Section
                            title="⭐ Satisfaction Ratings"
                            isExpanded={expandedSections.has('ratings')}
                            onToggle={() => toggleSection('ratings')}
                        >
                            <div className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Overall Satisfaction <span className="text-red-600">*</span>
                                    </label>
                                    <StarRating
                                        value={formData.overall_satisfaction}
                                        onChange={(value) => handleFieldChange('overall_satisfaction', value)}
                                        disabled={isCompleted}
                                    />
                                    {errors.overall_satisfaction && (
                                        <p className="text-sm text-red-600 mt-1 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.overall_satisfaction}
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            Work Environment <span className="text-red-600">*</span>
                                        </label>
                                        <StarRating
                                            value={formData.work_environment_rating}
                                            onChange={(value) => handleFieldChange('work_environment_rating', value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.work_environment_rating && (
                                            <p className="text-sm text-red-600 mt-1">
                                                {errors.work_environment_rating}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            Management & Leadership <span className="text-red-600">*</span>
                                        </label>
                                        <StarRating
                                            value={formData.management_rating}
                                            onChange={(value) => handleFieldChange('management_rating', value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.management_rating && (
                                            <p className="text-sm text-red-600 mt-1">
                                                {errors.management_rating}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            Compensation & Benefits <span className="text-red-600">*</span>
                                        </label>
                                        <StarRating
                                            value={formData.compensation_rating}
                                            onChange={(value) => handleFieldChange('compensation_rating', value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.compensation_rating && (
                                            <p className="text-sm text-red-600 mt-1">
                                                {errors.compensation_rating}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-3">
                                            Career Growth & Development <span className="text-red-600">*</span>
                                        </label>
                                        <StarRating
                                            value={formData.career_growth_rating}
                                            onChange={(value) => handleFieldChange('career_growth_rating', value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.career_growth_rating && (
                                            <p className="text-sm text-red-600 mt-1">
                                                {errors.career_growth_rating}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Work-Life Balance <span className="text-red-600">*</span>
                                    </label>
                                    <StarRating
                                        value={formData.work_life_balance_rating}
                                        onChange={(value) => handleFieldChange('work_life_balance_rating', value)}
                                        disabled={isCompleted}
                                    />
                                    {errors.work_life_balance_rating && (
                                        <p className="text-sm text-red-600 mt-1">
                                            {errors.work_life_balance_rating}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Section>

                        {/* Feedback Section */}
                        <Section
                            title="💭 Feedback & Suggestions"
                            isExpanded={expandedSections.has('feedback')}
                            onToggle={() => toggleSection('feedback')}
                        >
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        What did you like most about working here? <span className="text-red-600">*</span>
                                    </label>
                                    <textarea
                                        value={formData.liked_most}
                                        onChange={(e) => handleFieldChange('liked_most', e.target.value)}
                                        disabled={isCompleted}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                                            errors.liked_most
                                                ? 'border-red-500 focus:ring-red-500'
                                                : 'border-gray-300 focus:ring-blue-500'
                                        }`}
                                        rows={3}
                                        placeholder="Share the positive aspects of your experience..."
                                    />
                                    <div className="flex justify-between mt-2">
                                        <p className="text-xs text-gray-500">Minimum 10 characters required</p>
                                        <p className="text-xs text-gray-500">
                                            {formData.liked_most.length}/500
                                        </p>
                                    </div>
                                    {errors.liked_most && (
                                        <p className="text-sm text-red-600 mt-1 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.liked_most}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        What could we improve? <span className="text-red-600">*</span>
                                    </label>
                                    <textarea
                                        value={formData.liked_least}
                                        onChange={(e) => handleFieldChange('liked_least', e.target.value)}
                                        disabled={isCompleted}
                                        className={`w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 ${
                                            errors.liked_least
                                                ? 'border-red-500 focus:ring-red-500'
                                                : 'border-gray-300 focus:ring-blue-500'
                                        }`}
                                        rows={3}
                                        placeholder="Please mention areas that need improvement..."
                                    />
                                    <div className="flex justify-between mt-2">
                                        <p className="text-xs text-gray-500">Minimum 10 characters required</p>
                                        <p className="text-xs text-gray-500">
                                            {formData.liked_least.length}/500
                                        </p>
                                    </div>
                                    {errors.liked_least && (
                                        <p className="text-sm text-red-600 mt-1 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.liked_least}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Suggestions for improvement (Optional)
                                    </label>
                                    <textarea
                                        value={formData.suggestions_for_improvement}
                                        onChange={(e) => handleFieldChange('suggestions_for_improvement', e.target.value)}
                                        disabled={isCompleted}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        rows={3}
                                        placeholder="Any additional suggestions for organizational improvement..."
                                    />
                                    <p className="text-xs text-gray-500 mt-2">
                                        {formData.suggestions_for_improvement.length}/1000
                                    </p>
                                </div>
                            </div>
                        </Section>

                        {/* Future Intentions Section */}
                        <Section
                            title="🔮 Future Intentions"
                            isExpanded={expandedSections.has('intentions')}
                            onToggle={() => toggleSection('intentions')}
                        >
                            <div className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Would you recommend this company as a place to work? <span className="text-red-600">*</span>
                                    </label>
                                    <div className="flex gap-4">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="would_recommend_company"
                                                value="yes"
                                                checked={formData.would_recommend_company === true}
                                                onChange={() => handleFieldChange('would_recommend_company', true)}
                                                disabled={isCompleted}
                                                className="w-4 h-4"
                                            />
                                            <span className="text-gray-700">Yes, I would recommend</span>
                                        </label>
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="would_recommend_company"
                                                value="no"
                                                checked={formData.would_recommend_company === false}
                                                onChange={() => handleFieldChange('would_recommend_company', false)}
                                                disabled={isCompleted}
                                                className="w-4 h-4"
                                            />
                                            <span className="text-gray-700">No, I would not</span>
                                        </label>
                                    </div>
                                    {errors.would_recommend_company && (
                                        <p className="text-sm text-red-600 mt-2 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.would_recommend_company}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Would you consider returning to work here in the future? <span className="text-red-600">*</span>
                                    </label>
                                    <div className="flex gap-4">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="would_consider_returning"
                                                value="yes"
                                                checked={formData.would_consider_returning === true}
                                                onChange={() => handleFieldChange('would_consider_returning', true)}
                                                disabled={isCompleted}
                                                className="w-4 h-4"
                                            />
                                            <span className="text-gray-700">Yes, I would consider it</span>
                                        </label>
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                name="would_consider_returning"
                                                value="no"
                                                checked={formData.would_consider_returning === false}
                                                onChange={() => handleFieldChange('would_consider_returning', false)}
                                                disabled={isCompleted}
                                                className="w-4 h-4"
                                            />
                                            <span className="text-gray-700">No, I would not</span>
                                        </label>
                                    </div>
                                    {errors.would_consider_returning && (
                                        <p className="text-sm text-red-600 mt-2 flex items-center gap-2">
                                            <AlertCircle size={16} />
                                            {errors.would_consider_returning}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Section>

                        {/* Action Buttons */}
                        <div className="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleSaveDraft}
                                disabled={saveStatus === 'saving' || isCompleted}
                                className="flex items-center gap-2"
                            >
                                <Save size={18} />
                                {saveStatus === 'saving' ? 'Saving...' : saveStatus === 'saved' ? 'Draft Saved' : 'Save Draft'}
                            </Button>

                            <div className="flex-1" />

                            {!submitConfirm ? (
                                <Button
                                    type="button"
                                    onClick={() => setSubmitConfirm(true)}
                                    disabled={isCompleted}
                                    className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700"
                                >
                                    <Send size={18} />
                                    Submit Interview
                                </Button>
                            ) : (
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setSubmitConfirm(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="bg-green-600 hover:bg-green-700 flex items-center gap-2"
                                    >
                                        <CheckCircle size={18} />
                                        Confirm & Submit
                                    </Button>
                                </div>
                            )}
                        </div>

                        {submitConfirm && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
                                <AlertCircle className="text-blue-600 mt-1 flex-shrink-0" size={24} />
                                <div>
                                    <h3 className="font-semibold text-blue-900">Confirm Submission</h3>
                                    <p className="text-blue-800 text-sm">
                                        Once submitted, you will not be able to edit your responses. Please review your answers carefully before confirming.
                                    </p>
                                </div>
                            </div>
                        )}
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
