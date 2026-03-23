import { useEffect, useMemo, useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

interface TemplateOption {
    id: number;
    name: string;
    category?: string;
    status?: string;
}

export function ProcessRequestModal({ open, onClose, request }: ProcessRequestModalProps) {
    const { toast } = useToast();
    const [action, setAction] = useState<'generate' | 'upload' | 'reject'>('generate');
    const [templateId, setTemplateId] = useState('');
    const [templates, setTemplates] = useState<TemplateOption[]>([]);
    const [loadingTemplates, setLoadingTemplates] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [notes, setNotes] = useState('');
    const [sendEmail, setSendEmail] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const documentTypeToTemplateCategory = useMemo<Record<string, string | null>>(() => ({
        'Certificate of Employment': 'coe',
        Payslip: null,
        'BIR Form 2316': 'other',
        'SSS/PhilHealth/Pag-IBIG Contribution': 'other',
    }), []);

    const filteredTemplates = useMemo(() => {
        const templateCategory = documentTypeToTemplateCategory[request.document_type] ?? null;
        if (!templateCategory) return templates;
        const exactMatches = templates.filter((t) => t.category === templateCategory);
        return exactMatches.length > 0 ? exactMatches : templates;
    }, [documentTypeToTemplateCategory, request.document_type, templates]);

    // Reset form state when modal opens/closes or request changes
    useEffect(() => {
        if (open) {
            setAction('generate');
            setTemplateId('');
            setFile(null);
            setRejectionReason('');
            setNotes('');
            setSendEmail(true);
            setIsSubmitting(false);
        }
    }, [open, request.id]);

    // Fetch templates when modal opens and action is 'generate'
    useEffect(() => {
        if (!open || action !== 'generate') return;

        let active = true;

        const fetchTemplates = async () => {
            setLoadingTemplates(true);
            try {
                const response = await fetch('/hr/documents/api/templates?status=approved', {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) throw new Error(`Failed to load templates (${response.status})`);

                const result = await response.json();
                const rows: TemplateOption[] = Array.isArray(result?.data)
                    ? result.data.map((t: any) => ({
                          id: Number(t.id),
                          name: String(t.name),
                          category: t.category ? String(t.category) : undefined,
                          status: t.status ? String(t.status) : undefined,
                      }))
                    : [];

                if (active) setTemplates(rows);
            } catch (error) {
                console.error('Failed to load templates:', error);
                if (active) {
                    setTemplates([]);
                    toast({
                        title: 'Template loading failed',
                        description: 'Could not load templates. Please refresh and try again.',
                        variant: 'destructive',
                    });
                }
            } finally {
                if (active) setLoadingTemplates(false);
            }
        };

        fetchTemplates();
        return () => { active = false; };
    }, [open, action, toast]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isSubmitting) return;
        setIsSubmitting(true);

        const isReject = action === 'reject';
        const url = isReject
            ? `/hr/documents/requests/${request.id}/reject`
            : `/hr/documents/requests/${request.id}/approve`;

        // Build payload — Inertia router handles CSRF automatically
        const data: Record<string, any> = {
            send_email: sendEmail,
        };

        if (action === 'generate') {
            data.template_id = templateId;
        } else if (action === 'upload') {
            // File uploads need FormData; we pass the file directly and Inertia wraps it
            data.file = file;
        } else if (action === 'reject') {
            data.rejection_reason = rejectionReason;
        }

        if (notes) data.notes = notes;

        router.post(url, data, {
            forceFormData: action === 'upload', // ensures multipart for file uploads
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: isReject
                        ? 'Request rejected successfully.'
                        : 'Request approved and document generated.',
                });
                onClose();
            },
            onError: (errors) => {
                console.error('Process request errors:', errors);
                const firstError = Object.values(errors)[0] as string | undefined;
                toast({
                    title: 'Failed to process request',
                    description: firstError ?? 'Please check the form and try again.',
                    variant: 'destructive',
                });
                setIsSubmitting(false);
            },
            onFinish: () => {
                // onSuccess already closed; only reset if still open (error case)
                setIsSubmitting(false);
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (!selectedFile) return;
        if (selectedFile.size > 10 * 1024 * 1024) {
            toast({
                title: 'File too large',
                description: 'File size must be less than 10MB',
                variant: 'destructive',
            });
            return;
        }
        setFile(selectedFile);
    };

    const isSubmitDisabled =
        isSubmitting ||
        (action === 'generate' && !templateId) ||
        (action === 'reject' && rejectionReason.length < 20) ||
        (action === 'upload' && !file);

    return (
        <Dialog open={open} onOpenChange={(open) => !open && !isSubmitting && onClose()}>
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
                                        <h3 className="font-semibold text-lg">{request.employee_name}</h3>
                                        <p className="text-sm text-gray-600">{request.employee_number}</p>
                                    </div>
                                    <Badge variant={request.priority === 'urgent' ? 'destructive' : 'secondary'}>
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
                        <RadioGroup
                            value={action}
                            onValueChange={(value) => setAction(value as 'generate' | 'upload' | 'reject')}
                        >
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
                                <Select
                                    value={templateId}
                                    onValueChange={setTemplateId}
                                    disabled={loadingTemplates}
                                >
                                    <SelectTrigger id="template">
                                        <SelectValue
                                            placeholder={
                                                loadingTemplates
                                                    ? 'Loading templates...'
                                                    : filteredTemplates.length > 0
                                                    ? 'Select template'
                                                    : 'No approved templates available'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredTemplates.map((template) => (
                                            <SelectItem key={template.id} value={String(template.id)}>
                                                {template.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-gray-500 mt-1">
                                    Select an approved template for this document request.
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
                                />
                                <p className="text-xs text-gray-500 mt-1">Minimum 20 characters</p>
                            </div>

                            <div>
                                <Label className="text-sm text-gray-600 mb-2 block">
                                    Common Rejection Reasons
                                </Label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        'Incomplete employee records',
                                        'Document not available yet',
                                        'Request period is too recent for payroll processing',
                                        'Duplicate request already fulfilled',
                                    ].map((reason) => (
                                        <Button
                                            key={reason}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setRejectionReason(reason)}
                                        >
                                            {reason.length > 25 ? reason.substring(0, 25) + '…' : reason}
                                        </Button>
                                    ))}
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
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitDisabled}>
                            {isSubmitting
                                ? 'Processing...'
                                : action === 'reject'
                                ? 'Reject Request'
                                : 'Process Request'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}