import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Building2,
    Calendar,
    ClipboardList,
    Cog,
    DollarSign,
    FileText,
    GitBranch,
    Settings,
    Users,
    Briefcase,
} from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenuItem,
    SidebarMenuButton,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { usePermission } from '@/components/permission-gate';

export function NavAdmin() {
    const page = usePage();
    const { hasPermission } = usePermission();

    // Configuration Items
    const configurationItemsAll = [
        {
            title: 'Company Setup',
            icon: Building2,
            href: '/admin/company',
            permission: 'admin.company.view',
        },
        {
            title: 'Business Rules',
            icon: ClipboardList,
            href: '/admin/business-rules',
            permission: 'admin.business-rules.view',
        },
    ];
    const configurationItems = configurationItemsAll.filter(item => hasPermission(item.permission));

    // Organizational Structure Items
    const organizationItemsAll = [
        {
            title: 'Departments',
            icon: Users,
            href: '/admin/departments',
            permission: 'admin.departments.view',
        },
        {
            title: 'Positions',
            icon: Briefcase,
            href: '/admin/positions',
            permission: 'admin.positions.view',
        },
    ];
    const organizationItems = organizationItemsAll.filter(item => hasPermission(item.permission));

    // HR Policies Items
    const policiesItemsAll = [
        {
            title: 'Leave Policies',
            icon: Calendar,
            href: '/admin/leave-policies',
            permission: 'admin.leave-policies.view',
        },
        {
            title: 'Payroll Rules',
            icon: DollarSign,
            href: '/admin/payroll-rules',
            permission: 'admin.payroll-rules.view',
        },
    ];
    const policiesItems = policiesItemsAll.filter(item => hasPermission(item.permission));

    // System Management Items
    const systemItemsAll = [
        {
            title: 'System Configuration',
            icon: Settings,
            href: '/admin/system-config',
            permission: 'admin.system-config.view',
        },
        {
            title: 'Approval Workflows',
            icon: GitBranch,
            href: '/admin/approval-workflows',
            permission: 'admin.approval-workflows.view',
        },
    ];
    const systemItems = systemItemsAll.filter(item => hasPermission(item.permission));

    const isConfigurationActive = page.url.startsWith('/admin/company') || page.url.startsWith('/admin/business-rules');
    const isOrganizationActive = page.url.startsWith('/admin/departments') || page.url.startsWith('/admin/positions');
    const isPoliciesActive = page.url.startsWith('/admin/leave-policies') || page.url.startsWith('/admin/payroll-rules');
    const isSystemActive = page.url.startsWith('/admin/system-config') || page.url.startsWith('/admin/approval-workflows');

    return (
        <>

            {/* Configuration Section */}
            {configurationItems.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <Collapsible defaultOpen={isConfigurationActive} className="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger asChild>
                                <SidebarMenuButton tooltip="Configuration">
                                    <Cog />
                                    <span>Configuration</span>
                                    <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                                <SidebarMenuSub className="space-y-1">
                                    {configurationItems.map((item) => (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={page.url === item.href || page.url.startsWith(item.href + '/')}
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
            )}

            {/* Organizational Structure Section */}
            {organizationItems.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <Collapsible defaultOpen={isOrganizationActive} className="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger asChild>
                                <SidebarMenuButton tooltip="Organizational Structure">
                                    <Users />
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
                                                isActive={page.url === item.href || page.url.startsWith(item.href + '/')}
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
            )}

            {/* HR Policies Section */}
            {policiesItems.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <Collapsible defaultOpen={isPoliciesActive} className="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger asChild>
                                <SidebarMenuButton tooltip="HR Policies">
                                    <FileText />
                                    <span>HR Policies</span>
                                    <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                                <SidebarMenuSub className="space-y-1">
                                    {policiesItems.map((item) => (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={page.url === item.href || page.url.startsWith(item.href + '/')}
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
            )}

            {/* System Management Section */}
            {systemItems.length > 0 && (
                <SidebarGroup className="px-2 py-0">
                    <Collapsible defaultOpen={isSystemActive} className="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger asChild>
                                <SidebarMenuButton tooltip="System Management">
                                    <Settings />
                                    <span>System</span>
                                    <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                                <SidebarMenuSub className="space-y-1">
                                    {systemItems.map((item) => (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={page.url === item.href || page.url.startsWith(item.href + '/')}
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
            )}
        </>
    );
}
