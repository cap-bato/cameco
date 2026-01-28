import { useState, useEffect, useCallback, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Checkbox } from '@/components/ui/checkbox';
import {
    FileText,
    Clock,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Search,
    MoreVertical,
    RefreshCw,
    AlertCircle,
    FileQuestion,
    Download,
    Eye,
    Settings,
} from 'lucide-react';
import { PermissionGate } from '@/components/permission-gate';
import { ProcessRequestModal } from '@/components/hr/process-request-modal';
import { RequestDetailsModal } from '@/components/hr/request-details-modal';
import { formatDistanceToNow, format } from 'date-fns';
import { useToast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface DocumentRequest {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
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

interface RequestsIndexProps {
    requests: DocumentRequest[];
    statistics: {
        pending: number;
        processing: number;
        completed: number;
        rejected: number;
    };
    filters?: {
        status?: string;
        document_type?: string;
        priority?: string;
        date_from?: string;
        date_to?: string;
        search?: string;
    };
}

// ============================================================================
// Helper Functions
// ============================================================================

function getPriorityBadge(priority: string) {
    const priorityConfig: Record<string, { color: string; icon: React.ReactNode; label: string }> = {
        urgent: {
            color: 'bg-red-100 text-red-800 border-red-300',
            icon: <AlertTriangle className="h-3 w-3" />,
            label: 'Urgent',
        },
        high: {
            color: 'bg-orange-100 text-orange-800 border-orange-300',
            icon: <AlertCircle className="h-3 w-3" />,
            label: 'High',
        },
        normal: {
            color: 'bg-gray-100 text-gray-800 border-gray-300',
            icon: <Clock className="h-3 w-3" />,
            label: 'Normal',
        },
    };

    const config = priorityConfig[priority] || priorityConfig.normal;

    return (
        <Badge variant="outline" className={`inline-flex items-center gap-1 ${config.color}`}>
            {config.icon}
            {config.label}
        </Badge>
    );
}

function getStatusBadge(status: string, processedBy?: string | null) {
    const statusConfig: Record<string, { color: string; icon: React.ReactNode; label: string }> = {
        pending: {
            color: 'bg-yellow-100 text-yellow-800',
            icon: <Clock className="h-3 w-3" />,
            label: 'Pending',
        },
        processing: {
            color: 'bg-blue-100 text-blue-800',
            icon: <FileText className="h-3 w-3" />,
            label: 'Processing',
        },
        completed: {
            color: 'bg-green-100 text-green-800',
            icon: <CheckCircle className="h-3 w-3" />,
            label: 'Completed',
        },
        rejected: {
            color: 'bg-red-100 text-red-800',
            icon: <XCircle className="h-3 w-3" />,
            label: 'Rejected',
        },
    };

    const config = statusConfig[status] || statusConfig.pending;

    return (
        <div className="flex flex-col gap-0.5">
            <Badge className={`inline-flex items-center gap-1 ${config.color}`}>
                {config.icon}
                {config.label}
            </Badge>
            {processedBy && status !== 'pending' && (
                <span className="text-xs text-gray-500">by {processedBy}</span>
            )}
        </div>
    );
}

function getRelativeTime(dateString: string): string {
    try {
        return formatDistanceToNow(new Date(dateString), { addSuffix: true });
    } catch {
        return dateString;
    }
}

function truncateText(text: string, maxLength: number): string {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// ============================================================================
// Main Component
// ============================================================================

export default function RequestsIndex({ requests: initialRequests, statistics: initialStats, filters: initialFilters }: RequestsIndexProps) {
    // Mock data fallback
    const mockRequests = useMemo(() => initialRequests || [], [initialRequests]);
    const mockStats = useMemo(() => initialStats || { pending: 0, processing: 0, completed: 0, rejected: 0 }, [initialStats]);

    // State
    const { toast } = useToast();
    const [requests, setRequests] = useState<DocumentRequest[]>(mockRequests);
    const [statistics, setStatistics] = useState(initialStats || { pending: 0, processing: 0, completed: 0, rejected: 0 });
    const [selectedRequests, setSelectedRequests] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState(initialFilters?.search || '');
    const [statusFilter, setStatusFilter] = useState(initialFilters?.status || 'all');
    const [documentTypeFilter, setDocumentTypeFilter] = useState(initialFilters?.document_type || 'all');
    const [priorityFilter, setPriorityFilter] = useState(initialFilters?.priority || 'all');
    const [isProcessModalOpen, setIsProcessModalOpen] = useState(false);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<DocumentRequest | null>(null);

    // Fetch requests from API
    const fetchRequests = useCallback(async () => {
        try {
            const response = await fetch('/hr/documents/requests', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            setRequests(result.data || mockRequests);
            setStatistics(result.meta || mockStats);
        } catch (error) {
            console.error('Failed to fetch requests:', error);
            // Fallback to mock data
            setRequests(mockRequests);
            setStatistics(mockStats);
            toast({
                title: 'Loading fallback data',
                description: 'Using cached request data',
                variant: 'default',
            });
        }
    }, [mockRequests, mockStats, toast]);

    // Initialize data on mount
    useEffect(() => {
        fetchRequests();
    }, [fetchRequests]);

    // Filter logic
    const filteredRequests = requests.filter((request) => {
        if (statusFilter !== 'all' && request.status !== statusFilter) return false;
        if (documentTypeFilter !== 'all' && request.document_type !== documentTypeFilter) return false;
        if (priorityFilter !== 'all' && request.priority !== priorityFilter) return false;
        if (searchTerm) {
            const search = searchTerm.toLowerCase();
            return (
                request.employee_name.toLowerCase().includes(search) ||
                request.employee_number.toLowerCase().includes(search) ||
                request.document_type.toLowerCase().includes(search) ||
                `REQ-${String(request.id).padStart(3, '0')}`.toLowerCase().includes(search)
            );
        }
        return true;
    });

    // Sort: urgent priority first, then by request date
    const sortedRequests = [...filteredRequests].sort((a, b) => {
        if (a.priority === 'urgent' && b.priority !== 'urgent') return -1;
        if (a.priority !== 'urgent' && b.priority === 'urgent') return 1;
        return new Date(b.requested_at).getTime() - new Date(a.requested_at).getTime();
    });

    // Handlers
    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            const pendingIds = sortedRequests
                .filter((r) => r.status === 'pending')
                .map((r) => r.id);
            setSelectedRequests(pendingIds);
        } else {
            setSelectedRequests([]);
        }
    };

    const handleSelectRequest = (requestId: number, checked: boolean) => {
        if (checked) {
            setSelectedRequests([...selectedRequests, requestId]);
        } else {
            setSelectedRequests(selectedRequests.filter((id) => id !== requestId));
        }
    };

    const handleProcessRequest = (request: DocumentRequest) => {
        setSelectedRequest(request);
        setIsProcessModalOpen(true);
    };

    const handleViewDetails = (request: DocumentRequest) => {
        setSelectedRequest(request);
        setIsDetailsModalOpen(true);
    };

    const handleRefresh = () => {
        fetchRequests();
    };

    const handleDownloadDocument = async (request: DocumentRequest) => {
        if (!request.generated_document_path) {
            toast({
                title: 'Error',
                description: 'Document path not available',
                variant: 'destructive',
            });
            return;
        }

        try {
            const response = await fetch(request.generated_document_path, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to download document');
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${request.document_type.replace(/\s+/g, '-')}_${request.employee_number}.pdf`;
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
        }
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setStatusFilter('all');
        setDocumentTypeFilter('all');
        setPriorityFilter('all');
    };

    const handleQuickFilter = (filter: 'urgent' | 'pending' | 'my-assignments') => {
        if (filter === 'urgent') {
            setPriorityFilter('urgent');
        } else if (filter === 'pending') {
            setStatusFilter('pending');
        }
        // 'my-assignments' would need backend support
    };

    return (
        <AppLayout>
            <Head title="Document Requests" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Document Requests</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Process employee document requests for COE, payslips, and other documents
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                        >
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Requests</CardTitle>
                            <Clock className="h-8 w-8 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.pending}</div>
                            <p className="text-xs text-gray-600">Requires immediate attention</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Processing</CardTitle>
                            <FileText className="h-8 w-8 text-yellow-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.processing}</div>
                            <p className="text-xs text-gray-600">Being handled by HR staff</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Completed Today</CardTitle>
                            <CheckCircle className="h-8 w-8 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.completed}</div>
                            <p className="text-xs text-gray-600">Successfully processed</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Rejected This Week</CardTitle>
                            <XCircle className="h-8 w-8 text-gray-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.rejected}</div>
                            <p className="text-xs text-gray-600">Requests declined</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-5">
                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-500" />
                                <Input
                                    placeholder="Search requests..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-8"
                                />
                            </div>

                            {/* Status Filter */}
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="processing">Processing</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                </SelectContent>
                            </Select>

                            {/* Document Type Filter */}
                            <Select value={documentTypeFilter} onValueChange={setDocumentTypeFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Document Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="Certificate of Employment">Certificate of Employment</SelectItem>
                                    <SelectItem value="Payslip">Payslip</SelectItem>
                                    <SelectItem value="BIR Form 2316">BIR Form 2316</SelectItem>
                                    <SelectItem value="SSS/PhilHealth/Pag-IBIG Contribution">SSS/PhilHealth/Pag-IBIG</SelectItem>
                                    <SelectItem value="Leave Credits Statement">Leave Credits Statement</SelectItem>
                                    <SelectItem value="Employment Contract">Employment Contract</SelectItem>
                                </SelectContent>
                            </Select>

                            {/* Priority Filter */}
                            <Select value={priorityFilter} onValueChange={setPriorityFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Priority" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Priorities</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="normal">Normal</SelectItem>
                                </SelectContent>
                            </Select>

                            {/* Clear Filters */}
                            <Button variant="outline" onClick={handleClearFilters}>
                                Clear Filters
                            </Button>
                        </div>

                        {/* Quick Filters */}
                        <div className="flex gap-2 mt-4">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleQuickFilter('urgent')}
                            >
                                Urgent Only
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleQuickFilter('pending')}
                            >
                                Pending Approval
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Actions Toolbar */}
                {selectedRequests.length > 0 && (
                    <Card className="bg-blue-50 border-blue-200">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="text-sm font-medium">
                                    {selectedRequests.length} request(s) selected
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button size="sm" variant="outline">
                                        Assign to Me
                                    </Button>
                                    <Button size="sm" variant="outline">
                                        Mark as Processing
                                    </Button>
                                    <Button size="sm" variant="outline">
                                        Export Selected
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setSelectedRequests([])}
                                    >
                                        Clear Selection
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Requests Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Requests ({sortedRequests.length})</CardTitle>
                        <CardDescription>
                            View and process employee document requests
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sortedRequests.length === 0 ? (
                            <div className="text-center py-12">
                                <FileQuestion className="mx-auto h-12 w-12 text-gray-400" />
                                <h3 className="mt-2 text-sm font-semibold text-gray-900">
                                    No requests found
                                </h3>
                                <p className="mt-1 text-sm text-gray-500">
                                    Try adjusting your filters or search criteria
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="border-b bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                <Checkbox
                                                    checked={
                                                        selectedRequests.length > 0 &&
                                                        selectedRequests.length ===
                                                            sortedRequests.filter((r) => r.status === 'pending')
                                                                .length
                                                    }
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Request ID
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Employee
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Department
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Document Type
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Purpose
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Priority
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Request Date
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y bg-white">
                                        {sortedRequests.map((request) => (
                                            <tr
                                                key={request.id}
                                                className={`hover:bg-gray-50 ${
                                                    request.priority === 'urgent'
                                                        ? 'border-l-4 border-l-red-500'
                                                        : request.priority === 'high'
                                                        ? 'border-l-2 border-l-orange-500'
                                                        : ''
                                                }`}
                                            >
                                                <td className="px-4 py-4">
                                                    {request.status === 'pending' && (
                                                        <Checkbox
                                                            checked={selectedRequests.includes(request.id)}
                                                            onCheckedChange={(checked) =>
                                                                handleSelectRequest(
                                                                    request.id,
                                                                    checked as boolean
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge variant="outline" className="font-mono">
                                                        REQ-{String(request.id).padStart(3, '0')}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex flex-col">
                                                        <Link
                                                            href={`/hr/employees/${request.employee_id}`}
                                                            className="font-medium text-blue-600 hover:text-blue-800"
                                                        >
                                                            {request.employee_name}
                                                        </Link>
                                                        <span className="text-xs text-gray-500">
                                                            {request.employee_number}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-sm">
                                                    {request.department}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <FileText className="h-4 w-4 text-gray-500" />
                                                        <span className="font-medium text-sm">
                                                            {request.document_type}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-sm max-w-xs">
                                                    <span title={request.purpose}>
                                                        {truncateText(request.purpose, 50)}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {getPriorityBadge(request.priority)}
                                                </td>
                                                <td className="px-4 py-4 text-sm">
                                                    <div
                                                        className="flex flex-col"
                                                        title={format(
                                                            new Date(request.requested_at),
                                                            'PPpp'
                                                        )}
                                                    >
                                                        <span>{getRelativeTime(request.requested_at)}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    {getStatusBadge(request.status, request.processed_by)}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => handleViewDetails(request)}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            {(request.status === 'pending' ||
                                                                request.status === 'processing') && (
                                                                <PermissionGate permission="hr.documents.view">
                                                                    <DropdownMenuItem
                                                                        onClick={() =>
                                                                            handleProcessRequest(request)
                                                                        }
                                                                    >
                                                                        <Settings className="mr-2 h-4 w-4" />
                                                                        Process Request
                                                                    </DropdownMenuItem>
                                                                </PermissionGate>
                                                            )}
                                                            {request.status === 'completed' &&
                                                                request.generated_document_path && (
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDownloadDocument(request)}
                                                                    >
                                                                        <Download className="mr-2 h-4 w-4" />
                                                                        Download Document
                                                                    </DropdownMenuItem>
                                                                )}
                                                            {request.status === 'rejected' &&
                                                                request.rejection_reason && (
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleViewDetails(request)}
                                                                    >
                                                                        <AlertCircle className="mr-2 h-4 w-4" />
                                                                        View Rejection Reason
                                                                    </DropdownMenuItem>
                                                                )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Modals */}
            {selectedRequest && (
                <>
                    <ProcessRequestModal
                        open={isProcessModalOpen}
                        onClose={() => {
                            setIsProcessModalOpen(false);
                            setSelectedRequest(null);
                        }}
                        request={selectedRequest}
                    />
                    <RequestDetailsModal
                        open={isDetailsModalOpen}
                        onClose={() => {
                            setIsDetailsModalOpen(false);
                            setSelectedRequest(null);
                        }}
                        request={selectedRequest}
                    />
                </>
            )}
        </AppLayout>
    );
}
