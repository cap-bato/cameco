import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface DepartmentOption {
    id: number;
    name: string;
}

interface MetricRecord {
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_id: number;
    department_name: string;
    overall_score: number;
    attendance_rate: number;
    behavior_score: number;
    productivity_score: number;
    performance_category: 'high' | 'medium' | 'low';
    trend: 'improving' | 'stable' | 'declining';
}

interface SummaryStats {
    average_score: number;
    high_performers: number;
    low_performers: number;
    completion_rate: number;
}

interface FilterState {
    department_id: string;
    performance_category: string;
    date_from: string;
    date_to: string;
}

interface PerformanceMetricsIndexProps {
    metrics: MetricRecord[];
    departments: DepartmentOption[];
    summary: SummaryStats;
    filters: FilterState;
}

const categoryOptions = [
    { value: 'all', label: 'All Categories' },
    { value: 'high', label: 'High Performers' },
    { value: 'medium', label: 'Medium Performers' },
    { value: 'low', label: 'Low Performers' },
];

const trendColor: Record<MetricRecord['trend'], string> = {
    improving: 'text-green-600',
    stable: 'text-gray-600',
    declining: 'text-red-600',
};

const categoryBadge: Record<MetricRecord['performance_category'], string> = {
    high: 'bg-green-100 text-green-800',
    medium: 'bg-yellow-100 text-yellow-800',
    low: 'bg-red-100 text-red-800',
};

export default function PerformanceMetricsIndex({ metrics = [], departments = [], summary, filters }: PerformanceMetricsIndexProps) {
    const [localFilters, setLocalFilters] = useState<FilterState>(filters ?? {
        department_id: '',
        performance_category: '',
        date_from: '',
        date_to: '',
    });

    const breadcrumb = useMemo(() => [
        { title: 'HR', href: '/hr' },
        { title: 'Performance Metrics', href: '/hr/performance-metrics' },
    ], []);

    const handleFilterChange = (field: keyof FilterState, value: string) => {
        const next = { ...localFilters, [field]: value };
        setLocalFilters(next);
        router.get('/hr/performance-metrics', next, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const filteredMetrics = useMemo(() => {
        return metrics.filter((metric) => {
            const matchesDept = !localFilters.department_id
                || metric.department_id.toString() === localFilters.department_id;
            const matchesCategory = !localFilters.performance_category
                || metric.performance_category === localFilters.performance_category;
            return matchesDept && matchesCategory;
        });
    }, [metrics, localFilters]);

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Performance Metrics" />
            <div className="space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold">Performance Metrics</h1>
                    <p className="text-gray-600">Analyze appraisal results, attendance correlation, and performance risks.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Average Score</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-blue-600">{summary.average_score.toFixed(2)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">High Performers</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">{summary.high_performers}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Low Performers</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-red-600">{summary.low_performers}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-gray-500">Completion Rate</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-purple-600">{summary.completion_rate}%</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Select
                                value={localFilters.department_id || 'all'}
                                onValueChange={(value) => handleFilterChange('department_id', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All departments" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All departments</SelectItem>
                                    {departments.map((dept) => (
                                        <SelectItem key={dept.id} value={dept.id.toString()}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={localFilters.performance_category || 'all'}
                                onValueChange={(value) => handleFilterChange('performance_category', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categoryOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                type="date"
                                value={localFilters.date_from}
                                onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                placeholder="From"
                            />
                            <Input
                                type="date"
                                value={localFilters.date_to}
                                onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                placeholder="To"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Employee Metrics ({filteredMetrics.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Overall Score</TableHead>
                                        <TableHead>Attendance</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Trend</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredMetrics.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-6 text-gray-500">
                                                No metrics found for the selected filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {filteredMetrics.map((metric) => (
                                        <TableRow
                                            key={metric.employee_id}
                                            className="cursor-pointer hover:bg-muted/50"
                                            onClick={() => router.visit(`/hr/performance-metrics/${metric.employee_id}`)}
                                        >
                                            <TableCell>
                                                <div className="font-semibold">{metric.employee_name}</div>
                                                <div className="text-sm text-gray-500">{metric.employee_number}</div>
                                            </TableCell>
                                            <TableCell>{metric.department_name}</TableCell>
                                            <TableCell>{metric.overall_score.toFixed(1)}</TableCell>
                                            <TableCell>{metric.attendance_rate.toFixed(1)}%</TableCell>
                                            <TableCell>
                                                <Badge className={categoryBadge[metric.performance_category]}>
                                                    {metric.performance_category.replace('_', ' ')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className={trendColor[metric.trend]}>{metric.trend}</TableCell>
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
