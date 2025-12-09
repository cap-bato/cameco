import { useForm } from '@inertiajs/react';
import { AlertCircle, Clock, DollarSign, UserX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useToast } from '@/hooks/use-toast';

interface AttendancePoliciesFormProps {
    attendanceRules: {
        grace_period_minutes: number;
        late_deduction_type: 'per_minute' | 'per_bracket' | 'fixed';
        late_deduction_amount: number;
        undertime_enabled: boolean;
        undertime_deduction_type: 'proportional' | 'fixed' | 'none';
        absence_with_leave_deduction: number;
        absence_without_leave_deduction: number;
    };
}

export function AttendancePoliciesForm({ attendanceRules }: AttendancePoliciesFormProps) {
    const { toast } = useToast();

    const { data, setData, put, processing, errors } = useForm({
        grace_period_minutes: attendanceRules.grace_period_minutes || 15,
        late_deduction_type: attendanceRules.late_deduction_type || 'per_bracket',
        late_deduction_amount: attendanceRules.late_deduction_amount || 0,
        undertime_enabled: attendanceRules.undertime_enabled !== undefined ? attendanceRules.undertime_enabled : true,
        undertime_deduction_type: attendanceRules.undertime_deduction_type || 'proportional',
        absence_with_leave_deduction: attendanceRules.absence_with_leave_deduction !== undefined ? attendanceRules.absence_with_leave_deduction : 0,
        absence_without_leave_deduction: attendanceRules.absence_without_leave_deduction !== undefined ? attendanceRules.absence_without_leave_deduction : 1,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put('/admin/business-rules/attendance', {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Attendance Policies Updated',
                    description: 'Attendance configuration has been saved successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.message || 'Failed to update attendance policies',
                    variant: 'destructive',
                });
            },
        });
    };

    // Calculate example deduction
    const calculateExampleDeduction = () => {
        const lateMinutes = 30; // Example: 30 minutes late

        if (data.late_deduction_type === 'per_minute') {
            return `₱${(data.late_deduction_amount * lateMinutes).toFixed(2)} for ${lateMinutes} min late`;
        } else if (data.late_deduction_type === 'per_bracket') {
            const brackets = Math.ceil(lateMinutes / 15); // 15-min brackets
            return `₱${(data.late_deduction_amount * brackets).toFixed(2)} for ${lateMinutes} min (${brackets} brackets)`;
        } else {
            return `₱${data.late_deduction_amount.toFixed(2)} flat rate regardless of duration`;
        }
    };

    return (
        <Card className="p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header */}
                <div>
                    <h3 className="text-lg font-semibold">Attendance Policies Configuration</h3>
                    <p className="text-sm text-muted-foreground">
                        Define grace period, late deductions, undertime rules, and absence handling
                    </p>
                </div>

                {/* Grace Period */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <Clock className="h-4 w-4" />
                        Grace Period
                    </h4>
                    <div className="space-y-2">
                        <Label htmlFor="grace_period_minutes">
                            Grace Period (minutes)
                            <span className="text-destructive ml-1">*</span>
                        </Label>
                        <div className="flex items-center gap-2">
                            <Input
                                id="grace_period_minutes"
                                type="number"
                                min="0"
                                max="60"
                                step="1"
                                value={data.grace_period_minutes}
                                onChange={(e) => setData('grace_period_minutes', parseFloat(e.target.value))}
                                className={`w-32 ${errors.grace_period_minutes ? 'border-destructive' : ''}`}
                            />
                            <span className="text-sm text-muted-foreground">minutes</span>
                        </div>
                        {errors.grace_period_minutes && (
                            <p className="text-sm text-destructive">{errors.grace_period_minutes}</p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Employees arriving within this period are not marked as late
                        </p>
                    </div>
                </div>

                {/* Late Deduction Rules */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <DollarSign className="h-4 w-4" />
                        Late Deduction Rules
                    </h4>

                    <div className="grid gap-4 md:grid-cols-2">
                        {/* Deduction Type */}
                        <div className="space-y-2">
                            <Label htmlFor="late_deduction_type">
                                Deduction Type
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <Select
                                value={data.late_deduction_type}
                                onValueChange={(value) => setData('late_deduction_type', value as 'per_minute' | 'per_bracket' | 'fixed')}
                            >
                                <SelectTrigger className={errors.late_deduction_type ? 'border-destructive' : ''}>
                                    <SelectValue placeholder="Select deduction type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="per_minute">Per Minute</SelectItem>
                                    <SelectItem value="per_bracket">Per Bracket (15 min)</SelectItem>
                                    <SelectItem value="fixed">Fixed Amount</SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.late_deduction_type && (
                                <p className="text-sm text-destructive">{errors.late_deduction_type}</p>
                            )}
                        </div>

                        {/* Deduction Amount */}
                        <div className="space-y-2">
                            <Label htmlFor="late_deduction_amount">
                                Deduction Amount (₱)
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">₱</span>
                                <Input
                                    id="late_deduction_amount"
                                    type="number"
                                    min="0"
                                    max="1000"
                                    step="0.01"
                                    value={data.late_deduction_amount}
                                    onChange={(e) => setData('late_deduction_amount', parseFloat(e.target.value))}
                                    className={`w-32 ${errors.late_deduction_amount ? 'border-destructive' : ''}`}
                                />
                                {data.late_deduction_type === 'per_minute' && (
                                    <span className="text-sm text-muted-foreground">per minute</span>
                                )}
                                {data.late_deduction_type === 'per_bracket' && (
                                    <span className="text-sm text-muted-foreground">per 15-min bracket</span>
                                )}
                                {data.late_deduction_type === 'fixed' && (
                                    <span className="text-sm text-muted-foreground">flat rate</span>
                                )}
                            </div>
                            {errors.late_deduction_amount && (
                                <p className="text-sm text-destructive">{errors.late_deduction_amount}</p>
                            )}
                        </div>
                    </div>

                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <p className="font-medium mb-1">Example: Employee 30 minutes late</p>
                            <p className="text-sm">{calculateExampleDeduction()}</p>
                        </AlertDescription>
                    </Alert>
                </div>

                {/* Undertime Policy */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold">Undertime Policy</h4>

                    <div className="space-y-4">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="undertime_enabled"
                                checked={data.undertime_enabled}
                                onCheckedChange={(checked) => setData('undertime_enabled', checked as boolean)}
                            />
                            <Label
                                htmlFor="undertime_enabled"
                                className="text-sm font-normal cursor-pointer"
                            >
                                Enable undertime tracking and deductions
                            </Label>
                        </div>

                        {data.undertime_enabled && (
                            <div className="space-y-2 pl-6">
                                <Label htmlFor="undertime_deduction_type">
                                    Undertime Deduction Type
                                </Label>
                                <Select
                                    value={data.undertime_deduction_type}
                                    onValueChange={(value) => setData('undertime_deduction_type', value as 'proportional' | 'fixed' | 'none')}
                                >
                                    <SelectTrigger className={errors.undertime_deduction_type ? 'border-destructive' : ''}>
                                        <SelectValue placeholder="Select deduction type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="proportional">Proportional (deduct actual hours)</SelectItem>
                                        <SelectItem value="fixed">Fixed Amount</SelectItem>
                                        <SelectItem value="none">No Deduction (tracking only)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.undertime_deduction_type && (
                                    <p className="text-sm text-destructive">{errors.undertime_deduction_type}</p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {data.undertime_deduction_type === 'proportional' && 'Deduct pay based on actual hours missed'}
                                    {data.undertime_deduction_type === 'fixed' && 'Deduct fixed amount per undertime occurrence'}
                                    {data.undertime_deduction_type === 'none' && 'Track undertime but do not deduct pay'}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Absence Handling */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <UserX className="h-4 w-4" />
                        Absence Handling
                    </h4>

                    <div className="grid gap-4 md:grid-cols-2">
                        {/* With Approved Leave */}
                        <div className="space-y-2">
                            <Label htmlFor="absence_with_leave_deduction">
                                Absence With Approved Leave
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="absence_with_leave_deduction"
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    value={data.absence_with_leave_deduction}
                                    onChange={(e) => setData('absence_with_leave_deduction', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.absence_with_leave_deduction ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">× daily rate</span>
                            </div>
                            {errors.absence_with_leave_deduction && (
                                <p className="text-sm text-destructive">{errors.absence_with_leave_deduction}</p>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Standard: 0 (no deduction with approved leave)
                            </p>
                        </div>

                        {/* Without Approved Leave */}
                        <div className="space-y-2">
                            <Label htmlFor="absence_without_leave_deduction">
                                Absence Without Leave
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="absence_without_leave_deduction"
                                    type="number"
                                    min="0"
                                    max="1"
                                    step="0.01"
                                    value={data.absence_without_leave_deduction}
                                    onChange={(e) => setData('absence_without_leave_deduction', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.absence_without_leave_deduction ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">× daily rate</span>
                            </div>
                            {errors.absence_without_leave_deduction && (
                                <p className="text-sm text-destructive">{errors.absence_without_leave_deduction}</p>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Standard: 1.0 (full day deduction for unauthorized absence)
                            </p>
                        </div>
                    </div>

                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <p className="font-medium mb-2">Absence Deduction Examples (₱500/day):</p>
                            <ul className="text-sm space-y-1">
                                <li>• With approved leave (0): ₱{(500 * data.absence_with_leave_deduction).toFixed(2)} deduction</li>
                                <li>• Without leave (1.0): ₱{(500 * data.absence_without_leave_deduction).toFixed(2)} deduction</li>
                            </ul>
                        </AlertDescription>
                    </Alert>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-end gap-2 pt-4 border-t">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Save Attendance Policies'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}
