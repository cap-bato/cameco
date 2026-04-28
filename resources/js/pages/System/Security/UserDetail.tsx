import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
	ArrowLeft,
	CheckCircle2,
	XCircle,
	Mail,
	Calendar,
	Clock,
	Shield,
	ShieldAlert,
	ShieldCheck,
	KeyRound,
	UserX,
	UserCheck,
	AlertTriangle,
	Info,
	AlertCircle,
} from 'lucide-react';

interface AuditLog {
	id: number;
	user_id: number;
	event_type: string;
	description: string | null;
	severity: 'info' | 'warning' | 'critical';
	created_at: string;
}

interface LoginRecord {
	id: number;
	user_id: number;
	event_type: string;
	description: string | null;
	severity: string;
	created_at: string;
}

interface UserDetailProps {
	user: {
		id: number;
		name: string;
		email: string;
		is_active: boolean;
		email_verified_at: string | null;
		created_at: string;
		last_login_at: string | null;
		roles: string[];
		two_factor_confirmed: boolean;
	};
	auditLogs: AuditLog[];
	loginHistory: LoginRecord[];
}

function SeverityIcon({ severity }: { severity: string }) {
	switch (severity) {
		case 'critical':
			return <ShieldAlert className="h-4 w-4 text-red-500" />;
		case 'warning':
			return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
		default:
			return <Info className="h-4 w-4 text-blue-500" />;
	}
}

