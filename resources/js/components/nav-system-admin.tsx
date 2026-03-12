import { ChevronRight } from 'lucide-react';
import {
    SidebarGroup,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';
import { Activity, HardDrive, Database, Shield, Calendar, Lock, Users, Building2, BarChart3, Briefcase, AlertCircle, Cpu } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

export function NavSystemAdmin() {
    const page = usePage();
    
    const systemAdminItems = [
        {
            title: 'System Health',
            icon: Activity,
            href: '/system/health',
        },
        {
            title: 'Storage',
            icon: HardDrive,
            href: '/system/storage',
        },
        {
            title: 'Backups',
            icon: Database,
            href: '/system/backups',
        },
        {
            title: 'Security Audit',
            icon: Shield,
            href: '/system/security/audit',
        },
        {
            title: 'Error Logs',
            icon: AlertCircle,
            href: '/system/logs/errors',
        },
        {
            title: 'Cron Jobs',
            icon: Calendar,
            href: '/system/cron',
        },
        {
            title: 'Timekeeping Devices',
            icon: Cpu,
            href: '/system/timekeeping/devices',
        },
    ];

    const securityAccessItems = [
        {
            title: 'User Management',
            icon: Users,
            href: '/system/users',
        },
        {
            title: 'Roles & Permissions',
            icon: Lock,
            href: '/system/security/roles',
        },
    ];

    const organizationItems = [
        {
            title: 'Overview',
            icon: Building2,
            href: '/system/organization/overview',
        },
        {
            title: 'Departments',
            icon: Building2,
            href: '/system/organization/departments',
        },
        {
            title: 'Positions & Hierarchy',
            icon: Briefcase,
            href: '/system/organization/positions',
        },
    ];

    const reportingItems = [
        {
            title: 'Usage Analytics',
            icon: BarChart3,
            href: '/system/reports/usage',
        },
        {
            title: 'Security Reports',
            icon: Shield,
            href: '/system/reports/security',
        },
    ];

    const isSystemAdminActive = page.url.startsWith('/system/') && !page.url.startsWith('/system/users') && !page.url.startsWith('/system/security/roles') && !page.url.startsWith('/system/organization/') && !page.url.startsWith('/system/reports/');
    const isSecurityAccessActive = page.url.startsWith('/system/users') || page.url.startsWith('/system/security/roles');
    const isOrganizationActive = page.url.startsWith('/system/organization/');
    const isReportingActive = page.url.startsWith('/system/reports/');

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isSystemAdminActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="System Administration">
                                <Activity />
                                <span>System Administration</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {systemAdminItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={page.url.startsWith(item.href)}
                                        >
                                            <Link href={item.href} prefetch>
                                                <item.icon className="h-4 w-4" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isSecurityAccessActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Security & Access">
                                <Shield />
                                <span>Security & Access</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {securityAccessItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={page.url.startsWith(item.href)}
                                        >
                                            <Link href={item.href} prefetch>
                                                <item.icon className="h-4 w-4" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isOrganizationActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Organization Control">
                                <Building2 />
                                <span>Organization</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {organizationItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={page.url.startsWith(item.href)}
                                        >
                                            <Link href={item.href} prefetch>
                                                <item.icon className="h-4 w-4" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isReportingActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Monitoring & Reporting">
                                <BarChart3 />
                                <span>Reports</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {reportingItems.map((item) => (
                                    <SidebarMenuSubItem key={item.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={page.url.startsWith(item.href)}
                                        >
                                            <Link href={item.href} prefetch>
                                                <item.icon className="h-4 w-4" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>
        </>
    );
}
