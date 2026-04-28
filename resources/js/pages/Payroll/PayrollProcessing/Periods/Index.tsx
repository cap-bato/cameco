import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Calendar, List } from 'lucide-react';
import { useState } from 'react';
import { useDebouncedCallback } from 'use-debounce';
import { PayrollPeriodsPageProps, PayrollPeriodFormData, PayrollPeriod } from '@/types/payroll-pages';
import { PeriodsTable } from '@/components/payroll/periods-table';
import { PeriodsFilter, type PeriodFilters } from '@/components/payroll/periods-filter';
import { PeriodFormModal } from '@/components/payroll/period-form-modal';
import { PeriodsCalendar } from '@/components/payroll/periods-calendar';
import { PeriodDetailsModal } from '@/components/payroll/period-details-modal';
import type { BreadcrumbItem } from '@/types';

// ============================================================================
// Type Definitions
// ============================================================================

type PeriodIndexProps = PayrollPeriodsPageProps;

type ConfirmDialogState = {
    open: boolean;
    title: string;
    description: string;
    actionLabel: string;
    actionVariant?: 'default' | 'destructive';
    onConfirm: () => void;
};

const DEFAULT_CONFIRM_STATE: ConfirmDialogState = {
    open: false,
    title: '',
    description: '',
    actionLabel: 'Confirm',
    actionVariant: 'default',
    onConfirm: () => {},
};

// ============================================================================
// Breadcrumbs
// ============================================================================

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll',
        href: '/payroll/dashboard',
    },
    {
        title: 'Periods',
        href: '/payroll/periods',
    },
];

// ============================================================================
// Component
// ============================================================================

