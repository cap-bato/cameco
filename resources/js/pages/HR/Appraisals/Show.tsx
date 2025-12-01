import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface ScoreItem {
    id: number;
    criterion: string;
    score: number;
    weight: number;
    notes?: string | null;
}

interface AppraisalDetail {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_name: string;
    cycle_name: string;
    status: string;
    status_label: string;
    status_color: string;
    overall_score: number | null;
    attendance_rate: number;
    lateness_count: number;
    violation_count: number;
    created_at: string;
    updated_at: string;
    scores: ScoreItem[];
}

interface EmployeeInfo {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    department_name: string;
    position_name?: string;
    email?: string;
    phone?: string;
    date_employed?: string;
    status: string;
}

interface CycleInfo {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface AppraisalsShowProps {
    appraisal: AppraisalDetail;
    employee: EmployeeInfo;
    cycle: CycleInfo;
}

const InfoRow = ({ label, value }: { label: string; value: React.ReactNode }) => (
    <div className="flex flex-col">
        <span className="text-xs uppercase tracking-wide text-gray-500">{label}</span>
        <span className="text-sm font-medium text-gray-900">{value ?? '—'}</span>
    </div>
);

export default function AppraisalsShow({ appraisal, employee, cycle }: AppraisalsShowProps) {
    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Appraisals', href: '/hr/appraisals' },
        { title: employee.full_name, href: `/hr/appraisals/${appraisal.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title={`Appraisal • ${employee.full_name}`} />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3 flex-wrap">
                        <h1 className="text-3xl font-bold">{employee.full_name}</h1>
                        <Badge className={appraisal.status_color}>{appraisal.status_label}</Badge>
                    </div>
                    <p className="text-gray-600">Cycle: {cycle.name}</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Employee Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <InfoRow label="Employee Number" value={employee.employee_number} />
                            <InfoRow label="Department" value={employee.department_name} />
                            <InfoRow label="Position" value={employee.position_name ?? 'Not assigned'} />
                            <InfoRow label="Status" value={employee.status} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Cycle Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <InfoRow label="Name" value={cycle.name} />
                            <InfoRow label="Start" value={new Date(cycle.start_date).toLocaleDateString()} />
                            <InfoRow label="End" value={new Date(cycle.end_date).toLocaleDateString()} />
                            <InfoRow label="Last Updated" value={new Date(appraisal.updated_at).toLocaleString()} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Score Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3">
                            <InfoRow
                                label="Overall Score"
                                value={appraisal.overall_score ? appraisal.overall_score.toFixed(1) : 'Not scored'}
                            />
                            <InfoRow label="Attendance" value={`${appraisal.attendance_rate.toFixed(1)}%`} />
                            <InfoRow label="Late Entries" value={appraisal.lateness_count} />
                            <InfoRow label="Violations" value={appraisal.violation_count} />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Score Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Criterion</TableHead>
                                        <TableHead>Weight</TableHead>
                                        <TableHead>Score</TableHead>
                                        <TableHead>Notes</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {appraisal.scores.map((score) => (
                                        <TableRow key={score.id}>
                                            <TableCell className="font-medium">{score.criterion}</TableCell>
                                            <TableCell>{score.weight}%</TableCell>
                                            <TableCell>{score.score}</TableCell>
                                            <TableCell className="text-sm text-gray-600">{score.notes ?? '—'}</TableCell>
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
