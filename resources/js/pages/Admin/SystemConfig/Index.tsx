import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, Bell, FileText, Radio, ScrollText, AlertCircle } from 'lucide-react';
import { NotificationSettingsForm, type NotificationSettingsData } from '@/components/admin/notification-settings-form';
import { ReportSettingsForm, type ReportSettingsData } from '@/components/admin/report-settings-form';
import { RFIDSettingsForm, type RFIDSettingsData } from '@/components/admin/rfid-settings-form';
import { AuditLogsTable } from '@/components/admin/audit-logs-table';
import { useState } from 'react';
import { useToast } from '@/hooks/use-toast';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface SystemConfigData {
    notifications: {
        // SMTP Email settings
        smtp_host: string;
        smtp_port: number;
        smtp_username: string;
        smtp_encryption: 'tls' | 'ssl' | 'none';
        sender_email: string;
        sender_name: string;
        // SMS settings (future)
        sms_enabled: boolean;
        sms_gateway: string;
        sms_api_key: string;
        // Notification templates
        leave_approval_enabled: boolean;
        payslip_enabled: boolean;
        interview_enabled: boolean;
        appraisal_enabled: boolean;
        system_alerts_enabled: boolean;
    };
    reports: {
        // PDF settings
        pdf_company_logo: boolean;
        pdf_page_size: string;
        pdf_orientation: string;
        pdf_font_family: string;
        // Excel settings
        excel_auto_width: boolean;
        excel_freeze_panes: boolean;
        excel_summary_sheet: boolean;
        // Scheduled reports
        monthly_payroll_enabled: boolean;
        monthly_payroll_recipients: string[];
        attendance_summary_enabled: boolean;
        attendance_summary_recipients: string[];
        leave_utilization_enabled: boolean;
        leave_utilization_recipients: string[];
        government_remittance_enabled: boolean;
        government_remittance_recipients: string[];
    };
    integrations: {
        // RFID
        rfid_enabled: boolean;
        rfid_device_ip: string;
        rfid_device_port: number;
        rfid_protocol: string;
        rfid_event_bus_enabled: boolean;
        // Job board (future)
        job_board_enabled: boolean;
        job_board_url: string;
        job_board_auto_import: boolean;
    };
}

interface AuditLog {
    id: number;
    timestamp: string;
    relative_time: string;
    user_name: string;
    user_email: string;
    action: string;
    log_name: string;
    module: string;
    subject_type: string;
    subject_id: number | null;
    old_values: Record<string, any>;
    new_values: Record<string, any>;
    changes_summary: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface AuditLogsPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    data: AuditLog[];
}

interface SystemConfigIndexProps {
    systemConfig: SystemConfigData;
    auditLogs?: AuditLogsPagination;
    availableUsers?: User[];
    filters?: {
        date_from?: string;
        date_to?: string;
        user_id?: number;
        module?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'System Configuration',
        href: '/admin/system-config',
    },
];

