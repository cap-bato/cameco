import { Head, Link, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { 
    ArrowLeft, 
    AlertTriangle, 
    AlertCircle,
    CheckCircle2,
    Clock,
    RefreshCw
} from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface InactiveBadge {
    id: string;
    card_uid: string;
    card_type: string;
    employee_id: string;
    employee_name: string;
    employee_number: string;
    department: string;
    issued_at: string;
    last_used_at: string;
    days_inactive: number;
    alert_level: 'warning' | 'critical';
    badge_status: string;
    issued_by_name: string;
}

interface InactiveBadgesPageProps {
    badges: {
        data: InactiveBadge[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    stats: {
        total_inactive: number;
        critical_count: number;
        warning_count: number;
        average_days_inactive: number;
        percentage_inactive: number;
    };
    filters: {
        sort_by: string;
        sort_order: string;
        per_page: number;
    };
    error?: string;
}

export default function InactiveBadgesPage({ 
    badges, 
    stats, 
    filters,
    error 
}: InactiveBadgesPageProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [sortBy, setSortBy] = useState(filters.sort_by);
    const [sortOrder, setSortOrder] = useState(filters.sort_order);
    const [perPage, setPerPage] = useState(filters.per_page);

    const breadcrumbs = [
        { title: 'HR', href: '/hr' },
        { title: 'Timekeeping', href: '/hr/timekeeping' },
        { title: 'Badges', href: '/hr/timekeeping/badges' },
        { title: 'Inactive Badges', href: '/hr/timekeeping/badges/reports/inactive' },
    ];

    const handleRefresh = useCallback(async () => {
        setIsRefreshing(true);
        router.get('/hr/timekeeping/badges/reports/inactive', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        });
        setTimeout(() => setIsRefreshing(false), 1000);
    }, [sortBy, sortOrder, perPage]);

    const handleSortChange = useCallback((value: string) => {
        setSortBy(value);
    }, []);

    const handleSortOrderChange = useCallback((value: string) => {
        setSortOrder(value);
    }, []);

    const handlePerPageChange = useCallback((value: string) => {
        setPerPage(Number(value));
    }, []);

    const handlePageChange = useCallback((page: number) => {
        router.get('/hr/timekeeping/badges/reports/inactive', {
            page,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        });
    }, [sortBy, sortOrder, perPage]);

    const getAlertLevelBadge = (level: string) => {
        if (level === 'critical') {
            return (
                <Badge className="bg-red-100 text-red-800 hover:bg-red-200">
                    <AlertTriangle className="w-3 h-3 mr-1" />
                    Critical (60+ days)
                </Badge>
            );
        }
        return (
            <Badge className="bg-yellow-100 text-yellow-800 hover:bg-yellow-200">
                <Clock className="w-3 h-3 mr-1" />
                Warning (30-59 days)
            </Badge>
        );
    };

    const getStatusBadge = (status: string) => {
        if (status === 'active') {
            return (
                <Badge className="bg-green-100 text-green-800 hover:bg-green-200">
                    <CheckCircle2 className="w-3 h-3 mr-1" />
                    Active
                </Badge>
            );
        }
        return (
            <Badge variant="secondary">
                Inactive
            </Badge>
        );
    };

    const totalPages = badges.last_page;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inactive Badges Report" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                            <AlertTriangle className="w-8 h-8 text-amber-600" />
                            Inactive RFID Badges Report
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            Badges not scanned for 30 or more days
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing}
                        >
                            <RefreshCw className={`w-4 h-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
                            {isRefreshing ? 'Refreshing...' : 'Refresh'}
                        </Button>
                        <Link href="/hr/timekeeping/badges">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                Back to Badges
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Error Alert */}
                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {/* Summary Stats */}
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                    {/* Total Inactive */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Total Inactive</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_inactive}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.percentage_inactive}% of active badges
                            </p>
                        </CardContent>
                    </Card>

                    {/* Critical */}
                    <Card className="border-red-200 bg-red-50">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium flex items-center gap-1">
                                <AlertTriangle className="w-4 h-4 text-red-600" />
                                Critical
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.critical_count}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                60+ days without scan
                            </p>
                        </CardContent>
                    </Card>

                    {/* Warning */}
                    <Card className="border-yellow-200 bg-yellow-50">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium flex items-center gap-1">
                                <Clock className="w-4 h-4 text-yellow-600" />
                                Warning
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{stats.warning_count}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                30-59 days without scan
                            </p>
                        </CardContent>
                    </Card>

                    {/* Average */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Average Days Inactive</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.average_days_inactive}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                days since last scan
                            </p>
                        </CardContent>
                    </Card>

                    {/* Action */}
                    <Card className="bg-blue-50 border-blue-200">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">Recommended Action</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-blue-900">
                                Contact employees to verify badge status and issue replacements if needed.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter Controls */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="text-sm font-medium block mb-2">Sort By</label>
                                <Select value={sortBy} onValueChange={handleSortChange}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="days_inactive">Days Inactive</SelectItem>
                                        <SelectItem value="employee_name">Employee Name</SelectItem>
                                        <SelectItem value="department">Department</SelectItem>
                                        <SelectItem value="card_uid">Card UID</SelectItem>
                                        <SelectItem value="last_used_at">Last Used</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="text-sm font-medium block mb-2">Sort Order</label>
                                <Select value={sortOrder} onValueChange={handleSortOrderChange}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="desc">Descending</SelectItem>
                                        <SelectItem value="asc">Ascending</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="text-sm font-medium block mb-2">Per Page</label>
                                <Select value={perPage.toString()} onValueChange={handlePerPageChange}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="25">25</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                        <SelectItem value="100">100</SelectItem>
                                        <SelectItem value="250">250</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-end">
                                <Button onClick={handleRefresh} className="w-full">
                                    Apply Filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Inactive Badges Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Inactive Badges ({badges.total})</CardTitle>
                        <CardDescription>
                            Showing {Math.min(perPage, badges.data.length)} of {badges.total} inactive badges
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {badges.data.length === 0 ? (
                            <div className="text-center py-12">
                                <CheckCircle2 className="w-12 h-12 text-green-500 mx-auto mb-4" />
                                <h3 className="text-lg font-semibold">All Badges Active!</h3>
                                <p className="text-muted-foreground">
                                    No inactive badges found. All badges have been used within the last 30 days.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Card UID</TableHead>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>Last Used</TableHead>
                                            <TableHead className="text-right">Days Inactive</TableHead>
                                            <TableHead>Alert Level</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Card Type</TableHead>
                                            <TableHead>Issued By</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {badges.data.map((badge) => (
                                            <TableRow key={badge.id}>
                                                <TableCell className="font-mono text-sm">
                                                    {badge.card_uid}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <p className="font-medium">{badge.employee_name}</p>
                                                        <p className="text-xs text-muted-foreground">{badge.employee_number}</p>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{badge.department}</TableCell>
                                                <TableCell>
                                                    {badge.last_used_at === 'Never' ? (
                                                        <span className="text-muted-foreground italic">Never</span>
                                                    ) : (
                                                        badge.last_used_at
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span className="font-semibold text-lg">
                                                        {badge.days_inactive === 9999 ? '∞' : badge.days_inactive}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {getAlertLevelBadge(badge.alert_level)}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(badge.badge_status)}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {badge.card_type.toUpperCase()}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    {badge.issued_by_name}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {totalPages > 1 && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Page {badges.current_page} of {totalPages}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={badges.current_page === 1}
                                        onClick={() => handlePageChange(badges.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        disabled={badges.current_page === totalPages}
                                        onClick={() => handlePageChange(badges.current_page + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
