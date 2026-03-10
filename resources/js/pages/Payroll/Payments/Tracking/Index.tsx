import React, { useState, useEffect, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { CheckCircle, AlertCircle, Download, RefreshCw } from 'lucide-react';
import PaymentStatusCards from '@/components/payroll/payment-status-cards';
import PaymentTrackingTable from '@/components/payroll/payment-tracking-table';
import PaymentConfirmationModal, { PaymentConfirmationData } from '@/components/payroll/payment-confirmation-modal';
import FailedPaymentsList from '@/components/payroll/failed-payments-list';
import { ViewPaymentDetailsModal, MarkPaidConfirmationDialog } from '@/components/payroll/payment-action-modals';
import type { PaymentTrackingPageProps, PaymentTracking } from '@/types/payroll-pages';

const breadcrumbs = [
    { title: 'Payroll', href: '/payroll/dashboard' },
    { title: 'Payments', href: '#' },
    { title: 'Payment Tracking', href: '/payroll/payments/tracking' },
];

interface BreadcrumbItem {
    title: string;
    href: string;
}

interface FlashData {
    success?: string;
    error?: string;
}

export default function PaymentTrackingIndex({
    payments,
    summary,
    payroll_periods,
    departments,
    payment_methods,
    payment_statuses,
    failed_payments,
    flash = {},
}: PaymentTrackingPageProps & { flash?: FlashData }) {
    // Flash messages from Inertia
    const hasNotification = !!(flash.success || flash.error);
    const isSuccessNotification = !!flash.success;
    const notificationMessage = (flash.success || flash.error) ?? '';

    // Filter state
    const [search, setSearch] = useState('');
    const [periodId, setPeriodId] = useState('all');
    const [departmentId, setDepartmentId] = useState('all');
    const [paymentStatus, setPaymentStatus] = useState('all');
    const [paymentMethod, setPaymentMethod] = useState('all');

    // Modal states
    const [isConfirmationOpen, setIsConfirmationOpen] = useState(false);
    const [selectedPayment, setSelectedPayment] = useState<PaymentTracking | null>(null);
    const [isViewDetailsOpen, setIsViewDetailsOpen] = useState(false);
    const [viewDetailsPayment, setViewDetailsPayment] = useState<PaymentTracking | null>(null);
    const [isMarkPaidDialogOpen, setIsMarkPaidDialogOpen] = useState(false);
    const [markPaidPayment, setMarkPaidPayment] = useState<PaymentTracking | null>(null);
    const [notificationDialog, setNotificationDialog] = useState({
        isOpen: hasNotification,
        isSuccess: isSuccessNotification,
        message: notificationMessage,
    });

    // Action dialog states
    const [retryConfirmDialog, setRetryConfirmDialog] = useState({ isOpen: false, paymentId: 0 });
    const [methodChangeDialog, setMethodChangeDialog] = useState({ isOpen: false, paymentId: 0, newMethod: '' });
    const [isProcessing, setIsProcessing] = useState(false);

    // Apply filters via GET request
    const applyFilters = useCallback(() => {
        const params: Record<string, string | number> = {};

        if (search.trim()) params.search = search;
        if (periodId !== 'all') params.period_id = periodId;
        if (departmentId !== 'all') params.department_id = departmentId;
        if (paymentStatus !== 'all') params.payment_status = paymentStatus;
        if (paymentMethod !== 'all') params.payment_method = paymentMethod;

        router.get('/payroll/payments/tracking', params, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [search, periodId, departmentId, paymentStatus, paymentMethod]);

    // Reactive filtering with debounce
    useEffect(() => {
        const timer = setTimeout(() => {
            applyFilters();
        }, 500);

        return () => {
            clearTimeout(timer);
        };
    }, [search, periodId, departmentId, paymentStatus, paymentMethod, applyFilters]);

    // Show notification when flash message appears
    useEffect(() => {
        // Keep notification visible if there's a flash message
    }, [hasNotification]);

    const handleClearFilters = () => {
        setSearch('');
        setPeriodId('all');
        setDepartmentId('all');
        setPaymentStatus('all');
        setPaymentMethod('all');
        router.get('/payroll/payments/tracking', {});
    };

    const handleExportReport = () => {
        setIsProcessing(true);
        try {
            // Prepare CSV data
            const headers = ['Employee Name', 'Employee Number', 'Department', 'Period', 'Net Pay', 'Payment Method', 'Status', 'Payment Date', 'Reference'];
            const rows = payments.map(p => [
                p.employee_name,
                p.employee_number,
                p.department,
                p.period_name,
                p.formatted_net_pay,
                p.payment_method_label,
                p.payment_status_label,
                p.payment_date ? new Date(p.payment_date).toLocaleDateString('en-PH') : '-',
                p.payment_reference || '-',
            ]);

            // Create CSV content
            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${cell}"`).join(',')),
            ].join('\n');

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `payment-tracking-${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } finally {
            setIsProcessing(false);
        }
    };

    // Confirm Payment: Records detailed payment proof (date, reference, file) for processing payments
    const handleConfirmPayment = (paymentId: number, data: PaymentConfirmationData) => {
        setIsProcessing(true);
        router.post('/payroll/payments/tracking/confirm', {
            payment_id: paymentId,
            payment_date: data.payment_date,
            payment_reference: data.payment_reference,
            notes: data.notes,
        });
        setIsConfirmationOpen(false);
    };

    // Mark as Paid: Simple status update for pending payments (no additional data required)
    const handleMarkPaid = (paymentId: number) => {
        const payment = payments.find(p => p.id === paymentId);
        if (payment) {
            setMarkPaidPayment(payment);
            setIsMarkPaidDialogOpen(true);
        }
    };

    // Confirm the mark-paid action (just status change, no form data)
    const handleMarkPaidConfirm = (paymentId: number) => {
        setIsProcessing(true);
        router.post('/payroll/payments/tracking/mark-paid', {
            payment_id: paymentId,
        });
        setIsMarkPaidDialogOpen(false);
    };

    const handleRetryConfirm = () => {
        setIsProcessing(true);
        router.post('/payroll/payments/tracking/retry', {
            payment_id: retryConfirmDialog.paymentId,
        });
        setRetryConfirmDialog({ isOpen: false, paymentId: 0 });
    };

    const handleChangeMethodConfirm = () => {
        setIsProcessing(true);
        router.post('/payroll/payments/tracking/change-method', {
            payment_id: methodChangeDialog.paymentId,
            new_method: methodChangeDialog.newMethod,
        });
        setMethodChangeDialog({ isOpen: false, paymentId: 0, newMethod: '' });
    };

    const handleOpenConfirmation = (payment: PaymentTracking) => {
        setSelectedPayment(payment);
        setIsConfirmationOpen(true);
    };

    const handleRetry = (paymentId: number) => {
        setRetryConfirmDialog({ isOpen: true, paymentId });
    };

    const handleChangeMethod = (paymentId: number, newMethod: string) => {
        setMethodChangeDialog({ isOpen: true, paymentId, newMethod });
    };

    const handleViewPayment = (payment: PaymentTracking) => {
        setViewDetailsPayment(payment);
        setIsViewDetailsOpen(true);
    };

    // Get filter labels
    const getFilterLabel = (type: string, value: string): string | null => {
        if (value === 'all' || !value) return null;

        switch (type) {
            case 'search':
                return `Search: ${value}`;
            case 'period':
                return (payroll_periods as Array<{id: number; name: string}>).find((p) => p.id.toString() === value)?.name || null;
            case 'department':
                return (departments as Array<{id: number; name: string}>).find((d) => d.id.toString() === value)?.name || null;
            case 'status':
                return `Status: ${value.charAt(0).toUpperCase() + value.slice(1)}`;
            case 'method':
                return `Method: ${value.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}`;
            default:
                return null;
        }
    };

    // Check if any filters are applied
    const hasActiveFilters = search || periodId !== 'all' || departmentId !== 'all' || paymentStatus !== 'all' || paymentMethod !== 'all';
    const activeFilters = [
        search && getFilterLabel('search', search),
        periodId !== 'all' && getFilterLabel('period', periodId),
        departmentId !== 'all' && getFilterLabel('department', departmentId),
        paymentStatus !== 'all' && getFilterLabel('status', paymentStatus),
        paymentMethod !== 'all' && getFilterLabel('method', paymentMethod),
    ].filter(Boolean);

    const getMethodLabel = (method: string) => {
        return method.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs as BreadcrumbItem[]}>
            <Head title="Payment Tracking" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold text-gray-900">Payment Tracking</h1>
                        <p className="text-gray-600 text-sm">
                            Track and manage employee payment status, mark payments as paid, and handle failed transactions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={handleClearFilters}
                            className="text-xs"
                            disabled={isProcessing}
                        >
                            <RefreshCw className="h-4 w-4 mr-1" />
                            Clear Filters
                        </Button>
                        <Button
                            className="bg-blue-600 hover:bg-blue-700 text-xs"
                            onClick={handleExportReport}
                            disabled={isProcessing}
                        >
                            <Download className="h-4 w-4 mr-1" />
                            {isProcessing ? 'Exporting...' : 'Export Report'}
                        </Button>
                    </div>
                </div>

                {/* Active Filters Indicator */}
                {hasActiveFilters && (
                    <div className="flex flex-wrap items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <span className="text-xs font-semibold text-blue-900">Active Filters:</span>
                        {activeFilters.map((filter, index) => (
                            <span
                                key={index}
                                className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full border border-blue-300"
                            >
                                {filter}
                            </span>
                        ))}
                    </div>
                )}

                {/* Summary Cards */}
                <PaymentStatusCards summary={summary} />

                {/* Filter Card */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-5">
                            {/* Search Input */}
                            <div>
                                <label className="text-xs font-medium text-gray-700 block mb-1">
                                    Search Employee
                                </label>
                                <Input
                                    type="text"
                                    placeholder="Name or Employee #"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="text-sm"
                                />
                            </div>

                            {/* Period Filter */}
                            <div>
                                <label className="text-xs font-medium text-gray-700 block mb-1">
                                    Payroll Period
                                </label>
                                <Select value={periodId} onValueChange={setPeriodId}>
                                    <SelectTrigger className="text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Periods</SelectItem>
                                        {payroll_periods.map((period) => (
                                            <SelectItem key={period.id} value={period.id.toString()}>
                                                {period.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Department Filter */}
                            <div>
                                <label className="text-xs font-medium text-gray-700 block mb-1">
                                    Department
                                </label>
                                <Select value={departmentId} onValueChange={setDepartmentId}>
                                    <SelectTrigger className="text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Payment Status Filter */}
                            <div>
                                <label className="text-xs font-medium text-gray-700 block mb-1">
                                    Payment Status
                                </label>
                                <Select value={paymentStatus} onValueChange={setPaymentStatus}>
                                    <SelectTrigger className="text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        {payment_statuses.map((status) => (
                                            <SelectItem key={status} value={status}>
                                                {status.charAt(0).toUpperCase() + status.slice(1)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Payment Method Filter */}
                            <div>
                                <label className="text-xs font-medium text-gray-700 block mb-1">
                                    Payment Method
                                </label>
                                <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                                    <SelectTrigger className="text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Methods</SelectItem>
                                        {payment_methods.map((method) => (
                                            <SelectItem key={method.id} value={method.method_type}>
                                                {method.display_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Failed Payments Section */}
                {failed_payments.length > 0 && (
                    <FailedPaymentsList
                        failedPayments={failed_payments}
                        onRetry={handleRetry}
                        onChangeMethod={handleChangeMethod}
                    />
                )}

                {/* Payment Tracking Table */}
                <PaymentTrackingTable
                    payments={payments}
                    onMarkPaid={handleMarkPaid}
                    onRetry={handleRetry}
                    onConfirm={(paymentId: number) => {
                        const payment = payments.find(p => p.id === paymentId);
                        if (payment) handleOpenConfirmation(payment);
                    }}
                    onView={handleViewPayment}
                />

                {/* Payment Confirmation Modal */}
                <PaymentConfirmationModal
                    isOpen={isConfirmationOpen}
                    payment={selectedPayment ?? undefined}
                    onClose={() => setIsConfirmationOpen(false)}
                    onConfirm={handleConfirmPayment}
                    isLoading={isProcessing}
                />

                {/* Retry Confirmation Dialog */}
                <AlertDialog open={retryConfirmDialog.isOpen} onOpenChange={(open) => setRetryConfirmDialog({ ...retryConfirmDialog, isOpen: open })}>
                    <AlertDialogContent>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-yellow-600" />
                            Retry Payment
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to retry this failed payment? The system will attempt to process it again with the current payment method.
                        </AlertDialogDescription>
                        <div className="flex gap-3 justify-end">
                            <AlertDialogCancel disabled={isProcessing}>Cancel</AlertDialogCancel>
                            <Button
                                onClick={handleRetryConfirm}
                                disabled={isProcessing}
                                className="bg-yellow-600 hover:bg-yellow-700"
                            >
                                {isProcessing ? 'Processing...' : 'Retry Payment'}
                            </Button>
                        </div>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Payment Method Change Dialog */}
                <Dialog open={methodChangeDialog.isOpen} onOpenChange={(open) => setMethodChangeDialog({ ...methodChangeDialog, isOpen: open })}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <AlertCircle className="h-5 w-5 text-blue-600" />
                                Confirm Payment Method Change
                            </DialogTitle>
                            <DialogDescription>
                                You are changing the payment method to <strong>{getMethodLabel(methodChangeDialog.newMethod)}</strong>. The payment will be retried with the new method.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setMethodChangeDialog({ isOpen: false, paymentId: 0, newMethod: '' })}
                                disabled={isProcessing}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleChangeMethodConfirm}
                                disabled={isProcessing}
                                className="bg-blue-600 hover:bg-blue-700"
                            >
                                {isProcessing ? 'Processing...' : 'Change Method & Retry'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Notification Dialog */}
                <AlertDialog open={notificationDialog.isOpen} onOpenChange={() => setNotificationDialog({ ...notificationDialog, isOpen: false })}>
                    <AlertDialogContent>
                        <AlertDialogTitle className="flex items-center gap-2">
                            {notificationDialog.isSuccess ? (
                                <>
                                    <CheckCircle className="h-5 w-5 text-green-600" />
                                    Success
                                </>
                            ) : (
                                <>
                                    <AlertCircle className="h-5 w-5 text-red-600" />
                                    Error
                                </>
                            )}
                        </AlertDialogTitle>
                        <AlertDialogDescription className="text-gray-700">
                            {notificationDialog.message}
                        </AlertDialogDescription>
                        <AlertDialogAction>OK</AlertDialogAction>
                    </AlertDialogContent>
                </AlertDialog>

                {/* View Payment Details Modal */}
                <ViewPaymentDetailsModal
                    isOpen={isViewDetailsOpen}
                    payment={viewDetailsPayment ?? undefined}
                    onClose={() => setIsViewDetailsOpen(false)}
                />

                {/* Mark as Paid Confirmation Dialog */}
                <MarkPaidConfirmationDialog
                    isOpen={isMarkPaidDialogOpen}
                    payment={markPaidPayment ?? undefined}
                    onClose={() => setIsMarkPaidDialogOpen(false)}
                    onConfirm={handleMarkPaidConfirm}
                    isLoading={isProcessing}
                />
            </div>
        </AppLayout>
    );
}
