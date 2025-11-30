import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface ScoreEntry {
    criterion: string;
    score: number;
}

interface OverrideRecord {
    id: number;
    action: string;
    reason: string;
    created_by: string;
    created_at: string;
}

interface RecommendationDetail {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_number: string;
    department_name: string;
    cycle_name: string;
    recommendation: string;
    recommendation_label: string;
    recommendation_color: string;
    notes?: string;
    is_overridden: boolean;
    overridden_by: string | null;
    overall_score?: number;
    attendance_rate?: number;
    violation_count?: number;
    position_title?: string;
    risk_flags?: RiskFlag[];
    violations?: ViolationRecord[];
    created_at?: string;
    updated_at?: string;
}

interface AppraisalDetail {
    id: number;
    overall_score: number;
    scores: ScoreEntry[];
}

interface AttendanceMetrics {
    attendance_rate: number;
    lateness_count: number;
    violation_count: number;
}

interface EmployeeDetail {
    id: number;
    employee_number: string;
    department_name: string;
    full_name?: string;
    first_name?: string;
    last_name?: string;
    position_title?: string;
    email?: string;
}

interface ViolationRecord {
    id: number;
    type: string;
    description: string;
    severity: string;
    occurred_at: string;
}

interface RiskFlag {
    id: number | string;
    label: string;
    severity: 'low' | 'medium' | 'high';
    description: string;
}

interface RehireRecommendationShowProps {
    recommendation: RecommendationDetail;
    appraisal?: AppraisalDetail;
    employee?: EmployeeDetail;
    attendanceMetrics?: AttendanceMetrics;
    overrideHistory?: OverrideRecord[];
}

