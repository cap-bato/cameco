import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    DepartmentFormModal,
    type Department,
} from '@/components/hr/department-form-modal';
import { Building2, Plus, Edit, Archive, ChevronRight, Users } from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { usePermission } from '@/components/permission-gate';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from 'lucide-react';

// ============================================================================
// Type Definitions
// ============================================================================

interface DepartmentIndexProps {
    departments: Department[];
    statistics?: {
        total?: number;
        active?: number;
        inactive?: number;
    };
}

// ============================================================================
// Helper Functions
// ============================================================================

interface DepartmentNode extends Department {
    children?: DepartmentNode[];
}

/**
 * Build hierarchical tree structure from flat department list
 */
function buildDepartmentTree(departments: Department[]): DepartmentNode[] {
    const map = new Map<number, DepartmentNode>();
    const roots: DepartmentNode[] = [];

    // Create map of all departments
    departments.forEach(dept => {
        map.set(dept.id, { ...dept, children: [] });
    });

    // Build tree structure
    departments.forEach(dept => {
        const node = map.get(dept.id)!;
        if (dept.parent_id) {
            const parent = map.get(dept.parent_id);
            if (parent) {
                parent.children = parent.children || [];
                parent.children.push(node);
            }
        } else {
            roots.push(node);
        }
    });

    // Sort children alphabetically
    const sortChildren = (node: DepartmentNode) => {
        if (node.children) {
            node.children.sort((a, b) => a.name.localeCompare(b.name));
            node.children.forEach(sortChildren);
        }
    };

    roots.sort((a, b) => a.name.localeCompare(b.name));
    roots.forEach(sortChildren);

    return roots;
}

// ============================================================================
// Component
// ============================================================================

