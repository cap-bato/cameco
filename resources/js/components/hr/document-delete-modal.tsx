import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import { AlertCircle, Trash2 } from 'lucide-react';

// Helper function to get CSRF token
function getCsrfToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token || '';
}

interface DocumentDeleteModalProps {
    open: boolean;
    onClose: () => void;
    documentId?: number;
    documentName?: string;
    onSuccess?: () => void;
}

export function DocumentDeleteModal({
    open,
    onClose,
    documentId,
    documentName,
    onSuccess,
}: DocumentDeleteModalProps) {
    const [loading, setLoading] = useState(false);
    const { toast } = useToast();

    const handleDelete = async () => {
        if (!documentId) return;

        setLoading(true);
        try {
            const response = await fetch(`/hr/documents/${documentId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                toast({
                    title: 'Success',
                    description: 'Document deleted successfully.',
                });
                resetModal();
                onSuccess?.();
            } else {
                const data = await response.json();
                toast({
                    title: 'Error',
                    description: data.message || 'Failed to delete document.',
                    variant: 'destructive',
                });
            }
        } catch (error) {
            console.error('Error deleting document:', error);
            toast({
                title: 'Error',
                description: 'An unexpected error occurred.',
                variant: 'destructive',
            });
        } finally {
            setLoading(false);
        }
    };

    const resetModal = () => {
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={resetModal}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Document</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Warning Alert */}
                    <div className="rounded-lg bg-red-50 border border-red-200 p-3">
                        <div className="flex gap-2">
                            <Trash2 className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <div className="text-sm font-semibold text-red-900">
                                    Permanently Delete
                                </div>
                                <p className="text-sm text-red-800">{documentName}</p>
                            </div>
                        </div>
                    </div>

                    {/* Info Alert */}
                    <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <div className="flex gap-2">
                            <AlertCircle className="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" />
                            <p className="text-sm text-amber-800">
                                This document will be permanently deleted and cannot be recovered. This action is logged for audit purposes.
                            </p>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2 pt-4">
                        <Button
                            onClick={resetModal}
                            variant="outline"
                            disabled={loading}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleDelete}
                            disabled={loading}
                            variant="destructive"
                            className="ml-auto"
                        >
                            {loading ? 'Deleting...' : 'Delete Document'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
