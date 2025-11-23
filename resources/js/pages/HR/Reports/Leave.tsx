import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Download, Clock, CheckCircle, XCircle } from 'lucide-react';
import { LeaveReportsPageProps } from '@/types/hr-pages';

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Reports', href: '#' },
    { title: 'Leave Reports', href: '/hr/reports/leave' },
];

export default function LeaveReports({ summary, by_type, by_status }: LeaveReportsPageProps) {
    const totalRequests = (summary?.total_pending_requests || 0) + (summary?.total_approved_requests || 0) + (summary?.total_rejected_requests || 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Reports" />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div>
                        <Heading title="Leave Reports" />
                        <HeadingSmall title="Analyze leave request trends and statistics" />
                        </div>
                        <Button variant="outline">
                            <Download className="h-4 w-4 mr-2" />
                            Export Report
                        </Button>
                    </div>
                </div>

                {/* Summary Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Requests</CardTitle>
                            <Clock className="h-4 w-4 text-yellow-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{summary?.total_pending_requests || 0}</div>
                            <p className="text-xs text-muted-foreground">Awaiting approval</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Approved</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{summary?.total_approved_requests || 0}</div>
                            <p className="text-xs text-muted-foreground">This year</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Rejected</CardTitle>
                            <XCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{summary?.total_rejected_requests || 0}</div>
                            <p className="text-xs text-muted-foreground">This year</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Days Used</CardTitle>
                            <Clock className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{summary?.leave_days_used_this_year || 0}</div>
                            <p className="text-xs text-muted-foreground">Approved leave days</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Leave by Type */}
                <Card>
                    <CardHeader>
                        <CardTitle>Leave Requests by Type</CardTitle>
                        <CardDescription>
                            Distribution of leave requests by type
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {by_type && by_type.length > 0 ? (
                                by_type.map((leaveType) => (
                                    <div key={leaveType.leave_type} className="flex items-center justify-between">
                                        <div className="flex items-center gap-3 flex-1">
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">{leaveType.leave_type}</p>
                                                <p className="text-xs text-muted-foreground">{leaveType.count} requests</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="w-32 bg-muted rounded-full h-2">
                                                <div
                                                    className="bg-blue-600 h-2 rounded-full transition-all"
                                                    style={{
                                                        width: `${leaveType.percentage}%`,
                                                    }}
                                                />
                                            </div>
                                            <Badge variant="outline">
                                                {leaveType.percentage.toFixed(1)}%
                                            </Badge>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-8">
                                    <p className="text-muted-foreground">No leave data available</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Request Status Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle>Request Status Overview</CardTitle>
                        <CardDescription>
                            Summary of all leave requests by status
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="p-4 bg-yellow-50 dark:bg-yellow-950 rounded-lg border border-yellow-200 dark:border-yellow-800">
                                <p className="text-sm text-yellow-900 dark:text-yellow-100 font-medium mb-2">Pending</p>
                                <p className="text-3xl font-bold text-yellow-600">{summary?.total_pending_requests || 0}</p>
                                <p className="text-xs text-yellow-700 dark:text-yellow-200 mt-2">
                                    {totalRequests > 0
                                        ? ((summary?.total_pending_requests || 0) / totalRequests * 100).toFixed(1)
                                        : 0}
                                    % of total
                                </p>
                            </div>

                            <div className="p-4 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">
                                <p className="text-sm text-green-900 dark:text-green-100 font-medium mb-2">Approved</p>
                                <p className="text-3xl font-bold text-green-600">{summary?.total_approved_requests || 0}</p>
                                <p className="text-xs text-green-700 dark:text-green-200 mt-2">
                                    {totalRequests > 0
                                        ? ((summary?.total_approved_requests || 0) / totalRequests * 100).toFixed(1)
                                        : 0}
                                    % of total
                                </p>
                            </div>

                            <div className="p-4 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">
                                <p className="text-sm text-red-900 dark:text-red-100 font-medium mb-2">Rejected</p>
                                <p className="text-3xl font-bold text-red-600">{summary?.total_rejected_requests || 0}</p>
                                <p className="text-xs text-red-700 dark:text-red-200 mt-2">
                                    {totalRequests > 0
                                        ? ((summary?.total_rejected_requests || 0) / totalRequests * 100).toFixed(1)
                                        : 0}
                                    % of total
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Status Breakdown Table */}
                {by_status && by_status.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Status Breakdown Details</CardTitle>
                            <CardDescription>
                                Detailed breakdown of requests by status
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {by_status.map((statusItem) => (
                                    <div key={statusItem.status} className="flex items-center justify-between border-b pb-3 last:border-0">
                                        <div className="flex-1">
                                            <p className="text-sm font-medium capitalize">{statusItem.status}</p>
                                            <p className="text-xs text-muted-foreground">{statusItem.count} requests</p>
                                        </div>
                                        <Badge variant="outline">
                                            {statusItem.percentage.toFixed(1)}%
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Leave Statistics */}
                <Card>
                    <CardHeader>
                        <CardTitle>Leave Statistics</CardTitle>
                        <CardDescription>
                            Summary of leave usage and availability
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="p-4 bg-muted rounded-lg">
                                <p className="text-sm text-muted-foreground mb-1">Employees on Leave</p>
                                <p className="text-2xl font-bold">{summary?.employees_on_leave || 0}</p>
                                <p className="text-xs text-muted-foreground mt-1">Currently on leave</p>
                            </div>

                            <div className="p-4 bg-muted rounded-lg">
                                <p className="text-sm text-muted-foreground mb-1">Days Used This Year</p>
                                <p className="text-2xl font-bold">{summary?.leave_days_used_this_year || 0}</p>
                                <p className="text-xs text-muted-foreground mt-1">Total approved days</p>
                            </div>

                            <div className="p-4 bg-muted rounded-lg">
                                <p className="text-sm text-muted-foreground mb-1">Avg Days Remaining</p>
                                <p className="text-2xl font-bold">{(summary?.leave_days_remaining_average || 0).toFixed(1)}</p>
                                <p className="text-xs text-muted-foreground mt-1">Per employee</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Monthly Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle>Monthly Leave Trends</CardTitle>
                        <CardDescription>
                            Leave requests and approvals over the last 6 months
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-center py-8">
                            <p className="text-muted-foreground">
                                📊 Charts and detailed trends will be available with additional implementation
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Feature coming soon: Monthly trend visualization
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
