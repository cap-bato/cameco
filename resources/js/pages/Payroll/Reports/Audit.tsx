import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { PayrollAuditPageProps } from '@/types/payroll-pages';
import { AuditLogTable } from '@/components/payroll/audit-log-table';
import { ChangeHistoryComponent } from '@/components/payroll/change-history';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Download, RotateCcw, Search, Filter } from 'lucide-react';
import { useState, useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: '/payroll/reports',
    },
    {
        title: 'Audit Trail',
        href: '/payroll/reports/audit',
    },
];

export default function AuditTrailIndex({
    auditLogs,
    changeHistory,
    filters,
}: PayrollAuditPageProps) {
    const [activeTab, setActiveTab] = useState<'logs' | 'changes'>('logs');
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
    const [selectedAction, setSelectedAction] = useState(filters.action?.[0] ?? '');
    const [selectedEntity, setSelectedEntity] = useState(filters.entity_type?.[0] ?? '');
    const [selectedUser, setSelectedUser] = useState(filters.user_id?.[0]?.toString() ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_range?.from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_range?.to ?? '');

    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Known filter options — server controls actual filtering
    const actionOptions = [
        { value: 'created', label: 'Created' },
        { value: 'calculated', label: 'Calculated' },
        { value: 'adjusted', label: 'Adjusted' },
        { value: 'approved', label: 'Approved' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'finalized', label: 'Finalized' },
    ];

    const entityOptions = [
        { value: 'PayrollPeriod', label: 'Payroll Period' },
        { value: 'PayrollCalculation', label: 'Payroll Calculation' },
    ];

    // Derive unique users from the current server-filtered result set
    const uniqueUsers = Array.from(
        new Map(auditLogs.map((log) => [log.user_id, log.user_name] as [number, string])).entries(),
    ).filter(([id]) => id > 0);

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const applyFilters = (action: string, entity: string, user: string, search: string, from: string, to: string) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const params: Record<string, any> = {};
        if (action) params.action = action;
        if (entity) params.entity_type = entity;
        if (user) params.user_id = user;
        if (search) params.search = search;
        if (from && to) params.date_range = { from, to };
        router.get('/payroll/reports/audit', params, { preserveState: true, replace: true });
    };

    const handleSearchChange = (value: string) => {
        setSearchTerm(value);
        if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
        searchDebounceRef.current = setTimeout(() => {
            applyFilters(selectedAction, selectedEntity, selectedUser, value, dateFrom, dateTo);
        }, 400);
    };

    const handleActionChange = (value: string) => {
        setSelectedAction(value);
        applyFilters(value, selectedEntity, selectedUser, searchTerm, dateFrom, dateTo);
    };

    const handleEntityChange = (value: string) => {
        setSelectedEntity(value);
        applyFilters(selectedAction, value, selectedUser, searchTerm, dateFrom, dateTo);
    };

    const handleUserChange = (value: string) => {
        setSelectedUser(value);
        applyFilters(selectedAction, selectedEntity, value, searchTerm, dateFrom, dateTo);
    };

    const handleDateChange = (from: string, to: string) => {
        setDateFrom(from);
        setDateTo(to);
        applyFilters(selectedAction, selectedEntity, selectedUser, searchTerm, from, to);
    };

    const handleReset = () => {
        setSearchTerm('');
        setSelectedAction('');
        setSelectedEntity('');
        setSelectedUser('');
        setDateFrom('');
        setDateTo('');
        router.get('/payroll/reports/audit', {}, { preserveState: true, replace: true });
    };

    const hasActiveFilters = !!(searchTerm || selectedAction || selectedEntity || selectedUser || dateFrom);

    // Calculate audit statistics from the server-filtered result set
    const totalLogs = auditLogs.length;
    const logsToday = auditLogs.filter((log) => {
        const logDate = new Date(log.timestamp);
        const today = new Date();
        return logDate.toDateString() === today.toDateString();
    }).length;

    const uniqueUsersCount = new Set(auditLogs.map((log) => log.user_id)).size;
    const uniqueEntitiesModified = new Set(auditLogs.map((log) => log.entity_id)).size;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Trail & History" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight">Audit Trail & History</h1>
                        <p className="text-muted-foreground">
                            Track all payroll system changes and user actions for compliance and audit purposes
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm">
                            <Download className="h-4 w-4 mr-2" />
                            Export
                        </Button>
                        <Button variant="outline" size="sm">
                            <RotateCcw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card className="border-0 bg-white p-4">
                        <p className="text-sm font-medium text-gray-600">Total Audit Logs</p>
                        <p className="mt-2 text-3xl font-bold text-gray-900">{totalLogs}</p>
                        <p className="mt-1 text-xs text-gray-500">Complete record</p>
                    </Card>

                    <Card className="border-0 bg-white p-4">
                        <p className="text-sm font-medium text-gray-600">Changes Today</p>
                        <p className="mt-2 text-3xl font-bold text-gray-900">{logsToday}</p>
                        <p className="mt-1 text-xs text-gray-500">Since midnight</p>
                    </Card>

                    <Card className="border-0 bg-white p-4">
                        <p className="text-sm font-medium text-gray-600">Active Users</p>
                        <p className="mt-2 text-3xl font-bold text-gray-900">{uniqueUsersCount}</p>
                        <p className="mt-1 text-xs text-gray-500">System users</p>
                    </Card>

                    <Card className="border-0 bg-white p-4">
                        <p className="text-sm font-medium text-gray-600">Entities Modified</p>
                        <p className="mt-2 text-3xl font-bold text-gray-900">{uniqueEntitiesModified}</p>
                        <p className="mt-1 text-xs text-gray-500">Unique records</p>
                    </Card>
                </div>

                {/* Filters */}
                <Card className="border-0 bg-white p-4">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                                <Filter className="h-4 w-4" />
                                Filters
                            </h3>
                            {hasActiveFilters && (
                                <button
                                    onClick={handleReset}
                                    className="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    Clear all
                                </button>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                            {/* Search */}
                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-2">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        placeholder="Entity, user, changes..."
                                        value={searchTerm}
                                        onChange={(e) => handleSearchChange(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>

                            {/* Action Filter */}
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">Action</label>
                                <select
                                    value={selectedAction}
                                    onChange={(e) => handleActionChange(e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white"
                                >
                                    <option value="">All Actions</option>
                                    {actionOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Entity Type Filter */}
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">Entity Type</label>
                                <select
                                    value={selectedEntity}
                                    onChange={(e) => handleEntityChange(e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white"
                                >
                                    <option value="">All Entities</option>
                                    {entityOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* User Filter */}
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">User</label>
                                <select
                                    value={selectedUser}
                                    onChange={(e) => handleUserChange(e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white"
                                >
                                    <option value="">All Users</option>
                                    {uniqueUsers.map(([userId, userName]) => (
                                        <option key={userId} value={userId.toString()}>
                                            {userName}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Date Range */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">Date From</label>
                                <Input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => handleDateChange(e.target.value, dateTo)}
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">Date To</label>
                                <Input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => handleDateChange(dateFrom, e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Tabs */}
                <div className="flex gap-2 border-b border-gray-200">
                    <button
                        onClick={() => setActiveTab('logs')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'logs'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Audit Logs ({auditLogs.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('changes')}
                        className={`px-4 py-2 font-medium ${
                            activeTab === 'changes'
                                ? 'border-b-2 border-blue-600 text-blue-600'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Change History ({changeHistory.length})
                    </button>
                </div>

                {/* Tab Content */}
                <div className="mt-6">
                    {activeTab === 'logs' && auditLogs.length === 0 && (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-lg font-medium text-gray-500">No audit logs found</p>
                            <p className="mt-1 text-sm text-gray-400">
                                {hasActiveFilters
                                    ? 'Try adjusting or clearing your filters.'
                                    : 'No payroll activity has been recorded yet.'}
                            </p>
                        </div>
                    )}
                    {activeTab === 'logs' && auditLogs.length > 0 && (
                        <AuditLogTable
                            logs={auditLogs}
                            onRowClick={() => {}}
                        />
                    )}

                    {activeTab === 'changes' && changeHistory.length === 0 && (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <p className="text-lg font-medium text-gray-500">No change history found</p>
                            <p className="mt-1 text-sm text-gray-400">
                                {hasActiveFilters
                                    ? 'Try adjusting or clearing your filters.'
                                    : 'No payroll status changes have been recorded yet.'}
                            </p>
                        </div>
                    )}
                    {activeTab === 'changes' && changeHistory.length > 0 && (
                        <ChangeHistoryComponent
                            changes={changeHistory}
                            entityType="PayrollPeriod"
                            onFilterChange={() => {}}
                        />
                    )}
                </div>

                {/* Footer Info */}
                <div className="text-xs text-gray-600 border-t border-gray-200 pt-4">
                    <p>
                        <strong>Note:</strong> This audit trail maintains a complete record of all payroll system changes for compliance and security purposes. Rollback functionality is available for authorized administrators only.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
