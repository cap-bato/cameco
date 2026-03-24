import React, { useState, useEffect } from 'react';
import { Plus, Send, Download, RefreshCw, AlertCircle, CheckCircle } from 'lucide-react';
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
    periods = [],
    departments = [],
}: PayslipsPageProps) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const [isGeneratorOpen, setIsGeneratorOpen] = useState(false);
    const [isDistributionOpen, setIsDistributionOpen] = useState(false);
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);
    const [previewData, setPreviewData] = useState<PayslipPreviewData | null>(null);
    const [selectedPayslips, setSelectedPayslips] = useState<number[]>([]);

    const [search, setSearch] = useState(filters.search || '');
    const [periodId, setPeriodId] = useState(filters.period_id?.toString() || 'all');
    const [departmentId, setDepartmentId] = useState(filters.department_id?.toString() || 'all');
    const [status, setStatus] = useState(filters.status || 'all');

    const hasNotification = !!(flash?.success || flash?.error);
    const isSuccessNotification = !!flash?.success;
    const notificationMessage = (flash?.success || flash?.error || '') as string;

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                '/payroll/payments/payslips',
                {
                    search: search || undefined,
                    period_id: periodId !== 'all' ? periodId : undefined,
                    department_id: departmentId !== 'all' ? departmentId : undefined,
                    status: status !== 'all' ? status : undefined,
                },
                { preserveState: true, preserveScroll: true }
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [search, periodId, departmentId, status]);

    const handleClearFilters = () => {
        setSearch('');
        setPeriodId('all');
        setDepartmentId('all');
        setStatus('all');
        router.get('/payroll/payments/payslips', {}, { preserveState: false, preserveScroll: true });
    };

const handleGenerate = (data: PayslipGenerationRequest) => {
    router.post('/payroll/payments/payslips/generate', {
        period_id: data.period_id,
        employee_ids: data.employee_ids,
        regenerate: data.regenerate,
    }, {
        onSuccess: () => {
            setIsGeneratorOpen(false);
        },
        onError: () => {
            // keep modal open so user can fix and retry
        },
    });
};

    const handleDistribute = (data: PayslipDistributionRequest) => {
        router.post('/payroll/payments/payslips/distribute', {
            payslip_ids: data.payslip_ids,
            distribution_method: 'portal',
        }, {
            onSuccess: () => {
                setIsDistributionOpen(false);
                setSelectedPayslips([]);
            },
        });
    };

    const handleDownload = (id: number) => {
        window.open(`/payroll/payments/payslips/${id}/download`, '_blank');
    };

    const handleView = async (id: number) => {
        try {
            const response = await fetch(`/payroll/payments/payslips/${id}/preview`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Failed to fetch preview');
            const data: PayslipPreviewData = await response.json();
            setPreviewData(data);
            setIsPreviewOpen(true);
        } catch (error) {
            console.error('Preview error:', error);
        }
    };

    const [notificationDismissed, setNotificationDismissed] = useState(false);

    // Reset dismissed state when new flash arrives
    useEffect(() => {
        if (flash?.success || flash?.error) {
            setNotificationDismissed(false);
        }
    }, [flash]);

    const showNotification = hasNotification && !notificationDismissed;


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslips Management" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Payslips Management</h1>
                        <p className="mt-2 text-gray-600">
                            Generate and distribute DOLE-compliant employee payslips via the employee portal
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
                            <p className="mt-1 text-xs text-gray-600">{summary.acknowledged} acknowledged</p>
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
                            <CardTitle className="text-sm font-medium text-gray-600">Portal</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-blue-600">{summary.total_distribution_portal}</p>
                            <p className="mt-1 text-xs text-gray-600">Via employee portal</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
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
                                    <SelectTrigger id="period"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Periods</SelectItem>
                                        {(periods ?? []).map((period) => (
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
                                    <SelectTrigger id="department"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                    {(departments ?? []).map((dept) => (
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
                                    <SelectTrigger id="status"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="generated">Generated</SelectItem>
                                        <SelectItem value="distributed">Distributed</SelectItem>
                                        <SelectItem value="acknowledged">Acknowledged</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="mt-4 flex justify-end">
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
                                <p className="text-sm text-gray-600">Release selected payslips to the employee portal</p>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" onClick={() => {
                                    selectedPayslips.forEach(id => handleDownload(id));
                                }}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download All
                                </Button>
                                <Button size="sm" onClick={() => setIsDistributionOpen(true)}>
                                    <Send className="mr-2 h-4 w-4" />
                                    Release to Portal
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Payslips Table */}
                <PayslipsList
                    payslips={payslips?.data ?? []}
                    selectedPayslips={selectedPayslips}
                    onSelectionChange={setSelectedPayslips}
                    onDownload={handleDownload}
                    onView={handleView}
                />
            </div>

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

        <AlertDialog open={showNotification} onOpenChange={(open) => { if (!open) setNotificationDismissed(true); }}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <div className="flex items-center gap-3">
                        {isSuccessNotification
                            ? <CheckCircle className="h-6 w-6 text-green-600" />
                            : <AlertCircle className="h-6 w-6 text-red-600" />
                        }
                        <AlertDialogTitle className={isSuccessNotification ? 'text-green-700' : 'text-red-700'}>
                            {isSuccessNotification ? 'Success' : 'Error'}
                        </AlertDialogTitle>
                    </div>
                    <AlertDialogDescription>{notificationMessage}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogAction onClick={() => {
                    setNotificationDismissed(true);
                    router.reload();
                }}>
                    OK
                </AlertDialogAction>
            </AlertDialogContent>
        </AlertDialog>
        </AppLayout>
    );
}