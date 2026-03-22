import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertCircle, AlertTriangle, Info, CheckCircle2, Search, TrendingUp } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
}

interface ErrorLog {
    id: number;
    level: string;
    message: string;
    exception_class: string | null;
    exception_message: string | null;
    file: string | null;
    line: number | null;
    short_file: string;
    url: string | null;
    method: string | null;
    ip_address: string | null;
    user_id: number | null;
    is_resolved: boolean;
    resolution_notes: string | null;
    resolved_at: string | null;
    created_at: string;
    user?: User;
    resolver?: User;
    occurrence_count?: number;
}

interface PaginatedLogs {
    data: ErrorLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Stats {
    total: number;
    unresolved: number;
    critical: number;
    by_level: Record<string, number>;
}

interface TrendData {
    date: string;
    count: number;
}

interface Filters {
    level: string | null;
    is_resolved: boolean | null;
    exception_class: string | null;
    days: number;
    search: string | null;
}

interface Props {
    logs: PaginatedLogs;
    stats: Stats;
    exceptionClasses: string[];
    errorTrend: TrendData[];
    filters: Filters;
}

interface ResolveDialogState {
    open: boolean;
    errorId: number | null;
    errorMessage: string;
    notes: string;
    isSubmitting: boolean;
}

const DEFAULT_RESOLVE_STATE: ResolveDialogState = {
    open: false,
    errorId: null,
    errorMessage: '',
    notes: '',
    isSubmitting: false,
};

export default function ErrorLogs({ logs, stats, exceptionClasses, errorTrend, filters }: Props) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [resolveDialog, setResolveDialog] = useState<ResolveDialogState>(DEFAULT_RESOLVE_STATE);

    const handleFilterChange = (key: string, value: string | number | boolean | null) => {
        const newFilters = { ...filters, [key]: value === 'all' ? '' : value };
        router.get('/system/logs/errors', newFilters, { preserveState: true });
    };

    const handleSearch = () => {
        handleFilterChange('search', searchQuery);
    };

    const openResolveDialog = (error: ErrorLog) => {
        setResolveDialog({
            open: true,
            errorId: error.id,
            errorMessage: error.message,
            notes: '',
            isSubmitting: false,
        });
    };

    const closeResolveDialog = () => {
        if (resolveDialog.isSubmitting) return;
        setResolveDialog(DEFAULT_RESOLVE_STATE);
    };

    const handleResolveSubmit = () => {
        if (!resolveDialog.errorId || !resolveDialog.notes.trim()) return;

        setResolveDialog((prev) => ({ ...prev, isSubmitting: true }));

        router.post(
            `/system/logs/errors/${resolveDialog.errorId}/resolve`,
            { resolution_notes: resolveDialog.notes.trim() },
            {
                onSuccess: () => setResolveDialog(DEFAULT_RESOLVE_STATE),
                onError: () => setResolveDialog((prev) => ({ ...prev, isSubmitting: false })),
            }
        );
    };

    const getLevelBadge = (level: string) => {
        switch (level) {
            case 'critical':
                return (
                    <Badge className="bg-red-500">
                        <AlertCircle className="h-3 w-3 mr-1" />
                        Critical
                    </Badge>
                );
            case 'error':
                return (
                    <Badge className="bg-orange-500">
                        <AlertTriangle className="h-3 w-3 mr-1" />
                        Error
                    </Badge>
                );
            case 'warning':
                return (
                    <Badge className="bg-yellow-500">
                        <AlertTriangle className="h-3 w-3 mr-1" />
                        Warning
                    </Badge>
                );
            case 'notice':
                return (
                    <Badge className="bg-blue-500">
                        <Info className="h-3 w-3 mr-1" />
                        Notice
                    </Badge>
                );
            default:
                return <Badge>{level}</Badge>;
        }
    };

