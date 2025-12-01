import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface DepartmentComparisonRow {
    name: string;
    average_score: number;
    total_employees: number;
    appraised_employees: number;
    high_performers: number;
    medium_performers: number;
    low_performers: number;
}

interface DepartmentComparisonProps {
    comparison: DepartmentComparisonRow[];
}

export default function DepartmentComparison({ comparison = [] }: DepartmentComparisonProps) {
    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Performance Metrics', href: '/hr/performance-metrics' },
        { title: 'Department Comparison', href: '/hr/performance-metrics/department/comparison' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Department Comparison" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Department Comparison</h1>
                    <p className="text-gray-600">Benchmark average scores and identify departments that need coaching or recognition.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Performance by Department</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Department</TableHead>
                                        <TableHead>Average Score</TableHead>
                                        <TableHead>Appraised</TableHead>
                                        <TableHead>High</TableHead>
                                        <TableHead>Medium</TableHead>
                                        <TableHead>Low</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {comparison.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-6 text-gray-500">
                                                No comparison data available.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {comparison.map((dept) => (
                                        <TableRow key={dept.name}>
                                            <TableCell className="font-semibold">{dept.name}</TableCell>
                                            <TableCell>{dept.average_score.toFixed(2)}</TableCell>
                                            <TableCell>
                                                {dept.appraised_employees}/{dept.total_employees}
                                            </TableCell>
                                            <TableCell className="text-green-600 font-medium">{dept.high_performers}</TableCell>
                                            <TableCell className="text-yellow-600 font-medium">{dept.medium_performers}</TableCell>
                                            <TableCell className="text-red-600 font-medium">{dept.low_performers}</TableCell>
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
