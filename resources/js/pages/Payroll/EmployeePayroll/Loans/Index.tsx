import { useState, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { PayrollLoansPageProps, EmployeeLoan, EmployeeLoanFormData, LoanPayment } from '@/types/payroll-pages';
import { LoansListTable } from '@/components/payroll/loans-list-table';
import { LoansFilter, LoansFilterState } from '@/components/payroll/loans-filter';
import { LoanFormModal } from '@/components/payroll/loan-form-modal';
import { LoanDetailsModal } from '@/components/payroll/loan-details-modal';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll',
        href: '/payroll/dashboard',
    },
    {
        title: 'Employee Payroll',
        href: '#',
    },
    {
        title: 'Loans & Advances',
        href: '/payroll/loans',
    },
];

export default function LoansPage({
    loans,
    employees = [],
    approvers = [],
    departments = [],
}: PayrollLoansPageProps & {
    employees?: Array<{ id: number; name: string; employee_number: string; department: string }>;
    approvers?: Array<{ id: number; name: string }>;
    departments?: Array<{ id: number; name: string }>;
}) {
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);
    const [selectedLoan, setSelectedLoan] = useState<EmployeeLoan | null>(null);
    const [selectedLoanPayments, setSelectedLoanPayments] = useState<LoanPayment[]>([]);

    const [filters, setFilters] = useState<LoansFilterState>({
        search: '',
        loan_type: [],
        status: [],
        department_id: undefined,
        loan_date_from: undefined,
        loan_date_to: undefined,
    });

    // Filter loans based on active filters
    const filteredLoans = useMemo(() => {
        return loans.filter((loan) => {
            // Search filter
            if (filters.search) {
                const searchLower = filters.search.toLowerCase();
                if (!loan.employee_name.toLowerCase().includes(searchLower) &&
                    !loan.employee_number.toLowerCase().includes(searchLower) &&
                    !loan.loan_number.toLowerCase().includes(searchLower)) {
                    return false;
                }
            }

            // Loan type filter
            if (filters.loan_type.length > 0 && !filters.loan_type.includes(loan.loan_type)) {
                return false;
            }

            // Status filter
            if (filters.status.length > 0 && !filters.status.includes(loan.status)) {
                return false;
            }

            // Department filter
            if (filters.department_id && loan.department_id !== parseInt(filters.department_id)) {
                return false;
            }

            // Date range filters
            if (filters.loan_date_from) {
                const loanDate = new Date(loan.loan_date);
                const filterDate = new Date(filters.loan_date_from);
                if (loanDate < filterDate) return false;
            }

            if (filters.loan_date_to) {
                const loanDate = new Date(loan.loan_date);
                const filterDate = new Date(filters.loan_date_to);
                if (loanDate > filterDate) return false;
            }

            return true;
        });
    }, [loans, filters]);

    // Calculate summary stats
    const summaryStats = useMemo(() => {
        const activeLoans = filteredLoans.filter(l => l.status === 'active');
        const totalBalance = filteredLoans.reduce((sum, loan) => sum + loan.remaining_balance, 0);
        const totalDeductionsThisPeriod = filteredLoans.reduce((sum, loan) => sum + loan.monthly_amortization, 0);

        return {
            totalLoans: filteredLoans.length,
            activeLoans: activeLoans.length,
            totalBalance,
            totalDeductionsThisPeriod,
        };
    }, [filteredLoans]);

    // Fetch payment history from backend
    const getPaymentHistory = async (loanId: number): Promise<LoanPayment[]> => {
        try {
            const response = await fetch(`/payroll/loans/${loanId}/payments`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch payment history');
            }

            const data = await response.json();
            return data.payments || [];
        } catch (error) {
            console.error('Error fetching payment history:', error);
            return [];
        }
    };

    const handleViewLoan = async (loan: EmployeeLoan) => {
        setSelectedLoan(loan);
        const payments = await getPaymentHistory(loan.id);
        setSelectedLoanPayments(payments);
        setIsDetailsOpen(true);
    };

    const handleEditLoan = (loan: EmployeeLoan | undefined) => {
        if (loan) {
            setSelectedLoan(loan);
            setIsFormOpen(true);
        }
    };

    const handleDeleteLoan = (loan: EmployeeLoan | undefined) => {
        if (loan && window.confirm(`Are you sure you want to cancel this loan? (${loan.loan_number})`)) {
            router.delete(`/payroll/loans/${loan.id}`, {
                onError: (errors) => {
                    console.error('Failed to cancel loan:', errors);
                },
            });
        }
    };

    const handleCreateNewLoan = () => {
        setSelectedLoan(null);
        setIsFormOpen(true);
    };

    const handleFormSubmit = (data: EmployeeLoanFormData) => {
        if (selectedLoan) {
            router.put(`/payroll/loans/${selectedLoan.id}`, data as unknown as Record<string, string>, {
                onSuccess: () => {
                    setIsFormOpen(false);
                    setSelectedLoan(null);
                },
                onError: (errors) => {
                    console.error('Failed to update loan:', errors);
                },
            });
        } else {
            router.post('/payroll/loans', data as unknown as Record<string, string>, {
                onSuccess: () => {
                    setIsFormOpen(false);
                    setSelectedLoan(null);
                },
                onError: (errors) => {
                    console.error('Failed to create loan:', errors);
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loans Management" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">Loans Management</h1>
                        <p className="text-muted-foreground">
                            Manage employee loans including SSS, Pag-IBIG, company, and cash advances
                        </p>
                    </div>
                    <Button onClick={handleCreateNewLoan} className="gap-2">
                        <Plus className="h-4 w-4" />
                        New Loan
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card className="p-4">
                        <p className="text-xs font-semibold text-muted-foreground">Total Loans</p>
                        <p className="text-3xl font-bold mt-2">{summaryStats.totalLoans}</p>
                        <p className="text-xs text-muted-foreground mt-1">
                            {summaryStats.activeLoans} active
                        </p>
                    </Card>
                    <Card className="p-4">
                        <p className="text-xs font-semibold text-muted-foreground">Total Balance</p>
                        <p className="text-3xl font-bold mt-2">
                            ₱{summaryStats.totalBalance.toFixed(2)}
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">Outstanding amount</p>
                    </Card>
                    <Card className="p-4">
                        <p className="text-xs font-semibold text-muted-foreground">Monthly Deduction</p>
                        <p className="text-3xl font-bold mt-2">
                            ₱{summaryStats.totalDeductionsThisPeriod.toFixed(2)}
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">Total monthly amortization</p>
                    </Card>
                    <Card className="p-4">
                        <p className="text-xs font-semibold text-muted-foreground">Active Status</p>
                        <p className="text-3xl font-bold mt-2">
                            {((summaryStats.activeLoans / summaryStats.totalLoans) * 100).toFixed(0)}%
                        </p>
                        <p className="text-xs text-muted-foreground mt-1">Loan collection rate</p>
                    </Card>
                </div>

                {/* Filters */}
                <LoansFilter
                    filters={filters}
                    onFiltersChange={setFilters}
                    departments={departments}
                />

                {/* Loans Table */}
                <LoansListTable
                    loans={filteredLoans}
                    onView={handleViewLoan}
                    onEdit={handleEditLoan}
                    onDelete={handleDeleteLoan}
                />

                {/* Modals */}
                <LoanFormModal
                    isOpen={isFormOpen}
                    onClose={() => {
                        setIsFormOpen(false);
                        setSelectedLoan(null);
                    }}
                    onSubmit={handleFormSubmit}
                    loan={selectedLoan || undefined}
                    employees={employees}
                    approvers={approvers}
                />

                <LoanDetailsModal
                    isOpen={isDetailsOpen}
                    onClose={() => {
                        setIsDetailsOpen(false);
                        setSelectedLoan(null);
                    }}
                    loan={selectedLoan || undefined}
                    payments={selectedLoanPayments}
                    onEdit={handleEditLoan}
                    onCancel={handleDeleteLoan}
                />
            </div>
        </AppLayout>
    );
}
