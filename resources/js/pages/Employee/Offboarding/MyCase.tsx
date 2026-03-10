import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle2,
    AlertCircle,
    FileText,
    HardDrive,
    Calendar,
    Clock,
    Download,
    ChevronDown,
    ChevronUp,
    AlertTriangle,
    Phone,
    Mail,
} from 'lucide-react';
import { format } from 'date-fns';
import type { BreadcrumbItem } from '@/types';

interface ClearanceItem {
    id: number;
    category: string;
    item_name: string;
    description: string;
    priority: string;
    status: string;
    assigned_to: string | null;
    approved_by: string | null;
    approved_at: string | null;
    due_date: string | null;
    is_overdue: boolean;
}

interface CompanyAsset {
    id: number;
    asset_name: string;
    asset_type: string;
    serial_number: string | null;
    status: string;
    return_date: string | null;
}

interface ExitInterview {
    id: number;
    status: string;
    completed_at: string | null;
}

interface OffboardingDocument {
    id: number;
    document_type: string;
    document_name: string;
    file_path: string | null;
    created_at: string;
}

interface CaseDetail {
    id: number;
    case_number: string;
    status: string;
    last_working_day: string;
    separation_type: string;
    separation_reason: string;
    created_at: string;
    completion_percentage: number;
}

interface ProgressSummary {
    clearance_percentage: number;
    exit_interview_completed: boolean;
    assets_percentage: number;
    documents_percentage: number;
    access_revocation_percentage: number;
    overall_percentage: number;
}

interface MyCaseProps {
    case: CaseDetail;
    clearancesByCategory: Record<string, ClearanceItem[]>;
    clearanceStatistics: {
        total: number;
        approved: number;
        pending: number;
        issues: number;
    };
    exitInterview: ExitInterview | null;
    companyAssets: CompanyAsset[];
    documents: OffboardingDocument[];
    progressSummary: ProgressSummary;
    hrContactName: string;
    hrContactEmail: string;
    hrContactPhone: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/employee/dashboard' },
    { title: 'Offboarding', href: '#' },
];

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800';
        case 'clearance_pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'pending':
            return 'bg-orange-100 text-orange-800';
        case 'approved':
        case 'returned':
            return 'bg-green-100 text-green-800';
        case 'rejected':
        case 'not_returned':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityColor = (priority: string): string => {
    switch (priority) {
        case 'critical':
            return 'border-l-4 border-red-600 bg-red-50';
        case 'high':
            return 'border-l-4 border-orange-600 bg-orange-50';
        case 'medium':
            return 'border-l-4 border-yellow-600 bg-yellow-50';
        case 'low':
            return 'border-l-4 border-green-600 bg-green-50';
        default:
            return 'border-l-4 border-gray-600 bg-gray-50';
    }
};

