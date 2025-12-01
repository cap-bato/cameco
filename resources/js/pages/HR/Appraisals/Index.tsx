import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface AppraisalRecord {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_id: number;
    department_name: string;
    cycle_id: number;
    cycle_name: string;
    status: string;
    status_label: string;
    status_color: string;
    overall_score: number | null;
    attendance_rate: number | null;
    lateness_count: number;
    violation_count: number;
    created_at: string;
    updated_at: string;
}

interface OptionItem {
    id: number;
    name: string;
}

interface FilterState {
    cycle_id: string;
    status: string;
    department_id: string;
    search: string;
}

interface AppraisalsIndexProps {
    appraisals: AppraisalRecord[];
    cycles: OptionItem[];
    departments: OptionItem[];
    filters: FilterState;
}

const statusOptions = [
    { value: 'all', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'acknowledged', label: 'Acknowledged' },
];

export default function AppraisalsIndex({ appraisals = [], cycles = [], departments = [], filters }: AppraisalsIndexProps) {
    const [localFilters, setLocalFilters] = useState<FilterState>(filters ?? {
        cycle_id: '',
        status: '',
        department_id: '',
        search: '',
    });

    const breadcrumb = useMemo(() => [
        { title: 'HR', href: '/hr' },
        { title: 'Appraisals', href: '/hr/appraisals' },
    ], []);

    const handleFilterChange = (field: keyof FilterState, value: string) => {
        const next = { ...localFilters, [field]: value };
        setLocalFilters(next);
        router.get('/hr/appraisals', next, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const filteredAppraisals = useMemo(() => {
        return appraisals.filter((record) => {
            const matchesSearch = !localFilters.search
                || record.employee_name.toLowerCase().includes(localFilters.search.toLowerCase())
                || record.employee_number.toLowerCase().includes(localFilters.search.toLowerCase());
            const matchesCycle = !localFilters.cycle_id
                || record.cycle_id.toString() === localFilters.cycle_id;
            const matchesStatus = !localFilters.status || record.status === localFilters.status;
            const matchesDepartment = !localFilters.department_id
                || record.department_id.toString() === localFilters.department_id;
            return matchesSearch && matchesCycle && matchesStatus && matchesDepartment;
        });
    }, [appraisals, localFilters]);

    const stats = useMemo(() => {
        const total = appraisals.length;
        const completed = appraisals.filter((a) => a.status === 'completed').length;
        const inProgress = appraisals.filter((a) => a.status === 'in_progress').length;
        const avgScore = appraisals.length
            ? (appraisals.reduce((sum, a) => sum + (a.overall_score ?? 0), 0) / appraisals.length).toFixed(2)
            : '0.00';
        return { total, completed, inProgress, avgScore };
    }, [appraisals]);

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Appraisals" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-3xl font-bold">Performance Appraisals</h1>
                    <p className="text-gray-600">Track appraisal progress, scores, and audit readiness.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Total Appraisals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{stats.total}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Completed</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">{stats.completed}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">In Progress</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-blue-600">{stats.inProgress}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Average Score</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-orange-600">{stats.avgScore}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Input
                                placeholder="Search by employee name or number"
                                value={localFilters.search}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                            />
                            <Select
                                value={localFilters.cycle_id || 'all'}
                                onValueChange={(value) => handleFilterChange('cycle_id', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Cycles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Cycles</SelectItem>
                                    {cycles.map((cycle) => (
                                        <SelectItem key={cycle.id} value={cycle.id.toString()}>
                                            {cycle.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={localFilters.status || 'all'}
                                onValueChange={(value) => handleFilterChange('status', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={localFilters.department_id || 'all'}
                                onValueChange={(value) => handleFilterChange('department_id', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All Departments" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {departments.map((dept) => (
                                        <SelectItem key={dept.id} value={dept.id.toString()}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Appraisals ({filteredAppraisals.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Cycle</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Overall Score</TableHead>
                                        <TableHead>Attendance</TableHead>
                                        <TableHead>Last Updated</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredAppraisals.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-6 text-gray-500">
                                                No appraisals match the selected filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {filteredAppraisals.map((record) => (
                                        <TableRow
                                            key={record.id}
                                            className="cursor-pointer hover:bg-muted/50"
                                            onClick={() => router.visit(`/hr/appraisals/${record.id}`)}
                                        >
                                            <TableCell>
                                                <div className="font-semibold">{record.employee_name}</div>
                                                <div className="text-sm text-gray-500">{record.employee_number}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div>{record.cycle_name}</div>
                                                <div className="text-sm text-gray-500">{record.department_name}</div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary" className={record.status_color}>
                                                    {record.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{record.overall_score ?? '—'}</TableCell>
                                            <TableCell>
                                                {record.attendance_rate ? `${record.attendance_rate.toFixed(1)}%` : '—'}
                                            </TableCell>
                                            <TableCell className="text-sm text-gray-500">
                                                {new Date(record.updated_at).toLocaleString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
