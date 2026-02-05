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
import { AlertCircle, XCircle } from 'lucide-react';

// Helper function to get CSRF token
function getCsrfToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token || '';
}

interface DocumentRejectionModalProps {
    open: boolean;
    onClose: () => void;
    documentId?: number;
    documentName?: string;
    onSuccess?: () => void;
}

export function DocumentRejectionModal({
    open,
    onClose,
    documentId,
    documentName,
    onSuccess,
}: DocumentRejectionModalProps) {
    const [reason, setReason] = useState('');
    const [loading, setLoading] = useState(false);
    const { toast } = useToast();

    const handleReject = async () => {
        if (!documentId) return;
        if (!reason.trim()) {
            toast({
                title: 'Validation Error',
                description: 'Please provide a reason for rejection.',
                variant: 'destructive',
            });
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`/hr/documents/${documentId}/reject`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    rejection_reason: reason,
                }),
            });

            if (response.ok) {
                toast({
                    title: 'Success',
                    description: 'Document rejected successfully.',
                });
                resetModal();
                onSuccess?.();
            } else {
                const data = await response.json();
                toast({
                    title: 'Error',
                    description: data.message || 'Failed to reject document.',
                    variant: 'destructive',
                });
            }
        } catch (error) {
            console.error('Error rejecting document:', error);
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
        setReason('');
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={resetModal}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Reject Document</DialogTitle>
                    <DialogDescription>
                        Reject this document and notify the submitter
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Document Info */}
                    <div className="rounded-lg bg-red-50 border border-red-200 p-3">
                        <div className="flex gap-2">
                            <XCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <div className="text-sm font-semibold text-red-900">
                                    Reject This Document
                                </div>
                                <p className="text-sm text-red-800">{documentName}</p>
                            </div>
                        </div>
                    </div>

                    {/* Reason Field */}
                    <div className="space-y-2">
                        <label className="text-sm font-semibold">
                            Rejection Reason <span className="text-red-600">*</span>
                        </label>
                        <Textarea
                            placeholder="Explain why this document is being rejected..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            disabled={loading}
                            rows={4}
                            className="border-red-200 focus-visible:ring-red-500"
                        />
                        <p className="text-xs text-muted-foreground">
                            The employee will receive notification of the rejection with this reason.
                        </p>
                    </div>

                    {/* Info Alert */}
                    <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
                        <div className="flex gap-2">
                            <AlertCircle className="h-4 w-4 text-amber-600 flex-shrink-0 mt-0.5" />
                            <p className="text-sm text-amber-800">
                                Rejecting a document will send a notification to the employee requiring resubmission.
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
                            onClick={handleReject}
                            disabled={loading}
                            variant="destructive"
                            className="ml-auto"
                        >
                            {loading ? 'Rejecting...' : 'Reject'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
