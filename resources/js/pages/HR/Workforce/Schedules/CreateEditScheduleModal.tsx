import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, Calendar, Copy } from 'lucide-react';
import { WorkSchedule, ScheduleTemplate } from '@/types/workforce-pages';

interface CreateEditScheduleModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (data: Partial<WorkSchedule> & { save_as_template?: boolean }) => void;
    schedule?: WorkSchedule | null;
    departments: Array<{ id: number; name: string }>;
    templates: ScheduleTemplate[];
    isEditing: boolean;
}

interface ScheduleFormData {
    name: string;
    description: string;
    department_id: number;
    effective_date: string;
    status: 'active' | 'draft' | 'expired';
    shift_pattern: Record<string, { start_time: string; end_time: string }>;
    work_days: string[];
    rest_days: string[];
}

const DAYS_OF_WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default function CreateEditScheduleModal({
    isOpen,
    onClose,
    onSave,
    schedule,
    departments,
    templates,
    isEditing,
}: CreateEditScheduleModalProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [formData, setFormData] = useState<ScheduleFormData>({
        name: '',
        description: '',
        department_id: 0,
        effective_date: new Date().toISOString().split('T')[0],
        status: 'active',
        shift_pattern: {
            Monday: { start_time: '08:00', end_time: '17:00' },
            Tuesday: { start_time: '08:00', end_time: '17:00' },
            Wednesday: { start_time: '08:00', end_time: '17:00' },
            Thursday: { start_time: '08:00', end_time: '17:00' },
            Friday: { start_time: '08:00', end_time: '17:00' },
            Saturday: { start_time: '', end_time: '' },
            Sunday: { start_time: '', end_time: '' },
        },
        work_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        rest_days: ['Saturday', 'Sunday'],
    });

    const [selectedTemplate, setSelectedTemplate] = useState<string>('');
    const [saveAsTemplate, setSaveAsTemplate] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!isOpen) {
            // Reset form when modal closes
            return;
        }

        if (schedule && isEditing) {
            // Load existing schedule data for editing
            const shiftPattern: Record<string, { start_time: string; end_time: string }> = {};
            DAYS_OF_WEEK.forEach((day) => {
                const dayKey = day.toLowerCase();
                const startKey = `${dayKey}_start` as keyof WorkSchedule;
                const endKey = `${dayKey}_end` as keyof WorkSchedule;
                const start = schedule[startKey];
                const end = schedule[endKey];
                shiftPattern[day] = {
                    start_time: (start as string) || '',
                    end_time: (end as string) || '',
                };
            });

            // Parse effective_date - handle ISO format from database
            let effectiveDate = new Date().toISOString().split('T')[0];
            if (schedule.effective_date) {
                try {
                    const date = new Date(schedule.effective_date);
                    effectiveDate = date.toISOString().split('T')[0];
                } catch (e) {
                    effectiveDate = schedule.effective_date as string;
                }
            }

            setFormData({
                name: schedule.name || '',
                description: schedule.description || '',
                department_id: schedule.department_id || 0,
                effective_date: effectiveDate,
                status: (schedule.status as 'active' | 'draft' | 'expired') || 'active',
                shift_pattern: shiftPattern,
                work_days: DAYS_OF_WEEK.filter(day => {
                    const startKey = `${day.toLowerCase()}_start` as keyof WorkSchedule;
                    return !!schedule[startKey];
                }),
                rest_days: DAYS_OF_WEEK.filter(day => {
                    const startKey = `${day.toLowerCase()}_start` as keyof WorkSchedule;
                    return !schedule[startKey];
                }),
            });
        } else if (!isEditing) {
            // Reset form for creating new schedule
            setFormData({
                name: '',
                description: '',
                department_id: 0,
                effective_date: '',
                status: 'active',
                shift_pattern: {
                    Monday: { start_time: '08:00', end_time: '17:00' },
                    Tuesday: { start_time: '08:00', end_time: '17:00' },
                    Wednesday: { start_time: '08:00', end_time: '17:00' },
                    Thursday: { start_time: '08:00', end_time: '17:00' },
                    Friday: { start_time: '08:00', end_time: '17:00' },
                    Saturday: { start_time: '', end_time: '' },
                    Sunday: { start_time: '', end_time: '' },
                },
                work_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                rest_days: ['Saturday', 'Sunday'],
            });
            setErrors({});
        }
    }, [isOpen, schedule, isEditing]);

    const validateForm = () => {
        const newErrors: Record<string, string> = {};

        if (!formData.name?.trim()) newErrors.name = 'Schedule name is required';
        if (!formData.department_id) newErrors.department_id = 'Department is required';
        if (!formData.effective_date) newErrors.effective_date = 'Effective date is required';

        const hasValidShift = Object.values(formData.shift_pattern || {}).some(
            (shift) => shift.start_time && shift.end_time
        );
        if (!hasValidShift) {
            newErrors.shift_pattern = 'At least one shift must have times defined';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleApplyTemplate = (templateId: string) => {
        const template = templates.find((t) => t.id.toString() === templateId);
        if (template && template.shift_pattern && template.work_days && template.rest_days) {
            setFormData({
                ...formData,
                shift_pattern: template.shift_pattern,
                work_days: template.work_days,
                rest_days: template.rest_days,
            });
            setSelectedTemplate(templateId);
        }
    };

    const handleShiftChange = (day: string, field: 'start_time' | 'end_time', value: string) => {
        const pattern = formData.shift_pattern || {};
        setFormData({
            ...formData,
            shift_pattern: {
                ...pattern,
                [day]: {
                    ...(pattern[day] || {}),
                    [field]: value,
                },
            },
        });
    };

    const handleWorkDayToggle = (day: string) => {
        const workDays = formData.work_days || [];
        const restDays = formData.rest_days || [];
        const shiftPattern = formData.shift_pattern || {};

        if (workDays.includes(day)) {
            // Removing as work day - clear times
            setFormData({
                ...formData,
                work_days: workDays.filter((d: string) => d !== day),
                rest_days: [...restDays, day],
                shift_pattern: {
                    ...shiftPattern,
                    [day]: { start_time: '', end_time: '' },
                },
            });
        } else {
            // Adding as work day - set default times
            setFormData({
                ...formData,
                work_days: [...workDays, day],
                rest_days: restDays.filter((d: string) => d !== day),
                shift_pattern: {
                    ...shiftPattern,
                    [day]: { start_time: '08:00', end_time: '17:00' },
                },
            });
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) return;

        setIsLoading(true);
        try {
            const submitData: Record<string, any> = {
                name: formData.name,
                description: formData.description,
                effective_date: formData.effective_date,
                department_id: formData.department_id || null,
                status: formData.status,
            };

            // Map all days - set times for work days, clear for rest days
            const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            DAYS.forEach((day) => {
                const dayKey = day.toLowerCase();
                const times = formData.shift_pattern[day];
                
                if (times && times.start_time && times.end_time) {
                    // Format time - add :00 only if not present
                    const formatTime = (timeStr: string) => {
                        if (timeStr.split(':').length === 3) {
                            return timeStr; // Already has seconds
                        }
                        return timeStr + ':00'; // Add seconds
                    };
                    submitData[`${dayKey}_start`] = formatTime(times.start_time);
                    submitData[`${dayKey}_end`] = formatTime(times.end_time);
                } else {
                    // Clear times for rest days or days without times set
                    submitData[`${dayKey}_start`] = null;
                    submitData[`${dayKey}_end`] = null;
                }
            });

            if (isEditing && schedule?.id) {
                // Update existing schedule - use PUT method
                router.put(`/hr/workforce/schedules/${schedule.id}`, submitData, {
                    onSuccess: () => {
                        onClose();
                        // Refresh the page to get fresh data from server
                        window.location.reload();
                    },
                    onError: (errors) => {
                        setErrors(errors as Record<string, string>);
                    },
                });
            } else {
                // Create new schedule - use POST method
                router.post('/hr/workforce/schedules', submitData, {
                    onSuccess: () => {
                        onClose();
                        // Refresh the page to get fresh data from server
                        window.location.reload();
                    },
                    onError: (errors) => {
                        setErrors(errors as Record<string, string>);
                    },
                });
            }
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Edit Schedule' : 'Create New Schedule'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the schedule details and shift times'
                            : 'Set up a new work schedule with shift patterns'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Basic Information</h3>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Schedule Name</Label>
                                <Input
                                    id="name"
                                    value={formData.name || ''}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="e.g., Manufacturing A - Day Shift"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="department">Department</Label>
                                <Select
                                    value={(formData.department_id || 0).toString()}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, department_id: parseInt(value) })
                                    }
                                >
                                    <SelectTrigger
                                        id="department"
                                        className={errors.department_id ? 'border-red-500' : ''}
                                    >
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.department_id && (
                                    <p className="text-sm text-red-500">{errors.department_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={formData.description || ''}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Optional notes about this schedule"
                                className="resize-none"
                                rows={3}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="effective_date">Effective Date</Label>
                                <Input
                                    id="effective_date"
                                    type="date"
                                    value={formData.effective_date || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, effective_date: e.target.value })
                                    }
                                    className={errors.effective_date ? 'border-red-500' : ''}
                                />
                                {errors.effective_date && (
                                    <p className="text-sm text-red-500">{errors.effective_date}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">Status</Label>
                                <Select value={formData.status || 'active'} onValueChange={(value) =>
                                setFormData({ ...formData, status: value as 'active' | 'draft' | 'expired' })
                            }>
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="expired">Expired</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    {/* Template Selector */}
                    {templates.length > 0 && (
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Quick Templates</h3>
                            <div className="grid grid-cols-2 gap-2">
                                {templates.map((template) => (
                                    <Button
                                        key={template.id}
                                        type="button"
                                        variant={selectedTemplate === template.id.toString() ? 'default' : 'outline'}
                                        onClick={() => handleApplyTemplate(template.id.toString())}
                                        className="justify-start"
                                    >
                                        <Copy className="h-4 w-4 mr-2" />
                                        {template.name}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Work Days Selection */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Work Days</h3>
                        <div className="grid grid-cols-7 gap-2">
                            {DAYS_OF_WEEK.map((day) => (
                                <div key={day} className="flex items-center space-x-2">
                                    <Checkbox
                                        id={`day-${day}`}
                                        checked={(formData.work_days || []).includes(day)}
                                        onCheckedChange={() => handleWorkDayToggle(day)}
                                    />
                                    <Label
                                        htmlFor={`day-${day}`}
                                        className="text-xs font-medium cursor-pointer"
                                    >
                                        {day.slice(0, 3)}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Shift Times */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold">Shift Times</h3>
                        <div className="space-y-3">
                            {DAYS_OF_WEEK.map((day) => {
                                const shiftPattern = formData.shift_pattern || {};
                                const dayPattern = shiftPattern[day as keyof typeof shiftPattern] || {
                                    start_time: '',
                                    end_time: '',
                                };
                                const isWorkDay = (formData.work_days || []).includes(day);

                                return (
                                    <Card key={day} className={isWorkDay ? '' : 'opacity-50'}>
                                        <CardContent className="pt-4">
                                            <div className="grid grid-cols-3 gap-4 items-end">
                                                <div>
                                                    <Label className="text-sm">{day}</Label>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`start-${day}`} className="text-xs">
                                                        Start Time
                                                    </Label>
                                                    <Input
                                                        id={`start-${day}`}
                                                        type="time"
                                                        value={dayPattern.start_time || ''}
                                                        onChange={(e) =>
                                                            handleShiftChange(day, 'start_time', e.target.value)
                                                        }
                                                        disabled={!isWorkDay}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor={`end-${day}`} className="text-xs">
                                                        End Time
                                                    </Label>
                                                    <Input
                                                        id={`end-${day}`}
                                                        type="time"
                                                        value={dayPattern.end_time || ''}
                                                        onChange={(e) =>
                                                            handleShiftChange(day, 'end_time', e.target.value)
                                                        }
                                                        disabled={!isWorkDay}
                                                    />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                        {errors.shift_pattern && (
                            <p className="text-sm text-red-500">{errors.shift_pattern}</p>
                        )}
                    </div>

                    {/* Save as Template */}
                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="save-template"
                            checked={saveAsTemplate}
                            onCheckedChange={(checked) => setSaveAsTemplate(checked as boolean)}
                        />
                        <Label htmlFor="save-template" className="text-sm font-medium cursor-pointer">
                            Save this schedule as a reusable template
                        </Label>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-3 justify-end pt-4 border-t">
                        <Button type="button" variant="outline" onClick={onClose} disabled={isLoading}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isLoading}>
                            {isLoading ? 'Saving...' : isEditing ? 'Update Schedule' : 'Create Schedule'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
