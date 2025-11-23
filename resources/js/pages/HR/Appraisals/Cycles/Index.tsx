import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Plus, Edit2, Eye, Lock, Users, Trash2, Clock, CheckCircle2 } from 'lucide-react';
import { CycleFormModal } from '@/components/hr/appraisal/cycle-form-modal';
import { CycleStatusBadge } from '@/components/hr/appraisal/cycle-status-badge';
import { EmployeeAssignmentModal } from '@/components/hr/appraisal/employee-assignment-modal';
import { AppraisalCycle } from '@/types/appraisal-pages';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

interface Employee {
    id: number;
    name: string;
    employee_number: string;
    department: string;
    position: string;
}

interface CyclesIndexProps {
    cycles: Array<AppraisalCycle & {
        completion_percentage: number;
        criteria?: Array<{ name: string; weight: number }>;
    }>;
    employees: Employee[];
    stats: {
        total_cycles: number;
        active_cycles: number;
        avg_completion_rate: number;
        pending_appraisals: number;
    };
}

interface StatCardProps {
    title: string;
    value: string | number;
    icon: React.ReactNode;
    color: string;
}

const StatCard = ({ title, value, icon: Icon, color }: StatCardProps) => (
    <Card className="bg-white">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">{title}</CardTitle>
            <div className={`p-2 rounded-lg ${color}`}>{Icon}</div>
        </CardHeader>
        <CardContent>
            <div className="text-2xl font-bold">{value}</div>
        </CardContent>
    </Card>
);