export default function DepartmentIndex({
    departments,
    statistics = {}
}: DepartmentIndexProps) {
    const { hasPermission } = usePermission();
    const page = usePage();
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedDepartment, setSelectedDepartment] = useState<Department | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [expandedDepts, setExpandedDepts] = useState<Set<number>>(new Set());

    // Detect if accessed from Admin or HR context
    const isAdminContext = page.url.startsWith('/admin');
    const routePrefix = isAdminContext ? '/admin' : '/hr';

    useEffect(() => {
        // Check if user has either HR manage or Admin view permission
        const hasAccess = hasPermission('hr.departments.manage') || hasPermission('admin.departments.view');
        if (!hasAccess) {
            router.visit(isAdminContext ? '/admin/dashboard' : '/hr/dashboard');
        }
    }, [hasPermission, isAdminContext]);

    const departmentTree = useMemo(() => buildDepartmentTree(departments), [departments]);

    const handleCreateClick = () => {
        setSelectedDepartment(null);
        setModalMode('create');
        setIsModalOpen(true);
    };

    const handleEditClick = (dept: Department) => {
        setSelectedDepartment(dept);
        setModalMode('edit');
        setIsModalOpen(true);
    };

    const handleAddChildClick = (parentDept: Department) => {
        setSelectedDepartment({
            ...parentDept,
            id: 0,
            parent_id: parentDept.id,
        });
        setModalMode('create');
        setIsModalOpen(true);
    };

    const toggleExpanded = (deptId: number) => {
        const newExpanded = new Set(expandedDepts);
        if (newExpanded.has(deptId)) {
            newExpanded.delete(deptId);
        } else {
            newExpanded.add(deptId);
        }
        setExpandedDepts(newExpanded);
    };

    const handleModalSubmit = async (data: Omit<Department, 'id' | 'employee_count'>) => {
        const url = modalMode === 'create'
            ? `${routePrefix}/departments`
            : `${routePrefix}/departments/${selectedDepartment?.id}`;

        const method = modalMode === 'create' ? 'post' : 'put';

        router[method](url, data, {
            onSuccess: () => {
                setIsModalOpen(false);
            },
        });
    };

    const handleArchive = (dept: Department) => {
        if (confirm(`Are you sure you want to archive "${dept.name}"?`)) {
            router.delete(`${routePrefix}/departments/${dept.id}`);
        }
    };

    /**
     * Render department tree node recursively
     */
    const renderDepartmentNode = (node: DepartmentNode, depth = 0) => {
        const hasChildren = node.children && node.children.length > 0;
        const isExpanded = expandedDepts.has(node.id);

        return (
            <div key={node.id} className="border-l border-slate-200 dark:border-slate-800">
                <div
                    className={`flex items-center gap-2 border-b border-slate-100 px-4 py-3 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/50`}
                    style={{ marginLeft: `${depth * 24}px` }}
                >
                    {/* Expand Button */}
                    {hasChildren && (
                        <button
                            onClick={() => toggleExpanded(node.id)}
                            className="flex-shrink-0 p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"
                        >
                            <ChevronRight
                                className={`h-4 w-4 transition-transform ${
                                    isExpanded ? 'rotate-90' : ''
                                }`}
                            />
                        </button>
                    )}
                    {!hasChildren && <div className="w-6 flex-shrink-0" />}

                    {/* Department Icon */}
                    <Building2 className="h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400" />

                    {/* Department Info */}
                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2">
                            <span className="font-medium text-slate-900 dark:text-slate-100">
                                {node.name}
                            </span>
                            {!node.is_active && (
                                <Badge variant="outline" className="text-xs">
                                    Inactive
                                </Badge>
                            )}
                            {node.is_active && (
                                <Badge variant="outline" className="text-xs bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400">
                                    Active
                                </Badge>
                            )}
                        </div>
                        {node.code && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {node.code}
                            </div>
                        )}
                    </div>

                    {/* Employee Count */}
                    {node.employee_count !== undefined && (
                        <div className="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400 flex-shrink-0">
                            <Users className="h-3 w-3" />
                            <span>{node.employee_count}</span>
                        </div>
                    )}

                    {/* Actions */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel className="text-xs">Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => handleEditClick(node)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleAddChildClick(node)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Child
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => handleArchive(node)}
                                className="text-red-600 dark:text-red-400"
                            >
                                <Archive className="mr-2 h-4 w-4" />
                                Archive
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Render Children */}
                {hasChildren && isExpanded && node.children!.map(child => renderDepartmentNode(child, depth + 1))}
            </div>
        );
    };

    return (
        <AppLayout>
            <Head title="Departments Management" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-100">
                            Departments
                        </h1>
                        <p className="mt-1 text-slate-600 dark:text-slate-400">
                            Manage organizational departments and hierarchy
                        </p>
                    </div>
                    <Button
                        onClick={handleCreateClick}
                        className="gap-2 bg-blue-600 hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4" />
                        New Department
                    </Button>
                </div>

                {/* Statistics */}
                {statistics.total !== undefined && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-400">
                                    Total Departments
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

                {/* Departments Tree */}
                <Card>
                    <CardHeader>
                        <CardTitle>Organization Structure</CardTitle>
                        <CardDescription>
                            Click on the arrow to expand/collapse departments and view hierarchy
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {departments.length === 0 ? (
                            <div className="py-12 text-center">
                                <Building2 className="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
                                <p className="mt-4 text-slate-600 dark:text-slate-400">
                                    No departments found. Create one to get started.
                                </p>
                                <Button
                                    onClick={handleCreateClick}
                                    className="mt-4 bg-blue-600 hover:bg-blue-700"
                                >
                                    Create Department
                                </Button>
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-200 border border-slate-200 rounded-lg dark:divide-slate-800 dark:border-slate-800">
                                {departmentTree.map(dept => renderDepartmentNode(dept))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Department Form Modal */}
            <DepartmentFormModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSubmit={handleModalSubmit}
                department={selectedDepartment}
                departments={departments}
                mode={modalMode}
            />
        </AppLayout>
    );
}
