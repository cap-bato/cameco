import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Calendar, TrendingUp, TrendingDown, Search, X, ChevronLeft, ChevronRight, ChevronDown, ArrowUpDown } from 'lucide-react';

// Define window with route helper (Ziggy)
interface WindowWithRoute extends Window {
    route?: (name: string, params?: Record<string, string | number>) => string;
}

// Access route helper from window (Ziggy)
const getRoute = (name: string, params?: Record<string, string | number>): string => {
    const win = window as unknown as WindowWithRoute;
    if (typeof window !== 'undefined' && win.route) {
        return win.route(name, params);
    }
    // Fallback to hardcoded URL for this route
    const queryString = params ? '?' + new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)])).toString() : '';
    return `/hr/leave/balances${queryString}`;
};

interface BalanceItem {
    type: string;
    name: string;
    earned: number;
    used: number;
    remaining: number;
    carried_forward: number;
}

interface EmployeeBalance {
    id: number;
    employee_number: string;
    name: string;
    department: string;
    balances: BalanceItem[];
}

interface PaginationData {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from?: number;
    to?: number;
    has_more_pages: boolean;
}

interface LeaveBalancesPageProps {
    balances: EmployeeBalance[];
    pagination?: PaginationData;
    employees?: unknown[];
    selectedYear?: string;
    selectedEmployeeId?: number;
    years?: string[];
    summary?: {
        total_employees: number;
        total_earned: number;
        total_used: number;
        total_remaining: number;
    };
}

type SortColumn = 'employee' | 'leave_type' | 'earned' | 'used' | 'remaining' | 'carried_forward';
type SortDirection = 'asc' | 'desc';

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Leave Management', href: '#' },
    { title: 'Balances', href: '/hr/leave/balances' },
];

