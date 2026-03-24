import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PermissionGate } from '@/components/permission-gate';
import {
    AlertCircle,
    CheckCircle,
    Clock,
    FileText,
    AlertTriangle,
    ChevronDown,
    ChevronUp,
    User,
    Download,
    HardDrive,
    BookOpen,
    LogOut,
    Trash2,
    CheckCheck,
} from 'lucide-react';
import { useState } from 'react';

interface CaseDetail {
    id: number;
    case_number: string;
    employee: {
        id: number;
        name: string;
        employee_number: string;
        department: string;
    };
    separation_type: string;
    separation_reason: string;
    status: string;
    status_label: string;
    last_working_day: string;
    initiated_by: string | null;
    hr_coordinator: string | null;
    rehire_eligible: boolean | null;
    created_at: string;
    completion_percentage: number;
    position: string;
    notice_period_days: number | null;
    internal_notes: string | null;
    rehire_eligibility_reason: string | null;
}

interface ClearanceItem {
    id: number;
    category: string;
    item_name: string;
    description: string;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    assigned_to: string | null;
    approved_by: string | null;
    approved_at: string | null;
    due_date: string | null;
    has_issues: boolean;
    issue_description: string | null;
    is_overdue: boolean;
}

interface ExitInterview {
    id: number;
    status: string;
    status_label: string;
    interview_date: string | null;
    conducted_by: string | null;
    reason_for_leaving: string | null;
    overall_satisfaction: number | null;
    average_rating: number;
    would_recommend_company: boolean | null;
    would_consider_returning: boolean | null;
    sentiment_score: number;
    sentiment_classification: string;
    key_themes: string[];
    completed_at: string | null;
}

interface CompanyAsset {
    id: number;
    asset_name: string;
    asset_type: string;
    serial_number: string | null;
    status: string;
    status_label: string;
    assigned_date: string | null;
    return_date: string | null;
    condition_at_return: string | null;
    liability_amount: number;
    has_liability: boolean;
}

interface KnowledgeTransfer {
    id: number;
    item_type: string;
    item_type_label: string;
    title: string;
    description: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    transferred_to: string | null;
    due_date: string | null;
    is_overdue: boolean;
    completed_at: string | null;
}

interface AccessRevocation {
    id: number;
    system_name: string;
    system_category: string;
    system_category_label: string;
    account_identifier: string | null;
    status: string;
    status_label: string;
    data_backed_up: boolean;
    needs_backup: boolean;
    revoked_at: string | null;
}

interface OffboardingDocument {
    id: number;
    document_type: string;
    document_type_label: string;
    document_name: string;
    status: string;
    status_label: string;
    generated_by_system: boolean;
    issued_to_employee: boolean;
    file_path: string | null;
    file_size: string;
    created_at: string;
    issued_at: string | null;
}

interface ProgressSummary {
    clearance_percentage: number;
    exit_interview_completed: boolean;
    assets_percentage: number;
    documents_percentage: number;
    access_revocation_percentage: number;
    overall_percentage: number;
}

interface CaseShowProps {
    case: CaseDetail;
    clearancesByCategory: Record<string, ClearanceItem[]>;
    exitInterview: ExitInterview | null;
    companyAssets: CompanyAsset[];
    knowledgeTransfers: KnowledgeTransfer[];
    accessRevocations: AccessRevocation[];
    documents: OffboardingDocument[];
    progressSummary: ProgressSummary;
    nextActions: Array<{
        action: string;
        description: string;
        priority: string;
    }>;
    canComplete: boolean;
    canCancel: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Offboarding', href: '/hr/offboarding' },
    { title: 'Cases', href: '/hr/offboarding/cases' },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800';
        case 'clearance_pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'pending':
            return 'bg-orange-100 text-orange-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'approved':
        case 'returned':
            return 'bg-green-100 text-green-800';
        case 'issued':
            return 'bg-yellow-100 text-yellow-800';
        case 'revoked':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'high':
            return 'text-red-600 bg-red-50 border-l-4 border-red-600';
        case 'medium':
            return 'text-yellow-600 bg-yellow-50 border-l-4 border-yellow-600';
        case 'low':
            return 'text-green-600 bg-green-50 border-l-4 border-green-600';
        default:
            return 'text-gray-600 bg-gray-50 border-l-4 border-gray-600';
    }
};

