import { usePage } from '@inertiajs/react';
import {
    Calculator,
    Calendar,
    DollarSign,
    FileText,
    Users,
    TrendingUp,
    CreditCard,
    Building2,
    ClipboardList,
    BarChart3,
    ChevronRight,
    Landmark,
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
    SidebarMenuSubItem,
    SidebarMenuSubButton,
} from '@/components/ui/sidebar';

export function NavPayroll() {
    const page = usePage();

    const payrollPeriodsItems = [
        {
            title: 'Payroll Periods',
            icon: Calendar,
            href: '/payroll/periods',
        },
        {
            title: 'Calculations',
            icon: Calculator,
            href: '/payroll/calculations',
        },
        {
            title: 'Adjustments',
            icon: FileText,
            href: '/payroll/adjustments',
        },
        {
            title: 'Review & Approval',
            icon: ClipboardList,
            href: '/payroll/review',
        },
    ];

    const employeePayrollItems = [
        {
            title: 'Employee Payroll Info',
            icon: Users,
            href: '/payroll/employee-payroll-info',
            enabled: true,
        },
        {
            title: 'Salary Components',
            icon: DollarSign,
            href: '/payroll/salary-components',
            enabled: true, 
        },
        {
            title: 'Allowances & Deductions',
            icon: ClipboardList,
            href: '/payroll/allowances-deductions',
            enabled: true,
        },
        {
            title: 'Loans',
            icon: Landmark,
            href: '/payroll/loans',
            enabled: true,
        },
        {
            title: 'Advances',
            icon: Landmark,
            href: '/payroll/advances',
            enabled: true,
        },
    ];

    const governmentComplianceItems = [
        {
            title: 'BIR Reports',
            icon: FileText,
            href: '/payroll/government/bir',
            enabled: true,
        },
        {
            title: 'SSS Contributions',
            icon: Building2,
            href: '/payroll/government/sss',
            enabled: true,
        },
        {
            title: 'PhilHealth',
            icon: Building2,
            href: '/payroll/government/philhealth',
            enabled: true,
        },
        {
            title: 'Pag-IBIG',
            icon: Building2,
            href: '/payroll/government/pagibig',
            enabled: true,
        },
        {
            title: 'Remittances',
            icon: CreditCard,
            href: '/payroll/government/remittances',
            enabled: true,
        },
    ];

    const paymentsItems = [
        {
            title: 'Bank Files',
            icon: CreditCard,
            href: '/payroll/bank-files',
            enabled: true,
        },
        {
            title: 'Payslips',
            icon: FileText,
            href: '/payroll/payments/payslips',
            enabled: false,
        },
        {
            title: 'Payment Tracking',
            icon: TrendingUp,
            href: '/payroll/payments/tracking',
            enabled: true,
        },
        {
            title: 'Cash Management',
            icon: DollarSign,
            href: '/payroll/payments/cash',
            enabled: true,
        },
    ];

    const reportsItems = [
        {
            title: 'Payroll Register',
            icon: ClipboardList,
            href: '/payroll/reports/register',
            enabled: true,
        },
        {
            title: 'Government Reports',
            icon: Building2,
            href: '/payroll/reports/government',
            enabled: true,
        },
        {
            title: 'Analytics',
            icon: BarChart3,
            href: '/payroll/reports/analytics',
            enabled: true,
        },
        {
            title: 'Audit Trail',
            icon: BarChart3,
            href: '/payroll/reports/audit',
            enabled: true,
        },
    ];

    const isPayrollPeriodsActive = page.url.startsWith('/payroll/periods') || page.url.startsWith('/payroll/calculations') || page.url.startsWith('/payroll/adjustments') || page.url.startsWith('/payroll/review');
    const isEmployeePayrollActive = page.url.startsWith('/payroll/employee-payroll-info') || page.url.startsWith('/payroll/components') || page.url.startsWith('/payroll/allowances') || page.url.startsWith('/payroll/loans') || page.url.startsWith('/payroll/advances');
    const isGovernmentComplianceActive = page.url.startsWith('/payroll/government');
    const isPaymentsActive = page.url.startsWith('/payroll/bank-files') || page.url.startsWith('/payroll/payslips') || page.url.startsWith('/payroll/payments');
    const isReportsActive = page.url.startsWith('/payroll/reports');

    return (
        <>
            {/* Payroll Processing Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isPayrollPeriodsActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                                <Calculator className="h-4 w-4" />
                                <span>Payroll Processing</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub>
                                {payrollPeriodsItems.map((item) => {
                                    const isActive = page.url.startsWith(item.href);
                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActive}>
                                                <a href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    <span>{item.title}</span>
                                                </a>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            {/* Employee Payroll Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isEmployeePayrollActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                                <Users className="h-4 w-4" />
                                <span>Employee Payroll</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub>
                                {employeePayrollItems.map((item) => {
                                    const isActive = page.url.startsWith(item.href);
                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActive}>
                                                <a href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    <span>{item.title}</span>
                                                </a>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            {/* Government Compliance Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isGovernmentComplianceActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                                <Building2 className="h-4 w-4" />
                                <span>Government Compliance</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub>
                                {governmentComplianceItems.map((item) => {
                                    const isActive = page.url.startsWith(item.href);
                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActive}>
                                                <a href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    <span>{item.title}</span>
                                                </a>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            {/* Payments & Disbursements Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isPaymentsActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                                <CreditCard className="h-4 w-4" />
                                <span>Payments</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub>
                                {paymentsItems.map((item) => {
                                    const isActive = page.url.startsWith(item.href);
                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActive}>
                                                <a href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    <span>{item.title}</span>
                                                </a>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>

            {/* Reports Section */}
            <SidebarGroup className="px-2 py-0">
                <Collapsible defaultOpen={isReportsActive} className="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                                <BarChart3 className="h-4 w-4" />
                                <span>Reports & Analytics</span>
                                <ChevronRight className="ml-auto h-4 w-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:slide-out-to-top-2 data-[state=open]:slide-in-from-top-2">
                            <SidebarMenuSub>
                                {reportsItems.map((item) => {
                                    const isActive = page.url.startsWith(item.href);
                                    return (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActive}>
                                                <a href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    <span>{item.title}</span>
                                                </a>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    );
                                })}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarGroup>
        </>
    );
}
