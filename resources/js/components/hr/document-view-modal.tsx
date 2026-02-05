import { useState, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FileText, Download, Calendar, User, Clock } from 'lucide-react';

interface DocumentViewModalProps {
    open: boolean;
    onClose: () => void;
    documentId?: number;
    onDownload?: (id: number) => void;
}

interface DocumentDetails {
    id: number;
    employee_name: string;
    employee_number: string;
    document_category: string;
    document_type: string;
    file_name: string;
    file_size: number;
    file_path: string;
    status: string;
    uploaded_by: string;
    uploaded_at: string;
    expires_at: string | null;
    notes?: string | null;
    mime_type: string;
}

export function DocumentViewModal({ open, onClose, documentId, onDownload }: DocumentViewModalProps) {
    const [document, setDocument] = useState<DocumentDetails | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const loadDocument = async () => {
            setLoading(true);
            try {
                const response = await fetch(`/hr/documents/${documentId}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                
                if (response.ok) {
                    const data = await response.json();
                    setDocument(data.document || data);
                }
            } catch (error) {
                console.error('Error loading document:', error);
            } finally {
                setLoading(false);
            }
        };

        if (open && documentId) {
            loadDocument();
        }
    }, [open, documentId]);

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const getStatusColor = (status: string) => {
        const colorMap: Record<string, string> = {
            pending: 'bg-yellow-100 text-yellow-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800',
            auto_approved: 'bg-blue-100 text-blue-800',
            expired: 'bg-gray-100 text-gray-800',
        };
        return colorMap[status] || 'bg-gray-100 text-gray-800';
    };

    const getCategoryColor = (category: string) => {
        const colorMap: Record<string, string> = {
            personal: 'bg-blue-100 text-blue-800',
            educational: 'bg-purple-100 text-purple-800',
            employment: 'bg-green-100 text-green-800',
            medical: 'bg-red-100 text-red-800',
            contracts: 'bg-orange-100 text-orange-800',
            benefits: 'bg-pink-100 text-pink-800',
            performance: 'bg-indigo-100 text-indigo-800',
            separation: 'bg-gray-100 text-gray-800',
            government: 'bg-cyan-100 text-cyan-800',
            special: 'bg-violet-100 text-violet-800',
        };
        return colorMap[category] || 'bg-gray-100 text-gray-800';
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Document Details</DialogTitle>
                    <DialogDescription>
                        View complete information about this document
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <div className="text-muted-foreground">Loading document details...</div>
                    </div>
                ) : document ? (
                    <div className="space-y-6">
                        {/* File Information */}
                        <div className="rounded-lg border p-4">
                            <div className="flex items-start gap-4">
                                <FileText className="h-8 w-8 text-blue-500 flex-shrink-0" />
                                <div className="flex-1">
                                    <h3 className="font-semibold">{document.file_name}</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {document.mime_type} • {formatFileSize(document.file_size)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Document Metadata */}
                        <div className="grid grid-cols-2 gap-4">
                            {/* Employee Info */}
                            <div className="space-y-1">
                                <div className="text-sm font-semibold text-muted-foreground">Employee</div>
                                <div className="text-sm">{document.employee_name}</div>
                                <div className="text-sm text-muted-foreground">{document.employee_number}</div>
                            </div>

                            {/* Document Type */}
                            <div className="space-y-1">
                                <div className="text-sm font-semibold text-muted-foreground">Document Type</div>
                                <div className="text-sm">{document.document_type}</div>
                            </div>

                            {/* Category */}
                            <div className="space-y-1">
                                <div className="text-sm font-semibold text-muted-foreground">Category</div>
                                <Badge className={getCategoryColor(document.document_category)}>
                                    {document.document_category}
                                </Badge>
                            </div>

                            {/* Status */}
                            <div className="space-y-1">
                                <div className="text-sm font-semibold text-muted-foreground">Status</div>
                                <Badge className={getStatusColor(document.status)}>
                                    {document.status}
                                </Badge>
                            </div>
                        </div>

                        {/* Dates */}
                        <div className="grid grid-cols-2 gap-4">
                            {/* Uploaded Date */}
                            <div className="flex items-center gap-3">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div className="text-sm font-semibold text-muted-foreground">Uploaded</div>
                                    <div className="text-sm">{formatDate(document.uploaded_at)}</div>
                                </div>
                            </div>

                            {/* Expiry Date */}
                            {document.expires_at && (
                                <div className="flex items-center gap-3">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <div className="text-sm font-semibold text-muted-foreground">Expires</div>
                                        <div className="text-sm">{formatDate(document.expires_at)}</div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Uploaded By */}
                        <div className="flex items-center gap-3">
                            <User className="h-4 w-4 text-muted-foreground" />
                            <div>
                                <div className="text-sm font-semibold text-muted-foreground">Uploaded By</div>
                                <div className="text-sm">{document.uploaded_by}</div>
                            </div>
                        </div>

                        {/* Notes */}
                        {document.notes && (
                            <div className="rounded-lg bg-muted p-3">
                                <div className="text-sm font-semibold text-muted-foreground mb-1">Notes</div>
                                <p className="text-sm">{document.notes}</p>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex gap-2 pt-4">
                            {onDownload && (
                                <Button
                                    onClick={() => {
                                        onDownload(document.id);
                                        onClose();
                                    }}
                                    className="flex items-center gap-2"
                                    variant="outline"
                                >
                                    <Download className="h-4 w-4" />
                                    Download
                                </Button>
                            )}
                            <Button onClick={onClose} className="ml-auto">
                                Close
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center justify-center py-8">
                        <div className="text-muted-foreground">Unable to load document details</div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