export default function SystemConfigIndex({ systemConfig, auditLogs, availableUsers, filters }: SystemConfigIndexProps) {
    const { toast } = useToast();
    const [activeTab, setActiveTab] = useState('notifications');

    const handleNotificationsSubmit = (data: NotificationSettingsData) => {
        router.put('/admin/system-config/notifications', data as unknown as Record<string, string | number | boolean>, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Notification settings updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update notification settings. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Notification settings update errors:', errors);
            },
        });
    };

    const handleReportsSubmit = (data: ReportSettingsData) => {
        router.put('/admin/system-config/reports', data, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Report settings updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update report settings. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Report settings update errors:', errors);
            },
        });
    };

    const handleIntegrationsSubmit = (data: RFIDSettingsData) => {
        router.put('/admin/system-config/integrations', data, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'RFID integration settings updated successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update integration settings. Please check your inputs.',
                    variant: 'destructive',
                });
                console.error('Integration settings update errors:', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Configuration" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit('/admin/dashboard')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Dashboard
                        </Button>
                    </div>
                    <h1 className="text-3xl font-bold tracking-tight">System Configuration</h1>
                    <p className="text-muted-foreground">
                        Configure system-wide settings including notifications, reports, and integrations
                    </p>
                </div>

                {/* Info Alert */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <strong>Important:</strong> System configuration changes affect all users. Notification settings control email and SMS alerts. Report settings determine scheduled report generation. Integration settings manage external system connections like RFID timekeeping devices.
                    </AlertDescription>
                </Alert>

                {/* Overview Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>System Settings Overview</CardTitle>
                        <CardDescription>Manage notifications, reports, and system integrations</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-blue-100 dark:bg-blue-950 p-2">
                                    <Bell className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Email Notifications</p>
                                    <p className="text-2xl font-bold">
                                        {systemConfig.notifications.sender_email || 'Not configured'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-green-100 dark:bg-green-950 p-2">
                                    <FileText className="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Report Format</p>
                                    <p className="text-2xl font-bold">
                                        {systemConfig.reports.pdf_page_size} PDF
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-orange-100 dark:bg-orange-950 p-2">
                                    <Radio className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">RFID Integration</p>
                                    <p className="text-2xl font-bold">
                                        {systemConfig.integrations.rfid_enabled ? 'Enabled' : 'Disabled'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-purple-100 dark:bg-purple-950 p-2">
                                    <ScrollText className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Scheduled Reports</p>
                                    <p className="text-2xl font-bold">
                                        {[
                                            systemConfig.reports.monthly_payroll_enabled,
                                            systemConfig.reports.attendance_summary_enabled,
                                            systemConfig.reports.leave_utilization_enabled,
                                            systemConfig.reports.government_remittance_enabled
                                        ].filter(Boolean).length} Active
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabbed Interface */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="notifications" className="flex items-center gap-2">
                            <Bell className="h-4 w-4" />
                            Notifications
                        </TabsTrigger>
                        <TabsTrigger value="reports" className="flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            Reports
                        </TabsTrigger>
                        <TabsTrigger value="integrations" className="flex items-center gap-2">
                            <Radio className="h-4 w-4" />
                            RFID Integration
                        </TabsTrigger>
                        <TabsTrigger value="audit" className="flex items-center gap-2">
                            <ScrollText className="h-4 w-4" />
                            Audit Logs
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Notifications */}
                    <TabsContent value="notifications">
                        <NotificationSettingsForm
                            initialData={{
                                smtp_host: systemConfig.notifications.smtp_host,
                                smtp_port: systemConfig.notifications.smtp_port,
                                smtp_username: systemConfig.notifications.smtp_username,
                                smtp_password: '',
                                smtp_encryption: systemConfig.notifications.smtp_encryption,
                                sender_email: systemConfig.notifications.sender_email,
                                sender_name: systemConfig.notifications.sender_name,
                                sms_enabled: systemConfig.notifications.sms_enabled,
                                sms_gateway: systemConfig.notifications.sms_gateway,
                                sms_api_key: systemConfig.notifications.sms_api_key,
                                leave_approval_enabled: systemConfig.notifications.leave_approval_enabled,
                                payslip_enabled: systemConfig.notifications.payslip_enabled,
                                interview_enabled: systemConfig.notifications.interview_enabled,
                                appraisal_enabled: systemConfig.notifications.appraisal_enabled,
                                system_alerts_enabled: systemConfig.notifications.system_alerts_enabled,
                            }}
                            onSubmit={handleNotificationsSubmit}
                        />
                    </TabsContent>

                    {/* Tab 2: Reports */}
                    <TabsContent value="reports">
                        <ReportSettingsForm
                            initialData={{
                                pdf_company_logo: systemConfig.reports.pdf_company_logo,
                                pdf_page_size: systemConfig.reports.pdf_page_size,
                                pdf_orientation: systemConfig.reports.pdf_orientation,
                                pdf_font_family: systemConfig.reports.pdf_font_family,
                                excel_auto_width: systemConfig.reports.excel_auto_width,
                                excel_freeze_panes: systemConfig.reports.excel_freeze_panes,
                                excel_summary_sheet: systemConfig.reports.excel_summary_sheet,
                                monthly_payroll_enabled: systemConfig.reports.monthly_payroll_enabled,
                                monthly_payroll_recipients: systemConfig.reports.monthly_payroll_recipients || [],
                                attendance_summary_enabled: systemConfig.reports.attendance_summary_enabled,
                                attendance_summary_recipients: systemConfig.reports.attendance_summary_recipients || [],
                                leave_utilization_enabled: systemConfig.reports.leave_utilization_enabled,
                                leave_utilization_recipients: systemConfig.reports.leave_utilization_recipients || [],
                                government_remittance_enabled: systemConfig.reports.government_remittance_enabled,
                                government_remittance_recipients: systemConfig.reports.government_remittance_recipients || [],
                            }}
                            onSubmit={handleReportsSubmit}
                        />
                    </TabsContent>

                    {/* Tab 3: RFID Integration */}
                    <TabsContent value="integrations">
                        <RFIDSettingsForm
                            initialData={{
                                rfid_enabled: systemConfig.integrations.rfid_enabled,
                                rfid_device_ip: systemConfig.integrations.rfid_device_ip || '',
                                rfid_device_port: systemConfig.integrations.rfid_device_port || 8080,
                                rfid_protocol: systemConfig.integrations.rfid_protocol || 'http',
                                rfid_event_bus_enabled: systemConfig.integrations.rfid_event_bus_enabled,
                            }}
                            onSubmit={handleIntegrationsSubmit}
                        />
                    </TabsContent>

                    {/* Tab 4: Audit Logs */}
                    <TabsContent value="audit">
                        {auditLogs && availableUsers ? (
                            <AuditLogsTable
                                logs={auditLogs}
                                availableUsers={availableUsers}
                                filters={filters || {}}
                            />
                        ) : (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Configuration Audit Logs</CardTitle>
                                    <CardDescription>
                                        View all configuration changes made by Office Admin users
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Alert>
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>
                                            Loading audit logs...
                                        </AlertDescription>
                                    </Alert>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
