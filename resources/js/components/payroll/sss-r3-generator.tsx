import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertCircle,
    CheckCircle,
    Clock,
    Download,
    FileText,
    Send,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { SSSR3Report } from '@/types/payroll-pages';

interface SSSR3GeneratorProps {
    reports: SSSR3Report[];
    periods: Array<{ id: string | number; name: string; month: string }>;
}

export function SSSR3Generator({ reports, periods }: SSSR3GeneratorProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<string>(
        periods.length > 0 ? String(periods[0].id) : ''
    );
    const [isLoading, setIsLoading] = useState(false);
    const [confirmSubmitId, setConfirmSubmitId] = useState<string | number | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const currentPeriod = periods.find((p) => String(p.id) === selectedPeriod);
    const currentReport = reports.find((r) => String(r.period_id) === selectedPeriod);

    const handleGenerateR3 = () => {
        if (!currentPeriod) return;
        setIsLoading(true);
        router.post(
            `/payroll/government/sss/generate-r3/${currentPeriod.id}`,
            { month: currentPeriod.month },
            {
                onError: () => setIsLoading(false),
                onSuccess: () => setIsLoading(false),
            }
        );
    };

    const handleDownload = (reportId: string | number) => {
        window.open(`/payroll/government/sss/download-r3/${reportId}`, '_blank');
    };

    const handleSubmit = (reportId: string | number) => {
        setConfirmSubmitId(reportId);
    };

    const confirmSubmit = () => {
        if (!confirmSubmitId) return;
        setIsSubmitting(true);
        router.post(
            `/payroll/government/sss/submit/${confirmSubmitId}`,
            {},
            {
                onSuccess: () => {
                    setConfirmSubmitId(null);
                    setIsSubmitting(false);
                },
                onError: () => {
                    setConfirmSubmitId(null);
                    setIsSubmitting(false);
                },
            }
        );
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'submitted':
            case 'accepted':
                return 'bg-green-100 text-green-800';
            case 'ready':
                return 'bg-blue-100 text-blue-800';
            case 'draft':
                return 'bg-gray-100 text-gray-800';
            case 'rejected':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'submitted':
            case 'accepted':
                return <CheckCircle className="h-4 w-4" />;
            case 'ready':
                return <Clock className="h-4 w-4" />;
            default:
                return <AlertCircle className="h-4 w-4" />;
        }
    };

    return (
        <div className="space-y-6">
            {/* Generation Card */}
            <Card>
                <CardHeader>
                    <CardTitle>SSS R3 Report Generation</CardTitle>
                    <CardDescription>
                        Generate CSV format R3 reports for SSS ERPS portal submission
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-2">Select Period</label>
                            <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a period" />
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
                        <div>
                            <label className="block text-sm font-medium mb-2">Report Format</label>
                            <div className="rounded-md border bg-gray-50 px-3 py-2 text-sm">
                                CSV (Comma-Separated Values)
                            </div>
                        </div>
                    </div>

                    {currentReport && (
                        <Alert>
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Report Already Generated</AlertTitle>
                            <AlertDescription>
                                An R3 report for this period already exists ({currentReport.file_name}).
                                Generating a new one will replace the existing file.
                            </AlertDescription>
                        </Alert>
                    )}

                    {currentPeriod && (
                        <Alert className="border-blue-200 bg-blue-50">
                            <FileText className="h-4 w-4 text-blue-600" />
                            <AlertTitle className="text-blue-900">Period Information</AlertTitle>
                            <AlertDescription className="text-blue-800">
                                Report Month: <strong>{currentPeriod.month}</strong> • Period:{' '}
                                <strong>{currentPeriod.name}</strong>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Button
                        onClick={handleGenerateR3}
                        disabled={isLoading || !currentPeriod}
                        className="w-full"
                    >
                        {isLoading ? 'Generating...' : 'Generate R3 Report'}
                    </Button>
                </CardContent>
            </Card>

            {/* Reports List */}
            <Card>
                <CardHeader>
                    <CardTitle>Generated R3 Reports</CardTitle>
                    <CardDescription>
                        View and manage generated R3 reports for SSS submission
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {reports.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center">
                            <FileText className="mb-3 h-8 w-8 text-muted-foreground" />
                            <p className="font-medium">No reports generated yet</p>
                            <p className="text-sm text-muted-foreground">
                                Generate your first R3 report using the form above
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {reports.map((report) => (
                                <div
                                    key={report.id}
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-gray-400" />
                                            <div>
                                                <p className="font-medium">{report.file_name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Month: {report.month} • Employees:{' '}
                                                    {report.total_employees} • Size:{' '}
                                                    {(report.file_size / 1024).toFixed(1)} KB
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3 pl-4">
                                        <div className="text-right">
                                            <Badge
                                                className={`inline-flex items-center gap-1 ${getStatusColor(report.status)}`}
                                            >
                                                {getStatusIcon(report.status)}
                                                <span>{report.status}</span>
                                            </Badge>
                                            {report.submission_date && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Submitted:{' '}
                                                    {new Date(report.submission_date).toLocaleDateString()}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex gap-2">
                                            <Button
                                                onClick={() => handleDownload(report.id)}
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Download className="h-4 w-4" />
                                            </Button>
                                            {report.status === 'ready' && (
                                                <Button
                                                    onClick={() => handleSubmit(report.id)}
                                                    variant="default"
                                                    size="sm"
                                                >
                                                    <Send className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* R3 Format Info */}
            <Card>
                <CardHeader>
                    <CardTitle>R3 Report Format</CardTitle>
                    <CardDescription>CSV file structure for SSS ERPS submission</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3 text-sm">
                        <div>
                            <p className="font-medium mb-2">File Format: Comma-Separated Values (CSV)</p>
                            <p className="text-muted-foreground mb-3">
                                The R3 report is generated in CSV format compatible with SSS ERPS portal
                                upload requirements.
                            </p>
                        </div>
                        <div>
                            <p className="font-medium mb-2">Data Columns:</p>
                            <ul className="space-y-1 text-muted-foreground">
                                <li>• Sequence Number</li>
                                <li>• SSS Number (9-digit employee identifier)</li>
                                <li>• Employee Name</li>
                                <li>• Monthly Compensation</li>
                                <li>• Employee Share (EE) - 3%</li>
                                <li>• Employer Share (ER) - 3%</li>
                                <li>• EC Share (Employees Compensation) - 1%</li>
                                <li>• Total Contribution</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Submit Confirmation Dialog */}
            {confirmSubmitId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold">Submit R3 Report</h3>
                        <p className="mt-2 text-sm text-gray-600">
                            Submit this R3 report to SSS? This action cannot be undone.
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setConfirmSubmitId(null)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </Button>
                            <Button onClick={confirmSubmit} disabled={isSubmitting}>
                                {isSubmitting ? 'Submitting...' : 'Submit'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}