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

interface Department {
    id: number;
    name: string;
}

interface PaginatedAdvances {
    data: CashAdvance[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface AdvancesIndexProps {
    advances: PaginatedAdvances | CashAdvance[];
    filters?: { search?: string; status?: string; department_id?: string };
    employees?: Employee[];
    departments?: Department[];
    statuses?: string[];
}

export default function AdvancesIndex({
    advances,
    filters: serverFilters = {},
    employees: propEmployees = [],
    departments = [],
}: AdvancesIndexProps) {
    const [selectedAdvance, setSelectedAdvance] = useState<CashAdvance | undefined>();
    const [isRequestFormOpen, setIsRequestFormOpen] = useState(false);
    const [isApprovalModalOpen, setIsApprovalModalOpen] = useState(false);
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [filters, setFilters] = useState<FilterValues>({
        search: serverFilters.search ?? '',
        status: serverFilters.status ?? '',
        department: serverFilters.department_id ?? '',
        dateFrom: '',
        dateTo: '',
        amountFrom: null,
        amountTo: null,
    });

    const breadcrumb = [
        { title: 'Payroll', href: '/payroll' },
        { title: 'Cash Advances', href: '/payroll/advances' },
    ];

    // Support both paginated (object with .data) and flat array
    const advancesList: CashAdvance[] = Array.isArray(advances)
        ? advances
        : (advances as PaginatedAdvances).data ?? [];

    const pagination = !Array.isArray(advances) ? advances as PaginatedAdvances : null;

    // Summary metrics computed over the current page
    const summaryMetrics = useMemo(() => {
        const totalAdvances = pagination ? pagination.total : advancesList.length;
        const totalBalance = advancesList
            .filter((a) => a.deduction_status === 'active')
            .reduce((sum, a) => sum + a.remaining_balance, 0);
        const monthlyDeductions = advancesList
            .filter((a) => a.deduction_status === 'active')
            .reduce((sum, a) => sum + ((a.amount_approved ?? 0) / (a.number_of_installments || 1)), 0);
        const pendingApprovals = advancesList.filter((a) => a.approval_status === 'pending').length;
        const approvedAdvances = advancesList.filter((a) => a.approval_status === 'approved').length;
        const approvalRate = advancesList.length > 0 ? Math.round((approvedAdvances / advancesList.length) * 100) : 0;

        return { totalAdvances, totalBalance, monthlyDeductions, pendingApprovals, approvalRate };
    }, [advancesList, pagination]);

    // Push filters back to the server via Inertia
    const applyFilters = (newFilters: FilterValues) => {
        setFilters(newFilters);
        router.get(
            '/payroll/advances',
            {
                search: newFilters.search || undefined,
                status: newFilters.status || undefined,
                department_id: newFilters.department || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleApprove = (data: CashAdvanceApprovalData) => {
        if (!selectedAdvance) return;
        setIsSubmitting(true);
        router.post(
            `/payroll/advances/${selectedAdvance.id}/approve`,
            {
                amount_approved: data.amount_approved ?? selectedAdvance.amount_requested,
                deduction_schedule: data.deduction_schedule,
                number_of_installments: data.number_of_installments ?? 1,
                approval_notes: data.approval_notes,
            },
            {
                onSuccess: () => { setIsApprovalModalOpen(false); setSelectedAdvance(undefined); },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const handleReject = (data: CashAdvanceApprovalData) => {
        if (!selectedAdvance) return;
        setIsSubmitting(true);
        router.post(
            `/payroll/advances/${selectedAdvance.id}/reject`,
            { approval_notes: data.approval_notes },
            {
                onSuccess: () => { setIsApprovalModalOpen(false); setSelectedAdvance(undefined); },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const handleSubmitRequest = (data: CashAdvanceFormData) => {
        setIsSubmitting(true);
        router.post(
            '/payroll/advances',
            data,
            {
                onSuccess: () => { setIsRequestFormOpen(false); },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const employees: Employee[] = propEmployees;

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
                        <h2 className="text-lg font-semibold">
                            All Advances
                            {pagination && (
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    ({pagination.total} total)
                                </span>
                            )}
                        </h2>
                        <AdvancesFilter onFilter={applyFilters} />
                    </div>

                    <AdvancesListTable
                        advances={advancesList}
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

                    {/* Pagination */}
                    {pagination && pagination.last_page > 1 && (
                        <div className="flex items-center justify-between pt-4 border-t">
                            <p className="text-sm text-muted-foreground">
                                Page {pagination.current_page} of {pagination.last_page}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!pagination.prev_page_url}
                                    onClick={() => router.get(pagination.prev_page_url!, {}, { preserveState: true })}
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={!pagination.next_page_url}
                                    onClick={() => router.get(pagination.next_page_url!, {}, { preserveState: true })}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>

                {/* Request Form Modal */}
                {isRequestFormOpen && (
                    <AdvanceRequestForm
                        isOpen={isRequestFormOpen}
                        onClose={() => setIsRequestFormOpen(false)}
                        onSubmit={handleSubmitRequest}
                        employees={employees}
                        isLoading={isSubmitting}
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
                        isLoading={isSubmitting}
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
