import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { QuickStatCard } from '@/components/employee/quick-stat-card';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Calendar, 
    Clock, 
    DollarSign, 
    FileText,
    User,
    Bell,
    CheckCircle2,
    XCircle,
    AlertCircle,
    CalendarCheck,
    Wallet,
} from 'lucide-react';
import { format } from 'date-fns';

interface LeaveBalance {
    leave_type: string;
    leave_code: string;
    available: number;
    used: number;
    total: number;
}

interface TodayAttendance {
    status: string;
    time_in?: string;
    time_out?: string;
    hours_worked?: number;
}

interface NextPayday {
    date: string;
    days_until: number;
    period: string;
}

interface QuickStats {
    leave_balances: LeaveBalance[];
    today_attendance: TodayAttendance;
    pending_requests_count: number;
    next_payday: NextPayday;
}

interface RecentActivity {
    type: string;
    icon: string;
    title: string;
    description: string;
    timestamp: string;
    status?: string;
}

interface EmployeeInfo {
    id: number;
    employee_number: string;
    full_name: string;
    position: string;
    department: string;
}

interface DashboardProps {
    employee: EmployeeInfo;
    quickStats: QuickStats;
    recentActivity: RecentActivity[];
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
];

// Activity type icons and colors
const activityConfig = {
    leave_request: {
        icon: Calendar,
        color: 'text-blue-600 bg-blue-100 dark:bg-blue-900/30',
    },
    profile_update: {
        icon: User,
        color: 'text-purple-600 bg-purple-100 dark:bg-purple-900/30',
    },
    attendance_correction: {
        icon: Clock,
        color: 'text-orange-600 bg-orange-100 dark:bg-orange-900/30',
    },
    payslip_released: {
        icon: Wallet,
        color: 'text-green-600 bg-green-100 dark:bg-green-900/30',
    },
};

// Status badge variants
const statusConfig = {
    Pending: { variant: 'secondary' as const, icon: AlertCircle },
    Approved: { variant: 'default' as const, icon: CheckCircle2 },
    Rejected: { variant: 'destructive' as const, icon: XCircle },
    Completed: { variant: 'default' as const, icon: CheckCircle2 },
};

