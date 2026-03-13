import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { SystemHealthWidgets, CronJobsCard } from '@/components/system/system-health-widgets';
import { ModuleGrid } from '@/components/module-grid';
import { ModuleCategory } from '@/types/modules';

interface SystemOnboarding {
    id: number;
    status: string;
    checklist_json?: string | Record<string, unknown>;
}

interface SystemHealthData {
    server: {
        cpu_usage: number;
        memory_usage: number;
        load_average: string | null;
        uptime: number;
        uptime_formatted: string;
        status: 'healthy' | 'warning' | 'critical';
    };
    database: {
        status: string;
        response_time_ms: number;
        connection_status: string;
    };
    cache: {
        driver: string;
        status: string;
    };
    queue: {
        pending_jobs: number;
        failed_jobs: number;
        status: string;
    };
    storage: {
        total_bytes: number;
        used_bytes: number;
        free_bytes: number;
        usage_percentage: number;
        total_formatted: string;
        used_formatted: string;
        free_formatted: string;
        status: 'healthy' | 'warning' | 'critical';
    };
    backup: {
        latest_backup: {
            backup_type: string;
            status: string;
            created_at: string;
            completed_at?: string;
            size_bytes?: number;
        } | null;
        success_rate: number;
        status: 'healthy' | 'warning' | 'critical';
    };
    patches: {
        pending_total: number;
        security_pending: number;
        status: string;
    };
    security: {
        critical_events_24h: number;
        failed_logins_24h: number;
        recent_events: Array<{
            id: number;
            event_type: string;
            severity: string;
            created_at: string;
        }>;
        status: 'healthy' | 'warning' | 'critical';
    };
    overall_status: 'healthy' | 'warning' | 'critical';
}

interface CronMetrics {
    total_jobs: number;
    enabled_jobs: number;
    disabled_jobs: number;
    overdue_jobs: number;
    recent_failures_24h: number;
    overall_success_rate: number;
    next_job: {
        name: string;
        next_run_at: string;
        formatted_next_run: string;
    } | null;
    status: 'healthy' | 'warning' | 'critical' | 'unavailable';
}

interface SystemDashboardProps {
    counts: {
        users: number;
    };
    company: {
        name: string | null;
    };
    systemOnboarding: SystemOnboarding | null;
    onboardingStatus: string;
    canCompleteOnboarding: boolean;
    welcomeText: string;
    systemHealth: SystemHealthData | null;
    cronMetrics: CronMetrics | null;
    moduleCategories?: Array<{
        id: string;
        title: string;
        description?: string;
        modules: Array<{
            id: string;
            icon: string;
            title: string;
            description: string;
            href: string;
            badge?: {
                count: number;
                label: string;
                variant?: string;
            };
            isDisabled?: boolean;
            comingSoon?: boolean;
        }>;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({
    company,
    welcomeText,
    systemHealth,
    cronMetrics,
    moduleCategories = [],
}: SystemDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">
                        {company.name ? `${company.name} Dashboard` : 'System Dashboard'}
                    </h1>
                    <p className="text-muted-foreground">{welcomeText}</p>
                </div>

                {/* System Health Monitoring - Premium positioning */}
                {systemHealth && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">System Status</h2>
                        <SystemHealthWidgets health={systemHealth} />
                    </div>
                )}

                {/* Cron Jobs - Direct access since it's a quick reference */}
                {cronMetrics && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Scheduled Tasks</h2>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <CronJobsCard cronMetrics={cronMetrics} />
                        </div>
                    </div>
                )}

                {/* Module Quick Access Grid - Primary navigation */}
                {moduleCategories && moduleCategories.length > 0 && (
                    <div className="space-y-4 pt-4">
                        <div className="space-y-2">
                            <h2 className="text-2xl font-bold tracking-tight">Module Navigation</h2>
                            <p className="text-sm text-muted-foreground">
                                Quick access to all superadmin modules, organized by function
                            </p>
                        </div>
                        <ModuleGrid categories={moduleCategories as unknown as ModuleCategory[]} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

