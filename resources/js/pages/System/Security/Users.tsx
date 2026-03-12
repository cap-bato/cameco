import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { CheckCircle2, AlertCircle, Mail, UserPlus, MoreHorizontal, Pencil, KeyRound, UserX, UserCheck, Eye } from 'lucide-react';

interface User {
	id: number;
	name: string;
	email: string;
	is_active: boolean;
	email_verified_at: string | null;
	created_at: string;
	last_login_at: string | null;
	roles: string[];
	audit_logs_count: number;
}

interface Role {
	id: number;
	name: string;
}

interface UsersPageProps {
	users: {
		data: User[];
		current_page: number;
		last_page: number;
		total: number;
	};
	roles: Role[];
	stats: {
		total_users: number;
		active_users: number;
		inactive_users: number;
		unverified_users: number;
	};
	filters: {
		status: string;
		role: string;
		search: string;
	};
}

export default function UsersPage({
	users,
	roles,
	stats,
	filters,
}: UsersPageProps) {
	const [search, setSearch] = useState(filters.search);
	const [status, setStatus] = useState(filters.status);
	const [role, setRole] = useState(filters.role);
	const [showCreateModal, setShowCreateModal] = useState(false);
	const [editingUser, setEditingUser] = useState<User | null>(null);
	const [confirmDeactivateUser, setConfirmDeactivateUser] = useState<User | null>(null);
	const [deactivateReason, setDeactivateReason] = useState('');

	// Flash messages from Inertia
	const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;

	// Edit user form
	const editForm = useForm({
		name: '',
		email: '',
		roles: [] as number[],
	});

	const handleEditOpen = (user: User) => {
		const roleIds = roles.filter((r) => user.roles.includes(r.name)).map((r) => r.id);
		editForm.setData({ name: user.name, email: user.email, roles: roleIds });
		setEditingUser(user);
	};

	const handleEditSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		editForm.put(`/system/users/${editingUser!.id}`, {
			onSuccess: () => {
				setEditingUser(null);
				editForm.reset();
			},
		});
	};

	const toggleEditRole = (roleId: number) => {
		const current = editForm.data.roles;
		editForm.setData(
			'roles',
			current.includes(roleId) ? current.filter((id) => id !== roleId) : [...current, roleId],
		);
	};

	const handleResetPassword = (userId: number) => {
		router.post(`/system/users/${userId}/password-reset`, {}, { preserveScroll: true });
	};

	const handleActivate = (userId: number) => {
		router.post(`/system/users/${userId}/activate`, {}, { preserveScroll: true });
	};

	const handleConfirmDeactivate = () => {
		if (!confirmDeactivateUser) return;
		router.post(
			`/system/users/${confirmDeactivateUser.id}/deactivate`,
			{ reason: deactivateReason },
			{
				preserveScroll: true,
				onSuccess: () => {
					setConfirmDeactivateUser(null);
					setDeactivateReason('');
				},
			},
		);
	};

	// Create user form
	const createForm = useForm({
		name: '',
		email: '',
		password: '',
		password_confirmation: '',
		roles: [] as number[],
	});

	const handleCreateSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		createForm.post('/system/users', {
			onSuccess: () => {
				setShowCreateModal(false);
				createForm.reset();
			},
		});
	};

	const toggleCreateRole = (roleId: number) => {
		const current = createForm.data.roles;
		createForm.setData(
			'roles',
			current.includes(roleId) ? current.filter((id) => id !== roleId) : [...current, roleId],
		);
	};

	const handleFilter = () => {
		const params = new URLSearchParams();
		if (search) params.append('search', search);
		if (status !== 'all') params.append('status', status);
		if (role !== 'all') params.append('role', role);
		router.get('/system/users?' + params.toString());
	};

	return (
		<AppLayout
			breadcrumbs={[
				{ title: 'Dashboard', href: '/dashboard' },
				{ title: 'Security & Access', href: '#' },
				{ title: 'User Management', href: '/system/users' },
			]}
		>
			<Head title="User Management" />

			<div className="space-y-6 p-6">
				{/* Header */}
				<div className="flex items-start justify-between">
					<div>
						<h1 className="text-3xl font-bold tracking-tight dark:text-foreground">User Management</h1>
						<p className="text-muted-foreground mt-1">
							Manage users, their roles, and account status
						</p>
					</div>
					<Button onClick={() => setShowCreateModal(true)} className="gap-2">
						<UserPlus className="h-4 w-4" />
						Create User
					</Button>
				</div>

				{/* Flash messages */}
				{flash?.success && (
					<div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
						{flash.success}
					</div>
				)}
				{flash?.error && (
					<div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
						{flash.error}
					</div>
				)}

				{/* Stats */}
				<div className="grid gap-4 md:grid-cols-4">
					<Card>
						<CardHeader className="pb-3">
							<CardTitle className="text-base">Total Users</CardTitle>
						</CardHeader>
						<CardContent>
							<div className="text-3xl font-bold">{stats.total_users}</div>
						</CardContent>
					</Card>
					<Card>
						<CardHeader className="pb-3">
							<CardTitle className="text-base">Active Users</CardTitle>
						</CardHeader>
						<CardContent>
							<div className="text-3xl font-bold text-green-600">{stats.active_users}</div>
						</CardContent>
					</Card>
					<Card>
						<CardHeader className="pb-3">
							<CardTitle className="text-base">Inactive Users</CardTitle>
						</CardHeader>
						<CardContent>
							<div className="text-3xl font-bold text-red-600">{stats.inactive_users}</div>
						</CardContent>
					</Card>
					<Card>
						<CardHeader className="pb-3">
							<CardTitle className="text-base">Unverified</CardTitle>
						</CardHeader>
						<CardContent>
							<div className="text-3xl font-bold text-yellow-600">{stats.unverified_users}</div>
						</CardContent>
					</Card>
				</div>

				{/* Filters */}
				<Card>
					<CardHeader>
						<CardTitle>Filters</CardTitle>
					</CardHeader>
					<CardContent className="space-y-4">
						<div className="grid gap-4 md:grid-cols-4">
							<div className="space-y-2">
								<label className="text-sm font-medium">Search</label>
								<Input
									placeholder="Name or email..."
									value={search}
									onChange={(e) => setSearch(e.target.value)}
								/>
							</div>

							<div className="space-y-2">
								<label className="text-sm font-medium">Status</label>
								<Select value={status} onValueChange={setStatus}>
									<SelectTrigger>
										<SelectValue />
									</SelectTrigger>
									<SelectContent>
										<SelectItem value="all">All Users</SelectItem>
										<SelectItem value="active">Active</SelectItem>
										<SelectItem value="inactive">Inactive</SelectItem>
										<SelectItem value="pending">Pending Verification</SelectItem>
									</SelectContent>
								</Select>
							</div>

							<div className="space-y-2">
								<label className="text-sm font-medium">Role</label>
								<Select value={role} onValueChange={setRole}>
									<SelectTrigger>
										<SelectValue />
									</SelectTrigger>
									<SelectContent>
										<SelectItem value="all">All Roles</SelectItem>
										{roles.map((r) => (
											<SelectItem key={r.id} value={r.name}>
												{r.name}
											</SelectItem>
										))}
									</SelectContent>
								</Select>
							</div>

							<div className="flex items-end">
								<Button onClick={handleFilter} className="w-full">
									Apply Filters
								</Button>
							</div>
						</div>
					</CardContent>
				</Card>

				{/* Users List */}
				<div className="space-y-4">
					<div className="flex items-center justify-between">
						<h2 className="text-lg font-semibold">
							Users ({users.total})
						</h2>
					</div>

					{users.data.length === 0 ? (
						<Card>
							<CardContent className="py-12 text-center text-muted-foreground">
								No users found matching your filters
							</CardContent>
						</Card>
					) : (
						<div className="space-y-4">
							{users.data.map((user) => (
								<Card key={user.id} className="hover:shadow-md transition-shadow">
									<CardHeader className="pb-3">
										<div className="flex items-start justify-between">
											<div className="space-y-1 flex-1">
												<div className="flex items-center gap-2">
													<CardTitle className="text-base">{user.name}</CardTitle>
													{!user.email_verified_at && (
														<Badge variant="outline" className="gap-1">
															<AlertCircle className="h-3 w-3" />
															Unverified
														</Badge>
													)}
													{user.is_active ? (
														<Badge variant="default" className="gap-1 bg-green-600 hover:bg-green-700">
															<CheckCircle2 className="h-3 w-3" />
															Active
														</Badge>
													) : (
														<Badge variant="secondary">Inactive</Badge>
													)}
												</div>
												<CardDescription className="flex items-center gap-2">
													<Mail className="h-4 w-4" />
													{user.email}
												</CardDescription>
											</div>
											<div className="flex gap-2">
											<DropdownMenu>
												<DropdownMenuTrigger asChild>
													<Button variant="outline" size="sm" className="gap-2">
														<MoreHorizontal className="h-4 w-4" />
														Actions
													</Button>
												</DropdownMenuTrigger>
												<DropdownMenuContent align="end">
													<DropdownMenuItem asChild>
														<Link href={`/system/users/${user.id}`} className="flex items-center gap-2">
															<Eye className="h-4 w-4" />
															View Details
														</Link>
													</DropdownMenuItem>
													<DropdownMenuSeparator />
													<DropdownMenuItem onClick={() => handleEditOpen(user)}>
														<Pencil className="h-4 w-4 mr-2" />
														Edit
													</DropdownMenuItem>
													<DropdownMenuItem onClick={() => handleResetPassword(user.id)}>
														<KeyRound className="h-4 w-4 mr-2" />
														Reset Password
													</DropdownMenuItem>
													<DropdownMenuSeparator />
													{user.is_active ? (
														<DropdownMenuItem
															className="text-red-600"
															onClick={() => setConfirmDeactivateUser(user)}
														>
															<UserX className="h-4 w-4 mr-2" />
															Deactivate
														</DropdownMenuItem>
													) : (
														<DropdownMenuItem onClick={() => handleActivate(user.id)}>
															<UserCheck className="h-4 w-4 mr-2" />
															Activate
														</DropdownMenuItem>
													)}
												</DropdownMenuContent>
											</DropdownMenu>
											</div>
										</div>
									</CardHeader>
									<Separator />
									<CardContent className="pt-4">
										<div className="grid gap-4 md:grid-cols-4">
											<div>
												<p className="text-sm text-muted-foreground">Roles</p>
												<div className="flex flex-wrap gap-1 mt-1">
													{user.roles.length === 0 ? (
														<span className="text-xs text-muted-foreground">No roles</span>
													) : (
														user.roles.map((r) => (
															<Badge key={r} variant="outline" className="text-xs">
																{r}
															</Badge>
														))
													)}
												</div>
											</div>
											<div>
												<p className="text-sm text-muted-foreground">Joined</p>
												<p className="text-sm font-medium">{user.created_at}</p>
											</div>
											<div>
												<p className="text-sm text-muted-foreground">Last Login</p>
												<p className="text-sm font-medium">
													{user.last_login_at ? user.last_login_at : 'Never'}
												</p>
											</div>
											<div>
												<p className="text-sm text-muted-foreground">Audit Events</p>
												<p className="text-sm font-medium">{user.audit_logs_count}</p>
											</div>
										</div>
									</CardContent>
								</Card>
							))}
						</div>
					)}

					{/* Pagination */}
					{users.last_page > 1 && (
						<div className="flex justify-center gap-2">
							{Array.from({ length: users.last_page }).map((_, i) => {
								const page = i + 1;
								const isActive = page === users.current_page;
								return (
									<Button
										key={page}
										variant={isActive ? 'default' : 'outline'}
										onClick={() => {
											const params = new URLSearchParams();
											params.append('page', page.toString());
											if (search) params.append('search', search);
											if (status !== 'all') params.append('status', status);
											if (role !== 'all') params.append('role', role);
											router.get('/system/users?' + params.toString());
										}}
									>
										{page}
									</Button>
								);
							})}
						</div>
					)}
				</div>
			</div>
			{/* Edit User Modal */}
			<Dialog open={editingUser !== null} onOpenChange={(open) => { if (!open) { setEditingUser(null); editForm.reset(); } }}>
				<DialogContent className="max-w-lg">
					<DialogHeader>
						<DialogTitle>Edit User</DialogTitle>
						<DialogDescription>
							Update {editingUser?.name ?? 'user'}'s account details and roles.
						</DialogDescription>
					</DialogHeader>
					<form onSubmit={handleEditSubmit} className="space-y-4">
						{(editForm.errors as Record<string, string>).error && (
							<div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
								{(editForm.errors as Record<string, string>).error}
							</div>
						)}
						<div className="space-y-2">
							<Label htmlFor="edit-name">Full Name</Label>
							<Input
								id="edit-name"
								value={editForm.data.name}
								onChange={(e) => editForm.setData('name', e.target.value)}
							/>
							{editForm.errors.name && <p className="text-sm text-red-600">{editForm.errors.name}</p>}
						</div>
						<div className="space-y-2">
							<Label htmlFor="edit-email">Email Address</Label>
							<Input
								id="edit-email"
								type="email"
								value={editForm.data.email}
								onChange={(e) => editForm.setData('email', e.target.value)}
							/>
							{editForm.errors.email && <p className="text-sm text-red-600">{editForm.errors.email}</p>}
						</div>
						<div className="space-y-2">
							<Label>Assign Roles</Label>
							<div className="rounded-md border p-3 space-y-2 max-h-40 overflow-y-auto">
								{roles.map((r) => (
									<div key={r.id} className="flex items-center gap-2">
										<Checkbox
											id={`edit-role-${r.id}`}
											checked={editForm.data.roles.includes(r.id)}
											onCheckedChange={() => toggleEditRole(r.id)}
										/>
										<label htmlFor={`edit-role-${r.id}`} className="text-sm cursor-pointer">
											{r.name}
										</label>
									</div>
								))}
							</div>
							{editForm.errors.roles && <p className="text-sm text-red-600">{editForm.errors.roles}</p>}
						</div>
						<DialogFooter>
							<Button type="button" variant="outline" onClick={() => { setEditingUser(null); editForm.reset(); }}>
								Cancel
							</Button>
							<Button type="submit" disabled={editForm.processing} className="gap-2">
								<Pencil className="h-4 w-4" />
								{editForm.processing ? 'Saving...' : 'Save Changes'}
							</Button>
						</DialogFooter>
					</form>
				</DialogContent>
			</Dialog>

			{/* Deactivate Confirmation Dialog */}
			<Dialog open={confirmDeactivateUser !== null} onOpenChange={(open) => { if (!open) { setConfirmDeactivateUser(null); setDeactivateReason(''); } }}>
				<DialogContent className="max-w-md">
					<DialogHeader>
						<DialogTitle>Deactivate User</DialogTitle>
						<DialogDescription>
							Deactivate <strong>{confirmDeactivateUser?.name}</strong>? They will no longer be able to log in.
						</DialogDescription>
					</DialogHeader>
					<div className="space-y-2">
						<Label htmlFor="deactivate-reason">Reason for deactivation</Label>
						<Input
							id="deactivate-reason"
							value={deactivateReason}
							onChange={(e) => setDeactivateReason(e.target.value)}
							placeholder="e.g. Resigned, contract ended..."
						/>
					</div>
					<DialogFooter>
						<Button
							variant="outline"
							onClick={() => { setConfirmDeactivateUser(null); setDeactivateReason(''); }}
						>
							Cancel
						</Button>
						<Button
							variant="destructive"
							onClick={handleConfirmDeactivate}
							disabled={!deactivateReason.trim()}
							className="gap-2"
						>
							<UserX className="h-4 w-4" />
							Deactivate
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
				{/* Create User Modal */}
				<Dialog open={showCreateModal} onOpenChange={(open) => { setShowCreateModal(open); if (!open) createForm.reset(); }}>
					<DialogContent className="max-w-lg">
						<DialogHeader>
							<DialogTitle>Create New User</DialogTitle>
							<DialogDescription>
								Create a new system account and assign roles.
							</DialogDescription>
						</DialogHeader>
						<form onSubmit={handleCreateSubmit} className="space-y-4">
							{/* Global error */}
							{(createForm.errors as Record<string, string>).error && (
								<div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
									{(createForm.errors as Record<string, string>).error}
								</div>
							)}

							<div className="space-y-2">
								<Label htmlFor="create-name">Full Name</Label>
								<Input
									id="create-name"
									value={createForm.data.name}
									onChange={(e) => createForm.setData('name', e.target.value)}
									placeholder="Juan dela Cruz"
								/>
								{createForm.errors.name && <p className="text-sm text-red-600">{createForm.errors.name}</p>}
							</div>

							<div className="space-y-2">
								<Label htmlFor="create-email">Email Address</Label>
								<Input
									id="create-email"
									type="email"
									value={createForm.data.email}
									onChange={(e) => createForm.setData('email', e.target.value)}
									placeholder="user@example.com"
								/>
								{createForm.errors.email && <p className="text-sm text-red-600">{createForm.errors.email}</p>}
							</div>

							<div className="grid grid-cols-2 gap-3">
								<div className="space-y-2">
									<Label htmlFor="create-password">Password</Label>
									<Input
										id="create-password"
										type="password"
										value={createForm.data.password}
										onChange={(e) => createForm.setData('password', e.target.value)}
										placeholder="Min. 8 characters"
									/>
									{createForm.errors.password && <p className="text-sm text-red-600">{createForm.errors.password}</p>}
								</div>
								<div className="space-y-2">
									<Label htmlFor="create-password-confirm">Confirm Password</Label>
									<Input
										id="create-password-confirm"
										type="password"
										value={createForm.data.password_confirmation}
										onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
										placeholder="Repeat password"
									/>
								</div>
							</div>

							<div className="space-y-2">
								<Label>Assign Roles</Label>
								<div className="rounded-md border p-3 space-y-2 max-h-40 overflow-y-auto">
									{roles.length === 0 ? (
										<p className="text-sm text-muted-foreground">No roles available</p>
									) : (
										roles.map((r) => (
											<div key={r.id} className="flex items-center gap-2">
												<Checkbox
													id={`create-role-${r.id}`}
													checked={createForm.data.roles.includes(r.id)}
													onCheckedChange={() => toggleCreateRole(r.id)}
												/>
												<label htmlFor={`create-role-${r.id}`} className="text-sm cursor-pointer">
													{r.name}
												</label>
											</div>
										))
									)}
								</div>
								{createForm.errors.roles && <p className="text-sm text-red-600">{createForm.errors.roles}</p>}
							</div>

							<DialogFooter>
								<Button type="button" variant="outline" onClick={() => { setShowCreateModal(false); createForm.reset(); }}>
									Cancel
								</Button>
								<Button type="submit" disabled={createForm.processing} className="gap-2">
									<UserPlus className="h-4 w-4" />
									{createForm.processing ? 'Creating...' : 'Create User'}
								</Button>
							</DialogFooter>
						</form>
					</DialogContent>
				</Dialog>
		</AppLayout>
	);
}