import { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    FileText,
    Download,
    Plus,
    AlertTriangle,
    Clock,
    CheckCircle,
    XCircle,
    Filter,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface Document {
    id: number;
    document_type: string;
    document_category: string;
    uploaded_date: string;
    expiry_date: string;
    status: string;
    status_display: {
        label: string;
        color: string;
        icon: string;
        expiry_label?: string;
        expiry_color?: string;
    };
    days_remaining: number | null;
    file_size: string;
    mime_type: string;
    notes: string | null;
}

interface IndexPageProps {
    employee: {
        id: number;
        name: string;
        employee_number: string;
    };
    documents: Document[];
    categories: Record<string, string>;
    selectedCategory: string | null;
    pendingRequests: number;
    totalDocuments: number;
}

// ============================================================================
// Main Component
// ============================================================================

export default function DocumentsIndex({
    documents,
    categories,
    selectedCategory,
    pendingRequests,
    totalDocuments,
}: IndexPageProps) {
    const { toast } = useToast();
    const [selectedCategoryFilter, setSelectedCategoryFilter] = useState<string | null>(
        selectedCategory
    );

    // Filter documents by category
    const filteredDocuments = useMemo(() => {
        if (!selectedCategoryFilter) return documents;
        return documents.filter((doc) => doc.document_category === selectedCategoryFilter);
    }, [documents, selectedCategoryFilter]);

    // Group documents by category
    const groupedDocuments = useMemo(() => {
        const grouped: Record<string, Document[]> = {};
        filteredDocuments.forEach((doc) => {
            if (!grouped[doc.document_category]) {
                grouped[doc.document_category] = [];
            }
            grouped[doc.document_category].push(doc);
        });
        return grouped;
    }, [filteredDocuments]);

    // Get icon for status
    const getStatusIcon = (iconName: string) => {
        type IconType = React.ReactNode;
        const iconMap: Record<string, IconType> = {
            Clock: <Clock className="h-4 w-4" />,
            CheckCircle: <CheckCircle className="h-4 w-4" />,
            XCircle: <XCircle className="h-4 w-4" />,
        };
        return iconMap[iconName] || <FileText className="h-4 w-4" />;
    };

    // Get badge variant
    const getBadgeVariant = (color: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
        const variantMap: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            success: 'default',
            warning: 'secondary',
            destructive: 'destructive',
            secondary: 'outline',
        };
        return variantMap[color] || 'outline';
    };

    // Handle download
    const handleDownload = (documentId: number, documentType: string) => {
        // Trigger download via navigation
        const link = document.createElement('a');
        link.href = `/employee/documents/${documentId}/download`;
        link.click();
        
        toast({
            title: 'Download Started',
            description: `${documentType} is downloading...`,
        });
    };

    // Handle document request
    const handleRequestDocument = () => {
        window.location.href = '/employee/documents/request';
    };

    return (
        <AppLayout>
            <Head title="My Documents" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">My Documents</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            View and manage your employee documents
                        </p>
                    </div>
                    <Button onClick={handleRequestDocument} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Request Document
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Total Documents</p>
                                    <p className="text-2xl font-bold mt-1">{totalDocuments}</p>
                                </div>
                                <FileText className="h-8 w-8 text-blue-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Pending Requests</p>
                                    <p className="text-2xl font-bold mt-1">{pendingRequests}</p>
                                </div>
                                <Clock className="h-8 w-8 text-yellow-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">Expiring Soon</p>
                                    <p className="text-2xl font-bold mt-1">
                                        {documents.filter(
                                            (d) =>
                                                d.days_remaining !== null &&
                                                d.days_remaining <= 30 &&
                                                d.days_remaining > 0
                                        ).length}
                                    </p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-orange-500 opacity-20" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Alerts */}
                {documents.some((d) => d.days_remaining !== null && d.days_remaining < 0) && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Action Required:</strong> You have{' '}
                            {documents.filter((d) => d.days_remaining !== null && d.days_remaining < 0)
                                .length}{' '}
                            expired document(s). Please request renewal from HR.
                        </AlertDescription>
                    </Alert>
                )}

                {documents.some((d) => d.days_remaining !== null && d.days_remaining <= 7 && d.days_remaining > 0) && (
                    <Alert>
                        <Clock className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Notice:</strong> You have{' '}
                            {documents.filter((d) => d.days_remaining !== null && d.days_remaining <= 7 && d.days_remaining > 0)
                                .length}{' '}
                            document(s) expiring within 7 days. Consider requesting renewal now.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Category Filter */}
                {Object.keys(categories).length > 0 && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex flex-wrap items-center gap-2">
                                <Filter className="h-4 w-4 text-gray-500" />
                                <span className="text-sm font-medium text-gray-600">Filter:</span>
                                <Button
                                    variant={selectedCategoryFilter === null ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setSelectedCategoryFilter(null)}
                                >
                                    All Documents
                                </Button>
                                {Object.entries(categories).map(([key, label]) => (
                                    <Button
                                        key={key}
                                        variant={selectedCategoryFilter === key ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => setSelectedCategoryFilter(key)}
                                    >
                                        {label}
                                    </Button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Documents List */}
                {filteredDocuments.length === 0 ? (
                    <Card>
                        <CardContent className="pt-12 pb-12">
                            <div className="text-center">
                                <FileText className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                                <h3 className="text-lg font-semibold text-gray-900">No documents available</h3>
                                <p className="text-sm text-gray-600 mt-1">
                                    You don't have any documents yet. HR will upload your documents or you can
                                    request specific documents.
                                </p>
                                <Button onClick={handleRequestDocument} className="mt-4">
                                    Request Document
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    Object.entries(groupedDocuments).map(([category, docs]) => (
                        <Card key={category}>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    {categories[category] || category}
                                </CardTitle>
                                <CardDescription>{docs.length} document(s)</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {docs.map((doc) => (
                                        <div
                                            key={doc.id}
                                            className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors"
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <FileText className="h-5 w-5 text-blue-500" />
                                                    <h4 className="font-medium text-gray-900">
                                                        {doc.document_type}
                                                    </h4>
                                                    <Badge variant={getBadgeVariant(doc.status_display.color)}>
                                                        <span className="flex items-center gap-1">
                                                            {getStatusIcon(doc.status_display.icon)}
                                                            {doc.status_display.label}
                                                        </span>
                                                    </Badge>
                                                    {doc.status_display.expiry_label && (
                                                        <Badge
                                                            variant={getBadgeVariant(doc.status_display.expiry_color || 'secondary')}                                                        >
                                                            {doc.status_display.expiry_label}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap gap-4 text-sm text-gray-600">
                                                    <span>Uploaded: {doc.uploaded_date}</span>
                                                    <span>Expires: {doc.expiry_date}</span>
                                                    <span>Size: {doc.file_size}</span>
                                                </div>
                                                {doc.notes && (
                                                    <p className="text-sm text-gray-600 mt-2">
                                                        <strong>Notes:</strong> {doc.notes}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                onClick={() => handleDownload(doc.id, doc.document_type)}
                                                disabled={doc.status !== 'approved' && doc.status !== 'auto_approved'}
                                                className="gap-2"
                                                size="sm"
                                            >
                                                <Download className="h-4 w-4" />
                                                Download
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
