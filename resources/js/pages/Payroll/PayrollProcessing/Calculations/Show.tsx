import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    Search,
    Users,
    TrendingUp,
    TrendingDown,
    Wallet,
    AlertCircle,
    CheckCircle2,
    Loader2,
    XCircle,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import type { BreadcrumbItem } from '@/types';
import type { PayrollCalculationShowPageProps, EmployeeCalculation } from '@/types/payroll-pages';

// ============================================================================
// Helpers
// ============================================================================

const peso = (n: number) =>
    '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const calcStatusInfo = (status: string) => {
    switch (status) {
        case 'pending':
            return { label: 'Pending', variant: 'secondary' as const, icon: <AlertCircle className="h-3 w-3" /> };
        case 'processing':
            return { label: 'Processing', variant: 'outline' as const, icon: <Loader2 className="h-3 w-3 animate-spin" /> };
        case 'completed':
            return { label: 'Completed', variant: 'default' as const, icon: <CheckCircle2 className="h-3 w-3" /> };
        case 'cancelled':
            return { label: 'Cancelled', variant: 'secondary' as const, icon: <XCircle className="h-3 w-3" /> };
        default:
            return { label: status, variant: 'secondary' as const, icon: null };
    }
};

const empStatusInfo = (status: string) => {
    switch (status) {
        case 'calculated':
            return { label: 'Calculated', className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' };
        case 'failed':
            return { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' };
        case 'adjusted':
            return { label: 'Adjusted', className: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100' };
        default:
            return { label: 'Pending', className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100' };
    }
};

// ============================================================================
// Employee Detail Panel
// ============================================================================

function EmployeeDetailPanel({ emp }: { emp: EmployeeCalculation }) {
    const govDeductions =
        (emp.deductions.sss ?? 0) +
        (emp.deductions.philhealth ?? 0) +
        (emp.deductions.pagibig ?? 0);

    return (
        <TableRow className="bg-muted/30">
            <TableCell colSpan={8} className="p-4">
                <div className="grid gap-4 sm:grid-cols-3">
                    {/* Earnings */}
                    <Card className="shadow-none">
                        <CardHeader className="pb-2 pt-3 px-4">
                            <CardTitle className="text-sm font-semibold text-green-700">Earnings</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-3 space-y-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Basic Pay</span>
                                <span className="font-medium">{peso(emp.basic_pay)}</span>
                            </div>
                            {(emp.earnings.overtime ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Overtime</span>
                                    <span className="font-medium">{peso(emp.earnings.overtime)}</span>
                                </div>
                            )}
                            {(emp.earnings.allowances ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Allowances</span>
                                    <span className="font-medium">{peso(emp.earnings.allowances)}</span>
                                </div>
                            )}
                            {(emp.earnings.bonuses ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Bonuses</span>
                                    <span className="font-medium">{peso(emp.earnings.bonuses)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t pt-1 font-semibold text-green-700">
                                <span>Gross Pay</span>
                                <span>{peso(emp.gross_pay)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deductions */}
                    <Card className="shadow-none">
                        <CardHeader className="pb-2 pt-3 px-4">
                            <CardTitle className="text-sm font-semibold text-red-700">Deductions</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-3 space-y-1 text-sm">
                            {(emp.deductions.sss ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">SSS</span>
                                    <span className="font-medium text-red-600">-{peso(emp.deductions.sss)}</span>
                                </div>
                            )}
                            {(emp.deductions.philhealth ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">PhilHealth</span>
                                    <span className="font-medium text-red-600">-{peso(emp.deductions.philhealth)}</span>
                                </div>
                            )}
                            {(emp.deductions.pagibig ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Pag-IBIG</span>
                                    <span className="font-medium text-red-600">-{peso(emp.deductions.pagibig)}</span>
                                </div>
                            )}
                            {(emp.deductions.tax ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Withholding Tax</span>
                                    <span className="font-medium text-red-600">-{peso(emp.deductions.tax)}</span>
                                </div>
                            )}
                            {(emp.deductions.loans ?? 0) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Loan Deductions</span>
                                    <span className="font-medium text-red-600">-{peso(emp.deductions.loans)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t pt-1 font-semibold text-red-700">
                                <span>Total Deductions</span>
                                <span>-{peso(emp.total_deductions)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Net Pay + Meta */}
                    <Card className="shadow-none border-2 border-green-200 bg-green-50 dark:bg-green-950 dark:border-green-800">
                        <CardHeader className="pb-2 pt-3 px-4">
                            <CardTitle className="text-sm font-semibold text-green-800 dark:text-green-200">Net Pay</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-3 space-y-2">
                            <p className="text-3xl font-bold text-green-700 dark:text-green-300">
                                {peso(emp.net_pay)}
                            </p>
                            <div className="text-xs text-muted-foreground space-y-1 pt-1">
                                <div className="flex justify-between">
                                    <span>Employee #</span>
                                    <span className="font-medium">{emp.employee_number}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span>Position</span>
                                    <span className="font-medium">{emp.position ?? '—'}</span>
                                </div>
                                {emp.calculated_at && (
                                    <div className="flex justify-between">
                                        <span>Calculated</span>
                                        <span className="font-medium">
                                            {new Date(emp.calculated_at).toLocaleDateString('en-PH')}
                                        </span>
                                    </div>
                                )}
                            </div>
                            {emp.error_message && (
                                <div className="mt-2 rounded bg-red-100 px-2 py-1 text-xs text-red-700 dark:bg-red-900 dark:text-red-200">
                                    <AlertCircle className="inline h-3 w-3 mr-1" />
                                    {emp.error_message}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </TableCell>
        </TableRow>
    );
}

// ============================================================================
// Main Page
// ============================================================================

export default function PayrollCalculationShow({
    calculation,
    employee_calculations,
}: PayrollCalculationShowPageProps) {
    const [search, setSearch] = useState('');
    const [expandedId, setExpandedId] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Payroll', href: '/payroll/dashboard' },
        { title: 'Calculations', href: '/payroll/calculations' },
        { title: calculation.payroll_period.name, href: '#' },
    ];

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        if (!q) return employee_calculations;
        return employee_calculations.filter(
            (e) =>
                e.employee_name.toLowerCase().includes(q) ||
                e.employee_number.toLowerCase().includes(q) ||
                (e.department ?? '').toLowerCase().includes(q)
        );
    }, [employee_calculations, search]);

    const statusInfo = calcStatusInfo(calculation.status);

    const toggleExpand = (id: number) =>
        setExpandedId((prev) => (prev === id ? null : id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calculation — ${calculation.payroll_period.name}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-wrap items-start gap-4">
                    <Link href="/payroll/calculations">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-1" />
                            Back
                        </Button>
                    </Link>

                    <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight truncate">
                                {calculation.payroll_period.name}
                            </h1>
                            <Badge variant={statusInfo.variant} className="flex items-center gap-1">
                                {statusInfo.icon}
                                {statusInfo.label}
                            </Badge>
                            <Badge variant="outline" className="capitalize">
                                {calculation.calculation_type}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm mt-0.5">
                            {calculation.payroll_period.start_date} — {calculation.payroll_period.end_date}
                            {calculation.calculated_by && (
                                <span className="ml-3">Calculated by: {calculation.calculated_by}</span>
                            )}
                        </p>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-5">
                            <div className="rounded-full bg-blue-100 p-2 dark:bg-blue-900">
                                <Users className="h-5 w-5 text-blue-600 dark:text-blue-300" />
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Employees</p>
                                <p className="text-2xl font-bold">{calculation.total_employees}</p>
                                {calculation.failed_employees > 0 && (
                                    <p className="text-xs text-red-500">
                                        {calculation.failed_employees} failed
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 pt-5">
                            <div className="rounded-full bg-green-100 p-2 dark:bg-green-900">
                                <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-300" />
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Total Gross Pay</p>
                                <p className="text-xl font-bold">{peso(calculation.total_gross_pay)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 pt-5">
                            <div className="rounded-full bg-red-100 p-2 dark:bg-red-900">
                                <TrendingDown className="h-5 w-5 text-red-600 dark:text-red-300" />
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Total Deductions</p>
                                <p className="text-xl font-bold">{peso(calculation.total_deductions)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex items-center gap-3 pt-5">
                            <div className="rounded-full bg-purple-100 p-2 dark:bg-purple-900">
                                <Wallet className="h-5 w-5 text-purple-600 dark:text-purple-300" />
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">Total Net Pay</p>
                                <p className="text-xl font-bold">{peso(calculation.total_net_pay)}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Employee Table */}
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle className="text-base">
                                Employee Calculations
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    ({filtered.length} of {employee_calculations.length})
                                </span>
                            </CardTitle>
                            <div className="relative w-60">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search name, ID, dept..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 h-8 text-sm"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {employee_calculations.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <Loader2 className="h-8 w-8 text-muted-foreground animate-spin mb-3" />
                                <p className="text-sm font-medium">Calculation in progress…</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Refresh this page once the queue worker finishes.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8" />
                                        <TableHead>Employee</TableHead>
                                        <TableHead className="hidden md:table-cell">Department</TableHead>
                                        <TableHead className="text-right">Basic Pay</TableHead>
                                        <TableHead className="text-right">Gross Pay</TableHead>
                                        <TableHead className="text-right">Deductions</TableHead>
                                        <TableHead className="text-right font-semibold">Net Pay</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filtered.map((emp) => {
                                        const isExpanded = expandedId === emp.id;
                                        const { label, className } = empStatusInfo(emp.status);
                                        return (
                                            <>
                                                <TableRow
                                                    key={emp.id}
                                                    className="cursor-pointer hover:bg-muted/40"
                                                    onClick={() => toggleExpand(emp.id)}
                                                >
                                                    <TableCell className="pl-4">
                                                        {isExpanded
                                                            ? <ChevronUp className="h-4 w-4 text-muted-foreground" />
                                                            : <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                                        }
                                                    </TableCell>
                                                    <TableCell>
                                                        <p className="font-medium text-sm">{emp.employee_name}</p>
                                                        <p className="text-xs text-muted-foreground">{emp.employee_number}</p>
                                                    </TableCell>
                                                    <TableCell className="hidden md:table-cell text-sm text-muted-foreground">
                                                        {emp.department ?? '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm font-mono">
                                                        {peso(emp.basic_pay)}
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm font-mono text-green-600">
                                                        {peso(emp.gross_pay)}
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm font-mono text-red-600">
                                                        -{peso(emp.total_deductions)}
                                                    </TableCell>
                                                    <TableCell className="text-right text-sm font-mono font-semibold">
                                                        {peso(emp.net_pay)}
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>
                                                            {label}
                                                        </span>
                                                    </TableCell>
                                                </TableRow>
                                                {isExpanded && (
                                                    <EmployeeDetailPanel key={`detail-${emp.id}`} emp={emp} />
                                                )}
                                            </>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
