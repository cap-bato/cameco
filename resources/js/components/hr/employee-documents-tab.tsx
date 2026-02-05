import { useState, useEffect, useCallback } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { useToast } from '@/hooks/use-toast';
import { Progress } from '@/components/ui/progress';
import { format } from 'date-fns';
import { 
    FileText, 
    Upload, 
    Download, 
    Trash2, 
    Eye,
    File,
    FileCheck,
    AlertCircle,
    CheckCircle,
    Clock,
    XCircle,
    Briefcase,
    X,
    Plus,
    CalendarIcon,
    Loader2,
    RefreshCw,
    ThumbsUp,
    ThumbsDown,
    Search,
} from 'lucide-react';

interface Document {
    id: number;
    name: string;
    document_type: string;
    file_name: string;
    file_size: number;
    category: 'personal' | 'educational' | 'employment' | 'medical' | 'contracts' | 'benefits' | 'performance' | 'separation' | 'government' | 'special';
    status: 'pending' | 'approved' | 'rejected';
    uploaded_at: string;
    uploaded_by: string;
    expires_at: string | null;
    notes?: string;
    rejection_reason?: string;
}

interface EmployeeDocumentsTabProps {
    employeeId: number;
    documents?: Document[];
}

interface UploadFormData {
    category: string;
    document_type: string;
    file: File | null;
    expires_at: Date | undefined;
    notes: string;
}

const DOCUMENT_CATEGORIES = [
    { value: 'personal', label: 'Personal' },
    { value: 'educational', label: 'Educational' },
    { value: 'employment', label: 'Employment' },
    { value: 'medical', label: 'Medical' },
    { value: 'contracts', label: 'Contracts' },
    { value: 'benefits', label: 'Benefits' },
    { value: 'performance', label: 'Performance' },
    { value: 'separation', label: 'Separation' },
    { value: 'government', label: 'Government' },
    { value: 'special', label: 'Special' },
];

const DOCUMENT_TYPE_SUGGESTIONS: Record<string, string[]> = {
    personal: ['Birth Certificate', 'Marriage Certificate', 'Valid ID', 'TIN ID', 'Passport'],
    educational: ['Diploma', 'Transcript of Records', 'Certificate', 'Training Certificate'],
    employment: ['COE', 'Service Record', 'Job Description', 'Performance Review'],
    medical: ['Medical Certificate', 'Annual Physical Exam', 'Vaccination Record', 'Health Card'],
    contracts: ['Employment Contract', 'Probationary Contract', 'Regular Contract', 'Consultancy Agreement'],
    benefits: ['SSS E-1', 'PhilHealth', 'Pag-IBIG', 'HMO Card', 'Life Insurance'],
    performance: ['Appraisal Form', 'KPI Report', 'Performance Improvement Plan', 'Award Certificate'],
    separation: ['Clearance Form', 'Exit Interview', 'Final Pay Computation', 'Certificate of Employment'],
    government: ['NBI Clearance', 'Police Clearance', 'Barangay Clearance', 'BIR 2316', 'SSS Contribution'],
    special: ['Memo', 'Incident Report', 'Disciplinary Action', 'Other Documents'],
};

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ACCEPTED_FILE_TYPES = ['.pdf', '.jpg', '.jpeg', '.png', '.docx'];

// Mock data for initial load
const mockDocuments: Document[] = [
    {
        id: 1,
        name: 'Birth Certificate',
        document_type: 'Birth Certificate (PSA)',
        file_name: 'birth_certificate_psa.pdf',
        file_size: 245000,
        category: 'personal',
        status: 'approved',
        uploaded_at: '2024-01-15',
        uploaded_by: 'HR Staff',
        expires_at: null,
    },
    {
        id: 2,
        name: 'NBI Clearance',
        document_type: 'NBI Clearance',
        file_name: 'nbi_clearance_2024.pdf',
        file_size: 180000,
        category: 'government',
        status: 'approved',
        uploaded_at: '2024-03-20',
        uploaded_by: 'HR Staff',
        expires_at: '2025-03-20',
    },
];