export default function RehireRecommendationShow({
    recommendation,
    appraisal,
    employee,
    attendanceMetrics,
    overrideHistory = [],
}: RehireRecommendationShowProps) {
    const employeeName = employee?.full_name ?? recommendation.employee_name;
    const employeeNumber = employee?.employee_number ?? recommendation.employee_number;
    const departmentName = employee?.department_name ?? recommendation.department_name;
    const positionTitle = employee?.position_title ?? recommendation.position_title ?? '—';
    const appraisalScore = appraisal?.overall_score ?? recommendation.overall_score ?? 0;
    const attendanceSummary: AttendanceMetrics = attendanceMetrics ?? {
        attendance_rate: recommendation.attendance_rate ?? 0,
        lateness_count: 0,
        violation_count: recommendation.violation_count ?? 0,
    };
    const scoreBreakdown = appraisal?.scores ?? [];
    const manualRiskFlags = recommendation.risk_flags ?? [];

    const derivedRiskFlags = useMemo(() => {
        const flags: RiskFlag[] = [...manualRiskFlags];
        if (attendanceSummary.attendance_rate < 85) {
            flags.push({
                id: `attendance-${flags.length}`,
                label: 'Low attendance compliance',
                severity: 'medium',
                description: 'Attendance rate fell below the 85% threshold.',
            });
        }
        if ((attendanceSummary.violation_count ?? 0) > 0) {
            flags.push({
                id: `violations-${flags.length}`,
                label: 'Policy violations detected',
                severity: 'high',
                description: `${attendanceSummary.violation_count} recorded violation(s) in the last cycle.`,
            });
        }
        if (appraisalScore < 6.5) {
            flags.push({
                id: `score-${flags.length}`,
                label: 'Low performance score',
                severity: 'medium',
                description: 'Overall appraisal score is below the recommended eligibility threshold.',
            });
        }
        return flags;
    }, [manualRiskFlags, attendanceSummary, appraisalScore]);

    const violationLogs = recommendation.violations ?? [];

    const lastUpdatedRaw = recommendation.updated_at ?? recommendation.created_at ?? '';
    const formattedUpdated = lastUpdatedRaw ? new Date(lastUpdatedRaw).toLocaleString() : 'Not available';

    const breadcrumbs = useMemo(() => [
        { title: 'HR', href: '/hr' },
        { title: 'Rehire Recommendations', href: '/hr/rehire-recommendations' },
        { title: employeeName, href: `/hr/rehire-recommendations/${recommendation.id}` },
    ], [employeeName, recommendation.id]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Rehire Recommendation - ${recommendation.employee_name}`} />
            <div className="space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-3xl font-bold">{employeeName}</h1>
                    <p className="text-gray-600">
                        {employeeNumber} &middot; {departmentName} &middot; {positionTitle}
                    </p>
                    {employee?.email && <p className="text-sm text-gray-500">{employee.email}</p>}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Recommendation</CardTitle></CardHeader>
                        <CardContent>
                            <Badge className={recommendation.recommendation_color}>
                                {recommendation.recommendation_label}
                            </Badge>
                            <p className="text-sm text-gray-600 mt-2">{recommendation.notes ?? 'No analyst notes provided.'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Appraisal Score</CardTitle></CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{appraisalScore.toFixed(1)}</p>
                            <p className="text-sm text-gray-600">{recommendation.cycle_name}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500">Attendance Rate</CardTitle></CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{attendanceSummary.attendance_rate.toFixed(1)}%</p>
                            <p className="text-sm text-gray-600">
                                {attendanceSummary.lateness_count} lateness &middot; {attendanceSummary.violation_count} violation(s)
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Appraisal Snapshot</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div><span className="text-gray-500">Cycle:</span> {recommendation.cycle_name}</div>
                            <div><span className="text-gray-500">Overall Score:</span> {appraisalScore.toFixed(1)}</div>
                            <div><span className="text-gray-500">Recommendation:</span> {recommendation.recommendation_label}</div>
                            <div><span className="text-gray-500">Updated:</span> {formattedUpdated}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance & Compliance</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div><span className="text-gray-500">Attendance Rate:</span> {attendanceSummary.attendance_rate.toFixed(1)}%</div>
                            <div><span className="text-gray-500">Lateness Count:</span> {attendanceSummary.lateness_count}</div>
                            <div><span className="text-gray-500">Policy Violations:</span> {attendanceSummary.violation_count}</div>
                            <div><span className="text-gray-500">Override Status:</span> {recommendation.is_overridden ? `Overridden by ${recommendation.overridden_by}` : 'No overrides'}</div>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Risk Indicators</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {derivedRiskFlags.length === 0 && (
                            <p className="text-sm text-gray-500">No active risk indicators for this employee.</p>
                        )}
                        {derivedRiskFlags.map((flag) => (
                            <div key={flag.id} className="border border-border rounded-lg p-3">
                                <div className="flex items-center justify-between">
                                    <div className="font-semibold">{flag.label}</div>
                                    <Badge variant={flag.severity === 'high' ? 'destructive' : flag.severity === 'medium' ? 'secondary' : 'default'}>
                                        {flag.severity}
                                    </Badge>
                                </div>
                                <p className="text-sm text-gray-600 mt-1">{flag.description}</p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Score Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Criterion</TableHead>
                                    <TableHead>Score</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {scoreBreakdown.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={2} className="text-center py-4 text-gray-500">
                                            No score breakdown available for this appraisal.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {scoreBreakdown.map((entry) => (
                                    <TableRow key={entry.criterion}>
                                        <TableCell>{entry.criterion}</TableCell>
                                        <TableCell>{entry.score.toFixed(1)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Violation Notes</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {violationLogs.length === 0 && (
                            <p className="text-sm text-gray-500">
                                No detailed violation logs were provided. Total recorded violations: {recommendation.violation_count ?? 0}.
                            </p>
                        )}
                        {violationLogs.map((violation) => (
                            <div key={violation.id} className="border border-border rounded-lg p-3">
                                <div className="flex items-center justify-between">
                                    <div className="font-semibold">{violation.type}</div>
                                    <span className="text-sm text-gray-500">{violation.occurred_at}</span>
                                </div>
                                <p className="text-sm text-gray-600 mt-1">{violation.description}</p>
                                <Badge className="mt-2" variant={violation.severity === 'high' ? 'destructive' : violation.severity === 'medium' ? 'secondary' : 'default'}>
                                    {violation.severity}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Override History</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {overrideHistory.length === 0 && (
                            <p className="text-sm text-gray-500">No manual overrides recorded.</p>
                        )}
                        {overrideHistory.map((item) => (
                            <div key={item.id} className="border border-border rounded-lg p-3">
                                <div className="flex items-center justify-between">
                                    <div className="font-semibold">{item.action}</div>
                                    <span className="text-sm text-gray-500">{item.created_at}</span>
                                </div>
                                <p className="text-sm text-gray-600 mt-1">{item.reason}</p>
                                <div className="text-sm text-gray-500 mt-2">By {item.created_by}</div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
