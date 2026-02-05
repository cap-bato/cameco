import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PayslipDetailModal } from '@/components/employee/payslip-detail-modal';
import { AnnualPayslipSummary } from '@/components/employee/annual-payslip-summary';
import { BIR2316Section } from '@/components/employee/bir-2316-section';
import {
    DollarSign,
    Download,
    Eye,
    Calendar,
    AlertCircle,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { format, parseISO } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface EmployeeInfo {
    id: number;
    employee_number: string;
    full_name: string;
    department: string;
}

interface SalaryComponent {
    name: string;
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
    year_to_date_net: number;
    pdf_url?: string;
}

interface PayslipsIndexProps {
    employee: EmployeeInfo;
    payslips: PayslipRecord[];
    availableYears: number[];
    filters: {
        year: number;
    };
    annualSummary?: {
        year: number;
        total_gross: number;
        total_deductions: number;
        total_net: number;
        thirteenth_month_pay?: number;
        bonuses_received?: number;
        tax_withheld: number;
    };
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
    {
        title: 'Payslips',
        href: '/employee/payslips',
    },
];

// Status badge configuration
const statusConfig = {
    released: {
        label: 'Released',
        color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        icon: TrendingUp,
    },
    pending: {
        label: 'Pending',
        color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        icon: Calendar,
    },
    processing: {
        label: 'Processing',
        color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        icon: Calendar,
    },
    failed: {
        label: 'Failed',
        color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: AlertCircle,
    },
};

// ============================================================================
// Main Component
// ============================================================================

export default function PayslipsIndex({
    employee,
    payslips,
    filters,
    annualSummary,
    error,
}: PayslipsIndexProps) {
    const [selectedPayslip, setSelectedPayslip] = useState<PayslipRecord | null>(null);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [isBIR2316Downloading, setIsBIR2316Downloading] = useState(false);

    // Calculate year-to-date totals
    const ytdStats = {
        totalGross: payslips?.reduce((sum, p) => sum + (p.gross_pay || 0), 0) || 0,
        totalNet: payslips?.reduce((sum, p) => sum + (p.net_pay || 0), 0) || 0,
        totalDeductions: payslips?.reduce((sum, p) => {
            const deductionsSum = p.deductions?.reduce((ds, d) => ds + (d.amount || 0), 0) || 0;
            return sum + deductionsSum;
        }, 0) || 0,
        payslipsReleased: payslips?.filter(p => p.status === 'released').length || 0,
    };

    const handleViewDetails = (payslip: PayslipRecord) => {
        setSelectedPayslip(payslip);
        setIsDetailModalOpen(true);
    };

    const handleDownloadPDF = (payslip: PayslipRecord) => {
        if (payslip.pdf_url) {
            // TODO: Implement PDF download via backend
            // window.location.href = `/employee/payslips/${payslip.id}/download`;
            alert('PDF download will be available soon');
        }
    };

    const handleDownloadBIR2316 = async (year: number) => {
        setIsBIR2316Downloading(true);
        try {
            const response = await fetch(
                `/employee/payslips/bir-2316/download?year=${year}`,
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (!response.ok) {
                throw new Error(`Failed to download BIR 2316: ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `BIR-2316-${year}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Error downloading BIR 2316:', err);
            alert('Failed to download BIR 2316. Please try again.');
        } finally {
            setIsBIR2316Downloading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslips" />

            <div className="space-y-6 p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Payslips
                    </h1>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        View and download your payslips and salary history
                    </p>
                </div>
                {/* Error Message */}
                {error && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/10">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-red-800 dark:text-red-200">
                                <AlertCircle className="h-5 w-5" />
                                <p className="text-sm font-medium">{error}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Employee Info Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-full bg-green-100 p-3 dark:bg-green-900/30">
                                <DollarSign className="h-6 w-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {employee.full_name}
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {employee.employee_number} • {employee.department}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Year-to-Date Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-400" />
                            {filters.year} Year-to-Date Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/10">
                                <p className="text-sm text-green-600 dark:text-green-400">Total Gross</p>
                                <p className="mt-2 text-2xl font-bold text-green-800 dark:text-green-200">
                                    ₱{ytdStats.totalGross.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/10">
                                <p className="text-sm text-red-600 dark:text-red-400">Total Deductions</p>
                                <p className="mt-2 text-2xl font-bold text-red-800 dark:text-red-200">
                                    ₱{ytdStats.totalDeductions.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                                <p className="text-sm text-blue-600 dark:text-blue-400">Total Net Pay</p>
                                <p className="mt-2 text-2xl font-bold text-blue-800 dark:text-blue-200">
                                    ₱{ytdStats.totalNet.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </p>
                            </div>
                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-900/10">
                                <p className="text-sm text-purple-600 dark:text-purple-400">Payslips Released</p>
                                <p className="mt-2 text-2xl font-bold text-purple-800 dark:text-purple-200">
                                    {ytdStats.payslipsReleased}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Annual Payslip Summary - Only show if annual summary data is available */}
                {annualSummary && (
                    <AnnualPayslipSummary
                        year={annualSummary.year}
                        summaryData={annualSummary}
                        onDownloadBIR2316={() => handleDownloadBIR2316(annualSummary.year)}
                        isDownloading={isBIR2316Downloading}
                    />
                )}

                {/* Payslips Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Payslips for {filters.year}
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!payslips || payslips.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <DollarSign className="h-12 w-12 text-gray-400" />
                                <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                    No payslips found for {filters.year}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-200 dark:border-gray-700">
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Pay Period
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Pay Date
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                                Gross Pay
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                                Deductions
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                                Net Pay
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payslips?.map((payslip) => {
                                            const deductionsTotal = payslip.deductions?.reduce((sum, d) => sum + (d.amount || 0), 0) || 0;
                                            const StatusIcon = payslip.status && statusConfig[payslip.status] ? statusConfig[payslip.status].icon : TrendingUp;

                                            return (
                                                <tr 
                                                    key={payslip.id}
                                                    className="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50"
                                                >
                                                    <td className="px-4 py-3 text-gray-900 dark:text-white">
                                                        {payslip.pay_period_start && payslip.pay_period_end ? (
                                                            <>
                                                                {format(parseISO(payslip.pay_period_start), 'MMM dd')} - {format(parseISO(payslip.pay_period_end), 'MMM dd, yyyy')}
                                                            </>
                                                        ) : (
                                                            'N/A'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                        {payslip.pay_date ? format(parseISO(payslip.pay_date), 'MMM dd, yyyy') : 'N/A'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                                        ₱{payslip.gross_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                                        ₱{deductionsTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-bold text-green-700 dark:text-green-400">
                                                        ₱{payslip.net_pay.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <div className="flex items-center justify-center gap-1">
                                                            <StatusIcon className="h-4 w-4" />
                                                            {payslip.status && statusConfig[payslip.status] && (
                                                                <Badge className={statusConfig[payslip.status].color}>
                                                                    {statusConfig[payslip.status].label}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-center gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleViewDetails(payslip)}
                                                                title="View Details"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {payslip.pdf_url && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleDownloadPDF(payslip)}
                                                                    title="Download PDF"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* BIR 2316 Tax Certificate Download */}
                <BIR2316Section
                    currentYear={new Date().getFullYear()}
                    onDownload={handleDownloadBIR2316}
                />

                {/* Help Section */}
                <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/10">
                    <CardContent className="pt-6">
                        <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-100">Need Help?</h4>
                        <p className="mt-2 text-sm text-blue-800 dark:text-blue-200">
                            Payslips are released on the 15th and 30th/31st of each month. 
                            If you don't see your expected payslip, please contact Payroll Officer.
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Payslip Detail Modal */}
            {selectedPayslip && (
                <PayslipDetailModal
                    isOpen={isDetailModalOpen}
                    onClose={() => setIsDetailModalOpen(false)}
                    payslip={selectedPayslip}
                />
            )}
        </AppLayout>
    );
}
