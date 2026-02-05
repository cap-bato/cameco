import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Play, CheckCircle2, AlertTriangle, Users, TrendingUp, Clock, Calendar } from 'lucide-react';

interface BlackoutDate {
    start: string;
    end: string;
    reason: string;
}

interface ApprovalRules {
    duration_threshold_days: number;
    duration_tier2_days: number;
    balance_threshold_days: number;
    balance_warning_enabled: boolean;
    advance_notice_days: number;
    short_notice_requires_approval: boolean;
    coverage_threshold_percentage: number;
    coverage_warning_enabled: boolean;
    unpaid_leave_requires_manager: boolean;
    maternity_requires_admin: boolean;
    blackout_periods_enabled: boolean;
    blackout_dates: BlackoutDate[];
    frequency_limit_enabled: boolean;
    frequency_max_requests: number;
    frequency_period_days: number;
}

interface LeaveType {
    code: string;
    name: string;
    is_paid: boolean;
}

interface TestScenario {
    leaveType: string;
    duration: number;
    coverageImpact: number;
    advanceNotice: number;
    balanceAfter: number;
}

interface TestResult {
    scenario: TestScenario;
    approvalTier: 'hr_staff' | 'hr_manager' | 'office_admin';
    approvalLabel: string;
    escalationReasons: string[];
    warnings: string[];
    autoApprove: boolean;
}

interface WorkflowTesterProps {
    approvalRules: ApprovalRules;
    leaveTypes: LeaveType[];
}

