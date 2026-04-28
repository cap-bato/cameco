import { Head } from "@inertiajs/react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { AlertCircle } from "lucide-react";
import { useState } from "react";
import { router } from "@inertiajs/react";
import PagIbigContributionsTable from "@/components/payroll/government/pagibig-contributions-table";
import PagIbigMCRFGenerator from "@/components/payroll/government/pagibig-mcrf-generator";
import PagIbigRemittanceTracker from "@/components/payroll/government/pagibig-remittance-tracker";
import PagIbigLoanDeductions from "@/components/payroll/government/pagibig-loan-deductions";
import type {
    PagIbigPeriod,
    PagIbigContribution,
    PagIbigMCRFReport,
    PagIbigRemittance,
    PagIbigLoanDeduction,
} from "@/types/payroll-pages";
import AppLayout from "@/layouts/app-layout";

interface PagIbigIndexProps {
    contributions: PagIbigContribution[];
    periods: PagIbigPeriod[];
    summary: {
        total_employees: number;
        total_monthly_compensation: number;
        total_employee_contribution: number;
        total_employer_contribution: number;
        total_contribution: number;
        total_loan_deductions: number;
        last_remittance_date: string | null;
        next_due_date: string;
        pending_remittances: number;
    };
    remittances: PagIbigRemittance[];
    mcrf_reports: PagIbigMCRFReport[];
    loan_deductions: PagIbigLoanDeduction[];
}

