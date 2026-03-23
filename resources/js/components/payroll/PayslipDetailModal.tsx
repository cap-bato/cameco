import React from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Download, Info } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface SalaryComponent {
    name: string;
    description?: string;
    category?: string;
    amount: number;
}

interface PayslipRecord {
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
    year_to_date_deductions: number;
    year_to_date_net: number;
    pdf_url?: string;
}

interface PayslipDetailModalProps {
    isOpen: boolean;
    onClose: () => void;
    payslip: PayslipRecord;
    onDownload?: () => void;
}

const SalaryComponentRow: React.FC<{ component: SalaryComponent; variant?: 'normal' | 'total' }> = (
    { component, variant = 'normal' }
) => {
    const isTotal = variant === 'total';

    return (
        <div
            className={`flex items-center justify-between px-4 py-3 ${
                isTotal
                    ? 'border-t-2 border-blue-600 bg-gradient-to-r from-blue-50 to-indigo-50 font-semibold'
                    : 'border-b border-gray-200 hover:bg-gray-50'
            }`}
        >
            <div className="flex items-center gap-2">
                <span className={isTotal ? 'text-blue-900' : 'text-gray-700'}>
                    {component.name}
                </span>
                {component.description && !isTotal && (
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Info className="h-4 w-4 text-gray-400 hover:text-gray-600" />
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs">
                                <p className="text-sm">{component.description}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                )}
            </div>
            <span className={isTotal ? 'text-blue-900' : 'text-gray-900'}>
                ₱ {component.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </span>
        </div>
    );
};

export default function PayslipDetailModal({
    isOpen,
    onClose,
    payslip,
    onDownload,
}: PayslipDetailModalProps) {
    const totalDeductions = payslip.deductions.reduce(
        (sum, d) => sum + d.amount,
        0
    );

    const categoryGroups = {
        government: payslip.deductions.filter((d) => d.category === 'government'),
        tax: payslip.deductions.filter((d) => d.category === 'tax'),
        loan: payslip.deductions.filter((d) => d.category === 'loan'),
        advance: payslip.deductions.filter((d) => d.category === 'advance'),
        leave: payslip.deductions.filter((d) => d.category === 'leave'),
        attendance: payslip.deductions.filter((d) => d.category === 'attendance'),
        other: payslip.deductions.filter((d) => !d.category || d.category === 'other'),
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Payslip Details</DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Pay Period Information */}
                    <Card className="border-blue-200 bg-blue-50">
                        <CardContent className="pt-6">
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <p className="text-sm text-gray-600">Pay Period</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {payslip.pay_period_start} to {payslip.pay_period_end}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600">Pay Date</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {payslip.pay_date}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600">Status</p>
                                    <p className={`text-lg font-semibold ${
                                        payslip.status === 'released'
                                            ? 'text-green-600'
                                            : 'text-yellow-600'
                                    }`}>
                                        {payslip.status === 'released' ? 'Released' : 'Pending'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Earnings Section */}
                    <Card>
                        <CardHeader className="bg-gradient-to-r from-green-600 to-emerald-600">
                            <CardTitle className="text-white">Earnings</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="divide-y">
                                <div className="flex items-center justify-between px-4 py-3 bg-gray-50">
                                    <span className="font-semibold text-gray-700">Basic Salary</span>
                                    <span className="font-semibold text-gray-900">
                                        ₱ {payslip.basic_salary.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </span>
                                </div>

                                {payslip.allowances.length > 0 && (
                                    <>
                                        {payslip.allowances.map((allowance, idx) => (
                                            <SalaryComponentRow
                                                key={idx}
                                                component={allowance}
                                            />
                                        ))}
                                    </>
                                )}

                                <SalaryComponentRow
                                    component={{
                                        name: 'GROSS PAY',
                                        amount: payslip.gross_pay,
                                    }}
                                    variant="total"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deductions Section */}
                    <Card>
                        <CardHeader className="bg-gradient-to-r from-red-600 to-rose-600">
                            <CardTitle className="text-white">Deductions</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="divide-y">
                                {/* Government Contributions */}
                                {categoryGroups.government.length > 0 && (
                                    <>
                                        <div className="bg-blue-50 px-4 py-2">
                                            <p className="text-xs font-semibold text-blue-900 uppercase tracking-wide">
                                                Government Contributions
                                            </p>
                                        </div>
                                        {categoryGroups.government.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`govt-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                    </>
                                )}

                                {/* Tax */}
                                {categoryGroups.tax.length > 0 && (
                                    <>
                                        <div className="bg-yellow-50 px-4 py-2">
                                            <p className="text-xs font-semibold text-yellow-900 uppercase tracking-wide">
                                                Tax Withholding
                                            </p>
                                        </div>
                                        {categoryGroups.tax.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`tax-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                    </>
                                )}

                                {/* Loans & Advances */}
                                {(categoryGroups.loan.length > 0 ||
                                    categoryGroups.advance.length > 0) && (
                                    <>
                                        <div className="bg-purple-50 px-4 py-2">
                                            <p className="text-xs font-semibold text-purple-900 uppercase tracking-wide">
                                                Loans & Advances
                                            </p>
                                        </div>
                                        {categoryGroups.loan.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`loan-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                        {categoryGroups.advance.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`adv-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                    </>
                                )}

                                {/* Attendance Related */}
                                {(categoryGroups.leave.length > 0 ||
                                    categoryGroups.attendance.length > 0) && (
                                    <>
                                        <div className="bg-orange-50 px-4 py-2">
                                            <p className="text-xs font-semibold text-orange-900 uppercase tracking-wide">
                                                Attendance & Leave
                                            </p>
                                        </div>
                                        {categoryGroups.leave.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`leave-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                        {categoryGroups.attendance.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`att-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                    </>
                                )}

                                {/* Other */}
                                {categoryGroups.other.length > 0 && (
                                    <>
                                        <div className="bg-gray-100 px-4 py-2">
                                            <p className="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                                Other Deductions
                                            </p>
                                        </div>
                                        {categoryGroups.other.map((d, idx) => (
                                            <SalaryComponentRow
                                                key={`other-${idx}`}
                                                component={d}
                                            />
                                        ))}
                                    </>
                                )}

                                <SalaryComponentRow
                                    component={{
                                        name: 'TOTAL DEDUCTIONS',
                                        amount: totalDeductions,
                                    }}
                                    variant="total"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Summary */}
                    <Card className="border-2 border-blue-600">
                        <CardHeader className="bg-gradient-to-r from-blue-600 to-indigo-600">
                            <CardTitle className="text-white text-2xl">
                                NET PAY (Take-Home)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="mb-6 text-center">
                                <p className="text-5xl font-bold text-blue-600">
                                    ₱ {payslip.net_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>

                            <div className="grid grid-cols-3 gap-4 border-t pt-4">
                                <div className="text-center">
                                    <p className="text-sm text-gray-600">Gross Income (YTD)</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        ₱ {payslip.year_to_date_gross.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="text-sm text-gray-600">Deductions (YTD)</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        ₱ {payslip.year_to_date_deductions.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="text-sm text-gray-600">Net Pay (YTD)</p>
                                    <p className="text-lg font-semibold text-blue-600">
                                        ₱ {payslip.year_to_date_net.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Download Button */}
                    {onDownload && (
                        <Button
                            onClick={onDownload}
                            className="w-full gap-2 bg-green-600 hover:bg-green-700"
                            size="lg"
                        >
                            <Download className="h-5 w-5" />
                            Download Payslip PDF
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
