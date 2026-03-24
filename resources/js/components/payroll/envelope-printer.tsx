import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, Printer, FileText } from 'lucide-react';
import type { CashEmployee } from '@/types/payroll-pages';

interface EnvelopePrinterProps {
    employees: CashEmployee[];
    periodId: string;
}

export default function EnvelopePrinter({ employees }: EnvelopePrinterProps) {
    const [isPrinting, setIsPrinting] = useState(false);

    const printableEmployees = employees.filter(
        (emp) => emp.envelope_status !== 'distributed' && emp.envelope_status !== 'unclaimed'
    );

    const handlePrintEnvelopes = async () => {
        setIsPrinting(true);
        try {
            // Simulate envelope printing
            window.print();
            setTimeout(() => setIsPrinting(false), 1000);
        } catch (error) {
            console.error('Error printing envelopes:', error);
            setIsPrinting(false);
        }
    };

    const handleGenerateForDownload = async () => {
        if (printableEmployees.length === 0) {
            alert('No envelopes available for printing');
            return;
        }

        setIsPrinting(true);
        // In real implementation, generate PDF and download
        setTimeout(() => {
            setIsPrinting(false);
            alert('Envelopes generated! Ready for printing.');
        }, 1000);
    };

    return (
        <>
            <style>{`
                @media print {
                    body, html {
                        background: #fff !important;
                    }
                    aside,
                    header,
                    nav,
                    [data-sidebar],
                    .sidebar,
                    .print\:hidden,
                    .print-hide,
                    .app-layout,
                    .main-content:not(.envelope-print-area) {
                        display: none !important;
                    }
                    .envelope-print-area {
                        display: block !important;
                        width: 100vw !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #fff !important;
                    }
                }
            `}</style>
            <div className="space-y-4 envelope-print-area">
            {/* Instructions Card */}
            <Card className="border-blue-200 bg-blue-50 print:hidden">
                <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2 text-blue-900">
                        <AlertCircle className="h-5 w-5" />
                        How to Use Envelope Printer
                    </CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-blue-800 space-y-2">
                    <p>1. Click "Generate PDF" to create a printable envelope document</p>
                    <p>2. Review the preview and adjust printer settings</p>
                    <p>3. Print on standard A4 paper (3 envelopes per page)</p>
                    <p>4. Cut along the dotted lines to separate envelopes</p>
                    <p>5. Verify barcode/QR code is readable before distribution</p>
                </CardContent>
            </Card>

            {/* Action Buttons */}
            <Card className="print:hidden">
                <CardHeader>
                    <CardTitle className="text-base">Envelope Generation</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex gap-2">
                        <Button
                            onClick={handlePrintEnvelopes}
                            disabled={printableEmployees.length === 0 || isPrinting}
                            variant="outline"
                        >
                            <Printer className="h-4 w-4 mr-2" />
                            Print Now
                        </Button>
                    </div>

                    <div className="text-sm text-gray-600">
                        <p>
                            <strong>{printableEmployees.length}</strong> employee envelopes ready to print
                        </p>
                        <p className="text-xs text-gray-500 mt-1">
                            Total amount: ₱{(printableEmployees.reduce((sum, emp) => sum + emp.net_pay, 0) / 100000).toFixed(2)}
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Envelope Preview */}
            {printableEmployees.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Preview (First 3 Envelopes)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-1">
                            {printableEmployees.slice(0, 3).map((employee, idx) => (
                                <div
                                    key={employee.id}
                                    className="border-4 border-dashed border-gray-300 p-6 bg-white rounded-lg"
                                    style={{ minHeight: '300px' }}
                                >
                                    {/* Envelope Header */}
                                    <div className="text-center mb-4 pb-4 border-b border-gray-300">
                                        <h3 className="font-bold text-lg">{employee.employee_name}</h3>
                                        <p className="text-sm text-gray-600">{employee.employee_number}</p>
                                    </div>

                                    {/* Envelope Body */}
                                    <div className="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p className="text-xs font-semibold text-gray-600">POSITION</p>
                                            <p className="text-sm font-semibold">{employee.position}</p>

                                            <p className="text-xs font-semibold text-gray-600 mt-2">DEPARTMENT</p>
                                            <p className="text-sm font-semibold">{employee.department}</p>
                                        </div>

                                        <div className="text-right">
                                            <p className="text-xs font-semibold text-gray-600">NET PAY</p>
                                            <p className="text-2xl font-bold text-green-600">
                                                {employee.formatted_net_pay}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Period Info */}
                                    <div className="mb-4 pb-4 border-b border-gray-300">
                                        <p className="text-xs font-semibold text-gray-600">PAYROLL PERIOD</p>
                                        <p className="text-sm">{employee.period_name}</p>
                                    </div>

                                    {/* QR Code Area */}
                                    <div className="flex items-center justify-center h-20 bg-gray-100 rounded border border-gray-300">
                                        <div className="text-center text-xs text-gray-500">
                                            <p>QR CODE</p>
                                            <p>EMP{employee.employee_number}</p>
                                        </div>
                                    </div>

                                    {/* Footer */}
                                    <div className="text-center mt-4 text-xs text-gray-500">
                                        <p>Print Date: {new Date().toLocaleDateString()}</p>
                                        <p>{idx + 1} of {printableEmployees.length}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {printableEmployees.length > 3 && (
                            <div className="mt-4 p-4 bg-gray-50 rounded text-center text-sm text-gray-600">
                                ... and {printableEmployees.length - 3} more envelopes
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {printableEmployees.length === 0 && (
                <Card className="print:hidden">
                    <CardContent className="pt-8 text-center">
                        <p className="text-muted-foreground">No envelopes available for printing</p>
                        <p className="text-xs text-gray-500 mt-2">
                            All envelopes have been printed or distributed
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
        </>
    );
}
