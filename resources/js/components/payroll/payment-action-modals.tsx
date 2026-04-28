import React from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertCircle, Eye } from 'lucide-react';
import type { PaymentTracking } from '@/types/payroll-pages';

interface ViewPaymentDetailsModalProps {
    isOpen: boolean;
    payment?: PaymentTracking;
    onClose: () => void;
}

interface MarkPaidConfirmationDialogProps {
    isOpen: boolean;
    payment?: PaymentTracking;
    onClose: () => void;
    onConfirm: (paymentId: number) => void;
    isLoading?: boolean;
}

/**
 * View Payment Details Modal
 * Displays comprehensive payment information for review
 */
export function ViewPaymentDetailsModal({
    isOpen,
    payment,
    onClose,
}: ViewPaymentDetailsModalProps) {
    if (!payment) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Eye className="h-5 w-5 text-blue-600" />
                        Payment Details
                    </DialogTitle>
                    <DialogDescription>
                        Complete payment information for {payment.employee_name}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Employee Information */}
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 mb-3">Employee Information</h3>
                        <div className="grid grid-cols-2 gap-4 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p className="text-xs font-medium text-gray-500">Name</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.employee_name}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Employee Number</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.employee_number}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Department</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.department}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Position</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.position || '-'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Payment Information */}
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 mb-3">Payment Information</h3>
                        <div className="grid grid-cols-2 gap-4 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p className="text-xs font-medium text-gray-500">Payroll Period</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.period_name}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Gross Pay</p>
                                <p className="text-sm font-semibold text-gray-900">
                                    {payment.formatted_net_pay}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Net Pay</p>
                                <p className="text-sm font-semibold text-blue-600 text-lg">
                                    {payment.formatted_net_pay}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Payment Method</p>
                                <p className="text-sm font-semibold text-gray-900">{payment.payment_method_label}</p>
                            </div>
                            <div>
                                <p className="text-xs font-medium text-gray-500">Status</p>
                                <p className={`text-sm font-semibold ${
                                    payment.payment_status === 'paid' ? 'text-green-600' :
                                    payment.payment_status === 'pending' ? 'text-yellow-600' :
                                    payment.payment_status === 'failed' ? 'text-red-600' :
                                    'text-blue-600'
                                }`}>
                                    {payment.payment_status_label}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Payment Details */}
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900 mb-3">Payment Details</h3>
                        <div className="space-y-2 p-3 bg-gray-50 rounded-lg">
                            <div className="flex justify-between items-center py-2 border-b border-gray-200">
                                <span className="text-xs font-medium text-gray-600">Payment Date</span>
                                <span className="text-sm font-semibold text-gray-900">
                                    {payment.payment_date 
                                        ? new Date(payment.payment_date).toLocaleDateString('en-PH')
                                        : '-'
                                    }
                                </span>
                            </div>
                            <div className="flex justify-between items-center py-2 border-b border-gray-200">
                                <span className="text-xs font-medium text-gray-600">Reference Number</span>
                                <span className="text-sm font-mono text-gray-900">
                                    {payment.payment_reference || '-'}
                                </span>
                            </div>
                            {payment.account_number && (
                                <div className="flex justify-between items-center py-2 border-b border-gray-200">
                                    <span className="text-xs font-medium text-gray-600">Account Number</span>
                                    <span className="text-sm font-mono text-gray-900">
                                        {payment.account_number}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Mark as Paid Confirmation Dialog
 * Confirms intent to mark a payment as paid
 */
export function MarkPaidConfirmationDialog({
    isOpen,
    payment,
    onClose,
    onConfirm,
    isLoading = false,
}: MarkPaidConfirmationDialogProps) {
    if (!payment) return null;

    return (
        <AlertDialog open={isOpen} onOpenChange={onClose}>
            <AlertDialogContent>
                <AlertDialogTitle className="flex items-center gap-2">
                    <AlertCircle className="h-5 w-5 text-green-600" />
                    Confirm Payment
                </AlertDialogTitle>

                {/* Simple one-liner description for accessibility — no block elements */}
                <AlertDialogDescription>
                    Mark payment as paid for {payment.employee_name} — {payment.period_name}
                </AlertDialogDescription>

                {/* Rich content lives outside AlertDialogDescription */}
                <div className="space-y-3 mt-2">
                    <p className="text-sm text-gray-700">
                        Are you sure you want to mark this payment as <strong>paid</strong>?
                    </p>
                    <div className="bg-green-50 border border-green-200 rounded-lg p-3 space-y-2">
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-700">Employee:</span>
                            <span className="font-semibold text-gray-900">{payment.employee_name}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-700">Period:</span>
                            <span className="font-semibold text-gray-900">{payment.period_name}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-700">Amount:</span>
                            <span className="font-bold text-green-600">{payment.formatted_net_pay}</span>
                        </div>
                    </div>
                    <p className="text-xs text-amber-700">
                        ⚠️ This action will update the payment status to "Paid" and cannot be easily reversed.
                    </p>
                </div>

                <div className="flex gap-3 justify-end mt-4">
                    <AlertDialogCancel disabled={isLoading}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() => onConfirm(payment.id)}
                        disabled={isLoading}
                        className="bg-green-600 hover:bg-green-700"
                    >
                        {isLoading ? 'Processing...' : 'Confirm Payment'}
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialog>
    );
}