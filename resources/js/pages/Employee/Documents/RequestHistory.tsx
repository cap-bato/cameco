import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    FileText,
    Download,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    ArrowLeft,
    RefreshCw,
    X,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface DocumentRequest {
    id: number;
    document_type: string;
    document_type_key: string;
    purpose: string;
    period: string | null;
    status: string;
    status_display: {
        label: string;
        color: string;
        icon: string;
        description: string;
    };
    requested_at: string;
    processed_at: string | null;
    processed_by: string | null;
    file_path: string | null;
    rejection_reason: string | null;
    notes: string | null;
    can_cancel: boolean;
    can_resubmit: boolean;
    days_pending: number | null;
}

interface RequestHistoryProps {
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    requests: DocumentRequest[];
    statistics: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
    };
    filters: {
        status: string;
        document_type: string;
    };
}

// ============================================================================
// Helper Functions
// ============================================================================

function getStatusIcon(iconName: string) {
    const icons: Record<string, JSX.Element> = {
        Clock: <Clock className="h-4 w-4" />,
        FileText: <FileText className="h-4 w-4" />,
        CheckCircle: <CheckCircle className="h-4 w-4" />,
        XCircle: <XCircle className="h-4 w-4" />,
    };
    return icons[iconName] || <FileText className="h-4 w-4" />;
}

function getStatusBadgeClass(color: string): string {
    const colorMap: Record<string, string> = {
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-300',
        blue: 'bg-blue-100 text-blue-800 border-blue-300',
        green: 'bg-green-100 text-green-800 border-green-300',
        red: 'bg-red-100 text-red-800 border-red-300',
        gray: 'bg-gray-100 text-gray-800 border-gray-300',
    };
    return colorMap[color] || colorMap.gray;
}

// ============================================================================
// Main Component
// ============================================================================

