import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Download, Filter, X, ChevronLeft, ChevronRight, FileText, User, Calendar, Activity } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

interface AuditLog {
    id: number;
    timestamp: string;
    relative_time: string;
    user_name: string;
    user_email: string;
    action: string;
    log_name: string;
    module: string;
    subject_type: string;
    subject_id: number | null;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    changes_summary: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuditLogsPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    data: AuditLog[];
}

interface AuditLogsTableProps {
    logs: AuditLogsPagination;
    availableUsers: User[];
    filters: {
        date_from?: string;
        date_to?: string;
        user_id?: number;
        module?: string;
    };
}

export function AuditLogsTable({ logs, availableUsers, filters }: AuditLogsTableProps) {
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [userId, setUserId] = useState<string>(filters.user_id?.toString() || '');
    const [module, setModule] = useState(filters.module || '');
    const [showFilters, setShowFilters] = useState(false);
    const [expandedRow, setExpandedRow] = useState<number | null>(null);

    const modules = [
        'Company',
        'Department',
        'Position',
        'LeavePolicy',
        'Holiday',
        'PayrollRules',
        'SystemConfig',
        'ApprovalWorkflow',
        'BusinessRules',
    ];

    const handleApplyFilters = () => {
        const params: Record<string, string | number> = {};

        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        if (userId) params.user_id = userId;
        if (module) params.module = module;

        router.get('/admin/system-config/audit-logs', params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleClearFilters = () => {
        setDateFrom('');
        setDateTo('');
        setUserId('');
        setModule('');
        
        router.get('/admin/system-config/audit-logs', {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleExport = () => {
        const params = new URLSearchParams();

        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (userId) params.append('user_id', userId);
        if (module) params.append('module', module);

        window.location.href = `/admin/system-config/audit-logs/export?${params.toString()}`;
    };

    const handlePageChange = (page: number) => {
        const params: Record<string, string | number> = { page };

        if (dateFrom) params.date_from = dateFrom;
        if (dateTo) params.date_to = dateTo;
        if (userId) params.user_id = userId;
        if (module) params.module = module;

        router.get('/admin/system-config/audit-logs', params, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const toggleRowExpansion = (logId: number) => {
        setExpandedRow(expandedRow === logId ? null : logId);
    };

    const renderValueDiff = (oldValue: unknown, newValue: unknown, key: string) => {
        // Format values
        const formatValue = (value: unknown) => {
            if (value === null || value === undefined) return 'null';
            if (typeof value === 'boolean') return value ? 'true' : 'false';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
        };

        const oldFormatted = formatValue(oldValue);
        const newFormatted = formatValue(newValue);

        if (oldFormatted === newFormatted) {
            return null; // No change
        }

        return (
            <div key={key} className="text-sm py-1">
                <span className="font-medium text-muted-foreground">{key}:</span>{' '}
                <span className="text-red-600 dark:text-red-400 line-through">{oldFormatted}</span>{' '}
                <span className="text-muted-foreground">→</span>{' '}
                <span className="text-green-600 dark:text-green-400">{newFormatted}</span>
            </div>
        );
    };

    const hasActiveFilters = dateFrom || dateTo || userId || module;

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert>
                <Activity className="h-4 w-4" />
                <AlertDescription>
                    <strong>Audit Log:</strong> This read-only log tracks all configuration changes made by Office
                    Administrators. All changes are automatically recorded and cannot be deleted. Use filters to narrow down
                    results by date, user, or module.
                </AlertDescription>
            </Alert>

            {/* Filter Controls */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Filter className="h-5 w-5" />
                                Filters & Export
                            </CardTitle>
                            <CardDescription>Filter audit logs by date range, user, or module</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" onClick={() => setShowFilters(!showFilters)}>
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </Button>
                    </div>
                </CardHeader>
                {showFilters && (
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="date_from">
                                    <Calendar className="h-3 w-3 inline mr-1" />
                                    Date From
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="date_to">
                                    <Calendar className="h-3 w-3 inline mr-1" />
                                    Date To
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="user_id">
                                    <User className="h-3 w-3 inline mr-1" />
                                    User
                                </Label>
                                <Select value={userId} onValueChange={setUserId}>
                                    <SelectTrigger id="user_id">
                                        <SelectValue placeholder="All Users" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Users</SelectItem>
                                        {availableUsers.map((user) => (
                                            <SelectItem key={user.id} value={user.id.toString()}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="module">
                                    <FileText className="h-3 w-3 inline mr-1" />
                                    Module
                                </Label>
                                <Select value={module} onValueChange={setModule}>
                                    <SelectTrigger id="module">
                                        <SelectValue placeholder="All Modules" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Modules</SelectItem>
                                        {modules.map((mod) => (
                                            <SelectItem key={mod} value={mod}>
                                                {mod}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="flex gap-2">
                            <Button onClick={handleApplyFilters} size="sm">
                                <Filter className="h-4 w-4 mr-2" />
                                Apply Filters
                            </Button>
                            {hasActiveFilters && (
                                <Button onClick={handleClearFilters} variant="outline" size="sm">
                                    <X className="h-4 w-4 mr-2" />
                                    Clear Filters
                                </Button>
                            )}
                            <Button onClick={handleExport} variant="outline" size="sm" className="ml-auto">
                                <Download className="h-4 w-4 mr-2" />
                                Export to CSV
                            </Button>
                        </div>

                        {hasActiveFilters && (
                            <div className="flex flex-wrap gap-2">
                                <span className="text-sm text-muted-foreground">Active filters:</span>
                                {dateFrom && (
                                    <Badge variant="secondary">
                                        From: {dateFrom}
                                        <X
                                            className="h-3 w-3 ml-1 cursor-pointer"
                                            onClick={() => setDateFrom('')}
                                        />
                                    </Badge>
                                )}
                                {dateTo && (
                                    <Badge variant="secondary">
                                        To: {dateTo}
                                        <X
                                            className="h-3 w-3 ml-1 cursor-pointer"
                                            onClick={() => setDateTo('')}
                                        />
                                    </Badge>
                                )}
                                {userId && (
                                    <Badge variant="secondary">
                                        User: {availableUsers.find((u) => u.id.toString() === userId)?.name}
                                        <X
                                            className="h-3 w-3 ml-1 cursor-pointer"
                                            onClick={() => setUserId('')}
                                        />
                                    </Badge>
                                )}
                                {module && (
                                    <Badge variant="secondary">
                                        Module: {module}
                                        <X
                                            className="h-3 w-3 ml-1 cursor-pointer"
                                            onClick={() => setModule('')}
                                        />
                                    </Badge>
                                )}
                            </div>
                        )}
                    </CardContent>
                )}
            </Card>

            {/* Audit Logs Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Audit Logs</CardTitle>
                    <CardDescription>
                        Showing {logs.from || 0} to {logs.to || 0} of {logs.total} log entries
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[180px]">Timestamp</TableHead>
                                    <TableHead className="w-[150px]">User</TableHead>
                                    <TableHead className="w-[120px]">Module</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Changes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                            No audit logs found. Try adjusting your filters.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    logs.data.map((log) => (
                                        <>
                                            <TableRow
                                                key={log.id}
                                                className="cursor-pointer hover:bg-muted/50"
                                                onClick={() => toggleRowExpansion(log.id)}
                                            >
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium">{log.timestamp}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {log.relative_time}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium">{log.user_name}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {log.user_email}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{log.module}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">{log.action}</TableCell>
                                                <TableCell>
                                                    <span className="text-sm text-muted-foreground">
                                                        {log.changes_summary}
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                            {expandedRow === log.id && (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="bg-muted/30 p-4">
                                                        <div className="space-y-3">
                                                            <div className="flex items-center gap-2 text-sm font-semibold">
                                                                <Activity className="h-4 w-4" />
                                                                Detailed Changes
                                                            </div>
                                                            <div className="grid gap-2 md:grid-cols-2 border-l-2 border-muted-foreground/20 pl-4">
                                                                {Object.keys(log.new_values).length === 0 ? (
                                                                    <div className="text-sm text-muted-foreground">
                                                                        No detailed changes available
                                                                    </div>
                                                                ) : (
                                                                    Object.keys(log.new_values).map((key) => {
                                                                        const diff = renderValueDiff(
                                                                            log.old_values[key],
                                                                            log.new_values[key],
                                                                            key
                                                                        );
                                                                        return diff;
                                                                    })
                                                                )}
                                                            </div>
                                                            <div className="flex items-center gap-4 text-xs text-muted-foreground pt-2 border-t">
                                                                <span>Log Name: {log.log_name}</span>
                                                                <span>Subject: {log.subject_type}</span>
                                                                {log.subject_id && <span>ID: {log.subject_id}</span>}
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination */}
                    {logs.last_page > 1 && (
                        <div className="flex items-center justify-between mt-4">
                            <div className="text-sm text-muted-foreground">
                                Page {logs.current_page} of {logs.last_page}
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handlePageChange(logs.current_page - 1)}
                                    disabled={logs.current_page === 1}
                                >
                                    <ChevronLeft className="h-4 w-4 mr-1" />
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handlePageChange(logs.current_page + 1)}
                                    disabled={logs.current_page === logs.last_page}
                                >
                                    Next
                                    <ChevronRight className="h-4 w-4 ml-1" />
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
