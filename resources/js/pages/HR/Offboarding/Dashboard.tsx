import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PermissionGate } from '@/components/permission-gate';
import { 
    PieChart, 
    Pie, 
    Cell, 
    XAxis, 
    YAxis, 
    CartesianGrid, 
    Tooltip, 
    ResponsiveContainer,
    LineChart,
    Line
} from 'recharts';
import { AlertCircle, CheckCircle, Clock, Users, FileText, AlertTriangle, CheckCheck, TrendingUp } from 'lucide-react';

interface CaseItem {
    id: number;
    case_number: string;
    employee_name: string;
    employee_number: string;
    department: string;
    status: string;
    separation_type: string;
    last_working_day: string;
    hr_coordinator: string | null;
    clearance_completion: number;
    days_remaining: number;
    is_overdue: boolean;
    exit_interview_completed: boolean;
}

interface Activity {
    type: string;
    title: string;
    description: string;
    timestamp: string;
    date: string;
    severity: 'success' | 'info' | 'warning' | 'error';
}

interface Statistics {
    total_cases: number;
    pending: number;
    in_progress: number;
    clearance_pending: number;
    completed: number;
    cancelled: number;
    overdue: number;
    completion_rate: number;
    clearance: {
        pending: number;
        approved: number;
        issues: number;
        approval_rate: number;
    };
    exit_interviews: {
        completed: number;
    };
    assets: {
        pending_return: number;
        returned: number;
        lost_damaged: number;
    };
}

interface ClearanceStat {
    category: string;
    total: number;
    approved: number;
    pending: number;
    issues: number;
    approval_rate: number;
}

interface OffboardingDashboardProps {
    statistics: Statistics;
    casesThisWeek: CaseItem[];
    casesNextWeek: CaseItem[];
    overdueCases: CaseItem[];
    recentActivity: Activity[];
    trends: {
        labels: string[];
        data: number[];
    };
    separationReasons: {
        labels: string[];
        data: Array<{ label: string; value: number }>;
    };
    clearanceStats: ClearanceStat[];
    myAssignedCases: CaseItem[];
    userCanInitiate: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Offboarding', href: '/hr/offboarding' },
    { title: 'Dashboard', href: '/hr/offboarding/dashboard' },
];

const COLORS = ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899'];

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'completed':
            return <CheckCircle className="h-4 w-4 text-green-600" />;
        case 'in_progress':
            return <Clock className="h-4 w-4 text-blue-600" />;
        case 'clearance_pending':
            return <AlertCircle className="h-4 w-4 text-yellow-600" />;
        case 'pending':
            return <AlertTriangle className="h-4 w-4 text-orange-600" />;
        default:
            return <FileText className="h-4 w-4 text-gray-600" />;
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800';
        case 'clearance_pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'pending':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getActivityIcon = (type: string) => {
    switch (type) {
        case 'case_completed':
            return <CheckCheck className="h-4 w-4 text-green-600" />;
        case 'exit_interview_completed':
            return <CheckCircle className="h-4 w-4 text-blue-600" />;
        case 'clearance_approved':
            return <CheckCircle className="h-4 w-4 text-green-600" />;
        default:
            return <FileText className="h-4 w-4 text-gray-600" />;
    }
};

const getActivityColor = (severity: string) => {
    switch (severity) {
        case 'success':
            return 'border-l-green-500 bg-green-50';
        case 'info':
            return 'border-l-blue-500 bg-blue-50';
        case 'warning':
            return 'border-l-yellow-500 bg-yellow-50';
        case 'error':
            return 'border-l-red-500 bg-red-50';
        default:
            return 'border-l-gray-500 bg-gray-50';
    }
};

