import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Download, FileText, Send, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Period {
    id: string | number;
    name: string;
    month: string;
    status: string;
}

interface RF1Report {
    id: string | number;
    period_id: string | number;
    month: string;
    file_name: string;
    file_size: number;
    total_employees: number;
    total_basic_salary: number;
    total_employee_premium: number;
    total_employer_premium: number;
    total_premium: number;
    indigent_count: number;
    status: 'draft' | 'ready' | 'submitted' | 'accepted' | 'rejected';
    submission_status: string;
    submission_date: string | null;
}

interface PhilHealthRF1GeneratorProps {
    reports: RF1Report[];
    periods: Period[];
}

/**
 * PhilHealth RF1 Generator Component
 * Manages RF1 report generation and submission
 * RF1 = Monthly contribution report for PhilHealth EPRS
 */
export function PhilHealthRF1Generator({
    reports,
    periods,
}: PhilHealthRF1GeneratorProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<string>(
        periods.length > 0 ? String(periods[0].id) : ''
    );
    const [isGenerating, setIsGenerating] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{
        type: 'success' | 'error' | 'info';
        message: string;
    } | null>(null);

    const currentPeriod = periods.find((p) => String(p.id) === selectedPeriod);
    const existingReport = reports.find((r) => String(r.period_id) === selectedPeriod);

    const handleGenerateRF1 = () => {
        if (!currentPeriod) return;

        setIsGenerating(true);
        router.post(
            `/payroll/government/philhealth/generate-rf1/${currentPeriod.id}`,
            {},
            {
                onSuccess: () => {
                    setStatusMessage({
                        type: 'success',
                        message: `RF1 report generated for ${currentPeriod.name}`,
                    });
                    setTimeout(() => setStatusMessage(null), 4000);
                },
                onError: () => {
                    setStatusMessage({
                        type: 'error',
                        message: 'Failed to generate RF1 report',
                    });
                },
                onFinish: () => setIsGenerating(false),
            }
        );
    };

const handleDownload = (reportId: string | number) => {
    window.open(`/payroll/government/philhealth/download-rf1/${reportId}`, '_blank');
};
    const handleSubmit = (reportId: string | number) => {
        setIsSubmitting(true);
        router.post(
            `/payroll/government/philhealth/submit/${reportId}`,
            {},
            {
                onSuccess: () => {
                    setStatusMessage({
                        type: 'success',
                        message: 'RF1 report submitted successfully to PhilHealth',
                    });
                    setTimeout(() => setStatusMessage(null), 4000);
                },
                onError: () => {
                    setStatusMessage({
                        type: 'error',
                        message: 'Failed to submit RF1 report',
                    });
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'draft':
                return 'bg-gray-100 text-gray-800';
            case 'ready':
                return 'bg-blue-100 text-blue-800';
            case 'submitted':
                return 'bg-yellow-100 text-yellow-800';
            case 'accepted':
                return 'bg-green-100 text-green-800';
            case 'rejected':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <div className="space-y-6">
            {/* Status Message */}
            {statusMessage && (
                <Alert
                    className={
                        statusMessage.type === 'success'
                            ? 'border-green-200 bg-green-50'
                            : 'border-red-200 bg-red-50'
                    }
                >
                    <AlertCircle
                        className={
                            statusMessage.type === 'success'
                                ? 'h-4 w-4 text-green-600'
                                : 'h-4 w-4 text-red-600'
                        }
                    />
                    <AlertDescription
                        className={
                            statusMessage.type === 'success'
                                ? 'text-green-700'
                                : 'text-red-700'
                        }
                    >
                        {statusMessage.message}
                    </AlertDescription>
                </Alert>
            )}

            {/* Generation Section */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Generate RF1 Report
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <label className="block text-sm font-medium">Select Period</label>
                        <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {periods.map((period) => (
                                    <SelectItem key={period.id} value={String(period.id)}>
                                        {period.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {existingReport && (
                        <Alert className="border-amber-200 bg-amber-50">
                            <AlertCircle className="h-4 w-4 text-amber-600" />
                            <AlertDescription className="text-amber-700">
                                A report already exists for this period. Generating a new report will replace the existing one.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Button
                        onClick={handleGenerateRF1}
                        disabled={isGenerating || !currentPeriod}
                        className="w-full"
                    >
                        {isGenerating ? 'Generating...' : 'Generate RF1 Report'}
                    </Button>

                    {/* RF1 Format Information */}
                    <div className="mt-4 rounded-lg bg-blue-50 p-4">
                        <h4 className="font-medium text-blue-900">RF1 Report Format</h4>
                        <ul className="mt-2 space-y-1 text-sm text-blue-800">
                            <li>• Monthly contribution report</li>
                            <li>• CSV format for PhilHealth EPRS portal</li>
                            <li>• Includes all active and indigent members</li>
                            <li>• Summary with totals and breakdown</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            {/* Reports List */}
            <Card>
                <CardHeader>
                    <CardTitle>Generated Reports</CardTitle>
                </CardHeader>
                <CardContent>
                    {reports.length === 0 ? (
                        <p className="text-center text-muted-foreground">No RF1 reports generated yet</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Period</TableHead>
                                        <TableHead>Employees</TableHead>
                                        <TableHead className="text-right">Total Premium</TableHead>
                                        <TableHead>File Size</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reports.map((report) => (
                                        <TableRow key={report.id}>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="font-medium">{report.file_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {new Date(report.month).toLocaleDateString('en-US', {
                                                            year: 'numeric',
                                                            month: 'long',
                                                        })}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="font-medium">{report.total_employees}</p>
                                                    {report.indigent_count > 0 && (
                                                        <p className="text-xs text-amber-600">
                                                            {report.indigent_count} indigent
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                ₱{report.total_premium.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </TableCell>
                                            <TableCell>{(report.file_size / 1024).toFixed(1)} KB</TableCell>
                                            <TableCell>
                                                <Badge className={getStatusColor(report.status)}>
                                                    {report.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-2">
                                                    <Button
                                                        onClick={() => handleDownload(report.id)}
                                                        variant="outline"
                                                        size="sm"
                                                        title="Download RF1 CSV"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </Button>
                                                    {report.status === 'ready' && (
                                                        <Button
                                                            onClick={() => handleSubmit(report.id)}
                                                            disabled={isSubmitting}
                                                            size="sm"
                                                            className="bg-blue-600"
                                                            title="Submit to PhilHealth"
                                                        >
                                                            <Send className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