function getLeaveTypeColor(type: string): string {
    const colorMap: Record<string, string> = {
        'Vacation Leave': 'bg-blue-100 text-blue-800',
        'Sick Leave': 'bg-red-100 text-red-800',
        'Emergency Leave': 'bg-yellow-100 text-yellow-800',
        'Maternity Leave': 'bg-pink-100 text-pink-800',
        'Paternity Leave': 'bg-pink-100 text-pink-800',
        'Privilege Leave': 'bg-green-100 text-green-800',
        'Bereavement Leave': 'bg-purple-100 text-purple-800',
    };
    return colorMap[type] || 'bg-gray-100 text-gray-800';
}
export default function LeaveBalances({ 
    balances, 
    pagination, 
    summary,
    selectedYear: initialYear,
    years: initialYears,
}: LeaveBalancesPageProps) {
    const employeeBalances = Array.isArray(balances) ? balances : [];
    const paginationData = pagination || {
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
        from: 1,
        to: 0,
        has_more_pages: false,
    };

    const [searchTerm, setSearchTerm] = useState('');
    const [selectedYear, setSelectedYear] = useState((initialYear || new Date().getFullYear()).toString());
    const [selectedLeaveType, setSelectedLeaveType] = useState('all');
    const [perPage, setPerPage] = useState(paginationData.per_page.toString());
    const [expandedEmployees, setExpandedEmployees] = useState<Set<number>>(new Set());
    const [sortColumn, setSortColumn] = useState<SortColumn>('employee');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
    const [showOnlyWithBalance, setShowOnlyWithBalance] = useState(false);
    const [showOnlyWithUsage, setShowOnlyWithUsage] = useState(false);
    const [selectedDepartment, setSelectedDepartment] = useState('all');

    // Flatten balances for filtering (client-side filtering only)
    const flatBalances = employeeBalances.flatMap((emp) =>
        emp.balances.map((bal) => ({
            ...bal,
            employee_id: emp.id,
            employee_number: emp.employee_number,
            employee_name: emp.name,
            department: emp.department,
            leave_type: bal.name,
        }))
    );

    const years = initialYears || Array.from({ length: 5 }, (_, i) => (new Date().getFullYear() - i).toString());
    const leaveTypes = ['all', ...Array.from(new Set(flatBalances.map((b) => b.leave_type)))];
    const departments = ['all', ...Array.from(new Set(employeeBalances.map((emp) => emp.department)))].filter(Boolean);

    // Toggle employee expansion
    const toggleEmployeeExpanded = (employeeId: number) => {
        setExpandedEmployees((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(employeeId)) {
                newSet.delete(employeeId);
            } else {
                newSet.add(employeeId);
            }
            return newSet;
        });
    };

    // Sort balances by column
    const sortBalances = (balances: typeof flatBalances, sortCol: SortColumn, sortDir: SortDirection) => {
        return [...balances].sort((a, b) => {
            let aVal: string | number = '';
            let bVal: string | number = '';
            let isNumeric = false;

            switch (sortCol) {
                case 'employee':
                    aVal = a.employee_name?.toLowerCase() || '';
                    bVal = b.employee_name?.toLowerCase() || '';
                    break;
                case 'leave_type':
                    aVal = a.leave_type?.toLowerCase() || '';
                    bVal = b.leave_type?.toLowerCase() || '';
                    break;
                case 'earned':
                    aVal = a.earned || 0;
                    bVal = b.earned || 0;
                    isNumeric = true;
                    break;
                case 'used':
                    aVal = a.used || 0;
                    bVal = b.used || 0;
                    isNumeric = true;
                    break;
                case 'remaining':
                    aVal = a.remaining || 0;
                    bVal = b.remaining || 0;
                    isNumeric = true;
                    break;
                case 'carried_forward':
                    aVal = a.carried_forward || 0;
                    bVal = b.carried_forward || 0;
                    isNumeric = true;
                    break;
                default:
                    return 0;
            }

            if (isNumeric) {
                return sortDir === 'asc' ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number);
            }
            return sortDir === 'asc' ? (aVal as string).localeCompare(bVal as string) : (bVal as string).localeCompare(aVal as string);
        });
    };

    // Handle column sort
    const handleSort = (column: SortColumn) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    // Client-side filtering for search and leave type (but pagination is server-side)
    const filteredBalances = flatBalances.filter((balance) => {
        const matchesSearch =
            searchTerm === '' ||
            balance.employee_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            balance.employee_number?.toLowerCase().includes(searchTerm.toLowerCase());

        const matchesLeaveType = selectedLeaveType === 'all' || balance.leave_type === selectedLeaveType;

        const matchesDepartment = selectedDepartment === 'all' || balance.department === selectedDepartment;

        const matchesBalance = !showOnlyWithBalance || balance.remaining > 0;

        const matchesUsage = !showOnlyWithUsage || balance.used > 0;

        return matchesSearch && matchesLeaveType && matchesDepartment && matchesBalance && matchesUsage;
    });

    // Sort the filtered balances
    const sortedBalances = sortBalances(filteredBalances, sortColumn, sortDirection);

    // Group sorted balances by employee for collapsible rows
    const groupedAndSorted = employeeBalances.map((emp) => ({
        employee: emp,
        balances: sortedBalances.filter((b) => b.employee_id === emp.id),
    })).filter((item) => item.balances.length > 0);

    const handleClearFilters = () => {
        setSearchTerm('');
        setSelectedYear((initialYear || new Date().getFullYear()).toString());
        setSelectedLeaveType('all');
        setSelectedDepartment('all');
        setShowOnlyWithBalance(false);
        setShowOnlyWithUsage(false);
        setSortColumn('employee');
        setSortDirection('asc');
        setExpandedEmployees(new Set());
    };

    const handlePageChange = (newPage: number) => {
        router.get(getRoute('hr.leave.balances'), {
            page: newPage,
            year: selectedYear,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePerPageChange = (newPerPage: string) => {
        setPerPage(newPerPage);
        router.get(getRoute('hr.leave.balances'), {
            page: 1,
            year: selectedYear,
            per_page: newPerPage,
        }, { preserveState: true });
    };

    const handleYearChange = (newYear: string) => {
        setSelectedYear(newYear);
        router.get(getRoute('hr.leave.balances'), {
            year: newYear,
            per_page: perPage,
        }, { preserveState: true });
    };

    const totalEarned = summary?.total_earned || filteredBalances.reduce((sum, b) => sum + (b.earned || 0), 0);
    const totalUsed = summary?.total_used || filteredBalances.reduce((sum, b) => sum + (b.used || 0), 0);
    const totalRemaining = summary?.total_remaining || filteredBalances.reduce((sum, b) => sum + (b.remaining || 0), 0);

    // Render sort indicator
    const renderSortIcon = (column: SortColumn) => {
        if (sortColumn !== column) {
            return <ArrowUpDown className="h-4 w-4 text-muted-foreground ml-1 inline opacity-50" />;
        }
        return sortDirection === 'asc' ? 
            <ArrowUpDown className="h-4 w-4 text-foreground ml-1 inline" /> : 
            <ArrowUpDown className="h-4 w-4 text-foreground ml-1 inline rotate-180" />;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Balances" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <h1 className="text-3xl font-bold tracking-tight">Leave Balances</h1>
                    </div>
                    <p className="text-muted-foreground">
                        View employee leave balance across all leave types
                    </p>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filters</CardTitle>
                        <CardDescription>Filter leave balances by employee, year, or leave type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                {/* Search */}
                                <div className="space-y-2">
                                    <Label htmlFor="search">Search Employee</Label>
                                    <div className="relative">
                                        <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            placeholder="Name or number..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            className="pl-8"
                                        />
                                    </div>
                                </div>

                                {/* Year Filter */}
                                <div className="space-y-2">
                                    <Label htmlFor="year">Year</Label>
                                    <Select value={selectedYear} onValueChange={handleYearChange}>
                                        <SelectTrigger id="year">
                                            <SelectValue placeholder="Select year" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {years.map((year) => (
                                                <SelectItem key={year} value={year}>
                                                    {year}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Leave Type Filter */}
                                <div className="space-y-2">
                                    <Label htmlFor="leave_type">Leave Type</Label>
                                    <Select value={selectedLeaveType} onValueChange={setSelectedLeaveType}>
                                        <SelectTrigger id="leave_type">
                                            <SelectValue placeholder="All types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {leaveTypes.map((type) => (
                                                <SelectItem key={type} value={type}>
                                                    {type === 'all' ? 'All Types' : type}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Department Filter */}
                                <div className="space-y-2">
                                    <Label htmlFor="department">Department</Label>
                                    <Select value={selectedDepartment} onValueChange={setSelectedDepartment}>
                                        <SelectTrigger id="department">
                                            <SelectValue placeholder="All departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((dept) => (
                                                <SelectItem key={dept} value={dept}>
                                                    {dept === 'all' ? 'All Departments' : dept}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Toggle Filters */}
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="balance_toggle"
                                        checked={showOnlyWithBalance}
                                        onChange={(e) => setShowOnlyWithBalance(e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300"
                                    />
                                    <Label htmlFor="balance_toggle" className="font-normal cursor-pointer">
                                        Show only with remaining balance
                                    </Label>
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="usage_toggle"
                                        checked={showOnlyWithUsage}
                                        onChange={(e) => setShowOnlyWithUsage(e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300"
                                    />
                                    <Label htmlFor="usage_toggle" className="font-normal cursor-pointer">
                                        Show only with usage
                                    </Label>
                                </div>

                                <div className="flex justify-end">
                                    <Button variant="outline" onClick={handleClearFilters} className="w-full md:w-auto">
                                        <X className="h-4 w-4 mr-2" />
                                        Clear Filters
                                    </Button>
                                </div>
                            </div>

                            <div className="flex items-center justify-between text-sm text-muted-foreground border-t pt-4">
                                <span>
                                    Showing {paginationData.from || 0} to {paginationData.to || 0} of {paginationData.total} employees
                                </span>
                                <span>
                                    Records per page: 
                                    <Select value={perPage} onValueChange={handlePerPageChange}>
                                        <SelectTrigger className="w-20 ml-2 inline-flex">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="10">10</SelectItem>
                                            <SelectItem value="25">25</SelectItem>
                                            <SelectItem value="50">50</SelectItem>
                                            <SelectItem value="100">100</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                <span>Total Earned</span>
                                <TrendingUp className="h-4 w-4 text-green-600" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalEarned.toFixed(1)} days</div>
                            <p className="text-xs text-muted-foreground mt-1">Across all leave types</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                <span>Total Used</span>
                                <TrendingDown className="h-4 w-4 text-blue-600" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalUsed.toFixed(1)} days</div>
                            <p className="text-xs text-muted-foreground mt-1">Already consumed</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center justify-between text-sm font-medium text-muted-foreground">
                                <span>Total Remaining</span>
                                <Calendar className="h-4 w-4 text-amber-600" />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{totalRemaining.toFixed(1)} days</div>
                            <p className="text-xs text-muted-foreground mt-1">Available to use</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Leave Balances Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Leave Balance Details</CardTitle>
                        <CardDescription>
                            Detailed breakdown of leave balances by type and employee
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sortedBalances && sortedBalances.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b">
                                        <tr>
                                            <th className="text-left py-3 px-4 font-semibold w-8"></th>
                                            <th 
                                                className="text-left py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('employee')}
                                            >
                                                <div className="flex items-center">
                                                    Employee
                                                    {renderSortIcon('employee')}
                                                </div>
                                            </th>
                                            <th 
                                                className="text-left py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('leave_type')}
                                            >
                                                <div className="flex items-center">
                                                    Leave Type
                                                    {renderSortIcon('leave_type')}
                                                </div>
                                            </th>
                                            <th 
                                                className="text-right py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('earned')}
                                            >
                                                <div className="flex items-center justify-end">
                                                    Earned
                                                    {renderSortIcon('earned')}
                                                </div>
                                            </th>
                                            <th 
                                                className="text-right py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('used')}
                                            >
                                                <div className="flex items-center justify-end">
                                                    Used
                                                    {renderSortIcon('used')}
                                                </div>
                                            </th>
                                            <th 
                                                className="text-right py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('remaining')}
                                            >
                                                <div className="flex items-center justify-end">
                                                    Remaining
                                                    {renderSortIcon('remaining')}
                                                </div>
                                            </th>
                                            <th 
                                                className="text-right py-3 px-4 font-semibold cursor-pointer hover:bg-muted/50"
                                                onClick={() => handleSort('carried_forward')}
                                            >
                                                <div className="flex items-center justify-end">
                                                    Carried Forward
                                                    {renderSortIcon('carried_forward')}
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {groupedAndSorted.flatMap((group) => [
                                            // Employee Header Row
                                            <tr key={`emp-${group.employee.id}`} className="border-b bg-muted/30 hover:bg-muted/50">
                                                <td className="py-3 px-4">
                                                    <button
                                                        onClick={() => toggleEmployeeExpanded(group.employee.id)}
                                                        className="inline-flex items-center justify-center"
                                                    >
                                                        <ChevronDown 
                                                            className={`h-5 w-5 transition-transform ${
                                                                expandedEmployees.has(group.employee.id) ? 'rotate-0' : '-rotate-90'
                                                            }`}
                                                        />
                                                    </button>
                                                </td>
                                                <td className="py-3 px-4 font-bold text-foreground">
                                                    <div className="flex flex-col">
                                                        <span>{group.employee.name}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {group.employee.employee_number} • {group.employee.department}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4 text-muted-foreground text-right">
                                                    {group.balances.length} leave {group.balances.length === 1 ? 'type' : 'types'}
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    <span className="font-semibold">
                                                        {group.balances.reduce((sum, b) => sum + (b.earned || 0), 0).toFixed(1)}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 text-right font-semibold text-red-600">
                                                    {group.balances.reduce((sum, b) => sum + (b.used || 0), 0).toFixed(1)}
                                                </td>
                                                <td className="py-3 px-4 text-right font-semibold text-green-600">
                                                    {group.balances.reduce((sum, b) => sum + (b.remaining || 0), 0).toFixed(1)}
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    {group.balances.reduce((sum, b) => sum + (b.carried_forward || 0), 0).toFixed(1)}
                                                </td>
                                            </tr>,
                                            // Leave Type Sub-rows (shown when expanded)
                                            ...(expandedEmployees.has(group.employee.id) 
                                                ? group.balances.map((balance, idx) => (
                                                    <tr key={`${group.employee.id}-${balance.type}-${idx}`} className="border-b hover:bg-muted/50">
                                                        <td className="py-3 px-4"></td>
                                                        <td className="py-3 px-4 pl-12 text-muted-foreground"></td>
                                                        <td className="py-3 px-4">
                                                            <Badge className={getLeaveTypeColor(balance.leave_type || '')}>
                                                                {balance.leave_type || 'N/A'}
                                                            </Badge>
                                                        </td>
                                                        <td className="text-right py-3 px-4">{balance.earned?.toFixed(1) || '0.0'}</td>
                                                        <td className="text-right py-3 px-4 text-red-600">{balance.used?.toFixed(1) || '0.0'}</td>
                                                        <td className="text-right py-3 px-4 font-semibold text-green-600">{balance.remaining?.toFixed(1) || '0.0'}</td>
                                                        <td className="text-right py-3 px-4">{balance.carried_forward?.toFixed(1) || '0.0'}</td>
                                                    </tr>
                                                ))
                                                : []
                                            ),
                                        ])}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-2" />
                                <p className="text-muted-foreground">No leave balance data found</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    Try adjusting your filters or leave balances will appear here once they are configured
                                </p>
                            </div>
                        )}

                        {/* Pagination Controls */}
                        {paginationData.total > 0 && (
                            <div className="mt-6 flex items-center justify-between border-t pt-4">
                                <div className="text-sm text-muted-foreground">
                                    Page {paginationData.current_page} of {paginationData.last_page}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(paginationData.current_page - 1)}
                                        disabled={paginationData.current_page === 1}
                                    >
                                        <ChevronLeft className="h-4 w-4 mr-1" />
                                        Previous
                                    </Button>
                                    <div className="flex items-center gap-1">
                                        {Array.from({ length: Math.min(5, paginationData.last_page) }, (_, i) => {
                                            let pageNum;
                                            if (paginationData.last_page <= 5) {
                                                pageNum = i + 1;
                                            } else if (paginationData.current_page <= 3) {
                                                pageNum = i + 1;
                                            } else if (paginationData.current_page >= paginationData.last_page - 2) {
                                                pageNum = paginationData.last_page - 4 + i;
                                            } else {
                                                pageNum = paginationData.current_page - 2 + i;
                                            }
                                            return (
                                                <Button
                                                    key={pageNum}
                                                    variant={pageNum === paginationData.current_page ? 'default' : 'outline'}
                                                    size="sm"
                                                    onClick={() => handlePageChange(pageNum)}
                                                    className="w-10"
                                                >
                                                    {pageNum}
                                                </Button>
                                            );
                                        })}
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(paginationData.current_page + 1)}
                                        disabled={!paginationData.has_more_pages}
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
        </AppLayout>
    );
}
