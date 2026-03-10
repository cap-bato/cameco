import React, { useState, useEffect } from 'react';
import { Plus, Send, Download, Mail, RefreshCw, AlertCircle, CheckCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { PayslipsList } from '@/components/payroll/payslips-list';
import { PayslipGenerator } from '@/components/payroll/payslip-generator';
import { PayslipDistribution } from '@/components/payroll/payslip-distribution';
import { PayslipPreview } from '@/components/payroll/payslip-preview';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
import type { PayslipsPageProps, PayslipPreviewData, PayslipGenerationRequest, PayslipDistributionRequest } from '@/types/payroll-pages';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payslips', href: '/payroll/payments/payslips' },
];

export default function PayslipsIndex({
    payslips,
    summary,
    filters,
    periods,
    departments,
    distributionMethods,
}: PayslipsPageProps) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const [isGeneratorOpen, setIsGeneratorOpen] = useState(false);
    const [isDistributionOpen, setIsDistributionOpen] = useState(false);
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);
    const [previewData, setPreviewData] = useState<PayslipPreviewData | null>(null);
    const [selectedPayslips, setSelectedPayslips] = useState<number[]>([]);

    // Filters state
    const [search, setSearch] = useState(filters.search || '');
    const [periodId, setPeriodId] = useState(filters.period_id?.toString() || 'all');
    const [departmentId, setDepartmentId] = useState(filters.department_id?.toString() || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [distributionMethod, setDistributionMethod] = useState(filters.distribution_method || 'all');
    
    const hasNotification = !!(flash?.success || flash?.error);
    const isSuccessNotification = !!flash?.success;
    const notificationMessage = (flash?.success || flash?.error || '') as string;

    // Apply filters reactively when any filter changes
    useEffect(() => {
        // Debounce for search field
        const timer = setTimeout(() => {
            router.get(
                '/payroll/payments/payslips',
                {
                    search: search || undefined,
                    period_id: periodId !== 'all' ? periodId : undefined,
                    department_id: departmentId !== 'all' ? departmentId : undefined,
                    status: status !== 'all' ? status : undefined,
                    distribution_method: distributionMethod !== 'all' ? distributionMethod : undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                }
            );
        }, 500); // 500ms debounce

        return () => {
            clearTimeout(timer);
        };
    }, [search, periodId, departmentId, status, distributionMethod]);


    const handleClearFilters = () => {
        setSearch('');
        setPeriodId('all');
        setDepartmentId('all');
        setStatus('all');
        setDistributionMethod('all');
        router.get('/payroll/payments/payslips', {}, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const handleGenerate = (data: PayslipGenerationRequest) => {
        router.post('/payroll/payments/payslips/generate', {
            period_id: data.period_id,
            employee_ids: data.employee_ids,
        }, {
            onSuccess: () => {
                setIsGeneratorOpen(false);
            },
        });
    };

    const handleDistribute = (data: PayslipDistributionRequest) => {
        router.post('/payroll/payments/payslips/distribute', {
            payslip_ids: data.payslip_ids,
            distribution_method: data.distribution_method,
            email_subject: data.email_subject,
            email_message: data.email_message,
        }, {
            onSuccess: () => {
                setIsDistributionOpen(false);
                setSelectedPayslips([]);
            },
        });
    };

    const handleDownload = (id: number) => {
        router.get(`/payroll/payments/payslips/${id}/download`);
    };

    const handleEmail = (id: number) => {
        router.post(`/payroll/payments/payslips/${id}/email`);
    };

    const handleView = (id: number) => {
        // In production, this would fetch preview data via API
        // For now, using a placeholder
        const mockPreview: PayslipPreviewData = {
            employee_id: id,
            employee_number: 'EMP-001',
            employee_name: 'Sample Employee',
            position: 'Position',
            department: 'Department',
            period_name: 'October 2025',
            period_start: '2025-10-01',
            period_end: '2025-10-15',
            pay_date: '2025-10-16',
            earnings: [],
            gross_pay: 0,
            deductions: [],
            total_deductions: 0,
            net_pay: 0,
            ytd_gross: 0,
            ytd_deductions: 0,
            ytd_net: 0,
        };
        setPreviewData(mockPreview);
        setIsPreviewOpen(true);
    };

    const handlePrint = (id: number) => {
        router.get(`/payroll/payments/payslips/${id}/print`);
    };

    const handleBulkDistribute = () => {
        setIsDistributionOpen(true);
    };

    const handleBulkDownload = () => {
        router.post('/payroll/payments/payslips/bulk-download', {
            payslip_ids: selectedPayslips,
        });
    };

    const handleBulkEmail = () => {
        router.post('/payroll/payments/payslips/bulk-email', {
            payslip_ids: selectedPayslips,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslips Management" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Payslips Management</h1>
                        <p className="mt-2 text-gray-600">
                            Generate, distribute, and manage DOLE-compliant employee payslips
                        </p>
                    </div>
                    <Button onClick={() => setIsGeneratorOpen(true)} size="lg">
                        <Plus className="mr-2 h-5 w-5" />
                        Generate Payslips
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Payslips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary.total_payslips}</p>
                            <p className="mt-1 text-xs text-gray-600">All payslips generated</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Distributed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">{summary.distributed}</p>
                            <p className="mt-1 text-xs text-gray-600">
                                {summary.acknowledged} acknowledged
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Generated</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-orange-600">{summary.generated}</p>
                            <p className="mt-1 text-xs text-gray-600">Ready for distribution</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Distribution</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-1 text-xs">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Email:</span>
                                    <span className="font-medium">{summary.total_distribution_email}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Portal:</span>
                                    <span className="font-medium">{summary.total_distribution_portal}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Printed:</span>
                                    <span className="font-medium">{summary.total_distribution_print}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-6">
                            <div className="md:col-span-2">
                                <Label htmlFor="search">Search</Label>
                                <Input
                                    id="search"
                                    placeholder="Employee name or payslip #"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div>
                                <Label htmlFor="period">Period</Label>
                                <Select value={periodId} onValueChange={setPeriodId}>
                                    <SelectTrigger id="period">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Periods</SelectItem>
                                        {periods.map((period) => (
                                            <SelectItem key={period.id} value={period.id.toString()}>
                                                {period.period_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="department">Department</Label>
                                <Select value={departmentId} onValueChange={setDepartmentId}>
                                    <SelectTrigger id="department">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="status">Status</Label>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="generated">Generated</SelectItem>
                                        <SelectItem value="distributed">Distributed</SelectItem>
                                        <SelectItem value="acknowledged">Acknowledged</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="distribution">Distribution</Label>
                                <Select value={distributionMethod} onValueChange={setDistributionMethod}>
                                    <SelectTrigger id="distribution">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Methods</SelectItem>
                                        {distributionMethods.map((method) => (
                                            <SelectItem key={method.id} value={method.id}>
                                                {method.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="mt-4 flex justify-end gap-2">
                            <Button variant="outline" onClick={handleClearFilters}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Clear Filters
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Actions */}
                {selectedPayslips.length > 0 && (
                    <Card className="border-blue-200 bg-blue-50">
                        <CardContent className="flex items-center justify-between py-4">
                            <div>
                                <p className="font-semibold">
                                    {selectedPayslips.length} payslip{selectedPayslips.length > 1 ? 's' : ''} selected
                                </p>
                                <p className="text-sm text-gray-600">Perform bulk actions on selected payslips</p>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" onClick={handleBulkDownload}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download All
                                </Button>
                                <Button variant="outline" size="sm" onClick={handleBulkEmail}>
                                    <Mail className="mr-2 h-4 w-4" />
                                    Email All
                                </Button>
                                <Button size="sm" onClick={handleBulkDistribute}>
                                    <Send className="mr-2 h-4 w-4" />
                                    Distribute
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Payslips Table */}
                <PayslipsList
                    payslips={payslips.data}
                    selectedPayslips={selectedPayslips}
                    onSelectionChange={setSelectedPayslips}
                    onDownload={handleDownload}
                    onEmail={handleEmail}
                    onView={handleView}
                    onPrint={handlePrint}
                />
            </div>

            {/* Modals */}
            <PayslipGenerator
                open={isGeneratorOpen}
                onOpenChange={setIsGeneratorOpen}
                onGenerate={handleGenerate}
                periods={periods}
            />

            <PayslipDistribution
                open={isDistributionOpen}
                onOpenChange={setIsDistributionOpen}
                onDistribute={handleDistribute}
                selectedPayslipIds={selectedPayslips}
                selectedCount={selectedPayslips.length}
            />

            <PayslipPreview
                open={isPreviewOpen}
                onOpenChange={setIsPreviewOpen}
                data={previewData}
                onDownload={() => previewData && handleDownload(Number(previewData.employee_id))}
            />

            {/* Notification Dialog */}
            <AlertDialog open={hasNotification} onOpenChange={() => {}}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <div className="flex items-center gap-3">
                            {isSuccessNotification ? (
                                <CheckCircle className="h-6 w-6 text-green-600" />
                            ) : (
                                <AlertCircle className="h-6 w-6 text-red-600" />
                            )}
                            <AlertDialogTitle className={isSuccessNotification ? 'text-green-700' : 'text-red-700'}>
                                {isSuccessNotification ? 'Success' : 'Error'}
                            </AlertDialogTitle>
                        </div>
                        <AlertDialogDescription>{notificationMessage}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogAction onClick={() => router.reload()}>
                        OK
                    </AlertDialogAction>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
