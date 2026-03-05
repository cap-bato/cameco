import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Play } from 'lucide-react';
import { useState } from 'react';
import { useDebouncedCallback } from 'use-debounce';
import { PayrollCalculationsPageProps, PayrollCalculation } from '@/types/payroll-pages';
import { CalculationsTable } from '@/components/payroll/calculations-table';
import { CalculationsFilter, type CalculationFilters } from '@/components/payroll/calculations-filter';
import { CalculationProgressModal } from '@/components/payroll/calculation-progress-modal';
import { 
    RecalculateConfirmationModal, 
    ApproveConfirmationModal, 
    CancelConfirmationModal 
} from '@/components/payroll/calculation-action-modals';
import type { BreadcrumbItem } from '@/types';

// ============================================================================
// Type Definitions
// ============================================================================

type CalculationIndexProps = PayrollCalculationsPageProps;

// ============================================================================
// Breadcrumbs
// ============================================================================

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payroll',
        href: '/payroll/dashboard',
    },
    {
        title: 'Calculations',
        href: '/payroll/calculations',
    },
];

// ============================================================================
// Component
// ============================================================================

export default function PayrollCalculations({ 
    calculations: initialCalculations, 
    available_periods,
    filters: initialFilters 
}: CalculationIndexProps) {
    const [filters, setFilters] = useState<CalculationFilters>(initialFilters as CalculationFilters);
    const [isProgressModalOpen, setIsProgressModalOpen] = useState(false);
    const [selectedCalculation, setSelectedCalculation] = useState<PayrollCalculation | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    
    // Confirmation modal states
    const [recalculateModal, setRecalculateModal] = useState<{ isOpen: boolean; calculation: PayrollCalculation | null }>({ 
        isOpen: false, 
        calculation: null 
    });
    const [approveModal, setApproveModal] = useState<{ isOpen: boolean; calculation: PayrollCalculation | null }>({ 
        isOpen: false, 
        calculation: null 
    });
    const [cancelModal, setCancelModal] = useState<{ isOpen: boolean; calculation: PayrollCalculation | null }>({ 
        isOpen: false, 
        calculation: null 
    });

    // Debounced filter change to avoid excessive requests
    const debouncedFilterChange = useDebouncedCallback(
        (newFilters: CalculationFilters) => {
            const queryParams = new URLSearchParams();
            
            if (newFilters.period_id) queryParams.append('period_id', newFilters.period_id.toString());
            if (newFilters.status) queryParams.append('status', newFilters.status);
            if (newFilters.calculation_type) queryParams.append('calculation_type', newFilters.calculation_type);

            router.get(`/payroll/calculations?${queryParams.toString()}`, {}, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        300
    );

    const handleFilterChange = (newFilters: CalculationFilters) => {
        setFilters(newFilters);
        debouncedFilterChange(newFilters);
    };

    const handleStartCalculation = () => {
        // Open modal to select period and start calculation
        setSelectedCalculation(null);
        setIsProgressModalOpen(true);
    };

    const handleViewDetails = (calculation: PayrollCalculation) => {
        if (calculation.status === 'completed') {
            router.visit(`/payroll/calculations/${calculation.id}`);
        } else {
            setSelectedCalculation(calculation);
            setIsProgressModalOpen(true);
        }
    };

    const handleRecalculate = (calculation: PayrollCalculation) => {
        setRecalculateModal({ isOpen: true, calculation });
    };

    const confirmRecalculate = () => {
        if (!recalculateModal.calculation) return;
        
        setIsLoading(true);
        router.post(`/payroll/calculations/${recalculateModal.calculation.id}/recalculate`, {}, {
            onSuccess: () => {
                router.get('/payroll/calculations', filters as unknown as Record<string, string | undefined>);
                setRecalculateModal({ isOpen: false, calculation: null });
            },
            onError: (errors) => {
                console.error('Recalculation failed:', errors);
                setIsLoading(false);
            },
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    const handleApprove = (calculation: PayrollCalculation) => {
        setApproveModal({ isOpen: true, calculation });
    };

    const confirmApprove = () => {
        if (!approveModal.calculation) return;
        
        setIsLoading(true);
        router.post(`/payroll/calculations/${approveModal.calculation.id}/approve`, {}, {
            onSuccess: () => {
                router.get('/payroll/calculations', filters as unknown as Record<string, string | undefined>);
                setApproveModal({ isOpen: false, calculation: null });
            },
            onError: (errors) => {
                console.error('Approval failed:', errors);
                setIsLoading(false);
            },
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    const handleCancel = (calculation: PayrollCalculation) => {
        setCancelModal({ isOpen: true, calculation });
    };

    const confirmCancel = () => {
        if (!cancelModal.calculation) return;
        
        setIsLoading(true);
        router.delete(`/payroll/calculations/${cancelModal.calculation.id}`, {
            onSuccess: () => {
                router.get('/payroll/calculations', filters as unknown as Record<string, string | undefined>);
                setCancelModal({ isOpen: false, calculation: null });
            },
            onError: (errors) => {
                console.error('Cancellation failed:', errors);
                setIsLoading(false);
            },
            onFinish: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Calculations" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Payroll Calculations</h1>
                        <p className="text-muted-foreground mt-1">
                            Run and manage payroll calculations for periods
                        </p>
                    </div>
                    <Button
                        onClick={handleStartCalculation}
                        disabled={isLoading}
                    >
                        <Play className="h-4 w-4 mr-2" />
                        Start Calculation
                    </Button>
                </div>

                {/* Filters */}
                <CalculationsFilter
                    onFilterChange={handleFilterChange}
                    initialFilters={filters}
                    availablePeriods={available_periods}
                    isLoading={isLoading}
                />

                {/* Calculations Table */}
                <Card>
                    <CardContent>
                        <CalculationsTable
                            calculations={initialCalculations}
                            onViewDetails={handleViewDetails}
                            onRecalculate={handleRecalculate}
                            onApprove={handleApprove}
                            onCancel={handleCancel}
                            isLoading={isLoading}
                        />

                        {/* Empty State Help */}
                        {initialCalculations.length === 0 && !filters.period_id && !filters.status && (
                            <div className="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    <strong>Getting Started:</strong> Start a payroll calculation by clicking the 
                                    "Start Calculation" button above. You'll select a payroll period and the system 
                                    will process all employees' salaries, deductions, and benefits.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Calculation Progress Modal */}
            <CalculationProgressModal
                isOpen={isProgressModalOpen}
                onClose={() => setIsProgressModalOpen(false)}
                calculation={selectedCalculation}
                availablePeriods={available_periods}
            />

            {/* Confirmation Modals */}
            {recalculateModal.calculation && (
                <RecalculateConfirmationModal
                    isOpen={recalculateModal.isOpen}
                    onClose={() => setRecalculateModal({ isOpen: false, calculation: null })}
                    onConfirm={confirmRecalculate}
                    calculation={recalculateModal.calculation}
                    isLoading={isLoading}
                />
            )}

            {approveModal.calculation && (
                <ApproveConfirmationModal
                    isOpen={approveModal.isOpen}
                    onClose={() => setApproveModal({ isOpen: false, calculation: null })}
                    onConfirm={confirmApprove}
                    calculation={approveModal.calculation}
                    isLoading={isLoading}
                />
            )}

            {cancelModal.calculation && (
                <CancelConfirmationModal
                    isOpen={cancelModal.isOpen}
                    onClose={() => setCancelModal({ isOpen: false, calculation: null })}
                    onConfirm={confirmCancel}
                    calculation={cancelModal.calculation}
                    isLoading={isLoading}
                />
            )}
        </AppLayout>
    );
}
