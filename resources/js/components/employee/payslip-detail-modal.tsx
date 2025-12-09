import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Download,
    Printer,
    X,
    DollarSign,
    TrendingUp,
    TrendingDown,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface SalaryComponent {
    name: string;
    amount: number;
}

interface Payslip {
    id: number;
    pay_period_start: string;
    pay_period_end: string;
    pay_date: string;
    status: 'released' | 'pending' | 'processing' | 'failed';
    basic_salary: number;
    allowances: SalaryComponent[];
    gross_pay: number;
    deductions: SalaryComponent[];
    net_pay: number;
    year_to_date_gross: number;
    year_to_date_net: number;
    pdf_url?: string;
}

interface PayslipDetailModalProps {
    isOpen: boolean;
    onClose: () => void;
    payslip: Payslip;
}

// ============================================================================
// Helper Components
// ============================================================================

interface ComponentRowProps {
    label: string;
    amount: number;
    isSubtotal?: boolean;
    isBold?: boolean;
}

function ComponentRow({ label, amount, isSubtotal = false, isBold = false }: ComponentRowProps) {
    return (
        <div className={`flex items-center justify-between ${isSubtotal ? 'py-2' : 'py-1.5'}`}>
            <span className={`text-sm ${isBold ? 'font-semibold' : 'text-gray-600 dark:text-gray-400'}`}>
                {label}
            </span>
            <span className={`text-sm font-medium text-gray-900 dark:text-white ${isBold ? 'text-base font-bold' : ''}`}>
                ₱{amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
        </div>
    );
}

// ============================================================================
// Main Component
// ============================================================================

export function PayslipDetailModal({
    isOpen,
    onClose,
    payslip,
}: PayslipDetailModalProps) {
    const deductionsTotal = payslip.deductions.reduce((sum, d) => sum + d.amount, 0);
    const allowancesTotal = payslip.allowances.reduce((sum, a) => sum + a.amount, 0);

    const handlePrint = () => {
        window.print();
    };

    const handleDownload = () => {
        // TODO: Implement PDF download
        alert('PDF download will be available soon');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                {/* Dialog Header */}
                <DialogHeader className="flex flex-row items-center justify-between space-y-0">
                    <DialogTitle className="flex items-center gap-2">
                        <DollarSign className="h-5 w-5 text-green-600 dark:text-green-400" />
                        Payslip Details
                    </DialogTitle>
                    <DialogClose asChild>
                        <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                            <X className="h-4 w-4" />
                        </Button>
                    </DialogClose>
                </DialogHeader>

                {/* Modal Content */}
                <div className="space-y-4">
                    {/* Pay Period Information */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                        Pay Period
                                    </p>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        {format(parseISO(payslip.pay_period_start), 'MMMM dd')} -{' '}
                                        {format(parseISO(payslip.pay_period_end), 'MMMM dd, yyyy')}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                        Pay Date
                                    </p>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        {format(parseISO(payslip.pay_date), 'MMMM dd, yyyy')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Earnings Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingUp className="h-4 w-4 text-green-600 dark:text-green-400" />
                                Earnings
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <ComponentRow
                                label="Basic Salary"
                                amount={payslip.basic_salary}
                                isBold
                            />

                            {payslip.allowances.length > 0 && (
                                <>
                                    <Separator className="my-2" />
                                    <div className="space-y-0">
                                        <p className="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                            Allowances
                                        </p>
                                        {payslip.allowances.map((allowance, idx) => (
                                            <ComponentRow
                                                key={idx}
                                                label={allowance.name}
                                                amount={allowance.amount}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}

                            <Separator className="my-2" />
                            <ComponentRow
                                label="Allowances Total"
                                amount={allowancesTotal}
                                isSubtotal
                            />

                            <Separator className="my-3 border-gray-300 dark:border-gray-600" />
                            <ComponentRow
                                label="Gross Pay"
                                amount={payslip.gross_pay}
                                isBold
                            />
                        </CardContent>
                    </Card>

                    {/* Deductions Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingDown className="h-4 w-4 text-red-600 dark:text-red-400" />
                                Deductions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 border-t border-gray-200 pt-4 dark:border-gray-700">
                            {payslip.deductions.length > 0 ? (
                                <>
                                    <div className="space-y-0">
                                        {payslip.deductions.map((deduction, idx) => (
                                            <ComponentRow
                                                key={idx}
                                                label={deduction.name}
                                                amount={deduction.amount}
                                            />
                                        ))}
                                    </div>
                                    <Separator className="my-3 border-gray-300 dark:border-gray-600" />
                                    <ComponentRow
                                        label="Total Deductions"
                                        amount={deductionsTotal}
                                        isBold
                                    />
                                </>
                            ) : (
                                <p className="py-4 text-center text-sm text-gray-600 dark:text-gray-400">
                                    No deductions for this pay period
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Net Pay Section */}
                    <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/10">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold text-green-900 dark:text-green-100">
                                    Net Pay (Take Home)
                                </p>
                                <p className="text-3xl font-bold text-green-700 dark:text-green-400">
                                    ₱{payslip.net_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Year-to-Date Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Year-to-Date Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <ComponentRow
                                label="YTD Gross"
                                amount={payslip.year_to_date_gross}
                                isBold
                            />
                            <Separator className="my-2" />
                            <ComponentRow
                                label="YTD Deductions"
                                amount={deductionsTotal}
                            />
                            <Separator className="my-3 border-gray-300 dark:border-gray-600" />
                            <ComponentRow
                                label="YTD Net"
                                amount={payslip.year_to_date_net}
                                isBold
                            />
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    <div className="flex gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <Button
                            variant="outline"
                            onClick={handlePrint}
                            className="flex-1"
                        >
                            <Printer className="mr-2 h-4 w-4" />
                            Print
                        </Button>
                        <Button
                            onClick={handleDownload}
                            className="flex-1 bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700"
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Download PDF
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
