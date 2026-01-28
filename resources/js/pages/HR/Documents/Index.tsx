import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
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
    Upload,
    Download,
    Eye,
    Trash2,
    CheckCircle,
    XCircle,
    Clock,
    AlertTriangle,
    Search,
    MoreVertical,
    FileUp,
    FileSignature,
    AlertCircle,
} from 'lucide-react';
import { PermissionGate, usePermission } from '@/components/permission-gate';
import { useToast } from '@/hooks/use-toast';
import { DocumentUploadModal } from '@/components/hr/document-upload-modal';
import { DocumentViewModal } from '@/components/hr/document-view-modal';
import { DocumentApprovalModal } from '@/components/hr/document-approval-modal';
import { DocumentRejectionModal } from '@/components/hr/document-rejection-modal';
import { DocumentDeleteModal } from '@/components/hr/document-delete-modal';

// ============================================================================
// Type Definitions
// ============================================================================

interface Document {
    id: number;
    employee_id: number;
    employee_number: string;
    employee_name: string;
    department?: string;
    document_category: 'personal' | 'educational' | 'employment' | 'medical' | 'contracts' | 'benefits' | 'performance' | 'separation' | 'government' | 'special';
    document_type: string;
    file_name: string;
    file_size: number;
    file_path: string;
    uploaded_by: string;
    uploaded_at: string;
    status: 'pending' | 'approved' | 'rejected' | 'auto_approved' | 'expired';
    expires_at: string | null;
    notes?: string | null;
    mime_type: string;
}

