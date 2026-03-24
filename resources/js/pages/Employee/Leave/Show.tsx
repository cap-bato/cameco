import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Calendar, Clock, CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { format, parseISO } from 'date-fns';

type LeaveRequestDetail = {
    id: number;
    leave_type: string;
    leave_type_code: string;
    start_date: string;
    end_date: string;
    total_days: number;
    reason: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    created_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    approver_name: string | null;
    approver_role: string | null;
    rejection_reason: string | null;
    cancelled_at: string | null;
};

type EmployeeInfo = {
    id: number;
    employee_number: string;
    full_name: string;
    department: string;
};

type Props = {
    employee: EmployeeInfo;
    request: LeaveRequestDetail;
};

const statusConfig = {
    pending: {
        label: 'Pending',
        icon: Clock,
        className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    },
    approved: {
        label: 'Approved',
        icon: CheckCircle2,
        className: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    },
    rejected: {
        label: 'Rejected',
        icon: XCircle,
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
    cancelled: {
        label: 'Cancelled',
        icon: AlertCircle,
        className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
    },
};

export default function LeaveRequestShow({ employee, request }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/employee/dashboard' },
        { title: 'Leave History', href: '/employee/leave/history' },
        { title: `Request #${request.id}`, href: `/employee/leave/request/${request.id}` },
    ];

    const status = statusConfig[request.status];
    const StatusIcon = status.icon;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Leave Request #${request.id}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Leave Request #{request.id}
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {employee.full_name} • {employee.employee_number} • {employee.department}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/employee/leave/history">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to History
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span>Request Details</span>
                            <Badge className={status.className}>
                                <StatusIcon className="mr-1 h-3 w-3" />
                                {status.label}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Leave Type</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {request.leave_type} ({request.leave_type_code})
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Total Days</p>
                                <p className="font-medium text-gray-900 dark:text-white">{request.total_days}</p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Start Date</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {format(parseISO(request.start_date), 'MMM dd, yyyy')}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">End Date</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {format(parseISO(request.end_date), 'MMM dd, yyyy')}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">Reason</p>
                            <p className="text-sm text-gray-900 dark:text-gray-100">{request.reason}</p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Submitted</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {request.created_at ? format(parseISO(request.created_at), 'MMM dd, yyyy hh:mm a') : 'N/A'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Approver</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {request.approver_name ?? 'Pending assignment'}
                                </p>
                                {request.approver_role && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{request.approver_role}</p>
                                )}
                            </div>
                        </div>

                        {request.status === 'approved' && request.approved_at && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                                Approved on {format(parseISO(request.approved_at), 'MMM dd, yyyy hh:mm a')}
                            </div>
                        )}

                        {request.status === 'rejected' && (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                                <p className="font-medium">Request was rejected.</p>
                                {request.rejection_reason && <p className="mt-1">Reason: {request.rejection_reason}</p>}
                                {request.rejected_at && (
                                    <p className="mt-1 text-xs">{format(parseISO(request.rejected_at), 'MMM dd, yyyy hh:mm a')}</p>
                                )}
                            </div>
                        )}

                        {request.status === 'cancelled' && request.cancelled_at && (
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/20 dark:text-gray-300">
                                Cancelled on {format(parseISO(request.cancelled_at), 'MMM dd, yyyy hh:mm a')}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
