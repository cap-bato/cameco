import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft, Save } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
    description?: string;
    has_permission?: boolean;
}

interface RoleData {
    id: number;
    name: string;
    description: string;
    permissions: number[];
}

interface Props {
    role: RoleData | null;
    permissions: Record<string, Permission[]>;
}

export default function RoleForm({ role, permissions }: Props) {
    const isEdit = role !== null;

    const initialPermissions = useMemo(() => {
        if (role?.permissions && role.permissions.length > 0) {
            return role.permissions;
        }

        // Fallback for edit payloads that rely on has_permission flags.
        return Object.values(permissions)
            .flat()
            .filter((p) => p.has_permission)
            .map((p) => p.id);
    }, [permissions, role]);

    const form = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: initialPermissions as number[],
    });

    const togglePermission = (permissionId: number) => {
        const exists = form.data.permissions.includes(permissionId);
        form.setData(
            'permissions',
            exists
                ? form.data.permissions.filter((id) => id !== permissionId)
                : [...form.data.permissions, permissionId],
        );
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && role) {
            form.put(`/system/security/roles/${role.id}`);
            return;
        }

        form.post('/system/security/roles');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'Security & Access', href: '#' },
                { title: 'Roles & Permissions', href: '/system/security/roles' },
                { title: isEdit ? 'Edit Role' : 'Create Role', href: '#' },
            ]}
        >
            <Head title={isEdit ? 'Edit Role' : 'Create Role'} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight dark:text-foreground">
                            {isEdit ? 'Edit Role' : 'Create Role'}
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            {isEdit
                                ? 'Update role details and assigned permissions.'
                                : 'Create a new role and assign permissions.'}
                        </p>
                    </div>
                    <Link href="/system/security/roles">
                        <Button variant="outline" className="gap-2">
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                </div>

                {(form.errors as Record<string, string>).error && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        {(form.errors as Record<string, string>).error}
                    </div>
                )}

                <form onSubmit={onSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Role Information</CardTitle>
                            <CardDescription>Basic role details used by access control.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Role Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="e.g. payroll_officer"
                                />
                                {form.errors.name && (
                                    <p className="text-sm text-red-600">{form.errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    placeholder="Role purpose and scope"
                                />
                                {form.errors.description && (
                                    <p className="text-sm text-red-600">{form.errors.description}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Permissions</CardTitle>
                            <CardDescription>Select all permissions this role should have.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {Object.keys(permissions).length === 0 ? (
                                <p className="text-sm text-muted-foreground">No permissions available.</p>
                            ) : (
                                Object.entries(permissions).map(([group, items]) => (
                                    <div key={group} className="space-y-3 rounded-md border p-4">
                                        <h3 className="text-sm font-semibold">{group}</h3>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            {items.map((permission) => (
                                                <label
                                                    key={permission.id}
                                                    htmlFor={`permission-${permission.id}`}
                                                    className="flex cursor-pointer items-start gap-3 rounded-md border p-3"
                                                >
                                                    <Checkbox
                                                        id={`permission-${permission.id}`}
                                                        checked={form.data.permissions.includes(permission.id)}
                                                        onCheckedChange={() => togglePermission(permission.id)}
                                                    />
                                                    <div className="space-y-0.5">
                                                        <p className="text-sm font-medium">{permission.name}</p>
                                                        {permission.description && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {permission.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}

                            {form.errors.permissions && (
                                <p className="text-sm text-red-600">{form.errors.permissions}</p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Link href="/system/security/roles">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button type="submit" disabled={form.processing} className="gap-2">
                            <Save className="h-4 w-4" />
                            {form.processing ? 'Saving...' : isEdit ? 'Update Role' : 'Create Role'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
