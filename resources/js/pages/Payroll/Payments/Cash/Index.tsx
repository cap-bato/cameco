import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DollarSign, TrendingUp, AlertCircle, Printer, Download } from 'lucide-react';
import CashPayrollList from '@/components/payroll/cash-payroll-list';
import EnvelopePrinter from '@/components/payroll/envelope-printer';
import CashDistributionTracker from '@/components/payroll/cash-distribution-tracker';
import type { CashPaymentPageProps } from '@/types/payroll-pages';

const breadcrumbs = [
    { title: 'Payroll', href: '/payroll/dashboard' },
    { title: 'Payments', href: '#' },
    { title: 'Cash Management', href: '/payroll/payments/cash' },
];

export default function CashPaymentIndex({
    cash_employees,
    summary,
    payroll_periods,
    batches,
    unclaimed_cash,
    query_params,
}: CashPaymentPageProps & { query_params?: Record<string, string> }) {
    // Initialize state from URL parameters - DO NOT trigger updates on init
    const [periodId, setPeriodId] = useState(query_params?.period_id || 'all');
    const [departmentId, setDepartmentId] = useState(query_params?.department_id || 'all');
    const [status, setStatus] = useState(query_params?.status || 'all');
    const [searchQuery, setSearchQuery] = useState(query_params?.search || '');
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [activeTab, setActiveTab] = useState('employees');

    // Filter employees locally (client-side)
    const filteredEmployees = useMemo(() => {
        let filtered = cash_employees;

        // Apply search filter
        if (searchQuery.trim()) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(
                (emp) =>
                    emp.employee_name.toLowerCase().includes(query) ||
                    emp.employee_number.toLowerCase().includes(query) ||
                    emp.department.toLowerCase().includes(query) ||
                    emp.position.toLowerCase().includes(query)
            );
        }

        // Apply department filter
        if (departmentId !== 'all') {
            filtered = filtered.filter((emp) => emp.department === departmentId);
        }

        // Apply payment status filter (mapped from payroll_payments.status)
        if (status !== 'all') {
            filtered = filtered.filter((emp) => emp.payment_status === status);
        }

        return filtered;
    }, [searchQuery, departmentId, status, cash_employees]);

    // Handle filter changes - create URL with current filter values
    const handleFilterChange = () => {
        const params: Record<string, string> = {};
        if (periodId !== 'all') params.period_id = periodId;
        if (departmentId !== 'all') params.department_id = departmentId;
        if (status !== 'all') params.status = status;
        if (searchQuery.trim()) params.search = searchQuery;

        const queryString = new URLSearchParams(params).toString();
        router.get(`/payroll/payments/cash${queryString ? '?' + queryString : ''}`, {}, { preserveScroll: true });
    };

    const handleResetFilters = () => {
        setPeriodId('1');
        setDepartmentId('all');
        setStatus('all');
        setSearchQuery('');
        router.get('/payroll/payments/cash', {}, { preserveScroll: true });
    };

    const handleSelectEmployee = (employeeId: number) => {
        setSelectedEmployees((prev) =>
            prev.includes(employeeId) ? prev.filter((id) => id !== employeeId) : [...prev, employeeId]
        );
    };

    const handleSelectAllEmployees = () => {
        if (selectedEmployees.length === cash_employees.length) {
            setSelectedEmployees([]);
        } else {
            setSelectedEmployees(cash_employees.map((emp) => emp.id));
        }
    };

    const handleGenerateEnvelopes = () => {
        if (selectedEmployees.length === 0) {
            alert('Please select at least one employee');
            return;
        }

        router.post('/payroll/payments/cash/generate-envelopes', {
            period_id: periodId,
            employee_ids: selectedEmployees,
        });
    };

    const handleDownloadAccountabilityReport = () => {
        router.get('/payroll/payments/cash/accountability-report', {
            period_id: periodId,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cash Payment Management" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Cash Payment Management</h1>
                    <p className="text-muted-foreground">
                        Manage cash payment envelopes, distribution, and accountability tracking
                    </p>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Cash Employees</CardTitle>
                            <DollarSign className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_cash_employees}</div>
                            <p className="text-xs text-muted-foreground mt-1">{summary.formatted_total_cash}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Envelopes Printed</CardTitle>
                            <Printer className="h-4 w-4 text-yellow-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.envelopes_printed}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {summary.envelopes_pending} pending
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Distributed</CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.distributed_count}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {summary.pending_distribution} pending
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Unclaimed</CardTitle>
                            <AlertCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.unclaimed_count}</div>
                            <p className="text-xs text-muted-foreground mt-1">{summary.formatted_unclaimed_amount}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Search */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Search & Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Search Box */}
                            <div>
                                <label className="text-xs font-semibold text-gray-700 mb-1 block">
                                    Search by name, employee ID, department, or position
                                </label>
                                <Input
                                    placeholder="Search employees..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full"
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label className="text-xs font-semibold text-gray-700 mb-1 block">
                                        Payroll Period
                                    </label>
                                    <Select value={periodId} onValueChange={setPeriodId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select period" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Periods</SelectItem>
                                            {payroll_periods.map((period) => (
                                                <SelectItem key={period.id} value={String(period.id)}>
                                                    {period.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <label className="text-xs font-semibold text-gray-700 mb-1 block">
                                        Department
                                    </label>
                                    <Select value={departmentId} onValueChange={setDepartmentId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Departments</SelectItem>
                                            <SelectItem value="Operations">Operations</SelectItem>
                                            <SelectItem value="Sales">Sales</SelectItem>
                                            <SelectItem value="Finance">Finance</SelectItem>
                                            <SelectItem value="HR">HR</SelectItem>
                                            <SelectItem value="IT">IT</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <label className="text-xs font-semibold text-gray-700 mb-1 block">
                                        Payment Status
                                    </label>
                                    <Select value={status} onValueChange={setStatus}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Statuses</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="processing">Processing</SelectItem>
                                            <SelectItem value="paid">Paid</SelectItem>
                                            <SelectItem value="failed">Failed</SelectItem>
                                            <SelectItem value="unclaimed">Unclaimed</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={handleFilterChange} className="bg-blue-600 hover:bg-blue-700" size="sm">
                                    Apply Filters
                                </Button>
                                <Button onClick={handleResetFilters} variant="outline" size="sm">
                                    Clear All Filters
                                </Button>
                                {(searchQuery || departmentId !== 'all' || status !== 'all') && (
                                    <div className="text-sm text-gray-600 flex items-center">
                                        Showing {filteredEmployees.length} of {cash_employees.length} employees
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="employees">Employees ({filteredEmployees.length})</TabsTrigger>
                        <TabsTrigger value="envelopes">Envelope Printing</TabsTrigger>
                        <TabsTrigger value="distribution">Distribution Tracking</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Employees */}
                    <TabsContent value="employees" className="space-y-4">
                        <div className="flex gap-2 mb-4">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSelectAllEmployees}
                                disabled={filteredEmployees.length === 0}
                            >
                                {selectedEmployees.length === filteredEmployees.length && filteredEmployees.length > 0
                                    ? 'Deselect All'
                                    : 'Select All'}
                            </Button>
                            <Button
                                onClick={handleGenerateEnvelopes}
                                disabled={selectedEmployees.length === 0}
                                className="bg-blue-600 hover:bg-blue-700"
                            >
                                <Printer className="h-4 w-4 mr-2" />
                                Generate Envelopes ({selectedEmployees.length})
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleDownloadAccountabilityReport}
                            >
                                <Download className="h-4 w-4 mr-2" />
                                Accountability Report
                            </Button>
                        </div>

                        <CashPayrollList
                            employees={filteredEmployees}
                            selectedEmployees={selectedEmployees}
                            onSelectEmployee={handleSelectEmployee}
                        />
                    </TabsContent>

                    {/* Tab 2: Envelope Printing */}
                    <TabsContent value="envelopes" className="space-y-4">
                        <EnvelopePrinter employees={cash_employees} periodId={periodId} />
                    </TabsContent>

                    {/* Tab 3: Distribution Tracking */}
                    <TabsContent value="distribution" className="space-y-4">
                        <CashDistributionTracker
                            distributions={batches}
                            unclaimedCash={unclaimed_cash}
                            employees={cash_employees}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
