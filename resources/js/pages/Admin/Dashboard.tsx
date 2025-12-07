import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { ModuleCard } from '@/components/module-card';
import { SetupChecklist } from '@/components/admin/setup-checklist';
import { 
    Building2, 
    Briefcase, 
    Calendar, 
    Users, 
    FileText, 
    Settings, 
    GitBranch,
    Activity,
    CheckCircle2,
    AlertCircle,
    DollarSign
} from 'lucide-react';

interface SetupStep {
    id: string;
    title: string;
    description: string;
    route: string;
    completed: boolean;
    priority: 'high' | 'medium' | 'low';
}

interface SetupStatus {
    steps: SetupStep[];
    completedCount: number;
    totalSteps: number;
    completionPercentage: number;
    isComplete: boolean;
}

interface QuickStat {
    count: number;
    label: string;
    icon: string;
    route: string | null;
}

interface QuickStats {
    departments: QuickStat;
    positions: QuickStat;
    leavePolicies: QuickStat;
    configurationChanges: QuickStat;
}

interface RecentChange {
    id: number;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_name: string;
    causer_email: string | null;
    properties: Record<string, unknown>;
    created_at: string;
    relative_time: string;
}

interface AdminDashboardProps {
    setupStatus: SetupStatus;
    quickStats: QuickStats;
    recentChanges: RecentChange[];
    userRole: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ 
    setupStatus, 
    quickStats, 
    recentChanges,
    userRole 
}: AdminDashboardProps) {
    const isSuperadmin = userRole === 'Superadmin';
    
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Office Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h1 className="text-3xl font-bold tracking-tight">
                            {isSuperadmin ? 'System Admin Dashboard' : 'Office Admin Dashboard'}
                        </h1>
                        <Badge variant={setupStatus.isComplete ? 'default' : 'secondary'} className="text-sm">
                            {setupStatus.isComplete ? (
                                <><CheckCircle2 className="mr-1 h-4 w-4" /> Setup Complete</>
                            ) : (
                                <><AlertCircle className="mr-1 h-4 w-4" /> Setup In Progress</>
                            )}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground">
                        {isSuperadmin 
                            ? 'System-wide configuration and company setup for Cathay Metal Corporation'
                            : 'Company setup, business rules, and system configuration management'
                        }
                    </p>
                </div>

                {/* Setup Progress Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span>Setup Progress</span>
                            <span className="text-2xl font-bold">{setupStatus.completionPercentage}%</span>
                        </CardTitle>
                        <CardDescription>
                            {setupStatus.completedCount} of {setupStatus.totalSteps} configuration areas completed
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Progress value={setupStatus.completionPercentage} className="h-3" />
                    </CardContent>
                </Card>

                {/* Setup Checklist - Detailed */}
                <SetupChecklist setupStatus={setupStatus} />

                {/* Module Cards Grid */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Configuration Modules</h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <ModuleCard
                            icon={Building2}
                            title="Company Setup"
                            description="Configure company information, tax details, and government registration numbers"
                            href="/admin/company"
                        />
                        <ModuleCard
                            icon={FileText}
                            title="Business Rules"
                            description="Define working hours, holidays, overtime rules, and attendance policies"
                            href="/admin/business-rules"
                        />
                        <ModuleCard
                            icon={Users}
                            title="Departments"
                            description="Manage organizational structure and department hierarchy"
                            href="/admin/departments"
                            badge={{
                                count: quickStats.departments.count,
                                label: 'active',
                                variant: 'secondary'
                            }}
                        />
                        <ModuleCard
                            icon={Briefcase}
                            title="Positions"
                            description="Configure job positions, salary ranges, and reporting structure"
                            href="/admin/positions"
                            badge={{
                                count: quickStats.positions.count,
                                label: 'active',
                                variant: 'secondary'
                            }}
                        />
                        <ModuleCard
                            icon={Calendar}
                            title="Leave Policies"
                            description="Configure leave types, accrual methods, and approval workflows"
                            href="/admin/leave-policies"
                            badge={{
                                count: quickStats.leavePolicies.count,
                                label: 'types',
                                variant: 'secondary'
                            }}
                        />
                        <ModuleCard
                            icon={DollarSign}
                            title="Payroll Rules"
                            description="Set up salary structures, deductions, and government contribution rates"
                            href="/admin/payroll-rules"
                        />
                        <ModuleCard
                            icon={Settings}
                            title="System Configuration"
                            description="Configure payment methods, notifications, and system integrations"
                            href="/admin/system-config"
                        />
                        <ModuleCard
                            icon={GitBranch}
                            title="Approval Workflows"
                            description="Set up approval chains for leave requests, overtime, and expenses"
                            href="/admin/approval-workflows"
                        />
                    </div>
                </div>

                {/* Quick Statistics */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {quickStats.departments.label}
                            </CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{quickStats.departments.count}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {quickStats.positions.label}
                            </CardTitle>
                            <Briefcase className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{quickStats.positions.count}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {quickStats.leavePolicies.label}
                            </CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{quickStats.leavePolicies.count}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                {quickStats.configurationChanges.label}
                            </CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{quickStats.configurationChanges.count}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Configuration Changes */}
                {recentChanges.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Configuration Changes</CardTitle>
                            <CardDescription>
                                Latest system configuration updates from the past 30 days
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {recentChanges.slice(0, 5).map((change) => (
                                    <div key={change.id} className="flex items-start gap-3 border-b pb-3 last:border-0">
                                        <Activity className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <div className="flex-1 space-y-1">
                                            <p className="text-sm font-medium">{change.description}</p>
                                            <p className="text-xs text-muted-foreground">
                                                By {change.causer_name} • {change.relative_time}
                                            </p>
                                        </div>
                                        <Badge variant="outline" className="text-xs">
                                            {change.subject_type}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Info Card - Getting Started */}
                <Card>
                    <CardHeader>
                        <CardTitle>Getting Started</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p>
                            Welcome to the Office Admin Dashboard. This is your central hub for managing all system-wide configurations.
                        </p>
                        <ul className="list-disc list-inside space-y-2 text-muted-foreground">
                            <li>Complete the <strong>Setup Checklist</strong> to configure all essential company information</li>
                            <li>Use <strong>Company Setup</strong> to add company details, tax information, and government registration</li>
                            <li>Configure <strong>Business Rules</strong> to define working hours, holidays, and attendance policies</li>
                            <li>Set up <strong>Departments & Positions</strong> to establish your organizational structure</li>
                            <li>Create <strong>Leave Policies</strong> and configure approval workflows for employee requests</li>
                            <li>Configure <strong>Payroll Rules</strong> including government contribution rates and deductions</li>
                        </ul>
                        {!setupStatus.isComplete && (
                            <p className="text-xs text-muted-foreground pt-2">
                                <strong>Note:</strong> Complete all setup steps to ensure the system is fully configured for your organization.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
