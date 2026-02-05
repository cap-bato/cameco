import { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import {
    FileText,
    Building,
    Calendar,
    User,
    CheckCircle,
    Clock,
    XCircle,
    AlertCircle,
    Download,
    Printer,
    Loader2,
} from 'lucide-react';
import { format } from 'date-fns';

interface DocumentRequest {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    position?: string;
    email?: string;
    phone?: string;
    date_hired?: string;
    employment_status?: string;
    document_type: string;
    purpose: string;
    priority: 'urgent' | 'high' | 'normal';
    status: 'pending' | 'processing' | 'completed' | 'rejected';
    requested_at: string;
    processed_by: string | null;
    processed_at: string | null;
    generated_document_path?: string | null;
    rejection_reason?: string | null;
}

interface DocumentHistory {
    id: number;
    document_type: string;
    status: string;
    requested_at: string;
    processed_by?: string;
    downloaded_at?: string;
}

interface RequestStatistics {
    total_requests: number;
    most_requested_type: string;
    average_processing_time_minutes: number;
    success_rate_percentage: number;
}

interface AuditTrailEntry {
    id: number;
    action: string;
    description: string;
    timestamp: string;
    user: string;
    event_type: string;
}

interface RequestDetailsModalProps {
    open: boolean;
    onClose: () => void;
    request: DocumentRequest;
    onProcessClick?: () => void;
}

export function RequestDetailsModal({ open, onClose, request, onProcessClick }: RequestDetailsModalProps) {
    const { toast } = useToast();
    const [loading, setLoading] = useState(false);
    const [fullRequest, setFullRequest] = useState<DocumentRequest>(request);
    const [history, setHistory] = useState<DocumentHistory[]>([]);
    const [statistics, setStatistics] = useState<RequestStatistics | null>(null);
    const [auditTrail, setAuditTrail] = useState<AuditTrailEntry[]>([]);
    const [downloading, setDownloading] = useState(false);

    // Fetch full request details with history and audit trail
    useEffect(() => {
        if (!open || !request.id) return;

        const fetchRequestDetails = async () => {
            setLoading(true);
            try {
                const response = await fetch(`/hr/documents/requests/${request.id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) throw new Error('Failed to fetch request details');
                
                const result = await response.json();
                
                if (result.data) {
                    setFullRequest((prev) => ({ ...prev, ...result.data }));
                }
                if (result.history) {
                    setHistory(result.history);
                }
                if (result.statistics) {
                    setStatistics(result.statistics);
                }
                if (result.audit_trail) {
                    setAuditTrail(result.audit_trail);
                }
            } catch (error) {
                console.error('Error fetching request details:', error);
                toast({
                    title: 'Error',
                    description: 'Failed to load request details',
                    variant: 'destructive',
                });
            } finally {
                setLoading(false);
            }
        };

        fetchRequestDetails();
    }, [open, request.id, toast]);

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'pending':
                return <Clock className="h-5 w-5 text-yellow-500" />;
            case 'processing':
                return <FileText className="h-5 w-5 text-blue-500" />;
            case 'completed':
                return <CheckCircle className="h-5 w-5 text-green-500" />;
            case 'rejected':
                return <XCircle className="h-5 w-5 text-red-500" />;
            default:
                return <AlertCircle className="h-5 w-5 text-gray-500" />;
        }
    };

    const getPriorityBadge = (priority: string) => {
        const variants: Record<string, string> = {
            urgent: 'bg-red-100 text-red-800',
            high: 'bg-orange-100 text-orange-800',
            normal: 'bg-gray-100 text-gray-800',
        };
        return (
            <Badge className={variants[priority] || variants.normal}>
                {priority.toUpperCase()}
            </Badge>
        );
    };

    const getEventTypeColor = (eventType: string): string => {
        switch (eventType) {
            case 'submitted':
                return 'bg-blue-100 text-blue-600';
            case 'assigned':
                return 'bg-purple-100 text-purple-600';
            case 'processing':
                return 'bg-yellow-100 text-yellow-600';
            case 'generated':
                return 'bg-green-100 text-green-600';
            case 'uploaded':
                return 'bg-green-100 text-green-600';
            case 'rejected':
                return 'bg-red-100 text-red-600';
            case 'email_sent':
                return 'bg-indigo-100 text-indigo-600';
            case 'downloaded':
                return 'bg-teal-100 text-teal-600';
            default:
                return 'bg-gray-100 text-gray-600';
        }
    };

    const handleDownloadDocument = async () => {
        if (!fullRequest.generated_document_path) {
            toast({
                title: 'Error',
                description: 'Document path not available',
                variant: 'destructive',
            });
            return;
        }

        setDownloading(true);
        try {
            const response = await fetch(fullRequest.generated_document_path);
            if (!response.ok) throw new Error('Failed to download document');

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${fullRequest.document_type.replace(/\s+/g, '-')}_${fullRequest.employee_number}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);

            toast({
                title: 'Success',
                description: 'Document downloaded successfully',
            });
        } catch (error) {
            console.error('Download error:', error);
            toast({
                title: 'Error',
                description: 'Failed to download document',
                variant: 'destructive',
            });
        } finally {
            setDownloading(false);
        }
    };

    const handlePrint = () => {
        window.print();
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <DialogTitle className="flex items-center gap-2">
                                <Badge variant="outline" className="font-mono">
                                    REQ-{String(fullRequest.id).padStart(3, '0')}
                                </Badge>
                                {getStatusIcon(fullRequest.status)}
                                <span className="capitalize">{fullRequest.status}</span>
                            </DialogTitle>
                            <DialogDescription>
                                Detailed information about this document request
                            </DialogDescription>
                        </div>
                        {getPriorityBadge(fullRequest.priority)}
                    </div>
                </DialogHeader>

                <Tabs defaultValue="info" className="mt-4">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="info">Request Information</TabsTrigger>
                        <TabsTrigger value="history">Document History</TabsTrigger>
                        <TabsTrigger value="audit">Audit Trail</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Request Information */}
                    <TabsContent value="info" className="space-y-4">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                            </div>
                        ) : (
                            <>
                                {/* Employee Information */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Employee Information</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-start gap-4">
                                            <Avatar className="h-16 w-16">
                                                <AvatarFallback className="text-lg">
                                                    {fullRequest.employee_name
                                                        .split(' ')
                                                        .map((n) => n[0])
                                                        .join('')
                                                        .toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 space-y-3">
                                                <div>
                                                    <h3 className="font-semibold text-lg">
                                                        {fullRequest.employee_name}
                                                    </h3>
                                                    <p className="text-sm text-gray-600">
                                                        {fullRequest.employee_number}
                                                    </p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-4 text-sm">
                                                    <div className="flex items-center gap-2">
                                                        <Building className="h-4 w-4 text-gray-500" />
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Department
                                                            </div>
                                                            <div className="font-medium">
                                                                {fullRequest.department}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <User className="h-4 w-4 text-gray-500" />
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Position
                                                            </div>
                                                            <div className="font-medium">
                                                                {fullRequest.position || 'N/A'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {fullRequest.email && (
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Email
                                                            </div>
                                                            <div className="font-medium text-sm">
                                                                {fullRequest.email}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {fullRequest.phone && (
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Phone
                                                            </div>
                                                            <div className="font-medium text-sm">
                                                                {fullRequest.phone}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {fullRequest.date_hired && (
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Date Hired
                                                            </div>
                                                            <div className="font-medium text-sm">
                                                                {format(
                                                                    new Date(fullRequest.date_hired),
                                                                    'MMM dd, yyyy'
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {fullRequest.employment_status && (
                                                        <div>
                                                            <div className="text-xs text-gray-500">
                                                                Employment Status
                                                            </div>
                                                            <div className="font-medium text-sm capitalize">
                                                                {fullRequest.employment_status}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Request Details */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Request Details</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="text-sm text-gray-500">
                                                    Document Type
                                                </label>
                                                <div className="flex items-center gap-2 mt-1">
                                                    <FileText className="h-4 w-4 text-blue-600" />
                                                    <span className="font-medium">
                                                        {fullRequest.document_type}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="text-sm text-gray-500">
                                                    Priority
                                                </label>
                                                <div className="mt-1">
                                                    {getPriorityBadge(fullRequest.priority)}
                                                </div>
                                            </div>
                                            <div>
                                                <label className="text-sm text-gray-500">
                                                    Request Date
                                                </label>
                                                <div className="flex items-center gap-2 mt-1">
                                                    <Calendar className="h-4 w-4 text-gray-500" />
                                                    <span className="font-medium">
                                                        {format(new Date(fullRequest.requested_at), 'PPpp')}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="text-sm text-gray-500">Status</label>
                                                <div className="flex items-center gap-2 mt-1">
                                                    {getStatusIcon(fullRequest.status)}
                                                    <span className="font-medium capitalize">
                                                        {fullRequest.status}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <label className="text-sm text-gray-500">
                                                Purpose / Reason
                                            </label>
                                            <p className="mt-1 p-3 bg-gray-50 rounded-md text-sm">
                                                {fullRequest.purpose}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Processing Information */}
                                {fullRequest.status !== 'pending' && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">
                                                Processing Information
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {fullRequest.processed_by && (
                                                <div>
                                                    <label className="text-sm text-gray-500">
                                                        Processed By
                                                    </label>
                                                    <p className="font-medium">
                                                        {fullRequest.processed_by}
                                                    </p>
                                                </div>
                                            )}
                                            {fullRequest.processed_at && (
                                                <div>
                                                    <label className="text-sm text-gray-500">
                                                        Processed Date
                                                    </label>
                                                    <p className="font-medium">
                                                        {format(
                                                            new Date(fullRequest.processed_at),
                                                            'PPpp'
                                                        )}
                                                    </p>
                                                </div>
                                            )}
                                            {fullRequest.status === 'rejected' &&
                                                fullRequest.rejection_reason && (
                                                    <div>
                                                        <label className="text-sm text-gray-500">
                                                            Rejection Reason
                                                        </label>
                                                        <p className="mt-1 p-3 bg-red-50 border border-red-200 rounded-md text-sm">
                                                            {fullRequest.rejection_reason}
                                                        </p>
                                                    </div>
                                                )}
                                            {fullRequest.status === 'completed' &&
                                                fullRequest.generated_document_path && (
                                                    <div>
                                                        <label className="text-sm text-gray-500">
                                                            Generated Document
                                                        </label>
                                                        <p className="mt-1 p-3 bg-green-50 border border-green-200 rounded-md text-sm font-mono">
                                                            {fullRequest.generated_document_path}
                                                        </p>
                                                    </div>
                                                )}
                                        </CardContent>
                                    </Card>
                                )}
                            </>
                        )}
                    </TabsContent>

                    {/* Tab 2: Document History */}
                    <TabsContent value="history">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                            </div>
                        ) : (
                            <>
                                {/* Statistics Card */}
                                {statistics && (
                                    <Card className="mb-4">
                                        <CardHeader>
                                            <CardTitle className="text-lg">Statistics</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-4 gap-4">
                                                <div className="text-center">
                                                    <div className="text-2xl font-bold text-blue-600">
                                                        {statistics.total_requests}
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Total Requests
                                                    </p>
                                                </div>
                                                <div className="text-center">
                                                    <div className="text-2xl font-bold text-purple-600">
                                                        {statistics.most_requested_type}
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Most Requested
                                                    </p>
                                                </div>
                                                <div className="text-center">
                                                    <div className="text-2xl font-bold text-orange-600">
                                                        {Math.round(
                                                            statistics.average_processing_time_minutes
                                                        )}{' '}
                                                        min
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Avg Processing Time
                                                    </p>
                                                </div>
                                                <div className="text-center">
                                                    <div className="text-2xl font-bold text-green-600">
                                                        {statistics.success_rate_percentage.toFixed(1)}
                                                        %
                                                    </div>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        Success Rate
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}

                                {/* Document History Timeline */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Previous Document Requests</CardTitle>
                                        <CardDescription>
                                            History of document requests from this employee
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {history && history.length > 0 ? (
                                            <div className="space-y-4">
                                                {history.map((item) => (
                                                    <div
                                                        key={item.id}
                                                        className="flex items-start gap-4 pb-4 border-b last:border-b-0"
                                                    >
                                                        <div className="flex-shrink-0 mt-1">
                                                            <div
                                                                className={`h-10 w-10 rounded-full flex items-center justify-center ${
                                                                    item.status === 'completed'
                                                                        ? 'bg-green-100'
                                                                        : item.status === 'rejected'
                                                                        ? 'bg-red-100'
                                                                        : 'bg-yellow-100'
                                                                }`}
                                                            >
                                                                <FileText
                                                                    className={`h-5 w-5 ${
                                                                        item.status === 'completed'
                                                                            ? 'text-green-600'
                                                                            : item.status === 'rejected'
                                                                            ? 'text-red-600'
                                                                            : 'text-yellow-600'
                                                                    }`}
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between">
                                                                <h4 className="font-medium">
                                                                    {item.document_type}
                                                                </h4>
                                                                <Badge
                                                                    variant={
                                                                        item.status === 'completed'
                                                                            ? 'default'
                                                                            : 'destructive'
                                                                    }
                                                                    className="capitalize"
                                                                >
                                                                    {item.status}
                                                                </Badge>
                                                            </div>
                                                            <p className="text-sm text-gray-600 mt-1">
                                                                Requested:{' '}
                                                                {format(
                                                                    new Date(item.requested_at),
                                                                    'PPp'
                                                                )}
                                                            </p>
                                                            {item.processed_by && (
                                                                <p className="text-sm text-gray-600">
                                                                    Processed by: {item.processed_by}
                                                                </p>
                                                            )}
                                                            {item.downloaded_at && (
                                                                <p className="text-sm text-gray-600">
                                                                    Downloaded:{' '}
                                                                    {format(
                                                                        new Date(item.downloaded_at),
                                                                        'PPp'
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="text-center py-8 text-gray-500">
                                                <FileText className="h-12 w-12 mx-auto mb-2 text-gray-400" />
                                                <p>No document history available</p>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </TabsContent>

                    {/* Tab 3: Audit Trail */}
                    <TabsContent value="audit">
                        {loading ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                            </div>
                        ) : (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Activity Timeline</CardTitle>
                                    <CardDescription>
                                        Complete audit trail of all actions on this request
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {auditTrail && auditTrail.length > 0 ? (
                                        <div className="space-y-4">
                                            {auditTrail.map((entry, index) => (
                                                <div key={entry.id || index} className="flex gap-4">
                                                    <div className="flex-shrink-0">
                                                        <div
                                                            className={`h-10 w-10 rounded-full flex items-center justify-center ${getEventTypeColor(
                                                                entry.event_type
                                                            )}`}
                                                        >
                                                            <FileText className="h-5 w-5" />
                                                        </div>
                                                    </div>
                                                    <div className="flex-1">
                                                        <p className="font-medium">
                                                            {entry.action}
                                                        </p>
                                                        <p className="text-sm text-gray-600">
                                                            {entry.description}
                                                        </p>
                                                        <div className="flex items-center gap-4 mt-1 text-xs text-gray-500">
                                                            <span>
                                                                {format(
                                                                    new Date(entry.timestamp),
                                                                    'PPpp'
                                                                )}
                                                            </span>
                                                            <span>by {entry.user}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <FileText className="h-12 w-12 mx-auto mb-2 text-gray-400" />
                                            <p>No audit trail entries available</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>

                {/* Footer Actions */}
                <div className="flex items-center justify-between mt-6 pt-6 border-t gap-3">
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>

                    <div className="flex items-center gap-3">
                        {fullRequest.status === 'completed' &&
                            fullRequest.generated_document_path && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePrint}
                                        disabled={downloading}
                                    >
                                        <Printer className="h-4 w-4 mr-2" />
                                        Print
                                    </Button>
                                    <Button
                                        onClick={handleDownloadDocument}
                                        disabled={downloading}
                                        size="sm"
                                    >
                                        {downloading ? (
                                            <>
                                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                Downloading...
                                            </>
                                        ) : (
                                            <>
                                                <Download className="h-4 w-4 mr-2" />
                                                Download Document
                                            </>
                                        )}
                                    </Button>
                                </>
                            )}

                        {(fullRequest.status === 'pending' ||
                            fullRequest.status === 'processing') &&
                            onProcessClick && (
                                <Button onClick={onProcessClick} size="sm">
                                    Process Request
                                </Button>
                            )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
