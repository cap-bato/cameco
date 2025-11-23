import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    AppraisalCycle,
    Appraisal,
    CycleAnalytics,
    APPRAISAL_STATUS_COLORS,
} from '@/types/appraisal-pages';
import { CycleStatusBadge } from '@/components/hr/appraisal/cycle-status-badge';
import { CycleAnalyticsTab } from '@/components/hr/appraisal/cycle-analytics-tab';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { ArrowLeft, Edit2, Lock, Trash2 } from 'lucide-react';

interface CycleShowProps {
    cycle: AppraisalCycle;
    appraisals: Appraisal[];
    analytics: CycleAnalytics;
}

export default function CycleShow({ cycle, appraisals, analytics }: CycleShowProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [closeDialogOpen, setCloseDialogOpen] = useState(false);

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Appraisals', href: '/hr/appraisals' },
        { title: 'Cycles', href: '/hr/appraisals/cycles' },
        { title: cycle.name, href: '#' },
    ];

    // Calculate completion stats
    const completionStats = useMemo(() => {
        const total = appraisals.length;
        const completed = appraisals.filter((a) => a.status === 'completed').length;
        const inProgress = appraisals.filter((a) => a.status === 'in_progress').length;
        const pending = appraisals.filter((a) => a.status === 'draft').length;

        return {
            total,
            completed,
            inProgress,
            pending,
            percentage: total > 0 ? Math.round((completed / total) * 100) : 0,
        };
    }, [appraisals]);

    // Get recent activity
    const recentActivity = useMemo(() => {
        return appraisals
            .filter((a) => a.updated_at)
            .sort(
                (a, b) =>
                    new Date(b.updated_at || 0).getTime() -
                    new Date(a.updated_at || 0).getTime()
            )
            .slice(0, 5);
    }, [appraisals]);

    const handleDelete = () => {
        router.delete(`/hr/appraisals/cycles/${cycle.id}`, {
            onFinish: () => setDeleteDialogOpen(false),
        });
    };

    const handleClose = () => {
        router.post(`/hr/appraisals/cycles/${cycle.id}/close`);
        setCloseDialogOpen(false);
    };

    const startDate = new Date(cycle.start_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
    const endDate = new Date(cycle.end_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title={`Appraisal Cycle: ${cycle.name}`} />

            <div className="space-y-6 px-6 py-4">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                    <div className="flex items-center gap-3 mb-2">
                        <button
                            onClick={() => router.visit('/hr/appraisals/cycles')}
                            className="inline-flex items-center text-gray-600 hover:text-gray-900"
                        >
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Back
                        </button>
                    </div>
                    <div className="flex items-center gap-4">
                        <h1 className="text-3xl font-bold">{cycle.name}</h1>
                        <CycleStatusBadge status={cycle.status} showIcon />
                    </div>
                    <p className="text-gray-600 mt-2">
                        {startDate} - {endDate}
                    </p>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.visit(`/hr/appraisals/cycles/${cycle.id}/edit`)
                        }
                        className="gap-2"
                    >
                        <Edit2 className="w-4 h-4" />
                        Edit
                    </Button>

                    {cycle.status === 'open' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCloseDialogOpen(true)}
                            className="gap-2"
                        >
                            <Lock className="w-4 h-4" />
                            Close
                        </Button>
                    )}

                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => setDeleteDialogOpen(true)}
                        className="gap-2"
                    >
                        <Trash2 className="w-4 h-4" />
                        Delete
                    </Button>
                </div>
            </div>

            {/* Progress Bar */}
            <Card className="mb-6">
                <CardContent className="pt-6">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">Appraisal Completion</span>
                            <span className="text-sm text-gray-600">
                                {completionStats.percentage}%
                            </span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-3">
                            <div
                                className="bg-blue-600 h-3 rounded-full transition-all"
                                style={{ width: `${completionStats.percentage}%` }}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Tabs */}
            <Tabs defaultValue="overview" className="space-y-6">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="appraisals">Appraisals</TabsTrigger>
                    <TabsTrigger value="analytics">Analytics</TabsTrigger>
                </TabsList>

                {/* Overview Tab */}
                <TabsContent value="overview" className="space-y-6">
                    {/* Completion Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Total Assigned
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {completionStats.total}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Completed</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">
                                    {completionStats.completed}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">In Progress</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-yellow-600">
                                    {completionStats.inProgress}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">Pending</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">
                                    {completionStats.pending}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Recent Activity */}
                    {recentActivity.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent Activity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentActivity.map((appraisal) => (
                                        <div
                                            key={appraisal.id}
                                            className="flex items-center justify-between pb-4 border-b last:border-b-0"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {appraisal.employee_name || 'Unknown Employee'}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    {appraisal.updated_at
                                                        ? new Date(
                                                              appraisal.updated_at
                                                          ).toLocaleDateString('en-US', {
                                                              year: 'numeric',
                                                              month: 'short',
                                                              day: 'numeric',
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                          })
                                                        : 'N/A'}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <span
                                                    className={`inline-block px-3 py-1 rounded-full text-xs font-semibold ${
                                                        APPRAISAL_STATUS_COLORS[appraisal.status] ||
                                                        'bg-gray-100 text-gray-800'
                                                    }`}
                                                >
                                                    {appraisal.status
                                                        .replace('_', ' ')
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        appraisal.status
                                                            .replace('_', ' ')
                                                            .slice(1)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>

                {/* Appraisals Tab */}
                <TabsContent value="appraisals" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Assigned Appraisals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {appraisals.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="px-4 py-3 text-left text-sm font-semibold">
                                                    Employee
                                                </th>
                                                <th className="px-4 py-3 text-left text-sm font-semibold">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-left text-sm font-semibold">
                                                    Score
                                                </th>
                                                <th className="px-4 py-3 text-left text-sm font-semibold">
                                                    Assigned
                                                </th>
                                                <th className="px-4 py-3 text-left text-sm font-semibold">
                                                    Due Date
                                                </th>
                                                <th className="px-4 py-3 text-right text-sm font-semibold">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {appraisals.map((appraisal) => (
                                                <tr
                                                    key={appraisal.id}
                                                    className="border-b hover:bg-gray-50"
                                                >
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">
                                                            {appraisal.employee_name ||
                                                                'Unknown'}
                                                        </p>
                                                        <p className="text-sm text-gray-500">
                                                            {appraisal.employee_id}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`inline-block px-3 py-1 rounded-full text-xs font-semibold ${
                                                                APPRAISAL_STATUS_COLORS[
                                                                    appraisal.status
                                                                ] || 'bg-gray-100 text-gray-800'
                                                            }`}
                                                        >
                                                            {appraisal.status
                                                                .replace('_', ' ')
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                                appraisal.status
                                                                    .replace('_', ' ')
                                                                    .slice(1)}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {appraisal.overall_score !== null ? (
                                                            <span className="font-semibold">
                                                                {Number(
                                                                    appraisal.overall_score
                                                                ).toFixed(1)}
                                                                /10
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-500">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">
                                                        {appraisal.created_at
                                                            ? new Date(
                                                                  appraisal.created_at
                                                              ).toLocaleDateString('en-US', {
                                                                  year: 'numeric',
                                                                  month: 'short',
                                                                  day: 'numeric',
                                                              })
                                                            : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm">
                                                        {appraisal.created_at
                                                            ? new Date(
                                                                  appraisal.created_at
                                                              ).toLocaleDateString('en-US', {
                                                                  year: 'numeric',
                                                                  month: 'short',
                                                                  day: 'numeric',
                                                              })
                                                            : '-'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.visit(
                                                                    `/hr/appraisals/${appraisal.id}`
                                                                )
                                                            }
                                                        >
                                                            View
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-center py-8 text-gray-500">
                                    No appraisals assigned yet
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Analytics Tab */}
                <TabsContent value="analytics">
                    <CycleAnalyticsTab analytics={analytics} />
                </TabsContent>
            </Tabs>

            {/* Delete Dialog */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Appraisal Cycle</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this appraisal cycle? This action
                            cannot be undone and will delete all associated appraisals.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="flex gap-3 justify-end">
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>

            {/* Close Dialog */}
            <AlertDialog open={closeDialogOpen} onOpenChange={setCloseDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Close Appraisal Cycle</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to close this appraisal cycle? Once closed, no
                            new appraisals can be added or modified.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="flex gap-3 justify-end">
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleClose} className="bg-blue-600 hover:bg-blue-700">
                            Close Cycle
                        </AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
            </div>
        </AppLayout>
    );
}
