import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Download, CheckCircle, AlertCircle, Zap } from "lucide-react";
import { useState } from "react";
import type { PagIbigPeriod, PagIbigMCRFReport } from "@/types/payroll-pages";

interface PagIbigMCRFGeneratorProps {
    periods: PagIbigPeriod[];
    mcrf_reports: PagIbigMCRFReport[];
    onGenerateMCRF: (periodId: number) => void;
    onDownloadMCRF: (reportId: number) => void;
}

export default function PagIbigMCRFGenerator({
    periods,
    mcrf_reports,
    onGenerateMCRF,
    onDownloadMCRF,
}: PagIbigMCRFGeneratorProps) {
    const [selectedPeriodId, setSelectedPeriodId] = useState<string>(periods[0]?.id.toString() || "");
    const [isGenerating, setIsGenerating] = useState(false);

    const handleGenerate = async () => {
        setIsGenerating(true);
        try {
            await onGenerateMCRF(parseInt(selectedPeriodId));
        } finally {
            setIsGenerating(false);
        }
    };

    const selectedPeriod = periods.find((p) => p.id.toString() === selectedPeriodId);

    const getStatusColor = (status: string) => {
        switch (status) {
            case "draft":
                return "bg-gray-100 text-gray-800";
            case "ready":
                return "bg-blue-100 text-blue-800";
            case "submitted":
                return "bg-yellow-100 text-yellow-800";
            case "accepted":
                return "bg-green-100 text-green-800";
            case "rejected":
                return "bg-red-100 text-red-800";
            default:
                return "bg-gray-100 text-gray-800";
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case "accepted":
                return <CheckCircle className="w-4 h-4" />;
            case "rejected":
                return <AlertCircle className="w-4 h-4" />;
            case "submitted":
                return <Zap className="w-4 h-4" />;
            default:
                return null;
        }
    };

    return (
        <div className="space-y-6">
            {/* Generation Section */}
            <Card>
                <CardHeader>
                    <CardTitle>MCRF Report Generator</CardTitle>
                    <CardDescription>Generate Monthly Contribution Report Form for Pag-IBIG eSRS submission</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="text-sm font-medium block mb-2">Select Period</label>
                            <Select value={selectedPeriodId} onValueChange={setSelectedPeriodId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a period" />
                                </SelectTrigger>
                                <SelectContent>
                                    {periods.map((period) => (
                                        <SelectItem key={period.id} value={period.id.toString()}>
                                            {period.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {selectedPeriod && (
                            <>
                                <div>
                                    <label className="text-sm font-medium block mb-2">Period Details</label>
                                    <div className="text-sm space-y-1">
                                        <div className="font-medium text-sm">{selectedPeriod.start_date}</div>
                                        <div className="text-xs text-muted-foreground">to {selectedPeriod.end_date}</div>
                                        <Badge variant="outline" className="mt-2">{selectedPeriod.status}</Badge>
                                    </div>
                                </div>

                                <div className="flex items-end">
                                    <Button
                                        onClick={handleGenerate}
                                        disabled={isGenerating}
                                        className="w-full"
                                    >
                                        {isGenerating ? "Generating..." : "Generate MCRF"}
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded p-3 text-sm">
                        <p className="font-medium mb-1">MCRF Information:</p>
                        <ul className="space-y-1 text-muted-foreground text-xs">
                            <li>• MCRF = Monthly Contribution Report Form</li>
                            <li>• Submitted to Pag-IBIG eSRS portal within 30 days of month-end</li>
                            <li>• Contains all contributions, loans, and member information</li>
                            <li>• Required for valid contribution remittance</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            {/* Reports List */}
            <Card>
                <CardHeader>
                    <CardTitle>Generated MCRF Reports</CardTitle>
                    <CardDescription>View and manage submitted reports</CardDescription>
                </CardHeader>
                <CardContent>
                    {mcrf_reports.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            <p>No MCRF reports generated yet</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Period</TableHead>
                                        <TableHead>File Name</TableHead>
                                        <TableHead className="text-center">Employees</TableHead>
                                        <TableHead className="text-right">Total Contrib</TableHead>
                                        <TableHead className="text-center">With Loans</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                        <TableHead className="text-center">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {mcrf_reports.map((report) => (
                                        <TableRow key={report.id}>
                                            <TableCell className="font-medium">{report.month}</TableCell>
                                            <TableCell className="font-mono text-sm">{report.file_name}</TableCell>
                                            <TableCell className="text-center">{report.total_employees}</TableCell>
                                            <TableCell className="text-right">
                                                ₱{report.total_contribution.toFixed(2)}
                                            </TableCell>
                                            <TableCell className="text-center text-blue-600 font-medium">
                                                {report.employees_with_loans}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge className={getStatusColor(report.status)}>
                                                    <span className="flex items-center gap-1">
                                                        {getStatusIcon(report.status)}
                                                        {report.status.charAt(0).toUpperCase() + report.status.slice(1)}
                                                    </span>
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex gap-2 justify-center">
                                                    {report.status !== "submitted" && report.status !== "accepted" && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => onDownloadMCRF(Number(report.id))}
                                                                title="Download report"
                                                            >
                                                                <Download className="w-4 h-4" />
                                                            </Button>
                                                        </>
                                                    )}
                                                    {(report.status === "submitted" || report.status === "accepted") && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => onDownloadMCRF(Number(report.id))}
                                                            title="Download report"
                                                        >
                                                            <Download className="w-4 h-4" />
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

                    {/* Reports Summary */}
                    {mcrf_reports.length > 0 && (
                        <div className="mt-6 pt-4 border-t space-y-2">
                            <div className="flex justify-between text-sm">
                                <span>Total Reports:</span>
                                <span className="font-semibold">{mcrf_reports.length}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span>Submitted:</span>
                                <span className="font-semibold">
                                    {mcrf_reports.filter((r) => r.status === "submitted" || r.status === "accepted").length}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span>Ready to Submit:</span>
                                <span className="font-semibold">
                                    {mcrf_reports.filter((r) => r.status === "ready" || r.status === "draft").length}
                                </span>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Format Specification */}
            <Card className="bg-green-50 border-green-200">
                <CardHeader>
                    <CardTitle className="text-sm">MCRF File Format</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <p className="text-muted-foreground">
                        MCRF reports are formatted as CSV files compatible with Pag-IBIG eSRS submission system. Files contain:
                    </p>
                    <ul className="list-disc list-inside space-y-1 text-muted-foreground text-xs">
                        <li>Employee information (name, ID, Pag-IBIG number)</li>
                        <li>Monthly compensation and contribution breakdown</li>
                        <li>Employee rate information (1% or 2%)</li>
                        <li>Active loan deduction indicators</li>
                        <li>Report summary with totals and employee counts</li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    );
}
