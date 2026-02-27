import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Printer, Download, ChevronLeft } from 'lucide-react';
import type { EnvelopeData } from '@/types/payroll-pages';

interface EnvelopePreviewProps {
    envelopes: EnvelopeData[];
    total_amount: number;
    formatted_total: string;
    count: number;
    period_id: string;
}

const breadcrumbs = [
    { title: 'Payroll', href: '/payroll/dashboard' },
    { title: 'Payments', href: '#' },
    { title: 'Cash Management', href: '/payroll/payments/cash' },
    { title: 'Envelope Preview', href: '#' },
];

export default function EnvelopePreview({
    envelopes,
    formatted_total,
    count,
    period_id,
}: EnvelopePreviewProps) {
    const [selectedEnvelope, setSelectedEnvelope] = useState<number | null>(null);
    const [printMode, setPrintMode] = useState(false);
    const [printSelectedOnly, setPrintSelectedOnly] = useState(false);

    const handlePrintAll = () => {
        setPrintSelectedOnly(false);
        setPrintMode(true);
        setTimeout(() => {
            window.print();
            setPrintMode(false);
        }, 100);
    };

    const handlePrintSelected = () => {
        setPrintSelectedOnly(true);
        setPrintMode(true);
        setTimeout(() => {
            window.print();
            setPrintMode(false);
            setPrintSelectedOnly(false);
        }, 100);
    };


    const selectedData = selectedEnvelope !== null ? envelopes.find((e) => e.id === selectedEnvelope) : envelopes[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Envelope Preview" />
            <style>{`
                @media print {
                    aside,
                    header,
                    nav,
                    [data-sidebar],
                    .sidebar,
                    .print\\:hidden {
                        display: none !important;
                    }
                    main,
                    .main-content,
                    [data-main] {
                        width: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    @page {
                        size: landscape;
                        margin: 1cm;
                    }
                }
            `}</style>
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 print:p-0 print:gap-0">
                {/* Header */}
                <div className="flex items-center justify-between print:hidden">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">Envelope Preview</h1>
                        <p className="text-muted-foreground">
                            Review and print {count} cash payment envelopes
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get('/payroll/payments/cash')}
                    >
                        <ChevronLeft className="h-4 w-4 mr-2" />
                        Back
                    </Button>
                </div>

                {/* Summary */}
                <Card className="print:hidden">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">Summary</CardTitle>
                        <div className="flex gap-2">
                            {/* Download all envelopes */}
                            <Button size="sm" variant="outline" asChild>
                                <a href={`/payroll/payments/cash/generate-envelopes/pdf?period_id=${period_id}`}>
                                    <Download className="h-4 w-4 mr-2" />
                                    Download All (PDF)
                                </a>
                            </Button>
                            {/* Download selected envelope */}
                            {selectedData && (
                                <Button size="sm" variant="outline" asChild>
                                    <a href={`/payroll/payments/cash/generate-envelopes/pdf?period_id=${period_id}&employee_ids[]=${selectedData.employee_id}`}>
                                        <Download className="h-4 w-4 mr-2" />
                                        Download This Envelope
                                    </a>
                                </Button>
                            )}
                            {/* Print selected envelope */}
                            <Button size="sm" onClick={handlePrintSelected} disabled={!selectedData}>
                                <Printer className="h-4 w-4 mr-2" />
                                Print Selected
                            </Button>
                            {/* Print all envelopes */}
                            <Button size="sm" onClick={handlePrintAll} className="bg-blue-600 hover:bg-blue-700">
                                <Printer className="h-4 w-4 mr-2" />
                                Print All
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-xs font-semibold text-gray-600">TOTAL ENVELOPES</p>
                            <p className="text-2xl font-bold">{count}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-600">TOTAL AMOUNT</p>
                            <p className="text-2xl font-bold">{formatted_total}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-gray-600">PAGES</p>
                            <p className="text-2xl font-bold">
                                {selectedData ? selectedData.total_pages : envelopes[0]?.total_pages || 1}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Main Preview and List */}
                <div className={printMode ? '' : 'grid gap-6 md:grid-cols-4'}>
                    {/* Envelope List - Hide on print */}
                    {!printMode && (
                        <Card className="md:col-span-1">
                            <CardHeader>
                                <CardTitle className="text-sm">Envelopes ({count})</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 max-h-96 overflow-y-auto">
                                {envelopes.map((env) => (
                                    <button
                                        key={env.id}
                                        onClick={() => setSelectedEnvelope(env.id)}
                                        className={`w-full text-left p-2 rounded text-sm transition ${
                                            selectedEnvelope === env.id
                                                ? 'bg-blue-100 border-blue-500 border'
                                                : 'hover:bg-gray-100 border border-transparent'
                                        }`}
                                    >
                                        <p className="font-semibold text-gray-900">{env.employee_name}</p>
                                        <p className="text-xs text-gray-600">{env.employee_number}</p>
                                        <p className="text-xs font-semibold text-green-600 mt-1">
                                            {env.formatted_net_pay}
                                        </p>
                                    </button>
                                ))}
                            </CardContent>
                        </Card>
                    )}

                    {/* Envelope Preview */}
                    <div className={printMode ? 'w-full space-y-0' : 'md:col-span-3 space-y-4'}>
                        {printMode ? (
                            // Print view: Show all envelopes or only selected based on printSelectedOnly
                            <div className="space-y-6 print:space-y-4">
                                {(printSelectedOnly && selectedData ? [selectedData] : envelopes).map((envelope, index) => (
                                    <div
                                        key={envelope.id}
                                        className="bg-white border-4 border-dashed border-gray-300 p-8 rounded space-y-4 print:border-2 print:p-4 print:break-inside-avoid"
                                    >
                                        {/* Header */}
                                        <div className="border-b-2 border-gray-200 pb-4">
                                            <p className="text-xs font-semibold text-gray-600">CASH PAYMENT ENVELOPE</p>
                                            <p className="text-lg font-bold text-gray-900">
                                                {envelope.employee_name}
                                            </p>
                                        </div>

                                        {/* Employee Info */}
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">EMPLOYEE NUMBER</p>
                                                <p className="text-sm font-bold text-gray-900">
                                                    {envelope.employee_number}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">POSITION</p>
                                                <p className="text-sm text-gray-900">{envelope.position}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">DEPARTMENT</p>
                                                <p className="text-sm text-gray-900">{envelope.department}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">PERIOD</p>
                                                <p className="text-sm text-gray-900">{envelope.period_name}</p>
                                            </div>
                                        </div>

                                        {/* Period Dates */}
                                        <div className="flex gap-4 border-t-2 border-b-2 border-gray-200 py-3">
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">FROM</p>
                                                <p className="text-sm text-gray-900">{envelope.period_start_date}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">TO</p>
                                                <p className="text-sm text-gray-900">{envelope.period_end_date}</p>
                                            </div>
                                        </div>

                                        {/* Amount */}
                                        <div className="bg-green-50 p-4 rounded border-2 border-green-200">
                                            <p className="text-xs font-semibold text-gray-600">NET PAY</p>
                                            <p className="text-3xl font-bold text-green-600">
                                                {envelope.formatted_net_pay}
                                            </p>
                                        </div>

                                        {/* Barcode & QR Code */}
                                        <div className="grid grid-cols-2 gap-4 border-t-2 border-gray-200 pt-4">
                                            <div className="text-center">
                                                <p className="text-xs font-semibold text-gray-600 mb-2">BARCODE</p>
                                                <div className="bg-gray-100 p-2 rounded text-xs font-mono text-center">
                                                    {envelope.barcode}
                                                </div>
                                            </div>
                                            <div className="text-center">
                                                <p className="text-xs font-semibold text-gray-600 mb-2">QR CODE</p>
                                                <div className="bg-gray-100 p-4 rounded flex items-center justify-center min-h-16">
                                                    <span className="text-xs text-gray-400">[QR Code]</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Footer */}
                                        <div className="border-t-2 border-gray-200 pt-3 flex justify-between items-center text-xs text-gray-600">
                                            <p>Printed: {envelope.print_date}</p>
                                            <p>
                                                Envelope {index + 1} of {envelopes.length}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : selectedData ? (
                            // Preview view: Show selected envelope
                            <div className="space-y-4">
                                {/* Large Preview */}
                                <Card className="print:break-inside-avoid">
                                    <CardContent className="pt-6">
                                        {/* Envelope Template */}
                                        <div className="bg-white border-4 border-dashed border-gray-300 p-8 rounded space-y-4">
                                            {/* Header */}
                                            <div className="border-b-2 border-gray-200 pb-4">
                                                <p className="text-xs font-semibold text-gray-600">CASH PAYMENT ENVELOPE</p>
                                                <p className="text-lg font-bold text-gray-900">
                                                    {selectedData.employee_name}
                                                </p>
                                            </div>

                                            {/* Employee Info */}
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">EMPLOYEE NUMBER</p>
                                                    <p className="text-sm font-bold text-gray-900">
                                                        {selectedData.employee_number}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">POSITION</p>
                                                    <p className="text-sm text-gray-900">{selectedData.position}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">DEPARTMENT</p>
                                                    <p className="text-sm text-gray-900">{selectedData.department}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">PERIOD</p>
                                                    <p className="text-sm text-gray-900">{selectedData.period_name}</p>
                                                </div>
                                            </div>

                                            {/* Period Dates */}
                                            <div className="flex gap-4 border-t-2 border-b-2 border-gray-200 py-3">
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">FROM</p>
                                                    <p className="text-sm text-gray-900">{selectedData.period_start_date}</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs font-semibold text-gray-600">TO</p>
                                                    <p className="text-sm text-gray-900">{selectedData.period_end_date}</p>
                                                </div>
                                            </div>

                                            {/* Amount */}
                                            <div className="bg-green-50 p-4 rounded border-2 border-green-200">
                                                <p className="text-xs font-semibold text-gray-600">NET PAY</p>
                                                <p className="text-3xl font-bold text-green-600">
                                                    {selectedData.formatted_net_pay}
                                                </p>
                                            </div>

                                            {/* Barcode & QR Code */}
                                            <div className="grid grid-cols-2 gap-4 border-t-2 border-gray-200 pt-4">
                                                <div className="text-center">
                                                    <p className="text-xs font-semibold text-gray-600 mb-2">BARCODE</p>
                                                    <div className="bg-gray-100 p-2 rounded text-xs font-mono text-center">
                                                        {selectedData.barcode}
                                                    </div>
                                                </div>
                                                <div className="text-center">
                                                    <p className="text-xs font-semibold text-gray-600 mb-2">QR CODE</p>
                                                    <div className="bg-gray-100 p-4 rounded flex items-center justify-center min-h-16">
                                                        <span className="text-xs text-gray-400">[QR Code]</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Footer */}
                                            <div className="border-t-2 border-gray-200 pt-3 flex justify-between items-center text-xs text-gray-600">
                                                <p>Printed: {selectedData.print_date}</p>
                                                <p>
                                                    Page {selectedData.page_number} of {selectedData.total_pages}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Envelope Details Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">Envelope Details</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">BARCODE</p>
                                                <p className="text-sm font-mono text-gray-900">
                                                    {selectedData.barcode}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">PRINT DATE</p>
                                                <p className="text-sm text-gray-900">{selectedData.print_date}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">PAGE NUMBER</p>
                                                <p className="text-sm text-gray-900">
                                                    {selectedData.page_number} of {selectedData.total_pages}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold text-gray-600">AMOUNT</p>
                                                <p className="text-sm font-bold text-green-600">
                                                    {selectedData.formatted_net_pay}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        ) : null}
                    </div>
                </div>

                {/* Instructions */}
                <Card className="bg-blue-50 border-blue-200 print:hidden">
                    <CardHeader>
                        <CardTitle className="text-sm">Printing Instructions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-gray-700">
                        <p>1. Review the envelope preview and ensure all details are correct</p>
                        <p>2. Select "Print Now" to print directly or "Download PDF" to save for later</p>
                        <p>3. Use standard A4 paper with 3 envelopes per page</p>
                        <p>4. Cut along the dashed borders to separate envelopes</p>
                        <p>5. Insert cash and seal envelopes before distribution</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
