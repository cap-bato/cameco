import React from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Download, Info } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

// ============================================================================
// Type Definitions
// ============================================================================

interface SalaryComponent {
    name: string;
    description?: string;
    category?: string;
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
    year_to_date_deductions: number;
    year_to_date_net: number;
    pdf_url?: string;
}

interface PayslipDetailModalProps {
    isOpen: boolean;
    onClose: () => void;
    payslip: Payslip;
}

const categoryColors: Record<string, { bg: string; border: string; text: string }> = {
    government: { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' },
    tax: { bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700' },
    loan: { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-700' },
    advance: { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-700' },
    leave: { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-700' },
    attendance: { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-700' },
    other: { bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-700' },
};

// ============================================================================
// Helper Components
// ============================================================================

const SalaryComponentRow: React.FC<{ 
    component: SalaryComponent;
    type: 'earning' | 'deduction';
}> = ({ component, type }) => {
    const categoryKey = (component.category || 'other').toLowerCase() as keyof typeof categoryColors;
    const colors = categoryColors[categoryKey] || categoryColors.other;

    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-3 px-4 hover:bg-gray-50">
            <div className="flex items-center gap-2 flex-1">
                <span className="text-gray-900 font-medium">{component.name}</span>
                {component.description && (
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Info className="h-4 w-4 text-gray-400 hover:text-gray-600 cursor-help" />
                            </TooltipTrigger>
                            <TooltipContent side="right" className="max-w-xs">
                                <p className="text-white text-sm">{component.description}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                )}
            </div>
            <span className={`font-semibold ${type === 'earning' ? 'text-green-700' : 'text-red-700'}`}>
                {type === 'earning' ? '+' : '-'} ₱{component.amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
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
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-PH', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    };

    const groupedDeductions: Record<string, SalaryComponent[]> = {};
    payslip.deductions.forEach((deduction) => {
        const category = (deduction.category || 'other').toLowerCase();
        if (!groupedDeductions[category]) {
            groupedDeductions[category] = [];
        }
        groupedDeductions[category].push(deduction);
    });

    const categoryLabels: Record<string, string> = {
        government: 'Government Contributions',
        tax: 'Tax Withholding',
        loan: 'Loans & Advances',
        advance: 'Advances',
        leave: 'Leave Deductions',
        attendance: 'Attendance & Leave',
        other: 'Other Deductions',
    };

    const handleDownloadPDF = () => {
        window.open(`/employee/payslips/${payslip.id}/download`, '_blank');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <span>Payslip Details</span>
                        <Button
                            onClick={handleDownloadPDF}
                            className="bg-green-600 hover:bg-green-700 text-white"
                            size="sm"
                        >
                            <Download className="h-4 w-4 mr-2" />
                            Download PDF
                        </Button>
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Pay Period Info */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p className="text-xs text-blue-600 font-semibold">PAY PERIOD</p>
                                <p className="text-sm font-medium text-gray-900 mt-1">
                                    {formatDate(payslip.pay_period_start)} - {formatDate(payslip.pay_period_end)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-blue-600 font-semibold">PAY DATE</p>
                                <p className="text-sm font-medium text-gray-900 mt-1">
                                    {formatDate(payslip.pay_date)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-blue-600 font-semibold">STATUS</p>
                                <p className="text-sm font-medium text-gray-900 mt-1 capitalize">
                                    {payslip.status}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-blue-600 font-semibold">PAYSLIP ID</p>
                                <p className="text-sm font-medium text-gray-900 mt-1">
                                    #{payslip.id}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Earnings Section */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="bg-gradient-to-r from-green-600 to-emerald-700 text-white px-4 py-3">
                            <h3 className="font-semibold text-sm uppercase tracking-wider">Earnings & Allowances</h3>
                        </div>
                        <div className="divide-y">
                            <SalaryComponentRow
                                component={{
                                    name: 'Basic Salary',
                                    amount: payslip.basic_salary,
                                }}
                                type="earning"
                            />
                            {payslip.allowances && payslip.allowances.length > 0 ? (
                                payslip.allowances.map((allowance, idx) => (
                                    <SalaryComponentRow
                                        key={idx}
                                        component={allowance}
                                        type="earning"
                                    />
                                ))
                            ) : null}
                            <div className="flex items-center justify-between bg-green-50 border-t-2 border-green-200 py-3 px-4">
                                <span className="font-bold text-gray-900">GROSS PAY</span>
                                <span className="font-bold text-lg text-green-700">
                                    ₱{payslip.gross_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Deductions Section */}
                    <div className="border rounded-lg overflow-hidden">
                        <div className="bg-gradient-to-r from-red-600 to-rose-700 text-white px-4 py-3">
                            <h3 className="font-semibold text-sm uppercase tracking-wider">Deductions</h3>
                        </div>
                        <div className="divide-y">
                            {Object.entries(groupedDeductions).length > 0 ? (
                                Object.entries(groupedDeductions).map(([category, deductions]) => (
                                    <div key={category}>
                                        {/* Category Header */}
                                        {Object.entries(groupedDeductions).length > 1 && (
                                            <div className={`px-4 py-2 ${categoryColors[category as keyof typeof categoryColors]?.bg || categoryColors.other.bg}`}>
                                                <p className={`text-xs font-bold uppercase tracking-wider ${categoryColors[category as keyof typeof categoryColors]?.text || categoryColors.other.text}`}>
                                                    {categoryLabels[category] || category}
                                                </p>
                                            </div>
                                        )}
                                        {/* Deduction Items */}
                                        {deductions.map((deduction, idx) => (
                                            <SalaryComponentRow
                                                key={idx}
                                                component={deduction}
                                                type="deduction"
                                            />
                                        ))}
                                    </div>
                                ))
                            ) : null}
                            <div className="flex items-center justify-between bg-red-50 border-t-2 border-red-200 py-3 px-4">
                                <span className="font-bold text-gray-900">TOTAL DEDUCTIONS</span>
                                <span className="font-bold text-lg text-red-700">
                                    ₱{payslip.deductions.reduce((sum, d) => sum + d.amount, 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Summary/Net Pay Section */}
                    <div className="border rounded-lg overflow-hidden bg-gradient-to-br from-blue-600 to-indigo-700">
                        <div className="bg-gradient-to-r from-blue-700 to-indigo-800 text-white px-4 py-3">
                            <h3 className="font-semibold text-sm uppercase tracking-wider">Payroll Summary</h3>
                        </div>
                        <div className="p-6 space-y-4">
                            {/* Net Pay */}
                            <div className="text-center mb-6">
                                <p className="text-blue-100 text-sm uppercase tracking-wider mb-2">NET PAY (Take-Home)</p>
                                <p className="text-5xl font-bold text-white">
                                    ₱{payslip.net_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>

                            {/* Year-to-Date Summary */}
                            <div className="grid grid-cols-3 gap-4 pt-6 border-t border-indigo-700">
                                <div className="text-center">
                                    <p className="text-indigo-100 text-xs uppercase tracking-wider">YTD Gross</p>
                                    <p className="text-lg font-bold text-white mt-2">
                                        ₱{payslip.year_to_date_gross.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="text-indigo-100 text-xs uppercase tracking-wider">YTD Deductions</p>
                                    <p className="text-lg font-bold text-white mt-2">
                                        ₱{payslip.year_to_date_deductions.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                                <div className="text-center">
                                    <p className="text-indigo-100 text-xs uppercase tracking-wider">YTD Net</p>
                                    <p className="text-lg font-bold text-white mt-2">
                                        ₱{payslip.year_to_date_net.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Info Footer */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p className="text-xs text-blue-800">
                            <strong>Confidential:</strong> This payslip contains your personal salary and deduction information. 
                            Please keep it safe and secure. If you have questions about your payslip, contact the Payroll Officer.
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
