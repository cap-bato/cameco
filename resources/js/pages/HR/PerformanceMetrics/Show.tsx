import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface EmployeeInfo {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    department_name: string;
    email?: string;
}

interface MetricSnapshot {
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_name: string;
    overall_score: number;
    attendance_rate: number;
    behavior_score: number;
    productivity_score: number;
    performance_category: string;
    trend: 'improving' | 'stable' | 'declining';
}

interface HistoricalMetric {
    cycle_name: string;
    overall_score: number;
    cycle_date: string;
}

interface CorrelationData {
    months: string[];
    appraisalScores: number[];
    attendanceRates: number[];
}

interface PerformanceMetricsShowProps {
    employee: EmployeeInfo;
    currentMetric: MetricSnapshot;
    historicalMetrics: HistoricalMetric[];
    departmentAverage: number;
    trend: string;
    attendanceCorrelation: CorrelationData;
}

const TrendBadge = ({ trend }: { trend: MetricSnapshot['trend'] }) => {
    const color = trend === 'improving' ? 'bg-green-100 text-green-800' : trend === 'declining' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800';
    return <Badge className={color}>{trend}</Badge>;
};

export default function PerformanceMetricsShow({ employee, currentMetric, historicalMetrics, departmentAverage, trend, attendanceCorrelation }: PerformanceMetricsShowProps) {
    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Performance Metrics', href: '/hr/performance-metrics' },
        { title: employee.full_name, href: `/hr/performance-metrics/${employee.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title={`Performance • ${employee.full_name}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center gap-3 flex-wrap">
                    <div>
                        <h1 className="text-3xl font-bold">{employee.full_name}</h1>
                        <p className="text-gray-600">{employee.department_name}</p>
                    </div>
                    <TrendBadge trend={currentMetric.trend} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Current Snapshot</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Overall Score</span>
                                <span className="text-lg font-semibold">{currentMetric.overall_score.toFixed(1)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Behavior</span>
                                <span className="font-medium">{currentMetric.behavior_score.toFixed(1)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Productivity</span>
                                <span className="font-medium">{currentMetric.productivity_score.toFixed(1)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Attendance</span>
                                <span className="font-medium">{currentMetric.attendance_rate.toFixed(1)}%</span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Benchmark</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Department Average</span>
                                <span className="text-lg font-semibold">{departmentAverage.toFixed(1)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Trend</span>
                                <span className="font-medium capitalize">{trend}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Category</span>
                                <Badge variant="secondary">{currentMetric.performance_category}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance Correlation</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-gray-600">
                            Performance improves when attendance stays above 93%.
                            <div className="mt-2 text-xs text-gray-500">
                                Latest month: score {attendanceCorrelation.appraisalScores.at(-1)?.toFixed(1)} • attendance {attendanceCorrelation.attendanceRates.at(-1)}%
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Historical Performance</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Cycle</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Score</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {historicalMetrics.map((item) => (
                                    <TableRow key={item.cycle_name}>
                                        <TableCell>{item.cycle_name}</TableCell>
                                        <TableCell>{new Date(item.cycle_date).toLocaleDateString()}</TableCell>
                                        <TableCell>{item.overall_score.toFixed(1)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
