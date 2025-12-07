import { useForm } from '@inertiajs/react';
import { AlertCircle, Clock, DollarSign } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useToast } from '@/hooks/use-toast';

interface OvertimeRulesFormProps {
    overtimeRules: {
        threshold_hours: number;
        rate_regular: number;
        rate_holiday: number;
        rate_rest_day: number;
        max_hours_per_day: number;
        max_hours_per_week: number;
        auto_approve_threshold: number;
        requires_approval: boolean;
    };
}

export function OvertimeRulesForm({ overtimeRules }: OvertimeRulesFormProps) {
    const { toast } = useToast();

    const { data, setData, put, processing, errors } = useForm({
        threshold_hours: overtimeRules.threshold_hours || 8,
        rate_regular: overtimeRules.rate_regular || 1.25,
        rate_holiday: overtimeRules.rate_holiday || 2.0,
        rate_rest_day: overtimeRules.rate_rest_day || 1.3,
        max_hours_per_day: overtimeRules.max_hours_per_day || 4,
        max_hours_per_week: overtimeRules.max_hours_per_week || 20,
        auto_approve_threshold: overtimeRules.auto_approve_threshold || 2,
        requires_approval: overtimeRules.requires_approval !== undefined ? overtimeRules.requires_approval : true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put('/admin/business-rules/overtime', {
            preserveScroll: true,
            onSuccess: () => {
                toast({
                    title: 'Overtime Rules Updated',
                    description: 'Overtime configuration has been saved successfully.',
                });
            },
            onError: (errors) => {
                toast({
                    title: 'Error',
                    description: errors.message || 'Failed to update overtime rules',
                    variant: 'destructive',
                });
            },
        });
    };

    // Calculate sample overtime pay
    const calculateSamplePay = () => {
        const baseHourlyRate = 100; // Sample base rate
        const overtimeHours = 2;
        
        const regularOT = baseHourlyRate * data.rate_regular * overtimeHours;
        const holidayOT = baseHourlyRate * data.rate_holiday * overtimeHours;
        const restDayOT = baseHourlyRate * data.rate_rest_day * overtimeHours;

        return {
            regular: regularOT.toFixed(2),
            holiday: holidayOT.toFixed(2),
            restDay: restDayOT.toFixed(2),
        };
    };

    const samplePay = calculateSamplePay();

    return (
        <Card className="p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header */}
                <div>
                    <h3 className="text-lg font-semibold">Overtime Rules Configuration</h3>
                    <p className="text-sm text-muted-foreground">
                        Define overtime pay rates, limits, and approval requirements
                    </p>
                </div>

                {/* Overtime Threshold */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <Clock className="h-4 w-4" />
                        Overtime Threshold
                    </h4>
                    <div className="space-y-2">
                        <Label htmlFor="threshold_hours">
                            Regular Working Hours (before OT)
                            <span className="text-destructive ml-1">*</span>
                        </Label>
                        <div className="flex items-center gap-2">
                            <Input
                                id="threshold_hours"
                                type="number"
                                min="0"
                                max="24"
                                step="0.5"
                                value={data.threshold_hours}
                                onChange={(e) => setData('threshold_hours', parseFloat(e.target.value))}
                                className={`w-32 ${errors.threshold_hours ? 'border-destructive' : ''}`}
                            />
                            <span className="text-sm text-muted-foreground">hours per day</span>
                        </div>
                        {errors.threshold_hours && (
                            <p className="text-sm text-destructive">{errors.threshold_hours}</p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Hours worked beyond this will be considered overtime
                        </p>
                    </div>
                </div>

                {/* Rate Multipliers */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <DollarSign className="h-4 w-4" />
                        Overtime Rate Multipliers
                    </h4>
                    
                    <div className="grid gap-4 md:grid-cols-3">
                        {/* Regular Day OT */}
                        <div className="space-y-2">
                            <Label htmlFor="rate_regular">
                                Regular Day OT
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="rate_regular"
                                    type="number"
                                    min="1.0"
                                    max="5.0"
                                    step="0.05"
                                    value={data.rate_regular}
                                    onChange={(e) => setData('rate_regular', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.rate_regular ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">x base rate</span>
                            </div>
                            {errors.rate_regular && (
                                <p className="text-sm text-destructive">{errors.rate_regular}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Standard: 1.25x (Philippine Labor Code)
                            </p>
                        </div>

                        {/* Holiday OT */}
                        <div className="space-y-2">
                            <Label htmlFor="rate_holiday">
                                Holiday OT
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="rate_holiday"
                                    type="number"
                                    min="1.0"
                                    max="5.0"
                                    step="0.05"
                                    value={data.rate_holiday}
                                    onChange={(e) => setData('rate_holiday', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.rate_holiday ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">x base rate</span>
                            </div>
                            {errors.rate_holiday && (
                                <p className="text-sm text-destructive">{errors.rate_holiday}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Standard: 2.0x (Philippine Labor Code)
                            </p>
                        </div>

                        {/* Rest Day OT */}
                        <div className="space-y-2">
                            <Label htmlFor="rate_rest_day">
                                Rest Day OT
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="rate_rest_day"
                                    type="number"
                                    min="1.0"
                                    max="5.0"
                                    step="0.05"
                                    value={data.rate_rest_day}
                                    onChange={(e) => setData('rate_rest_day', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.rate_rest_day ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">x base rate</span>
                            </div>
                            {errors.rate_rest_day && (
                                <p className="text-sm text-destructive">{errors.rate_rest_day}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Standard: 1.3x (Philippine Labor Code)
                            </p>
                        </div>
                    </div>
                </div>

                {/* Maximum Limits */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold flex items-center gap-2">
                        <AlertCircle className="h-4 w-4" />
                        Maximum Overtime Limits
                    </h4>
                    
                    <div className="grid gap-4 md:grid-cols-2">
                        {/* Max per Day */}
                        <div className="space-y-2">
                            <Label htmlFor="max_hours_per_day">
                                Maximum OT Hours per Day
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="max_hours_per_day"
                                    type="number"
                                    min="0"
                                    max="12"
                                    step="0.5"
                                    value={data.max_hours_per_day}
                                    onChange={(e) => setData('max_hours_per_day', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.max_hours_per_day ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">hours</span>
                            </div>
                            {errors.max_hours_per_day && (
                                <p className="text-sm text-destructive">{errors.max_hours_per_day}</p>
                            )}
                        </div>

                        {/* Max per Week */}
                        <div className="space-y-2">
                            <Label htmlFor="max_hours_per_week">
                                Maximum OT Hours per Week
                                <span className="text-destructive ml-1">*</span>
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="max_hours_per_week"
                                    type="number"
                                    min="0"
                                    max="60"
                                    step="1"
                                    value={data.max_hours_per_week}
                                    onChange={(e) => setData('max_hours_per_week', parseFloat(e.target.value))}
                                    className={`w-24 ${errors.max_hours_per_week ? 'border-destructive' : ''}`}
                                />
                                <span className="text-sm text-muted-foreground">hours</span>
                            </div>
                            {errors.max_hours_per_week && (
                                <p className="text-sm text-destructive">{errors.max_hours_per_week}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Approval Settings */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold">Approval Requirements</h4>
                    
                    <div className="space-y-4">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="requires_approval"
                                checked={data.requires_approval}
                                onCheckedChange={(checked) => setData('requires_approval', checked as boolean)}
                            />
                            <Label
                                htmlFor="requires_approval"
                                className="text-sm font-normal cursor-pointer"
                            >
                                Require supervisor approval for overtime requests
                            </Label>
                        </div>

                        {data.requires_approval && (
                            <div className="space-y-2 pl-6">
                                <Label htmlFor="auto_approve_threshold">
                                    Auto-approve threshold
                                </Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="auto_approve_threshold"
                                        type="number"
                                        min="0"
                                        max={data.max_hours_per_day}
                                        step="0.5"
                                        value={data.auto_approve_threshold}
                                        onChange={(e) => setData('auto_approve_threshold', parseFloat(e.target.value))}
                                        className={`w-24 ${errors.auto_approve_threshold ? 'border-destructive' : ''}`}
                                    />
                                    <span className="text-sm text-muted-foreground">hours or less</span>
                                </div>
                                {errors.auto_approve_threshold && (
                                    <p className="text-sm text-destructive">{errors.auto_approve_threshold}</p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    OT requests at or below this threshold will be auto-approved
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Sample Calculation */}
                <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        <p className="font-medium mb-2">Sample Calculation (₱100/hour base rate, 2 hours OT):</p>
                        <ul className="space-y-1 text-sm">
                            <li>• Regular Day OT: ₱{samplePay.regular} ({data.rate_regular}x)</li>
                            <li>• Holiday OT: ₱{samplePay.holiday} ({data.rate_holiday}x)</li>
                            <li>• Rest Day OT: ₱{samplePay.restDay} ({data.rate_rest_day}x)</li>
                        </ul>
                    </AlertDescription>
                </Alert>

                {/* Action Buttons */}
                <div className="flex justify-end gap-2 pt-4 border-t">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Save Overtime Rules'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}