export default function OffboardingDashboard({
    statistics,
    casesThisWeek,
    overdueCases,
    recentActivity,
    trends,
    separationReasons,
    clearanceStats,
    myAssignedCases,
    userCanInitiate,
}: OffboardingDashboardProps) {

    // Transform trend data for chart
    const trendData = trends.labels.map((label, index) => ({
        name: label,
        cases: trends.data[index],
    }));

    // Transform separation reasons for pie chart
    const separationData = separationReasons.data || [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Offboarding Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h1 className="text-3xl font-bold tracking-tight">Offboarding Dashboard</h1>
                        {userCanInitiate && (
                            <Link href="/hr/offboarding/cases/create">
                                <Button className="bg-blue-600 hover:bg-blue-700">
                                    <Users className="h-4 w-4 mr-2" />
                                    Initiate Offboarding
                                </Button>
                            </Link>
                        )}
                    </div>
                    <p className="text-muted-foreground">
                        Track and manage employee offboarding cases, clearance approvals, and exit workflows
                    </p>
                </div>

                {/* Key Statistics Cards - Top Row */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    {/* Total Cases */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Cases</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.total_cases}</div>
                            <p className="text-xs text-muted-foreground">
                                {statistics.completed} completed ({statistics.completion_rate}%)
                            </p>
                        </CardContent>
                    </Card>

                    {/* Pending Cases */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.pending}</div>
                            <p className="text-xs text-muted-foreground">Awaiting initiation</p>
                        </CardContent>
                    </Card>

                    {/* In Progress */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">In Progress</CardTitle>
                            <Clock className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.in_progress}</div>
                            <p className="text-xs text-muted-foreground">Active offboarding</p>
                        </CardContent>
                    </Card>

                    {/* Overdue Cases */}
                    <Card className={statistics.overdue > 0 ? 'border-red-200 bg-red-50' : ''}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Overdue</CardTitle>
                            <AlertCircle className={`h-4 w-4 ${statistics.overdue > 0 ? 'text-red-600' : 'text-gray-600'}`} />
                        </CardHeader>
                        <CardContent>
                            <div className={`text-2xl font-bold ${statistics.overdue > 0 ? 'text-red-600' : ''}`}>
                                {statistics.overdue}
                            </div>
                            <p className="text-xs text-muted-foreground">Past last working day</p>
                        </CardContent>
                    </Card>

                    {/* Clearance Approval Rate */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Clearance Rate</CardTitle>
                            <CheckCheck className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.clearance.approval_rate}%</div>
                            <p className="text-xs text-muted-foreground">{statistics.clearance.approved} approved</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Left Column: Cases & Assignment */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* My Assigned Cases */}
                        {myAssignedCases.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">My Assigned Cases</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {myAssignedCases.map((case_item) => (
                                            <Link
                                                key={case_item.id}
                                                href={`/hr/offboarding/cases/${case_item.id}`}
                                                className="block"
                                            >
                                                <div className="flex items-start justify-between p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2">
                                                            {getStatusIcon(case_item.status)}
                                                            <p className="font-medium">{case_item.employee_name}</p>
                                                            <Badge className={getStatusColor(case_item.status)}>
                                                                {case_item.status.replace(/_/g, ' ')}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {case_item.case_number} • {case_item.department}
                                                        </p>
                                                        <div className="mt-2 flex items-center gap-4 text-xs">
                                                            <span>LWD: {case_item.last_working_day}</span>
                                                            <div className="w-24 bg-gray-200 rounded-full h-2">
                                                                <div
                                                                    className="bg-blue-600 h-2 rounded-full"
                                                                    style={{ width: `${case_item.clearance_completion}%` }}
                                                                />
                                                            </div>
                                                            <span>{case_item.clearance_completion}% complete</span>
                                                        </div>
                                                    </div>
                                                    {case_item.is_overdue && (
                                                        <AlertCircle className="h-5 w-5 text-red-600 ml-2" />
                                                    )}
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Cases Due This Week */}
                        {casesThisWeek.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <Clock className="h-5 w-5" />
                                        Due This Week
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {casesThisWeek.map((case_item) => (
                                            <Link
                                                key={case_item.id}
                                                href={`/hr/offboarding/cases/${case_item.id}`}
                                                className="block"
                                            >
                                                <div className="flex items-center justify-between p-2 border rounded hover:bg-gray-50 transition-colors text-sm">
                                                    <span className="font-medium">{case_item.employee_name}</span>
                                                    <span className="text-muted-foreground">{case_item.last_working_day}</span>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Overdue Cases Alert */}
                        {overdueCases.length > 0 && (
                            <Card className="border-red-200 bg-red-50">
                                <CardHeader>
                                    <CardTitle className="text-lg flex items-center gap-2 text-red-800">
                                        <AlertCircle className="h-5 w-5" />
                                        Overdue Cases ({overdueCases.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {overdueCases.slice(0, 5).map((case_item) => (
                                            <Link
                                                key={case_item.id}
                                                href={`/hr/offboarding/cases/${case_item.id}`}
                                                className="block"
                                            >
                                                <div className="flex items-center justify-between p-2 bg-white rounded hover:bg-gray-50 transition-colors text-sm">
                                                    <div>
                                                        <span className="font-medium">{case_item.employee_name}</span>
                                                        <span className="text-xs text-red-600 ml-2">
                                                            {case_item.days_remaining} days overdue
                                                        </span>
                                                    </div>
                                                    <Badge variant="destructive">{case_item.days_remaining}d overdue</Badge>
                                                </div>
                                            </Link>
                                        ))}
                                        {overdueCases.length > 5 && (
                                            <Link href="/hr/offboarding/cases?filter=overdue">
                                                <Button variant="outline" className="w-full text-xs">
                                                    View All {overdueCases.length} Overdue Cases
                                                </Button>
                                            </Link>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Column: Trends & Statistics */}
                    <div className="space-y-4">
                        {/* Recent Activity */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Recent Activity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 max-h-96 overflow-y-auto">
                                    {recentActivity.length > 0 ? (
                                        recentActivity.map((activity, index) => (
                                            <div
                                                key={index}
                                                className={`flex gap-3 p-3 rounded-lg border-l-4 ${getActivityColor(
                                                    activity.severity
                                                )}`}
                                            >
                                                <div className="flex-shrink-0 pt-1">
                                                    {getActivityIcon(activity.type)}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium">{activity.title}</p>
                                                    <p className="text-xs text-muted-foreground truncate">
                                                        {activity.description}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground pt-1">
                                                        {activity.date} at {activity.timestamp}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No recent activity</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Asset Return Status */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Asset Return Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm">Pending Return</span>
                                    <Badge variant="outline">{statistics.assets.pending_return}</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm">Returned</span>
                                    <Badge className="bg-green-100 text-green-800">{statistics.assets.returned}</Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm">Lost/Damaged</span>
                                    <Badge variant="destructive">{statistics.assets.lost_damaged}</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Charts Section */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Separations Trend Chart */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="h-5 w-5" />
                                Separations Trend (12 Months)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart
                                    data={trendData}
                                    margin={{ top: 5, right: 30, left: 0, bottom: 5 }}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis 
                                        dataKey="name" 
                                        angle={-45}
                                        textAnchor="end"
                                        height={80}
                                        tick={{ fontSize: 12 }}
                                    />
                                    <YAxis />
                                    <Tooltip />
                                    <Line
                                        type="monotone"
                                        dataKey="cases"
                                        stroke="#3b82f6"
                                        dot={{ fill: '#3b82f6' }}
                                        activeDot={{ r: 6 }}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    {/* Separation Reasons Chart */}
                    {separationData.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Separation Reasons</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie
                                            data={separationData}
                                            cx="50%"
                                            cy="50%"
                                            labelLine={false}
                                            label={({ name, value }) => `${name}: ${value}`}
                                            outerRadius={80}
                                            fill="#8884d8"
                                            dataKey="value"
                                        >
                                            {separationData.map((entry, index) => (
                                                <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Clearance Statistics */}
                {clearanceStats.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Clearance Statistics by Category</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b">
                                        <tr>
                                            <th className="text-left py-2 px-4 font-medium">Category</th>
                                            <th className="text-right py-2 px-4 font-medium">Total</th>
                                            <th className="text-right py-2 px-4 font-medium">Approved</th>
                                            <th className="text-right py-2 px-4 font-medium">Pending</th>
                                            <th className="text-right py-2 px-4 font-medium">Issues</th>
                                            <th className="text-right py-2 px-4 font-medium">Approval Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {clearanceStats.map((stat) => (
                                            <tr key={stat.category} className="border-b hover:bg-gray-50">
                                                <td className="py-3 px-4">{stat.category}</td>
                                                <td className="text-right py-3 px-4">{stat.total}</td>
                                                <td className="text-right py-3 px-4">
                                                    <Badge className="bg-green-100 text-green-800">
                                                        {stat.approved}
                                                    </Badge>
                                                </td>
                                                <td className="text-right py-3 px-4">
                                                    <Badge variant="outline">{stat.pending}</Badge>
                                                </td>
                                                <td className="text-right py-3 px-4">
                                                    {stat.issues > 0 && (
                                                        <Badge variant="destructive">{stat.issues}</Badge>
                                                    )}
                                                </td>
                                                <td className="text-right py-3 px-4">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <div className="w-16 bg-gray-200 rounded-full h-2">
                                                            <div
                                                                className="bg-blue-600 h-2 rounded-full"
                                                                style={{
                                                                    width: `${stat.approval_rate}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-xs font-medium">
                                                            {stat.approval_rate}%
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Quick Links */}
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Links</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                            <Link href="/hr/offboarding/cases">
                                <Button variant="outline" className="w-full justify-start">
                                    <FileText className="h-4 w-4 mr-2" />
                                    All Cases
                                </Button>
                            </Link>
                            <Link href="/hr/offboarding/cases?filter=pending">
                                <Button variant="outline" className="w-full justify-start">
                                    <AlertTriangle className="h-4 w-4 mr-2" />
                                    Pending Cases
                                </Button>
                            </Link>
                            <Link href="/hr/offboarding/cases?filter=overdue">
                                <Button variant="outline" className="w-full justify-start">
                                    <AlertCircle className="h-4 w-4 mr-2" />
                                    Overdue Cases
                                </Button>
                            </Link>
                            <PermissionGate permission="hr.offboarding.reports.view">
                                <Link href="/hr/offboarding/reports">
                                    <Button variant="outline" className="w-full justify-start">
                                        <TrendingUp className="h-4 w-4 mr-2" />
                                        Reports
                                    </Button>
                                </Link>
                            </PermissionGate>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