export default function PagIbigIndex({
    contributions,
    periods,
    summary,
    remittances,
    mcrf_reports,
    loan_deductions,
}: PagIbigIndexProps) {
    const [selectedPeriodId, setSelectedPeriodId] = useState<string>(periods[0]?.id.toString() || "");
    const selectedPeriod = periods.find((p) => p.id.toString() === selectedPeriodId) || periods[0];
    const selectedContributions = contributions.filter((c) => c.period_id.toString() === selectedPeriodId);

    const breadcrumb = [
        { title: "Payroll", href: "/payroll" },
        { title: "Government", href: "/payroll/government" },
        { title: "Pag-IBIG", href: "/payroll/government/pagibig" },
    ];

    const handleDownloadContributions = () => {
        if (selectedPeriod) {
            window.open(
                `/payroll/government/pagibig/download-contributions/${selectedPeriod.id}`,
                '_blank'
            );
        }
    };

    const handleGenerateMCRF = (periodId: number) => {
        const period = periods.find((p) => Number(p.id) === periodId);
        router.post(
            `/payroll/government/pagibig/generate-mcrf/${periodId}`,
            { month: period?.month ?? '' },
            { onSuccess: () => router.reload() }
        );
    };

    const handleDownloadMCRF = (reportId: number) => {
        window.open(`/payroll/government/pagibig/download-mcrf/${reportId}`, '_blank');
    };


    const handleSubmitMCRF = (reportId: number) => {
        router.post(`/payroll/government/pagibig/submit/${reportId}`, {}, {
            onSuccess: () => router.reload(),
        });
    };
    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Pag-IBIG Contributions" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <h1 className="text-3xl font-bold">Pag-IBIG Contributions</h1>
                    </div>
                    <p className="text-muted-foreground">
                        Manage Pag-IBIG contributions, MCRF reports, remittances, and loan deductions
                    </p>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Employees
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_employees}</div>
                            <p className="text-xs text-muted-foreground mt-1">Active members</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Monthly Compensation
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">₱{(summary.total_monthly_compensation / 1000).toFixed(0)}K</div>
                            <p className="text-xs text-muted-foreground mt-1">Aggregate payroll</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Monthly Contribution
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                ₱{summary.total_contribution.toFixed(2)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">EE + ER combined</p>
                        </CardContent>
                    </Card>

                    <Card className={summary.pending_remittances > 0 ? "border-amber-200 bg-amber-50" : ""}>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Next Due Date
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold">
                                {new Date(summary.next_due_date).toLocaleDateString("en-PH", {
                                    month: "short",
                                    day: "numeric",
                                })}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {summary.pending_remittances > 0
                                    ? `${summary.pending_remittances} pending`
                                    : "All clear"}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Period Selector */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Period Selection</CardTitle>
                                <CardDescription>Filter data by contribution period</CardDescription>
                            </div>
                            <Select value={selectedPeriodId} onValueChange={setSelectedPeriodId}>
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="Select period" />
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
                    </CardHeader>
                    <CardContent>
                        {selectedPeriod && (
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="text-xs font-medium text-muted-foreground">Start Date</label>
                                    <p className="text-sm font-medium">{selectedPeriod.start_date}</p>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-muted-foreground">End Date</label>
                                    <p className="text-sm font-medium">{selectedPeriod.end_date}</p>
                                </div>
                                <div>
                                    <label className="text-xs font-medium text-muted-foreground">Status</label>
                                    <Badge className="mt-1">
                                        {selectedPeriod.status.charAt(0).toUpperCase() + selectedPeriod.status.slice(1)}
                                    </Badge>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Alerts */}
                {summary.pending_remittances > 0 && (
                    <Card className="border-amber-200 bg-amber-50">
                        <CardHeader className="pb-3">
                            <div className="flex items-center gap-2">
                                <AlertCircle className="w-5 h-5 text-amber-600" />
                                <CardTitle className="text-base">Pending Remittances</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="text-sm text-amber-800">
                            <p>
                                {summary.pending_remittances} remittance{summary.pending_remittances !== 1 ? "s" : ""} due by{" "}
                                {new Date(summary.next_due_date).toLocaleDateString("en-PH")}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Tabs */}
                <Tabs defaultValue="contributions" className="w-full">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="contributions">Contributions</TabsTrigger>
                        <TabsTrigger value="mcrf">MCRF Report</TabsTrigger>
                        <TabsTrigger value="remittances">Remittances</TabsTrigger>
                        <TabsTrigger value="loans">Loans</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Contributions */}
                    <TabsContent value="contributions" className="space-y-4">
                        {selectedPeriod && (
                            <PagIbigContributionsTable
                                contributions={selectedContributions}
                                period={selectedPeriod}
                                onDownloadContributions={handleDownloadContributions}
                            />
                        )}
                    </TabsContent>

                    {/* Tab 2: MCRF Report */}
                    <TabsContent value="mcrf" className="space-y-4">
                        <PagIbigMCRFGenerator
                            periods={periods}
                            mcrf_reports={mcrf_reports}
                            onGenerateMCRF={handleGenerateMCRF}
                            onDownloadMCRF={handleDownloadMCRF}
                            onSubmitMCRF={handleSubmitMCRF}
                        />
                    </TabsContent>

                    {/* Tab 3: Remittances */}
                    <TabsContent value="remittances" className="space-y-4">
                        <PagIbigRemittanceTracker remittances={remittances} />
                    </TabsContent>

                    {/* Tab 4: Loans */}
                    <TabsContent value="loans" className="space-y-4">
                        <PagIbigLoanDeductions loans={loan_deductions} />
                    </TabsContent>
                </Tabs>

                {/* Quick Reference */}
                <Card className="bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-200">
                    <CardHeader>
                        <CardTitle className="text-sm">Pag-IBIG Quick Reference</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p className="font-medium">Rate Structure</p>
                                <p className="text-muted-foreground text-xs">
                                    EE: 1-2% | ER: 2% | Max: ₱100/each
                                </p>
                            </div>
                            <div>
                                <p className="font-medium">File Format</p>
                                <p className="text-muted-foreground text-xs">
                                    MCRF CSV for Pag-IBIG eSRS portal
                                </p>
                            </div>
                            <div>
                                <p className="font-medium">Loan Types</p>
                                <p className="text-muted-foreground text-xs">
                                    Housing, Calamity, Educational, Other
                                </p>
                            </div>
                            <div>
                                <p className="font-medium">Due Date</p>
                                <p className="text-muted-foreground text-xs">
                                    10th of following month
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
