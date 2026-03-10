import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { 
    Calculator, 
    Users, 
    DollarSign, 
    MoreHorizontal, 
    Eye, 
    RefreshCw,
    CheckCircle,
    AlertCircle,
    XCircle,
    Loader2
} from 'lucide-react';
import { PayrollCalculation } from '@/types/payroll-pages';
import { router } from '@inertiajs/react';
import { usePayrollProgress } from '@/hooks/use-payroll-progress';
import { toast } from '@/hooks/use-toast';

// ============================================================================
// Type Definitions
// ============================================================================

interface CalculationsTableProps {
    calculations: PayrollCalculation[];
    onViewDetails?: (calculation: PayrollCalculation) => void;
    onRecalculate?: (calculation: PayrollCalculation) => void;
    onApprove?: (calculation: PayrollCalculation) => void;
    onCancel?: (calculation: PayrollCalculation) => void;
    isLoading?: boolean;
}

// ============================================================================
// Status Badge Configuration
// ============================================================================

interface StatusConfig {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    icon?: React.ReactNode;
}

const statusConfigMap: Record<PayrollCalculation['status'], StatusConfig> = {
    pending: {
        label: 'Pending',
        variant: 'secondary',
        icon: <AlertCircle className="h-3 w-3" />,
    },
    processing: {
        label: 'Processing',
        variant: 'outline',
        icon: <Loader2 className="h-3 w-3 animate-spin" />,
    },
    completed: {
        label: 'Completed',
        variant: 'default',
        icon: <CheckCircle className="h-3 w-3" />,
    },
    failed: {
        label: 'Failed',
        variant: 'destructive',
        icon: <XCircle className="h-3 w-3" />,
    },
    cancelled: {
        label: 'Cancelled',
        variant: 'secondary',
        icon: <XCircle className="h-3 w-3" />,
    },
};

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format currency for display
 */
function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(amount);
}

/**
 * Format date for display
 */
function formatDate(dateString: string): string {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    } catch {
        return dateString;
    }
}

/**
 * Get calculation type label
 */
function getCalculationTypeLabel(type: PayrollCalculation['calculation_type']): string {
    const labels: Record<PayrollCalculation['calculation_type'], string> = {
        regular: 'Regular',
        adjustment: 'Adjustment',
        final: 'Final',
        're-calculation': 'Re-calculation',
    };
    return labels[type] || type;
}

/**
 * Determine which actions should be available based on status
 */
function getAvailableActions(status: PayrollCalculation['status']): string[] {
    const actions: string[] = ['view'];

    // Can recalculate failed or completed calculations
    if (status === 'failed' || status === 'completed') {
        actions.push('recalculate');
    }

    // Can approve completed calculations
    if (status === 'completed') {
        actions.push('approve');
    }

    // Can cancel pending or processing calculations
    if (status === 'pending' || status === 'processing') {
        actions.push('cancel');
    }

    return actions;
}

// ============================================================================
// CalculationRow Component (with real-time polling)
// ============================================================================

interface CalculationRowProps {
    calculation: PayrollCalculation;
    onViewDetails?: (calculation: PayrollCalculation) => void;
    onRecalculate?: (calculation: PayrollCalculation) => void;
    onApprove?: (calculation: PayrollCalculation) => void;
    onCancel?: (calculation: PayrollCalculation) => void;
    isLoading?: boolean;
}