const SentimentIndicator = ({ score }: { score: number }) => {
    let label = 'Neutral';
    let color = 'bg-gray-100 text-gray-800';

    if (score > 0.6) {
        label = 'Positive';
        color = 'bg-green-100 text-green-800';
    } else if (score < -0.6) {
        label = 'Negative';
        color = 'bg-red-100 text-red-800';
    }

    return <Badge className={color}>{label}</Badge>;
};

export default function CaseShow({
    case: caseData,
    clearancesByCategory,
    exitInterview,
    companyAssets,
    knowledgeTransfers,
    accessRevocations,
    documents,
    progressSummary,
    nextActions,
    canComplete,
    canCancel,
}: CaseShowProps) {
    const [expandedSections, setExpandedSections] = useState<Set<string>>(
        new Set(['overview', 'progress', 'clearance'])
    );

    const toggleSection = (section: string) => {
        const newExpanded = new Set(expandedSections);
        if (newExpanded.has(section)) {
            newExpanded.delete(section);
        } else {
            newExpanded.add(section);
        }
        setExpandedSections(newExpanded);
    };

    const totalClearanceItems = Object.values(clearancesByCategory).flat().length;
    const approvedClearanceItems = Object.values(clearancesByCategory)
        .flat()
        .filter((item) => item.status === 'approved').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Offboarding Case ${caseData.case_number}`} />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3 flex-wrap">
                            <h1 className="text-3xl font-bold">{caseData.employee.name}</h1>
                            <Badge className={getStatusColor(caseData.status)}>
                                {caseData.status_label}
                            </Badge>
                            {caseData.rehire_eligible === true && (
                                <Badge className="bg-green-100 text-green-800">Rehire Eligible</Badge>
                            )}
                            {caseData.rehire_eligible === false && (
                                <Badge className="bg-red-100 text-red-800">Not Eligible</Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground mt-1">Case #{caseData.case_number}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <PermissionGate permission="hr.offboarding.cases.update">
                            <Link href={`/hr/offboarding/cases/${caseData.id}/edit`}>
                                <Button variant="outline" size="sm">
                                    Edit Case
                                </Button>
                            </Link>
                        </PermissionGate>
                        {canComplete && (
                            <PermissionGate permission="hr.offboarding.cases.complete">
                                <Button
                                    size="sm"
                                    className="bg-green-600 hover:bg-green-700"
                                    onClick={() => {
                                        if (confirm('Mark this offboarding case as completed?')) {
                                            router.post(
                                                `/hr/offboarding/cases/${caseData.id}/complete`
                                            );
                                        }
                                    }}
                                >
                                    <CheckCheck className="h-4 w-4 mr-2" />
                                    Complete Case
                                </Button>
                            </PermissionGate>
                        )}
                        {canCancel && (
                            <PermissionGate permission="hr.offboarding.cases.cancel">
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('Are you sure you want to cancel this case?')) {
                                            router.delete(
                                                `/hr/offboarding/cases/${caseData.id}/cancel`
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Cancel
                                </Button>
                            </PermissionGate>
                        )}
                    </div>
                </div>

                {/* Next Actions Alert */}
                {nextActions.length > 0 && (
                    <Card className="border-blue-200 bg-blue-50">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-blue-900">
                                <AlertCircle className="h-5 w-5" />
                                Next Actions Required
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {nextActions.filter(a => a && (a.action || a.description)).map((action, index) => (
                                    <li key={index} className="text-sm text-blue-800">
                                        {action.action ? <span className="font-medium">{action.action}{action.description ? ':' : ''}</span> : null}
                                        {action.description}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {/* Overview and Progress Section */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Overview */}
                    <Card className="lg:col-span-2">
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('overview')}
                        >
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Employee Information
                                </CardTitle>
                                {expandedSections.has('overview') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('overview') && (
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Employee Number
                                        </p>
                                        <p className="text-sm font-mono">#{caseData.employee.employee_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Department
                                        </p>
                                        <p className="text-sm">{caseData.employee.department}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Position
                                        </p>
                                        <p className="text-sm">{caseData.position || 'Not specified'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Separation Type
                                        </p>
                                        <p className="text-sm capitalize">{caseData.separation_type.replace(/_/g, ' ')}</p>
                                    </div>
                                </div>

                                <div className="border-t pt-4 space-y-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Last Working Day
                                        </p>
                                        <p className="text-sm">{caseData.last_working_day}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Separation Reason
                                        </p>
                                        <p className="text-sm">{caseData.separation_reason}</p>
                                    </div>
                                    {caseData.notice_period_days !== null && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                                Notice Period
                                            </p>
                                            <p className="text-sm">{caseData.notice_period_days} days</p>
                                        </div>
                                    )}
                                </div>

                                <div className="border-t pt-4 space-y-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            HR Coordinator
                                        </p>
                                        <p className="text-sm">{caseData.hr_coordinator || 'Unassigned'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Case Created
                                        </p>
                                        <p className="text-sm">{caseData.created_at}</p>
                                    </div>
                                </div>

                                {caseData.internal_notes && (
                                    <div className="border-t pt-4">
                                        <p className="text-xs font-medium text-muted-foreground uppercase mb-2">
                                            Internal Notes
                                        </p>
                                        <p className="text-sm bg-gray-50 p-3 rounded border">
                                            {caseData.internal_notes}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        )}
                    </Card>

                    {/* Progress Tracking */}
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('progress')}
                        >
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    Progress
                                </CardTitle>
                                {expandedSections.has('progress') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('progress') && (
                            <CardContent className="space-y-4">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground uppercase mb-2">
                                        Overall Progress
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <div className="flex-1 bg-gray-200 rounded-full h-3">
                                            <div
                                                className="bg-blue-600 h-3 rounded-full"
                                                style={{ width: `${progressSummary.overall_percentage}%` }}
                                            />
                                        </div>
                                        <span className="text-sm font-medium">{progressSummary.overall_percentage}%</span>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Clearance</span>
                                        <span className="font-medium">{progressSummary.clearance_percentage}%</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Exit Interview</span>
                                        <span className="font-medium">
                                            {progressSummary.exit_interview_completed ? '100%' : '0%'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Assets</span>
                                        <span className="font-medium">{progressSummary.assets_percentage}%</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Access Revocation</span>
                                        <span className="font-medium">
                                            {progressSummary.access_revocation_percentage}%
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Documents</span>
                                        <span className="font-medium">{progressSummary.documents_percentage}%</span>
                                    </div>
                                </div>
                            </CardContent>
                        )}
                    </Card>
                </div>

                {/* Clearance Checklist */}
                <Card>
                    <CardHeader
                        className="cursor-pointer"
                        onClick={() => toggleSection('clearance')}
                    >
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle className="h-5 w-5" />
                                    Clearance Checklist
                                </CardTitle>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {approvedClearanceItems} of {totalClearanceItems} approved
                                </p>
                            </div>
                            {expandedSections.has('clearance') ? (
                                <ChevronUp className="h-5 w-5" />
                            ) : (
                                <ChevronDown className="h-5 w-5" />
                            )}
                        </div>
                    </CardHeader>
                    {expandedSections.has('clearance') && (
                        <CardContent className="space-y-6">
                            {Object.entries(clearancesByCategory).map(([category, items]) => (
                                <div key={category} className="space-y-3">
                                    <h3 className="font-semibold text-sm capitalize">
                                        {category.replace(/_/g, ' ')}
                                    </h3>
                                    <div className="space-y-2">
                                        {items.map((item) => (
                                            <div
                                                key={item.id}
                                                className={`p-3 rounded-lg border ${getPriorityColor(item.priority)}`}
                                            >
                                                <div className="flex items-start justify-between">
                                                    <div className="flex-1">
                                                        <p className="font-medium text-sm">{item.item_name}</p>
                                                        <p className="text-xs opacity-75 mt-1">{item.description}</p>
                                                        <div className="flex items-center gap-2 mt-2 flex-wrap">
                                                            <Badge className={getStatusColor(item.status)}>
                                                                {item.status_label}
                                                            </Badge>
                                                            {item.due_date && (
                                                                <span className="text-xs opacity-75">
                                                                    Due: {item.due_date}
                                                                    {item.is_overdue && (
                                                                        <AlertTriangle className="h-3 w-3 inline ml-1 text-red-600" />
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {item.assigned_to && (
                                                            <p className="text-xs opacity-75 mt-1">
                                                                Assigned to: {item.assigned_to}
                                                            </p>
                                                        )}
                                                        {item.approved_by && (
                                                            <p className="text-xs opacity-75">
                                                                Approved by: {item.approved_by} on{' '}
                                                                {item.approved_at}
                                                            </p>
                                                        )}
                                                        {item.has_issues && item.issue_description && (
                                                            <div className="mt-2 p-2 bg-red-100 rounded text-xs">
                                                                <p className="font-medium">Issue: {item.issue_description}</p>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    )}
                </Card>

                {/* Exit Interview Section */}
                {exitInterview && (
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('interview')}
                        >
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <BookOpen className="h-5 w-5" />
                                    Exit Interview
                                </CardTitle>
                                {expandedSections.has('interview') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('interview') && (
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Status
                                        </p>
                                        <p className="text-sm mt-1">
                                            <Badge className={getStatusColor(exitInterview.status)}>
                                                {exitInterview.status_label}
                                            </Badge>
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground uppercase">
                                            Sentiment
                                        </p>
                                        <p className="text-sm mt-1">
                                            <SentimentIndicator score={exitInterview.sentiment_score} />
                                        </p>
                                    </div>
                                </div>

                                {exitInterview.interview_date && (
                                    <div className="grid grid-cols-2 gap-4 border-t pt-4">
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                                Interview Date
                                            </p>
                                            <p className="text-sm mt-1">{exitInterview.interview_date}</p>
                                        </div>
                                        {exitInterview.conducted_by && (
                                            <div>
                                                <p className="text-xs font-medium text-muted-foreground uppercase">
                                                    Conducted By
                                                </p>
                                                <p className="text-sm mt-1">{exitInterview.conducted_by}</p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {exitInterview.reason_for_leaving && (
                                    <div className="border-t pt-4">
                                        <p className="text-xs font-medium text-muted-foreground uppercase mb-2">
                                            Reason for Leaving
                                        </p>
                                        <p className="text-sm">{exitInterview.reason_for_leaving}</p>
                                    </div>
                                )}

                                {exitInterview.overall_satisfaction !== null && (
                                    <div className="border-t pt-4 grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                                Satisfaction
                                            </p>
                                            <p className="text-sm mt-1">
                                                {exitInterview.overall_satisfaction}/10
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground uppercase">
                                                Average Rating
                                            </p>
                                            <p className="text-sm mt-1">
                                                {exitInterview.average_rating.toFixed(2)}/5
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {(exitInterview.would_recommend_company !== null ||
                                    exitInterview.would_consider_returning !== null) && (
                                    <div className="border-t pt-4 space-y-2">
                                        {exitInterview.would_recommend_company !== null && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm">Would Recommend Company:</span>
                                                <Badge
                                                    className={
                                                        exitInterview.would_recommend_company
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-red-100 text-red-800'
                                                    }
                                                >
                                                    {exitInterview.would_recommend_company ? 'Yes' : 'No'}
                                                </Badge>
                                            </div>
                                        )}
                                        {exitInterview.would_consider_returning !== null && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm">Would Consider Returning:</span>
                                                <Badge
                                                    className={
                                                        exitInterview.would_consider_returning
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-red-100 text-red-800'
                                                    }
                                                >
                                                    {exitInterview.would_consider_returning ? 'Yes' : 'No'}
                                                </Badge>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {exitInterview.key_themes && exitInterview.key_themes.length > 0 && (
                                    <div className="border-t pt-4">
                                        <p className="text-xs font-medium text-muted-foreground uppercase mb-2">
                                            Key Themes
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {exitInterview.key_themes.map((theme, index) => (
                                                <Badge key={index} variant="outline">
                                                    {theme}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* Assets Section */}
                {companyAssets.length > 0 && (
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('assets')}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <HardDrive className="h-5 w-5" />
                                        Company Assets
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {companyAssets.length} assets
                                    </p>
                                </div>
                                {expandedSections.has('assets') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('assets') && (
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b bg-muted/50">
                                            <tr>
                                                <th className="py-2 px-4 text-left font-medium">Asset</th>
                                                <th className="py-2 px-4 text-left font-medium">Serial #</th>
                                                <th className="py-2 px-4 text-left font-medium">Status</th>
                                                <th className="py-2 px-4 text-left font-medium">
                                                    Condition at Return
                                                </th>
                                                <th className="py-2 px-4 text-right font-medium">Liability</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {companyAssets.map((asset) => (
                                                <tr key={asset.id} className="border-b hover:bg-muted/50">
                                                    <td className="py-3 px-4">
                                                        <div>
                                                            <p className="font-medium">{asset.asset_name}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {asset.asset_type}
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-xs">
                                                        {asset.serial_number || '—'}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <Badge className={getStatusColor(asset.status)}>
                                                            {asset.status_label}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4">{asset.condition_at_return || '—'}</td>
                                                    <td className="py-3 px-4 text-right">
                                                        {asset.has_liability ? (
                                                            <span className="text-red-600 font-semibold">
                                                                ${asset.liability_amount.toFixed(2)}
                                                            </span>
                                                        ) : (
                                                            <span className="text-green-600">No liability</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* Access Revocation Section */}
                {accessRevocations.length > 0 && (
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('access')}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <LogOut className="h-5 w-5" />
                                        Access Revocations
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {accessRevocations.length} systems
                                    </p>
                                </div>
                                {expandedSections.has('access') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('access') && (
                            <CardContent>
                                <div className="space-y-3">
                                    {accessRevocations.map((revocation) => (
                                        <div
                                            key={revocation.id}
                                            className="p-3 border rounded-lg hover:bg-muted/50"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-sm">{revocation.system_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {revocation.system_category_label}
                                                    </p>
                                                    {revocation.account_identifier && (
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            Account: {revocation.account_identifier}
                                                        </p>
                                                    )}
                                                    {revocation.needs_backup && (
                                                        <p className="text-xs text-red-600 mt-1 font-medium">
                                                            ⚠️ Data backup needed
                                                        </p>
                                                    )}
                                                </div>
                                                <Badge className={getStatusColor(revocation.status)}>
                                                    {revocation.status_label}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* Documents Section */}
                {documents.length > 0 && (
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('documents')}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Documents
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {documents.length} document(s)
                                    </p>
                                </div>
                                {expandedSections.has('documents') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('documents') && (
                            <CardContent>
                                <div className="space-y-3">
                                    {documents.map((doc) => (
                                        <div
                                            key={doc.id}
                                            className="p-3 border rounded-lg hover:bg-muted/50"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-sm">{doc.document_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {doc.document_type_label}
                                                    </p>
                                                    <div className="flex items-center gap-2 mt-2 flex-wrap">
                                                        <Badge className={getStatusColor(doc.status)}>
                                                            {doc.status_label}
                                                        </Badge>
                                                        {doc.generated_by_system && (
                                                            <Badge variant="outline">System Generated</Badge>
                                                        )}
                                                        {doc.issued_to_employee && (
                                                            <Badge className="bg-green-100 text-green-800">
                                                                Issued
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-2">
                                                        Created: {doc.created_at}
                                                        {doc.issued_at && ` • Issued: ${doc.issued_at}`}
                                                    </p>
                                                </div>
                                                {doc.file_path && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a href={doc.file_path} download>
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* Knowledge Transfer Section */}
                {knowledgeTransfers.length > 0 && (
                    <Card>
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => toggleSection('knowledge')}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <BookOpen className="h-5 w-5" />
                                        Knowledge Transfer
                                    </CardTitle>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {knowledgeTransfers.length} item(s)
                                    </p>
                                </div>
                                {expandedSections.has('knowledge') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('knowledge') && (
                            <CardContent>
                                <div className="space-y-3">
                                    {knowledgeTransfers.map((item) => (
                                        <div
                                            key={item.id}
                                            className={`p-3 rounded-lg border ${getPriorityColor(item.priority)}`}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-sm">{item.title}</p>
                                                    <p className="text-xs opacity-75 mt-1">{item.description}</p>
                                                    <div className="flex items-center gap-2 mt-2 flex-wrap">
                                                        <Badge className={getStatusColor(item.status)}>
                                                            {item.status_label}
                                                        </Badge>
                                                        {item.due_date && (
                                                            <span className="text-xs opacity-75">
                                                                Due: {item.due_date}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {item.transferred_to && (
                                                        <p className="text-xs opacity-75 mt-1">
                                                            Transferred to: {item.transferred_to}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
