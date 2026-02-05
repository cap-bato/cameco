import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { 
    CheckCircle2, 
    Circle, 
    AlertTriangle,
    ArrowRight,
    Building2,
    FileText,
    Users,
    Calendar,
    DollarSign,
    GitBranch
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface SetupStep {
    id: string;
    title: string;
    description: string;
    route: string;
    completed: boolean;
    priority: 'high' | 'medium' | 'low';
}

interface SetupStatus {
    steps: SetupStep[];
    completedCount: number;
    totalSteps: number;
    completionPercentage: number;
    isComplete: boolean;
}

interface SetupChecklistProps {
    setupStatus: SetupStatus;
}

const iconMap: Record<string, typeof Building2> = {
    'company_setup': Building2,
    'business_rules': FileText,
    'departments': Users,
    'leave_policies': Calendar,
    'payroll_rules': DollarSign,
    'system_config': FileText,
    'approval_workflows': GitBranch,
};

const priorityConfig = {
    high: {
        label: 'High Priority',
        variant: 'destructive' as const,
        color: 'text-red-600 dark:text-red-400',
    },
    medium: {
        label: 'Medium Priority',
        variant: 'secondary' as const,
        color: 'text-yellow-600 dark:text-yellow-400',
    },
    low: {
        label: 'Low Priority',
        variant: 'outline' as const,
        color: 'text-blue-600 dark:text-blue-400',
    },
};

export function SetupChecklist({ setupStatus }: SetupChecklistProps) {
    const { steps, completedCount, totalSteps, isComplete } = setupStatus;

    // Group steps by priority
    const highPrioritySteps = steps.filter(step => step.priority === 'high');
    const mediumPrioritySteps = steps.filter(step => step.priority === 'medium');
    const lowPrioritySteps = steps.filter(step => step.priority === 'low');

    const highPriorityComplete = highPrioritySteps.every(step => step.completed);
    const mediumPriorityComplete = mediumPrioritySteps.every(step => step.completed);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between">
                    <span>Setup Checklist</span>
                    {!isComplete && highPriorityComplete && (
                        <Badge variant="secondary" className="text-xs">
                            <AlertTriangle className="mr-1 h-3 w-3" />
                            {totalSteps - completedCount} steps remaining
                        </Badge>
                    )}
                </CardTitle>
                <CardDescription>
                    Complete these configuration steps to set up your company in the system
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* High Priority Steps */}
                {highPrioritySteps.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Badge variant={priorityConfig.high.variant} className="text-xs">
                                {priorityConfig.high.label}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {highPrioritySteps.filter(s => s.completed).length} of {highPrioritySteps.length} completed
                            </span>
                        </div>
                        <div className="space-y-2">
                            {highPrioritySteps.map((step) => (
                                <SetupStepItem key={step.id} step={step} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Medium Priority Steps */}
                {mediumPrioritySteps.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Badge variant={priorityConfig.medium.variant} className="text-xs">
                                {priorityConfig.medium.label}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {mediumPrioritySteps.filter(s => s.completed).length} of {mediumPrioritySteps.length} completed
                            </span>
                        </div>
                        <div className="space-y-2">
                            {mediumPrioritySteps.map((step) => (
                                <SetupStepItem key={step.id} step={step} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Low Priority Steps */}
                {lowPrioritySteps.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <Badge variant={priorityConfig.low.variant} className="text-xs">
                                {priorityConfig.low.label}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {lowPrioritySteps.filter(s => s.completed).length} of {lowPrioritySteps.length} completed
                            </span>
                        </div>
                        <div className="space-y-2">
                            {lowPrioritySteps.map((step) => (
                                <SetupStepItem key={step.id} step={step} />
                            ))}
                        </div>
                    </div>
                )}

                {/* Completion Message */}
                {isComplete && (
                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                        <div className="flex items-start gap-3">
                            <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5" />
                            <div className="flex-1">
                                <h4 className="text-sm font-semibold text-green-900 dark:text-green-100">
                                    Setup Complete!
                                </h4>
                                <p className="mt-1 text-sm text-green-700 dark:text-green-300">
                                    All configuration steps have been completed. Your system is ready to use.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Next Steps Guidance */}
                {!isComplete && (
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <h4 className="text-sm font-semibold mb-2">Next Steps</h4>
                        <p className="text-sm text-muted-foreground">
                            {!highPriorityComplete 
                                ? 'Start with high priority items to set up essential company information and business rules.'
                                : !mediumPriorityComplete
                                ? 'Continue with medium priority items to configure organizational structure and policies.'
                                : 'Complete the remaining low priority items to finish system configuration.'
                            }
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SetupStepItem({ step }: { step: SetupStep }) {
    const Icon = iconMap[step.id] || Building2;

    return (
        <div className={cn(
            "flex items-start gap-3 rounded-lg border p-3 transition-all",
            step.completed 
                ? "border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/20" 
                : "border-border bg-background hover:bg-muted/50"
        )}>
            <div className="mt-0.5">
                {step.completed ? (
                    <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                ) : (
                    <Circle className="h-5 w-5 text-muted-foreground" />
                )}
            </div>
            <div className="flex-1 space-y-1 min-w-0">
                <div className="flex items-center gap-2">
                    <Icon className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <h4 className={cn(
                        "text-sm font-medium",
                        step.completed && "text-green-900 dark:text-green-100"
                    )}>
                        {step.title}
                    </h4>
                </div>
                <p className={cn(
                    "text-xs",
                    step.completed 
                        ? "text-green-700 dark:text-green-300" 
                        : "text-muted-foreground"
                )}>
                    {step.description}
                </p>
            </div>
            <Link href={step.route}>
                <Button 
                    size="sm" 
                    variant={step.completed ? "outline" : "default"}
                    className="flex-shrink-0"
                >
                    {step.completed ? 'Review' : 'Configure'}
                    <ArrowRight className="ml-1 h-3 w-3" />
                </Button>
            </Link>
        </div>
    );
}
