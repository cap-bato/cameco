import React, { useState, useMemo } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Plus, Clock, AlertTriangle, Filter, Calendar, Download, BarChart3 } from 'lucide-react';
import AssignmentFilters, { AssignmentFiltersState } from '@/components/workforce/assignment-filters';
import AssignmentCalendar from '@/components/workforce/assignment-calendar';
import CoverageAnalytics from '@/components/workforce/coverage-analytics';
import CreateEditAssignmentModal from './CreateEditAssignmentModal';
import AssignmentActionsMenu from '@/components/workforce/assignment-actions-menu';
import AssignmentDetailModal from '@/components/workforce/assignment-detail-modal';
import {
    formatTime,
    calculateShiftDuration,
    getShiftTypeColorClasses,
    getStatusColorClasses,
} from '@/lib/workforce-utils';
import { ShiftAssignment, Department, EmployeeReference } from '@/types/workforce-pages';

interface AssignmentsIndexProps {
    assignments: ShiftAssignment[];
    summary: {
        total_assignments: number;
        todays_shifts: number;
        coverage_percentage: number;
        overtime_hours: number;
        conflicts_count?: number;
        understaffed_days?: number;
    };
    departments: Department[];
    employees: EmployeeReference[];
    schedules: Array<{ id: number; name: string; shift_start: string; shift_end: string }>;
    view_mode?: string;
}

