import { useState } from 'react';
import { CashAdvance, CashAdvanceApprovalData } from '@/types/payroll-pages';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertCircle, CheckCircle2, XCircle } from 'lucide-react';
import { formatCurrency } from '@/lib/utils';

interface AdvanceApprovalModalProps {
    isOpen: boolean;
    onClose: () => void;
    advance: CashAdvance | undefined;
    onApprove: (data: CashAdvanceApprovalData) => void;
    onReject: (data: CashAdvanceApprovalData) => void;
    isLoading?: boolean;
}

const DEDUCTION_SCHEDULES = [
    { value: 'single_period', label: 'Single Payroll Period' },
    { value: 'installments', label: 'Multiple Installments' },
];

export function AdvanceApprovalModal({
    isOpen,
    onClose,
    advance,
    onApprove,
    onReject,
    isLoading = false,
}: AdvanceApprovalModalProps) {
    const [approvalStatus, setApprovalStatus] = useState<'approved' | 'rejected'>('approved');
    const [approvedAmount, setApprovedAmount] = useState<number>(advance?.amount_requested || 0);
    const [deductionSchedule, setDeductionSchedule] = useState('single_period');
    const [numberOfInstallments, setNumberOfInstallments] = useState(3);
    const [approvalNotes, setApprovalNotes] = useState('');

    if (!advance || !isOpen) return null;

    const handleApprove = () => {
        const data: CashAdvanceApprovalData = {
            advance_id: advance.id,
            approval_status: 'approved',
            amount_approved: approvedAmount,
            deduction_schedule: deductionSchedule as 'single_period' | 'installments',
            number_of_installments: deductionSchedule === 'installments' ? numberOfInstallments : 1,
            approval_notes: approvalNotes,
        };
        onApprove(data);
        onClose();
    };

    const handleReject = () => {
        const data: CashAdvanceApprovalData = {
            advance_id: advance.id,
            approval_status: 'rejected',
            deduction_schedule: 'single_period',
            approval_notes: approvalNotes,
            rejection_reason: approvalNotes,
        };
        onReject(data);
        onClose();
    };

    const discountAmount = advance.amount_requested - approvedAmount;
    const amountPerInstallment = numberOfInstallments > 0 ? approvedAmount / numberOfInstallments : 0;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Approve Cash Advance</DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Advance Details Summary */}
                    <Card className="p-4 space-y-3 bg-muted/30">
                        <p className="text-sm font-semibold text-muted-foreground">Request Details</p>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div>
                                <p className="text-xs text-muted-foreground">Employee</p>
                                <p className="text-sm font-medium mt-1">{advance.employee_name}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Department</p>
                                <p className="text-sm font-medium mt-1">{advance.department_name}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Advance Type</p>
                                <p className="text-sm font-medium mt-1">{advance.advance_type}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Purpose</p>
                                <p className="text-sm font-medium mt-1 truncate">{advance.purpose}</p>
                            </div>
                        </div>
                    </Card>

                    {/* Amount Section */}
                    <Card className="p-4 space-y-3 border-blue-200 bg-blue-50">
                        <p className="text-sm font-semibold text-blue-900">Amount Requested</p>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <p className="text-xs text-blue-700">Requested</p>
                                <p className="text-lg font-bold mt-1 text-blue-900">
                                    {formatCurrency(advance.amount_requested)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-blue-700">Approved Amount</p>
                                <p className="text-lg font-bold mt-1 text-blue-900">
                                    {formatCurrency(approvedAmount)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-blue-700">Discount</p>
                                <p className={`text-lg font-bold mt-1 ${discountAmount > 0 ? 'text-orange-600' : 'text-blue-900'}`}>
                                    {formatCurrency(discountAmount)}
                                </p>
                            </div>
                        </div>
                    </Card>

                    {/* Approval Decision Section */}
                    <Card className="p-4 space-y-4">
                        <p className="text-sm font-semibold text-muted-foreground">Approval Decision</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Button
                                type="button"
                                variant={approvalStatus === 'approved' ? 'default' : 'outline'}
                                onClick={() => setApprovalStatus('approved')}
                                className={
                                    approvalStatus === 'approved'
                                        ? 'bg-green-600 hover:bg-green-700 gap-2'
                                        : 'gap-2'
                                }
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                Approve
                            </Button>
                            <Button
                                type="button"
                                variant={approvalStatus === 'rejected' ? 'destructive' : 'outline'}
                                onClick={() => setApprovalStatus('rejected')}
                                className={approvalStatus === 'rejected' ? 'gap-2' : 'gap-2'}
                            >
                                <XCircle className="h-4 w-4" />
                                Reject
                            </Button>
                        </div>
                    </Card>

                    {/* Approved Amount Input (only for approval) */}
                    {approvalStatus === 'approved' && (
                        <div className="space-y-3">
                            <p className="text-sm font-semibold text-muted-foreground">Approved Amount</p>
                            <Input
                                type="number"
                                step="0.01"
                                value={approvedAmount}
                                onChange={(e) => setApprovedAmount(parseFloat(e.target.value) || 0)}
                                placeholder="0.00"
                            />
                            <p className="text-xs text-muted-foreground">
                                You can approve a lower amount than requested
                            </p>
                        </div>
                    )}

                    {/* Deduction Schedule (only for approval) */}
                    {approvalStatus === 'approved' && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="deduction_schedule">Deduction Schedule</Label>
                                <Select value={deductionSchedule} onValueChange={setDeductionSchedule}>
                                    <SelectTrigger id="deduction_schedule">
                                        <SelectValue placeholder="Select schedule..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DEDUCTION_SCHEDULES.map((schedule) => (
                                            <SelectItem key={schedule.value} value={schedule.value}>
                                                {schedule.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Number of Installments */}
                            {deductionSchedule === 'installments' && (
                                <div className="space-y-2">
                                    <Label htmlFor="number_of_installments">Number of Installments</Label>
                                    <Input
                                        id="number_of_installments"
                                        type="number"
                                        min="2"
                                        max="12"
                                        value={numberOfInstallments}
                                        onChange={(e) => setNumberOfInstallments(parseInt(e.target.value) || 1)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Per installment: {formatCurrency(amountPerInstallment)}
                                    </p>
                                </div>
                            )}

                            {/* Deduction Schedule Preview */}
                            <Card className="p-3 bg-muted/50">
                                <p className="text-xs font-semibold text-muted-foreground mb-2">
                                    Deduction Schedule
                                </p>
                                <p className="text-sm">
                                    {deductionSchedule === 'single_period'
                                        ? `Full amount of ${formatCurrency(approvedAmount)} will be deducted in a single payroll period`
                                        : `${numberOfInstallments} monthly installments of ${formatCurrency(amountPerInstallment)} each`}
                                </p>
                            </Card>
                        </div>
                    )}

                    {/* Approval Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="approval_notes">
                            {approvalStatus === 'approved' ? 'Approval Notes' : 'Rejection Reason'} (Optional)
                        </Label>
                        <Textarea
                            id="approval_notes"
                            value={approvalNotes}
                            onChange={(e) => setApprovalNotes(e.target.value)}
                            placeholder={
                                approvalStatus === 'approved'
                                    ? 'Add any notes regarding approval...'
                                    : 'Explain the reason for rejection...'
                            }
                            className="min-h-[80px]"
                        />
                    </div>

                    {/* Info Message */}
                    <div className="flex gap-2 rounded-lg bg-blue-50 p-3">
                        <AlertCircle className="h-4 w-4 text-blue-600 flex-shrink-0 mt-0.5" />
                        <p className="text-xs text-blue-700">
                            {approvalStatus === 'approved'
                                ? 'Approved advances will begin deduction in the next payroll period.'
                                : 'The employee will be notified of the rejection with the reason provided.'}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={isLoading}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={approvalStatus === 'approved' ? handleApprove : handleReject}
                            disabled={isLoading}
                            className={
                                approvalStatus === 'approved'
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-destructive hover:bg-destructive/90'
                            }
                        >
                            {isLoading
                                ? 'Processing...'
                                : approvalStatus === 'approved'
                                  ? 'Approve Request'
                                  : 'Reject Request'}
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