function SeverityBadge({ severity }: { severity: string }) {
	const variants: Record<string, string> = {
		critical: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
		warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200',
		info: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
	};
	return (
		<span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${variants[severity] ?? variants.info}`}>
			{severity}
		</span>
	);
}

export default function UserDetailPage({ user, auditLogs, loginHistory }: UserDetailProps) {
	const breadcrumbs = [
		{ title: 'System', href: '#' },
		{ title: 'Security & Access', href: '#' },
		{ title: 'User Management', href: '/system/users' },
		{ title: user.name, href: '#' },
	];

	function handleResetPassword() {
		if (!confirm('Send password reset email to this user?')) return;
		router.post(`/system/users/${user.id}/password-reset`);
	}

	function handleToggleActive() {
		const action = user.is_active ? 'deactivate' : 'activate';
		const label = user.is_active ? 'deactivate' : 'activate';
		if (!confirm(`Are you sure you want to ${label} this user?`)) return;
		router.post(`/system/users/${user.id}/${action}`);
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title={`User: ${user.name}`} />

			<div className="flex flex-col gap-6 p-4 md:p-6">
				{/* Header */}
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<div className="flex items-center gap-3">
						<Link href="/system/users">
							<Button variant="outline" size="sm" className="gap-2">
								<ArrowLeft className="h-4 w-4" />
								Back
							</Button>
						</Link>
						<div>
							<h1 className="text-2xl font-bold">{user.name}</h1>
							<p className="text-sm text-muted-foreground">{user.email}</p>
						</div>
					</div>
					<div className="flex gap-2">
						<Button variant="outline" size="sm" className="gap-2" onClick={handleResetPassword}>
							<KeyRound className="h-4 w-4" />
							Reset Password
						</Button>
						<Button
							variant={user.is_active ? 'destructive' : 'default'}
							size="sm"
							className="gap-2"
							onClick={handleToggleActive}
						>
							{user.is_active ? (
								<>
									<UserX className="h-4 w-4" />
									Deactivate
								</>
							) : (
								<>
									<UserCheck className="h-4 w-4" />
									Activate
								</>
							)}
						</Button>
					</div>
				</div>

				<div className="grid gap-6 lg:grid-cols-3">
					{/* Left column — profile card */}
					<div className="flex flex-col gap-4">
						<Card>
							<CardHeader>
								<CardTitle className="text-base">Account Details</CardTitle>
							</CardHeader>
							<CardContent className="space-y-4">
								{/* Status */}
								<div className="flex items-center justify-between">
									<span className="text-sm text-muted-foreground">Status</span>
									{user.is_active ? (
										<Badge className="gap-1 bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200">
											<CheckCircle2 className="h-3 w-3" />
											Active
										</Badge>
									) : (
										<Badge variant="secondary" className="gap-1">
											<XCircle className="h-3 w-3" />
											Inactive
										</Badge>
									)}
								</div>

								<Separator />

								{/* Email */}
								<div className="flex flex-col gap-1">
									<span className="text-xs text-muted-foreground">Email</span>
									<span className="flex items-center gap-2 text-sm font-medium">
										<Mail className="h-3.5 w-3.5 text-muted-foreground" />
										{user.email}
									</span>
									{user.email_verified_at ? (
										<span className="text-xs text-green-600">
											Verified {user.email_verified_at}
										</span>
									) : (
										<span className="text-xs text-yellow-600">Not verified</span>
									)}
								</div>

								<Separator />

								{/* Roles */}
								<div className="flex flex-col gap-2">
									<span className="text-xs text-muted-foreground">Roles</span>
									{user.roles.length === 0 ? (
										<span className="text-sm text-muted-foreground">No roles assigned</span>
									) : (
										<div className="flex flex-wrap gap-1">
											{user.roles.map((r) => (
												<Badge key={r} variant="outline" className="text-xs">
													<Shield className="mr-1 h-3 w-3" />
													{r}
												</Badge>
											))}
										</div>
									)}
								</div>

								<Separator />

								{/* 2FA */}
								<div className="flex items-center justify-between">
									<span className="text-sm text-muted-foreground">Two-Factor Auth</span>
									{user.two_factor_confirmed ? (
										<div className="flex items-center gap-1 text-xs text-green-600">
											<ShieldCheck className="h-3.5 w-3.5" />
											Enabled
										</div>
									) : (
										<div className="flex items-center gap-1 text-xs text-muted-foreground">
											<AlertCircle className="h-3.5 w-3.5" />
											Not set up
										</div>
									)}
								</div>

								<Separator />

								{/* Timestamps */}
								<div className="flex flex-col gap-2">
									<div className="flex flex-col gap-0.5">
										<span className="text-xs text-muted-foreground">Account Created</span>
										<span className="flex items-center gap-1.5 text-sm">
											<Calendar className="h-3.5 w-3.5 text-muted-foreground" />
											{user.created_at}
										</span>
									</div>
									<div className="flex flex-col gap-0.5">
										<span className="text-xs text-muted-foreground">Last Login</span>
										<span className="flex items-center gap-1.5 text-sm">
											<Clock className="h-3.5 w-3.5 text-muted-foreground" />
											{user.last_login_at ?? 'Never'}
										</span>
									</div>
								</div>
							</CardContent>
						</Card>
					</div>

					{/* Right columns — audit log + login history */}
					<div className="flex flex-col gap-6 lg:col-span-2">
						{/* Audit Log */}
						<Card>
							<CardHeader>
								<CardTitle className="text-base">Recent Audit Events</CardTitle>
							</CardHeader>
							<CardContent>
								{auditLogs.length === 0 ? (
									<p className="py-4 text-center text-sm text-muted-foreground">No audit events recorded.</p>
								) : (
									<div className="divide-y">
										{auditLogs.map((log) => (
											<div key={log.id} className="flex items-start gap-3 py-3">
												<div className="mt-0.5 shrink-0">
													<SeverityIcon severity={log.severity} />
												</div>
												<div className="min-w-0 flex-1">
													<div className="flex flex-wrap items-center gap-2">
														<span className="text-sm font-medium">{log.event_type}</span>
														<SeverityBadge severity={log.severity} />
													</div>
													{log.description && (
														<p className="mt-0.5 text-xs text-muted-foreground">{log.description}</p>
													)}
												</div>
												<span className="shrink-0 text-xs text-muted-foreground">{log.created_at}</span>
											</div>
										))}
									</div>
								)}
							</CardContent>
						</Card>

						{/* Login History */}
						<Card>
							<CardHeader>
								<CardTitle className="text-base">Login History</CardTitle>
							</CardHeader>
							<CardContent>
								{loginHistory.length === 0 ? (
									<p className="py-4 text-center text-sm text-muted-foreground">No login records found.</p>
								) : (
									<div className="divide-y">
										{loginHistory.map((record) => (
											<div key={record.id} className="flex items-start gap-3 py-3">
												<div className="mt-0.5 shrink-0">
													<SeverityIcon severity={record.severity} />
												</div>
												<div className="min-w-0 flex-1">
													<span className="text-sm font-medium">{record.event_type}</span>
													{record.description && (
														<p className="mt-0.5 text-xs text-muted-foreground">{record.description}</p>
													)}
												</div>
												<span className="shrink-0 text-xs text-muted-foreground">{record.created_at}</span>
											</div>
										))}
									</div>
								)}
							</CardContent>
						</Card>
					</div>
				</div>
			</div>
		</AppLayout>
	);
}
