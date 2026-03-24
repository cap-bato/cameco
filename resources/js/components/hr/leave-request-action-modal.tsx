import React, { useState } from 'react';
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
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, XCircle, Eye } from 'lucide-react';

interface LeaveRequestActionModalProps {
    isOpen: boolean;
    onClose: () => void;
    request: {
        id: number;
        employee_name: string;
        leave_type: string;
        leave_type_variant?: string | null;
        start_date: string;
        end_date: string;
        days_requested: number;
        status: string;
        reason?: string;
    };
    action: 'approve' | 'reject' | 'view';
}

function getStatusColor(status: string): string {
    const colorMap: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        Pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        Approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        Rejected: 'bg-red-100 text-red-800',
    };
    return colorMap[status] || 'bg-gray-100 text-gray-800';
}

function getVariantLabel(variant: string | null | undefined): string {
    const labels: Record<string, string> = {
        'half_am': 'Half Day AM',
        'half_pm': 'Half Day PM',
    };
    return labels[variant || ''] || '';
}

function formatLeaveTypeWithVariant(leaveType: string, variant: string | null | undefined): string {
    const variantLabel = getVariantLabel(variant);
    if (variantLabel) {
        return `${leaveType} (${variantLabel})`;
    }
    return leaveType;
}

export function LeaveRequestActionModal({
    isOpen,
    onClose,
    request,
    action,
}: LeaveRequestActionModalProps) {
    const [comments, setComments] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = () => {
        if (action === 'view') {
            onClose();
            return;
        }

        setIsSubmitting(true);
        router.put(
            `/hr/leave/requests/${request.id}`,
            {
                action,
                approval_comments: comments,
            },
            {
                onSuccess: () => {
                    onClose();
                    setComments('');
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const getTitle = () => {
        switch (action) {
            case 'approve':
                return 'Approve Leave Request';
            case 'reject':
                return 'Reject Leave Request';
            case 'view':
                return 'Leave Request Details';
        }
    };

    const getDescription = () => {
        switch (action) {
            case 'approve':
                return 'Confirm approval of this leave request';
            case 'reject':
                return 'Provide a reason for rejection';
            case 'view':
                return 'View complete leave request information';
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {action === 'approve' && <CheckCircle2 className="h-5 w-5 text-green-600" />}
                        {action === 'reject' && <XCircle className="h-5 w-5 text-red-600" />}
                        {action === 'view' && <Eye className="h-5 w-5" />}
                        {getTitle()}
                    </DialogTitle>
                    <DialogDescription>{getDescription()}</DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Request Details */}
                    <div className="rounded-lg border p-4 space-y-3 bg-muted/30">
                        <div className="flex justify-between items-start">
                            <div>
                                <div className="font-semibold">{request.employee_name}</div>
                                <div className="text-sm text-muted-foreground">{formatLeaveTypeWithVariant(request.leave_type, request.leave_type_variant)}</div>
                            </div>
                            <Badge className={getStatusColor(request.status)}>{request.status}</Badge>
                        </div>

                        <div className="grid grid-cols-3 gap-2 text-sm pt-2 border-t">
                            <div>
                                <div className="text-muted-foreground">From</div>
                                <div className="font-medium">{request.start_date}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">To</div>
                                <div className="font-medium">{request.end_date}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Days</div>
                                <div className="font-medium">{request.days_requested} days</div>
                            </div>
                        </div>

                        {request.reason && (
                            <div className="text-sm pt-2 border-t">
                                <div className="text-muted-foreground mb-1">Reason:</div>
                                <div>{request.reason}</div>
                            </div>
                        )}
                    </div>

                    {/* Comments/Notes (for approve/reject actions) */}
                    {action !== 'view' && (
                        <div className="space-y-2">
                            <Label htmlFor="comments">
                                {action === 'reject' ? 'Rejection Reason *' : 'Comments (optional)'}
                            </Label>
                            <Textarea
                                id="comments"
                                value={comments}
                                onChange={(e) => setComments(e.target.value)}
                                placeholder={
                                    action === 'reject'
                                        ? 'Please provide a reason for rejection...'
                                        : 'Add any comments or notes...'
                                }
                                rows={4}
                                required={action === 'reject'}
                            />
                            {action === 'reject' && (
                                <p className="text-xs text-muted-foreground">
                                    This reason will be sent to the employee
                                </p>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    {action === 'approve' && (
                        <Button onClick={handleSubmit} disabled={isSubmitting} className="bg-green-600 hover:bg-green-700">
                            <CheckCircle2 className="h-4 w-4 mr-2" />
                            {isSubmitting ? 'Approving...' : 'Approve Request'}
                        </Button>
                    )}
                    {action === 'reject' && (
                        <Button
                            onClick={handleSubmit}
                            disabled={isSubmitting || !comments.trim()}
                            variant="destructive"
                        >
                            <XCircle className="h-4 w-4 mr-2" />
                            {isSubmitting ? 'Rejecting...' : 'Reject Request'}
                        </Button>
                    )}
                    {action === 'view' && (
                        <Button onClick={onClose}>
                            Close
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