export default function PayrollPeriods({ 
    periods: initialPeriods, 
    filters: initialFilters 
}: PeriodIndexProps) {
    const [activeTab, setActiveTab] = useState<'list' | 'calendar'>('list');
    const [filters, setFilters] = useState<PeriodFilters>(initialFilters);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedPeriod, setSelectedPeriod] = useState<PayrollPeriod | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [isLoading, setIsLoading] = useState(false);
    const [isDetailsModalOpen, setIsDetailsModalOpen] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState<ConfirmDialogState>(DEFAULT_CONFIRM_STATE);

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    const openConfirm = (options: Omit<ConfirmDialogState, 'open'>) => {
        setConfirmDialog({ ...options, open: true });
    };

    const closeConfirm = () => {
        setConfirmDialog((prev) => ({ ...prev, open: false }));
    };

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    const debouncedFilterChange = useDebouncedCallback(
        (newFilters: PeriodFilters) => {
            const queryParams = new URLSearchParams();
            
            if (newFilters.search) queryParams.append('search', newFilters.search);
            if (newFilters.status) queryParams.append('status', newFilters.status);
            if (newFilters.period_type) queryParams.append('period_type', newFilters.period_type);

            router.get(`/payroll/periods?${queryParams.toString()}`, {}, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        300
    );

    const handleFilterChange = (newFilters: PeriodFilters) => {
        setFilters(newFilters);
        debouncedFilterChange(newFilters);
    };

    // -------------------------------------------------------------------------
    // CRUD actions
    // -------------------------------------------------------------------------

    const handleCreateClick = () => {
        setSelectedPeriod(null);
        setModalMode('create');
        setIsModalOpen(true);
    };

    const handleEditClick = (period: PayrollPeriod) => {
        setSelectedPeriod(period);
        setModalMode('edit');
        setIsModalOpen(true);
    };

    const handleDetailsModalEditClick = (period: PayrollPeriod) => {
        setIsDetailsModalOpen(false);
        setSelectedPeriod(period);
        setModalMode('edit');
        setIsModalOpen(true);
    };

    const handleViewClick = (period: PayrollPeriod) => {
        setSelectedPeriod(period);
        setIsDetailsModalOpen(true);
    };

    const handleModalSubmit = async (data: PayrollPeriodFormData) => {
        setIsLoading(true);

        try {
            if (modalMode === 'create') {
                router.post('/payroll/periods', {
                    name: data.name,
                    period_type: data.period_type,
                    start_date: data.start_date,
                    end_date: data.end_date,
                    cutoff_date: data.cutoff_date,
                    pay_date: data.pay_date,
                    deduction_timing: data.deduction_timing,
                }, {
                    onSuccess: () => { setIsModalOpen(false); },
                    onError: (errors) => { console.error('Creation failed:', errors); },
                    onFinish: () => { setIsLoading(false); },
                });
            } else if (selectedPeriod) {
                router.put(`/payroll/periods/${selectedPeriod.id}`, {
                    name: data.name,
                    period_type: data.period_type,
                    start_date: data.start_date,
                    end_date: data.end_date,
                    cutoff_date: data.cutoff_date,
                    pay_date: data.pay_date,
                    deduction_timing: data.deduction_timing,
                }, {
                    onSuccess: () => { setIsModalOpen(false); },
                    onError: (errors) => { console.error('Update failed:', errors); },
                    onFinish: () => { setIsLoading(false); },
                });
            }
        } catch (error) {
            console.error('Error:', error);
            setIsLoading(false);
        }
    };

    const handleDeleteClick = (period: PayrollPeriod) => {
        openConfirm({
            title: 'Delete Payroll Period',
            description: `Are you sure you want to delete "${period.name}"? This action cannot be undone.`,
            actionLabel: 'Delete',
            actionVariant: 'destructive',
            onConfirm: () => {
                closeConfirm();
                setIsLoading(true);
                router.delete(`/payroll/periods/${period.id}`, {
                    onSuccess: () => {
                        router.get('/payroll/periods', filters as unknown as Record<string, string | undefined>);
                    },
                    onError: (errors) => {
                        console.error('Deletion failed:', errors);
                        setIsLoading(false);
                    },
                    onFinish: () => { setIsLoading(false); },
                });
            },
        });
    };

    const handleCalculateClick = (period: PayrollPeriod) => {
        openConfirm({
            title: 'Calculate Payroll',
            description: `Calculate payroll for "${period.name}"? This will process all employees.`,
            actionLabel: 'Calculate',
            actionVariant: 'default',
            onConfirm: () => {
                closeConfirm();
                setIsLoading(true);
                router.post(`/payroll/periods/${period.id}/calculate`, {}, {
                    onSuccess: () => {
                        router.get('/payroll/periods', filters as unknown as Record<string, string | undefined>);
                    },
                    onError: (errors) => {
                        console.error('Calculation failed:', errors);
                        setIsLoading(false);
                    },
                    onFinish: () => { setIsLoading(false); },
                });
            },
        });
    };

    const handleApproveClick = (period: PayrollPeriod) => {
        openConfirm({
            title: 'Approve Payroll',
            description: `Approve payroll for "${period.name}"? Approved payroll cannot be modified.`,
            actionLabel: 'Approve',
            actionVariant: 'default',
            onConfirm: () => {
                closeConfirm();
                setIsLoading(true);
                router.post(`/payroll/periods/${period.id}/approve`, {}, {
                    onSuccess: () => {
                        router.get('/payroll/periods', filters as unknown as Record<string, string | undefined>);
                    },
                    onError: (errors) => {
                        console.error('Approval failed:', errors);
                        setIsLoading(false);
                    },
                    onFinish: () => { setIsLoading(false); },
                });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Periods" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Payroll Periods</h1>
                        <p className="text-muted-foreground mt-1">
                            Create and manage payroll periods for salary calculations
                        </p>
                    </div>
                    <Button onClick={handleCreateClick} disabled={isLoading}>
                        <Plus className="h-4 w-4 mr-2" />
                        Create Period
                    </Button>
                </div>

                {/* Filters */}
                <PeriodsFilter
                    onFilterChange={handleFilterChange}
                    initialFilters={filters}
                    isLoading={isLoading}
                />

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as 'list' | 'calendar')}>
                    <TabsList>
                        <TabsTrigger value="list" className="flex gap-2">
                            <List className="h-4 w-4" />
                            List View
                        </TabsTrigger>
                        <TabsTrigger value="calendar" className="flex gap-2">
                            <Calendar className="h-4 w-4" />
                            Calendar View
                        </TabsTrigger>
                    </TabsList>

                    {/* List View Tab */}
                    <TabsContent value="list" className="space-y-4">
                        <Card>
                            <CardContent>
                                <PeriodsTable
                                    periods={initialPeriods}
                                    onView={handleViewClick}
                                    onEdit={handleEditClick}
                                    onDelete={handleDeleteClick}
                                    onCalculate={handleCalculateClick}
                                    onApprove={handleApproveClick}
                                    isLoading={isLoading}
                                />

                                {initialPeriods.length === 0 && !filters.search && !filters.status && !filters.period_type && (
                                    <div className="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                                        <p className="text-sm text-blue-900 dark:text-blue-100">
                                            <strong>Getting Started:</strong> Create your first payroll period by clicking the 
                                            "Create Period" button above. You'll need to specify the payroll dates, cutoff date, 
                                            and pay date for your organization's payroll cycle.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Calendar View Tab */}
                    <TabsContent value="calendar">
                        <PeriodsCalendar periods={initialPeriods} />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Period Form Modal */}
            <PeriodFormModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSubmit={handleModalSubmit}
                period={selectedPeriod}
                mode={modalMode}
            />

            {/* Period Details Modal */}
            <PeriodDetailsModal
                period={isDetailsModalOpen ? selectedPeriod : null}
                onClose={() => setIsDetailsModalOpen(false)}
                onEdit={handleDetailsModalEditClick}
            />

            {/* Confirmation Dialog */}
            <AlertDialog open={confirmDialog.open} onOpenChange={(open) => !open && closeConfirm()}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{confirmDialog.title}</AlertDialogTitle>
                        <AlertDialogDescription>{confirmDialog.description}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDialog.onConfirm}
                            className={
                                confirmDialog.actionVariant === 'destructive'
                                    ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90'
                                    : ''
                            }
                        >
                            {confirmDialog.actionLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}