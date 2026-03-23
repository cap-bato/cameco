import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Download,
    Eye,
    Send,
    AlertCircle,
    CheckCircle,
    FileText,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { BIRReport, GeneratedBIRReport } from '@/types/bir-pages';

interface BIRReportsListProps {
    reports: BIRReport[];
    generatedReports: GeneratedBIRReport[];
    selectedPeriod: string;
}

/**
 * BIRReportsList Component
 * Displays history of generated and submitted BIR reports
 * Shows report type, status, generation date, and actions
 */
export const BIRReportsList: React.FC<BIRReportsListProps> = ({
    reports,
    generatedReports,
    selectedPeriod,
}) => {
    const [isDownloading, setIsDownloading] = useState<string | null>(null);
    const [selectedReport, setSelectedReport] = useState<GeneratedBIRReport | null>(null);
    const [showDetails, setShowDetails] = useState(false);

    const getStatusBadge = (status: string) => {
        const colors: Record<string, string> = {
            draft: 'bg-gray-100 text-gray-800',
            ready: 'bg-blue-100 text-blue-800',
            generated: 'bg-green-100 text-green-800',
            submitted: 'bg-purple-100 text-purple-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800',
        };

        return (
            <Badge className={colors[status]}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    const getTypeLabel = (type: string) => {
        const labels: Record<string, string> = {
            '1601C': 'Monthly Remittance (1601C)',
            '2316': 'Annual Certificate (2316)',
            '1604C': 'Tax Withheld (1604C)',
            '1604CF': 'Tax Withheld Final (1604CF)',
            Alphalist: 'Employee Alphalist',
            BIR1601E: 'BIR1601E',
        };
        return labels[type] || type;
    };

    const formatFileSize = (bytes: number | null) => {
        if (!bytes) return '-';
        const kb = bytes / 1024;
        return kb > 1024 ? `${(kb / 1024).toFixed(2)} MB` : `${kb.toFixed(2)} KB`;
    };

    // Download handled by anchor tag for browser compatibility

    const handleViewDetails = (report: GeneratedBIRReport) => {
        setSelectedReport(report);
        setShowDetails(true);
    };

    const handleSubmit = (reportId: string | number) => {
        router.post(
            `/payroll/government/bir/submit/${reportId}`,
            {},
            {
                onSuccess: () => {
                    // Reports list will refresh via Inertia
                },
                onError: () => {
                    alert('Failed to submit report');
                },
            }
        );
    };

    const periodReports = reports.filter(
        (r) => String(r.period_id) === selectedPeriod
    );

    const periodGeneratedReports = generatedReports.filter(
        (r) => String(r.file_path).includes(selectedPeriod)
    );

    return (
        <div className="space-y-6">
            {/* Generated Reports Summary */}
            {periodGeneratedReports.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Generated Files</CardTitle>
                        <CardDescription>
                            Reports that have been generated and ready for submission
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {periodGeneratedReports.map((report) => (
                                <Card key={report.id} className="border">
                                    <CardContent className="pt-4">
                                        <div className="space-y-3">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <p className="text-sm font-semibold">
                                                        {getTypeLabel(report.report_type)}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {report.period}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        report.submission_status === 'accepted'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {report.submission_status}
                                                </Badge>
                                            </div>
                                            <div className="text-xs text-gray-600">
                                                <p>File: {report.file_name}</p>
                                                <p>Size: {formatFileSize(report.file_size)}</p>
                                                <p>
                                                    Generated:{' '}
                                                    {new Date(report.generated_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                            <div className="flex gap-2 pt-2">
                                                <a
                                                    href={`/payroll/government/bir/download/${report.id}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    style={{ flex: 1 }}
                                                >
                                                    <Button size="sm" variant="outline" className="w-full">
                                                        <Download className="w-3 h-3 mr-1" />
                                                        Download
                                                    </Button>
                                                </a>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleViewDetails(report)}
                                                >
                                                    <Eye className="w-3 h-3 mr-1" />
                                                    View
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Reports History Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Report History</CardTitle>
                    <CardDescription>
                        All BIR reports for the selected period
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {periodReports.length > 0 ? (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Report Type</TableHead>
                                        <TableHead>Period</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Employees</TableHead>
                                        <TableHead>Total Amount</TableHead>
                                        <TableHead>Generated</TableHead>
                                        <TableHead>Submitted</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {periodReports.map((report) => (
                                        <TableRow key={report.id}>
                                            <TableCell className="font-medium">
                                                <span className="flex items-center gap-2">
                                                    <FileText className="w-4 h-4 text-gray-400" />
                                                    {getTypeLabel(report.type)}
                                                </span>
                                            </TableCell>
                                            <TableCell>{report.period_name}</TableCell>
                                            <TableCell>{getStatusBadge(report.status)}</TableCell>
                                            <TableCell className="text-right">
                                                {report.employee_count}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                ₱{(report.total_amount / 1000).toFixed(1)}K
                                            </TableCell>
                                            <TableCell>
                                                {report.generated_at
                                                    ? new Date(report.generated_at).toLocaleDateString()
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {report.submitted_at ? (
                                                    <div className="flex items-center gap-1">
                                                        <CheckCircle className="w-4 h-4 text-green-600" />
                                                        <span className="text-sm">
                                                            {new Date(report.submitted_at).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-400">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    {report.file_name && (
                                                        <a
                                                            href={`/payroll/government/bir/download/${report.id}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <Button size="sm" variant="ghost">
                                                                <Download className="w-4 h-4" />
                                                            </Button>
                                                        </a>
                                                    )}
                                                    {report.status === 'generated' && !report.submitted_at && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleSubmit(report.id)}
                                                        >
                                                            <Send className="w-4 h-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <AlertCircle className="w-12 h-12 text-gray-300 mx-auto mb-2" />
                            <p className="text-gray-500">No reports generated for this period</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Report Details Dialog */}
            <Dialog open={showDetails} onOpenChange={setShowDetails}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Report Details</DialogTitle>
                        <DialogDescription>
                            {selectedReport?.report_type} - {selectedReport?.period}
                        </DialogDescription>
                    </DialogHeader>

                    {selectedReport && (
                        <div className="grid grid-cols-2 gap-4 py-4">
                            <div>
                                <p className="text-sm text-gray-500">Report Type</p>
                                <p className="font-semibold">{getTypeLabel(selectedReport.report_type)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Period</p>
                                <p className="font-semibold">{selectedReport.period}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">File Name</p>
                                <p className="font-semibold text-sm">{selectedReport.file_name}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">File Size</p>
                                <p className="font-semibold">{formatFileSize(selectedReport.file_size)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Generated Date</p>
                                <p className="font-semibold">
                                    {new Date(selectedReport.generated_at).toLocaleDateString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Status</p>
                                <Badge>{selectedReport.submission_status}</Badge>
                            </div>
                            {selectedReport.rejection_reason && (
                                <div className="col-span-2 bg-red-50 border border-red-200 rounded p-3">
                                    <p className="text-sm text-red-800">{selectedReport.rejection_reason}</p>
                                </div>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDetails(false)}>
                            Close
                        </Button>
                        {selectedReport && (
                            <a
                                href={`/payroll/government/bir/download/${selectedReport.id}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={() => setShowDetails(false)}
                            >
                                <Button>
                                    <Download className="w-4 h-4 mr-2" />
                                    Download
                                </Button>
                            </a>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
};
