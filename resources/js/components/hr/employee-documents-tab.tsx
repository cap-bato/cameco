import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    X
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
}

interface EmployeeDocumentsTabProps {
    employeeId: number;
    documents?: Document[];
}

// Mock data for demonstration
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
    {
        id: 3,
        name: 'Medical Certificate',
        document_type: 'Pre-Employment Medical',
        file_name: 'medical_cert_2024.pdf',
        file_size: 320000,
        category: 'medical',
        status: 'approved',
        uploaded_at: '2024-02-10',
        uploaded_by: 'HR Manager',
        expires_at: '2025-02-10',
    },
    {
        id: 4,
        name: 'Employment Contract',
        document_type: 'Regular Employment Contract',
        file_name: 'employment_contract_signed.pdf',
        file_size: 450000,
        category: 'contracts',
        status: 'approved',
        uploaded_at: '2024-01-20',
        uploaded_by: 'HR Manager',
        expires_at: null,
    },
    {
        id: 5,
        name: 'SSS E-1 Form',
        document_type: 'SSS Registration',
        file_name: 'sss_e1_form.pdf',
        file_size: 150000,
        category: 'government',
        status: 'approved',
        uploaded_at: '2024-01-25',
        uploaded_by: 'HR Staff',
        expires_at: null,
    },
    {
        id: 6,
        name: 'College Diploma',
        document_type: 'Bachelor\'s Degree Diploma',
        file_name: 'diploma_bscs.pdf',
        file_size: 280000,
        category: 'educational',
        status: 'approved',
        uploaded_at: '2024-01-18',
        uploaded_by: 'HR Staff',
        expires_at: null,
    },
];

export function EmployeeDocumentsTab({ employeeId, documents = mockDocuments }: EmployeeDocumentsTabProps) {
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [viewingDocument, setViewingDocument] = useState<Document | null>(null);

    // Philippine 201 File Categories
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

    const filteredDocuments = selectedCategory === 'all' 
        ? documents 
        : documents.filter(doc => doc.category === selectedCategory);

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
                <Button asChild>
                    <Link href={`/hr/documents/upload?employee_id=${employeeId}`}>
                        <Upload className="mr-2 h-4 w-4" />
                        Upload Document
                    </Link>
                </Button>
            </div>

            {/* Category Filter */}
            <Card className="p-4">
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

            {/* Documents List or Empty State */}
            {filteredDocuments.length === 0 ? (
                <Card className="p-12">
                    <div className="flex flex-col items-center justify-center text-center">
                        <div className="rounded-full bg-muted p-4 mb-4">
                            <FileText className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold mb-2">No Documents Yet</h3>
                        <p className="text-sm text-muted-foreground mb-4 max-w-sm">
                            {selectedCategory === 'all' 
                                ? "No documents have been uploaded for this employee. Upload the first document to get started."
                                : `No ${getCategoryLabel(selectedCategory).toLowerCase()} documents found. Try selecting a different category.`
                            }
                        </p>
                        {selectedCategory === 'all' && (
                            <Button>
                                <Upload className="mr-2 h-4 w-4" />
                                Upload First Document
                            </Button>
                        )}
                    </div>
                </Card>
            ) : (
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
                                    <Button variant="ghost" size="sm" title="Download">
                                        <Download className="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" title="Delete" className="hover:text-destructive">
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {/* Development Notice */}
            <Card className="p-6 bg-blue-50 border-blue-200">
                <div className="flex items-start gap-3">
                    <AlertCircle className="h-5 w-5 text-blue-600 mt-0.5" />
                    <div>
                        <h4 className="font-semibold text-blue-900 mb-1">
                            Document Management System (Phase 1 Complete)
                        </h4>
                        <p className="text-sm text-blue-800 mb-2">
                            ✅ Permissions configured (9 permissions for HR Staff & HR Manager)<br/>
                            ✅ Routes configured (18 endpoints for documents, templates, and requests)<br/>
                            ✅ Validation classes created (4 request validators)<br/>
                            ✅ Controllers implemented (10 methods with security audit logging)
                        </p>
                        <p className="text-sm text-blue-800">
                            <strong>Currently showing:</strong> Mock data for demonstration. Database models and file storage implementation pending (Phase 4).
                        </p>
                    </div>
                </div>
            </Card>

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
                            {/* Document Info - Compact */}
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

                            {/* Mock Document Preview - Scrollable */}
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
                                        
                                        {/* Document Content Preview */}
                                        <div className="bg-white border rounded-lg p-6 text-left shadow-sm">
                                            <div className="space-y-2 text-sm text-gray-700">
                                                <div className="pb-3 mb-3 border-b">
                                                    <p className="font-semibold text-lg text-gray-900">
                                                        {viewingDocument.name}
                                                    </p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-3 text-xs">
                                                    <div>
                                                        <span className="text-muted-foreground">Document Type:</span>
                                                        <p className="font-medium">{viewingDocument.document_type}</p>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Category:</span>
                                                        <p className="font-medium">{getCategoryLabel(viewingDocument.category)}</p>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Status:</span>
                                                        <p className="font-medium uppercase">{viewingDocument.status}</p>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">File Size:</span>
                                                        <p className="font-medium">{formatFileSize(viewingDocument.file_size)}</p>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Uploaded:</span>
                                                        <p className="font-medium">{formatDate(viewingDocument.uploaded_at)}</p>
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Uploaded By:</span>
                                                        <p className="font-medium">{viewingDocument.uploaded_by}</p>
                                                    </div>
                                                    {viewingDocument.expires_at && (
                                                        <div className="col-span-2">
                                                            <span className="text-muted-foreground">Expiry Date:</span>
                                                            <p className="font-medium text-amber-600">
                                                                {formatDate(viewingDocument.expires_at)}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="mt-4 pt-4 border-t">
                                                    <p className="text-xs italic text-gray-500">
                                                        This is a mock document preview for demonstration purposes.
                                                        Actual PDF content will be displayed here once file storage is implemented (Phase 4).
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="px-6 py-4 border-t bg-muted/20 flex items-center justify-end gap-2 shrink-0">
                                <Button variant="outline" onClick={() => setViewingDocument(null)}>
                                    <X className="h-4 w-4 mr-2" />
                                    Close
                                </Button>
                                <Button variant="outline">
                                    <Download className="h-4 w-4 mr-2" />
                                    Download
                                </Button>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
