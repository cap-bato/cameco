import { useState } from 'react';
import { Building2, Plus, Trash2, Edit2, ChevronDown, ChevronRight } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { DepartmentArchiveDialog } from '@/components/hr/department-archive-dialog';
import type { BreadcrumbItem } from '@/types';

type Department = {
  id: number;
  name: string;
  code: string;
  description: string | null;
  parent_id: number | null;
  parent_name: string | null;
  manager_id: number | null;
  manager_name: string | null;
  budget: number | null;
  is_active: boolean;
  positions_count: number;
  employee_count: number;
  created_at: string;
};

type Stats = {
  total: number;
  active: number;
  inactive: number;
  with_manager: number;
  total_budget: number;
};

type DepartmentHierarchy = {
  id: number;
  name: string;
  code: string;
  manager_name: string | null;
  is_active: boolean;
  positions_count: number;
  employee_count: number;
  depth: number;
  children: DepartmentHierarchy[];
};

interface Props {
  departments: Department[];
  hierarchical: DepartmentHierarchy[];
  managers: Array<{ id: number; name: string; email: string }>;
  stats: Stats;
}

export default function Departments({ departments, hierarchical, managers, stats }: Props) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/system/dashboard' },
    { title: 'Organization', href: '/system/organization/overview' },
    { title: 'Departments', href: '#' },
  ];
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());
  const [departmentToArchive, setDepartmentToArchive] = useState<Department | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    code: '',
    description: '',
    parent_id: '',
    manager_id: '',
    budget: '',
  });

  const toggleExpanded = (id: number) => {
    const newExpanded = new Set(expandedIds);
    if (newExpanded.has(id)) {
      newExpanded.delete(id);
    } else {
      newExpanded.add(id);
    }
    setExpandedIds(newExpanded);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (editingId) {
      router.put(`/system/organization/departments/${editingId}`, formData as Record<string, string>);
    } else {
      router.post('/system/organization/departments', formData as Record<string, string>);
    }
    resetForm();
  };

  const handleEdit = (dept: Department) => {
    setFormData({
      name: dept.name,
      code: dept.code,
      description: dept.description || '',
      parent_id: dept.parent_id?.toString() || '',
      manager_id: dept.manager_id?.toString() || '',
      budget: dept.budget?.toString() || '',
    });
    setEditingId(dept.id);
    setShowForm(true);
  };

  const handleDelete = (dept: Department) => {
    setDepartmentToArchive(dept);
  };

  const resetForm = () => {
    setFormData({
      name: '',
      code: '',
      description: '',
      parent_id: '',
      manager_id: '',
      budget: '',
    });
    setEditingId(null);
    setShowForm(false);
  };

  const formatCurrency = (value: number | null) => {
    if (!value) return 'N/A';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
  };

  const DepartmentTreeNode = ({ dept, depth = 0 }: { dept: DepartmentHierarchy; depth?: number }) => {
    const hasChildren = dept.children.length > 0;
    const isExpanded = expandedIds.has(dept.id);

    return (
      <div key={dept.id} style={{ marginLeft: `${depth * 24}px` }}>
        <div className="flex items-center gap-2 py-2 px-3 rounded hover:bg-muted border-l-2 border-transparent hover:border-primary">
          {hasChildren && (
            <button
              onClick={() => toggleExpanded(dept.id)}
              className="p-0 h-5 w-5"
            >
              {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
            </button>
          )}
          {!hasChildren && <div className="h-5 w-5" />}
          
          <Building2 className="h-4 w-4 text-blue-600" />
          
          <div className="flex-1 min-w-0">
            <div className="font-medium text-sm">{dept.name}</div>
            <div className="text-xs text-muted-foreground">{dept.code}</div>
          </div>

          <div className="flex items-center gap-4 text-xs">
            {dept.manager_name && (
              <span className="text-muted-foreground">{dept.manager_name}</span>
            )}
            <Badge variant={dept.is_active ? 'default' : 'secondary'}>
              {dept.is_active ? 'Active' : 'Inactive'}
            </Badge>
            {dept.positions_count > 0 && (
              <Badge variant="outline">{dept.positions_count} Positions</Badge>
            )}
          </div>
        </div>

        {hasChildren && isExpanded && (
          <div>
            {dept.children.map((child) => (
              <DepartmentTreeNode key={child.id} dept={child} depth={depth + 1} />
            ))}
          </div>
        )}
      </div>
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Departments Management" />
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">

        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold dark:text-foreground">Departments Management</h1>
            <p className="text-muted-foreground mt-2">Organize your company structure and manage departments</p>
          </div>
          <Button onClick={() => setShowForm(!showForm)} size="lg">
            <Plus className="mr-2 h-4 w-4" />
            New Department
          </Button>
        </div>

        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Total Departments</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold">{stats.total}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Active</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600">{stats.active}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Inactive</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-yellow-600">{stats.inactive}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">With Manager</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600">{stats.with_manager}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Total Budget</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{formatCurrency(stats.total_budget)}</div>
            </CardContent>
          </Card>
        </div>

        {/* Form */}
        {showForm && (
          <Card className="border-blue-200 bg-blue-50">
            <CardHeader>
              <CardTitle>{editingId ? 'Edit' : 'New'} Department</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="text-sm font-medium">Department Name *</label>
                    <Input
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      placeholder="e.g., Engineering"
                    />
                  </div>

                  <div>
                    <label className="text-sm font-medium">Department Code *</label>
                    <Input
                      required
                      value={formData.code}
                      onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                      placeholder="e.g., ENG"
                      maxLength={10}
                    />
                  </div>

                  <div>
                    <label className="text-sm font-medium">Parent Department</label>
                    <Select value={formData.parent_id} onValueChange={(value) => setFormData({ ...formData, parent_id: value })}>
                      <SelectTrigger>
                        <SelectValue placeholder="None (Root Department)" />
                      </SelectTrigger>
                      <SelectContent>
                        {departments.filter((d) => d.id !== editingId).map((d) => (
                          <SelectItem key={d.id} value={d.id.toString()}>
                            {d.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Manager</label>
                    <Select value={formData.manager_id} onValueChange={(value) => setFormData({ ...formData, manager_id: value })}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select manager" />
                      </SelectTrigger>
                      <SelectContent>
                        {managers.map((m) => (
                          <SelectItem key={m.id} value={m.id.toString()}>
                            {m.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <label className="text-sm font-medium">Budget (USD)</label>
                    <Input
                      type="number"
                      value={formData.budget}
                      onChange={(e) => setFormData({ ...formData, budget: e.target.value })}
                      placeholder="0"
                      min="0"
                    />
                  </div>
                </div>

                <div>
                  <label className="text-sm font-medium">Description</label>
                  <Input
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    placeholder="Department description..."
                  />
                </div>

                <div className="flex gap-2 justify-end">
                  <Button type="button" variant="outline" onClick={resetForm}>
                    Cancel
                  </Button>
                  <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                    {editingId ? 'Update' : 'Create'} Department
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {/* Department Hierarchy Tree */}
        <Card>
          <CardHeader>
            <CardTitle>Department Hierarchy</CardTitle>
            <CardDescription>Organizational structure with sub-departments</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-1">
              {hierarchical.length > 0 ? (
                hierarchical.map((dept) => <DepartmentTreeNode key={dept.id} dept={dept} />)
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  <Building2 className="h-12 w-12 mx-auto mb-3 opacity-20" />
                  <p>No departments yet. Create one to get started.</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* All Departments List */}
        <Card>
          <CardHeader>
            <CardTitle>All Departments</CardTitle>
            <CardDescription>Complete list of all departments</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              {departments.length > 0 ? (
                departments.map((dept) => (
                  <div key={dept.id} className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted">
                    <div className="flex-1">
                      <div className="font-medium">{dept.name}</div>
                      <div className="text-sm text-muted-foreground">
                        {dept.code} {dept.parent_name && `• Parent: ${dept.parent_name}`}
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <Badge variant={dept.is_active ? 'default' : 'secondary'}>
                        {dept.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                      {dept.positions_count > 0 && <Badge variant="outline">{dept.positions_count} Pos</Badge>}
                      {dept.manager_name && <span className="text-sm text-muted-foreground">{dept.manager_name}</span>}
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleEdit(dept)}
                      >
                        <Edit2 className="h-4 w-4" />
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleDelete(dept)}
                        className="text-red-600 hover:text-red-700"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                ))
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  <Building2 className="h-12 w-12 mx-auto mb-3 opacity-20" />
                  <p>No departments found</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Department Archive Dialog */}
        {departmentToArchive && (
          <DepartmentArchiveDialog
            open={!!departmentToArchive}
            onOpenChange={(open) => !open && setDepartmentToArchive(null)}
            departmentId={departmentToArchive.id}
            departmentName={departmentToArchive.name}
            employeeCount={departmentToArchive.employee_count}
            routePrefix="/system/organization"
          />
        )}
      </div>
    </AppLayout>
);
}
