import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, Save, AlertCircle, CheckCircle2, Clock, Users, Calendar, AlertTriangle, TrendingUp, Ban } from 'lucide-react';
import { ApprovalRuleCard } from '@/components/admin/approval-rule-card';
import { WorkflowTester } from '@/components/admin/workflow-tester';
import { useToast } from '@/hooks/use-toast';

interface BlackoutDate {
    start: string;
    end: string;
    reason: string;
}

interface ApprovalRules {
    // Duration-based rules
    duration_threshold_days: number;
    duration_tier2_days: number;
    
    // Balance threshold
    balance_threshold_days: number;
    balance_warning_enabled: boolean;
    
    // Advance notice
    advance_notice_days: number;
    short_notice_requires_approval: boolean;
    
    // Workforce impact
    coverage_threshold_percentage: number;
    coverage_warning_enabled: boolean;
    
    // Leave type specific
    unpaid_leave_requires_manager: boolean;
    maternity_requires_admin: boolean;
    
    // Blackout periods
    blackout_periods_enabled: boolean;
    blackout_dates: BlackoutDate[];
    
    // Frequency limits
    frequency_limit_enabled: boolean;
    frequency_max_requests: number;
    frequency_period_days: number;
}

interface LeaveType {
    code: string;
    name: string;
    is_paid: boolean;
}

interface ApprovalRulesPageProps {
    approvalRules: ApprovalRules;
    leaveTypes: LeaveType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Leave Policies',
        href: '/admin/leave-policies',
    },
    {
        title: 'Approval Rules',
        href: '/admin/leave-policies/approval-rules',
    },
];

