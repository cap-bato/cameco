import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    FileText,
    Upload as UploadIcon,
    XCircle,
    Building,
    Calendar,
} from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

interface DocumentRequest {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    document_type: string;
    purpose: string;
    priority: 'urgent' | 'high' | 'normal';
    status: string;
    requested_at: string;
}

interface ProcessRequestModalProps {
    open: boolean;
    onClose: () => void;
    request: DocumentRequest;
}

export function ProcessRequestModal({ open, onClose, request }: ProcessRequestModalProps) {
    const { toast } = useToast();
    const [action, setAction] = useState<'generate' | 'upload' | 'reject'>('generate');
    const [templateId, setTemplateId] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [notes, setNotes] = useState('');
    const [sendEmail, setSendEmail] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        try {
            const formData = new FormData();
            formData.append('action', action);
            
            if (action === 'generate' && templateId) {
                formData.append('template_id', templateId);
            } else if (action === 'upload' && file) {
                formData.append('file', file);
            } else if (action === 'reject' && rejectionReason) {
                formData.append('rejection_reason', rejectionReason);
            }
            
            if (notes) formData.append('notes', notes);
            formData.append('send_email', sendEmail ? '1' : '0');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch(`/hr/documents/requests/${request.id}/process`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            toast({
                title: 'Success',
                description: `Request ${action === 'reject' ? 'rejected' : 'processed'} successfully`,
            });

            // Refresh the requests list
            window.location.reload();
            onClose();
        } catch (error) {
            console.error('Error processing request:', error);
            toast({
                title: 'Error',
                description: 'Failed to process request',
                variant: 'destructive',
            });
            setIsSubmitting(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            // Validate file size (10MB)
            if (selectedFile.size > 10 * 1024 * 1024) {
                toast({
                    title: 'File too large',
                    description: 'File size must be less than 10MB',
                    variant: 'destructive',
                });
                return;
            }
            setFile(selectedFile);
        }
    };

    const insertCommonReason = (reason: string) => {
        setRejectionReason(reason);
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Process Document Request</DialogTitle>
                    <DialogDescription>
                        Review the request details and choose an action
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    {/* Request Details */}
                    <div className="border rounded-lg p-4 mb-6 bg-gray-50">
                        <div className="flex items-start gap-4">
                            <Avatar className="h-12 w-12">
                                <AvatarFallback>
                                    {request.employee_name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .join('')
                                        .toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <div className="flex-1 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="font-semibold text-lg">
                                            {request.employee_name}
                                        </h3>
                                        <p className="text-sm text-gray-600">
                                            {request.employee_number}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            request.priority === 'urgent'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {request.priority}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-gray-500" />
                                        <span>{request.department}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-gray-500" />
                                        <span>{new Date(request.requested_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                                <div className="pt-2 border-t">
                                    <div className="flex items-center gap-2 mb-1">
                                        <FileText className="h-4 w-4 text-blue-600" />
                                        <span className="font-medium">{request.document_type}</span>
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        <span className="font-medium">Purpose:</span> {request.purpose}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Action Selector */}
                    <div className="space-y-4 mb-6">
                        <Label>Choose Action</Label>
                        <RadioGroup value={action} onValueChange={(value) => setAction(value as 'generate' | 'upload' | 'reject')}>
                            <div className="flex items-center space-x-2 border rounded-lg p-4 hover:bg-gray-50">
                                <RadioGroupItem value="generate" id="generate" />
                                <Label htmlFor="generate" className="flex-1 cursor-pointer">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                        <div>
                                            <div className="font-medium">Generate from Template</div>
                                            <div className="text-sm text-gray-600">
                                                Auto-generate document using a template
                                            </div>
                                        </div>
                                    </div>
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2 border rounded-lg p-4 hover:bg-gray-50">
                                <RadioGroupItem value="upload" id="upload" />
                                <Label htmlFor="upload" className="flex-1 cursor-pointer">
                                    <div className="flex items-center gap-2">
                                        <UploadIcon className="h-5 w-5 text-green-600" />
                                        <div>
                                            <div className="font-medium">Upload Existing Document</div>
                                            <div className="text-sm text-gray-600">
                                                Upload a pre-signed document file
                                            </div>
                                        </div>
                                    </div>
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2 border rounded-lg p-4 hover:bg-gray-50">
                                <RadioGroupItem value="reject" id="reject" />
                                <Label htmlFor="reject" className="flex-1 cursor-pointer">
                                    <div className="flex items-center gap-2">
                                        <XCircle className="h-5 w-5 text-red-600" />
                                        <div>
                                            <div className="font-medium">Reject Request</div>
                                            <div className="text-sm text-gray-600">
                                                Decline this request with reason
                                            </div>
                                        </div>
                                    </div>
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    {/* Action-Specific Fields */}
                    {action === 'generate' && (
                        <div className="space-y-4 mb-6">
                            <div>
                                <Label htmlFor="template">Template</Label>
                                <Input
                                    id="template"
                                    placeholder="Select template (implementation pending)"
                                    value={templateId}
                                    onChange={(e) => setTemplateId(e.target.value)}
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Template selection will be implemented with Task 2.6
                                </p>
                            </div>
                        </div>
                    )}

                    {action === 'upload' && (
                        <div className="space-y-4 mb-6">
                            <div>
                                <Label htmlFor="file">Upload Document</Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".pdf,.docx,.jpg,.jpeg,.png"
                                    onChange={handleFileChange}
                                    className="cursor-pointer"
                                />
                                {file && (
                                    <p className="text-sm text-gray-600 mt-2">
                                        Selected: {file.name} ({(file.size / 1024 / 1024).toFixed(2)} MB)
                                    </p>
                                )}
                                <p className="text-xs text-gray-500 mt-1">
                                    Max 10MB. Accepted: PDF, DOCX, JPG, PNG
                                </p>
                            </div>
                        </div>
                    )}

                    {action === 'reject' && (
                        <div className="space-y-4 mb-6">
                            <div>
                                <Label htmlFor="rejection_reason">Rejection Reason *</Label>
                                <Textarea
                                    id="rejection_reason"
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    placeholder="Provide a clear reason for rejecting this request..."
                                    rows={4}
                                    className="resize-none"
                                    required
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    Minimum 20 characters
                                </p>
                            </div>

                            <div>
                                <Label className="text-sm text-gray-600 mb-2 block">
                                    Common Rejection Reasons
                                </Label>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            insertCommonReason('Incomplete employee records')
                                        }
                                    >
                                        Incomplete Records
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            insertCommonReason('Document not available yet')
                                        }
                                    >
                                        Not Available
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            insertCommonReason(
                                                'Request period is too recent for payroll processing'
                                            )
                                        }
                                    >
                                        Too Recent
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            insertCommonReason(
                                                'Duplicate request already fulfilled'
                                            )
                                        }
                                    >
                                        Duplicate Request
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Notes */}
                    <div className="space-y-2 mb-6">
                        <Label htmlFor="notes">Notes (Optional)</Label>
                        <Textarea
                            id="notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Add any additional notes..."
                            rows={3}
                            className="resize-none"
                        />
                    </div>

                    {/* Email Notification */}
                    <div className="flex items-center space-x-2 mb-6">
                        <input
                            type="checkbox"
                            id="send_email"
                            checked={sendEmail}
                            onChange={(e) => setSendEmail(e.target.checked)}
                            className="rounded"
                        />
                        <Label htmlFor="send_email" className="cursor-pointer">
                            Send email notification to employee
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                isSubmitting ||
                                (action === 'reject' && rejectionReason.length < 20) ||
                                (action === 'upload' && !file)
                            }
                        >
                            {isSubmitting ? 'Processing...' : action === 'reject' ? 'Reject Request' : 'Process Request'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
