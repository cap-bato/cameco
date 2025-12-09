import { useState } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Save, AlertCircle, Clock } from 'lucide-react';

export interface WorkingHoursData {
    regular_start: string;
    regular_end: string;
    break_duration: number;
    break_start: string;
    work_days: string[];
    shift_enabled: boolean;
}

interface WorkingHoursFormProps {
    initialData: WorkingHoursData;
    onSubmit: (data: WorkingHoursData) => void;
    isSubmitting?: boolean;
}

interface ValidationErrors {
    [key: string]: string;
}

const DAYS_OF_WEEK = [
    { value: 'monday', label: 'Monday', short: 'Mon' },
    { value: 'tuesday', label: 'Tuesday', short: 'Tue' },
    { value: 'wednesday', label: 'Wednesday', short: 'Wed' },
    { value: 'thursday', label: 'Thursday', short: 'Thu' },
    { value: 'friday', label: 'Friday', short: 'Fri' },
    { value: 'saturday', label: 'Saturday', short: 'Sat' },
    { value: 'sunday', label: 'Sunday', short: 'Sun' },
];

export function WorkingHoursForm({ 
    initialData, 
    onSubmit, 
    isSubmitting = false 
}: WorkingHoursFormProps) {
    const [formData, setFormData] = useState<WorkingHoursData>({
        regular_start: initialData.regular_start || '08:00',
        regular_end: initialData.regular_end || '17:00',
        break_duration: initialData.break_duration || 60,
        break_start: initialData.break_start || '12:00',
        work_days: initialData.work_days || ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        shift_enabled: initialData.shift_enabled || false,
    });

    const [errors, setErrors] = useState<ValidationErrors>({});

    // Calculate total working hours per day
    const calculateDailyHours = (): number => {
        if (!formData.regular_start || !formData.regular_end) return 0;

        const [startHour, startMinute] = formData.regular_start.split(':').map(Number);
        const [endHour, endMinute] = formData.regular_end.split(':').map(Number);

        const startMinutes = startHour * 60 + startMinute;
        const endMinutes = endHour * 60 + endMinute;

        const totalMinutes = endMinutes - startMinutes;
        const workMinutes = totalMinutes - (formData.break_duration || 0);

        return workMinutes / 60;
    };

    // Calculate total working hours per week
    const calculateWeeklyHours = (): number => {
        const dailyHours = calculateDailyHours();
        const workDaysCount = formData.work_days.length;
        return dailyHours * workDaysCount;
    };

    // Validation schema
    const validateForm = (): boolean => {
        const newErrors: ValidationErrors = {};

        if (!formData.regular_start) {
            newErrors.regular_start = 'Start time is required';
        }

        if (!formData.regular_end) {
            newErrors.regular_end = 'End time is required';
        }

        // Validate end time is after start time
        if (formData.regular_start && formData.regular_end) {
            const [startHour, startMinute] = formData.regular_start.split(':').map(Number);
            const [endHour, endMinute] = formData.regular_end.split(':').map(Number);
            const startMinutes = startHour * 60 + startMinute;
            const endMinutes = endHour * 60 + endMinute;

            if (endMinutes <= startMinutes) {
                newErrors.regular_end = 'End time must be after start time';
            }
        }

        if (formData.break_duration < 0 || formData.break_duration > 240) {
            newErrors.break_duration = 'Break duration must be between 0 and 240 minutes';
        }

        if (formData.work_days.length === 0) {
            newErrors.work_days = 'At least one working day must be selected';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleInputChange = (field: keyof WorkingHoursData, value: string | number | boolean | string[]) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        
        // Clear error for this field
        if (errors[field]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const handleDayToggle = (day: string, checked: boolean) => {
        const updatedDays = checked
            ? [...formData.work_days, day]
            : formData.work_days.filter(d => d !== day);
        
        handleInputChange('work_days', updatedDays);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (validateForm()) {
            onSubmit(formData);
        } else {
            // Scroll to first error
            const firstErrorField = Object.keys(errors)[0];
            const element = document.getElementById(firstErrorField);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    };

    const dailyHours = calculateDailyHours();
    const weeklyHours = calculateWeeklyHours();

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Regular Schedule */}
            <div className="space-y-4">
                <h3 className="text-lg font-semibold">Regular Schedule</h3>
                
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="regular_start">
                            Start Time <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="regular_start"
                            type="time"
                            value={formData.regular_start}
                            onChange={(e) => handleInputChange('regular_start', e.target.value)}
                            className={errors.regular_start ? 'border-destructive' : ''}
                        />
                        {errors.regular_start && (
                            <p className="text-sm text-destructive">{errors.regular_start}</p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="regular_end">
                            End Time <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="regular_end"
                            type="time"
                            value={formData.regular_end}
                            onChange={(e) => handleInputChange('regular_end', e.target.value)}
                            className={errors.regular_end ? 'border-destructive' : ''}
                        />
                        {errors.regular_end && (
                            <p className="text-sm text-destructive">{errors.regular_end}</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Break Time */}
            <div className="space-y-4">
                <h3 className="text-lg font-semibold">Break Time</h3>
                
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="break_start">Break Start Time</Label>
                        <Input
                            id="break_start"
                            type="time"
                            value={formData.break_start}
                            onChange={(e) => handleInputChange('break_start', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            When employees typically take their break
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="break_duration">
                            Break Duration (minutes) <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="break_duration"
                            type="number"
                            min="0"
                            max="240"
                            step="15"
                            value={formData.break_duration}
                            onChange={(e) => handleInputChange('break_duration', parseInt(e.target.value, 10))}
                            className={errors.break_duration ? 'border-destructive' : ''}
                        />
                        {errors.break_duration && (
                            <p className="text-sm text-destructive">{errors.break_duration}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Common values: 30, 45, 60 minutes
                        </p>
                    </div>
                </div>
            </div>

            {/* Working Days */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold">Working Days</h3>
                    {errors.work_days && (
                        <p className="text-sm text-destructive">{errors.work_days}</p>
                    )}
                </div>
                
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 md:grid-cols-7">
                    {DAYS_OF_WEEK.map((day) => (
                        <div key={day.value} className="flex items-center space-x-2">
                            <Checkbox
                                id={day.value}
                                checked={formData.work_days.includes(day.value)}
                                onCheckedChange={(checked) => handleDayToggle(day.value, checked as boolean)}
                            />
                            <Label
                                htmlFor={day.value}
                                className="text-sm font-normal cursor-pointer"
                            >
                                <span className="hidden sm:inline">{day.label}</span>
                                <span className="sm:hidden">{day.short}</span>
                            </Label>
                        </div>
                    ))}
                </div>
                <p className="text-xs text-muted-foreground">
                    Select the days your company operates
                </p>
            </div>

            {/* Shift Schedule Toggle */}
            <div className="space-y-4">
                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="shift_enabled"
                        checked={formData.shift_enabled}
                        onCheckedChange={(checked) => handleInputChange('shift_enabled', checked as boolean)}
                    />
                    <Label
                        htmlFor="shift_enabled"
                        className="text-sm font-normal cursor-pointer"
                    >
                        Enable shift schedules (morning/afternoon/night shifts)
                    </Label>
                </div>
                {formData.shift_enabled && (
                    <Alert>
                        <Clock className="h-4 w-4" />
                        <AlertDescription className="text-sm">
                            Shift schedules will be configured in the Workforce Management module. This setting enables 
                            the ability to assign employees to different shift rotations.
                        </AlertDescription>
                    </Alert>
                )}
            </div>

            {/* Working Hours Summary */}
            <Card className="bg-muted/50">
                <CardContent className="pt-6">
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-muted-foreground">Daily Work Hours</p>
                            <p className="text-2xl font-bold">
                                {dailyHours.toFixed(1)} hrs
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {Math.floor(dailyHours)} hours {Math.round((dailyHours % 1) * 60)} minutes
                            </p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-muted-foreground">Working Days per Week</p>
                            <p className="text-2xl font-bold">
                                {formData.work_days.length} days
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formData.work_days.map(d => d.charAt(0).toUpperCase() + d.slice(1, 3)).join(', ')}
                            </p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-muted-foreground">Weekly Work Hours</p>
                            <p className="text-2xl font-bold">
                                {weeklyHours.toFixed(1)} hrs
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Total hours per week
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Submit Button */}
            <div className="flex justify-end gap-3 pt-4 border-t">
                <Button
                    type="submit"
                    size="lg"
                    disabled={isSubmitting}
                >
                    {isSubmitting ? (
                        <>Saving...</>
                    ) : (
                        <>
                            <Save className="mr-2 h-4 w-4" />
                            Save Working Hours
                        </>
                    )}
                </Button>
            </div>

            {/* Validation Summary */}
            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                        Please fix the following errors before submitting:
                        <ul className="list-disc list-inside mt-2 space-y-1">
                            {Object.entries(errors).map(([field, error]) => (
                                <li key={field} className="text-sm">{error}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
        </form>
    );
}
