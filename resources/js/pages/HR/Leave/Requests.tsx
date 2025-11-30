import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Calendar, CheckCircle2, XCircle, Clock, Search } from 'lucide-react';
import { LeaveRequestActionModal } from '@/components/hr/leave-request-action-modal';

interface LeaveRequest {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department: string;
    leave_type: string;
    policy_days?: number | null;
    start_date: string;
    end_date: string;
    days_requested: number;
    reason: string;
    status: string;
    supervisor_name: string;
    submitted_at: string;
    supervisor_approved_at: string | null;
    manager_approved_at: string | null;
    hr_processed_at: string | null;
}

interface LeaveRequestsProps {
    requests: LeaveRequest[];
    filters?: Record<string, unknown>;
    employees?: unknown[];
    departments?: unknown[];
    meta?: {
        total_pending: number;
        total_approved: number;
        total_rejected: number;
    };
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Leave Management', href: '#' },
    { title: 'Requests', href: '/hr/leave/requests' },
];

function getStatusColor(status: string): string {
    const colorMap: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        Pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        Approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        Rejected: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-800',
        Cancelled: 'bg-gray-100 text-gray-800',
    };
    return colorMap[status] || 'bg-gray-100 text-gray-800';
}

function getStatusIcon(status: string) {
    const statusLower = status.toLowerCase();
    switch (statusLower) {
        case 'approved':
            return <CheckCircle2 className="h-4 w-4" />;
        case 'rejected':
            return <XCircle className="h-4 w-4" />;
        case 'pending':
            return <Clock className="h-4 w-4" />;
        default:
            return <Calendar className="h-4 w-4" />;
    }
}

