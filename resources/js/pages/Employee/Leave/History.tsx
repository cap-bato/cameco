import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Calendar,
    FileText,
    Download,
    Filter,
    Plus,
    AlertCircle,
    CheckCircle2,
    Clock,
    XCircle,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { useState } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

interface LeaveRequest {
    id: number;
    leave_type: string;
    leave_type_code: string;
    start_date: string;
    end_date: string;
    total_days: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    reason: string;
    approver_name: string | null;
    approver_role: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    cancelled_at: string | null;
    created_at: string;
    has_documents: boolean;
}

interface LeaveHistoryProps {
    requests: LeaveRequest[];
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string;
    };
    filters: {
        status: string;
        leave_type: string;
    };
    leaveTypes: Array<{ code: string; name: string }>;
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
    {
        title: 'Leave Management',
        href: '/employee/leave/balances',
    },
    {
        title: 'Leave History',
        href: '/employee/leave/history',
    },
];

// Status configuration
const statusConfig = {
    pending: {
        label: 'Pending',
        color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        icon: Clock,
    },
    approved: {
        label: 'Approved',
        color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        icon: CheckCircle2,
    },
    rejected: {
        label: 'Rejected',
        color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: XCircle,
    },
    cancelled: {
        label: 'Cancelled',
        color: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        icon: XCircle,
    },
};

// ============================================================================
// Main Component
// ============================================================================

export default function LeaveHistory({
    requests,
    employee,
    filters,
    leaveTypes,
    error,
}: LeaveHistoryProps) {
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
    const [selectedLeaveType, setSelectedLeaveType] = useState(filters.leave_type || 'all');

    // Handle filter changes
    const handleFilterChange = () => {
        router.get('/employee/leave/history', {
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            leave_type: selectedLeaveType !== 'all' ? selectedLeaveType : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Handle export
    const handleExport = (format: 'pdf' | 'excel') => {
        // TODO: Implement export functionality
        alert(`Export to ${format.toUpperCase()} will be available soon`);
    };

    // Calculate statistics
    const stats = {
        total: requests.length,
        pending: requests.filter(r => r.status === 'pending').length,
        approved: requests.filter(r => r.status === 'approved').length,
        rejected: requests.filter(r => r.status === 'rejected').length,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave History" />

            <div className="mb-6 space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Leave History
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            View all your past and current leave requests
                        </p>
                    </div>
                    <Button asChild>
                        <a href="/employee/leave/request">
                            <Plus className="mr-2 h-4 w-4" />
                            Apply for Leave
                        </a>
                    </Button>
                </div>
            </div>

            <div className="space-y-6 p-6">
                {/* Error Message */}
                {error && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/10">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-red-800 dark:text-red-200">
                                <AlertCircle className="h-5 w-5" />
                                <p className="text-sm font-medium">{error}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Employee Info Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                                <FileText className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {employee.full_name}
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {employee.employee_number} • {employee.department}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Statistics Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5 text-green-600 dark:text-green-400" />
                            Request Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                                <p className="text-sm text-blue-600 dark:text-blue-400">Total Requests</p>
                                <p className="mt-2 text-2xl font-bold text-blue-800 dark:text-blue-200">
                                    {stats.total}
                                </p>
                            </div>
                            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-900/10">
                                <p className="text-sm text-yellow-600 dark:text-yellow-400">Pending</p>
                                <p className="mt-2 text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                                    {stats.pending}
                                </p>
                            </div>
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/10">
                                <p className="text-sm text-green-600 dark:text-green-400">Approved</p>
                                <p className="mt-2 text-2xl font-bold text-green-800 dark:text-green-200">
                                    {stats.approved}
                                </p>
                            </div>
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/10">
                                <p className="text-sm text-red-600 dark:text-red-400">Rejected</p>
                                <p className="mt-2 text-2xl font-bold text-red-800 dark:text-red-200">
                                    {stats.rejected}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Filters and Export */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Filter className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Filters & Export
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {/* Status Filter */}
                            <div>
                                <label className="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    Status
                                </label>
                                <select
                                    value={selectedStatus}
                                    onChange={(e) => setSelectedStatus(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            {/* Leave Type Filter */}
                            <div>
                                <label className="block text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    Leave Type
                                </label>
                                <select
                                    value={selectedLeaveType}
                                    onChange={(e) => setSelectedLeaveType(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="all">All Leave Types</option>
                                    {leaveTypes.map((type) => (
                                        <option key={type.code} value={type.code}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex items-end gap-2">
                                <Button 
                                    variant="outline" 
                                    onClick={handleFilterChange}
                                    className="flex-1"
                                >
                                    Apply Filters
                                </Button>
                                <Button 
                                    variant="outline"
                                    onClick={() => handleExport('pdf')}
                                >
                                    <Download className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Leave Requests Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            Leave Requests
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {requests.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Calendar className="h-12 w-12 text-gray-400" />
                                <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                    No leave requests found
                                </p>
                                <Button asChild className="mt-4" variant="outline">
                                    <a href="/employee/leave/request">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Submit Your First Leave Request
                                    </a>
                                </Button>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-200 dark:border-gray-700">
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Leave Type
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Dates
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                                Days
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Approver
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">
                                                Submitted
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                                Details
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {requests.map((request) => {
                                            const StatusIcon = statusConfig[request.status].icon;
                                            
                                            return (
                                                <tr 
                                                    key={request.id}
                                                    className="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800/50"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div>
                                                            <p className="font-medium text-gray-900 dark:text-white">
                                                                {request.leave_type}
                                                            </p>
                                                            {request.has_documents && (
                                                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                                                    📎 Has attachments
                                                                </p>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                        <p>{format(parseISO(request.start_date), 'MMM dd, yyyy')}</p>
                                                        <p className="text-xs">to {format(parseISO(request.end_date), 'MMM dd, yyyy')}</p>
                                                    </td>
                                                    <td className="px-4 py-3 text-center font-medium text-gray-900 dark:text-white">
                                                        {request.total_days}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <div className="flex items-center justify-center gap-1">
                                                            <StatusIcon className="h-4 w-4" />
                                                            <Badge className={statusConfig[request.status].color}>
                                                                {statusConfig[request.status].label}
                                                            </Badge>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {request.approver_name ? (
                                                            <div>
                                                                <p className="text-gray-900 dark:text-white">
                                                                    {request.approver_name}
                                                                </p>
                                                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                                                    {request.approver_role}
                                                                </p>
                                                            </div>
                                                        ) : (
                                                            <span className="text-gray-400">Pending assignment</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                        {format(parseISO(request.created_at), 'MMM dd, yyyy')}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <a href={`/employee/leave/requests/${request.id}`}>
                                                                View
                                                            </a>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Help Section */}
                <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-900/10">
                    <CardContent className="pt-6">
                        <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-100">Need Help?</h4>
                        <p className="mt-2 text-sm text-blue-800 dark:text-blue-200">
                            <strong>Pending requests</strong> are awaiting approval from HR Staff or HR Manager. 
                            Most requests are processed within 24 hours during business days. 
                            If you need to cancel a pending request, click "View" and then "Cancel Request".
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
