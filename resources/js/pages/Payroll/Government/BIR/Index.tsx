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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertCircle,
    CheckCircle,
    FileText,
    RefreshCw,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import { BIRReportsList } from '@/components/payroll/bir-reports-list';
import { BIR1601CGenerator } from '@/components/payroll/bir-1601c-generator';
import { BIR2316Generator } from '@/components/payroll/bir-2316-generator';
import { BIRAlphaListGenerator } from '@/components/payroll/bir-alphalist-generator';
import { BIRPageProps } from '@/types/bir-pages';

/**
 * BIR Reports Page
 * Main interface for Bureau of Internal Revenue (BIR) reporting
 * Supports: 1601C (monthly), 2316 (annual), Alphalist (DAT format)
 */
import { BIR1601CEmployee, BIR2316Certificate } from '@/types/bir-pages';

import { AlphalistEmployee } from '@/types/bir-pages';

interface BIRIndexProps extends BIRPageProps {
    bir_employees: BIR1601CEmployee[];
    bir_2316_certificates: BIR2316Certificate[];
    bir_alphalist_employees: AlphalistEmployee[];
}

export default function BIRIndex({ reports, periods, summary, generated_reports, bir_employees, bir_2316_certificates, bir_alphalist_employees }: BIRIndexProps) {
    const [selectedPeriod, setSelectedPeriod] = useState<string>(
        periods.length > 0 ? String(periods[0].id) : ''
    );
    const [activeTab, setActiveTab] = useState('reports-list');
    const [isLoading, setIsLoading] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{
        type: 'success' | 'error' | 'info';
        message: string;
    } | null>(null);

    const currentPeriod = periods.find((p) => String(p.id) === selectedPeriod);

    const handleRefreshReports = () => {
        setIsLoading(true);
        router.get(`/payroll/government/bir`, {}, {
            onSuccess: () => {
                setStatusMessage({
                    type: 'success',
                    message: 'Reports refreshed successfully',
                });
                setTimeout(() => setStatusMessage(null), 3000);
            },
            onError: () => {
                setStatusMessage({
                    type: 'error',
                    message: 'Failed to refresh reports',
                });
            },
            onFinish: () => setIsLoading(false),
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'submitted':
            case 'approved':
                return 'text-green-600';
            case 'draft':
            case 'ready':
                return 'text-blue-600';
            case 'rejected':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Payroll', href: '/payroll/dashboard' },
            { title: 'Government', href: '#' },
            { title: 'BIR Reports', href: '/payroll/government/bir' },
        ]}>
            <Head title="BIR Reports" />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <h1 className="text-3xl font-bold">BIR Reports</h1>
                    </div>
                    <p className="text-muted-foreground">
                        Manage BIR reports including 1601C, 2316, and Alphalist
                    </p>
                </div>
                {/* Status Alert */}
                {statusMessage && (
                    <Alert variant={statusMessage.type === 'error' ? 'destructive' : 'default'}>
                        {statusMessage.type === 'error' ? (
                            <AlertCircle className="h-4 w-4" />
                        ) : (
                            <CheckCircle className="h-4 w-4" />
                        )}
                        <AlertTitle>
                            {statusMessage.type === 'error' ? 'Error' : 'Success'}
                        </AlertTitle>
                        <AlertDescription>{statusMessage.message}</AlertDescription>
                    </Alert>
                )}

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Total Employees</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_employees}</div>
                            <p className="text-xs text-gray-500">Current reporting cycle</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Gross Compensation
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                ₱{(summary.total_gross_compensation / 1000).toFixed(1)}K
                            </div>
                            <p className="text-xs text-gray-500">All employees</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Withholding Tax
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                ₱{(summary.total_withholding_tax / 1000).toFixed(1)}K
                            </div>
                            <p className="text-xs text-gray-500">Income tax withheld</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Reports Generated</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.reports_generated_count}</div>
                            <p className="text-xs text-gray-500">
                                Next deadline: {summary.next_deadline}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Period Selector & Controls */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <CardTitle>Reporting Period</CardTitle>
                                <CardDescription>
                                    Select period and view BIR reports
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                                    <SelectTrigger className="w-64">
                                        <SelectValue placeholder="Select period" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {periods.map((period) => (
                                            <SelectItem key={period.id} value={String(period.id)}>
                                                {period.name}
                                                {period.status === 'submitted' && ' ✓'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={handleRefreshReports}
                                    disabled={isLoading}
                                >
                                    <RefreshCw
                                        className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`}
                                    />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    {currentPeriod && (
                        <CardContent>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p className="text-gray-500">Period Name</p>
                                    <p className="font-semibold">{currentPeriod.name}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Start Date</p>
                                    <p className="font-semibold">{currentPeriod.start_date}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">End Date</p>
                                    <p className="font-semibold">{currentPeriod.end_date}</p>
                                </div>
                                <div>
                                    <p className="text-gray-500">Status</p>
                                    <p className={`font-semibold ${getStatusColor(currentPeriod.status)}`}>
                                        {currentPeriod.status}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    )}
                </Card>

                {/* Tabs for Different BIR Forms */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="reports-list" className="flex items-center gap-2">
                            <FileText className="w-4 h-4" />
                            <span className="hidden sm:inline">Reports List</span>
                        </TabsTrigger>
                        <TabsTrigger value="1601c" className="flex items-center gap-2">
                            <FileText className="w-4 h-4" />
                            <span className="hidden sm:inline">Form 1601C</span>
                        </TabsTrigger>
                        <TabsTrigger value="2316" className="flex items-center gap-2">
                            <FileText className="w-4 h-4" />
                            <span className="hidden sm:inline">Form 2316</span>
                        </TabsTrigger>
                        <TabsTrigger value="alphalist" className="flex items-center gap-2">
                            <FileText className="w-4 h-4" />
                            <span className="hidden sm:inline">Alphalist</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Reports List Tab */}
                    <TabsContent value="reports-list" className="space-y-4">
                        <BIRReportsList
                            reports={reports}
                            generatedReports={generated_reports}
                            selectedPeriod={selectedPeriod}
                        />
                    </TabsContent>

                    {/* 1601C Tab */}
                    <TabsContent value="1601c" className="space-y-4">
                        <BIR1601CGenerator
                            period={currentPeriod}
                            periodId={selectedPeriod}
                            employees={bir_employees}
                        />
                    </TabsContent>

                    {/* 2316 Tab */}
                    <TabsContent value="2316" className="space-y-4">
                        <BIR2316Generator
                            period={currentPeriod}
                            periodId={selectedPeriod}
                            certificates={bir_2316_certificates}
                        />
                    </TabsContent>

                    {/* Alphalist Tab */}
                    <TabsContent value="alphalist" className="space-y-4">
                        <BIRAlphaListGenerator
                            period={currentPeriod}
                            periodId={selectedPeriod}
                            employees={bir_alphalist_employees}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