export default function Dashboard({ employee, quickStats, recentActivity, error }: DashboardProps) {
    // Calculate total available leave days
    const totalLeaveAvailable = quickStats.leave_balances.reduce(
        (sum, balance) => sum + balance.available,
        0
    );

    // Get primary leave balance (Vacation Leave)
    const primaryLeaveBalance = quickStats.leave_balances.find(
        (balance) => balance.leave_code === 'VL'
    ) || quickStats.leave_balances[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employee Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">
                                Welcome, {employee.full_name.split(' ')[0]}
                            </h1>
                            <p className="text-muted-foreground mt-1">
                                {employee.position} • {employee.department}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">Employee ID</p>
                            <p className="text-lg font-semibold">{employee.employee_number}</p>
                        </div>
                    </div>
                </div>

                {/* Error Message */}
                {error && (
                    <Card className="border-destructive/50 bg-destructive/10">
                        <CardContent className="pt-6">
                            <p className="text-sm text-destructive">{error}</p>
                        </CardContent>
                    </Card>
                )}

                {/* Quick Stats */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Quick Overview</h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <QuickStatCard
                            title="Leave Balance"
                            value={totalLeaveAvailable}
                            subtitle={primaryLeaveBalance ? `${primaryLeaveBalance.available} ${primaryLeaveBalance.leave_type} days` : 'View all balances'}
                            icon={Calendar}
                            linkTo="/employee/leave/balances"
                            colorScheme="blue"
                            badge="days"
                        />
                        <QuickStatCard
                            title="Today's Attendance"
                            value={
                                quickStats.today_attendance.status === 'Not clocked in'
                                    ? 'Not In'
                                    : quickStats.today_attendance.time_in || '--:--'
                            }
                            subtitle={
                                quickStats.today_attendance.hours_worked
                                    ? `${quickStats.today_attendance.hours_worked} hours worked`
                                    : quickStats.today_attendance.status
                            }
                            icon={Clock}
                            linkTo="/employee/attendance"
                            colorScheme="green"
                        />
                        <QuickStatCard
                            title="Pending Requests"
                            value={quickStats.pending_requests_count}
                            subtitle="Awaiting approval"
                            icon={FileText}
                            linkTo="/employee/leave/history"
                            colorScheme="orange"
                            badge="requests"
                        />
                        <QuickStatCard
                            title="Next Payday"
                            value={quickStats.next_payday.days_until}
                            subtitle={`${format(new Date(quickStats.next_payday.date), 'MMM dd, yyyy')} • ${quickStats.next_payday.period}`}
                            icon={DollarSign}
                            linkTo="/employee/payslips"
                            colorScheme="purple"
                            badge="days"
                        />
                    </div>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Recent Activity - 2 columns */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Recent Activity</CardTitle>
                                <Link href="/employee/leave/history">
                                    <Button variant="ghost" size="sm">
                                        View All
                                    </Button>
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {recentActivity.length > 0 ? (
                                <div className="space-y-4">
                                    {recentActivity.map((activity, index) => {
                                        const config = activityConfig[activity.type as keyof typeof activityConfig] || {
                                            icon: FileText,
                                            color: 'text-gray-600 bg-gray-100 dark:bg-gray-900/30',
                                        };
                                        const Icon = config.icon;
                                        const statusCfg = activity.status
                                            ? statusConfig[activity.status as keyof typeof statusConfig]
                                            : null;
                                        const StatusIcon = statusCfg?.icon;

                                        return (
                                            <div
                                                key={index}
                                                className="flex items-start gap-4 rounded-lg border p-3 hover:bg-muted/50 transition-colors"
                                            >
                                                <div className={`rounded-lg p-2 ${config.color}`}>
                                                    <Icon className="h-4 w-4" />
                                                </div>
                                                <div className="flex-1 space-y-1">
                                                    <div className="flex items-center justify-between">
                                                        <p className="text-sm font-medium">
                                                            {activity.title}
                                                        </p>
                                                        {activity.status && statusCfg && (
                                                            <Badge variant={statusCfg.variant} className="gap-1">
                                                                <StatusIcon className="h-3 w-3" />
                                                                {activity.status}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {activity.description}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {format(new Date(activity.timestamp), 'MMM dd, yyyy • hh:mm a')}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <FileText className="h-12 w-12 text-muted-foreground/50 mb-4" />
                                    <p className="text-sm text-muted-foreground">
                                        No recent activity to display
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Quick Actions - 1 column */}
                    <div className="space-y-6">
                        {/* Quick Actions Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Link href="/employee/leave/request">
                                    <Button variant="outline" className="w-full justify-start" size="sm">
                                        <Calendar className="h-4 w-4 mr-2" />
                                        Apply for Leave
                                    </Button>
                                </Link>
                                <Link href="/employee/payslips">
                                    <Button variant="outline" className="w-full justify-start" size="sm">
                                        <DollarSign className="h-4 w-4 mr-2" />
                                        View Payslips
                                    </Button>
                                </Link>
                                <Link href="/employee/attendance">
                                    <Button variant="outline" className="w-full justify-start" size="sm">
                                        <Clock className="h-4 w-4 mr-2" />
                                        View Attendance
                                    </Button>
                                </Link>
                                <Link href="/employee/profile">
                                    <Button variant="outline" className="w-full justify-start" size="sm">
                                        <User className="h-4 w-4 mr-2" />
                                        Update Profile
                                    </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        {/* Notifications Preview Card */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Bell className="h-4 w-4" />
                                        Notifications
                                    </CardTitle>
                                    <Link href="/employee/notifications">
                                        <Button variant="ghost" size="sm">
                                            View All
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3 rounded-lg border p-3 bg-muted/30">
                                        <CalendarCheck className="h-4 w-4 text-blue-600 mt-0.5" />
                                        <div className="flex-1 space-y-1">
                                            <p className="text-sm font-medium">
                                                Leave request updates
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Check your notifications for leave request status updates
                                            </p>
                                        </div>
                                    </div>
                                    <Link href="/employee/notifications">
                                        <Button variant="outline" size="sm" className="w-full">
                                            View All Notifications
                                        </Button>
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Help Card */}
                        <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-900/10">
                            <CardContent className="pt-6">
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                                            Need Help?
                                        </h3>
                                    </div>
                                    <p className="text-sm text-blue-800 dark:text-blue-200">
                                        Contact HR Staff for assistance with leave requests, attendance issues, 
                                        or profile updates.
                                    </p>
                                    <p className="text-xs text-blue-700 dark:text-blue-300">
                                        Session timeout: 30 minutes of inactivity
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </Card>
            </div>
            </div>
        </AppLayout>
    );
}
