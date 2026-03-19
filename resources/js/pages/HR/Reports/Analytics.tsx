import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Users, UserCheck, TrendingUp, Clock } from 'lucide-react';
import DepartmentBreakdownChart from '@/components/hr/department-breakdown-chart';
import EmployeeStatusPieChart from '@/components/hr/employee-status-pie-chart';
import RecentHiresWidget from '@/components/hr/recent-hires-widget';

interface Metric {
    total_employees: number;
    active_employees: number;
    inactive_employees: number;
    employees_by_department: Array<{
        id: number;
        name: string;
        code: string;
        employee_count: number;
        percentage: number;
    }>;
    recent_hires: Array<{
        id: number;
        name: string;
        position: string;
        department: string;
        date_hired: string;
        date_hired_formatted: string;
        photo_url?: string;
        employment_type: string;
    }>;
    employee_status_breakdown?: Array<{
        status: string;
        status_key: string;
        count: number;
        percentage: number;
    }>;
    employment_type_breakdown?: Array<{
        type: string;
        type_key: string;
        count: number;
        percentage: number;
    }>;
    turnover_rate: number;
    average_employment_duration: number;
    new_hires_this_month: number;
}

interface AnalyticsPageProps {
    metrics: Metric;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'HR',
        href: '/hr/dashboard',
    },
    {
        title: 'Analytics',
        href: '/hr/reports/analytics',
    },
];

function getStatusColor(status: string): string {
    const colorMap: Record<string, string> = {
        active: 'bg-green-100 text-green-800',
        Active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        Inactive: 'bg-gray-100 text-gray-800',
        on_leave: 'bg-blue-100 text-blue-800',
        On_Leave: 'bg-blue-100 text-blue-800',
        terminated: 'bg-red-100 text-red-800',
        Terminated: 'bg-red-100 text-red-800',
        probation: 'bg-yellow-100 text-yellow-800',
        Probation: 'bg-yellow-100 text-yellow-800',
    };
    return colorMap[status] || 'bg-gray-100 text-gray-800';
}

export default function Analytics({ metrics }: AnalyticsPageProps) {
    // Ensure metrics has all required fields with defaults
    const safeMetrics = {
        total_employees: metrics?.total_employees ?? 0,
        active_employees: metrics?.active_employees ?? 0,
        inactive_employees: metrics?.inactive_employees ?? 0,
        employees_by_department: metrics?.employees_by_department ?? [],
        recent_hires: metrics?.recent_hires ?? [],
        employee_status_breakdown: metrics?.employee_status_breakdown ?? [],
        employment_type_breakdown: metrics?.employment_type_breakdown ?? [],
        turnover_rate: metrics?.turnover_rate ?? 0,
        average_employment_duration: metrics?.average_employment_duration ?? 0,
        new_hires_this_month: metrics?.new_hires_this_month ?? 0,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="HR Analytics & Reports" />
            <div className="flex h-full flex-1 flex-col gap-8 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">HR Analytics & Reports</h1>
                    <p className="text-muted-foreground">
                        Comprehensive HR metrics and employee statistics for Cathay Metal Corporation
                    </p>
                </div>

                {/* Key Metrics Cards - Top Row */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Overview</h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {/* Total Employees Card */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                    <span>Total Employees</span>
                                    <Users className="h-4 w-4 text-blue-600" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{safeMetrics.total_employees}</div>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Active workforce
                                </p>
                            </CardContent>
                        </Card>

                        {/* Active Employees Card */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                    <span>Active</span>
                                    <UserCheck className="h-4 w-4 text-green-600" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{safeMetrics.active_employees}</div>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {safeMetrics.total_employees > 0 ? Math.round((safeMetrics.active_employees / safeMetrics.total_employees) * 100) : 0}% of total
                                </p>
                            </CardContent>
                        </Card>

                        {/* Avg Employment Duration Card */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                    <span>Avg. Duration</span>
                                    <Clock className="h-4 w-4 text-blue-600" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{safeMetrics.average_employment_duration}</div>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Average tenure
                                </p>
                            </CardContent>
                        </Card>

                        {/* Turnover Rate Card */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                    <span>Turnover Rate</span>
                                    <TrendingUp className="h-4 w-4 text-amber-600" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{(safeMetrics.turnover_rate ?? 0).toFixed(1)}%</div>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Last 12 months
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Charts and Breakdown Section */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Department Breakdown Chart */}
                    <div>
                        <DepartmentBreakdownChart data={safeMetrics.employees_by_department ?? []} />
                    </div>

                    {/* Employee Status Pie and Breakdown */}
                    <div className="flex flex-col gap-4">
                        <EmployeeStatusPieChart data={safeMetrics.employee_status_breakdown ?? []} />
                        <Card>
                            <CardHeader>
                                <CardTitle>Employee Status</CardTitle>
                                <CardDescription>Distribution by employment status</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {safeMetrics.employee_status_breakdown && safeMetrics.employee_status_breakdown.length > 0 ? (
                                        safeMetrics.employee_status_breakdown.map((status) => (
                                            <div key={status.status_key} className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge className={getStatusColor(status.status)}>
                                                        {status.status}
                                                    </Badge>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold">{status.count}</p>
                                                    <p className="text-xs text-muted-foreground">{status.percentage}%</p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No status data available</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent Hires and Employment Type */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Recent Hires Widget - Spans 2 columns */}
                    <div className="lg:col-span-2">
                        <RecentHiresWidget recent_hires={safeMetrics.recent_hires} />
                    </div>

                    {/* Employment Type Breakdown */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Employment Types</CardTitle>
                            <CardDescription>Workforce composition</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {safeMetrics.employment_type_breakdown && safeMetrics.employment_type_breakdown.length > 0 ? (
                                    safeMetrics.employment_type_breakdown.map((type) => (
                                        <div key={type.type_key} className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium">{type.type}</span>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold">{type.count}</p>
                                                <p className="text-xs text-muted-foreground">{type.percentage}%</p>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">No employment type data available</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* New Hires This Month */}
                <Card>
                    <CardHeader>
                        <CardTitle>New Hires This Month</CardTitle>
                        <CardDescription>Employees hired in the current month</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-4xl font-bold text-emerald-600">
                            {safeMetrics.new_hires_this_month}
                        </div>
                        <p className="text-sm text-muted-foreground mt-2">
                            New team members onboarded this month
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