export default function CyclesIndex({ cycles: rawCycles, employees, stats }: CyclesIndexProps) {
    const cycles = rawCycles as Array<AppraisalCycle & { completion_percentage: number; criteria?: Array<{ name: string; weight: number }> }>;
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isAssignmentOpen, setIsAssignmentOpen] = useState(false);
    const [selectedCycleForAssignment, setSelectedCycleForAssignment] = useState<number | null>(null);
    const [editingCycle, setEditingCycle] = useState<(typeof cycles)[0] | undefined>(undefined);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'open' | 'closed'>('all');
    const [yearFilter, setYearFilter] = useState<string>('all');
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Appraisals', href: '/hr/appraisals' },
        { title: 'Cycles', href: '/hr/appraisals/cycles' },
    ];

    // Filter cycles
    const filteredCycles = cycles.filter((cycle) => {
        const matchesSearch = cycle.name.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || cycle.status === statusFilter;
        const cycleYear = new Date(cycle.start_date).getFullYear().toString();
        const matchesYear = yearFilter === 'all' || cycleYear === yearFilter;
        return matchesSearch && matchesStatus && matchesYear;
    });

    // Get unique years from cycles
    const years = Array.from(
        new Set(cycles.map((c) => new Date(c.start_date).getFullYear().toString()))
    ).sort((a, b) => b.localeCompare(a));

    const handleEdit = (cycle: (typeof cycles)[0]) => {
        setEditingCycle(cycle);
        setIsFormOpen(true);
    };

    const handleDelete = (id: number) => {
        router.delete(`/hr/appraisals/cycles/${id}`, {
            onSuccess: () => {
                // Cycle deleted successfully
            },
        });
    };

    const handleCloseCycle = (id: number) => {
        router.post(`/hr/appraisals/cycles/${id}/close`, {}, {
            onSuccess: () => {
                // Cycle closed successfully
            },
        });
    };

    const handleFormClose = () => {
        setIsFormOpen(false);
        setEditingCycle(undefined);
    };

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Appraisal Cycles" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Appraisal Cycles</h1>
                        <p className="text-gray-500 mt-1">Manage and track performance review cycles</p>
                    </div>
                    <Button onClick={() => setIsFormOpen(true)}>
                        <Plus className="h-4 w-4 mr-2" />
                        Create Cycle
                    </Button>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        title="Total Cycles"
                        value={stats.total_cycles}
                        icon={<Clock className="h-5 w-5 text-blue-600" />}
                        color="bg-blue-50"
                    />
                    <StatCard
                        title="Active Cycles"
                        value={stats.active_cycles}
                        icon={<CheckCircle2 className="h-5 w-5 text-green-600" />}
                        color="bg-green-50"
                    />
                    <StatCard
                        title="Avg Completion"
                        value={`${stats.avg_completion_rate}%`}
                        icon={<CheckCircle2 className="h-5 w-5 text-purple-600" />}
                        color="bg-purple-50"
                    />
                    <StatCard
                        title="Pending Appraisals"
                        value={stats.pending_appraisals}
                        icon={<Users className="h-5 w-5 text-orange-600" />}
                        color="bg-orange-50"
                    />
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-lg border space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold">Filters</h3>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant={viewMode === 'grid' ? 'default' : 'outline'}
                                onClick={() => setViewMode('grid')}
                            >
                                Grid
                            </Button>
                            <Button
                                size="sm"
                                variant={viewMode === 'list' ? 'default' : 'outline'}
                                onClick={() => setViewMode('list')}
                            >
                                List
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Search */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Search Cycles</label>
                            <Input
                                placeholder="Search by cycle name..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                        </div>

                        {/* Status Filter */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Status</label>
                            <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as 'all' | 'open' | 'closed')}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="open">Open</SelectItem>
                                    <SelectItem value="closed">Closed</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Year Filter */}
                        <div>
                            <label className="text-sm font-medium mb-2 block">Year</label>
                            <Select value={yearFilter} onValueChange={(value) => setYearFilter(value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Years</SelectItem>
                                    {years.map((year) => (
                                        <SelectItem key={year} value={year}>
                                            {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                {/* Cycles Display */}
                {filteredCycles.length === 0 ? (
                    <Card className="bg-white border-2 border-dashed">
                        <CardContent className="p-12 text-center">
                            <Clock className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                            <h3 className="text-lg font-semibold text-gray-900 mb-1">No cycles found</h3>
                            <p className="text-gray-500 mb-6">
                                {searchQuery || statusFilter !== 'all' || yearFilter !== 'all'
                                    ? 'Try adjusting your filters'
                                    : 'Create your first appraisal cycle to get started'}
                            </p>
                            {!searchQuery && statusFilter === 'all' && yearFilter === 'all' && (
                                <Button onClick={() => setIsFormOpen(true)}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    Create First Cycle
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : viewMode === 'grid' ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredCycles.map((cycle) => (
                            <Card key={cycle.id} className="hover:shadow-lg transition-shadow flex flex-col">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1 min-w-0">
                                            <CardTitle className="text-lg break-words">{cycle.name}</CardTitle>
                                            <p className="text-sm text-gray-500 mt-1">
                                                {new Date(cycle.start_date).toLocaleDateString()} -{' '}
                                                {new Date(cycle.end_date).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <CycleStatusBadge status={cycle.status} />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 flex-1 flex flex-col">
                                    {/* Progress Bar */}
                                    <div>
                                        <div className="flex justify-between items-center mb-2">
                                            <span className="text-sm font-medium">Completion Rate</span>
                                            <span className="text-sm font-semibold">{cycle.completion_percentage}%</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-blue-600 h-2 rounded-full transition-all"
                                                style={{ width: `${cycle.completion_percentage}%` }}
                                            />
                                        </div>
                                    </div>

                                    {/* Criteria Tags */}
                                    {cycle.criteria && cycle.criteria.length > 0 && (
                                        <div>
                                            <p className="text-xs font-medium text-gray-600 mb-2">Criteria:</p>
                                            <div className="flex flex-wrap gap-1">
                                                {cycle.criteria.slice(0, 3).map((criterion, idx) => (
                                                    <Badge key={idx} variant="secondary" className="text-xs">
                                                        {criterion.name} ({criterion.weight}%)
                                                    </Badge>
                                                ))}
                                                {cycle.criteria.length > 3 && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        +{cycle.criteria.length - 3} more
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Spacer to push buttons to bottom */}
                                    <div className="flex-1" />

                                    {/* Actions */}
                                    <div className="flex flex-col gap-2 pt-2 border-t">
                                        <div className="flex gap-2">
                                            <Link href={`/hr/appraisals/cycles/${cycle.id}`} className="flex-1">
                                                <Button size="sm" variant="outline" className="w-full">
                                                    <Eye className="h-4 w-4" />
                                                    <span className="hidden sm:inline ml-1">View</span>
                                                </Button>
                                            </Link>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => handleEdit(cycle)}
                                                className="flex-1"
                                            >
                                                <Edit2 className="h-4 w-4" />
                                                <span className="hidden sm:inline ml-1">Edit</span>
                                            </Button>
                                        </div>
                                        {cycle.status === 'open' && (
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        setSelectedCycleForAssignment(cycle.id);
                                                        setIsAssignmentOpen(true);
                                                    }}
                                                    className="flex-1"
                                                >
                                                    <Users className="h-4 w-4" />
                                                    <span className="hidden sm:inline ml-1">Assign</span>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleCloseCycle(cycle.id)}
                                                    className="flex-1"
                                                >
                                                    <Lock className="h-4 w-4" />
                                                    <span className="hidden sm:inline ml-1">Close</span>
                                                </Button>
                                            </div>
                                        )}
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button size="sm" variant="outline" className="w-full">
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="hidden sm:inline ml-1">Delete</span>
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogTitle>Delete Cycle</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Are you sure you want to delete "{cycle.name}"? This action cannot be
                                                    undone.
                                                </AlertDialogDescription>
                                                <div className="flex gap-3 justify-end">
                                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                    <AlertDialogAction
                                                        onClick={() => handleDelete(cycle.id)}
                                                        className="bg-red-600 hover:bg-red-700"
                                                    >
                                                        Delete
                                                    </AlertDialogAction>
                                                </div>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="px-6 py-3 text-left text-sm font-semibold">Name</th>
                                        <th className="px-6 py-3 text-left text-sm font-semibold">Date Range</th>
                                        <th className="px-6 py-3 text-left text-sm font-semibold">Status</th>
                                        <th className="px-6 py-3 text-left text-sm font-semibold">Completion</th>
                                        <th className="px-6 py-3 text-left text-sm font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCycles.map((cycle) => (
                                        <tr key={cycle.id} className="border-b hover:bg-gray-50">
                                            <td className="px-6 py-4 font-medium">{cycle.name}</td>
                                            <td className="px-6 py-4 text-sm text-gray-600">
                                                {new Date(cycle.start_date).toLocaleDateString()} -{' '}
                                                {new Date(cycle.end_date).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-4">
                                                <CycleStatusBadge status={cycle.status} showIcon={false} />
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="w-32">
                                                    <div className="flex justify-between items-center mb-1">
                                                        <span className="text-xs text-gray-600">
                                                            {cycle.completion_percentage}%
                                                        </span>
                                                    </div>
                                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                                        <div
                                                            className="bg-blue-600 h-2 rounded-full"
                                                            style={{ width: `${cycle.completion_percentage}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex gap-2">
                                                    <Link href={`/hr/appraisals/cycles/${cycle.id}`}>
                                                        <Button size="sm" variant="ghost">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleEdit(cycle)}
                                                    >
                                                        <Edit2 className="h-4 w-4" />
                                                    </Button>
                                                    {cycle.status === 'open' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => {
                                                                    setSelectedCycleForAssignment(cycle.id);
                                                                    setIsAssignmentOpen(true);
                                                                }}
                                                            >
                                                                <Users className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => handleCloseCycle(cycle.id)}
                                                            >
                                                                <Lock className="h-4 w-4" />
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>

            {/* Form Modal */}
            <CycleFormModal
                isOpen={isFormOpen}
                onClose={handleFormClose}
                cycle={editingCycle}
                mode={editingCycle ? 'edit' : 'create'}
            />

            {/* Employee Assignment Modal */}
            {selectedCycleForAssignment && (
                <EmployeeAssignmentModal
                    isOpen={isAssignmentOpen}
                    onClose={() => {
                        setIsAssignmentOpen(false);
                        setSelectedCycleForAssignment(null);
                    }}
                    cycleId={selectedCycleForAssignment}
                    employees={employees}
                />
            )}
        </AppLayout>
    );
}
