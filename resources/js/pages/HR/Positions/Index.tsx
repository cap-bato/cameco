import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { usePermission } from '@/components/permission-gate';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    PositionFormModal,
    type Position,
    type Department,
} from '@/components/hr/position-form-modal';
import { Briefcase, Plus, Edit, Archive, MoreHorizontal } from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';

// ============================================================================
// Type Definitions
// ============================================================================

interface PositionIndexProps {
    positions: Position[];
    departments: Department[];
    statistics?: {
        total?: number;
        active?: number;
        inactive?: number;
    };
}

// ============================================================================
// Component
// ============================================================================

export default function PositionIndex({
    positions,
    departments,
    statistics = {}
}: PositionIndexProps) {
    const { hasPermission } = usePermission();
    const page = usePage();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedPosition, setSelectedPosition] = useState<Position | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [selectedDepartmentFilter, setSelectedDepartmentFilter] = useState<number | null>(null);

    // Detect if accessed from Admin or HR context
    const isAdminContext = page.url.startsWith('/admin');
    const routePrefix = isAdminContext ? '/admin' : '/hr';

    useEffect(() => {
        // Check if user has either HR manage or Admin view permission
        const hasAccess = hasPermission('hr.positions.manage') || hasPermission('admin.positions.view');
        if (!hasAccess) {
            router.visit(isAdminContext ? '/admin/dashboard' : '/hr/dashboard');
        }
    }, [hasPermission, isAdminContext]);

    // Group positions by department
    const positionsByDepartment = useMemo(() => {
        const grouped = new Map<number, Position[]>();

        departments.forEach(dept => {
            grouped.set(dept.id, []);
        });

        positions.forEach(pos => {
            const group = grouped.get(pos.department_id) || [];
            group.push(pos);
            grouped.set(pos.department_id, group);
        });

        // Sort positions within each department
        grouped.forEach((positions) => {
            positions.sort((a, b) => a.title.localeCompare(b.title));
        });

        return grouped;
    }, [positions, departments]);

    // Filter departments to display
    const visibleDepartments = useMemo(() => {
        return departments.filter(dept => {
            if (!selectedDepartmentFilter) return true;
            return dept.id === selectedDepartmentFilter;
        });
    }, [departments, selectedDepartmentFilter]);

    const handleCreateClick = () => {
        setSelectedPosition(null);
        setModalMode('create');
        setIsModalOpen(true);
    };

    const handleEditClick = (position: Position) => {
        setSelectedPosition(position);
        setModalMode('edit');
        setIsModalOpen(true);
    };

    const handleModalSubmit = async (data: Omit<Position, 'id' | 'employee_count'>) => {
        const url = modalMode === 'create'
            ? `${routePrefix}/positions`
            : `${routePrefix}/positions/${selectedPosition?.id}`;

        const method = modalMode === 'create' ? 'post' : 'put';

        router[method](url, data, {
            onSuccess: () => {
                setIsModalOpen(false);
            },
        });
    };

    const handleArchive = (position: Position) => {
        if (confirm(`Are you sure you want to archive "${position.title}"?`)) {
            router.delete(`${routePrefix}/positions/${position.id}`);
        }
    };

    /**
     * Get position title by ID
     */
    const getPositionTitle = (posId: number | null): string | null => {
        if (!posId) return null;
        return positions.find(p => p.id === posId)?.title || null;
    };

    /**
     * Format currency
     */
    const formatCurrency = (value: number | null): string => {
        if (!value) return 'N/A';
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(value);
    };

    return (
        <AppLayout>
            <Head title="Positions Management" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">
                            Positions
                        </h1>
                        <p className="mt-1 text-slate-600 dark:text-slate-400">
                            Manage job positions and reporting structures
                        </p>
                    </div>
                    <Button
                        onClick={handleCreateClick}
                        className="gap-2 bg-blue-600 hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4" />
                        New Position
                    </Button>
                </div>

                {/* Statistics */}
                {statistics.total !== undefined && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                    Total Positions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                    {statistics.total}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                    Active
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {statistics.active || 0}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                    Inactive
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-500 dark:text-slate-400">
                                    {statistics.inactive || 0}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Department Filter */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Filter by Department</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant={selectedDepartmentFilter === null ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setSelectedDepartmentFilter(null)}
                                className={selectedDepartmentFilter === null ? 'bg-blue-600 hover:bg-blue-700' : ''}
                            >
                                All Departments
                            </Button>
                            {departments.map(dept => (
                                <Button
                                    key={dept.id}
                                    variant={selectedDepartmentFilter === dept.id ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setSelectedDepartmentFilter(dept.id)}
                                    className={selectedDepartmentFilter === dept.id ? 'bg-blue-600 hover:bg-blue-700' : ''}
                                >
                                    {dept.name}
                                </Button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Positions by Department */}
                {positions.length === 0 ? (
                    <Card>
                        <CardContent className="pt-12">
                            <div className="text-center">
                                <Briefcase className="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
                                <p className="mt-4 text-slate-600 dark:text-slate-400">
                                    No positions found. Create one to get started.
                                </p>
                                <Button
                                    onClick={handleCreateClick}
                                    className="mt-4 bg-blue-600 hover:bg-blue-700"
                                >
                                    Create Position
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {visibleDepartments.map(department => {
                            const deptPositions = positionsByDepartment.get(department.id) || [];

                            return (
                                <Card key={department.id}>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Briefcase className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            {department.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {deptPositions.length} position{deptPositions.length !== 1 ? 's' : ''}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {deptPositions.length === 0 ? (
                                            <div className="py-8 text-center text-sm text-slate-600 dark:text-slate-400">
                                                No positions in this department
                                            </div>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow className="border-slate-200 dark:border-slate-800">
                                                            <TableHead className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Position Title
                                                            </TableHead>
                                                            <TableHead className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Code
                                                            </TableHead>
                                                            <TableHead className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Reports To
                                                            </TableHead>
                                                            <TableHead className="text-right font-semibold text-slate-900 dark:text-slate-100">
                                                                Salary Range
                                                            </TableHead>
                                                            <TableHead className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Status
                                                            </TableHead>
                                                            <TableHead className="text-right font-semibold text-slate-900 dark:text-slate-100">
                                                                <span className="sr-only">Actions</span>
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {deptPositions.map(position => (
                                                            <TableRow
                                                                key={position.id}
                                                                className="border-slate-100 dark:border-slate-800"
                                                            >
                                                                <TableCell className="font-medium text-slate-900 dark:text-slate-100">
                                                                    {position.title}
                                                                </TableCell>
                                                                <TableCell className="text-slate-600 dark:text-slate-400">
                                                                    <Badge variant="outline">
                                                                        {position.code}
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell className="text-sm text-slate-600 dark:text-slate-400">
                                                                    {position.reports_to
                                                                        ? getPositionTitle(position.reports_to) || 'N/A'
                                                                        : 'None (Top Level)'}
                                                                </TableCell>
                                                                <TableCell className="text-right text-sm text-slate-600 dark:text-slate-400">
                                                                    {position.salary_min && position.salary_max ? (
                                                                        <div>
                                                                            <div>{formatCurrency(position.salary_min)}</div>
                                                                            <div className="text-xs text-slate-500 dark:text-slate-500">
                                                                                to {formatCurrency(position.salary_max)}
                                                                            </div>
                                                                        </div>
                                                                    ) : (
                                                                        'Not set'
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {position.is_active ? (
                                                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400">
                                                                            Active
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge variant="outline">
                                                                            Inactive
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    <DropdownMenu>
                                                                        <DropdownMenuTrigger asChild>
                                                                            <Button
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="h-8 w-8 p-0"
                                                                            >
                                                                                <MoreHorizontal className="h-4 w-4" />
                                                                            </Button>
                                                                        </DropdownMenuTrigger>
                                                                        <DropdownMenuContent align="end">
                                                                            <DropdownMenuLabel className="text-xs">
                                                                                Actions
                                                                            </DropdownMenuLabel>
                                                                            <DropdownMenuSeparator />
                                                                            <DropdownMenuItem
                                                                                onClick={() =>
                                                                                    handleEditClick(position)
                                                                                }
                                                                            >
                                                                                <Edit className="mr-2 h-4 w-4" />
                                                                                Edit
                                                                            </DropdownMenuItem>
                                                                            <DropdownMenuSeparator />
                                                                            <DropdownMenuItem
                                                                                onClick={() =>
                                                                                    handleArchive(position)
                                                                                }
                                                                                className="text-red-600 dark:text-red-400"
                                                                            >
                                                                                <Archive className="mr-2 h-4 w-4" />
                                                                                Archive
                                                                            </DropdownMenuItem>
                                                                        </DropdownMenuContent>
                                                                    </DropdownMenu>
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Position Form Modal */}
            <PositionFormModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSubmit={handleModalSubmit}
                position={selectedPosition}
                departments={departments}
                positions={positions}
                mode={modalMode}
            />
        </AppLayout>
    );
}
