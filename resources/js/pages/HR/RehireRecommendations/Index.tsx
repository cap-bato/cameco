import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface RecommendationRecord {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_id: number;
    department_name: string;
    appraisal_id: number;
    cycle_name: string;
    recommendation: 'eligible' | 'review_required' | 'not_recommended';
    recommendation_label: string;
    recommendation_color: string;
    overall_score: number;
    attendance_rate: number;
    violation_count: number;
    notes: string;
    is_overridden: boolean;
    overridden_by: string | null;
    created_at: string;
}

interface OptionItem {
    id: number;
    name: string;
}

interface FilterState {
    recommendation: string;
    department_id: string;
    search: string;
}

interface RehireRecommendationsIndexProps {
    recommendations: RecommendationRecord[];
    departments: OptionItem[];
    filters: FilterState;
}

const recommendationOptions = [
    { value: 'all', label: 'All Outcomes' },
    { value: 'eligible', label: 'Eligible for Rehire' },
    { value: 'review_required', label: 'Requires Review' },
    { value: 'not_recommended', label: 'Not Recommended' },
];

export default function RehireRecommendationsIndex({ recommendations = [], departments = [], filters }: RehireRecommendationsIndexProps) {
    const [localFilters, setLocalFilters] = useState<FilterState>(filters ?? {
        recommendation: '',
        department_id: '',
        search: '',
    });

    const breadcrumb = useMemo(() => [
        { title: 'HR', href: '/hr' },
        { title: 'Rehire Recommendations', href: '/hr/rehire-recommendations' },
    ], []);

    const handleFilterChange = (field: keyof FilterState, value: string) => {
        const next = { ...localFilters, [field]: value };
        setLocalFilters(next);
        router.get('/hr/rehire-recommendations', next, {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const filteredRecords = useMemo(() => {
        return recommendations.filter((record) => {
            const matchesRecommendation = !localFilters.recommendation
                || record.recommendation === localFilters.recommendation;
            const matchesDepartment = !localFilters.department_id
                || record.department_id.toString() === localFilters.department_id;
            const matchesSearch = !localFilters.search
                || record.employee_name.toLowerCase().includes(localFilters.search.toLowerCase())
                || record.employee_number.toLowerCase().includes(localFilters.search.toLowerCase());
            return matchesRecommendation && matchesDepartment && matchesSearch;
        });
    }, [recommendations, localFilters]);

    const summary = useMemo(() => {
        const total = recommendations.length;
        const eligible = recommendations.filter((r) => r.recommendation === 'eligible').length;
        const review = recommendations.filter((r) => r.recommendation === 'review_required').length;
        const blocked = recommendations.filter((r) => r.recommendation === 'not_recommended').length;
        return { total, eligible, review, blocked };
    }, [recommendations]);

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Rehire Recommendations" />
            <div className="space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold">Rehire Recommendations</h1>
                    <p className="text-gray-600">Automated rehire eligibility based on appraisal, attendance, and violations.</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Total</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold">{summary.total}</p></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Eligible</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold text-green-600">{summary.eligible}</p></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Review Required</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold text-yellow-600">{summary.review}</p></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Not Recommended</CardTitle></CardHeader>
                        <CardContent><p className="text-3xl font-bold text-red-600">{summary.blocked}</p></CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Input
                                placeholder="Search by employee or number"
                                value={localFilters.search}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                            />
                            <Select
                                value={localFilters.recommendation || 'all'}
                                onValueChange={(value) => handleFilterChange('recommendation', value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All outcomes" />
                                </SelectTrigger>
                                <SelectContent>
                                    {recommendationOptions.map((option) => (
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
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recommendations ({filteredRecords.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Cycle</TableHead>
                                        <TableHead>Recommendation</TableHead>
                                        <TableHead>Score</TableHead>
                                        <TableHead>Attendance</TableHead>
                                        <TableHead>Violations</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredRecords.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-6 text-gray-500">
                                                No recommendations match the selected filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {filteredRecords.map((record) => (
                                        <TableRow
                                            key={record.id}
                                            className="cursor-pointer hover:bg-muted/50"
                                            onClick={() => router.visit(`/hr/rehire-recommendations/${record.id}`)}
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
                                                <Badge className={record.recommendation_color}>{record.recommendation_label}</Badge>
                                            </TableCell>
                                            <TableCell>{record.overall_score.toFixed(1)}</TableCell>
                                            <TableCell>{record.attendance_rate.toFixed(1)}%</TableCell>
                                            <TableCell>{record.violation_count}</TableCell>
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
