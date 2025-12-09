import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, Clock, CalendarDays, Timer, UserCheck } from 'lucide-react';
import { WorkingHoursForm, type WorkingHoursData } from '@/components/admin/working-hours-form';
import { HolidayCalendar, type Holiday } from '@/components/admin/holiday-calendar';
import { OvertimeRulesForm } from '@/components/admin/overtime-rules-form';
import { AttendancePoliciesForm } from '@/components/admin/attendance-policies-form';
import { useState } from 'react';
import { useToast } from '@/hooks/use-toast';

// Holiday interface imported from holiday-calendar component

interface OvertimeRules {
    threshold_hours: number;
    rate_regular: number;
    rate_holiday: number;
    rate_rest_day: number;
    max_hours_per_day: number;
    max_hours_per_week: number;
    auto_approve_threshold: number;
    requires_approval: boolean;
}

interface AttendanceRules {
    grace_period_minutes: number;
    late_deduction_type: 'per_minute' | 'per_bracket' | 'fixed';
    late_deduction_amount: number;
    undertime_enabled: boolean;
    undertime_deduction_type: 'proportional' | 'fixed' | 'none';
    absence_with_leave_deduction: number;
    absence_without_leave_deduction: number;
}

interface HolidayMultipliers {
    regular_multiplier: number;
    special_multiplier: number;
}

interface BusinessRulesData {
    working_hours: WorkingHoursData;
    holidays: Holiday[];
    overtime: OvertimeRules;
    attendance: AttendanceRules;
    holiday: HolidayMultipliers;
}

interface BusinessRulesIndexProps {
    businessRules: BusinessRulesData;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Business Rules',
        href: '/admin/business-rules',
    },
];

export default function BusinessRulesIndex({ businessRules }: BusinessRulesIndexProps) {
    const { toast } = useToast();
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [activeTab, setActiveTab] = useState('working-hours');

    const handleWorkingHoursSubmit = (data: WorkingHoursData) => {
        setIsSubmitting(true);

        router.put('/admin/business-rules/working-hours', { ...data }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Working hours configuration updated successfully.',
                    variant: 'default',
                });
                setIsSubmitting(false);
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update working hours. Please check the form and try again.',
                    variant: 'destructive',
                });
                setIsSubmitting(false);
                console.error('Working hours update errors:', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Business Rules Configuration" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit('/admin/dashboard')}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Dashboard
                        </Button>
                    </div>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Business Rules Configuration</h1>
                        <p className="text-muted-foreground mt-1">
                            Define working hours, holidays, overtime rules, and attendance policies
                        </p>
                    </div>
                </div>

                {/* Info Card */}
                <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/20">
                    <CardHeader>
                        <CardTitle className="text-base">Business Rules Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Configure the core business rules that govern your company's operations:
                        </p>
                        <ul className="list-disc list-inside space-y-1 ml-2">
                            <li><strong>Working Hours</strong>: Regular schedule, shifts, break times</li>
                            <li><strong>Holiday Calendar</strong>: National and company-specific holidays</li>
                            <li><strong>Overtime Rules</strong>: Rates, thresholds, and approval requirements</li>
                            <li><strong>Attendance Policies</strong>: Grace period, late deductions, undertime</li>
                        </ul>
                        <p className="text-xs pt-2">
                            <strong>Note:</strong> These settings affect payroll calculations, leave management, and attendance tracking.
                        </p>
                    </CardContent>
                </Card>

                {/* Tabbed Interface */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="grid w-full grid-cols-4 lg:w-auto lg:inline-grid">
                        <TabsTrigger value="working-hours" className="gap-2">
                            <Clock className="h-4 w-4" />
                            <span className="hidden sm:inline">Working Hours</span>
                            <span className="sm:hidden">Hours</span>
                        </TabsTrigger>
                        <TabsTrigger value="holidays" className="gap-2">
                            <CalendarDays className="h-4 w-4" />
                            <span className="hidden sm:inline">Holidays</span>
                            <span className="sm:hidden">Calendar</span>
                        </TabsTrigger>
                        <TabsTrigger value="overtime" className="gap-2">
                            <Timer className="h-4 w-4" />
                            <span className="hidden sm:inline">Overtime</span>
                            <span className="sm:hidden">OT</span>
                        </TabsTrigger>
                        <TabsTrigger value="attendance" className="gap-2">
                            <UserCheck className="h-4 w-4" />
                            <span className="hidden sm:inline">Attendance</span>
                            <span className="sm:hidden">Policies</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Working Hours Tab */}
                    <TabsContent value="working-hours" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Working Hours Configuration</CardTitle>
                                <CardDescription>
                                    Define regular working schedule, break times, and working days
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <WorkingHoursForm
                                    initialData={businessRules.working_hours}
                                    onSubmit={handleWorkingHoursSubmit}
                                    isSubmitting={isSubmitting}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Holidays Tab */}
                    <TabsContent value="holidays" className="space-y-4">
                        <HolidayCalendar holidays={businessRules.holidays} />
                    </TabsContent>

                    {/* Overtime Tab */}
                    <TabsContent value="overtime" className="space-y-4">
                        <OvertimeRulesForm overtimeRules={businessRules.overtime} />
                    </TabsContent>

                    {/* Attendance Tab */}
                    <TabsContent value="attendance" className="space-y-4">
                        <AttendancePoliciesForm attendanceRules={businessRules.attendance} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