const getAssetStatusColor = (status: string): string => {
    switch (status) {
        case 'returned':
            return 'bg-green-100 text-green-800';
        case 'pending_return':
            return 'bg-yellow-100 text-yellow-800';
        case 'lost':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

export default function MyCase({
    case: caseData,
    clearancesByCategory,
    clearanceStatistics,
    exitInterview,
    companyAssets,
    documents,
    progressSummary,
    hrContactName,
    hrContactEmail,
    hrContactPhone,
}: MyCaseProps) {
    const [expandedSections, setExpandedSections] = useState<Set<string>>(
        new Set(['overview', 'clearance', 'assets'])
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

    const daysUntilLastDay = Math.ceil(
        (new Date(caseData.last_working_day).getTime() - new Date().getTime()) /
        (1000 * 60 * 60 * 24)
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Offboarding Case" />

            <div className="max-w-5xl mx-auto py-8 px-4 space-y-6">
                {/* Header */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div className="flex items-start justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">My Offboarding</h1>
                            <p className="text-gray-600 mt-2">Case #{caseData.case_number}</p>
                            <div className="flex items-center gap-3 mt-3">
                                <Badge className={getStatusColor(caseData.status)}>
                                    {caseData.status.replace(/_/g, ' ').toUpperCase()}
                                </Badge>
                                {daysUntilLastDay > 0 && (
                                    <span className="text-sm text-gray-600">
                                        <Calendar className="h-4 w-4 inline mr-1" />
                                        Last working day: {format(new Date(caseData.last_working_day), 'MMM dd, yyyy')}
                                        ({daysUntilLastDay} days)
                                    </span>
                                )}
                                {daysUntilLastDay <= 0 && (
                                    <span className="text-sm text-red-600 font-medium">
                                        <AlertTriangle className="h-4 w-4 inline mr-1" />
                                        Last working day has passed
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Progress Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5" />
                            Offboarding Progress
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-sm font-medium text-gray-700">Overall Progress</span>
                                <span className="text-sm font-bold text-gray-900">{progressSummary.overall_percentage}%</span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-4">
                                <div
                                    className="bg-gradient-to-r from-blue-500 to-indigo-600 h-4 rounded-full transition-all duration-300"
                                    style={{ width: `${progressSummary.overall_percentage}%` }}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 margin-top-4">
                            <div className="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Clearance</span>
                                    {progressSummary.clearance_percentage === 100 ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <span className="text-xs font-bold text-gray-900">
                                            {progressSummary.clearance_percentage}%
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Interview</span>
                                    {progressSummary.exit_interview_completed ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <span className="text-xs font-bold text-gray-900">0%</span>
                                    )}
                                </div>
                            </div>

                            <div className="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Assets</span>
                                    {progressSummary.assets_percentage === 100 ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <span className="text-xs font-bold text-gray-900">
                                            {progressSummary.assets_percentage}%
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Access</span>
                                    {progressSummary.access_revocation_percentage === 100 ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <span className="text-xs font-bold text-gray-900">
                                            {progressSummary.access_revocation_percentage}%
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="p-3 border rounded-lg hover:bg-gray-50 transition">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-600">Documents</span>
                                    {progressSummary.documents_percentage === 100 ? (
                                        <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <span className="text-xs font-bold text-gray-900">
                                            {progressSummary.documents_percentage}%
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Case Information */}
                <Card>
                    <CardHeader className="cursor-pointer" onClick={() => toggleSection('overview')}>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <AlertCircle className="h-5 w-5" />
                                Separation Information
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
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-medium text-gray-600 uppercase mb-1">Type</p>
                                    <p className="text-sm font-medium text-gray-900">
                                        {caseData.separation_type.replace(/_/g, ' ').toUpperCase()}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-gray-600 uppercase mb-1">Last Working Day</p>
                                    <p className="text-sm font-medium text-gray-900">
                                        {format(new Date(caseData.last_working_day), 'MMMM dd, yyyy')}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p className="text-xs font-medium text-gray-600 uppercase mb-1">Reason</p>
                                <p className="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                    {caseData.separation_reason}
                                </p>
                            </div>

                            <div className="text-xs text-gray-500">
                                Case created on {format(new Date(caseData.created_at), 'MMMM dd, yyyy')}
                            </div>
                        </CardContent>
                    )}
                </Card>

                {/* Clearance Checklist */}
                <Card>
                    <CardHeader className="cursor-pointer" onClick={() => toggleSection('clearance')}>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle2 className="h-5 w-5" />
                                    Clearance Checklist
                                </CardTitle>
                                <p className="text-xs text-gray-600 mt-1">
                                    {clearanceStatistics.approved} of {clearanceStatistics.total} approved
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
                            {Object.entries(clearancesByCategory).length === 0 ? (
                                <p className="text-sm text-gray-500 text-center py-4">No clearance items</p>
                            ) : (
                                Object.entries(clearancesByCategory).map(([category, items]) => (
                                    <div key={category}>
                                        <h3 className="font-semibold text-sm capitalize mb-3 text-gray-900">
                                            {category.replace(/_/g, ' ')}
                                        </h3>
                                        <div className="space-y-2">
                                            {items.map((item) => (
                                                <div key={item.id} className={`p-3 rounded-lg ${getPriorityColor(item.priority)}`}>
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex-1">
                                                            <p className="font-medium text-sm">{item.item_name}</p>
                                                            <p className="text-xs opacity-75 mt-1">{item.description}</p>

                                                            <div className="flex items-center gap-2 mt-2 flex-wrap">
                                                                <Badge className={getStatusColor(item.status)}>
                                                                    {item.status}
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
                                                                    Assigned to: <span className="font-medium">{item.assigned_to}</span>
                                                                </p>
                                                            )}

                                                            {item.approved_by && (
                                                                <p className="text-xs opacity-75 mt-1">
                                                                    ✓ Approved by {item.approved_by} on{' '}
                                                                    {item.approved_at ? format(new Date(item.approved_at), 'MMM dd, yyyy') : 'Unknown'}
                                                                </p>
                                                            )}
                                                        </div>

                                                        {item.status === 'approved' && (
                                                            <CheckCircle2 className="h-5 w-5 text-green-600 flex-shrink-0 mt-1" />
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    )}
                </Card>

                {/* Company Assets */}
                {companyAssets.length > 0 && (
                    <Card>
                        <CardHeader className="cursor-pointer" onClick={() => toggleSection('assets')}>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <HardDrive className="h-5 w-5" />
                                    Company Assets to Return
                                </CardTitle>
                                {expandedSections.has('assets') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('assets') && (
                            <CardContent>
                                <div className="space-y-3">
                                    {companyAssets.map((asset) => (
                                        <div key={asset.id} className="p-4 border rounded-lg hover:bg-gray-50 transition">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <h4 className="font-medium text-sm text-gray-900">{asset.asset_name}</h4>
                                                    <div className="flex items-center gap-2 mt-2 text-xs">
                                                        <span className="text-gray-600">Type:</span>
                                                        <Badge variant="outline">{asset.asset_type}</Badge>
                                                    </div>
                                                    {asset.serial_number && (
                                                        <p className="text-xs text-gray-600 mt-1 font-mono">
                                                            SN: {asset.serial_number}
                                                        </p>
                                                    )}
                                                    {asset.return_date && (
                                                        <p className="text-xs text-gray-600 mt-1">
                                                            Return by: {format(new Date(asset.return_date), 'MMM dd, yyyy')}
                                                        </p>
                                                    )}
                                                </div>
                                                <Badge className={getAssetStatusColor(asset.status)}>
                                                    {asset.status.replace(/_/g, ' ')}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* Exit Interview */}
                {exitInterview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Exit Interview
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {exitInterview.status === 'completed' && exitInterview.completed_at ? (
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded">
                                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                                        <span className="text-sm text-green-800">
                                            Completed on {format(new Date(exitInterview.completed_at), 'MMMM dd, yyyy')}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <p className="text-sm text-gray-600">
                                        Please complete the exit interview questionnaire. Your honest feedback is valuable for helping us improve.
                                    </p>
                                    <Link href={`/employee/offboarding/exit-interview/${caseData.id}`}>
                                        <Button className="bg-blue-600 hover:bg-blue-700 w-full">
                                            Complete Exit Interview
                                        </Button>
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Documents */}
                {documents.length > 0 && (
                    <Card>
                        <CardHeader className="cursor-pointer" onClick={() => toggleSection('documents')}>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2">
                                    <Download className="h-5 w-5" />
                                    Available Documents
                                </CardTitle>
                                {expandedSections.has('documents') ? (
                                    <ChevronUp className="h-5 w-5" />
                                ) : (
                                    <ChevronDown className="h-5 w-5" />
                                )}
                            </div>
                        </CardHeader>
                        {expandedSections.has('documents') && (
                            <CardContent>
                                <div className="space-y-2">
                                    {documents.map((doc) => (
                                        <div
                                            key={doc.id}
                                            className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 transition"
                                        >
                                            <div>
                                                <p className="font-medium text-sm text-gray-900">{doc.document_name}</p>
                                                <p className="text-xs text-gray-500 mt-1">
                                                    {doc.document_type.replace(/_/g, ' ')} • Created{' '}
                                                    {format(new Date(doc.created_at), 'MMM dd, yyyy')}
                                                </p>
                                            </div>
                                            {doc.file_path && (
                                                <Link href={`/employee/offboarding/documents/${doc.id}/download`}>
                                                    <Button size="sm" variant="outline">
                                                        <Download className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                {/* HR Contact Support */}
                <Card className="border-blue-200 bg-blue-50">
                    <CardHeader>
                        <CardTitle className="text-blue-900 flex items-center gap-2">
                            <Phone className="h-5 w-5" />
                            Need Help?
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-blue-900 mb-4">
                            Contact your HR Coordinator for any questions or assistance with your offboarding process.
                        </p>
                        <div className="space-y-2">
                            <div className="flex items-center gap-3">
                                <span className="font-medium text-sm text-blue-900 min-w-[60px]">Name:</span>
                                <span className="text-sm text-blue-800">{hrContactName}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="h-4 w-4 text-blue-600 min-w-[24px]" />
                                <Link href={`mailto:${hrContactEmail}`} className="text-sm text-blue-600 hover:underline">
                                    {hrContactEmail}
                                </Link>
                            </div>
                            <div className="flex items-center gap-3">
                                <Phone className="h-4 w-4 text-blue-600 min-w-[24px]" />
                                <Link href={`tel:${hrContactPhone}`} className="text-sm text-blue-600 hover:underline">
                                    {hrContactPhone}
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