function CalculationRow({
    calculation,
    onViewDetails,
    onRecalculate,
    onApprove,
    onCancel,
    isLoading = false,
}: CalculationRowProps) {
    // Real-time progress polling for processing calculations
    const progressState = usePayrollProgress({
        calculationId: calculation.id,
        initialStatus: calculation.status,
        enabled: calculation.status === 'processing',
        pollingInterval: 2000,
        onComplete: () => {
            router.reload({ only: ['calculations'] });
            toast({
                title: 'Calculation Complete',
                description: `Payroll calculation completed for ${calculation.payroll_period.name}`,
                variant: 'default',
            });
        },
    });

    // Use live polling state when available, otherwise fall back to calculation props
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

    const statusConfig = statusConfigMap[calculation.status];
    const availableActions = getAvailableActions(calculation.status);
    const calculationDate = formatDate(calculation.calculation_date);

    return (
        <TableRow key={calculation.id} className="hover:bg-gray-50 dark:hover:bg-gray-900">
            <TableCell className="font-medium">
                {calculation.payroll_period.name}
            </TableCell>
            <TableCell>
                <span className="text-sm text-gray-600 dark:text-gray-400">
                    {getCalculationTypeLabel(calculation.calculation_type)}
                </span>
            </TableCell>
            <TableCell>
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <Progress value={progressPercentage} className="w-24" />
                        <span className="text-xs text-gray-500">
                            {progressPercentage}%
                        </span>
                    </div>
                    <span className="text-xs text-gray-500">
                        {processedEmployees}/{totalEmployees} processed
                    </span>
                </div>
            </TableCell>
            <TableCell>
                <div className="flex items-center gap-1">
                    <Users className="h-4 w-4 text-gray-400" />
                    <span className="text-sm">{totalEmployees}</span>
                    {failedEmployees > 0 && (
                        <span className="text-xs text-red-600">
                            ({failedEmployees} failed)
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell>
                <div className="flex items-center gap-1">
                    <DollarSign className="h-4 w-4 text-gray-400" />
                    <span className="text-sm font-medium">
                        {formatCurrency(calculation.total_net_pay)}
                    </span>
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={statusConfig.variant} className="flex items-center gap-1 w-fit">
                    {statusConfig.icon}
                    {statusConfig.label}
                </Badge>
            </TableCell>
            <TableCell>
                <span className="text-sm text-gray-600 dark:text-gray-400">
                    {calculationDate}
                </span>
            </TableCell>
            <TableCell className="text-right">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={isLoading}
                        >
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        {availableActions.includes('view') && (
                            <DropdownMenuItem
                                onClick={() => onViewDetails?.(calculation)}
                                disabled={isLoading}
                            >
                                <Eye className="h-4 w-4 mr-2" />
                                View Details
                            </DropdownMenuItem>
                        )}

                        {availableActions.includes('recalculate') && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => onRecalculate?.(calculation)}
                                    disabled={isLoading}
                                    className="text-blue-600 dark:text-blue-400"
                                >
                                    <RefreshCw className="h-4 w-4 mr-2" />
                                    Recalculate
                                </DropdownMenuItem>
                            </>
                        )}

                        {availableActions.includes('approve') && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => onApprove?.(calculation)}
                                    disabled={isLoading}
                                    className="text-green-600 dark:text-green-400"
                                >
                                    <CheckCircle className="h-4 w-4 mr-2" />
                                    Approve
                                </DropdownMenuItem>
                            </>
                        )}

                        {availableActions.includes('cancel') && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => onCancel?.(calculation)}
                                    disabled={isLoading}
                                    className="text-red-600 dark:text-red-400"
                                >
                                    <XCircle className="h-4 w-4 mr-2" />
                                    Cancel
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </TableCell>
        </TableRow>
    );
}

// ============================================================================
// Component
// ============================================================================

export function CalculationsTable({
    calculations,
    onViewDetails,
    onRecalculate,
    onApprove,
    onCancel,
    isLoading = false,
}: CalculationsTableProps) {
    if (calculations.length === 0) {
        return (
            <Card>
                <CardContent className="pt-6">
                    <div className="text-center py-8">
                        <Calculator className="h-12 w-12 text-muted-foreground mx-auto mb-3 opacity-50" />
                        <p className="text-muted-foreground mb-2">No calculations found</p>
                        <p className="text-sm text-gray-500">
                            Start a new payroll calculation to get started
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Calculator className="h-5 w-5" />
                    Payroll Calculations
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Progress</TableHead>
                                <TableHead>Employees</TableHead>
                                <TableHead>Total Net Pay</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {calculations.map((calculation) => (
                                <CalculationRow
                                    key={calculation.id}
                                    calculation={calculation}
                                    onViewDetails={onViewDetails}
                                    onRecalculate={onRecalculate}
                                    onApprove={onApprove}
                                    onCancel={onCancel}
                                    isLoading={isLoading}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}
