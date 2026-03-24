import React, { useState } from 'react';
import { router, Head } from '@inertiajs/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertCircle,
    CheckCircle,
    Download,
    FileText,
    RefreshCw,
    TrendingUp,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import { PhilHealthContributionsTable } from '@/components/payroll/philhealth-contributions-table';
import { PhilHealthRF1Generator } from '@/components/payroll/philhealth-rf1-generator';
import { PhilHealthRemittanceTracker } from '@/components/payroll/philhealth-remittance-tracker';
import type { BreadcrumbItem } from '@/types';

interface Period {
    id: string | number;
    name: string;
    month: string;
    start_date: string;
    end_date: string;
    status: string;
}

interface Contribution {
    id: string | number;
    employee_id: string | number;
    employee_name: string;
    employee_number: string;
    philhealth_number: string;
    period_id: string | number;
    month: string;
    monthly_basic: number;
    employee_premium: number;
    employer_premium: number;
    total_premium: number;
    is_processed: boolean;
    is_remitted: boolean;
    is_indigent: boolean;
    created_at: string;
    updated_at: string;
}

interface Remittance {
    id: string | number;
    period_id: string | number;
    month: string;
    remittance_amount: number;
    due_date: string;
    payment_date: string | null;
    payment_reference: string | null;
    status: 'pending' | 'paid' | 'partially_paid' | 'overdue';
    has_penalty: boolean;
    penalty_amount: number;
    penalty_reason?: string;
    contributions: {
        employee_share: number;
        employer_share: number;
    };
    created_at: string;
    updated_at: string;
}

interface RF1Report {
    id: string | number;
    period_id: string | number;
    month: string;
    file_name: string;
    file_path: string;
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
    rejection_reason?: string | null;
    created_at: string;
    updated_at: string;
}

interface PhilHealthIndexPageProps {
    contributions: Contribution[];
    periods: Period[];
    summary: {
        total_employees: number;
        total_monthly_basic: number;
        total_employee_premium: number;
        total_employer_premium: number;
        total_premium: number;
        last_remittance_date: string | null;
        next_due_date: string;
        pending_remittances: number;
        indigent_members: number;
    };
    remittances: Remittance[];
    rf1_reports: RF1Report[];
}

export default function PhilHealthIndex({
    contributions,
    periods,
    summary,
    remittances,
    rf1_reports,
}: PhilHealthIndexPageProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<string>(
        periods.length > 0 ? String(periods[0].id) : ''
    );
    const [activeTab, setActiveTab] = useState('contributions');
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{
        type: 'success' | 'error' | 'info';
        message: string;
    } | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Payroll',
            href: '/payroll/dashboard',
        },
        {
            title: 'Government',
            href: '/payroll/government',
        },
        {
            title: 'PhilHealth Contributions',
            href: '/payroll/government/philhealth',
        },
    ];

    const currentPeriod = periods.find((p) => String(p.id) === selectedPeriod);
    const periodContributions = contributions.filter(
        (c) => String(c.period_id) === selectedPeriod
    );

    const handleRefresh = () => {
        setIsLoading(true);
        router.get(`/payroll/government/philhealth`, {}, {
            onSuccess: () => {
                setStatusMessage({
                    type: 'success',
                    message: 'Page refreshed successfully',
                });
                setTimeout(() => setStatusMessage(null), 3000);
            },
            onError: () => {
                setStatusMessage({
                    type: 'error',
                    message: 'Failed to refresh page',
                });
            },
            onFinish: () => setIsLoading(false),
        });
    };

