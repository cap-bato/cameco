import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Download, Printer, ChevronLeft, TrendingUp, DollarSign, CheckCircle, AlertCircle } from 'lucide-react';
import type { CashEmployee, CashDistribution } from '@/types/payroll-pages';

interface ReportData {
    period_id: string;
    period_label: string;
    total_cash_employees: number;
    total_cash_amount: number;
    formatted_total: string;
    distributed_count: number;
    distributed_amount: number;
    formatted_distributed: string;
    unclaimed_count: number;
    unclaimed_amount: number;
    formatted_unclaimed: string;
    distribution_rate: number;
}

interface AccountabilityReportProps {
    report: ReportData;
    employees: CashEmployee[];
    distributions: CashDistribution[];
}

const breadcrumbs = [
    { title: 'Payroll', href: '/payroll/dashboard' },
    { title: 'Payments', href: '#' },
    { title: 'Cash Management', href: '/payroll/payments/cash' },
    { title: 'Accountability Report', href: '#' },
];

export default function AccountabilityReport({
    report,
    employees,
}: AccountabilityReportProps) {
    const handlePrint = () => {
        window.print();
    };

    const handleDownloadPDF = () => {
        const prevTitle = document.title;
        document.title = `Cash Accountability Report - ${report.period_label}`;
        window.print();
        document.title = prevTitle;
    };

    const distributedEmployees = employees.filter((e) => e.distribution_status === 'distributed');
    const unclaimedEmployees = employees.filter((e) => e.distribution_status === 'unclaimed');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Accountability Report" />
            <style>{`@media print { nav { display: none !important; } }`}</style>
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 print:p-2 print:gap-3 print:rounded-none">
                {/* Header */}
                <div className="flex items-center justify-between print:mb-2 print:hidden">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">Cash Payment Accountability Report</h1>
                        <p className="text-muted-foreground">Period: {report.period_label}</p>
                    </div>
                    <div className="flex gap-2 print:hidden">
                        <Button variant="outline" size="sm" onClick={() => router.get('/payroll/payments/cash')}>
                            <ChevronLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                        <Button size="sm" onClick={handleDownloadPDF} variant="outline">
                            <Download className="h-4 w-4 mr-2" />
                            PDF
                        </Button>
                        <Button size="sm" onClick={handlePrint} className="bg-blue-600 hover:bg-blue-700">
                            <Printer className="h-4 w-4 mr-2" />
                            Print
                        </Button>
                    </div>
                </div>

                {/* Print Title */}
                <div className="hidden print:block print:mb-2">
                    <h1 className="text-lg font-bold">Cash Payment Accountability Report</h1>
                    <p className="text-sm text-gray-600">Period: {report.period_label}</p>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4 print:hidden">
                    <Card className="print:shadow-none print:border-gray-300">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 print:pb-1">
                            <CardTitle className="text-sm font-medium print:text-xs">Total Employees</CardTitle>
                            <DollarSign className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent className="print:pt-1">
                            <div className="text-2xl font-bold print:text-xl">{report.total_cash_employees}</div>
                            <p className="text-xs text-muted-foreground mt-1">{report.formatted_total}</p>
                        </CardContent>
                    </Card>

                    <Card className="print:shadow-none print:border-gray-300">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 print:pb-1">
                            <CardTitle className="text-sm font-medium print:text-xs">Distributed</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent className="print:pt-1">
                            <div className="text-2xl font-bold print:text-xl text-green-600">
                                {report.distributed_count}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">{report.formatted_distributed}</p>
                        </CardContent>
                    </Card>

                    <Card className="print:shadow-none print:border-gray-300">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 print:pb-1">
                            <CardTitle className="text-sm font-medium print:text-xs">Unclaimed</CardTitle>
                            <AlertCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent className="print:pt-1">
                            <div className="text-2xl font-bold print:text-xl text-red-600">
                                {report.unclaimed_count}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">{report.formatted_unclaimed}</p>
                        </CardContent>
                    </Card>

                    <Card className="print:shadow-none print:border-gray-300">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 print:pb-1">
                            <CardTitle className="text-sm font-medium print:text-xs">Distribution Rate</CardTitle>
                            <TrendingUp className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent className="print:pt-1">
                            <div className="text-2xl font-bold print:text-xl text-purple-600">{report.distribution_rate}%</div>
                            <p className="text-xs text-muted-foreground mt-1">of total amount</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Distribution Summary Table */}
                <Card className="print:shadow-none print:border-gray-300 print:border print:break-inside-avoid">
                    <CardHeader className="print:pb-2 print:mb-2">
                        <CardTitle className="text-base print:text-sm">Distribution Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="print:pt-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm border-collapse print:text-xs">
                                <thead>
                                    <tr className="border-b-2 border-gray-200 print:border-b print:border-gray-300">
                                        <th className="text-left py-2 px-2 font-semibold text-gray-700">Status</th>
                                        <th className="text-right py-2 px-2 font-semibold text-gray-700">Count</th>
                                        <th className="text-right py-2 px-2 font-semibold text-gray-700">Percentage</th>
                                        <th className="text-right py-2 px-2 font-semibold text-gray-700">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="border-b border-gray-100 print:border-gray-200">
                                        <td className="py-2 px-2">
                                            <Badge className="bg-green-100 text-green-800">Distributed</Badge>
                                        </td>
                                        <td className="text-right py-2 px-2 font-semibold">{report.distributed_count}</td>
                                        <td className="text-right py-2 px-2">
                                            {report.total_cash_employees > 0
                                                ? (
                                                      (report.distributed_count /
                                                          report.total_cash_employees) *
                                                      100
                                                  ).toFixed(1)
                                                : 0}
                                            %
                                        </td>
                                        <td className="text-right py-2 px-2 font-bold text-green-600">
                                            {report.formatted_distributed}
                                        </td>
                                    </tr>
                                    <tr className="border-b border-gray-100 print:border-gray-200">
                                        <td className="py-2 px-2">
                                            <Badge className="bg-red-100 text-red-800">Unclaimed</Badge>
                                        </td>
                                        <td className="text-right py-2 px-2 font-semibold">{report.unclaimed_count}</td>
                                        <td className="text-right py-2 px-2">
                                            {report.total_cash_employees > 0
                                                ? (
                                                      (report.unclaimed_count / report.total_cash_employees) *
                                                      100
                                                  ).toFixed(1)
                                                : 0}
                                            %
                                        </td>
                                        <td className="text-right py-2 px-2 font-bold text-red-600">
                                            {report.formatted_unclaimed}
                                        </td>
                                    </tr>
                                    <tr className="border-t-2 border-gray-300 bg-gray-50 print:bg-white print:border-t print:border-gray-300">
                                        <td className="py-2 px-2 font-bold">Total</td>
                                        <td className="text-right py-2 px-2 font-bold">{report.total_cash_employees}</td>
                                        <td className="text-right py-2 px-2 font-bold">100%</td>
                                        <td className="text-right py-2 px-2 font-bold">{report.formatted_total}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Distributed Employees */}
                {distributedEmployees.length > 0 && (
                    <Card className="print:shadow-none print:border-gray-300 print:border print:break-inside-avoid">
                        <CardHeader className="print:pb-2 print:mb-2">
                            <CardTitle className="text-base print:text-sm">
                                Distributed Employees ({distributedEmployees.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="print:pt-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm border-collapse print:text-xs">
                                    <thead>
                                        <tr className="border-b-2 border-gray-200 print:border-b print:border-gray-300">
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">
                                                Employee Name
                                            </th>
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">
                                                Employee #
                                            </th>
                                            <th className="text-right py-2 px-2 font-semibold text-gray-700">Net Pay</th>
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">
                                                Distributed By
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {distributedEmployees.map((emp) => (
                                            <tr key={emp.id} className="border-b border-gray-100 print:border-gray-200">
                                                <td className="py-2 px-2">{emp.employee_name}</td>
                                                <td className="py-2 px-2">{emp.employee_number}</td>
                                                <td className="text-right py-2 px-2 font-semibold text-green-600">
                                                    {emp.formatted_net_pay}
                                                </td>
                                                <td className="py-2 px-2">{emp.distributed_by || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Unclaimed Employees */}
                {unclaimedEmployees.length > 0 && (
                    <Card className="border-red-200 print:shadow-none print:border-gray-300 print:border-red-200 print:border print:break-inside-avoid">
                        <CardHeader className="print:pb-2 print:mb-2">
                            <CardTitle className="text-base print:text-sm text-red-600">
                                Unclaimed Employees ({unclaimedEmployees.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="print:pt-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm border-collapse print:text-xs">
                                    <thead>
                                        <tr className="border-b-2 border-red-200 print:border-b print:border-gray-300">
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">
                                                Employee Name
                                            </th>
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">
                                                Employee #
                                            </th>
                                            <th className="text-right py-2 px-2 font-semibold text-gray-700">Amount</th>
                                            <th className="text-left py-2 px-2 font-semibold text-gray-700">Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {unclaimedEmployees.map((emp) => (
                                            <tr key={emp.id} className="border-b border-red-100 print:border-gray-200">
                                                <td className="py-2 px-2">{emp.employee_name}</td>
                                                <td className="py-2 px-2">{emp.employee_number}</td>
                                                <td className="text-right py-2 px-2 font-semibold text-red-600">
                                                    {emp.formatted_net_pay}
                                                </td>
                                                <td className="py-2 px-2">{emp.department}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Report Footer */}
                <Card className="bg-gray-50 print:bg-white print:shadow-none print:border-gray-300 print:text-xs print:break-inside-avoid">
                    <CardContent className="pt-6 text-xs text-gray-600 space-y-1 print:pt-2 print:pb-2">
                        <p className="print:text-xs">Generated on: {new Date().toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        })}</p>
                        <p className="print:text-xs">This report provides a summary of cash payment distribution for accounting and reconciliation purposes.</p>
                        <p className="print:text-xs">All amounts are in Philippine Pesos (₱)</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