export default function ApprovalRulesPage({ approvalRules, leaveTypes }: ApprovalRulesPageProps) {
    const { toast } = useToast();
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [formData, setFormData] = useState<ApprovalRules>(approvalRules);
    const [hasChanges, setHasChanges] = useState(false);

    const handleRuleChange = (field: keyof ApprovalRules, value: number | boolean | BlackoutDate[]) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        setHasChanges(true);
    };

    const handleSaveAll = () => {
        setIsSubmitting(true);

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.put('/admin/leave-policies/approval-rules', formData as Record<string, any>, {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Approval rules updated successfully.',
                });
                setHasChanges(false);
                setIsSubmitting(false);
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: 'Failed to update approval rules. Please check your inputs.',
                    variant: 'destructive',
                });
                setIsSubmitting(false);
                console.error('Approval rules update errors:', errors);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Approval Rules" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Header */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => router.visit('/admin/leave-policies')}
                                >
                                    <ArrowLeft className="h-4 w-4 mr-2" />
                                    Back to Leave Policies
                                </Button>
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight mt-2">Leave Approval Rules</h1>
                            <p className="text-muted-foreground mt-1">
                                Configure 7 approval workflow rules for automated leave request routing
                            </p>
                        </div>
                        <Button 
                            onClick={handleSaveAll}
                            disabled={isSubmitting || !hasChanges}
                        >
                            {isSubmitting ? (
                                <>
                                    <Clock className="h-4 w-4 mr-2 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <Save className="h-4 w-4 mr-2" />
                                    Save All Rules
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Info Alert */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <strong>How Approval Rules Work:</strong> These rules determine automatic routing of leave requests. 
                        HR Staff approves by default. If any configured rule is triggered, the request escalates to HR Manager or Office Admin. 
                        Multiple rules can apply simultaneously.
                    </AlertDescription>
                </Alert>

                {/* Approval Flow Diagram */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Approval Flow Overview</CardTitle>
                        <CardDescription>Three-tier approval hierarchy</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="p-4 border rounded-lg bg-green-50 dark:bg-green-950/20">
                                <div className="flex items-center gap-2 mb-2">
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                    <h3 className="font-semibold">Tier 1: HR Staff</h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Standard requests within policy limits, sufficient balance, advance notice met, no workforce impact
                                </p>
                            </div>
                            <div className="p-4 border rounded-lg bg-yellow-50 dark:bg-yellow-950/20">
                                <div className="flex items-center gap-2 mb-2">
                                    <AlertTriangle className="h-5 w-5 text-yellow-600" />
                                    <h3 className="font-semibold">Tier 2: HR Manager</h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Triggered by: Duration threshold, short notice, low balance, coverage impact, blackout periods, frequency limits
                                </p>
                            </div>
                            <div className="p-4 border rounded-lg bg-red-50 dark:bg-red-950/20">
                                <div className="flex items-center gap-2 mb-2">
                                    <AlertCircle className="h-5 w-5 text-red-600" />
                                    <h3 className="font-semibold">Tier 3: Office Admin</h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Extended requests (&gt; {formData.duration_tier2_days} days), maternity leave, extended unpaid leave, special circumstances
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Approval Rules Cards */}
                <Tabs defaultValue="duration" className="space-y-4">
                    <TabsList className="grid w-full grid-cols-8">
                        <TabsTrigger value="duration">Duration</TabsTrigger>
                        <TabsTrigger value="balance">Balance</TabsTrigger>
                        <TabsTrigger value="notice">Notice</TabsTrigger>
                        <TabsTrigger value="coverage">Coverage</TabsTrigger>
                        <TabsTrigger value="leavetype">Leave Type</TabsTrigger>
                        <TabsTrigger value="blackout">Blackout</TabsTrigger>
                        <TabsTrigger value="frequency">Frequency</TabsTrigger>
                        <TabsTrigger value="tester">Workflow Tester</TabsTrigger>
                    </TabsList>

                    {/* Rule 1: Duration-based Rules */}
                    <TabsContent value="duration">
                        <ApprovalRuleCard
                            icon={Clock}
                            title="Duration-based Rules"
                            description="Route requests based on leave duration (number of days)"
                            ruleNumber={1}
                            isEnabled={true} // Always enabled
                            fields={[
                                {
                                    label: 'HR Manager Threshold',
                                    name: 'duration_threshold_days',
                                    type: 'number',
                                    value: formData.duration_threshold_days,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('duration_threshold_days', value as number),
                                    min: 1,
                                    max: 30,
                                    helpText: `Leave requests > ${formData.duration_threshold_days} days require HR Manager approval`,
                                },
                                {
                                    label: 'Office Admin Threshold',
                                    name: 'duration_tier2_days',
                                    type: 'number',
                                    value: formData.duration_tier2_days,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('duration_tier2_days', value as number),
                                    min: 1,
                                    max: 90,
                                    helpText: `Leave requests > ${formData.duration_tier2_days} days require Office Admin approval`,
                                },
                            ]}
                            examples={[
                                '1-5 days: HR Staff approves',
                                `6-${formData.duration_tier2_days} days: HR Manager approves`,
                                `${formData.duration_tier2_days + 1}+ days: Office Admin approves`,
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 2: Balance Threshold Rules */}
                    <TabsContent value="balance">
                        <ApprovalRuleCard
                            icon={TrendingUp}
                            title="Balance Threshold Rules"
                            description="Warn or require approval when leave balance is low"
                            ruleNumber={2}
                            isEnabled={formData.balance_warning_enabled}
                            onToggle={(enabled: boolean) => handleRuleChange('balance_warning_enabled', enabled)}
                            fields={[
                                {
                                    label: 'Warning Threshold (days)',
                                    name: 'balance_threshold_days',
                                    type: 'number',
                                    value: formData.balance_threshold_days,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('balance_threshold_days', value as number),
                                    min: 0,
                                    max: 30,
                                    helpText: `Show warning when balance falls below ${formData.balance_threshold_days} days after approval`,
                                },
                            ]}
                            examples={[
                                `Balance > ${formData.balance_threshold_days} days after leave: HR Staff approves`,
                                `Balance ≤ ${formData.balance_threshold_days} days: HR Manager approval with warning`,
                                'Insufficient balance: Request blocked',
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 3: Advance Notice Rules */}
                    <TabsContent value="notice">
                        <ApprovalRuleCard
                            icon={Calendar}
                            title="Advance Notice Rules"
                            description="Require manager approval for short-notice requests"
                            ruleNumber={3}
                            isEnabled={formData.short_notice_requires_approval}
                            onToggle={(enabled: boolean) => handleRuleChange('short_notice_requires_approval', enabled)}
                            fields={[
                                {
                                    label: 'Required Advance Notice (days)',
                                    name: 'advance_notice_days',
                                    type: 'number',
                                    value: formData.advance_notice_days,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('advance_notice_days', value as number),
                                    min: 0,
                                    max: 90,
                                    helpText: `Requests with less than ${formData.advance_notice_days} days notice require HR Manager approval`,
                                },
                            ]}
                            examples={[
                                `≥ ${formData.advance_notice_days} days notice: HR Staff approves`,
                                `< ${formData.advance_notice_days} days notice: HR Manager approval required`,
                                'Same-day requests: HR Manager reviews urgency',
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 4: Workforce Coverage Rules */}
                    <TabsContent value="coverage">
                        <ApprovalRuleCard
                            icon={Users}
                            title="Workforce Coverage Rules"
                            description="Prevent department understaffing by monitoring coverage percentage"
                            ruleNumber={4}
                            isEnabled={formData.coverage_warning_enabled}
                            onToggle={(enabled: boolean) => handleRuleChange('coverage_warning_enabled', enabled)}
                            fields={[
                                {
                                    label: 'Minimum Coverage (%)',
                                    name: 'coverage_threshold_percentage',
                                    type: 'number',
                                    value: formData.coverage_threshold_percentage,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('coverage_threshold_percentage', value as number),
                                    min: 0,
                                    max: 100,
                                    helpText: `Show warning if coverage falls below ${formData.coverage_threshold_percentage}% when leave is approved`,
                                },
                            ]}
                            examples={[
                                `Coverage ≥ ${formData.coverage_threshold_percentage}%: HR Staff approves`,
                                `Coverage < ${formData.coverage_threshold_percentage}%: HR Manager review required`,
                                'Critical: <50% coverage requires justification',
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 5: Leave Type Specific Rules */}
                    <TabsContent value="leave-type">
                        <ApprovalRuleCard
                            icon={AlertTriangle}
                            title="Leave Type Specific Rules"
                            description="Special approval requirements for certain leave types"
                            ruleNumber={5}
                            isEnabled={true} // Always enabled
                            fields={[
                                {
                                    label: 'Unpaid Leave Requires Manager',
                                    name: 'unpaid_leave_requires_manager',
                                    type: 'checkbox',
                                    value: formData.unpaid_leave_requires_manager,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('unpaid_leave_requires_manager', value as boolean),
                                    helpText: 'All unpaid leave requests require HR Manager approval',
                                },
                                {
                                    label: 'Maternity Leave Requires Admin',
                                    name: 'maternity_requires_admin',
                                    type: 'checkbox',
                                    value: formData.maternity_requires_admin,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('maternity_requires_admin', value as boolean),
                                    helpText: 'Maternity/Paternity leave (105/7 days) requires Office Admin approval',
                                },
                            ]}
                            examples={[
                                'Vacation/Sick Leave: Standard rules apply',
                                'Unpaid Leave: Always HR Manager approval',
                                'Maternity/Paternity: Always Office Admin approval',
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 6: Blackout Period Rules */}
                    <TabsContent value="blackout">
                        <ApprovalRuleCard
                            icon={Ban}
                            title="Blackout Period Rules"
                            description="Restrict leave requests during busy seasons or critical periods"
                            ruleNumber={6}
                            isEnabled={formData.blackout_periods_enabled}
                            onToggle={(enabled: boolean) => handleRuleChange('blackout_periods_enabled', enabled)}
                            fields={[
                                {
                                    label: 'Blackout Periods',
                                    name: 'blackout_dates',
                                    type: 'blackout-dates',
                                    value: formData.blackout_dates,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('blackout_dates', value),
                                    helpText: 'Define date ranges when leave requests are blocked or require additional approval',
                                },
                            ]}
                            examples={[
                                'Outside blackout periods: Standard rules apply',
                                'During blackout: HR Manager approval required',
                                'Critical blackout: May be blocked entirely',
                            ]}
                        />
                    </TabsContent>

                    {/* Rule 7: Frequency Limit Rules */}
                    <TabsContent value="frequency">
                        <ApprovalRuleCard
                            icon={TrendingUp}
                            title="Frequency Limit Rules"
                            description="Monitor and limit frequent leave requests within a timeframe"
                            ruleNumber={7}
                            isEnabled={formData.frequency_limit_enabled}
                            onToggle={(enabled: boolean) => handleRuleChange('frequency_limit_enabled', enabled)}
                            fields={[
                                {
                                    label: 'Maximum Requests',
                                    name: 'frequency_max_requests',
                                    type: 'number',
                                    value: formData.frequency_max_requests,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('frequency_max_requests', value as number),
                                    min: 1,
                                    max: 20,
                                    helpText: `Maximum number of leave requests within period`,
                                },
                                {
                                    label: 'Period (days)',
                                    name: 'frequency_period_days',
                                    type: 'number',
                                    value: formData.frequency_period_days,
                                    onChange: (value: number | boolean | BlackoutDate[]) => handleRuleChange('frequency_period_days', value as number),
                                    min: 1,
                                    max: 365,
                                    helpText: `Checking period (e.g., ${formData.frequency_period_days} days = 1 month)`,
                                },
                            ]}
                            examples={[
                                `≤ ${formData.frequency_max_requests} requests in ${formData.frequency_period_days} days: HR Staff approves`,
                                `> ${formData.frequency_max_requests} requests: HR Manager reviews pattern`,
                                'Excessive requests: May indicate attendance issues',
                            ]}
                        />
                    </TabsContent>

                    {/* Workflow Tester Tab */}
                    <TabsContent value="tester" className="space-y-4">
                        <WorkflowTester approvalRules={formData} leaveTypes={leaveTypes} />
                    </TabsContent>
                </Tabs>

                {/* Bottom Save Button */}
                {hasChanges && (
                    <div className="flex justify-end sticky bottom-6 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 p-4 rounded-lg border">
                        <Button 
                            onClick={handleSaveAll}
                            disabled={isSubmitting}
                            size="lg"
                        >
                            {isSubmitting ? (
                                <>
                                    <Clock className="h-4 w-4 mr-2 animate-spin" />
                                    Saving Changes...
                                </>
                            ) : (
                                <>
                                    <Save className="h-4 w-4 mr-2" />
                                    Save All Rules
                                </>
                            )}
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