    return (
        <AppLayout>
            <Head title="Error Logs" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight dark:text-foreground">Error Logs</h1>
                    <p className="text-muted-foreground">Monitor and resolve application errors</p>
                </div>

                {/* Statistics */}
                <div className="grid gap-6 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Total Errors</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-xs text-muted-foreground mt-1">Last {filters.days} days</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Unresolved</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{stats.unresolved}</div>
                            <p className="text-xs text-muted-foreground mt-1">Require attention</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Critical</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.critical}</div>
                            <p className="text-xs text-muted-foreground mt-1">High priority</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Trend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <TrendingUp className="h-5 w-5 text-muted-foreground" />
                                <span className="text-sm text-muted-foreground">
                                    {errorTrend.length} days tracked
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4 flex-wrap">
                            <Select
                                value={filters.level || 'all'}
                                onValueChange={(value) => handleFilterChange('level', value)}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter by level" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Levels</SelectItem>
                                    <SelectItem value="critical">Critical</SelectItem>
                                    <SelectItem value="error">Error</SelectItem>
                                    <SelectItem value="warning">Warning</SelectItem>
                                    <SelectItem value="notice">Notice</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.is_resolved === null ? 'all' : filters.is_resolved.toString()}
                                onValueChange={(value) =>
                                    handleFilterChange('is_resolved', value === 'all' ? null : value === 'true')
                                }
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="false">Unresolved</SelectItem>
                                    <SelectItem value="true">Resolved</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.exception_class || 'all'}
                                onValueChange={(value) => handleFilterChange('exception_class', value)}
                            >
                                <SelectTrigger className="w-[240px]">
                                    <SelectValue placeholder="Exception type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Exceptions</SelectItem>
                                    {exceptionClasses.map((cls) => (
                                        <SelectItem key={cls} value={cls}>
                                            {cls.split('\\').pop()}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.days.toString()}
                                onValueChange={(value) => handleFilterChange('days', parseInt(value))}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Time period" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="1">Last 24 hours</SelectItem>
                                    <SelectItem value="7">Last 7 days</SelectItem>
                                    <SelectItem value="30">Last 30 days</SelectItem>
                                    <SelectItem value="90">Last 90 days</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex gap-2 flex-1">
                                <Input
                                    placeholder="Search errors..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                    className="max-w-xs"
                                />
                                <Button onClick={handleSearch} variant="outline" size="icon">
                                    <Search className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Error List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Error Log Entries</CardTitle>
                        <CardDescription>
                            Showing {logs.data.length} of {logs.total} errors
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {logs.data.map((error) => (
                                <div
                                    key={error.id}
                                    className={`border rounded-lg p-4 ${
                                        error.is_resolved ? 'bg-muted/50' : ''
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1 space-y-2">
                                            <div className="flex items-center gap-3">
                                                {getLevelBadge(error.level)}
                                                {error.is_resolved && (
                                                    <Badge className="bg-green-500">
                                                        <CheckCircle2 className="h-3 w-3 mr-1" />
                                                        Resolved
                                                    </Badge>
                                                )}
                                                {error.occurrence_count && error.occurrence_count > 1 && (
                                                    <Badge variant="outline">
                                                        {error.occurrence_count}× occurrences
                                                    </Badge>
                                                )}
                                                <span className="text-sm text-muted-foreground">
                                                    {new Date(error.created_at).toLocaleString()}
                                                </span>
                                            </div>

                                            <div>
                                                <p className="font-medium">{error.message}</p>
                                                {error.exception_message && (
                                                    <p className="text-sm text-muted-foreground mt-1">
                                                        {error.exception_message}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex gap-4 text-xs text-muted-foreground">
                                                {error.exception_class && (
                                                    <span>
                                                        <strong>Exception:</strong> {error.exception_class.split('\\').pop()}
                                                    </span>
                                                )}
                                                {error.file && (
                                                    <span>
                                                        <strong>File:</strong> {error.short_file}
                                                        {error.line && `:${error.line}`}
                                                    </span>
                                                )}
                                                {error.user && (
                                                    <span>
                                                        <strong>User:</strong> {error.user.name}
                                                    </span>
                                                )}
                                            </div>

                                            {error.resolution_notes && (
                                                <div className="mt-2 p-2 bg-green-50 dark:bg-green-900/20 rounded text-sm">
                                                    <strong>Resolution:</strong> {error.resolution_notes}
                                                    {error.resolver && (
                                                        <span className="text-muted-foreground">
                                                            {' '}— {error.resolver.name}
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => router.get(`/system/logs/errors/${error.id}`)}
                                            >
                                                View Details
                                            </Button>
                                            {!error.is_resolved && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => openResolveDialog(error)}
                                                >
                                                    Resolve
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}

                            {logs.data.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    <CheckCircle2 className="h-12 w-12 mx-auto mb-2 opacity-50" />
                                    <p>No errors found for the selected filters</p>
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex items-center justify-between mt-6 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {logs.current_page} of {logs.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={logs.current_page === 1}
                                        onClick={() =>
                                            router.get(`/system/logs/errors`, {
                                                page: logs.current_page - 1,
                                                ...filters,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={logs.current_page === logs.last_page}
                                        onClick={() =>
                                            router.get(`/system/logs/errors`, {
                                                page: logs.current_page + 1,
                                                ...filters,
                                            })
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Resolve Error Dialog */}
            <Dialog open={resolveDialog.open} onOpenChange={(open) => !open && closeResolveDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <CheckCircle2 className="h-5 w-5 text-green-500" />
                            Resolve Error
                        </DialogTitle>
                        <DialogDescription className="line-clamp-2">
                            {resolveDialog.errorMessage}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2 py-2">
                        <Label htmlFor="resolution-notes">
                            Resolution Notes <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="resolution-notes"
                            placeholder="Describe how this error was resolved, what caused it, and any steps taken to prevent recurrence..."
                            rows={4}
                            value={resolveDialog.notes}
                            onChange={(e) =>
                                setResolveDialog((prev) => ({ ...prev, notes: e.target.value }))
                            }
                            disabled={resolveDialog.isSubmitting}
                            className="resize-none"
                        />
                        <p className="text-xs text-muted-foreground">
                            Resolution notes help the team understand what was done and prevent similar issues.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={closeResolveDialog}
                            disabled={resolveDialog.isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleResolveSubmit}
                            disabled={!resolveDialog.notes.trim() || resolveDialog.isSubmitting}
                            className="gap-2"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                            {resolveDialog.isSubmitting ? 'Resolving...' : 'Mark as Resolved'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}