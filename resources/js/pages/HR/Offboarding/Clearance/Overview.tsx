import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle, Clock, Search, ChevronRight, ClipboardCheck } from 'lucide-react';

interface ClearanceStats {
    total: number;
    pending: number;
    in_progress: number;
    approved: number;
    waived: number;
    issues: number;
    overdue: number;
    critical_pending: number;
}

interface CaseRow {
    id: number;
    case_number: string;
    status: string;
    separation_type: string;
    employee: {
        name: string;
        employee_number: string;
        department: string;
    };
    clearance_stats: ClearanceStats;
    clearance_url: string;
}

interface Totals {
    total_cases: number;
    total_pending: number;
    total_issues: number;
    total_overdue: number;
}

interface Props {
    cases: CaseRow[];
    totals: Totals;
}

const breadcrumbs = [
    { title: 'HR', href: '/hr/dashboard' },
    { title: 'Offboarding', href: '/hr/offboarding/dashboard' },
    { title: 'Clearance', href: '/hr/offboarding/clearance' },
];

function progressPercent(stats: ClearanceStats): number {
    if (stats.total === 0) return 0;
    const done = stats.approved + stats.waived;
    return Math.round((done / stats.total) * 100);
}

function statusColor(status: string): string {
    switch (status) {
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'clearance_pending': return 'bg-yellow-100 text-yellow-800';
        case 'pending': return 'bg-gray-100 text-gray-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}

function statusLabel(status: string): string {
    switch (status) {
        case 'in_progress': return 'In Progress';
        case 'clearance_pending': return 'Clearance Pending';
        case 'pending': return 'Pending';
        default: return status;
    }
}

export default function ClearanceOverview({ cases, totals }: Props) {
    const [search, setSearch] = useState('');

    const filtered = cases.filter((c) => {
        const q = search.toLowerCase();
        return (
            c.case_number.toLowerCase().includes(q) ||
            c.employee.name.toLowerCase().includes(q) ||
            c.employee.employee_number.toLowerCase().includes(q) ||
            (c.employee.department ?? '').toLowerCase().includes(q)
        );
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clearance Overview" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Clearance Overview</h1>
                    <p className="text-sm text-gray-500 mt-1">
                        All active offboarding cases and their clearance status
                    </p>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-sm text-gray-500">Active Cases</p>
                            <p className="text-3xl font-bold text-gray-900">{totals.total_cases}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-sm text-gray-500">Pending Items</p>
                            <p className="text-3xl font-bold text-blue-600">{totals.total_pending}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-sm text-gray-500">Items with Issues</p>
                            <p className="text-3xl font-bold text-red-600">{totals.total_issues}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-5">
                            <p className="text-sm text-gray-500">Overdue Items</p>
                            <p className="text-3xl font-bold text-orange-600">{totals.total_overdue}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Cases Table */}
                <Card>
                    <CardHeader className="pb-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardCheck className="h-5 w-5" />
                                Active Cases
                            </CardTitle>
                            <div className="relative w-64">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search cases..."
                                    className="pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {filtered.length === 0 ? (
                            <div className="text-center py-12 text-gray-500">
                                <CheckCircle className="mx-auto h-10 w-10 text-green-400 mb-3" />
                                <p className="font-medium">
                                    {cases.length === 0
                                        ? 'No active offboarding cases'
                                        : 'No cases match your search'}
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {filtered.map((c) => {
                                    const pct = progressPercent(c.clearance_stats);
                                    return (
                                        <div
                                            key={c.id}
                                            className="flex items-center gap-4 py-4 hover:bg-gray-50 px-2 rounded-lg transition-colors"
                                        >
                                            {/* Employee info */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-semibold text-gray-900 truncate">
                                                        {c.employee.name}
                                                    </span>
                                                    <span className="text-xs text-gray-400 font-mono shrink-0">
                                                        {c.employee.employee_number}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-gray-500">
                                                    <span>{c.case_number}</span>
                                                    <span>·</span>
                                                    <span>{c.employee.department ?? '—'}</span>
                                                    <span>·</span>
                                                    <span className="capitalize">{c.separation_type}</span>
                                                </div>
                                            </div>

                                            {/* Status badge */}
                                            <Badge className={statusColor(c.status)}>
                                                {statusLabel(c.status)}
                                            </Badge>

                                            {/* Clearance progress */}
                                            <div className="w-40 shrink-0">
                                                <div className="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span>Clearance</span>
                                                    <span>{pct}%</span>
                                                </div>
                                                <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div
                                                        className={`h-full rounded-full transition-all ${
                                                            pct === 100 ? 'bg-green-500' : 'bg-blue-500'
                                                        }`}
                                                        style={{ width: `${pct}%` }}
                                                    />
                                                </div>
                                                <div className="flex gap-2 mt-1 text-xs text-gray-500">
                                                    <span className="text-blue-600">{c.clearance_stats.pending} pending</span>
                                                    {c.clearance_stats.issues > 0 && (
                                                        <span className="text-red-600 flex items-center gap-0.5">
                                                            <AlertCircle className="h-3 w-3" />
                                                            {c.clearance_stats.issues}
                                                        </span>
                                                    )}
                                                    {c.clearance_stats.overdue > 0 && (
                                                        <span className="text-orange-600 flex items-center gap-0.5">
                                                            <Clock className="h-3 w-3" />
                                                            {c.clearance_stats.overdue}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Action */}
                                            <Button asChild size="sm" variant="outline" className="shrink-0">
                                                <Link href={c.clearance_url}>
                                                    View <ChevronRight className="h-4 w-4 ml-1" />
                                                </Link>
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