const handleDownloadContributions = () => {
    if (currentPeriod) {
        window.open(
            `/payroll/government/philhealth/download-contributions/${currentPeriod.id}`,
            '_blank'
        );
    }
};

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PhilHealth Contributions" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">PhilHealth Contributions</h1>
                        <p className="text-muted-foreground">
                            Manage PhilHealth insurance contributions and remittances for Cathay Metal Corporation
                        </p>
                    </div>
                    <Button
                        onClick={handleRefresh}
                        variant="outline"
                        disabled={isLoading}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        {isLoading ? 'Refreshing...' : 'Refresh'}
                    </Button>
                </div>

                {/* Status Message */}
                {statusMessage && (
                    <Alert className={`border-l-4 ${
                        statusMessage.type === 'success'
                            ? 'border-green-400 bg-green-50'
                            : statusMessage.type === 'error'
                            ? 'border-red-400 bg-red-50'
                            : 'border-blue-400 bg-blue-50'
                    }`}>
                        {statusMessage.type === 'success' ? (
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        ) : (
                            <AlertCircle className="h-4 w-4 text-blue-600" />
                        )}
                        <AlertDescription className={`${
                            statusMessage.type === 'success'
                                ? 'text-green-700'
                                : 'text-blue-700'
                        }`}>
                            {statusMessage.message}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Total Employees</p>
                                <p className="text-2xl font-bold">{summary.total_employees}</p>
                                <p className="text-xs text-muted-foreground">
                                    {summary.indigent_members > 0 && `${summary.indigent_members} indigent members`}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Monthly Basic</p>
                                <p className="text-2xl font-bold">
                                    ₱{(summary.total_monthly_basic / 1000000).toFixed(2)}M
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Total base salary
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Total Premium</p>
                                <p className="text-2xl font-bold text-blue-600">
                                    ₱{(summary.total_premium / 1000).toFixed(0)}k
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    EE (2.5%) + ER (2.5%)
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Pending Remittances</p>
                                <p className="text-2xl font-bold text-orange-600">
                                    {summary.pending_remittances}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Due by {new Date(summary.next_due_date).toLocaleDateString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Period Selector */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-end gap-4">
                            <div className="flex-1">
                                <label className="block text-sm font-medium mb-2">Select Period</label>
                                <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {periods.map((period) => (
                                            <SelectItem key={period.id} value={String(period.id)}>
                                                {period.name} ({period.month})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            {currentPeriod && (
                                <div className="text-sm">
                                    <p className="text-muted-foreground">Status</p>
                                    <p className="font-semibold capitalize">{currentPeriod.status}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="contributions" className="gap-2">
                            <TrendingUp className="h-4 w-4" />
                            Contributions
                        </TabsTrigger>
                        <TabsTrigger value="rf1-report" className="gap-2">
                            <FileText className="h-4 w-4" />
                            RF1 Report
                        </TabsTrigger>
                        <TabsTrigger value="remittance" className="gap-2">
                            <Download className="h-4 w-4" />
                            Remittances
                        </TabsTrigger>
                    </TabsList>

                    {/* Contributions Tab */}
                    <TabsContent value="contributions" className="space-y-6">
                        <Alert className="border-blue-200 bg-blue-50">
                            <FileText className="h-4 w-4 text-blue-600" />
                            <AlertTitle className="text-blue-900">Premium Calculation</AlertTitle>
                            <AlertDescription className="text-blue-800">
                                PhilHealth premium is 5% of monthly basic salary:
                                Employee Premium (EE) 2.5% + Employer Premium (ER) 2.5% (Max: ₱5,000/month)
                            </AlertDescription>
                        </Alert>

                        <PhilHealthContributionsTable
                            contributions={periodContributions}
                            period={currentPeriod?.name}
                            onDownload={handleDownloadContributions}
                        />
                    </TabsContent>

                    {/* RF1 Report Tab */}
                    <TabsContent value="rf1-report">
                        <PhilHealthRF1Generator
                            reports={rf1_reports}
                            periods={periods}
                        />
                    </TabsContent>

                    {/* Remittance Tab */}
                    <TabsContent value="remittance">
                        <PhilHealthRemittanceTracker remittances={remittances} />
                    </TabsContent>
                </Tabs>

                {/* Quick Reference */}
                <Card>
                    <CardHeader>
                        <CardTitle>PhilHealth System Overview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-3">
                            <div>
                                <h4 className="font-medium mb-2">Premium Rates</h4>
                                <ul className="space-y-1 text-sm text-muted-foreground">
                                    <li>• Employee Premium (EE): 2.5%</li>
                                    <li>• Employer Premium (ER): 2.5%</li>
                                    <li>• Total Premium: 5%</li>
                                    <li className="font-semibold text-gray-900 mt-2">Maximum: ₱5,000/month</li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-medium mb-2">Remittance Schedule</h4>
                                <ul className="space-y-1 text-sm text-muted-foreground">
                                    <li>• Frequency: Monthly</li>
                                    <li>• Due Date: 10th of next month</li>
                                    <li>• Portal: PhilHealth EPRS</li>
                                    <li className="font-semibold text-gray-900 mt-2">Format: RF1 (CSV)</li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-medium mb-2">Last Submission</h4>
                                <ul className="space-y-1 text-sm text-muted-foreground">
                                    <li>• Date: {summary.last_remittance_date ? new Date(summary.last_remittance_date).toLocaleDateString() : 'Not submitted'}</li>
                                    <li>• Status: {summary.last_remittance_date ? 'Submitted' : 'Pending'}</li>
                                    <li>• Next Due: {new Date(summary.next_due_date).toLocaleDateString()}</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
