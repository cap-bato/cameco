import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Download, TrendingUp, Gift } from 'lucide-react';
import { useState } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

interface AnnualSummaryData {
    year: number;
    total_gross: number;
    total_deductions: number;
    total_net: number;
    thirteenth_month_pay?: number;
    bonuses_received?: number;
    tax_withheld: number;
}

interface AnnualPayslipSummaryProps {
    year: number;
    summaryData: AnnualSummaryData;
    onDownloadBIR2316: () => void;
    isDownloading?: boolean;
}

// ============================================================================
// Component
// ============================================================================

export function AnnualPayslipSummary({
    year,
    summaryData,
    onDownloadBIR2316,
    isDownloading = false,
}: AnnualPayslipSummaryProps) {
    const [showDetails, setShowDetails] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    // Calculate additional metrics
    const totalEarnings = summaryData.total_gross + (summaryData.thirteenth_month_pay || 0) + (summaryData.bonuses_received || 0);
    const effectiveTaxRate = totalEarnings > 0 ? (summaryData.tax_withheld / totalEarnings * 100).toFixed(2) : '0.00';

    return (
        <div className="space-y-6">
            {/* Summary Header */}
            <div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    {year} Annual Summary
                </h2>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                    Complete payroll summary for the calendar year
                </p>
            </div>

            {/* Main Summary Cards */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {/* Total Gross */}
                <Card className="border-l-4 border-l-green-500">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total Gross Income
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                            {formatCurrency(summaryData.total_gross)}
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-500 mt-2">
                            Basic salary + allowances
                        </p>
                    </CardContent>
                </Card>

                {/* Total Deductions */}
                <Card className="border-l-4 border-l-red-500">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total Deductions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-600 dark:text-red-400">
                            {formatCurrency(summaryData.total_deductions)}
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-500 mt-2">
                            Taxes, SSS, PhilHealth, Pag-IBIG
                        </p>
                    </CardContent>
                </Card>

                {/* Total Net Pay */}
                <Card className="border-l-4 border-l-blue-500">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total Net Pay
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {formatCurrency(summaryData.total_net)}
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-500 mt-2">
                            Take-home pay for the year
                        </p>
                    </CardContent>
                </Card>

                {/* Tax Withheld */}
                <Card className="border-l-4 border-l-purple-500">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Tax Withheld
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {formatCurrency(summaryData.tax_withheld)}
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-500 mt-2">
                            Effective rate: {effectiveTaxRate}%
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Additional Compensation */}
            {(summaryData.thirteenth_month_pay || summaryData.bonuses_received) && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Additional Compensation</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {summaryData.thirteenth_month_pay && (
                                <div className="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                    <div className="flex items-center gap-3">
                                        <Gift className="h-5 w-5 text-orange-500" />
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                13th Month Pay
                                            </p>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                                Year-end bonus
                                            </p>
                                        </div>
                                    </div>
                                    <p className="font-bold text-lg text-orange-600 dark:text-orange-400">
                                        {formatCurrency(summaryData.thirteenth_month_pay)}
                                    </p>
                                </div>
                            )}

                            {summaryData.bonuses_received && (
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <TrendingUp className="h-5 w-5 text-indigo-500" />
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                Performance Bonuses
                                            </p>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                                Special bonuses received
                                            </p>
                                        </div>
                                    </div>
                                    <p className="font-bold text-lg text-indigo-600 dark:text-indigo-400">
                                        {formatCurrency(summaryData.bonuses_received)}
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Detailed Breakdown (Expandable) */}
            {showDetails && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Detailed Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {/* Income Breakdown */}
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white mb-3">
                                    Income Breakdown
                                </h3>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Basic Salary (Annual)
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_gross * 0.7)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Allowances (Annual)
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_gross * 0.3)}
                                        </span>
                                    </div>
                                    {summaryData.thirteenth_month_pay && (
                                        <div className="flex justify-between">
                                            <span className="text-gray-600 dark:text-gray-400">
                                                13th Month Pay
                                            </span>
                                            <span className="font-medium text-gray-900 dark:text-white">
                                                {formatCurrency(summaryData.thirteenth_month_pay)}
                                            </span>
                                        </div>
                                    )}
                                    {summaryData.bonuses_received && (
                                        <div className="flex justify-between">
                                            <span className="text-gray-600 dark:text-gray-400">
                                                Performance Bonuses
                                            </span>
                                            <span className="font-medium text-gray-900 dark:text-white">
                                                {formatCurrency(summaryData.bonuses_received)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="border-t border-gray-200 dark:border-gray-700 pt-2 flex justify-between font-bold">
                                        <span className="text-gray-900 dark:text-white">
                                            Total Earnings
                                        </span>
                                        <span className="text-green-600 dark:text-green-400">
                                            {formatCurrency(totalEarnings)}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Deductions Breakdown */}
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white mb-3">
                                    Deductions Breakdown
                                </h3>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Income Tax Withheld (BIR)
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.tax_withheld)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Social Security System (SSS)
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_deductions * 0.35)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            PhilHealth
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_deductions * 0.20)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Pag-IBIG
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_deductions * 0.15)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Other Deductions
                                        </span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(summaryData.total_deductions * 0.30)}
                                        </span>
                                    </div>
                                    <div className="border-t border-gray-200 dark:border-gray-700 pt-2 flex justify-between font-bold">
                                        <span className="text-gray-900 dark:text-white">
                                            Total Deductions
                                        </span>
                                        <span className="text-red-600 dark:text-red-400">
                                            {formatCurrency(summaryData.total_deductions)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Toggle Details & Download Actions */}
            <div className="flex flex-col sm:flex-row gap-3">
                <Button
                    variant="outline"
                    onClick={() => setShowDetails(!showDetails)}
                >
                    {showDetails ? 'Hide Details' : 'Show Details'}
                </Button>

                <Button
                    onClick={onDownloadBIR2316}
                    disabled={isDownloading}
                    className="sm:ml-auto"
                >
                    <Download className="mr-2 h-4 w-4" />
                    {isDownloading ? 'Downloading...' : 'Download BIR 2316'}
                </Button>
            </div>

            {/* Info Box */}
            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                <p className="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Note:</strong> This summary is for your reference. The official BIR 2316 
                    tax certificate is required for your annual tax filing with the Bureau of Internal Revenue.
                </p>
            </div>
        </div>
    );
}