interface DocumentsIndexProps {
    documents: Document[] | {
        data: Document[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        search?: string;
        department?: string;
        category?: string;
        status?: string;
    };
    meta?: {
        total: number;
        pending_approvals: number;
        expiring_soon: number;
        recently_uploaded: number;
    };
    departments?: { id: number; name: string }[];
    employees?: Array<{ id: number; employee_number: string; name: string; department: string }>;
}

// ============================================================================
// Helper Functions
// ============================================================================

function getCategoryBadgeColor(category: string): string {
    const colorMap: Record<string, string> = {
        personal: 'bg-blue-100 text-blue-800',
        educational: 'bg-purple-100 text-purple-800',
        employment: 'bg-green-100 text-green-800',
        medical: 'bg-red-100 text-red-800',
        contracts: 'bg-orange-100 text-orange-800',
        benefits: 'bg-cyan-100 text-cyan-800',
        performance: 'bg-indigo-100 text-indigo-800',
        separation: 'bg-gray-100 text-gray-800',
        government: 'bg-yellow-100 text-yellow-800',
        special: 'bg-pink-100 text-pink-800',
    };
    return colorMap[category] || 'bg-gray-100 text-gray-800';
}

function getStatusBadge(status: string) {
    const statusConfig: Record<string, { color: string; icon: React.ReactNode; label: string }> = {
        pending: {
            color: 'bg-yellow-100 text-yellow-800',
            icon: <Clock className="h-3 w-3" />,
            label: 'Pending',
        },
        approved: {
            color: 'bg-green-100 text-green-800',
            icon: <CheckCircle className="h-3 w-3" />,
            label: 'Approved',
        },
        rejected: {
            color: 'bg-red-100 text-red-800',
            icon: <XCircle className="h-3 w-3" />,
            label: 'Rejected',
        },
        expired: {
            color: 'bg-gray-100 text-gray-800',
            icon: <AlertCircle className="h-3 w-3" />,
            label: 'Expired',
        },
    };

    const config = statusConfig[status] || statusConfig.pending;

    return (
        <Badge className={`inline-flex items-center gap-1 ${config.color}`}>
            {config.icon}
            {config.label}
        </Badge>
    );
}

function getExpiryWarning(daysUntilExpiry: number | null) {
    if (daysUntilExpiry === null) return null;
    
    if (daysUntilExpiry < 0) {
        return <span title="Expired"><AlertCircle className="h-4 w-4 text-red-500" /></span>;
    } else if (daysUntilExpiry <= 7) {
        return <span title={`Expires in ${daysUntilExpiry} days`}><AlertTriangle className="h-4 w-4 text-orange-500" /></span>;
    } else if (daysUntilExpiry <= 30) {
        return <span title={`Expires in ${daysUntilExpiry} days`}><AlertTriangle className="h-4 w-4 text-yellow-500" /></span>;
    }
    
    return null;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

// ============================================================================
// Component
// ============================================================================

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Documents', href: '/hr/documents' },
];

export default function DocumentsIndex({
    documents = [],
    filters = {},
    meta = {
        total: 0,
        pending_approvals: 0,
        expiring_soon: 0,
        recently_uploaded: 0,
    },
    departments = [],
    employees = [],
}: DocumentsIndexProps) {
    // State
    const [selectedDocuments, setSelectedDocuments] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [departmentFilter, setDepartmentFilter] = useState(filters.department || 'all');
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
    
    // Modal states
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [selectedDocumentId, setSelectedDocumentId] = useState<number | undefined>(undefined);
    const [selectedDocumentName, setSelectedDocumentName] = useState<string>('');
    const [isApprovalModalOpen, setIsApprovalModalOpen] = useState(false);
    const [isRejectionModalOpen, setIsRejectionModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

    // Hooks
    const { toast } = useToast();

    // Permissions
    const { hasPermission } = usePermission();
    const canApprove = hasPermission('hr.documents.approve');
    const canDelete = hasPermission('hr.documents.delete');

    // Ensure documents is an array - handle both direct array and paginated object
    const documentsArray = Array.isArray(documents) 
        ? documents 
        : (documents?.data && Array.isArray(documents.data) ? documents.data : []);

    // Handle upload success - refresh documents list
    const handleUploadSuccess = () => {
        toast({
            title: 'Success',
            description: 'Document uploaded successfully!',
            duration: 3000,
        });
        // Reload the page data by fetching fresh props from the server
        router.get(window.location.href, {}, { preserveState: false });
    };

    // Filter documents
    const filteredDocuments = documentsArray.filter((doc) => {
        if (categoryFilter !== 'all' && doc.document_category !== categoryFilter) return false;
        if (statusFilter !== 'all' && doc.status !== statusFilter) return false;
        if (departmentFilter !== 'all' && doc.department !== departmentFilter) return false;
        if (searchTerm) {
            const query = searchTerm.toLowerCase();
            return (
                doc.employee_name.toLowerCase().includes(query) ||
                doc.employee_number.toLowerCase().includes(query) ||
                doc.document_type.toLowerCase().includes(query) ||
                doc.file_name.toLowerCase().includes(query)
            );
        }
        return true;
    });

    // Handle bulk selection
    const toggleSelectAll = () => {
        if (selectedDocuments.length === filteredDocuments.length) {
            setSelectedDocuments([]);
        } else {
            setSelectedDocuments(filteredDocuments.map((doc) => doc.id));
        }
    };

    const toggleSelectDocument = (id: number) => {
        setSelectedDocuments((prev) =>
            prev.includes(id) ? prev.filter((docId) => docId !== id) : [...prev, id]
        );
    };

    // Handle bulk actions
    const handleBulkApprove = () => {
        if (selectedDocuments.length === 0) return;
        // TODO: Implement bulk approve API call
        console.log('Bulk approve:', selectedDocuments);
    };

    const handleBulkDownload = () => {
        if (selectedDocuments.length === 0) return;
        // TODO: Implement bulk download API call
        console.log('Bulk download:', selectedDocuments);
    };

    const handleBulkDelete = () => {
        if (selectedDocuments.length === 0) return;
        if (confirm(`Are you sure you want to delete ${selectedDocuments.length} documents?`)) {
            // TODO: Implement bulk delete API call
            console.log('Bulk delete:', selectedDocuments);
        }
    };

    // Handle individual document actions
    const handleView = (documentId: number, documentName: string) => {
        setSelectedDocumentId(documentId);
        setSelectedDocumentName(documentName);
        setIsViewModalOpen(true);
    };

    const handleDownload = async (documentId: number) => {
        try {
            const response = await fetch(`/hr/documents/${documentId}/download`);
            
            if (response.ok) {
                // Get the blob from response
                const blob = await response.blob();
                
                // Get filename from Content-Disposition header or use default
                const contentDisposition = response.headers.get('content-disposition');
                let filename = 'document';
                
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename="?([^"]*)"?/);
                    if (match) filename = match[1];
                }
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
                
                toast({
                    title: 'Success',
                    description: 'Document downloaded successfully.',
                });
            } else {
                const data = await response.json();
                toast({
                    title: 'Error',
                    description: data.error || 'Failed to download document.',
                    variant: 'destructive',
                });
            }
        } catch (error) {
            console.error('Error downloading document:', error);
            toast({
                title: 'Error',
                description: 'An unexpected error occurred.',
                variant: 'destructive',
            });
        }
    };

    const handleApprove = (documentId: number, documentName: string) => {
        setSelectedDocumentId(documentId);
        setSelectedDocumentName(documentName);
        setIsApprovalModalOpen(true);
    };

    const handleReject = (documentId: number, documentName: string) => {
        setSelectedDocumentId(documentId);
        setSelectedDocumentName(documentName);
        setIsRejectionModalOpen(true);
    };

    const handleDelete = (documentId: number, documentName: string) => {
        setSelectedDocumentId(documentId);
        setSelectedDocumentName(documentName);
        setIsDeleteModalOpen(true);
    };

    const handleActionSuccess = () => {
        // Refresh the documents list
        router.get(window.location.href, {}, { preserveState: false });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Document Management" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Document Management</h1>
                            <p className="text-muted-foreground">
                                Manage employee documents across the organization
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <PermissionGate permission="hr.documents.templates.manage">
                                <Link href="/hr/documents/templates">
                                    <Button variant="outline" className="gap-2">
                                        <FileSignature className="h-4 w-4" />
                                        Manage Templates
                                    </Button>
                                </Link>
                            </PermissionGate>
                            <Link href="/hr/documents/bulk-upload">
                                <Button variant="outline" className="gap-2">
                                    <FileUp className="h-4 w-4" />
                                    Bulk Upload
                                </Button>
                            </Link>
                            <PermissionGate permission="hr.documents.upload">
                                <Button className="gap-2" onClick={() => setIsUploadModalOpen(true)}>
                                    <Upload className="h-4 w-4" />
                                    Upload Document
                                </Button>
                            </PermissionGate>
                        </div>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Documents</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{meta.total}</div>
                            <p className="text-xs text-muted-foreground">
                                Across all employees
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Approvals</CardTitle>
                            <Clock className="h-4 w-4 text-yellow-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{meta.pending_approvals}</div>
                            <p className="text-xs text-muted-foreground">
                                Requires review
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Expiring Soon</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{meta.expiring_soon}</div>
                            <p className="text-xs text-muted-foreground">
                                Within 30 days
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Recently Uploaded</CardTitle>
                            <Upload className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{meta.recently_uploaded}</div>
                            <p className="text-xs text-muted-foreground">
                                Last 7 days
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-medium">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search employee, document..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-9"
                                />
                            </div>

                            <Select value={departmentFilter} onValueChange={setDepartmentFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Department" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {departments.map((dept) => (
                                        <SelectItem key={dept.id} value={dept.name}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Categories</SelectItem>
                                    <SelectItem value="personal">Personal</SelectItem>
                                    <SelectItem value="educational">Educational</SelectItem>
                                    <SelectItem value="employment">Employment</SelectItem>
                                    <SelectItem value="medical">Medical</SelectItem>
                                    <SelectItem value="contracts">Contracts</SelectItem>
                                    <SelectItem value="benefits">Benefits</SelectItem>
                                    <SelectItem value="performance">Performance</SelectItem>
                                    <SelectItem value="separation">Separation</SelectItem>
                                    <SelectItem value="government">Government</SelectItem>
                                    <SelectItem value="special">Special</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                    <SelectItem value="expired">Expired</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Quick Filters */}
                        <div className="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setStatusFilter('pending')}
                            >
                                Pending My Approval
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setStatusFilter('all');
                                    // TODO: Add expiring filter logic
                                }}
                            >
                                Expiring This Month
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearchTerm('');
                                    setCategoryFilter('all');
                                    setStatusFilter('all');
                                    setDepartmentFilter('all');
                                }}
                            >
                                Clear Filters
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Actions Toolbar */}
                {selectedDocuments.length > 0 && (
                    <Card className="bg-muted/50">
                        <CardContent className="flex items-center justify-between p-4">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium">
                                    {selectedDocuments.length} document(s) selected
                                </span>
                            </div>
                            <div className="flex gap-2">
                                {canApprove && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleBulkApprove}
                                        className="gap-2"
                                    >
                                        <CheckCircle className="h-4 w-4" />
                                        Approve Selected
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleBulkDownload}
                                    className="gap-2"
                                >
                                    <Download className="h-4 w-4" />
                                    Download Selected
                                </Button>
                                {canDelete && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleBulkDelete}
                                        className="gap-2 text-red-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Delete Selected
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Documents Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Documents ({filteredDocuments.length})</CardTitle>
                        <CardDescription>
                            All employee documents across the organization
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {filteredDocuments.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No documents found</h3>
                                <p className="text-muted-foreground mb-4">
                                    Upload your first document to get started
                                </p>
                                <PermissionGate permission="hr.documents.upload">
                                    <Button onClick={() => setIsUploadModalOpen(true)}>
                                        <Upload className="h-4 w-4 mr-2" />
                                        Upload Document
                                    </Button>
                                </PermissionGate>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b">
                                        <tr>
                                            <th className="text-left py-3 px-2">
                                                <Checkbox
                                                    checked={selectedDocuments.length === filteredDocuments.length}
                                                    onCheckedChange={toggleSelectAll}
                                                />
                                            </th>
                                            <th className="text-left py-3 px-2 font-semibold">Employee</th>
                                            <th className="text-left py-3 px-2 font-semibold">Department</th>
                                            <th className="text-left py-3 px-2 font-semibold">Category</th>
                                            <th className="text-left py-3 px-2 font-semibold">Document Type</th>
                                            <th className="text-left py-3 px-2 font-semibold">File Name</th>
                                            <th className="text-left py-3 px-2 font-semibold">Uploaded By</th>
                                            <th className="text-left py-3 px-2 font-semibold">Upload Date</th>
                                            <th className="text-left py-3 px-2 font-semibold">Status</th>
                                            <th className="text-left py-3 px-2 font-semibold">Expiry</th>
                                            <th className="text-left py-3 px-2 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredDocuments.map((doc) => (
                                            <tr
                                                key={doc.id}
                                                className="border-b hover:bg-muted/50"
                                            >
                                                <td className="py-3 px-2" onClick={(e) => e.stopPropagation()}>
                                                    <Checkbox
                                                        checked={selectedDocuments.includes(doc.id)}
                                                        onCheckedChange={() => toggleSelectDocument(doc.id)}
                                                    />
                                                </td>
                                                <td className="py-3 px-2">
                                                    <div className="flex flex-col">
                                                        <Link
                                                            href={`/hr/employees/${doc.employee_id}`}
                                                            className="font-medium text-primary hover:underline"
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            {doc.employee_name}
                                                        </Link>
                                                        <span className="text-xs text-muted-foreground">
                                                            {doc.employee_number}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">{doc.department || '-'}</td>
                                                <td className="py-3 px-2">
                                                    <Badge className={getCategoryBadgeColor(doc.document_category)}>
                                                        {doc.document_category}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-2">{doc.document_type}</td>
                                                <td className="py-3 px-2">
                                                    <div className="flex flex-col">
                                                        <span className="truncate max-w-[200px]" title={doc.file_name}>
                                                            {doc.file_name}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatFileSize(doc.file_size)}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">{doc.uploaded_by}</td>
                                                <td className="py-3 px-2">{new Date(doc.uploaded_at).toLocaleDateString()}</td>
                                                <td className="py-3 px-2">{getStatusBadge(doc.status)}</td>
                                                <td className="py-3 px-2">
                                                    <div className="flex items-center gap-2">
                                                        {doc.expires_at || 'N/A'}
                                                        {doc.expires_at && getExpiryWarning(Math.ceil((new Date(doc.expires_at).getTime() - new Date().getTime()) / (1000 * 3600 * 24)))}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2" onClick={(e) => e.stopPropagation()}>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => handleView(doc.id, doc.file_name)}>
                                                                <Eye className="h-4 w-4 mr-2" />
                                                                View
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleDownload(doc.id)}>
                                                                <Download className="h-4 w-4 mr-2" />
                                                                Download
                                                            </DropdownMenuItem>
                                                            {doc.status === 'pending' && canApprove && (
                                                                <>
                                                                    <DropdownMenuItem onClick={() => handleApprove(doc.id, doc.file_name)}>
                                                                        <CheckCircle className="h-4 w-4 mr-2" />
                                                                        Approve
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => handleReject(doc.id, doc.file_name)}>
                                                                        <XCircle className="h-4 w-4 mr-2" />
                                                                        Reject
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                            {canDelete && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDelete(doc.id, doc.file_name)}
                                                                        className="text-red-600"
                                                                    >
                                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                </>
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

            {/* Document Upload Modal */}
            <DocumentUploadModal
                open={isUploadModalOpen}
                onClose={() => setIsUploadModalOpen(false)}
                onSuccess={handleUploadSuccess}
                employees={employees}
            />

            {/* Document View Modal */}
            <DocumentViewModal
                open={isViewModalOpen}
                onClose={() => setIsViewModalOpen(false)}
                documentId={selectedDocumentId}
                onDownload={(id) => {
                    setIsViewModalOpen(false);
                    handleDownload(id);
                }}
            />

            {/* Document Approval Modal */}
            <DocumentApprovalModal
                open={isApprovalModalOpen}
                onClose={() => setIsApprovalModalOpen(false)}
                documentId={selectedDocumentId}
                documentName={selectedDocumentName}
                onSuccess={handleActionSuccess}
            />

            {/* Document Rejection Modal */}
            <DocumentRejectionModal
                open={isRejectionModalOpen}
                onClose={() => setIsRejectionModalOpen(false)}
                documentId={selectedDocumentId}
                documentName={selectedDocumentName}
                onSuccess={handleActionSuccess}
            />

            {/* Document Delete Modal */}
            <DocumentDeleteModal
                open={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                documentId={selectedDocumentId}
                documentName={selectedDocumentName}
                onSuccess={handleActionSuccess}
            />
        </AppLayout>
    );
}
