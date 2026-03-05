import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { CashAdvance, CashAdvanceFormData, CashAdvanceApprovalData } from '@/types/payroll-pages';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, TrendingUp, Clock, CheckCircle2, AlertCircle } from 'lucide-react';
import { AdvancesListTable } from '@/components/payroll/advances-list-table';
import { AdvanceRequestForm } from '@/components/payroll/advance-request-form';
import { AdvanceApprovalModal } from '@/components/payroll/advance-approval-modal';
import { AdvanceDeductionTracker } from '@/components/payroll/advance-deduction-tracker';
import { AdvancesFilter, FilterValues } from '@/components/payroll/advances-filter';
import { formatCurrency } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';

interface Employee {
    id: number;
    name: string;
    employee_number: string;
    department: string;
}

interface AdvancesIndexProps {
    advances: CashAdvance[];
    initialFilters?: FilterValues;
    employees?: Array<{ id: number; name: string; employee_number: string; department: string }>;
}

export default function AdvancesIndex({ advances, initialFilters, employees: propEmployees }: AdvancesIndexProps) {
    const [selectedAdvance, setSelectedAdvance] = useState<CashAdvance | undefined>();
    const [isRequestFormOpen, setIsRequestFormOpen] = useState(false);
    const [isApprovalModalOpen, setIsApprovalModalOpen] = useState(false);
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);
    const [filters, setFilters] = useState<FilterValues>(initialFilters || {
        search: '',
        status: '',
        department: '',
        dateFrom: '',
        dateTo: '',
        amountFrom: null,
        amountTo: null,
    });

    const breadcrumb = [
        { title: 'Payroll', href: '/payroll' },
        { title: 'Cash Advances', href: '/payroll/advances' },
    ];

    // Calculate summary metrics
    const summaryMetrics = useMemo(() => {
        const totalAdvances = advances.length;
        const totalBalance = advances
            .filter((a) => a.deduction_status === 'active')
            .reduce((sum, a) => sum + a.remaining_balance, 0);
        const monthlyDeductions = advances
            .filter((a) => a.deduction_status === 'active')
            .reduce((sum, a) => sum + ((a.amount_approved ?? 0) / (a.number_of_installments || 1)), 0);
        const pendingApprovals = advances.filter((a) => a.approval_status === 'pending').length;
        const approvedAdvances = advances.filter((a) => a.approval_status === 'approved').length;
        const approvalRate = totalAdvances > 0 ? Math.round((approvedAdvances / totalAdvances) * 100) : 0;

        return {
            totalAdvances,
            totalBalance,
            monthlyDeductions,
            pendingApprovals,
            approvalRate,
        };
    }, [advances]);

    // Filter advances
    const filteredAdvances = useMemo(() => {
        return advances.filter((advance) => {
            // Search filter
            if (filters.search) {
                const searchLower = filters.search.toLowerCase();
                if (!advance.employee_name.toLowerCase().includes(searchLower) &&
                    !advance.employee_number?.toLowerCase().includes(searchLower)) {
                    return false;
                }
            }

            // Status filter
            if (filters.status) {
                const status = advance.approval_status === 'pending' && filters.status === 'pending'
                    ? 'pending'
                    : advance.approval_status === 'approved' && filters.status === 'approved'
                      ? 'approved'
                      : advance.approval_status === 'rejected' && filters.status === 'rejected'
                        ? 'rejected'
                        : advance.deduction_status === 'active' && filters.status === 'active'
                          ? 'active'
                          : advance.deduction_status === 'completed' && filters.status === 'completed'
                            ? 'completed'
                            : advance.deduction_status === 'cancelled' && filters.status === 'cancelled'
                              ? 'cancelled'
                              : null;

                if (status !== filters.status) return false;
            }

            // Department filter
            if (filters.department && advance.department_name.toLowerCase() !== filters.department.toLowerCase()) {
                return false;
            }

            // Date range filter
            if (filters.dateFrom && new Date(advance.requested_date) < new Date(filters.dateFrom)) {
                return false;
            }
            if (filters.dateTo && new Date(advance.requested_date) > new Date(filters.dateTo)) {
                return false;
            }

            // Amount range filter
            if (filters.amountFrom && advance.amount_requested < filters.amountFrom) {
                return false;
            }
            if (filters.amountTo && advance.amount_requested > filters.amountTo) {
                return false;
            }

            return true;
        });
    }, [advances, filters]);

    const handleApprove = (data: CashAdvanceApprovalData) => {
        router.post(`/payroll/advances/${data.advance_id}/approve`, {
            amount_approved: data.amount_approved,
            deduction_schedule: data.deduction_schedule,
            number_of_installments: data.number_of_installments,
            approval_notes: data.approval_notes,
        }, {
            onSuccess: () => {
                setIsApprovalModalOpen(false);
                // Success notification handled by backend
            },
            onError: (errors: any) => {
                console.error('Approval failed:', errors);
            },
        });
    };

    const handleReject = (data: CashAdvanceApprovalData) => {
        router.post(`/payroll/advances/${data.advance_id}/reject`, {
            rejection_reason: data.rejection_reason,
        }, {
            onSuccess: () => {
                setIsApprovalModalOpen(false);
            },
            onError: (errors: any) => {
                console.error('Rejection failed:', errors);
            },
        });
    };

    const handleSubmitRequest = (data: CashAdvanceFormData) => {
        router.post('/payroll/advances', data, {
            onSuccess: () => {
                setIsRequestFormOpen(false);
            },
            onError: (errors: any) => {
                console.error('Request failed:', errors);
            },
        });
    };

    const employees: Employee[] = propEmployees || [];

    return (
        <AppLayout breadcrumbs={breadcrumb}>

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Cash Advances</h1>
                        <p className="text-muted-foreground mt-1">Manage employee cash advances and deduction schedules</p>
                    </div>
                    <Button onClick={() => setIsRequestFormOpen(true)} className="gap-2" size="lg">
                        <Plus className="h-4 w-4" />
                        Request Advance
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="p-6 space-y-2 border-l-4 border-l-blue-500">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground font-medium">Total Advances</p>
                            <TrendingUp className="h-5 w-5 text-blue-500" />
                        </div>
                        <p className="text-3xl font-bold">{summaryMetrics.totalAdvances}</p>
                    </Card>

                    <Card className="p-6 space-y-2 border-l-4 border-l-orange-500">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground font-medium">Total Balance</p>
                            <AlertCircle className="h-5 w-5 text-orange-500" />
                        </div>
                        <p className="text-3xl font-bold">{formatCurrency(summaryMetrics.totalBalance)}</p>
                    </Card>

                    <Card className="p-6 space-y-2 border-l-4 border-l-green-500">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground font-medium">Monthly Deductions</p>
                            <CheckCircle2 className="h-5 w-5 text-green-500" />
                        </div>
                        <p className="text-3xl font-bold">{formatCurrency(summaryMetrics.monthlyDeductions)}</p>
                    </Card>

                    <Card className="p-6 space-y-2 border-l-4 border-l-purple-500">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-muted-foreground font-medium">Approval Rate</p>
                            <Clock className="h-5 w-5 text-purple-500" />
                        </div>
                        <p className="text-3xl font-bold">{summaryMetrics.approvalRate}%</p>
                        <p className="text-xs text-muted-foreground">{summaryMetrics.pendingApprovals} pending</p>
                    </Card>
                </div>

                {/* Filters and Table */}
                <Card className="p-6 space-y-4">
                    <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <h2 className="text-lg font-semibold">All Advances</h2>
                        <AdvancesFilter onFilter={setFilters} />
                    </div>

                    <AdvancesListTable
                        advances={filteredAdvances}
                        onView={(advance) => {
                            setSelectedAdvance(advance);
                            setIsDetailsOpen(true);
                        }}
                        onApprove={(advance) => {
                            setSelectedAdvance(advance);
                            setIsApprovalModalOpen(true);
                        }}
                        onReject={(advance) => {
                            setSelectedAdvance(advance);
                            setIsApprovalModalOpen(true);
                        }}
                        onEdit={(advance) => {
                            setSelectedAdvance(advance);
                            setIsRequestFormOpen(true);
                        }}
                        onComplete={(advance) => {
                            console.log('Mark completed:', advance.id);
                        }}
                    />
                </Card>

                {/* Request Form Modal */}
                {isRequestFormOpen && (
                    <AdvanceRequestForm
                        isOpen={isRequestFormOpen}
                        onClose={() => setIsRequestFormOpen(false)}
                        onSubmit={handleSubmitRequest}
                        employees={employees}
                    />
                )}

                {/* Approval Modal */}
                {isApprovalModalOpen && selectedAdvance && (
                    <AdvanceApprovalModal
                        isOpen={isApprovalModalOpen}
                        onClose={() => setIsApprovalModalOpen(false)}
                        advance={selectedAdvance}
                        onApprove={handleApprove}
                        onReject={handleReject}
                    />
                )}

                {/* Details Modal with Deduction Tracker */}
                {isDetailsOpen && selectedAdvance && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
                        <Card className="w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h2 className="text-2xl font-bold">{selectedAdvance.employee_name}</h2>
                                    <p className="text-muted-foreground">Advance ID: {selectedAdvance.id}</p>
                                </div>
                                <Button variant="outline" onClick={() => setIsDetailsOpen(false)}>
                                    Close
                                </Button>
                            </div>

                            <AdvanceDeductionTracker
                                advance={selectedAdvance}
                                deductions={[]}
                            />
                        </Card>
                    </div>
                )}
            </div>

    </AppLayout>
    );
}