export function EmployeeDocumentsTab({ employeeId, documents: initialDocuments }: EmployeeDocumentsTabProps) {
    const { toast } = useToast();
    
    // State management
    const [documents, setDocuments] = useState<Document[]>(initialDocuments || mockDocuments);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [viewingDocument, setViewingDocument] = useState<Document | null>(null);
    const [documentToDelete, setDocumentToDelete] = useState<Document | null>(null);
    const [documentToReject, setDocumentToReject] = useState<Document | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    
    // Upload form state
    const [showUploadForm, setShowUploadForm] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadForm, setUploadForm] = useState<UploadFormData>({
        category: '',
        document_type: '',
        file: null,
        expires_at: undefined,
        notes: '',
    });
    const [uploadErrors, setUploadErrors] = useState<Record<string, string>>({});

    // Fetch documents from API
    const fetchDocuments = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch documents');
            }

            const result = await response.json();
            setDocuments(result.data || []);
            setLoading(false);
        } catch (err) {
            setError('Failed to load documents. Please try again.');
            setLoading(false);
            console.error('Error fetching documents:', err);
        }
    }, [employeeId]);

    // Fetch documents on component mount
    useEffect(() => {
        fetchDocuments();
    }, [fetchDocuments]);

    // Upload document
    const handleUpload = async (e: React.FormEvent) => {
        e.preventDefault();
        setUploadErrors({});

        // Validation
        const errors: Record<string, string> = {};
        if (!uploadForm.category) errors.category = 'Category is required';
        if (!uploadForm.document_type) errors.document_type = 'Document type is required';
        if (!uploadForm.file) errors.file = 'File is required';
        if (uploadForm.file && uploadForm.file.size > MAX_FILE_SIZE) {
            errors.file = `File size must be less than ${MAX_FILE_SIZE / (1024 * 1024)}MB`;
        }
        if (uploadForm.notes && uploadForm.notes.length > 500) {
            errors.notes = 'Notes must be less than 500 characters';
        }

        if (Object.keys(errors).length > 0) {
            setUploadErrors(errors);
            return;
        }

        setIsUploading(true);
        setUploadProgress(0);

        const formData = new FormData();
        formData.append('employee_id', employeeId.toString());
        formData.append('document_category', uploadForm.category);
        formData.append('document_type', uploadForm.document_type);
        if (uploadForm.file) formData.append('file', uploadForm.file);
        if (uploadForm.expires_at) {
            formData.append('expires_at', format(uploadForm.expires_at, 'yyyy-MM-dd'));
        }
        if (uploadForm.notes) formData.append('notes', uploadForm.notes);

        try {
            // Simulate upload progress
            const progressInterval = setInterval(() => {
                setUploadProgress((prev) => {
                    if (prev >= 90) {
                        clearInterval(progressInterval);
                        return 90;
                    }
                    return prev + 10;
                });
            }, 200);

            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: formData,
            });

            clearInterval(progressInterval);
            setUploadProgress(100);

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to upload document');
            }

            const result = await response.json();

            toast({
                title: 'Document uploaded',
                description: result.message || 'The document has been uploaded successfully.',
            });

            setUploadForm({
                category: '',
                document_type: '',
                file: null,
                expires_at: undefined,
                notes: '',
            });
            setShowUploadForm(false);
            setIsUploading(false);
            setUploadProgress(0);
            fetchDocuments();
        } catch (err) {
            console.error('Error uploading document:', err);
            toast({
                variant: 'destructive',
                title: 'Upload failed',
                description: err instanceof Error ? err.message : 'Failed to upload document. Please try again.',
            });
            setIsUploading(false);
            setUploadProgress(0);
        }
    };

    // Download document
    const handleDownload = async (documentId: number) => {
        try {
            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents/${documentId}/download`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to download document');
            }

            const result = await response.json();

            if (result.data?.download_url) {
                // Open download URL in new window
                window.open(result.data.download_url, '_blank');
                
                toast({
                    title: 'Download started',
                    description: result.message || 'Your document download has started.',
                });
            }
        } catch (err) {
            console.error('Error downloading document:', err);
            toast({
                variant: 'destructive',
                title: 'Download failed',
                description: 'Failed to download document. Please try again.',
            });
        }
    };

    // Approve document
    const handleApprove = async (documentId: number) => {
        try {
            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents/${documentId}/approve`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({}),
            });

            if (!response.ok) {
                throw new Error('Failed to approve document');
            }

            const result = await response.json();

            toast({
                title: 'Document approved',
                description: result.message || 'The document has been approved successfully.',
            });
            setViewingDocument(null);
            fetchDocuments();
        } catch (err) {
            console.error('Error approving document:', err);
            toast({
                variant: 'destructive',
                title: 'Approval failed',
                description: 'Failed to approve document. Please try again.',
            });
        }
    };

    // Reject document
    const handleReject = async () => {
        if (!documentToReject || !rejectionReason) return;

        try {
            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents/${documentToReject.id}/reject`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ rejection_reason: rejectionReason }),
            });

            if (!response.ok) {
                throw new Error('Failed to reject document');
            }

            const result = await response.json();

            toast({
                title: 'Document rejected',
                description: result.message || 'The document has been rejected.',
            });
            setDocumentToReject(null);
            setRejectionReason('');
            setViewingDocument(null);
            fetchDocuments();
        } catch (err) {
            console.error('Error rejecting document:', err);
            toast({
                variant: 'destructive',
                title: 'Rejection failed',
                description: 'Failed to reject document. Please try again.',
            });
        }
    };

    // Delete document
    const handleDelete = async () => {
        if (!documentToDelete) return;

        try {
            const response = await fetch(`/hr/api/hr/employees/${employeeId}/documents/${documentToDelete.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to delete document');
            }

            const result = await response.json();

            toast({
                title: 'Document deleted',
                description: result.message || 'The document has been deleted successfully.',
            });
            setDocumentToDelete(null);
            setViewingDocument(null);
            fetchDocuments();
        } catch (err) {
            console.error('Error deleting document:', err);
            toast({
                variant: 'destructive',
                title: 'Deletion failed',
                description: 'Failed to delete document. Please try again.',
            });
        }
    };

    // Helper functions
    const categories = [
        { value: 'all', label: 'All Documents', icon: FileText },
        { value: 'personal', label: 'Personal IDs', icon: File },
        { value: 'government', label: 'Government', icon: FileCheck },
        { value: 'educational', label: 'Educational', icon: FileCheck },
        { value: 'employment', label: 'Employment', icon: Briefcase },
        { value: 'medical', label: 'Medical', icon: FileText },
        { value: 'contracts', label: 'Contracts', icon: FileText },
        { value: 'benefits', label: 'Benefits', icon: FileCheck },
        { value: 'performance', label: 'Performance', icon: FileCheck },
        { value: 'separation', label: 'Separation', icon: File },
        { value: 'special', label: 'Special', icon: File },
    ];

    // Filtered documents
    const filteredDocuments = documents.filter((doc) => {
        if (selectedCategory !== 'all' && doc.category !== selectedCategory) return false;
        if (statusFilter !== 'all' && doc.status !== statusFilter) return false;
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            return (
                doc.document_type.toLowerCase().includes(query) ||
                doc.file_name.toLowerCase().includes(query) ||
                doc.notes?.toLowerCase().includes(query)
            );
        }
        return true;
    });

    const getCategoryBadgeColor = (category: string) => {
        switch (category) {
            case 'personal': return 'bg-blue-100 text-blue-800';
            case 'government': return 'bg-green-100 text-green-800';
            case 'educational': return 'bg-purple-100 text-purple-800';
            case 'employment': return 'bg-cyan-100 text-cyan-800';
            case 'medical': return 'bg-red-100 text-red-800';
            case 'contracts': return 'bg-indigo-100 text-indigo-800';
            case 'benefits': return 'bg-emerald-100 text-emerald-800';
            case 'performance': return 'bg-amber-100 text-amber-800';
            case 'separation': return 'bg-gray-100 text-gray-800';
            case 'special': return 'bg-pink-100 text-pink-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return (
                    <Badge className="bg-green-100 text-green-800">
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Approved
                    </Badge>
                );
            case 'pending':
                return (
                    <Badge className="bg-yellow-100 text-yellow-800">
                        <Clock className="h-3 w-3 mr-1" />
                        Pending
                    </Badge>
                );
            case 'rejected':
                return (
                    <Badge className="bg-red-100 text-red-800">
                        <XCircle className="h-3 w-3 mr-1" />
                        Rejected
                    </Badge>
                );
            default:
                return null;
        }
    };

    const getCategoryLabel = (category: string) => {
        const found = categories.find(c => c.value === category);
        return found ? found.label : 'Other';
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    const formatDate = (dateString: string): string => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    return (
        <div className="space-y-6">
            {/* Header with Upload Button */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold">Employee Documents (201 File)</h3>
                    <p className="text-sm text-muted-foreground">
                        {documents.length} document{documents.length !== 1 ? 's' : ''} • Philippine labor law compliance
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={fetchDocuments} disabled={loading}>
                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        Refresh
                    </Button>
                    <Button onClick={() => setShowUploadForm(!showUploadForm)}>
                        {showUploadForm ? (
                            <>
                                <X className="mr-2 h-4 w-4" />
                                Cancel
                            </>
                        ) : (
                            <>
                                <Plus className="mr-2 h-4 w-4" />
                                Upload Document
                            </>
                        )}
                    </Button>
                </div>
            </div>

            {/* Error State */}
            {error && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        {error}
                        <Button variant="link" className="ml-2 p-0 h-auto" onClick={fetchDocuments}>
                            Try again
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            {/* Upload Form */}
            {showUploadForm && (
                <Card className="p-6">
                    <form onSubmit={handleUpload} className="space-y-4">
                        <div className="flex items-center justify-between mb-4">
                            <h4 className="font-semibold">Upload New Document</h4>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowUploadForm(false)}
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Category */}
                            <div className="space-y-2">
                                <Label htmlFor="category">Category *</Label>
                                <Select
                                    value={uploadForm.category}
                                    onValueChange={(value) =>
                                        setUploadForm({ ...uploadForm, category: value, document_type: '' })
                                    }
                                >
                                    <SelectTrigger id="category">
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DOCUMENT_CATEGORIES.map((cat) => (
                                            <SelectItem key={cat.value} value={cat.value}>
                                                {cat.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {uploadErrors.category && (
                                    <p className="text-sm text-red-600">{uploadErrors.category}</p>
                                )}
                            </div>

                            {/* Document Type */}
                            <div className="space-y-2">
                                <Label htmlFor="document_type">Document Type *</Label>
                                <Input
                                    id="document_type"
                                    placeholder="e.g., Birth Certificate"
                                    value={uploadForm.document_type}
                                    onChange={(e) =>
                                        setUploadForm({ ...uploadForm, document_type: e.target.value })
                                    }
                                    list="document-types"
                                />
                                {uploadForm.category && (
                                    <datalist id="document-types">
                                        {DOCUMENT_TYPE_SUGGESTIONS[uploadForm.category]?.map((type) => (
                                            <option key={type} value={type} />
                                        ))}
                                    </datalist>
                                )}
                                {uploadErrors.document_type && (
                                    <p className="text-sm text-red-600">{uploadErrors.document_type}</p>
                                )}
                            </div>
                        </div>

                        {/* File Upload */}
                        <div className="space-y-2">
                            <Label htmlFor="file">File *</Label>
                            <Input
                                id="file"
                                type="file"
                                accept={ACCEPTED_FILE_TYPES.join(',')}
                                onChange={(e) =>
                                    setUploadForm({ ...uploadForm, file: e.target.files?.[0] || null })
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Max 10MB. Accepted formats: PDF, JPG, PNG, DOCX
                            </p>
                            {uploadErrors.file && (
                                <p className="text-sm text-red-600">{uploadErrors.file}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {/* Expiry Date */}
                            <div className="space-y-2">
                                <Label>Expiry Date (Optional)</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-full justify-start text-left font-normal"
                                        >
                                            <CalendarIcon className="mr-2 h-4 w-4" />
                                            {uploadForm.expires_at ? (
                                                format(uploadForm.expires_at, 'PPP')
                                            ) : (
                                                <span>Pick a date</span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-auto p-0">
                                        <Calendar
                                            mode="single"
                                            selected={uploadForm.expires_at}
                                            onSelect={(date) =>
                                                setUploadForm({ ...uploadForm, expires_at: date })
                                            }
                                            disabled={(date) => date < new Date()}
                                            initialFocus
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>

                        {/* Notes */}
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes (Optional)</Label>
                            <Textarea
                                id="notes"
                                placeholder="Add any additional notes..."
                                rows={3}
                                value={uploadForm.notes}
                                onChange={(e) => setUploadForm({ ...uploadForm, notes: e.target.value })}
                                maxLength={500}
                            />
                            <p className="text-xs text-muted-foreground">
                                {uploadForm.notes.length}/500 characters
                            </p>
                            {uploadErrors.notes && (
                                <p className="text-sm text-red-600">{uploadErrors.notes}</p>
                            )}
                        </div>

                        {/* Upload Progress */}
                        {isUploading && (
                            <div className="space-y-2">
                                <Progress value={uploadProgress} />
                                <p className="text-sm text-center text-muted-foreground">
                                    Uploading... {uploadProgress}%
                                </p>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowUploadForm(false)}
                                disabled={isUploading}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={isUploading}>
                                {isUploading ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Uploading...
                                    </>
                                ) : (
                                    <>
                                        <Upload className="mr-2 h-4 w-4" />
                                        Upload Document
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>
                </Card>
            )}

            {/* Search and Filters */}
            <Card className="p-4 space-y-4">
                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search documents..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-9"
                    />
                </div>

                {/* Status Filter */}
                <div className="flex items-center gap-2">
                    <Label className="text-sm font-medium">Status:</Label>
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Category Filter */}
                <div className="flex flex-wrap gap-2">
                    {categories.map((category) => {
                        const Icon = category.icon;
                        return (
                            <Button
                                key={category.value}
                                variant={selectedCategory === category.value ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setSelectedCategory(category.value)}
                            >
                                <Icon className="mr-2 h-4 w-4" />
                                {category.label}
                            </Button>
                        );
                    })}
                </div>
            </Card>

            {/* Loading State */}
            {loading && (
                <div className="space-y-3">
                    {[1, 2, 3].map((i) => (
                        <Card key={i} className="p-4">
                            <div className="flex items-start space-x-4">
                                <Skeleton className="h-12 w-12 rounded-lg" />
                                <div className="flex-1 space-y-2">
                                    <Skeleton className="h-5 w-1/3" />
                                    <Skeleton className="h-4 w-1/2" />
                                    <Skeleton className="h-3 w-2/3" />
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {/* Documents List or Empty State */}
            {!loading && filteredDocuments.length === 0 ? (
                <Card className="p-12">
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-4 mb-4">
                            <FileText className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold mb-2">No Documents Found</h3>
                        <p className="text-sm text-muted-foreground mb-4 max-w-sm">
                            {searchQuery || statusFilter !== 'all' || selectedCategory !== 'all'
                                ? 'No documents match your filters. Try adjusting your search criteria.'
                                : 'No documents have been uploaded for this employee. Upload the first document to get started.'}
                        </p>
                        {!searchQuery && statusFilter === 'all' && selectedCategory === 'all' && (
                            <Button onClick={() => setShowUploadForm(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Upload First Document
                            </Button>
                        )}
                    </div>
                </Card>
            ) : !loading ? (
                <div className="space-y-3">
                    {filteredDocuments.map((document) => (
                        <Card key={document.id} className="p-4 hover:shadow-md transition-shadow">
                            <div className="flex items-start justify-between">
                                <div className="flex items-start space-x-4 flex-1">
                                    <div className="rounded-lg bg-muted p-3">
                                        <FileText className="h-5 w-5 text-muted-foreground" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <h4 
                                                className="font-medium cursor-pointer hover:text-primary hover:underline"
                                                onClick={() => setViewingDocument(document)}
                                            >
                                                {document.document_type}
                                            </h4>
                                            <Badge className={getCategoryBadgeColor(document.category)}>
                                                {getCategoryLabel(document.category)}
                                            </Badge>
                                            {getStatusBadge(document.status)}
                                        </div>
                                        <p className="text-sm text-muted-foreground mb-2">{document.file_name}</p>
                                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                            <span>{formatFileSize(document.file_size)}</span>
                                            <span>•</span>
                                            <span>Uploaded {formatDate(document.uploaded_at)}</span>
                                            <span>•</span>
                                            <span>By {document.uploaded_by}</span>
                                            {document.expires_at && (
                                                <>
                                                    <span>•</span>
                                                    <span className="text-amber-600 font-medium">
                                                        Expires {formatDate(document.expires_at)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 ml-4">
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        title="View"
                                        onClick={() => setViewingDocument(document)}
                                    >
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        title="Download"
                                        onClick={() => handleDownload(document.id)}
                                    >
                                        <Download className="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        title="Delete" 
                                        className="hover:text-destructive"
                                        onClick={() => setDocumentToDelete(document)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            ) : null}

            {/* Document Viewer Dialog */}
            <Dialog open={!!viewingDocument} onOpenChange={(open) => !open && setViewingDocument(null)}>
                <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col p-0">
                    <DialogHeader className="px-6 pt-6 pb-4 border-b shrink-0">
                        <DialogTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            {viewingDocument?.document_type}
                        </DialogTitle>
                        <DialogDescription>
                            {viewingDocument?.file_name} • {viewingDocument && formatFileSize(viewingDocument.file_size)}
                        </DialogDescription>
                    </DialogHeader>
                    
                    {viewingDocument && (
                        <>
                            {/* Document Info */}
                            <div className="px-6 py-3 bg-muted/30 border-b shrink-0">
                                <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">Category:</span>
                                        <Badge className={getCategoryBadgeColor(viewingDocument.category)}>
                                            {getCategoryLabel(viewingDocument.category)}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">Status:</span>
                                        {getStatusBadge(viewingDocument.status)}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">Uploaded:</span>
                                        <span className="text-xs">{formatDate(viewingDocument.uploaded_at)} by {viewingDocument.uploaded_by}</span>
                                    </div>
                                    {viewingDocument.expires_at && (
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">Expires:</span>
                                            <span className="text-xs text-amber-600 font-medium whitespace-nowrap">
                                                {formatDate(viewingDocument.expires_at)}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Mock Document Preview */}
                            <div className="flex-1 overflow-y-auto px-6 py-6">
                                <div className="min-h-full border rounded-lg bg-gray-50 flex items-center justify-center p-8">
                                    <div className="text-center max-w-2xl">
                                        <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-lg shadow-sm mb-4">
                                            <FileText className="h-10 w-10 text-primary" />
                                        </div>
                                        <h3 className="text-lg font-semibold mb-2">{viewingDocument.document_type}</h3>
                                        <p className="text-sm text-muted-foreground mb-1">{viewingDocument.file_name}</p>
                                        <p className="text-xs text-muted-foreground mb-6">
                                            {formatFileSize(viewingDocument.file_size)} • PDF Document
                                        </p>
                                        
                                        {/* Rejection Reason */}
                                        {viewingDocument.status === 'rejected' && viewingDocument.rejection_reason && (
                                            <Alert variant="destructive" className="mb-4">
                                                <AlertCircle className="h-4 w-4" />
                                                <AlertDescription>
                                                    <strong>Rejection Reason:</strong> {viewingDocument.rejection_reason}
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        {/* Notes */}
                                        {viewingDocument.notes && (
                                            <Alert className="mb-4">
                                                <AlertDescription>
                                                    <strong>Notes:</strong> {viewingDocument.notes}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        
                                        <div className="bg-white border rounded-lg p-6 text-left shadow-sm">
                                            <div className="space-y-2 text-sm text-gray-700">
                                                <div className="pb-3 mb-3 border-b">
                                                    <p className="font-semibold text-lg text-gray-900">
                                                        {viewingDocument.name}
                                                    </p>
                                                </div>
                                                <p className="text-xs italic text-gray-500">
                                                    Document preview will be available once file storage is implemented (Phase 4).
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="px-6 py-4 border-t bg-muted/20 flex items-center justify-between shrink-0">
                                <div className="flex gap-2">
                                    {viewingDocument.status === 'pending' && (
                                        <>
                                            <Button
                                                variant="default"
                                                onClick={() => handleApprove(viewingDocument.id)}
                                            >
                                                <ThumbsUp className="h-4 w-4 mr-2" />
                                                Approve
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                onClick={() => {
                                                    setDocumentToReject(viewingDocument);
                                                    setViewingDocument(null);
                                                }}
                                            >
                                                <ThumbsDown className="h-4 w-4 mr-2" />
                                                Reject
                                            </Button>
                                        </>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" onClick={() => setViewingDocument(null)}>
                                        <X className="h-4 w-4 mr-2" />
                                        Close
                                    </Button>
                                    <Button variant="outline" onClick={() => handleDownload(viewingDocument.id)}>
                                        <Download className="h-4 w-4 mr-2" />
                                        Download
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={!!documentToDelete} onOpenChange={(open) => !open && setDocumentToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Document?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{documentToDelete?.document_type}"? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Reject Document Dialog */}
            <AlertDialog open={!!documentToReject} onOpenChange={(open) => !open && setDocumentToReject(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Reject Document?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Please provide a reason for rejecting "{documentToReject?.document_type}".
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="my-4">
                        <Textarea
                            placeholder="Enter rejection reason..."
                            value={rejectionReason}
                            onChange={(e) => setRejectionReason(e.target.value)}
                            rows={4}
                            className="w-full"
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleReject}
                            disabled={!rejectionReason}
                            className="bg-destructive"
                        >
                            Reject Document
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
