import { Send, Globe, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { PayslipDistributionRequest } from '@/types/payroll-pages';

interface PayslipDistributionProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDistribute: (data: PayslipDistributionRequest) => void;
    selectedPayslipIds: number[];
    selectedCount: number;
    isLoading?: boolean;
}

export function PayslipDistribution({
    open,
    onOpenChange,
    onDistribute,
    selectedPayslipIds,
    selectedCount,
    isLoading = false,
}: PayslipDistributionProps) {
    const handleDistribute = () => {
        onDistribute({
            payslip_ids: selectedPayslipIds,
            distribution_method: 'portal',
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Send className="h-5 w-5" />
                        Distribute Payslips
                    </DialogTitle>
                    <DialogDescription>
                        {selectedCount} payslip{selectedCount > 1 ? 's' : ''} selected for distribution
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div className="flex items-center gap-3 rounded-lg border bg-green-50 p-4 dark:bg-green-900/10">
                        <Globe className="h-6 w-6 text-green-600 dark:text-green-400 shrink-0" />
                        <div>
                            <p className="font-medium text-green-900 dark:text-green-100">Employee Portal</p>
                            <p className="text-sm text-green-700 dark:text-green-300">
                                Payslips will be made available for employees to view and download in their portal.
                            </p>
                        </div>
                    </div>

                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>{selectedCount} payslip{selectedCount > 1 ? 's' : ''}</strong> will be released to the employee portal. Employees will be able to view and download them immediately.
                        </AlertDescription>
                    </Alert>
                </div>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isLoading}>
                        Cancel
                    </Button>
                    <Button onClick={handleDistribute} disabled={selectedPayslipIds.length === 0 || isLoading}>
                        {isLoading ? (
                            <>Distributing...</>
                        ) : (
                            <>
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Release {selectedCount} Payslip{selectedCount > 1 ? 's' : ''} to Portal
                            </>
                        )}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}