import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Calendar, Plus, Trash2, LucideIcon } from 'lucide-react';

interface BlackoutDate {
    start: string;
    end: string;
    reason: string;
}

interface RuleField {
    label: string;
    name: string;
    type: 'number' | 'checkbox' | 'blackout-dates';
    value: number | boolean | BlackoutDate[];
    onChange: (value: number | boolean | BlackoutDate[]) => void;
    min?: number;
    max?: number;
    helpText?: string;
}

interface ApprovalRuleCardProps {
    icon: LucideIcon;
    title: string;
    description: string;
    ruleNumber: number;
    isEnabled?: boolean;
    onToggle?: (enabled: boolean) => void;
    fields: RuleField[];
    examples: string[];
}

export function ApprovalRuleCard({
    icon: Icon,
    title,
    description,
    ruleNumber,
    isEnabled = true,
    onToggle,
    fields,
    examples,
}: ApprovalRuleCardProps) {
    const handleAddBlackoutDate = () => {
        const newDate: BlackoutDate = {
            start: new Date().toISOString().split('T')[0],
            end: new Date().toISOString().split('T')[0],
            reason: '',
        };
        
        const blackoutField = fields.find(f => f.type === 'blackout-dates');
        if (blackoutField) {
            const currentDates = (blackoutField.value as BlackoutDate[]) || [];
            blackoutField.onChange([...currentDates, newDate]);
        }
    };

    const handleRemoveBlackoutDate = (index: number) => {
        const blackoutField = fields.find(f => f.type === 'blackout-dates');
        if (blackoutField) {
            const currentDates = [...(blackoutField.value as BlackoutDate[])];
            currentDates.splice(index, 1);
            blackoutField.onChange(currentDates);
        }
    };

    const handleBlackoutDateChange = (index: number, field: keyof BlackoutDate, value: string) => {
        const blackoutField = fields.find(f => f.type === 'blackout-dates');
        if (blackoutField) {
            const currentDates = [...(blackoutField.value as BlackoutDate[])];
            currentDates[index] = { ...currentDates[index], [field]: value };
            blackoutField.onChange(currentDates);
        }
    };

    const renderField = (field: RuleField) => {
        switch (field.type) {
            case 'number':
                return (
                    <div key={field.name} className="space-y-2">
                        <Label htmlFor={field.name}>{field.label}</Label>
                        <Input
                            id={field.name}
                            type="number"
                            value={field.value as number}
                            onChange={(e) => field.onChange(parseFloat(e.target.value) || 0)}
                            min={field.min}
                            max={field.max}
                            disabled={onToggle && !isEnabled}
                        />
                        {field.helpText && (
                            <p className="text-xs text-muted-foreground">{field.helpText}</p>
                        )}
                    </div>
                );

            case 'checkbox':
                return (
                    <div key={field.name} className="flex items-start space-x-2 py-2">
                        <Checkbox
                            id={field.name}
                            checked={field.value as boolean}
                            onCheckedChange={(checked) => field.onChange(checked as boolean)}
                            disabled={onToggle && !isEnabled}
                        />
                        <div className="grid gap-1.5 leading-none">
                            <Label
                                htmlFor={field.name}
                                className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                            >
                                {field.label}
                            </Label>
                            {field.helpText && (
                                <p className="text-xs text-muted-foreground">{field.helpText}</p>
                            )}
                        </div>
                    </div>
                );

            case 'blackout-dates': {
                const dates = (field.value as BlackoutDate[]) || [];
                return (
                    <div key={field.name} className="space-y-4">
                        <div className="flex items-center justify-between">
                            <Label>{field.label}</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleAddBlackoutDate}
                                disabled={onToggle && !isEnabled}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                Add Period
                            </Button>
                        </div>
                        
                        {dates.length === 0 ? (
                            <Alert>
                                <Calendar className="h-4 w-4" />
                                <AlertDescription>
                                    No blackout periods configured. Click "Add Period" to define restricted dates.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <div className="space-y-3">
                                {dates.map((date: BlackoutDate, index: number) => (
                                    <Card key={index} className="p-4">
                                        <div className="grid gap-3">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-2">
                                                    <Label htmlFor={`start-${index}`} className="text-xs">
                                                        Start Date
                                                    </Label>
                                                    <Input
                                                        id={`start-${index}`}
                                                        type="date"
                                                        value={date.start}
                                                        onChange={(e) => handleBlackoutDateChange(index, 'start', e.target.value)}
                                                        disabled={onToggle && !isEnabled}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`end-${index}`} className="text-xs">
                                                        End Date
                                                    </Label>
                                                    <Input
                                                        id={`end-${index}`}
                                                        type="date"
                                                        value={date.end}
                                                        onChange={(e) => handleBlackoutDateChange(index, 'end', e.target.value)}
                                                        disabled={onToggle && !isEnabled}
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor={`reason-${index}`} className="text-xs">
                                                    Reason
                                                </Label>
                                                <Input
                                                    id={`reason-${index}`}
                                                    placeholder="e.g., Peak season, Year-end closing"
                                                    value={date.reason}
                                                    onChange={(e) => handleBlackoutDateChange(index, 'reason', e.target.value)}
                                                    disabled={onToggle && !isEnabled}
                                                />
                                            </div>
                                            <div className="flex justify-end">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRemoveBlackoutDate(index)}
                                                    disabled={onToggle && !isEnabled}
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Remove
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}
                        
                        {field.helpText && (
                            <p className="text-xs text-muted-foreground">{field.helpText}</p>
                        )}
                    </div>
                );
            }

            default:
                return null;
        }
    };

    return (
        <Card className={!isEnabled && onToggle ? 'opacity-60' : ''}>
            <CardHeader>
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3 flex-1">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <Icon className="h-5 w-5 text-primary" />
                        </div>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <CardTitle className="text-lg">{title}</CardTitle>
                                <Badge variant="outline">Rule #{ruleNumber}</Badge>
                            </div>
                            <CardDescription className="mt-1">{description}</CardDescription>
                        </div>
                    </div>
                    {onToggle && (
                        <div className="flex items-center space-x-2">
                            <Label htmlFor={`toggle-${ruleNumber}`} className="text-sm">
                                {isEnabled ? 'Enabled' : 'Disabled'}
                            </Label>
                            <Switch
                                id={`toggle-${ruleNumber}`}
                                checked={isEnabled}
                                onCheckedChange={onToggle}
                            />
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Rule Fields */}
                <div className="space-y-4">
                    <h4 className="text-sm font-semibold">Configuration</h4>
                    {fields.map(renderField)}
                </div>

                {/* Examples */}
                <div className="space-y-2">
                    <h4 className="text-sm font-semibold">How It Works</h4>
                    <ul className="space-y-1">
                        {examples.map((example, index) => (
                            <li key={index} className="text-sm text-muted-foreground flex items-start gap-2">
                                <span className="text-primary mt-1">•</span>
                                <span>{example}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Status Indicator */}
                {onToggle && (
                    <div className={`p-3 rounded-lg ${isEnabled ? 'bg-green-50 dark:bg-green-950/20' : 'bg-gray-50 dark:bg-gray-950/20'}`}>
                        <p className="text-xs font-medium">
                            {isEnabled ? (
                                <span className="text-green-700 dark:text-green-400">
                                    ✓ This rule is active and will be applied to leave requests
                                </span>
                            ) : (
                                <span className="text-gray-600 dark:text-gray-400">
                                    ○ This rule is disabled and will not affect leave routing
                                </span>
                            )}
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