export function WorkflowTester({ approvalRules, leaveTypes }: WorkflowTesterProps) {
    const [testScenario, setTestScenario] = useState<TestScenario>({
        leaveType: leaveTypes[0]?.code || 'VL',
        duration: 1,
        coverageImpact: 90,
        advanceNotice: 7,
        balanceAfter: 10,
    });

    const [testResult, setTestResult] = useState<TestResult | null>(null);
    const [testHistory, setTestHistory] = useState<TestResult[]>([]);

    const runTest = () => {
        const escalationReasons: string[] = [];
        const warnings: string[] = [];
        let approvalTier: 'hr_staff' | 'hr_manager' | 'office_admin' = 'hr_staff';

        // Find selected leave type
        const selectedLeave = leaveTypes.find(lt => lt.code === testScenario.leaveType);

        // Rule 1: Duration-based escalation
        if (testScenario.duration >= approvalRules.duration_tier2_days) {
            approvalTier = 'office_admin';
            escalationReasons.push(`Duration (${testScenario.duration} days) exceeds Office Admin threshold (${approvalRules.duration_tier2_days} days)`);
        } else if (testScenario.duration >= approvalRules.duration_threshold_days) {
            approvalTier = 'hr_manager';
            escalationReasons.push(`Duration (${testScenario.duration} days) exceeds HR Manager threshold (${approvalRules.duration_threshold_days} days)`);
        }

        // Rule 2: Balance threshold warning
        if (approvalRules.balance_warning_enabled && testScenario.balanceAfter < approvalRules.balance_threshold_days) {
            warnings.push(`⚠️ Balance after leave (${testScenario.balanceAfter} days) is below warning threshold (${approvalRules.balance_threshold_days} days)`);
            if (approvalTier === 'hr_staff') {
                approvalTier = 'hr_manager';
                escalationReasons.push('Low balance requires manager approval');
            }
        }

        // Rule 3: Advance notice
        if (approvalRules.short_notice_requires_approval && testScenario.advanceNotice < approvalRules.advance_notice_days) {
            if (approvalTier === 'hr_staff') {
                approvalTier = 'hr_manager';
                escalationReasons.push(`Short notice (${testScenario.advanceNotice} days) requires manager approval (minimum ${approvalRules.advance_notice_days} days)`);
            } else {
                warnings.push(`⚠️ Advance notice (${testScenario.advanceNotice} days) is less than required (${approvalRules.advance_notice_days} days)`);
            }
        }

        // Rule 4: Workforce coverage
        if (approvalRules.coverage_warning_enabled && testScenario.coverageImpact < approvalRules.coverage_threshold_percentage) {
            warnings.push(`⚠️ Coverage impact (${testScenario.coverageImpact}%) is below threshold (${approvalRules.coverage_threshold_percentage}%)`);
            if (approvalTier === 'hr_staff') {
                approvalTier = 'hr_manager';
                escalationReasons.push(`Workforce coverage (${testScenario.coverageImpact}%) below minimum (${approvalRules.coverage_threshold_percentage}%)`);
            }
        }

        // Rule 5: Leave type specific
        if (selectedLeave && !selectedLeave.is_paid && approvalRules.unpaid_leave_requires_manager) {
            if (approvalTier === 'hr_staff') {
                approvalTier = 'hr_manager';
                escalationReasons.push('Unpaid leave requires manager approval');
            }
        }

        if (testScenario.leaveType === 'MATERNITY' && approvalRules.maternity_requires_admin) {
            approvalTier = 'office_admin';
            escalationReasons.push('Maternity leave requires Office Admin approval');
        }

        // Rule 6: Blackout periods (simplified - would need actual date checking)
        if (approvalRules.blackout_periods_enabled && approvalRules.blackout_dates.length > 0) {
            warnings.push('⚠️ Check blackout period calendar before approval');
        }

        // Determine approval label
        let approvalLabel = '';
        let autoApprove = false;

        if (approvalTier === 'hr_staff' && escalationReasons.length === 0) {
            autoApprove = true;
            approvalLabel = 'Auto-approved by HR Staff';
        } else if (approvalTier === 'hr_staff') {
            approvalLabel = 'Approved by HR Staff';
        } else if (approvalTier === 'hr_manager') {
            approvalLabel = 'Requires HR Manager Approval';
        } else {
            approvalLabel = 'Requires Office Admin Approval';
        }

        const result: TestResult = {
            scenario: { ...testScenario },
            approvalTier,
            approvalLabel,
            escalationReasons,
            warnings,
            autoApprove,
        };

        setTestResult(result);
        setTestHistory(prev => [result, ...prev.slice(0, 4)]); // Keep last 5 tests
    };

    const getApprovalBadgeColor = (tier: string) => {
        if (tier === 'hr_staff') return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (tier === 'hr_manager') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Test Approval Workflow</CardTitle>
                    <CardDescription>
                        Simulate a leave request to see how it will be routed based on your configured approval rules.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Input Fields */}
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="leaveType">Leave Type</Label>
                            <Select
                                value={testScenario.leaveType}
                                onValueChange={(value) => setTestScenario({ ...testScenario, leaveType: value })}
                            >
                                <SelectTrigger id="leaveType">
                                    <SelectValue placeholder="Select leave type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {leaveTypes.map(lt => (
                                        <SelectItem key={lt.code} value={lt.code}>
                                            {lt.name} ({lt.code}) {!lt.is_paid && '- Unpaid'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="duration">Duration (days)</Label>
                            <Input
                                id="duration"
                                type="number"
                                min={1}
                                max={365}
                                value={testScenario.duration}
                                onChange={(e) => setTestScenario({ ...testScenario, duration: parseInt(e.target.value) || 1 })}
                            />
                            <p className="text-xs text-muted-foreground">
                                HR Manager: {approvalRules.duration_threshold_days}+ days | Office Admin: {approvalRules.duration_tier2_days}+ days
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="coverageImpact">Workforce Coverage Impact (%)</Label>
                            <Input
                                id="coverageImpact"
                                type="number"
                                min={0}
                                max={100}
                                value={testScenario.coverageImpact}
                                onChange={(e) => setTestScenario({ ...testScenario, coverageImpact: parseInt(e.target.value) || 0 })}
                            />
                            <p className="text-xs text-muted-foreground">
                                Minimum threshold: {approvalRules.coverage_threshold_percentage}%
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="advanceNotice">Advance Notice (days)</Label>
                            <Input
                                id="advanceNotice"
                                type="number"
                                min={0}
                                max={90}
                                value={testScenario.advanceNotice}
                                onChange={(e) => setTestScenario({ ...testScenario, advanceNotice: parseInt(e.target.value) || 0 })}
                            />
                            <p className="text-xs text-muted-foreground">
                                Required: {approvalRules.advance_notice_days} days minimum
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="balanceAfter">Balance After Leave (%)</Label>
                            <Input
                                id="balanceAfter"
                                type="number"
                                min={0}
                                max={100}
                                value={testScenario.balanceAfter}
                                onChange={(e) => setTestScenario({ ...testScenario, balanceAfter: parseInt(e.target.value) || 0 })}
                            />
                            <p className="text-xs text-muted-foreground">
                                Warning threshold: {approvalRules.balance_threshold_days} days
                            </p>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button onClick={runTest} className="gap-2">
                            <Play className="h-4 w-4" />
                            Run Test
                        </Button>
                    </div>

                    {/* Test Result */}
                    {testResult && (
                        <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
                            <div className="flex items-start justify-between">
                                <div className="space-y-1">
                                    <h4 className="font-semibold">Test Result</h4>
                                    <p className="text-sm text-muted-foreground">
                                        {testResult.scenario.leaveType} - {testResult.scenario.duration} days
                                    </p>
                                </div>
                                <Badge className={getApprovalBadgeColor(testResult.approvalTier)}>
                                    {testResult.autoApprove && <CheckCircle2 className="mr-1 h-3 w-3" />}
                                    {testResult.approvalLabel}
                                </Badge>
                            </div>

                            {testResult.escalationReasons.length > 0 && (
                                <Alert>
                                    <TrendingUp className="h-4 w-4" />
                                    <AlertDescription>
                                        <div className="font-semibold">Escalation Reasons:</div>
                                        <ul className="mt-2 list-inside list-disc space-y-1 text-sm">
                                            {testResult.escalationReasons.map((reason, idx) => (
                                                <li key={idx}>{reason}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}

                            {testResult.warnings.length > 0 && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        <div className="font-semibold">Warnings:</div>
                                        <ul className="mt-2 list-inside list-disc space-y-1 text-sm">
                                            {testResult.warnings.map((warning, idx) => (
                                                <li key={idx}>{warning}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}

                            {testResult.escalationReasons.length === 0 && testResult.warnings.length === 0 && (
                                <Alert>
                                    <CheckCircle2 className="h-4 w-4" />
                                    <AlertDescription>
                                        ✅ No escalation triggers or warnings. This request meets all approval criteria.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Test History */}
            {testHistory.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Test History</CardTitle>
                        <CardDescription>Recent workflow test results</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {testHistory.map((result, idx) => (
                                <div 
                                    key={idx}
                                    className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">{result.scenario.leaveType}</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span>{result.scenario.duration} days</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <span>{result.scenario.coverageImpact}%</span>
                                        </div>
                                    </div>
                                    <Badge className={getApprovalBadgeColor(result.approvalTier)}>
                                        {result.approvalTier === 'hr_staff' && 'HR Staff'}
                                        {result.approvalTier === 'hr_manager' && 'HR Manager'}
                                        {result.approvalTier === 'office_admin' && 'Office Admin'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
