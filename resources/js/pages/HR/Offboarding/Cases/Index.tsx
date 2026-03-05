import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PermissionGate } from '@/components/permission-gate';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    Download,
    Eye,
    Filter,
    Plus,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { useDebouncedCallback } from 'use-debounce';

interface CaseItem {
    id: number;
    case_number: string;
    employee: {
        id: number;
        name: string;
        employee_number: string;
        department: string;
    };
    separation_type: string;
    status: string;
    status_label: string;
    last_working_day: string;
    hr_coordinator: string | null;
    completion_percentage: number;
    initiated_by: string | null;
    rehire_eligible: boolean | null;
    created_at: string;
}

interface PaginationData {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface StatisticsData {
    total: number;
    pending: number;
    in_progress: number;
    clearance_pending: number;
    completed: number;
    cancelled: number;
    due_this_week: number;
    overdue: number;
}

interface FiltersData {
    status: string;
    separation_type: string;
    search: string;
    per_page: number;
}

interface CaseListProps {
    cases: CaseItem[];
    pagination: PaginationData;
    statistics: StatisticsData;
    filters: FiltersData;
    statusOptions: Record<string, string>;
    separationTypeOptions: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Offboarding', href: '/hr/offboarding' },
    { title: 'Cases', href: '/hr/offboarding/cases' },
];

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
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getSeparationTypeColor = (type: string) => {
    switch (type) {
        case 'resignation':
            return 'bg-blue-50 border-l-4 border-blue-500';
        case 'termination':
            return 'bg-red-50 border-l-4 border-red-500';
        case 'retirement':
            return 'bg-purple-50 border-l-4 border-purple-500';
        case 'end_of_contract':
            return 'bg-orange-50 border-l-4 border-orange-500';
        case 'death':
            return 'bg-gray-50 border-l-4 border-gray-500';
        case 'abscondment':
            return 'bg-pink-50 border-l-4 border-pink-500';
        default:
            return 'bg-gray-50 border-l-4 border-gray-500';
    }
};

export default function CaseList({
    cases,
    pagination,
    statistics,
    filters: initialFilters,
    statusOptions,
    separationTypeOptions,
}: CaseListProps) {
    const [filters, setFilters] = useState(initialFilters);
    const [selectedCases, setSelectedCases] = useState<Set<number>>(new Set());

    const debouncedSearch = useDebouncedCallback((searchTerm: string) => {
        router.get('/hr/offboarding/cases', {
            ...filters,
            search: searchTerm,
            page: 1,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setFilters({ ...filters, search: value });
        debouncedSearch(value);
    };

    const handleStatusChange = (status: string) => {
        const newFilters = { ...filters, status };
        setFilters(newFilters);
        router.get('/hr/offboarding/cases', {
            ...newFilters,
            page: 1,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSeparationTypeChange = (separationType: string) => {
        const newFilters = { ...filters, separation_type: separationType };
        setFilters(newFilters);
        router.get('/hr/offboarding/cases', {
            ...newFilters,
            page: 1,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePerPageChange = (perPage: string) => {
        setFilters({ ...filters, per_page: parseInt(perPage) });
        router.get('/hr/offboarding/cases', {
            ...filters,
            per_page: perPage,
            page: 1,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        router.get('/hr/offboarding/cases', {
            ...filters,
            page,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setFilters({
            status: 'all',
            separation_type: 'all',
            search: '',
            per_page: 15,
        });
        router.get('/hr/offboarding/cases', {
            status: 'all',
            separation_type: 'all',
            search: '',
            per_page: 15,
        } as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const hasActiveFilters = useMemo(() => {
        return (
            filters.status !== 'all' ||
            filters.separation_type !== 'all' ||
            filters.search !== ''
        );
    }, [filters]);

    const handleToggleCase = (caseId: number) => {
        const newSelected = new Set(selectedCases);
        if (newSelected.has(caseId)) {
            newSelected.delete(caseId);
        } else {
            newSelected.add(caseId);
        }
        setSelectedCases(newSelected);
    };

    const handleSelectAll = () => {
        if (selectedCases.size === cases.length) {
            setSelectedCases(new Set());
        } else {
            setSelectedCases(new Set(cases.map((c) => c.id)));
        }
    };

    const handleExport = () => {
        const query = new URLSearchParams({
            status: filters.status,
            separation_type: filters.separation_type,
            search: filters.search,
        }).toString();
        window.location.href = `/hr/offboarding/cases/export?${query}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Offboarding Cases" />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Offboarding Cases</h1>
                        <p className="text-muted-foreground mt-1">
                            Manage employee offboarding and separation workflows
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExport}
                            disabled={cases.length === 0}
                        >
                            <Download className="h-4 w-4 mr-2" />
                            Export
                        </Button>
                        <PermissionGate permission="hr.offboarding.create">
                            <Link href="/hr/offboarding/cases/create">
                                <Button>
                                    <Plus className="h-4 w-4 mr-2" />
                                    New Case
                                </Button>
                            </Link>
                        </PermissionGate>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4 lg:grid-cols-8">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.total}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Pending</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {statistics.pending}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">In Progress</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {statistics.in_progress}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Clearance</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">
                                {statistics.clearance_pending}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Completed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {statistics.completed}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Cancelled</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {statistics.cancelled}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">This Week</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {statistics.due_this_week}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-red-200 bg-red-50">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-red-700">Overdue</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-700">
                                {statistics.overdue}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and Search */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-4">
                            {/* Search */}
                            <div className="relative">
                                <Search className="absolute left-2 top-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name, number, or case #..."
                                    value={filters.search}
                                    onChange={handleSearchChange}
                                    className="pl-8"
                                />
                            </div>

                            {/* Status Filter */}
                            <Select value={filters.status} onValueChange={handleStatusChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(statusOptions).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {/* Separation Type Filter */}
                            <Select
                                value={filters.separation_type}
                                onValueChange={handleSeparationTypeChange}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(separationTypeOptions).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {/* Per Page */}
                            <Select
                                value={filters.per_page.toString()}
                                onValueChange={handlePerPageChange}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Per page" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10 per page</SelectItem>
                                    <SelectItem value="15">15 per page</SelectItem>
                                    <SelectItem value="25">25 per page</SelectItem>
                                    <SelectItem value="50">50 per page</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {hasActiveFilters && (
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={handleClearFilters}>
                                    Clear Filters
                                </Button>
                                <span className="text-sm text-muted-foreground">
                                    Showing filtered results
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Cases Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Cases</CardTitle>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Showing {cases.length} of {pagination.total} cases
                                </p>
                            </div>
                            {selectedCases.size > 0 && (
                                <div className="text-sm text-muted-foreground">
                                    {selectedCases.size} selected
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {cases.length === 0 ? (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground">No cases found</p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b bg-muted/50">
                                            <tr>
                                                <th className="py-3 px-4 text-left font-medium">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedCases.size === cases.length && cases.length > 0}
                                                        onChange={handleSelectAll}
                                                        className="rounded border-gray-300"
                                                    />
                                                </th>
                                                <th className="py-3 px-4 text-left font-medium">Case #</th>
                                                <th className="py-3 px-4 text-left font-medium">Employee</th>
                                                <th className="py-3 px-4 text-left font-medium">Department</th>
                                                <th className="py-3 px-4 text-left font-medium">Type</th>
                                                <th className="py-3 px-4 text-left font-medium">Status</th>
                                                <th className="py-3 px-4 text-left font-medium">Last Working Day</th>
                                                <th className="py-3 px-4 text-left font-medium">Progress</th>
                                                <th className="py-3 px-4 text-left font-medium">Coordinator</th>
                                                <th className="py-3 px-4 text-left font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cases.map((caseItem) => (
                                                <tr
                                                    key={caseItem.id}
                                                    className={`border-b transition-colors hover:bg-muted/50 ${getSeparationTypeColor(caseItem.separation_type)}`}
                                                >
                                                    <td className="py-3 px-4">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedCases.has(caseItem.id)}
                                                            onChange={() => handleToggleCase(caseItem.id)}
                                                            className="rounded border-gray-300"
                                                        />
                                                    </td>
                                                    <td className="py-3 px-4 font-mono font-medium">
                                                        {caseItem.case_number}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div>
                                                            <p className="font-medium">{caseItem.employee.name}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                #{caseItem.employee.employee_number}
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-4">{caseItem.employee.department}</td>
                                                    <td className="py-3 px-4">
                                                        <Badge variant="outline">
                                                            {caseItem.separation_type.replace(/_/g, ' ')}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <Badge className={getStatusColor(caseItem.status)}>
                                                            {caseItem.status_label}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 text-sm">
                                                        {caseItem.last_working_day}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-24 bg-gray-200 rounded-full h-2">
                                                                <div
                                                                    className="bg-blue-600 h-2 rounded-full"
                                                                    style={{
                                                                        width: `${caseItem.completion_percentage}%`,
                                                                    }}
                                                                />
                                                            </div>
                                                            <span className="text-xs font-medium">
                                                                {caseItem.completion_percentage}%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-4 text-sm">
                                                        {caseItem.hr_coordinator ? (
                                                            <span>{caseItem.hr_coordinator}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground italic">
                                                                Unassigned
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="flex items-center gap-2">
                                                            <Link
                                                                href={`/hr/offboarding/cases/${caseItem.id}`}
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    title="View details"
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination */}
                                <div className="flex items-center justify-between mt-6 pt-4 border-t">
                                    <div className="text-sm text-muted-foreground">
                                        Page {pagination.current_page} of {pagination.last_page}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pagination.current_page === 1}
                                            onClick={() => handlePageChange(pagination.current_page - 1)}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                        </Button>

                                        {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map(
                                            (page) => {
                                                // Show first, last, current, and surrounding pages
                                                if (
                                                    page === 1 ||
                                                    page === pagination.last_page ||
                                                    (page >= pagination.current_page - 1 &&
                                                        page <= pagination.current_page + 1)
                                                ) {
                                                    return (
                                                        <Button
                                                            key={page}
                                                            variant={
                                                                page === pagination.current_page
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            onClick={() => handlePageChange(page)}
                                                        >
                                                            {page}
                                                        </Button>
                                                    );
                                                } else if (
                                                    page === 2 ||
                                                    page === pagination.last_page - 1
                                                ) {
                                                    return <span key={page}>...</span>;
                                                }
                                                return null;
                                            }
                                        )}

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pagination.current_page === pagination.last_page}
                                            onClick={() => handlePageChange(pagination.current_page + 1)}
                                        >
                                            <ChevronRight className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
