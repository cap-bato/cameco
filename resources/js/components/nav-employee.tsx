import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    User,
    Clock,
    DollarSign,
    Calendar,
    Bell,
    FileText,
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

export function NavEmployee() {
    const page = usePage();

    // Main menu items for employee self-service
    const mainMenuItems = [
        {
            title: 'My Profile',
            icon: User,
            href: '/employee/profile',
            description: 'View and update personal information',
        },
        {
            title: 'Attendance',
            icon: Clock,
            href: '/employee/attendance',
            description: 'View time logs and report issues',
        },
        {
            title: 'Payslips',
            icon: DollarSign,
            href: '/employee/payslips',
            description: 'View and download payslips',
        },
    ];

    // Leave Management submenu
    const leaveMenuItems = [
        {
            title: 'Leave Balances',
            href: '/employee/leave/balances',
            description: 'Check available leave days',
        },
        {
            title: 'Leave History',
            href: '/employee/leave/history',
            description: 'View past leave requests',
        },
        {
            title: 'Apply for Leave',
            href: '/employee/leave/request',
            description: 'Submit new leave request',
        },
    ];

    const isLeaveActive = page.url.startsWith('/employee/leave');

    return (
        <>
            {/* Main Navigation */}
            <SidebarGroup className="px-2 py-0">
                <div className="flex flex-col gap-1">
                    {mainMenuItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = page.url === item.href;
                        
                        return (
                            <SidebarMenuItem key={item.href}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive}
                                    tooltip={item.description}
                                >
                                    <Link href={item.href}>
                                        <Icon className="h-4 w-4" />
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </div>
            </SidebarGroup>

            {/* Leave Management Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isLeaveActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton tooltip="Leave Management">
                                <Calendar className="h-4 w-4" />
                                <span>Leave Management</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                {leaveMenuItems.map((item) => {
                                    const isActive = page.url === item.href;
                                    
                                    return (
                                        <SidebarMenuSubItem key={item.href}>
                                            <SidebarMenuSubButton
                                                asChild
                                                isActive={isActive}
                                            >
                                                <Link href={item.href}>
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            {/* Notifications */}
            <SidebarGroup className="px-2 py-0">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        isActive={page.url === '/employee/notifications'}
                        tooltip="View all notifications"
                    >
                        <Link href="/employee/notifications">
                            <Bell className="h-4 w-4" />
                            <span>Notifications</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarGroup>

            {/* Help Section */}
            <SidebarGroup className="mt-auto px-2 py-0">
                <div className="rounded-lg border bg-muted/50 p-3">
                    <div className="flex items-start gap-3">
                        <FileText className="mt-0.5 h-4 w-4 text-muted-foreground" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium">Need Help?</p>
                            <p className="text-xs text-muted-foreground">
                                Contact HR Staff for assistance with leave requests, attendance issues, or profile updates.
                            </p>
                        </div>
                    </div>
                </div>
            </SidebarGroup>
        </>
    );
}
