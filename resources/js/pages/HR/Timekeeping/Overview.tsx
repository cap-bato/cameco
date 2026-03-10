import { Head, usePage, Link } from '@inertiajs/react';
import { useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { SummaryCard } from '@/components/timekeeping/summary-card';
import { ExternalLink, Activity, CheckCircle, AlertCircle, AlertTriangle } from 'lucide-react';

interface StatusDistribution {
    status: string;
    count: number;
    percentage: number;
}

interface TopIssue {
    issue: string;
    count: number;
    trend: 'up' | 'down' | 'stable';
}

interface Analytics {
    summary: {
        total_employees: number;
        average_attendance_rate: number;
        average_late_rate: number;
        average_absent_rate: number;
        compliance_score: number;
    };
    status_distribution: StatusDistribution[];
    top_issues: TopIssue[];
}

interface LedgerHealthStatus {
    status: string;
    last_sequence_id: number;
    events_today: number;
    devices_online: number;
    devices_offline: number;
    last_sync: string;
    avg_latency_ms: number;
    hash_verification: {
        total_checked: number;
        passed: number;
        failed: number;
    };
    performance: {
        events_per_hour: number;
        avg_processing_time_ms: number;
        queue_depth: number;
    };
    alerts: Array<{ severity: string; message: string; timestamp: string }>;
}

interface Violation {
    id: number;
    employee: string;
    type: string;
    time: string;
    severity: 'low' | 'medium' | 'high';
    corrected_at: string;
}

interface DailyTrend {
    date: string;
    label: string;
    present: number;
    late: number;
    absent: number;
    attendance_rate: number;
}

export default function TimekeepingOverview() {
    const page = usePage();
    const analytics = (page.props as { analytics?: Analytics }).analytics || {
        summary: { total_employees: 0, average_attendance_rate: 0, average_late_rate: 0, average_absent_rate: 0, compliance_score: 0 },
        status_distribution: [],
        top_issues: [],
    };
    
    // Get ledgerHealth from Inertia props (passed from controller)
    const ledgerHealth = (page.props as { ledgerHealth?: LedgerHealthStatus }).ledgerHealth || null;
    
    // Get recentViolations from Inertia props (passed from controller)
    const recentViolations = (page.props as { recentViolations?: Violation[] }).recentViolations || [];
    
    // Get dailyTrends from Inertia props (passed from controller)
    const dailyTrends = (page.props as { dailyTrends?: DailyTrend[] }).dailyTrends || [];
    
    // Calculate totals for each day and format day names
    const trendsWithTotals = dailyTrends.map(trend => ({
        ...trend,
        total: trend.present + trend.late + trend.absent,
        day: new Date(trend.date).toLocaleDateString('en-US', { weekday: 'long' })
    }));

    // Simple status mapping function
    const getStatusIcon = (status: string) => {
        if (status === 'healthy') return <CheckCircle className="h-5 w-5 text-green-600" />;
        if (status === 'degraded') return <AlertTriangle className="h-5 w-5 text-yellow-600" />;
        return <AlertCircle className="h-5 w-5 text-red-600" />;
    };

    const getStatusColor = (status: string) => {
        if (status === 'healthy') return 'bg-green-50 border-green-200';
        if (status === 'degraded') return 'bg-yellow-50 border-yellow-200';
        return 'bg-red-50 border-red-200';
    };

    const getStatusBadge = (status: string) => {
        if (status === 'healthy') return <Badge className="bg-green-600">Healthy</Badge>;
        if (status === 'degraded') return <Badge variant="secondary" className="bg-yellow-600">Degraded</Badge>;
        return <Badge variant="destructive">Critical</Badge>;
    };

    // Handler for View Logs action
    const handleViewLogs = useCallback((filterType: string) => {
        // Navigate to attendance records page with pre-applied filters
        console.log(`Navigating to logs with filter: ${filterType}`);
        // Example: router.visit('/hr/timekeeping/attendance', { data: { filter: filterType } });
    }, []);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'Overview', href: '/hr/timekeeping/overview' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col">
                    <h1 className="text-3xl font-bold">Attendance Overview</h1>
                    <p className="text-gray-600">Monitor attendance metrics and trends </p>
                </div>

                {/* Ledger Health Overview Card - Simple Status */}
                {ledgerHealth ? (
                    <Card className={`border ${getStatusColor(ledgerHealth.status)}`}>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    {getStatusIcon(ledgerHealth.status)}
                                    <div>
                                        <CardTitle>RFID Ledger Status</CardTitle>
                                        <CardDescription>Event stream and device health overview</CardDescription>
                                    </div>
                                </div>
                                {getStatusBadge(ledgerHealth.status)}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <div className="text-muted-foreground">Devices Online</div>
                                    <div className="text-2xl font-bold text-green-600">{ledgerHealth.devices_online}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Devices Offline</div>
                                    <div className="text-2xl font-bold text-red-600">{ledgerHealth.devices_offline}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Events Today</div>
                                    <div className="text-2xl font-bold">{ledgerHealth.events_today}</div>
                                </div>
                                <div>
                                    <div className="text-muted-foreground">Processing Rate</div>
                                    <div className="text-2xl font-bold">{ledgerHealth.performance.events_per_hour}/hr</div>
                                </div>
                            </div>
                            <Link href="/hr/timekeeping/ledger" className="block mt-4">
                                <Button className="w-full gap-2">
                                    View Full Ledger & Replay
                                    <ExternalLink className="h-4 w-4" />
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : null}

                {/* Two Column Layout: Summary Cards + Analytics */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column: Summary Cards and Other Widgets */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Summary Cards */}
                        <div className="grid gap-4 grid-cols-1">
                            <SummaryCard
                                title="Attendance Rate"
                                value={`${analytics.summary.average_attendance_rate}%`}
                                description={`of ${analytics.summary.total_employees} employees`}
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('attendance')}
                            />
                            <SummaryCard
                                title="Late Rate"
                                value={`${analytics.summary.average_late_rate}%`}
                                description="late arrivals"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('late')}
                            />
                            <SummaryCard
                                title="Absent Rate"
                                value={`${analytics.summary.average_absent_rate}%`}
                                description="absent employees"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('absent')}
                            />
                            <SummaryCard
                                title="Compliance Score"
                                value={analytics.summary.compliance_score}
                                description="overall compliance"
                                actionLabel="View Logs"
                                onActionClick={() => handleViewLogs('compliance')}
                            />
                        </div>

                        {/* Status Distribution */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Status Distribution</CardTitle>
                                <CardDescription>Attendance status breakdown</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {analytics.status_distribution.map((status: StatusDistribution) => (
                                        <div key={status.status} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="capitalize text-sm font-medium">{status.status}</div>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <div className="text-right">
                                                    <div className="text-sm font-semibold">{status.count}</div>
                                                    <div className="text-xs text-muted-foreground">{status.percentage}%</div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Top Issues */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Issues</CardTitle>
                                <CardDescription>Most common attendance issues</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {analytics.top_issues.map((issue: TopIssue) => (
                                        <div key={issue.issue} className="flex items-center justify-between p-2 rounded-lg bg-muted">
                                            <div className="flex items-center gap-3">
                                                <div className="text-sm font-medium">{issue.issue}</div>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <div className="text-sm font-semibold">{issue.count}</div>
                                                <div className={`text-xs px-2 py-1 rounded ${
                                                    issue.trend === 'up' ? 'bg-red-100 text-red-700' :
                                                    issue.trend === 'down' ? 'bg-green-100 text-green-700' :
                                                    'bg-blue-100 text-blue-700'
                                                }`}>
                                                    {issue.trend}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Quick Actions & Insights */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* View Full Ledger Card */}
                        <Card className="bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-200">
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Activity className="h-5 w-5 text-blue-600" />
                                            RFID Event Ledger
                                        </CardTitle>
                                        <CardDescription className="mt-2">
                                            View detailed event stream with replay controls and device monitoring
                                        </CardDescription>
                                    </div>
                                    <Link href="/hr/timekeeping/ledger">
                                        <Button className="gap-2">
                                            View Full Ledger
                                            <ExternalLink className="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                                <CardDescription>Common timekeeping operations</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <Button 
                                        variant="outline" 
                                        className="justify-start h-auto py-4"
                                        onClick={() => handleViewLogs('all')}
                                    >
                                        <div className="text-left">
                                            <div className="font-semibold">View Attendance Records</div>
                                            <div className="text-xs text-muted-foreground mt-1">Browse all employee attendance</div>
                                        </div>
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        className="justify-start h-auto py-4"
                                        onClick={() => console.log('Navigate to import')}
                                    >
                                        <div className="text-left">
                                            <div className="font-semibold">Import Timesheets</div>
                                            <div className="text-xs text-muted-foreground mt-1">Upload and process attendance data</div>
                                        </div>
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        className="justify-start h-auto py-4"
                                        onClick={() => console.log('Navigate to overtime')}
                                    >
                                        <div className="text-left">
                                            <div className="font-semibold">Manage Overtime</div>
                                            <div className="text-xs text-muted-foreground mt-1">Review and approve overtime requests</div>
                                        </div>
                                    </Button>
                                    <Link href="/hr/timekeeping/ledger">
                                        <Button 
                                            variant="outline" 
                                            className="justify-start h-auto py-4 w-full"
                                        >
                                            <div className="text-left">
                                                <div className="font-semibold">View RFID Ledger</div>
                                                <div className="text-xs text-muted-foreground mt-1">Real-time event stream and replay</div>
                                            </div>
                                        </Button>
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Device Status Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Device Status Summary</CardTitle>
                                <CardDescription>RFID reader network health</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="text-center p-4 bg-green-50 rounded-lg">
                                        <div className="text-3xl font-bold text-green-700">
                                            {ledgerHealth?.devices_online || 0}
                                        </div>
                                        <div className="text-sm text-green-600 mt-1">Online</div>
                                    </div>
                                    <div className="text-center p-4 bg-red-50 rounded-lg">
                                        <div className="text-3xl font-bold text-red-700">
                                            {ledgerHealth?.devices_offline || 0}
                                        </div>
                                        <div className="text-sm text-red-600 mt-1">Offline</div>
                                    </div>
                                    <div className="text-center p-4 bg-blue-50 rounded-lg">
                                        <div className="text-3xl font-bold text-blue-700">
                                            {(ledgerHealth?.devices_online || 0) + (ledgerHealth?.devices_offline || 0)}
                                        </div>
                                        <div className="text-sm text-blue-600 mt-1">Total</div>
                                    </div>
                                </div>
                                <Link href="/hr/timekeeping/ledger" className="block mt-4">
                                    <Button variant="outline" className="w-full gap-2">
                                        View Device Dashboard
                                        <ExternalLink className="h-4 w-4" />
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        {/* Recent Violations */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Recent Violations</CardTitle>
                                        <CardDescription>Latest attendance policy violations</CardDescription>
                                    </div>
                                    <Button 
                                        variant="ghost" 
                                        size="sm"
                                        onClick={() => handleViewLogs('violations')}
                                    >
                                        View All
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {recentViolations.length > 0 ? (
                                        recentViolations.map((violation) => (
                                            <div key={violation.id} className="flex items-center justify-between p-3 rounded-lg bg-muted hover:bg-muted/80 transition-colors">
                                                <div>
                                                    <div className="font-medium text-sm">{violation.employee}</div>
                                                    <div className="text-xs text-muted-foreground">{violation.type} • {violation.time}</div>
                                                </div>
                                                <Badge 
                                                    variant={
                                                        violation.severity === 'high' ? 'destructive' :
                                                        violation.severity === 'medium' ? 'default' :
                                                        'secondary'
                                                    }
                                                    className="capitalize"
                                                >
                                                    {violation.severity}
                                                </Badge>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-center py-6 text-muted-foreground">
                                            No recent violations
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Attendance Trends Chart Placeholder */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Daily Attendance Trends</CardTitle>
                                <CardDescription>Last 7 days attendance patterns</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {trendsWithTotals.length > 0 ? (
                                        trendsWithTotals.map((day, index) => {
                                            const presentPercentage = day.total > 0 ? (day.present / day.total) * 100 : 0;
                                            const latePercentage = day.total > 0 ? (day.late / day.total) * 100 : 0;
                                            const absentPercentage = day.total > 0 ? (day.absent / day.total) * 100 : 0;
                                            
                                            return (
                                                <div key={index} className="space-y-1">
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="font-medium">{day.day}</span>
                                                        <span className="text-muted-foreground">{day.total} employees</span>
                                                    </div>
                                                    <div className="flex gap-1 h-2 rounded-full overflow-hidden bg-muted">
                                                        <div 
                                                            className="bg-green-500" 
                                                            style={{ width: `${presentPercentage}%` }}
                                                            title={`Present: ${day.present}`}
                                                        />
                                                        <div 
                                                            className="bg-yellow-500" 
                                                            style={{ width: `${latePercentage}%` }}
                                                            title={`Late: ${day.late}`}
                                                        />
                                                        <div 
                                                            className="bg-red-500" 
                                                            style={{ width: `${absentPercentage}%` }}
                                                            title={`Absent: ${day.absent}`}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="text-center py-6 text-muted-foreground">
                                            No attendance data available
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center justify-center gap-4 mt-4 text-xs">
                                    <div className="flex items-center gap-1">
                                        <div className="w-3 h-3 rounded bg-green-500"></div>
                                        <span>Present</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-3 h-3 rounded bg-yellow-500"></div>
                                        <span>Late</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-3 h-3 rounded bg-red-500"></div>
                                        <span>Absent</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