export default function RequestHistory({
    employee,
    requests,
    statistics,
    filters,
}: RequestHistoryProps) {
    const { toast } = useToast();
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<DocumentRequest | null>(null);

    // Handle filter changes
    const handleStatusFilter = (status: string) => {
        router.get(
            '/employee/documents/requests/history',
            { status, document_type: filters.document_type },
            { preserveState: true }
        );
    };

    const handleDocumentTypeFilter = (documentType: string) => {
        router.get(
            '/employee/documents/requests/history',
            { status: filters.status, document_type: documentType },
            { preserveState: true }
        );
    };

    // Handle cancel request
    const handleCancelClick = (request: DocumentRequest) => {
        setSelectedRequest(request);
        setCancelDialogOpen(true);
    };

    const confirmCancel = () => {
        if (!selectedRequest) return;

        router.post(
            `/employee/documents/requests/${selectedRequest.id}/cancel`,
            {},
            {
                onSuccess: () => {
                    toast({
                        title: 'Request Cancelled',
                        description: 'Your document request has been cancelled successfully.',
                    });
                    setCancelDialogOpen(false);
                    setSelectedRequest(null);
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to cancel request. Please try again.',
                        variant: 'destructive',
                    });
                },
            }
        );
    };

    // Handle download
    const handleDownload = (request: DocumentRequest) => {
        window.location.href = `/employee/documents/requests/${request.id}/download`;
        toast({
            title: 'Download Started',
            description: `Downloading ${request.document_type}...`,
        });
    };

    // Handle resubmit
    const handleResubmit = (request: DocumentRequest) => {
        router.get('/employee/documents/request', {
            document_type: request.document_type_key,
            purpose: request.purpose,
            period: request.period,
        });
    };

    return (
        <AppLayout>
            <Head title="Document Request History" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            onClick={() => router.visit('/employee/documents')}
                            variant="outline"
                            size="icon"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Request History</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Track the status of your document requests
                            </p>
                        </div>
                    </div>
                    <Button
                        onClick={() => router.visit('/employee/documents/request')}
                        className="gap-2"
                    >
                        <FileText className="h-4 w-4" />
                        New Request
                    </Button>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Total Requests</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.total}</p>
                                </div>
                                <FileText className="h-8 w-8 text-blue-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Pending</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.pending}</p>
                                </div>
                                <Clock className="h-8 w-8 text-yellow-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Approved</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.approved}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-green-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Declined</p>
                                    <p className="text-2xl font-bold mt-1">{statistics.rejected}</p>
                                </div>
                                <XCircle className="h-8 w-8 text-red-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">Status</label>
                                <Select value={filters.status} onValueChange={handleStatusFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="processing">Processing</SelectItem>
                                        <SelectItem value="completed">Approved</SelectItem>
                                        <SelectItem value="rejected">Declined</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-2 block">Document Type</label>
                                <Select
                                    value={filters.document_type}
                                    onValueChange={handleDocumentTypeFilter}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="certificate_of_employment">
                                            Certificate of Employment
                                        </SelectItem>
                                        <SelectItem value="payslip">Payslip</SelectItem>
                                        <SelectItem value="bir_form_2316">BIR Form 2316</SelectItem>
                                        <SelectItem value="government_compliance">
                                            Government Compliance
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Pending Requests Alert */}
                {statistics.pending > 0 && (
                    <Alert>
                        <Clock className="h-4 w-4" />
                        <AlertDescription>
                            You have {statistics.pending} pending request{statistics.pending > 1 ? 's' : ''}.
                            HR Staff will review and process your request shortly.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Requests List */}
                {requests.length === 0 ? (
                    <Card>
                        <CardContent className="pt-12 pb-12 text-center">
                            <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No requests found</h3>
                            <p className="text-gray-600 mb-4">
                                You haven't submitted any document requests yet.
                            </p>
                            <Button onClick={() => router.visit('/employee/documents/request')}>
                                Submit Your First Request
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {requests.map((request) => (
                            <Card key={request.id}>
                                <CardContent className="pt-6">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <h3 className="text-lg font-semibold">
                                                    {request.document_type}
                                                </h3>
                                                <Badge
                                                    variant="outline"
                                                    className={`flex items-center gap-1 ${getStatusBadgeClass(
                                                        request.status_display.color
                                                    )}`}
                                                >
                                                    {getStatusIcon(request.status_display.icon)}
                                                    {request.status_display.label}
                                                </Badge>
                                                {request.days_pending !== null &&
                                                    request.days_pending >= 3 && (
                                                        <Badge variant="outline" className="bg-orange-100 text-orange-800">
                                                            {request.days_pending} days pending
                                                        </Badge>
                                                    )}
                                            </div>

                                            <p className="text-sm text-gray-600 mb-3">
                                                {request.status_display.description}
                                            </p>

                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <span className="font-medium">Purpose:</span>{' '}
                                                    {request.purpose}
                                                </div>
                                                {request.period && (
                                                    <div>
                                                        <span className="font-medium">Period:</span>{' '}
                                                        {request.period}
                                                    </div>
                                                )}
                                                <div>
                                                    <span className="font-medium">Requested:</span>{' '}
                                                    {request.requested_at}
                                                </div>
                                                {request.processed_at && (
                                                    <div>
                                                        <span className="font-medium">Processed:</span>{' '}
                                                        {request.processed_at}
                                                    </div>
                                                )}
                                                {request.processed_by && (
                                                    <div>
                                                        <span className="font-medium">Processed By:</span>{' '}
                                                        {request.processed_by}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Rejection Reason */}
                                            {request.rejection_reason && (
                                                <Alert variant="destructive" className="mt-4">
                                                    <AlertCircle className="h-4 w-4" />
                                                    <AlertDescription>
                                                        <strong>Reason:</strong> {request.rejection_reason}
                                                    </AlertDescription>
                                                </Alert>
                                            )}

                                            {/* HR Notes */}
                                            {request.notes && request.status !== 'rejected' && (
                                                <Alert className="mt-4">
                                                    <AlertDescription>
                                                        <strong>Note:</strong> {request.notes}
                                                    </AlertDescription>
                                                </Alert>
                                            )}
                                        </div>

                                        {/* Actions */}
                                        <div className="flex flex-col gap-2 ml-4">
                                            {request.status === 'completed' && request.file_path && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleDownload(request)}
                                                    className="gap-2"
                                                >
                                                    <Download className="h-4 w-4" />
                                                    Download
                                                </Button>
                                            )}

                                            {request.can_cancel && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleCancelClick(request)}
                                                    className="gap-2"
                                                >
                                                    <X className="h-4 w-4" />
                                                    Cancel
                                                </Button>
                                            )}

                                            {request.can_resubmit && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleResubmit(request)}
                                                    className="gap-2"
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                    Re-submit
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Cancel Confirmation Dialog */}
            <AlertDialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Cancel Document Request?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to cancel this request for{' '}
                            <strong>{selectedRequest?.document_type}</strong>? This action cannot be
                            undone. You can submit a new request if needed.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep Request</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmCancel} className="bg-red-600 hover:bg-red-700">
                            Yes, Cancel Request
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}