export default function LeaveRequests({ requests, meta }: LeaveRequestsProps) {
    const requestsData = Array.isArray(requests) ? requests : [];
    
    const [isActionModalOpen, setIsActionModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<LeaveRequest | null>(null);
    const [modalAction, setModalAction] = useState<'approve' | 'reject' | 'view'>('view');

    const handleApprove = (request: LeaveRequest) => {
        setSelectedRequest(request);
        setModalAction('approve');
        setIsActionModalOpen(true);
    };

    const handleReject = (request: LeaveRequest) => {
        setSelectedRequest(request);
        setModalAction('reject');
        setIsActionModalOpen(true);
    };

    const handleView = (request: LeaveRequest) => {
        setSelectedRequest(request);
        setModalAction('view');
        setIsActionModalOpen(true);
    };
    

    const pendingCount = meta?.total_pending ?? requestsData.filter((r) => r.status?.toLowerCase() === 'pending').length;
    const approvedCount = meta?.total_approved ?? requestsData.filter((r) => r.status?.toLowerCase() === 'approved').length;
    const rejectedCount = meta?.total_rejected ?? requestsData.filter((r) => r.status?.toLowerCase() === 'rejected').length;

    // Tab state for status selection
    const [selectedStatus, setSelectedStatus] = useState<'pending' | 'approved' | 'rejected'>('pending');
    const [searchTerm, setSearchTerm] = useState<string>('');

    const statusTabs = [
        { key: 'pending', label: `Pending (${pendingCount})` },
        { key: 'approved', label: 'Approved' },
        { key: 'rejected', label: 'Rejected' },
    ];

    // access auth shared data to show create button to HR roles
    const { auth } = usePage().props as any;
    const userRoles: string[] = auth?.roles || [];
    const canCreate = userRoles.includes('HR Staff') || userRoles.includes('HR Manager');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Requests" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">

                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <h1 className="text-3xl font-bold tracking-tight">Leave Requests</h1>
                        <div className="flex gap-2">
                            {canCreate && (
                                <Link href="/hr/leave/requests/create">
                                    <Button className="gap-2">Create Request</Button>
                                </Link>
                            )}
                            <Link href="/hr/leave/requests" className="hidden">
                                <Button variant="outline">Refresh</Button>
                            </Link>
                        </div>
                    </div>
                    <p className="text-muted-foreground">
                        Manage and track employee leave requests across the organization
                    </p>
                </div>


                {/* Search + Status Tabs */}
                <div className="flex items-center justify-between gap-4">
                    <div className="w-full max-w-xs">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="leave-search"
                                placeholder="Search by name, employee #, type, reason..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <div className="flex gap-2 border-b mb-4">
                        {statusTabs.map(tab => (
                            <button
                                key={tab.key}
                                className={`px-4 py-2 -mb-px border-b-2 font-medium focus:outline-none transition-colors ${
                                    selectedStatus === tab.key
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-primary'
                                }`}
                                onClick={() => setSelectedStatus(tab.key as 'pending' | 'approved' | 'rejected')}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>


                {/* Leave Requests Table for Selected Status */}
                <div className="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border py-6 shadow-sm">
                    <div className="px-6 pb-2">
                        <h2 className="text-xl font-semibold mb-2 capitalize">{selectedStatus}</h2>
                    </div>
                    <div className="overflow-x-auto px-6">
                        {(() => {
                            const filtered = requestsData.filter(r => {
                                if ((r.status || '').toLowerCase() !== selectedStatus) return false;
                                if (!searchTerm || !searchTerm.trim()) return true;
                                const q = searchTerm.toLowerCase().trim();
                                const haystack = `${r.employee_name || ''} ${r.employee_number || ''} ${r.leave_type || ''} ${r.reason || ''}`.toLowerCase();
                                return haystack.includes(q);
                            });
                            if (filtered.length > 0) {
                                return (
                                    <table className="w-full text-sm">
                                        <thead className="border-b">
                                            <tr>
                                                <th className="text-left py-2 px-2 font-semibold">Employee</th>
                                                <th className="text-left py-2 px-2 font-semibold">Type</th>
                                                <th className="text-left py-2 px-2 font-semibold">From</th>
                                                <th className="text-left py-2 px-2 font-semibold">To</th>
                                                <th className="text-left py-2 px-2 font-semibold">Days</th>
                                                <th className="text-left py-2 px-2 font-semibold">Status</th>
                                                <th className="text-left py-2 px-2 font-semibold">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filtered.map((request) => (
                                                <tr key={request.id} className="border-b hover:bg-muted/50">
                                                        <td className="py-2 px-2 font-medium">{request.employee_name || 'N/A'}</td>
                                                        <td className="py-2 px-2">
                                                            <div className="flex flex-col">
                                                                <span>{request.leave_type || 'N/A'}</span>
                                                                <small className="text-xs text-muted-foreground">{Number(request.days_requested) === 1 ? `${request.days_requested} day` : `${request.days_requested} days`}</small>
                                                            </div>
                                                        </td>
                                                        <td className="py-2 px-2">{request.start_date || 'N/A'}</td>
                                                        <td className="py-2 px-2">{request.end_date || 'N/A'}</td>
                                                        <td className="py-2 px-2">
                                                            <div className="flex flex-col">
                                                                <span>{Math.abs(Number(request.days_requested || 0))}</span>
                                                                {typeof request.policy_days !== 'undefined' && request.policy_days !== null && (
                                                                    <small className="text-xs text-muted-foreground">Effective: {Number(request.policy_days) === 1 ? `${request.policy_days} day` : `${request.policy_days} days`}</small>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="py-2 px-2">
                                                            <div className="flex items-center gap-2">
                                                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(request.status)}`}>
                                                                    {getStatusIcon(request.status)}
                                                                    <span className="ml-2 capitalize">{request.status || 'N/A'}</span>
                                                                </span>
                                                            </div>
                                                        </td>
                                                    <td className="py-2 px-2">
                                                        <div className="flex gap-2">
                                                            {selectedStatus === 'pending' && (
                                                                <>
                                                                    <Button size="sm" variant="outline" className="text-xs" onClick={() => handleApprove(request)}>Approve</Button>
                                                                    <Button size="sm" variant="outline" className="text-xs text-red-600" onClick={() => handleReject(request)}>Reject</Button>
                                                                </>
                                                            )}
                                                            <Button size="sm" variant="ghost" className="text-xs" onClick={() => handleView(request)}>View</Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                );
                            } else {
                                return (
                                    <div className="text-center py-4 text-muted-foreground">No {selectedStatus} requests</div>
                                );
                            }
                        })()}
                    </div>
                </div>

                {/* Action Modal */}
                {isActionModalOpen && selectedRequest && (
                    <LeaveRequestActionModal
                        isOpen={isActionModalOpen}
                        onClose={() => setIsActionModalOpen(false)}
                        request={selectedRequest}
                        action={modalAction}
                    />
                )}
            </div>
        </AppLayout>
    );
}
