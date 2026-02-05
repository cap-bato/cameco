import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from './app-logo';
import { NavSystemAdmin } from '@/components/nav-system-admin';
import { NavHR } from '@/components/nav-hr';
import { NavPayroll } from '@/components/nav-payroll';
import { NavAdmin } from '@/components/nav-admin';
import { NavEmployee } from '@/components/nav-employee';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [

];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const userRoles = auth.roles || [];
    
    // Check user roles
    const isSuperadmin = userRoles.includes('Superadmin');
    const isOfficeAdmin = userRoles.includes('Office Admin');
    const isHRManager = userRoles.includes('HR Manager');
    const isHRStaff = userRoles.includes('HR Staff');
    const isPayrollOfficer = userRoles.includes('Payroll Officer');
    const isEmployee = userRoles.includes('Employee');
    const hasHRAccess = isHRManager || isHRStaff;
    
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                
                {/* Office Admin Navigation - Show for Office Admin */}
                {isOfficeAdmin && <NavAdmin />}
                
                {/* HR Navigation - Show for HR Manager and HR Staff */}
                {hasHRAccess && <NavHR />}
                
                {/* Payroll Officer Navigation - Show only for Payroll Officer */}
                {isPayrollOfficer && <NavPayroll />}
                
                {/* Employee Navigation - Show only for Employee role */}
                {isEmployee && <NavEmployee />}
                
                {/* System Admin Navigation - Show only for Superadmin */}
                {isSuperadmin && <NavSystemAdmin />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
