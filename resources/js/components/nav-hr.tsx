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
import { usePermission } from '@/components/permission-gate';
import { 
    Users, 
    Building2, 
    Briefcase, 
    Calendar, 
    FileText, 
    BarChart3, 
    UserCheck,
    ClipboardList,
    Shield,
    GitBranch,
    Repeat,
    ClipboardCheck,
    Clock,
    Upload,
    TrendingUp,
    FileSignature,
    FileQuestion
} from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

interface PageProps {
    auth?: {
        permissions?: string[];
    };
    url?: string;
    [key: string]: unknown;
}

export function NavHR() {
    const page = usePage<PageProps>();
    const pageProps = page.props;
    
    // Debug: Log everything
    console.log('=== NavHR Debug ===');
    console.log('Full page.props:', pageProps);
    console.log('page.props.auth:', pageProps.auth);
    console.log('page.props.auth.permissions:', pageProps.auth?.permissions);
    
    const { hasPermission, permissions } = usePermission();
    
    console.log('usePermission - permissions:', permissions);
    console.log('usePermission - has hr.employees.view?', hasPermission('hr.employees.view'));
    console.log('usePermission - has hr.leave-requests.view?', hasPermission('hr.leave-requests.view'));
    
    const employeeManagementItems = [
        {
            title: 'Employees',
            icon: Users,
            href: '/hr/employees',
            permission: 'hr.employees.view',
        },
        {
            title: 'Departments',
            icon: Building2,
            href: '/hr/departments',
            permission: 'hr.departments.view',
        },
        {
            title: 'Positions',
            icon: Briefcase,
            href: '/hr/positions',
            permission: 'hr.positions.view',
        },
    ];
    
    console.log('Before filter - employeeManagementItems:', employeeManagementItems);
    const filteredEmployeeItems = employeeManagementItems.filter(item => {
        const has = hasPermission(item.permission);
        console.log(`  Checking ${item.title} (${item.permission}):`, has);
        return has;
    });
    console.log('After filter - filteredEmployeeItems:', filteredEmployeeItems);

    const leaveManagementItems = [
        {
            title: 'Leave Requests',
            icon: ClipboardList,
            href: '/hr/leave/requests',
            permission: 'hr.leave-requests.view',
        },
        {
            title: 'Leave Balances',
            icon: Calendar,
            href: '/hr/leave/balances',
            permission: 'hr.leave-balances.view',
        },
        {
            title: 'Leave Policies',
            icon: Shield,
            href: '/hr/leave/policies',
            permission: 'hr.leave-policies.view',
        },
    ];
    
    console.log('Before filter - leaveManagementItems:', leaveManagementItems);
    const filteredLeaveItems = leaveManagementItems.filter(item => {
        const has = hasPermission(item.permission);
        console.log(`  Checking ${item.title} (${item.permission}):`, has);
        return has;
    });
    console.log('After filter - filteredLeaveItems:', filteredLeaveItems);

    const documentManagementItemsAll = [
        {
            title: 'All Documents',
            icon: FileText,
            href: '/hr/documents',
            permission: 'hr.documents.view',
        },
        {
            title: 'Templates',
            icon: FileSignature,
            href: '/hr/documents/templates',
            permission: 'hr.documents.templates.manage',
        },
        {
            title: 'Requests',
            icon: FileQuestion,
            href: '/hr/documents/requests',
            permission: 'hr.documents.view',
        },
    ];
    const documentManagementItems = documentManagementItemsAll.filter(item => hasPermission(item.permission));

    const recruitmentItemsAll = [
        {
            title: 'Job Postings',
            icon: Briefcase,
            href: '/hr/ats/job-postings',
            permission: 'hr.ats.view',
        },
        {
            title: 'Candidates',
            icon: Users,
            href: '/hr/ats/candidates',
            permission: 'hr.ats.candidates.view',
        },
        {
            title: 'Applications',
            icon: FileText,
            href: '/hr/ats/applications',
            permission: 'hr.ats.applications.view',
        },
        {
            title: 'Interviews',
            icon: Calendar,
            href: '/hr/ats/interviews',
            permission: 'hr.ats.interviews.schedule',
        },
        {
            title: 'Hiring Pipeline',
            icon: GitBranch,
            href: '/hr/ats/hiring-pipeline',
            permission: 'hr.ats.view',
        },
    ];
    const recruitmentItems = recruitmentItemsAll.filter(item => hasPermission(item.permission));

    const workforceManagementItemsAll = [
        {
            title: 'Work Schedules',
            icon: Calendar,
            href: '/hr/workforce/schedules',
            permission: 'hr.workforce.schedules.view',
        },
        {
            title: 'Employee Rotations',
            icon: Repeat,
            href: '/hr/workforce/rotations',
            permission: 'hr.workforce.rotations.view',
        },
        {
            title: 'Shift Assignments',
            icon: ClipboardCheck,
            href: '/hr/workforce/assignments',
            permission: 'hr.workforce.assignments.view',
        },
    ];
    const workforceManagementItems = workforceManagementItemsAll.filter(item => hasPermission(item.permission));

    const reportsItemsAll = [
        {
            title: 'Employee Reports',
            icon: BarChart3,
            href: '/hr/reports/employees',
            permission: 'hr.reports.view',
        },
        {
            title: 'Leave Reports',
            icon: Calendar,
            href: '/hr/reports/leave',
            permission: 'hr.reports.view',
        },
        {
            title: 'Analytics',
            icon: BarChart3,
            href: '/hr/reports/analytics',
            permission: 'hr.reports.view',
        },
    ];
    const reportsItems = reportsItemsAll.filter(item => hasPermission(item.permission));

    const timekeepingItemsAll = [
        {
            title: 'Attendance Overview',
            icon: Calendar,
            href: '/hr/timekeeping/overview',
            permission: 'hr.timekeeping.view',
        },
        {
            title: 'Attendance Records',
            icon: ClipboardList,
            href: '/hr/timekeeping/attendance',
            permission: 'hr.timekeeping.view',
        },
        {
            title: 'Overtime Requests',
            icon: Clock,
            href: '/hr/timekeeping/overtime',
            permission: 'hr.timekeeping.overtime.view',
        },
        {
            title: 'Import Management',
            icon: Upload,
            href: '/hr/timekeeping/import',
            permission: 'hr.timekeeping.manage',
        },
    ];
    const timekeepingItems = timekeepingItemsAll.filter(item => hasPermission(item.permission));

    const appraisalItemsAll = [
        {
            title: 'Appraisal Cycles',
            icon: Calendar,
            href: '/hr/appraisals/cycles',
            permission: 'hr.appraisals.view',
        },
        {
            title: 'Appraisals',
            icon: ClipboardCheck,
            href: '/hr/appraisals',
            permission: 'hr.appraisals.view',
        },
        {
            title: 'Performance Metrics',
            icon: TrendingUp,
            href: '/hr/performance-metrics',
            permission: 'hr.appraisals.view',
        },
        {
            title: 'Rehire Recommendations',
            icon: UserCheck,
            href: '/hr/rehire-recommendations',
            permission: 'hr.appraisals.view',
        },
    ];
    const appraisalItems = appraisalItemsAll.filter(item => hasPermission(item.permission));

    const isEmployeeManagementActive = page.url.startsWith('/hr/employees') || page.url.startsWith('/hr/departments') || page.url.startsWith('/hr/positions') || page.url === '/hr/dashboard';
    const isLeaveManagementActive = page.url.startsWith('/hr/leave');
    const isDocumentManagementActive = page.url.startsWith('/hr/documents');
    const isReportsActive = page.url.startsWith('/hr/reports');
    const isRecruitmentActive = page.url.startsWith('/hr/ats');
    const isWorkforceManagementActive = page.url.startsWith('/hr/workforce');
    const isTimekeepingActive = page.url.startsWith('/hr/timekeeping');
    const isAppraisalActive = page.url.startsWith('/hr/appraisals') || page.url.startsWith('/hr/performance-metrics') || page.url.startsWith('/hr/rehire-recommendations');

    return (
        <>
            {/* Employee Management Section */}
            {filteredEmployeeItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isEmployeeManagementActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Employee Management">
                                <UserCheck />
                                <span>Employee Management</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {filteredEmployeeItems.map((item) => (
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

            {/* Leave Management Section */}
            {filteredLeaveItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isLeaveManagementActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Leave Management">
                                <Calendar />
                                <span>Leave Management</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {filteredLeaveItems.map((item) => (
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
            )}

            {/* Document Management Section */}
            {documentManagementItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isDocumentManagementActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Document Management">
                                <FileText />
                                <span>Documents</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {documentManagementItems.map((item) => (
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
            )}

            {/* Recruitment Section */}
            {recruitmentItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isRecruitmentActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Recruitment & ATS">
                                <Briefcase />
                                <span>Recruitment</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {recruitmentItems.map((item) => (
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
            )}

            {/* Workforce Management Section */}
            {workforceManagementItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isWorkforceManagementActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Workforce Management">
                                <ClipboardCheck />
                                <span>Workforce Management</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {workforceManagementItems.map((item) => (
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
            )}

            {/* Timekeeping Section */}
            {timekeepingItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isTimekeepingActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Timekeeping & Attendance">
                                <Clock />
                                <span>Timekeeping</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {timekeepingItems.map((item) => (
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
            )}

            {/* Performance & Appraisal Section */}
            {appraisalItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isAppraisalActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Performance & Appraisal">
                                <TrendingUp />
                                <span>Performance & Appraisal</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {appraisalItems.map((item) => (
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
            )}

            {/* Reports Section */}
            {reportsItems.length > 0 && (
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isReportsActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="HR Reports & Analytics">
                                <FileText />
                                <span>Reports</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub className="space-y-1">
                                {reportsItems.map((item) => (
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
            )}
        </>
    );
}