export default function AssignmentsIndex() {
    const { assignments: initialAssignments, summary, departments, employees, schedules } = usePage().props as unknown as AssignmentsIndexProps;

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Assignments', href: '/hr/workforce/assignments' },
    ];

    const [viewMode, setViewMode] = useState<'list' | 'calendar' | 'analytics'>('list');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [selectedAssignment, setSelectedAssignment] = useState<ShiftAssignment | null>(null);
    const [editingAssignment, setEditingAssignment] = useState<ShiftAssignment | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [filters, setFilters] = useState<AssignmentFiltersState>({
        department_id: 'all',
        employee_id: 'all',
        shift_type: 'all',
        status: 'all',
        date_from: '',
        date_to: '',
    });
    const [showFilters, setShowFilters] = useState(false);

    // Filter assignments based on search and filters
    const filteredAssignments = useMemo(() => {
        return (Array.isArray(initialAssignments) ? initialAssignments : []).filter((assignment) => {
            const matchesSearch =
                assignment.employee_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                assignment.employee_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                assignment.schedule_name?.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesDept =
                filters.department_id === 'all' ||
                assignment.department_id === parseInt(filters.department_id);

            const matchesEmployee =
                filters.employee_id === 'all' ||
                assignment.employee_id === parseInt(filters.employee_id);

            const matchesShiftType =
                filters.shift_type === 'all' ||
                assignment.shift_type === filters.shift_type;

            const matchesStatus =
                filters.status === 'all' ||
                assignment.status === filters.status;

            const matchesOvertime =
                !filters.is_overtime ||
                filters.is_overtime === 'all' ||
                (filters.is_overtime === 'true' && assignment.is_overtime) ||
                (filters.is_overtime === 'false' && !assignment.is_overtime);

            const assignmentDate = new Date(assignment.date);
            const matchesDateFrom =
                !filters.date_from ||
                assignmentDate >= new Date(filters.date_from);

            const matchesDateTo =
                !filters.date_to ||
                assignmentDate <= new Date(filters.date_to);

            return (
                matchesSearch &&
                matchesDept &&
                matchesEmployee &&
                matchesShiftType &&
                matchesStatus &&
                matchesOvertime &&
                matchesDateFrom &&
                matchesDateTo
            );
        });
    }, [initialAssignments, searchTerm, filters]);

    const handleEdit = (assignment: ShiftAssignment) => {
        setEditingAssignment(assignment);
        setIsCreateModalOpen(true);
    };

    const handleViewDetails = (assignment: ShiftAssignment) => {
        setSelectedAssignment(assignment);
        setIsDetailModalOpen(true);
    };

    const handleDelete = (assignmentId: number) => {
        router.delete(`/hr/workforce/assignments/${assignmentId}`, {
            onSuccess: () => {
                // Assignment deleted
            },
        });
    };

    const handleMarkOvertime = (assignmentId: number, isOvertime: boolean) => {
        router.patch(`/hr/workforce/assignments/${assignmentId}`, {
            is_overtime: isOvertime,
        } as never, {
            onSuccess: () => {
                // Overtime status updated
            },
        });
    };

    const handleCancel = (assignmentId: number) => {
        router.patch(`/hr/workforce/assignments/${assignmentId}`, {
            status: 'cancelled',
        } as never, {
            onSuccess: () => {
                // Assignment cancelled
            },
        });
    };

    const handleCloseModal = () => {
        setIsCreateModalOpen(false);
        setEditingAssignment(null);
    };

    const handleSaveAssignment = (data: Record<string, unknown>) => {
        if (editingAssignment?.id) {
            router.put(`/hr/workforce/assignments/${editingAssignment.id}`, data as never);
            handleCloseModal();
        } else {
            router.post('/hr/workforce/assignments', data as never);
            handleCloseModal();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Shift Assignments" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Shift Assignments</h1>
                        <p className="text-gray-600 mt-1">Manage employee shift assignments and coverage</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" className="gap-2">
                            <Download className="h-4 w-4" />
                            Export
                        </Button>
                        <Button 
                            variant="outline"
                            onClick={() => router.get('/hr/workforce/assignments/bulk-assign')}
                            className="gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            Bulk Assign
                        </Button>
                        <Button onClick={() => setIsCreateModalOpen(true)} className="gap-2">
                            <Plus className="h-4 w-4" />
                            New Assignment
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                Total Assignments
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{summary.total_assignments}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                Today's Shifts
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-blue-600">{summary.todays_shifts}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                Coverage %
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-green-600">{summary.coverage_percentage}%</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                Overtime Hours
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold text-orange-600">{summary.overtime_hours}h</p>
                        </CardContent>
                    </Card>

                    {summary.conflicts_count !== undefined && (
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-gray-600">
                                    Conflicts
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className={`text-3xl font-bold ${summary.conflicts_count > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                    {summary.conflicts_count}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* View Controls and Filters */}
                <div className="space-y-4">
                    <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
                        <div className="flex-1 flex gap-2">
                            <Input
                                placeholder="Search by employee name or number..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="max-w-sm"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                                className="gap-2"
                            >
                                <Filter className="h-4 w-4" />
                                Filters
                            </Button>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant={viewMode === 'list' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setViewMode('list')}
                            >
                                List View
                            </Button>
                            <Button
                                variant={viewMode === 'calendar' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setViewMode('calendar')}
                                className="gap-2"
                            >
                                <Calendar className="h-4 w-4" />
                                Calendar
                            </Button>
                            <Button
                                variant={viewMode === 'analytics' ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setViewMode('analytics')}
                                className="gap-2"
                            >
                                <BarChart3 className="h-4 w-4" />
                                Analytics
                            </Button>
                        </div>
                    </div>

                    {/* Filters Panel */}
                    {showFilters && (
                        <AssignmentFilters
                            filters={filters}
                            onFilterChange={setFilters}
                            departments={departments}
                            employees={employees}
                        />
                    )}
                </div>

                {/* Assignments Table - Only show in list view */}
                {viewMode === 'list' && (
                <Card>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Shift Time</TableHead>
                                    <TableHead>Duration</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-24">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredAssignments.length > 0 ? (
                                    filteredAssignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {assignment.employee_name}
                                                    {assignment.is_overtime && (
                                                        <Badge variant="secondary" className="text-xs gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            OT
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs text-gray-500">{assignment.employee_number}</p>
                                            </TableCell>
                                            <TableCell>{assignment.department_name}</TableCell>
                                            <TableCell>{new Date(assignment.date).toLocaleDateString()}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge className={`${getShiftTypeColorClasses(assignment.shift_type || 'custom').bg} ${getShiftTypeColorClasses(assignment.shift_type || 'custom').text}`}>
                                                        {assignment.shift_type}
                                                    </Badge>
                                                    <span className="text-sm">
                                                        {formatTime(assignment.shift_start)} - {formatTime(assignment.shift_end)}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{calculateShiftDuration(assignment.shift_start, assignment.shift_end)}h</TableCell>
                                            <TableCell className="text-sm text-gray-600">{assignment.location || '-'}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge className={getStatusColorClasses(assignment.status)}>
                                                        {assignment.status}
                                                    </Badge>
                                                    {assignment.has_conflict && (
                                                        <div title={assignment.conflict_reason || 'Scheduling conflict'}>
                                                            <AlertTriangle className="h-4 w-4 text-red-600" />
                                                        </div>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <AssignmentActionsMenu
                                                    assignment={assignment}
                                                    onEdit={handleEdit}
                                                    onViewDetails={handleViewDetails}
                                                    onDelete={handleDelete}
                                                    onMarkOvertime={handleMarkOvertime}
                                                    onCancel={handleCancel}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-center py-8 text-gray-500">
                                            No assignments found
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
                )}

                {/* Calendar View */}
                {viewMode === 'calendar' && (
                    <AssignmentCalendar
                        assignments={filteredAssignments}
                        onAssignmentClick={(assignment) => {
                            handleEdit(assignment);
                        }}
                    />
                )}

                {/* Coverage Analytics View */}
                {viewMode === 'analytics' && (
                    <CoverageAnalytics
                        assignments={filteredAssignments}
                        departments={departments}
                        requiredStaffPerDay={5}
                    />
                )}

                {/* Create/Edit Modal */}
                <CreateEditAssignmentModal
                    isOpen={isCreateModalOpen}
                    onClose={handleCloseModal}
                    assignment={editingAssignment}
                    employees={employees}
                    departments={departments}
                    schedules={schedules}
                    onConfirm={handleSaveAssignment}
                />

                {/* Assignment Detail Modal */}
                <AssignmentDetailModal
                    isOpen={isDetailModalOpen}
                    onClose={() => {
                        setIsDetailModalOpen(false);
                        setSelectedAssignment(null);
                    }}
                    assignment={selectedAssignment}
                />
            </div>
        </AppLayout>
    );
}
