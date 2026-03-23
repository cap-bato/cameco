import React from 'react';
import { FileText, X, Download, Calendar, Building2, User } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { PayslipPreviewData } from '@/types/payroll-pages';

interface PayslipPreviewProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    data: PayslipPreviewData | null;
    onDownload?: () => void;
}

export function PayslipPreview({ open, onOpenChange, data, onDownload }: PayslipPreviewProps) {
    if (!data) return null;

    const formatPeso = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-PH', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto p-0">
                {/* Professional Header */}
                <div className="sticky top-0 z-10 bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-4 text-white flex items-center justify-between border-b-4 border-blue-700">
                    <div className="flex items-center gap-3">
                        <FileText className="h-6 w-6" />
                        <div>
                            <h2 className="text-xl font-bold">PAYSLIP PREVIEW</h2>
                            <p className="text-xs text-blue-100">Employee Compensation Statement</p>
                        </div>
                    </div>
                    {onDownload && (
                        <Button 
                            onClick={onDownload}
                            className="bg-white text-blue-900 hover:bg-blue-50 font-semibold"
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Download PDF
                        </Button>
                    )}
                </div>

                {/* Payslip Content */}
                <div className="space-y-6 py-6 px-6">
                    {/* Company Header */}
                    <div className="text-center border-b-2 border-gray-200 pb-4">
                        <h2 className="text-2xl font-bold text-blue-900">CAMECO CORPORATION</h2>
                        <p className="text-sm text-gray-600 font-medium">Professional Employee Payslip</p>
                    </div>

                    {/* Employee & Period Information */}
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Employee Info */}
                        <Card className="border-0 shadow-md hover:shadow-lg transition-shadow">
                            <CardHeader className="bg-gradient-to-r from-blue-50 to-blue-100 border-b-2 border-blue-200 pb-3">
                                <CardTitle className="flex items-center gap-2 text-base text-blue-900">
                                    <div className="bg-blue-900 text-white p-2 rounded">
                                        <User className="h-4 w-4" />
                                    </div>
                                    Employee Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm pt-4">
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Employee Name:</span>
                                    <span className="font-bold text-blue-900">{data.employee_name}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Employee ID:</span>
                                    <span className="font-mono text-gray-900 font-bold">{data.employee_number}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Position:</span>
                                    <span className="text-gray-900">{data.position}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="font-semibold text-gray-700">Department:</span>
                                    <span className="text-gray-900 flex items-center gap-1">
                                        <Building2 className="h-4 w-4 text-blue-600" />
                                        {data.department}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Period Info */}
                        <Card className="border-0 shadow-md hover:shadow-lg transition-shadow">
                            <CardHeader className="bg-gradient-to-r from-green-50 to-green-100 border-b-2 border-green-200 pb-3">
                                <CardTitle className="flex items-center gap-2 text-base text-green-900">
                                    <div className="bg-green-900 text-white p-2 rounded">
                                        <Calendar className="h-4 w-4" />
                                    </div>
                                    Pay Period
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm pt-4">
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Period:</span>
                                    <span className="font-bold text-gray-900">{data.period_name}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Period Start:</span>
                                    <span className="text-gray-900">{formatDate(data.period_start)}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                    <span className="font-semibold text-gray-700">Period End:</span>
                                    <span className="text-gray-900">{formatDate(data.period_end)}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="font-semibold text-gray-700">Pay Date:</span>
                                    <span className="font-bold text-green-700">{formatDate(data.pay_date)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Earnings Section */}
                    <Card className="border-0 shadow-md hover:shadow-lg transition-shadow">
                        <CardHeader className="bg-gradient-to-r from-green-600 to-green-700 text-white border-b-4 border-green-800">
                            <CardTitle className="text-lg font-bold">EARNINGS</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <div className="space-y-3">
                                {data.earnings.map((earning, index) => (
                                    <div key={index} className="flex justify-between items-center text-sm border-b border-gray-100 pb-2 last:border-0">
                                        <span className="text-gray-700 font-medium">{earning.name}</span>
                                        <span className="font-bold text-green-700 text-right">{formatPeso(earning.amount)}</span>
                                    </div>
                                ))}
                                <div className="flex justify-between items-center text-base font-bold pt-3 border-t-2 border-green-200 mt-3">
                                    <span className="text-green-900">GROSS PAY</span>
                                    <span className="text-green-700 text-lg">{formatPeso(data.gross_pay)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deductions Section */}
                    <Card className="border-0 shadow-md hover:shadow-lg transition-shadow">
                        <CardHeader className="bg-gradient-to-r from-red-600 to-red-700 text-white border-b-4 border-red-800">
                            <CardTitle className="text-lg font-bold">DEDUCTIONS</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <div className="space-y-3">
                                {data.deductions.map((deduction, index) => (
                                    <div key={index} className="flex justify-between items-center text-sm border-b border-gray-100 pb-2 last:border-0">
                                        <span className="text-gray-700 font-medium">{deduction.name}</span>
                                        <span className="font-bold text-red-600 text-right">({formatPeso(deduction.amount)})</span>
                                    </div>
                                ))}
                                <div className="flex justify-between items-center text-base font-bold pt-3 border-t-2 border-red-200 mt-3">
                                    <span className="text-red-900">TOTAL DEDUCTIONS</span>
                                    <span className="text-red-700 text-lg">({formatPeso(data.total_deductions)})</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Net Pay Section */}
                    <Card className="border-0 shadow-lg">
                        <CardContent className="bg-gradient-to-r from-blue-900 to-blue-800 text-white py-8 px-6 rounded-lg">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-bold text-blue-100 mb-1">NET PAY</p>
                                    <p className="text-xs text-blue-200">Amount to be received</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-4xl font-black">{formatPeso(data.net_pay)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Year-to-Date Summary */}
                    <Card className="border-0 shadow-md hover:shadow-lg transition-shadow">
                        <CardHeader className="bg-gradient-to-r from-blue-50 to-blue-100 border-b-2 border-blue-200">
                            <CardTitle className="text-base text-blue-900">Year-to-Date (YTD) Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="grid gap-6 md:grid-cols-3">
                                <div className="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border-l-4 border-green-600">
                                    <p className="text-xs font-bold text-gray-700 mb-2">YTD GROSS</p>
                                    <p className="text-2xl font-bold text-green-700">{formatPeso(data.ytd_gross)}</p>
                                </div>
                                <div className="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4 border-l-4 border-red-600">
                                    <p className="text-xs font-bold text-gray-700 mb-2">YTD DEDUCTIONS</p>
                                    <p className="text-2xl font-bold text-red-700">({formatPeso(data.ytd_deductions)})</p>
                                </div>
                                <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border-l-4 border-blue-600">
                                    <p className="text-xs font-bold text-gray-700 mb-2">YTD NET</p>
                                    <p className="text-2xl font-bold text-blue-700">{formatPeso(data.ytd_net)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Footer Notes */}
                    <div className="bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-300 p-5">
                        <p className="font-bold text-gray-900 mb-3 flex items-center gap-2">
                            <div className="w-1 h-5 bg-blue-900 rounded"></div>
                            Important Notes:
                        </p>
                        <ul className="space-y-2 text-xs text-gray-700">
                            <li className="flex gap-2">
                                <span className="text-blue-900 font-bold">•</span>
                                <span>This payslip is computer-generated and complies with DOLE regulations</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-blue-900 font-bold">•</span>
                                <span>All deductions are made in accordance with Philippine labor law</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-blue-900 font-bold">•</span>
                                <span>Government contributions (SSS, PhilHealth, Pag-IBIG) are as per current rates</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-blue-900 font-bold">•</span>
                                <span>For questions or concerns, please contact the Payroll Department</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
