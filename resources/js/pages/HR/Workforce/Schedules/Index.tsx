import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Edit, Trash2, Copy, Calendar, Grid, List } from 'lucide-react';
import ScheduleFilters from '@/components/workforce/schedule-filters';
import ScheduleCard from '@/components/workforce/schedule-card';
import CreateEditScheduleModal from './CreateEditScheduleModal';
import { SchedulesIndexProps, WorkSchedule } from '@/types/workforce-pages';

export default function SchedulesIndex() {
    const { schedules: initialSchedules, summary, departments, templates } = usePage().props as unknown as SchedulesIndexProps;

    const [viewMode, setViewMode] = useState<'card' | 'list'>('card');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [selectedSchedule, setSelectedSchedule] = useState<WorkSchedule | null>(null);
    const [filters, setFilters] = useState({
        department_id: 0,
        status: 'all',
        search: '',
        date_range: 'all',
    });

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Schedules', href: '/hr/workforce/schedules' },
    ];

    // Extract schedules array from paginated or array data
    const schedulesArray = Array.isArray(initialSchedules) ? initialSchedules : initialSchedules.data || [];

    // Filter schedules based on filters
    const filteredSchedules = schedulesArray.filter((schedule) => {
        const matchesSearch =
            !filters.search ||
            schedule.name?.toLowerCase().includes(filters.search.toLowerCase()) ||
            schedule.description?.toLowerCase().includes(filters.search.toLowerCase());

        const matchesDept =
            !filters.department_id ||
            schedule.department_id === filters.department_id;

        const matchesStatus =
            filters.status === 'all' ||
            schedule.status === filters.status;

        return matchesSearch && matchesDept && matchesStatus;
    });

    const handleEditClick = (schedule: WorkSchedule) => {
        setSelectedSchedule(schedule);
        setIsEditModalOpen(true);
    };

    const handleDeleteClick = (id: number) => {
        if (confirm('Are you sure you want to delete this schedule?')) {
            router.delete(`/hr/workforce/schedules/${id}`, {
                onSuccess: () => {
                    // Refresh the page to update the list
                    window.location.reload();
                },
            });
        }
    };

    const handleDuplicateClick = (schedule: WorkSchedule) => {
        router.post(`/hr/workforce/schedules/${schedule.id}/duplicate`, {
            name: `${schedule.name} (Copy)`,
        }, {
            onSuccess: () => {
                // Full page reload to get fresh data from server
                window.location.reload();
            },
        });
    };

    const handleSaveSchedule = () => {
        // This is now handled by the modal's direct router.post calls
        // Just refresh the page after successful save
    };

    const getTotalSchedulesCount = () => {
        return schedulesArray.length;
    };

    const getStatusColor = (status: string | undefined) => {
        switch (status) {
            case 'active': return 'bg-green-100 text-green-800';
            case 'expired': return 'bg-red-100 text-red-800';
            case 'draft': return 'bg-gray-100 text-gray-800';
            default: return 'bg-blue-100 text-blue-800';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Work Schedules" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Work Schedules</h1>
                        <p className="text-gray-600">Manage employee work schedules and shift templates</p>
                    </div>
                    <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Create Schedule
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Schedules</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_schedules}</div>
                            <p className="text-xs text-gray-500">All schedules</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Active Schedules</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{summary.active_schedules}</div>
                            <p className="text-xs text-gray-500">Currently in use</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Employees Assigned</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{summary.employees_assigned}</div>
                            <p className="text-xs text-gray-500">To active schedules</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Coverage</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">{summary.templates_available} templates</div>
                            <p className="text-xs text-gray-500">Reusable templates</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and View Toggle */}
                <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
                    <ScheduleFilters 
                        departments={departments}
                        filters={filters}
                        onFiltersChange={setFilters}
                    />
                <div className="flex gap-2">
                    <Button
                        variant={viewMode === 'card' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setViewMode('card')}
                        className="gap-2"
                    >
                        <Grid className="h-4 w-4" />
                        Card View
                    </Button>
                    <Button
                        variant={viewMode === 'list' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setViewMode('list')}
                        className="gap-2"
                    >
                        <List className="h-4 w-4" />
                        List View
                    </Button>
                </div>
                </div>

                {/* Results Count */}
                <p className="text-sm text-gray-600">Showing {filteredSchedules.length} of {getTotalSchedulesCount()} schedules</p>
                {/* Schedules Grid/List */}
                {filteredSchedules.length > 0 ? (
                    viewMode === 'card' ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {filteredSchedules.map((schedule: WorkSchedule) => (
                            <ScheduleCard
                                key={schedule.id}
                                schedule={schedule}
                                onEdit={handleEditClick}
                                onDelete={handleDeleteClick}
                                onDuplicate={handleDuplicateClick}
                            />
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-3 px-4 font-semibold">Schedule Name</th>
                                            <th className="text-left py-3 px-4 font-semibold">Department</th>
                                            <th className="text-left py-3 px-4 font-semibold">Effective Date</th>
                                            <th className="text-left py-3 px-4 font-semibold">Status</th>
                                            <th className="text-left py-3 px-4 font-semibold">Employees</th>
                                            <th className="text-right py-3 px-4 font-semibold">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredSchedules.map((schedule) => (
                                            <tr key={schedule.id} className="border-b hover:bg-gray-50">
                                                <td className="py-3 px-4 font-medium">{schedule.name}</td>
                                                <td className="py-3 px-4">
                                                    {schedule.department_name || 'N/A'}
                                                </td>
                                                <td className="py-3 px-4">{new Date(schedule.effective_date).toLocaleDateString()}</td>
                                                <td className="py-3 px-4">
                                                    <span className={`px-2 py-1 rounded text-xs font-semibold ${getStatusColor(schedule.status)}`}>
                                                        {schedule.status ? schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1) : 'Active'}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4">{schedule.assigned_employees_count || 0}</td>
                                                <td className="py-3 px-4 text-right">
                                                    <div className="flex gap-2 justify-end">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleEditClick(schedule)}
                                                            className="gap-1"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDuplicateClick(schedule)}
                                                            className="gap-1"
                                                        >
                                                            <Copy className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDeleteClick(schedule.id)}
                                                            className="gap-1 text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )
                ) : (
                    <div className="text-center py-12">
                        <Calendar className="h-12 w-12 mx-auto text-gray-400 mb-4" />
                        <h3 className="text-lg font-semibold text-gray-900">No schedules found</h3>
                        <p className="text-gray-600 mt-2">Create your first schedule to get started</p>
                        <Button onClick={() => setIsCreateModalOpen(true)} className="mt-4 gap-2">
                            <Plus className="h-4 w-4" />
                            Create Schedule
                        </Button>
                    </div>
                )}
            </div>

            {/* Create/Edit Modal */}
            <CreateEditScheduleModal
                isOpen={isCreateModalOpen || isEditModalOpen}
                onClose={() => {
                    setIsCreateModalOpen(false);
                    setIsEditModalOpen(false);
                    setSelectedSchedule(null);
                }}
                onSave={handleSaveSchedule}
                schedule={selectedSchedule}
                departments={departments}
                templates={templates || []}
                isEditing={!!selectedSchedule?.id}
            />
        </AppLayout>
    );
}
