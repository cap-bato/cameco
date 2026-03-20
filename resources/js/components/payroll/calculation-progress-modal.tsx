import { useState } from 'react';
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
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    CheckCircle,
    XCircle,
    AlertCircle,
    Loader2,
    Users,
    DollarSign,
    Calculator,
    RefreshCw,
    Play
} from 'lucide-react';
import { PayrollCalculation, PayrollPeriod } from '@/types/payroll-pages';
import { usePayrollProgress } from '@/hooks/use-payroll-progress';
import { toast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface CalculationProgressModalProps {
    isOpen: boolean;
    onClose: () => void;
    calculation?: PayrollCalculation | null;
    availablePeriods: PayrollPeriod[];
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount);
}

// ============================================================================
// Component
// ============================================================================

export function CalculationProgressModal({
    isOpen,
    onClose,
    calculation,
    availablePeriods,
}: CalculationProgressModalProps) {
    const [selectedPeriodId, setSelectedPeriodId] = useState<number | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [calculationType, setCalculationType] = useState<'regular' | 'adjustment' | 'final' | 're-calculation'>('regular');

    // Real-time progress polling
    const progressState = usePayrollProgress({
    calculationId: calculation?.id || 0,
    initialStatus: calculation?.status || 'pending',
    enabled: isOpen && !!calculation && calculation.status === 'processing',
    pollingInterval: 2000,
    onComplete: () => {
        // ✅ Reload FIRST, then optionally close
        router.reload({ only: ['calculations'] });
        toast({
        title: 'Calculation Complete',
        description: `Payroll for ${calculation?.payroll_period?.name} is done.`,
        });
        // Don't call onClose() here — let the user see the completed state
    },
    onFailed: (error) => {
        toast({
        title: 'Calculation Failed',
        description: error ?? 'An error occurred during payroll calculation.',
        variant: 'destructive',
        });
        router.reload({ only: ['calculations'] });
    },
    });

    const handleStartCalculation = () => {
        if (!selectedPeriodId) {
            alert('Please select a payroll period');
            return;
        }

        setIsSubmitting(true);
        router.post('/payroll/calculations', {
            payroll_period_id: selectedPeriodId,
            calculation_type: calculationType,
        }, {
            onSuccess: () => {
                onClose();
                setIsSubmitting(false);
            },
            onError: (errors) => {
                console.error('Calculation failed to start:', errors);
                setIsSubmitting(false);
                alert('Failed to start calculation. Please try again.');
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleRetry = () => {
        if (!calculation) return;

        setIsSubmitting(true);
        router.post(`/payroll/calculations/${calculation.id}/recalculate`, {}, {
            onSuccess: () => {
                onClose();
                setIsSubmitting(false);
            },
            onError: (errors) => {
                console.error('Retry failed:', errors);
                setIsSubmitting(false);
                alert('Failed to retry calculation. Please try again.');
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    // Render: Start New Calculation
    if (!calculation) {
        return (
            <Dialog open={isOpen} onOpenChange={onClose} key={isOpen ? 'open' : 'closed'}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Calculator className="h-5 w-5" />
                            Start Payroll Calculation
                        </DialogTitle>
                        <DialogDescription>
                            Select a payroll period to calculate employee salaries, deductions, and benefits.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {/* Period Selection */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Payroll Period <span className="text-red-500">*</span>
                            </label>
                            <Select
                                value={selectedPeriodId?.toString() || ''}
                                onValueChange={(value) => setSelectedPeriodId(parseInt(value))}
                                disabled={isSubmitting}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a period" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availablePeriods.map((period) => (
                                        <SelectItem key={period.id} value={period.id.toString()}>
                                            {period.name} ({period.start_date} - {period.end_date})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Calculation Type */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Calculation Type
                            </label>
                            <Select
                                value={calculationType}
                                onValueChange={(value) => setCalculationType(value as typeof calculationType)}
                                disabled={isSubmitting}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="regular">Regular</SelectItem>
                                    <SelectItem value="adjustment">Adjustment</SelectItem>
                                    <SelectItem value="final">Final</SelectItem>
                                    <SelectItem value="re-calculation">Re-calculation</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Info Alert */}
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>What happens next?</AlertTitle>
                            <AlertDescription className="text-sm">
                                The system will process each employee's payroll, calculating gross pay, 
                                deductions (SSS, PhilHealth, Pag-IBIG, tax), and net pay. This may take 
                                a few minutes depending on employee count.
                            </AlertDescription>
                        </Alert>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={onClose} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleStartCalculation}
                            disabled={!selectedPeriodId || isSubmitting}
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Starting...
                                </>
                            ) : (
                                <>
                                    <Play className="h-4 w-4 mr-2" />
                                    Start Calculation
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    // Render: View Calculation Progress/Details
    // Use live polling state when available, otherwise fall back to calculation props
    const isProcessing = progressState.isPolling ? progressState.status === 'processing' : calculation.status === 'processing';
    const isCompleted = progressState.isPolling ? progressState.status === 'completed' : calculation.status === 'completed';
    const isFailed = progressState.isPolling ? progressState.status === 'failed' : calculation.status === 'failed';
    const progressPercentage = progressState.isPolling ? progressState.progress : calculation.progress_percentage;
    const processedEmployees = progressState.isPolling && progressState.totalJobs !== null && progressState.pendingJobs !== null
        ? progressState.totalJobs - progressState.pendingJobs
        : calculation.processed_employees;
    const totalEmployees = progressState.isPolling && progressState.totalJobs !== null
        ? progressState.totalJobs
        : calculation.total_employees;
    const failedEmployees = progressState.isPolling && progressState.failedJobs !== null
        ? progressState.failedJobs
        : calculation.failed_employees;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Calculator className="h-5 w-5" />
                        Calculation Progress: {calculation.payroll_period.name}
                    </DialogTitle>
                    <DialogDescription>
                        {calculation.calculation_type.charAt(0).toUpperCase() + calculation.calculation_type.slice(1)} calculation
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    {/* Progress Bar - Compact */}
                    <div className="rounded-lg border bg-card p-3">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium">Progress</span>
                                <div className="flex items-center gap-2">
                                    <span className="text-lg font-bold">{progressPercentage}%</span>
                                    {isProcessing && <Loader2 className="h-3 w-3 animate-spin" />}
                                    {isCompleted && <CheckCircle className="h-3 w-3 text-green-600" />}
                                    {isFailed && <XCircle className="h-3 w-3 text-red-600" />}
                                </div>
                            </div>
                            <Progress value={progressPercentage} className="h-2" />
                            <div className="text-xs text-muted-foreground">
                                {processedEmployees ?? 0} of {totalEmployees ?? 0} employees processed
                            </div>
                        </div>
                    </div>

                    {/* Summary - Compact Grid */}
                    <div className="grid grid-cols-3 gap-2 text-sm">
                        <div className="rounded-lg border bg-card p-2">
                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Users className="h-3 w-3" />
                                Employees
                            </div>
                            <div className="font-bold">{totalEmployees}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-2">
                            <div className="text-xs text-muted-foreground">Gross Pay</div>
                            <div className="font-bold text-sm">{formatCurrency(calculation.total_gross_pay)}</div>
                        </div>
                        <div className="rounded-lg border bg-card p-2">
                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                <DollarSign className="h-3 w-3" />
                                Net Pay
                            </div>
                            <div className="font-bold text-sm">{formatCurrency(calculation.total_net_pay)}</div>
                        </div>
                    </div>

                    {/* Status Alerts - Compact */}
                    {failedEmployees > 0 && (
                        <Alert variant="destructive" className="py-2">
                            <AlertCircle className="h-3 w-3" />
                            <AlertTitle className="text-sm">Processing Errors</AlertTitle>
                            <AlertDescription className="text-xs">
                                {failedEmployees} employee(s) failed.
                                {calculation.error_message && (
                                    <span className="block mt-1">
                                        <strong>Error:</strong> {calculation.error_message}
                                    </span>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    {isCompleted && failedEmployees === 0 && (
                        <Alert className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950 py-2">
                            <CheckCircle className="h-3 w-3 text-green-600" />
                            <AlertTitle className="text-sm text-green-900 dark:text-green-100">
                                Calculation Complete
                            </AlertTitle>
                            <AlertDescription className="text-xs text-green-800 dark:text-green-200">
                                All employees processed successfully. Ready to approve.
                            </AlertDescription>
                        </Alert>
                    )}

                    {isFailed && (
                        <Alert variant="destructive" className="py-2">
                            <XCircle className="h-3 w-3" />
                            <AlertTitle className="text-sm">Calculation Failed</AlertTitle>
                            <AlertDescription className="text-xs">
                                {calculation.error_message || 'The payroll calculation encountered an error.'}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Additional Details - Compact */}
                    <div className="rounded-lg bg-muted/50 p-2">
                        <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                            <div>
                                <span className="text-muted-foreground">Deductions:</span>{' '}
                                <span className="font-medium">{formatCurrency(calculation.total_deductions)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Status:</span>{' '}
                                <span className="font-medium capitalize">{calculation.status}</span>
                            </div>
                            <div className="col-span-2">
                                <span className="text-muted-foreground">Started:</span>{' '}
                                <span className="font-medium">{new Date(calculation.calculation_date).toLocaleString()}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                    {isFailed && (
                        <Button
                            onClick={handleRetry}
                            disabled={isSubmitting}
                            variant="default"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Retrying...
                                </>
                            ) : (
                                <>
                                    <RefreshCw className="h-4 w-4 mr-2" />
                                    Retry Calculation
                                </>
                            )}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
