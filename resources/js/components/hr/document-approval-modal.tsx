import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { CheckCircle, AlertCircle } from 'lucide-react';

// Helper function to get CSRF token
function getCsrfToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token || '';
}

interface DocumentApprovalModalProps {
    open: boolean;
    onClose: () => void;
    documentId?: number;
    documentName?: string;
    onSuccess?: () => void;
}

export function DocumentApprovalModal({
    open,
    onClose,
    documentId,
    documentName,
    onSuccess,
}: DocumentApprovalModalProps) {
    const [notes, setNotes] = useState('');
    const [loading, setLoading] = useState(false);
    const { toast } = useToast();

    const handleApprove = async () => {
        if (!documentId) return;

        setLoading(true);
        try {
            const response = await fetch(`/hr/documents/${documentId}/approve`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    notes: notes || null,
                }),
            });

            if (response.ok) {
                toast({
                    title: 'Success',
                    description: 'Document approved successfully.',
                });
                resetModal();
                onSuccess?.();
            } else {
                const data = await response.json();
                toast({
                    title: 'Error',
                    description: data.message || 'Failed to approve document.',
                    variant: 'destructive',
                });
            }
        } catch (error) {
            console.error('Error approving document:', error);
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
        setNotes('');
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={resetModal}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Approve Document</DialogTitle>
                    <DialogDescription>
                        Review and approve this document submission
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Document Info */}
                    <div className="rounded-lg bg-blue-50 border border-blue-200 p-3">
                        <div className="flex gap-2">
                            <CheckCircle className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <div className="text-sm font-semibold text-blue-900">
                                    Ready to Approve
                                </div>
                                <p className="text-sm text-blue-800">{documentName}</p>
                            </div>
                        </div>
                    </div>

                    {/* Notes Field */}
                    <div className="space-y-2">
                        <label className="text-sm font-semibold">Approval Notes (Optional)</label>
                        <Textarea
                            placeholder="Add any notes about this approval..."
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            disabled={loading}
                            rows={4}
                        />
                    </div>

                    {/* Info Alert */}
                    <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <div className="flex gap-2">
                            <AlertCircle className="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" />
                            <p className="text-sm text-amber-800">
                                This action cannot be undone. The document will be marked as approved.
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
                            onClick={handleApprove}
                            disabled={loading}
                            className="ml-auto"
                        >
                            {loading ? 'Approving...' : 'Approve'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
