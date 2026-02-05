import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Save, AlertCircle, CheckCircle2, Clock, Users, Briefcase, DollarSign, Receipt, GitBranch, Calendar, AlertTriangle, Ban, TrendingUp } from 'lucide-react';
import { ApprovalRuleCard } from '@/components/admin/approval-rule-card';
import { WorkflowTester } from '@/components/admin/workflow-tester';
import { useToast } from '@/hooks/use-toast';

interface BlackoutDate {
    start: string;
    end: string;
    reason: string;
}

interface LeaveApprovalRules {
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

interface ApprovalWorkflowsIndexProps {
    approvalRules: LeaveApprovalRules;
    leaveTypes: LeaveType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
    },
    {
        title: 'Approval Workflows',
        href: '/admin/approval-workflows',
    },
];

export default function ApprovalWorkflowsIndex({ approvalRules, leaveTypes }: ApprovalWorkflowsIndexProps) {
    const { toast } = useToast();
    const [activeTab, setActiveTab] = useState('leave');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [formData, setFormData] = useState<LeaveApprovalRules>(approvalRules);
    const [hasChanges, setHasChanges] = useState(false);

    const handleRuleChange = (field: keyof LeaveApprovalRules, value: number | boolean | BlackoutDate[]) => {
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
                    description: 'Leave approval rules updated successfully.',
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
            <Head title="Approval Workflows" />
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
                    <h1 className="text-3xl font-bold tracking-tight">Approval Workflows</h1>
                    <p className="text-muted-foreground">
                        Configure multi-level approval workflows for leave requests, hiring, payroll, and expenses
                    </p>
                </div>

                {/* Info Alert */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <strong>MVP Scope:</strong> Leave Approval workflow is fully implemented. Hiring, Payroll, and Expense workflows are planned for Phase 2. Configure at least 3 leave approval rules to complete dashboard setup Step 7.
                    </AlertDescription>
                </Alert>

                {/* Overview Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Workflow Overview</CardTitle>
                        <CardDescription>Manage approval routing for different business processes</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-blue-100 dark:bg-blue-950 p-2">
                                    <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Leave Approval</p>
                                    <p className="text-2xl font-bold">
                                        {Object.values(formData).filter(v => typeof v === 'boolean' && v).length} Rules Active
                                    </p>
                                    <Badge variant="default" className="mt-1">Fully Implemented</Badge>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-green-100 dark:bg-green-950 p-2">
                                    <Briefcase className="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Hiring Approval</p>
                                    <p className="text-2xl font-bold">Phase 2</p>
                                    <Badge variant="outline" className="mt-1">Coming Soon</Badge>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-purple-100 dark:bg-purple-950 p-2">
                                    <DollarSign className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Payroll Approval</p>
                                    <p className="text-2xl font-bold">Phase 2</p>
                                    <Badge variant="outline" className="mt-1">Coming Soon</Badge>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="rounded-lg bg-orange-100 dark:bg-orange-950 p-2">
                                    <Receipt className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Expense Approval</p>
                                    <p className="text-2xl font-bold">Phase 2</p>
                                    <Badge variant="outline" className="mt-1">Coming Soon</Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabbed Interface */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="leave" className="flex items-center gap-2">
                            <Clock className="h-4 w-4" />
                            Leave Approval
                            <Badge variant="default" className="ml-1 text-xs">Active</Badge>
                        </TabsTrigger>
                        <TabsTrigger value="hiring" className="flex items-center gap-2">
                            <Briefcase className="h-4 w-4" />
                            Hiring
                            <Badge variant="outline" className="ml-1 text-xs">Phase 2</Badge>
                        </TabsTrigger>
                        <TabsTrigger value="payroll" className="flex items-center gap-2">
                            <DollarSign className="h-4 w-4" />
                            Payroll
                            <Badge variant="outline" className="ml-1 text-xs">Phase 2</Badge>
                        </TabsTrigger>
                        <TabsTrigger value="expense" className="flex items-center gap-2">
                            <Receipt className="h-4 w-4" />
                            Expense
                            <Badge variant="outline" className="ml-1 text-xs">Phase 2</Badge>
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Leave Approval (Full Implementation) */}
                    <TabsContent value="leave" className="space-y-6">
                        {/* Leave Approval Rules - MVP Implementation */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Clock className="h-5 w-5" />
                                            Leave Approval Configuration
                                        </CardTitle>
                                        <CardDescription>
                                            Configure 7 rule types to automatically route leave requests to the appropriate approver
                                        </CardDescription>
                                    </div>
                                    {hasChanges && (
                                        <Button onClick={handleSaveAll} disabled={isSubmitting}>
                                            <Save className="h-4 w-4 mr-2" />
                                            {isSubmitting ? 'Saving...' : 'Save All Rules'}
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Rule 1: Duration-Based Rules */}
                                <ApprovalRuleCard
                                    icon={Clock}
                                    title="Duration-Based Rules"
                                    description="Route approval based on leave duration length"
                                    ruleNumber={1}
                                    fields={[
                                        {
                                            label: 'Days requiring HR Manager approval',
                                            name: 'duration_threshold_days',
                                            type: 'number',
                                            value: formData.duration_threshold_days,
                                            onChange: (v) => handleRuleChange('duration_threshold_days', v as number),
                                            min: 1,
                                            max: 365,
                                            helpText: 'Leave requests exceeding this duration require HR Manager approval',
                                        },
                                        {
                                            label: 'Days requiring Office Admin approval',
                                            name: 'duration_tier2_days',
                                            type: 'number',
                                            value: formData.duration_tier2_days,
                                            onChange: (v) => handleRuleChange('duration_tier2_days', v as number),
                                            min: 1,
                                            max: 365,
                                            helpText: 'Leave requests exceeding this duration require Office Admin approval (in addition to HR Manager)',
                                        },
                                    ]}
                                    examples={[
                                        '≤ 5 days → HR Staff approval',
                                        '6-14 days → HR Manager approval',
                                        '≥ 15 days → HR Manager + Office Admin approval',
                                    ]}
                                />

                                {/* Rule 2: Workforce Coverage Rules */}
                                <ApprovalRuleCard
                                    icon={Users}
                                    title="Workforce Coverage Rules"
                                    description="Route approval based on department staffing impact"
                                    ruleNumber={2}
                                    isEnabled={formData.coverage_warning_enabled}
                                    onToggle={(enabled) => handleRuleChange('coverage_warning_enabled', enabled)}
                                    fields={[
                                        {
                                            label: 'Critical coverage threshold (%)',
                                            name: 'coverage_threshold_percentage',
                                            type: 'number',
                                            value: formData.coverage_threshold_percentage,
                                            onChange: (v) => handleRuleChange('coverage_threshold_percentage', v as number),
                                            min: 1,
                                            max: 100,
                                            helpText: 'Require HR Manager approval if department coverage falls below this percentage',
                                        },
                                    ]}
                                    examples={[
                                        'Coverage > 75% → HR Staff approval',
                                        'Coverage 60-75% → HR Manager approval (warning)',
                                        'Coverage < 60% → HR Manager approval (required)',
                                    ]}
                                />

                                {/* Rule 3: Advance Notice Rules */}
                                <ApprovalRuleCard
                                    icon={Calendar}
                                    title="Advance Notice Rules"
                                    description="Route approval based on request timing"
                                    ruleNumber={3}
                                    isEnabled={formData.short_notice_requires_approval}
                                    onToggle={(enabled) => handleRuleChange('short_notice_requires_approval', enabled)}
                                    fields={[
                                        {
                                            label: 'Advance notice required (days)',
                                            name: 'advance_notice_days',
                                            type: 'number',
                                            value: formData.advance_notice_days,
                                            onChange: (v) => handleRuleChange('advance_notice_days', v as number),
                                            min: 0,
                                            max: 90,
                                            helpText: 'Requests with less notice require HR Manager approval',
                                        },
                                    ]}
                                    examples={[
                                        '≥ 3 days notice → HR Staff approval',
                                        '< 3 days notice → HR Manager approval',
                                        'Emergency leave → Always HR Staff approval (urgent)',
                                    ]}
                                />

                                {/* Rule 4: Leave Type Specific Rules */}
                                <ApprovalRuleCard
                                    icon={CheckCircle2}
                                    title="Leave Type Specific Rules"
                                    description="Route approval based on leave category"
                                    ruleNumber={4}
                                    fields={[
                                        {
                                            label: 'Unpaid leave requires HR Manager approval',
                                            name: 'unpaid_leave_requires_manager',
                                            type: 'checkbox',
                                            value: formData.unpaid_leave_requires_manager,
                                            onChange: (v) => handleRuleChange('unpaid_leave_requires_manager', v as boolean),
                                            helpText: 'All unpaid leave requests must be approved by HR Manager',
                                        },
                                        {
                                            label: 'Maternity/Paternity leave requires Office Admin approval',
                                            name: 'maternity_requires_admin',
                                            type: 'checkbox',
                                            value: formData.maternity_requires_admin,
                                            onChange: (v) => handleRuleChange('maternity_requires_admin', v as boolean),
                                            helpText: 'Extended leave types require Office Admin final approval',
                                        },
                                    ]}
                                    examples={[
                                        'Vacation/Sick Leave → Standard approval flow',
                                        'Unpaid Leave → Always HR Manager',
                                        'Maternity/Paternity → HR Manager + Office Admin',
                                    ]}
                                />

                                {/* Rule 5: Balance Threshold Rules */}
                                <ApprovalRuleCard
                                    icon={AlertTriangle}
                                    title="Balance Threshold Rules"
                                    description="Route approval based on remaining leave balance"
                                    ruleNumber={5}
                                    isEnabled={formData.balance_warning_enabled}
                                    onToggle={(enabled) => handleRuleChange('balance_warning_enabled', enabled)}
                                    fields={[
                                        {
                                            label: 'Remaining balance threshold (days)',
                                            name: 'balance_threshold_days',
                                            type: 'number',
                                            value: formData.balance_threshold_days,
                                            onChange: (v) => handleRuleChange('balance_threshold_days', v as number),
                                            min: 0,
                                            max: 30,
                                            helpText: 'Require HR Manager approval if remaining balance falls below this threshold',
                                        },
                                    ]}
                                    examples={[
                                        'Balance > 3 days remaining → HR Staff approval',
                                        'Balance ≤ 3 days remaining → HR Manager approval',
                                        'Insufficient balance → Blocked (cannot submit)',
                                    ]}
                                />

                                {/* Rule 6: Blackout Period Rules */}
                                <ApprovalRuleCard
                                    icon={Ban}
                                    title="Blackout Period Rules"
                                    description="Restrict or require approval during peak business periods"
                                    ruleNumber={6}
                                    isEnabled={formData.blackout_periods_enabled}
                                    onToggle={(enabled) => handleRuleChange('blackout_periods_enabled', enabled)}
                                    fields={[
                                        {
                                            label: 'Blackout Periods',
                                            name: 'blackout_dates',
                                            type: 'blackout-dates',
                                            value: formData.blackout_dates,
                                            onChange: (v) => handleRuleChange('blackout_dates', v as BlackoutDate[]),
                                            helpText: 'Define date ranges when leave requires special approval',
                                        },
                                    ]}
                                    examples={[
                                        'Year-end closing (Dec 15-31) → HR Manager approval required',
                                        'Inventory week → HR Manager approval required',
                                        'Regular periods → Standard approval flow',
                                    ]}
                                />

                                {/* Rule 7: Frequency Limit Rules */}
                                <ApprovalRuleCard
                                    icon={TrendingUp}
                                    title="Frequency Limit Rules"
                                    description="Route approval based on request frequency patterns"
                                    ruleNumber={7}
                                    isEnabled={formData.frequency_limit_enabled}
                                    onToggle={(enabled) => handleRuleChange('frequency_limit_enabled', enabled)}
                                    fields={[
                                        {
                                            label: 'Maximum requests per period',
                                            name: 'frequency_max_requests',
                                            type: 'number',
                                            value: formData.frequency_max_requests,
                                            onChange: (v) => handleRuleChange('frequency_max_requests', v as number),
                                            min: 1,
                                            max: 20,
                                            helpText: 'Require HR Manager approval if employee exceeds this limit',
                                        },
                                        {
                                            label: 'Period duration (days)',
                                            name: 'frequency_period_days',
                                            type: 'number',
                                            value: formData.frequency_period_days,
                                            onChange: (v) => handleRuleChange('frequency_period_days', v as number),
                                            min: 1,
                                            max: 365,
                                            helpText: 'Rolling time window for frequency calculation (e.g., 30 days = monthly)',
                                        },
                                    ]}
                                    examples={[
                                        '≤ 3 requests per month → HR Staff approval',
                                        '> 3 requests per month → HR Manager approval',
                                        'Suspicious pattern detection → Flag for review',
                                    ]}
                                />
                            </CardContent>
                        </Card>

                        {/* Workflow Tester */}
                        <WorkflowTester
                            approvalRules={formData}
                            leaveTypes={leaveTypes}
                        />

                        {/* Save Button (Bottom) */}
                        {hasChanges && (
                            <div className="flex justify-end gap-4">
                                <Button onClick={handleSaveAll} disabled={isSubmitting} size="lg">
                                    <Save className="h-4 w-4 mr-2" />
                                    {isSubmitting ? 'Saving...' : 'Save All Rules'}
                                </Button>
                            </div>
                        )}
                    </TabsContent>

                    {/* Tab 2: Hiring Approval (Placeholder) */}
                    <TabsContent value="hiring">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Briefcase className="h-5 w-5" />
                                    Hiring Approval Workflow
                                    <Badge variant="outline">Coming in Phase 2</Badge>
                                </CardTitle>
                                <CardDescription>
                                    Configure multi-stage approval for hiring decisions
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <Alert>
                                    <GitBranch className="h-4 w-4" />
                                    <AlertDescription>
                                        <strong>Planned for Phase 2:</strong> Hiring approval workflow will enable Office Admin to configure approval stages for new hire requests. This feature is deferred to post-MVP release.
                                    </AlertDescription>
                                </Alert>

                                <div className="space-y-4">
                                    <h3 className="font-semibold text-lg">Expected Approval Flow:</h3>
                                    <div className="border-l-2 border-muted-foreground/20 pl-4 space-y-4">
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-blue-100 dark:bg-blue-950 p-2 mt-0.5">
                                                <span className="text-blue-600 dark:text-blue-400 font-bold text-sm">1</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">HR Staff Screens Applications</p>
                                                <p className="text-sm text-muted-foreground">Initial candidate screening and shortlisting</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-green-100 dark:bg-green-950 p-2 mt-0.5">
                                                <span className="text-green-600 dark:text-green-400 font-bold text-sm">2</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">HR Manager Interviews & Recommends</p>
                                                <p className="text-sm text-muted-foreground">Conduct interview and provide hiring recommendation</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-purple-100 dark:bg-purple-950 p-2 mt-0.5">
                                                <span className="text-purple-600 dark:text-purple-400 font-bold text-sm">3</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Office Admin Final Approval</p>
                                                <p className="text-sm text-muted-foreground">Review salary offer and authorize hire</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-orange-100 dark:bg-orange-950 p-2 mt-0.5">
                                                <span className="text-orange-600 dark:text-orange-400 font-bold text-sm">4</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">HR Staff Processes Onboarding</p>
                                                <p className="text-sm text-muted-foreground">Complete pre-employment requirements and onboarding</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Payroll Approval (Placeholder) */}
                    <TabsContent value="payroll">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <DollarSign className="h-5 w-5" />
                                    Payroll Approval Workflow
                                    <Badge variant="outline">Coming in Phase 2</Badge>
                                </CardTitle>
                                <CardDescription>
                                    Configure approval stages for payroll processing
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <Alert>
                                    <GitBranch className="h-4 w-4" />
                                    <AlertDescription>
                                        <strong>Planned for Phase 2:</strong> Payroll approval workflow will enable Office Admin to review and authorize payroll calculations before payment distribution. This feature is deferred to post-MVP release.
                                    </AlertDescription>
                                </Alert>

                                <div className="space-y-4">
                                    <h3 className="font-semibold text-lg">Expected Approval Flow:</h3>
                                    <div className="border-l-2 border-muted-foreground/20 pl-4 space-y-4">
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-blue-100 dark:bg-blue-950 p-2 mt-0.5">
                                                <span className="text-blue-600 dark:text-blue-400 font-bold text-sm">1</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Payroll Officer Calculates Payroll</p>
                                                <p className="text-sm text-muted-foreground">Process timekeeping data and calculate gross/net pay</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-green-100 dark:bg-green-950 p-2 mt-0.5">
                                                <span className="text-green-600 dark:text-green-400 font-bold text-sm">2</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">HR Manager Reviews</p>
                                                <p className="text-sm text-muted-foreground">Verify calculations and review exceptions</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-purple-100 dark:bg-purple-950 p-2 mt-0.5">
                                                <span className="text-purple-600 dark:text-purple-400 font-bold text-sm">3</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Office Admin Final Approval</p>
                                                <p className="text-sm text-muted-foreground">Authorize payment before distribution</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-orange-100 dark:bg-orange-950 p-2 mt-0.5">
                                                <span className="text-orange-600 dark:text-orange-400 font-bold text-sm">4</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Payroll Officer Distributes Payment</p>
                                                <p className="text-sm text-muted-foreground">Release salaries and generate payslips</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 4: Expense Approval (Placeholder) */}
                    <TabsContent value="expense">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="h-5 w-5" />
                                    Expense Approval Workflow
                                    <Badge variant="outline">Coming in Phase 2</Badge>
                                </CardTitle>
                                <CardDescription>
                                    Configure approval thresholds for expense reimbursements
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <Alert>
                                    <GitBranch className="h-4 w-4" />
                                    <AlertDescription>
                                        <strong>Planned for Phase 2:</strong> Expense approval workflow will enable Office Admin to configure approval thresholds and routing for employee expense reimbursements. This feature is deferred to post-MVP release.
                                    </AlertDescription>
                                </Alert>

                                <div className="space-y-4">
                                    <h3 className="font-semibold text-lg">Expected Approval Flow:</h3>
                                    <div className="border-l-2 border-muted-foreground/20 pl-4 space-y-4">
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-blue-100 dark:bg-blue-950 p-2 mt-0.5">
                                                <span className="text-blue-600 dark:text-blue-400 font-bold text-sm">1</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Employee Submits Expense</p>
                                                <p className="text-sm text-muted-foreground">Submit expense claim with receipts via HR Staff</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-green-100 dark:bg-green-950 p-2 mt-0.5">
                                                <span className="text-green-600 dark:text-green-400 font-bold text-sm">2</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Department Head Approves</p>
                                                <p className="text-sm text-muted-foreground">Verify business justification and amount</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-purple-100 dark:bg-purple-950 p-2 mt-0.5">
                                                <span className="text-purple-600 dark:text-purple-400 font-bold text-sm">3</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Accounting Reviews</p>
                                                <p className="text-sm text-muted-foreground">Validate receipts and coding</p>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-full bg-orange-100 dark:bg-orange-950 p-2 mt-0.5">
                                                <span className="text-orange-600 dark:text-orange-400 font-bold text-sm">4</span>
                                            </div>
                                            <div>
                                                <p className="font-medium">Office Admin Approves (if above threshold)</p>
                                                <p className="text-sm text-muted-foreground">Final approval for expenses exceeding configured amount</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
