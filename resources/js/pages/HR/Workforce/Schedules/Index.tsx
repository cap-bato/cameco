import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Edit, Trash2, Copy, Calendar, Grid, List } from 'lucide-react';
import ScheduleFilters from '@/components/workforce/schedule-filters';
import ScheduleCard from '@/components/workforce/schedule-card';
import CreateEditScheduleModal from './CreateEditScheduleModal';
import { PermissionGate } from '@/components/permission-gate';
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
                preserveScroll: true,
            });
        }
    };

    const handleDuplicateClick = (schedule: WorkSchedule) => {
        router.post(`/hr/workforce/schedules/${schedule.id}/duplicate`, {
            name: `${schedule.name} (Copy)`,
        }, {
            preserveScroll: true,
        });
    };

    const handleSaveSchedule = () => {
        // Modal handles save directly with Inertia
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
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold">Work Schedules</h1>
                        <p className="text-muted-foreground mt-1">Manage employee work schedules and shift templates</p>
                    </div>
                    <PermissionGate permission="hr.workforce.schedules.create">
                        <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2 w-full sm:w-auto">
                            <Plus className="h-4 w-4" />
                            Create Schedule
                        </Button>
                    </PermissionGate>
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
                <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                    <div className="w-full lg:flex-1">
                        <ScheduleFilters 
                            departments={departments}
                            filters={filters}
                            onFiltersChange={setFilters}
                        />
                    </div>
                    <div className="flex gap-2 w-full lg:w-auto">
                        <Button
                            variant={viewMode === 'card' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setViewMode('card')}
                            className="gap-2 flex-1 lg:flex-none"
                        >
                            <Grid className="h-4 w-4" />
                            <span className="hidden sm:inline">Card</span>
                        </Button>
                        <Button
                            variant={viewMode === 'list' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setViewMode('list')}
                            className="gap-2 flex-1 lg:flex-none"
                        >
                            <List className="h-4 w-4" />
                            <span className="hidden sm:inline">List</span>
                        </Button>
                    </div>
                </div>

                {/* Results Count */}
                {filteredSchedules.length > 0 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing <span className="font-medium">{filteredSchedules.length}</span> of <span className="font-medium">{getTotalSchedulesCount()}</span> schedules
                        </p>
                    </div>
                )}
                {/* Schedules Grid/List */}
                {filteredSchedules.length > 0 ? (
                    viewMode === 'card' ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
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
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="text-left py-3 px-4 font-semibold text-sm">Schedule Name</th>
                                                <th className="text-left py-3 px-4 font-semibold text-sm">Department</th>
                                                <th className="text-left py-3 px-4 font-semibold text-sm">Effective Date</th>
                                                <th className="text-left py-3 px-4 font-semibold text-sm">Status</th>
                                                <th className="text-center py-3 px-4 font-semibold text-sm">Employees</th>
                                                <th className="text-right py-3 px-4 font-semibold text-sm">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredSchedules.map((schedule) => (
                                                <tr key={schedule.id} className="border-b last:border-0 hover:bg-muted/50 transition-colors">
                                                    <td className="py-3 px-4">
                                                        <div className="font-medium">{schedule.name}</div>
                                                        {schedule.description && (
                                                            <div className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                                                {schedule.description}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-4 text-sm">
                                                        {schedule.department_name || <span className="text-muted-foreground">All Departments</span>}
                                                    </td>
                                                    <td className="py-3 px-4 text-sm">
                                                        {new Date(schedule.effective_date).toLocaleDateString('en-US', {
                                                            year: 'numeric',
                                                            month: 'short',
                                                            day: 'numeric'
                                                        })}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(schedule.status)}`}>
                                                            {schedule.status ? schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1) : 'Active'}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4 text-center">
                                                        <span className="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary font-semibold text-sm">
                                                            {schedule.assigned_employees_count || 0}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <div className="flex gap-1 justify-end">
                                                            <PermissionGate permission="hr.workforce.schedules.update">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        handleEditClick(schedule);
                                                                    }}
                                                                    className="h-8 w-8 p-0"
                                                                    title="Edit schedule"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            </PermissionGate>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleDuplicateClick(schedule);
                                                                }}
                                                                className="h-8 w-8 p-0"
                                                                title="Duplicate schedule"
                                                            >
                                                                <Copy className="h-4 w-4" />
                                                            </Button>
                                                            <PermissionGate permission="hr.workforce.schedules.delete">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        handleDeleteClick(schedule.id);
                                                                    }}
                                                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                                    title="Delete schedule"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </PermissionGate>
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
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <div className="rounded-full bg-muted p-4 mb-4">
                                <Calendar className="h-10 w-10 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold mb-2">No schedules found</h3>
                            <p className="text-muted-foreground text-center max-w-sm mb-6">
                                {filters.search || filters.department_id || filters.status !== 'all'
                                    ? 'No schedules match your current filters. Try adjusting your search criteria.'
                                    : 'Get started by creating your first work schedule to manage employee shifts.'}
                            </p>
                            {!filters.search && !filters.department_id && filters.status === 'all' && (
                                <PermissionGate permission="hr.workforce.schedules.create">
                                    <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                                        <Plus className="h-4 w-4" />
                                        Create Schedule
                                    </Button>
                                </PermissionGate>
                            )}
                        </CardContent>
                    </Card>
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
