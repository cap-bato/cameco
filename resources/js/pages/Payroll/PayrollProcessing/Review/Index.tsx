import React, { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { PayrollReviewPageProps } from '@/types/payroll-review-types';
import { PayrollSummaryCards } from '@/components/payroll/payroll-summary-cards';
import { ReviewByDepartment } from '@/components/payroll/review-by-department';
import { ReviewExceptions } from '@/components/payroll/review-exceptions';
import { EmployeeCalculationDetails } from '@/components/payroll/employee-calculation-details';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AlertCircle, CheckCircle2, Lock, Download } from 'lucide-react';
import { Card } from '@/components/ui/card';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll',
        href: '/payroll/dashboard',
    },
    {
        title: 'Payroll Processing',
        href: '#',
    },
    {
        title: 'Payroll Review',
        href: '/payroll/review',
    },
];

export default function PayrollReviewPage({
    payroll_period,
    summary,
    departments,
    exceptions,
    approval_workflow,
    employee_calculations,
}: PayrollReviewPageProps) {
    const [selectedEmployee, setSelectedEmployee] = useState<number | null>(null);
    const [showLockDialog, setShowLockDialog] = useState(false);
    const [lockReason, setLockReason] = useState('');
    const [isSubmittingApproval, setIsSubmittingApproval] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);

    const handleApprove = async () => {
        setIsSubmittingApproval(true);
        setStatusMessage(null);
        try {
            await new Promise((resolve, reject) => {
                router.post(
                    `/payroll/review/${payroll_period.id}/approve`,
                    {},
                    {
                        onSuccess: (page) => {
                            setStatusMessage({
                                type: 'success',
                                message: 'Payroll approved successfully! ✓',
                            });
                            setIsSubmittingApproval(false);
                            resolve(page);
                            // Optionally reload or redirect
                            setTimeout(() => {
                                router.reload();
                            }, 2000);
                        },
                        onError: (errors) => {
                            const errorMsg = Object.values(errors).join(', ') || 'Failed to approve payroll';
                            setStatusMessage({
                                type: 'error',
                                message: String(errorMsg),
                            });
                            setIsSubmittingApproval(false);
                            reject(errors);
                        },
                    }
                );
            });
        } catch (error) {
            setIsSubmittingApproval(false);
            console.error('Approval error:', error);
        }
    };

    const handleReject = async (reason: string) => {
        setIsSubmittingApproval(true);
        setStatusMessage(null);
        try {
            await new Promise((resolve, reject) => {
                router.post(
                    `/payroll/review/${payroll_period.id}/reject`,
                    { reason },
                    {
                        onSuccess: (page) => {
                            setStatusMessage({
                                type: 'success',
                                message: 'Payroll rejected successfully',
                            });
                            setIsSubmittingApproval(false);
                            resolve(page);
                            setTimeout(() => {
                                router.reload();
                            }, 2000);
                        },
                        onError: (errors) => {
                            const errorMsg = Object.values(errors).join(', ') || 'Failed to reject payroll';
                            setStatusMessage({
                                type: 'error',
                                message: String(errorMsg),
                            });
                            setIsSubmittingApproval(false);
                            reject(errors);
                        },
                    }
                );
            });
        } catch (error) {
            setIsSubmittingApproval(false);
            console.error('Rejection error:', error);
        }
    };

    const handleLockPayroll = async () => {
        setIsSubmittingApproval(true);
        setStatusMessage(null);
        try {
            await new Promise((resolve, reject) => {
                router.post(
                    `/payroll/review/${payroll_period.id}/lock`,
                    { reason: lockReason },
                    {
                        onSuccess: (page) => {
                            setStatusMessage({
                                type: 'success',
                                message: 'Payroll locked successfully!',
                            });
                            setShowLockDialog(false);
                            setLockReason('');
                            setIsSubmittingApproval(false);
                            resolve(page);
                            setTimeout(() => {
                                router.reload();
                            }, 2000);
                        },
                        onError: (errors) => {
                            const errorMsg = Object.values(errors).join(', ') || 'Failed to lock payroll';
                            setStatusMessage({
                                type: 'error',
                                message: String(errorMsg),
                            });
                            setIsSubmittingApproval(false);
                            reject(errors);
                        },
                    }
                );
            });
        } catch (error) {
            setIsSubmittingApproval(false);
            console.error('Lock error:', error);
        }
    };

    const handleDownloadPayslips = async () => {
        setIsSubmittingApproval(true);
        setStatusMessage(null);
        try {
            await new Promise((resolve, reject) => {
                router.post(
                    `/payroll/review/${payroll_period.id}/download-payslips`,
                    {},
                    {
                        onSuccess: (page) => {
                            setStatusMessage({
                                type: 'success',
                                message: 'Payslips generated successfully! Download started.',
                            });
                            setIsSubmittingApproval(false);
                            resolve(page);
                        },
                        onError: (errors) => {
                            const errorMsg = Object.values(errors).join(', ') || 'Failed to generate payslips';
                            setStatusMessage({
                                type: 'error',
                                message: String(errorMsg),
                            });
                            setIsSubmittingApproval(false);
                            reject(errors);
                        },
                    }
                );
            });
        } catch (error) {
            setIsSubmittingApproval(false);
            console.error('Payslip download error:', error);
        }
    };

    const statusColor = payroll_period.status === 'approved' ? 'text-green-600' : 'text-blue-600';

    const statusIcon =
        payroll_period.status === 'approved' ? (
            <CheckCircle2 className="h-5 w-5" />
        ) : (
            <AlertCircle className="h-5 w-5" />
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Review" />

            <div className="space-y-6 rounded-xl p-6">
                {/* Status Message */}
                {statusMessage && (
                    <Card
                        className={`p-4 ${
                            statusMessage.type === 'success'
                                ? 'border-green-200 bg-green-50'
                                : 'border-red-200 bg-red-50'
                        }`}
                    >
                        <div className="flex gap-3">
                            {statusMessage.type === 'success' ? (
                                <CheckCircle2 className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
                            ) : (
                                <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                            )}
                            <p
                                className={`font-medium ${
                                    statusMessage.type === 'success' ? 'text-green-800' : 'text-red-800'
                                }`}
                            >
                                {statusMessage.message}
                            </p>
                        </div>
                    </Card>
                )}

                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold tracking-tight">{payroll_period.name}</h1>
                            <div className={`flex items-center gap-1 text-sm font-medium ${statusColor}`}>
                                {statusIcon}
                                <span className="capitalize">{payroll_period.status}</span>
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {payroll_period.start_date} to {payroll_period.end_date} • Pay Date:{' '}
                            {payroll_period.pay_date}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        {/* Approve - only when calculated */}
                        {approval_workflow.can_approve && (
                            <Button
                                onClick={handleApprove}
                                disabled={isSubmittingApproval}
                                className="gap-2 bg-green-600 hover:bg-green-700"
                            >
                                <CheckCircle2 className="h-4 w-4" />
                                {isSubmittingApproval ? 'Approving...' : 'Approve Payroll'}
                            </Button>
                        )}

                        {/* Lock - only when approved */}
                        {payroll_period.status === 'approved' && (
                            <Button
                                onClick={() => setShowLockDialog(true)}
                                variant="outline"
                                className="gap-2"
                            >
                                <Lock className="h-4 w-4" />
                                Lock & Finalize
                            </Button>
                        )}

                        {/* Send back for recalculation - payroll officer found error after approving */}
                        {approval_workflow.can_reject && (
                            <Button
                                onClick={() => {
                                    const reason = prompt('Reason for sending back for recalculation?');
                                    if (reason) handleReject(reason);
                                }}
                                variant="outline"
                                className="gap-2 border-orange-300 text-orange-600 hover:bg-orange-50"
                            >
                                Send Back for Recalculation
                            </Button>
                        )}
                    </div>
                </div>

                {/* Summary Cards */}
                <PayrollSummaryCards summary={summary} />

                {/* Exceptions Alert */}
                {exceptions.length > 0 && (
                    <Card className="border-yellow-200 bg-yellow-50 p-4">
                        <div className="flex gap-3">
                            <AlertCircle className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="font-medium text-yellow-800">
                                    {exceptions.length} Payroll Exception{exceptions.length !== 1 ? 's' : ''}
                                    Found
                                </p>
                                <p className="text-sm text-yellow-700">
                                    Review exceptions below before approving this payroll
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Officer Approval Status */}
                {payroll_period.status === 'approved' && (
                    <Card className="border-green-200 bg-green-50 p-4">
                        <div className="flex items-center gap-3">
                            <CheckCircle2 className="h-5 w-5 flex-shrink-0 text-green-600" />
                            <div>
                                <p className="font-medium text-green-800">Payroll Approved</p>
                                <p className="text-sm text-green-700">
                                    Approved by Payroll Officer. Ready to lock and release for payment.
                                </p>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Tabs for different views */}
                <Tabs defaultValue="department" className="w-full">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="department">Department Breakdown</TabsTrigger>
                        <TabsTrigger value="exceptions">
                            Exceptions
                            {exceptions.length > 0 && (
                                <span className="ml-2 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">
                                    {exceptions.length}
                                </span>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="employees">Employee Details</TabsTrigger>
                    </TabsList>

                    {/* Department Breakdown Tab */}
                    <TabsContent value="department" className="space-y-4">
                        <ReviewByDepartment departments={departments} />
                    </TabsContent>

                    {/* Exceptions Tab */}
                    <TabsContent value="exceptions" className="space-y-4">
                        {exceptions.length > 0 ? (
                            <ReviewExceptions exceptions={exceptions} />
                        ) : (
                            <Card className="p-8 text-center">
                                <CheckCircle2 className="mx-auto mb-3 h-12 w-12 text-green-600" />
                                <p className="text-muted-foreground">No exceptions found. Payroll is ready for approval.</p>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Employee Details Tab */}
                    <TabsContent value="employees" className="space-y-4">
                        <EmployeeCalculationDetails
                            employees={employee_calculations}
                            selectedEmployeeId={selectedEmployee}
                            onSelectEmployee={setSelectedEmployee}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Lock Dialog */}
            {showLockDialog && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md space-y-4 p-6">
                        <h2 className="text-lg font-semibold">Lock Payroll</h2>
                        <p className="text-sm text-muted-foreground">
                            Locking the payroll will prevent any further changes. This action is irreversible for the current
                            pay period.
                        </p>
                        <textarea
                            value={lockReason}
                            onChange={(e) => setLockReason(e.target.value)}
                            placeholder="Enter reason for locking payroll (optional)"
                            className="w-full rounded-lg border p-3 text-sm"
                            rows={4}
                        />
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setShowLockDialog(false)}
                                disabled={isSubmittingApproval}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleLockPayroll}
                                disabled={isSubmittingApproval}
                                className="bg-red-600 hover:bg-red-700"
                            >
                                {isSubmittingApproval ? 'Locking...' : 'Lock Payroll'}
                            </Button>
                        </div>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
