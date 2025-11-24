import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle, CheckCircle2, Clock } from 'lucide-react';
import { ShiftAssignment, EmployeeReference, Department } from '@/types/workforce-pages';
import {
    formatTime,
    calculateShiftDuration,
    formatShiftDuration,
    validateShiftTimes,
    detectConflicts,
} from '@/lib/workforce-utils';
import AssignmentConflictIndicator, {
    ConflictData,
} from '@/components/workforce/assignment-conflict-indicator';

interface CreateEditAssignmentModalProps {
    isOpen: boolean;
    onClose: () => void;
    assignment?: ShiftAssignment | null;
    employees: EmployeeReference[];
    departments: Department[];
    schedules: Array<{ id: number; name: string; shift_start: string; shift_end: string }>;
    onConfirm: (data: Record<string, unknown>) => void;
}

export default function CreateEditAssignmentModal({
    isOpen,
    onClose,
    assignment,
    employees = [],
    departments = [],
    schedules = [],
    onConfirm,
}: CreateEditAssignmentModalProps) {
    const [formData, setFormData] = useState<Partial<ShiftAssignment> & { save_as_template?: boolean }>({
        employee_id: undefined,
        schedule_id: undefined,
        date: new Date().toISOString().split('T')[0],
        shift_start: '06:00:00',
        shift_end: '14:00:00',
        location: '',
        is_overtime: false,
        notes: '',
    });

    const [hasConflict, setHasConflict] = useState(false);
    const [conflictMessage, setConflictMessage] = useState('');
    const [isValidating, setIsValidating] = useState(false);

    useEffect(() => {
        if (assignment) {
            setFormData({
                id: assignment.id,
                employee_id: assignment.employee_id,
                schedule_id: assignment.schedule_id,
                date: assignment.date,
                shift_start: assignment.shift_start,
                shift_end: assignment.shift_end,
                location: assignment.location,
                is_overtime: assignment.is_overtime,
                notes: assignment.notes,
            });
        } else {
            setFormData({
                employee_id: undefined,
                schedule_id: undefined,
                date: new Date().toISOString().split('T')[0],
                shift_start: '06:00:00',
                shift_end: '14:00:00',
                location: '',
                is_overtime: false,
                notes: '',
            });
        }
        setHasConflict(false);
        setConflictMessage('');
    }, [assignment, isOpen]);

    // Validate for conflicts when key fields change
    const validateAssignment = async () => {
        setIsValidating(true);

        const timeValidation = validateShiftTimes(formData.shift_start || '06:00:00', formData.shift_end || '14:00:00');
        if (!timeValidation.isValid) {
            setConflictMessage(timeValidation.errorMessage || 'Invalid shift times');
            setHasConflict(true);
            setIsValidating(false);
            return;
        }

        if (formData.employee_id && formData.date) {
            const conflicts = await detectConflicts(
                formData.employee_id,
                formData.date
            );

            setHasConflict(conflicts.hasConflict);
            setConflictMessage(conflicts.conflictMessage || '');
        }

        setIsValidating(false);
    };

    const handleSelectSchedule = (scheduleId: string) => {
        const schedule = schedules.find((s) => s.id === parseInt(scheduleId));
        if (schedule) {
            setFormData({
                ...formData,
                schedule_id: parseInt(scheduleId),
                shift_start: schedule.shift_start,
                shift_end: schedule.shift_end,
            });
            validateAssignment();
        }
    };

    const handleDateChange = (date: string) => {
        setFormData({ ...formData, date });
        validateAssignment();
    };

    const handleSubmit = () => {
        if (!formData.employee_id || !formData.schedule_id || !formData.date) {
            alert('Please fill in all required fields');
            return;
        }

        if (hasConflict) {
            if (!window.confirm('This assignment has a scheduling conflict. Override?')) {
                return;
            }
        }

        onConfirm(formData);
    };

    const selectedEmployee = employees.find((e) => e.id === formData.employee_id);
    const selectedSchedule = schedules.find((s) => s.id === formData.schedule_id);

    const getDurationDisplay = () => formatShiftDuration(formData.shift_start || '06:00:00', formData.shift_end || '14:00:00');

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {assignment ? 'Edit Shift Assignment' : 'Create New Shift Assignment'}
                    </DialogTitle>
                    <DialogDescription>
                        {assignment
                            ? `Update the shift assignment details below. The system will detect scheduling conflicts automatically.`
                            : `Assign an employee to a shift. The system will automatically detect any scheduling conflicts.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Main Assignment Fields */}
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            {/* Employee Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="employee_id" className="required">
                                    Employee <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={formData.employee_id?.toString()}
                                    onValueChange={(value) => {
                                        setFormData({ ...formData, employee_id: parseInt(value) });
                                        validateAssignment();
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select employee" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((emp) => (
                                            <SelectItem key={emp.id} value={emp.id.toString()}>
                                                {emp.full_name} ({emp.employee_number})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedEmployee && (
                                    <p className="text-xs text-gray-500">
                                        Department: {selectedEmployee.department_name}
                                    </p>
                                )}
                            </div>

                            {/* Schedule Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="schedule_id" className="required">
                                    Schedule <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={formData.schedule_id?.toString()}
                                    onValueChange={handleSelectSchedule}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select schedule template" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {schedules.map((sch) => (
                                            <SelectItem key={sch.id} value={sch.id.toString()}>
                                                {sch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedSchedule && (
                                    <p className="text-xs text-gray-500">
                                        {formatTime(selectedSchedule.shift_start)} - {formatTime(selectedSchedule.shift_end)}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Date Selection */}
                        <div className="space-y-2">
                            <Label htmlFor="date" className="required">
                                Date <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                type="date"
                                value={formData.date}
                                onChange={(e) => handleDateChange(e.target.value)}
                            />
                        </div>

                        {/* Shift Times */}
                        <div className="grid grid-cols-3 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="shift_start" className="required">
                                    Start Time <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    type="time"
                                    value={formData.shift_start}
                                    onChange={(e) =>
                                        setFormData({ ...formData, shift_start: e.target.value })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="shift_end" className="required">
                                    End Time <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    type="time"
                                    value={formData.shift_end}
                                    onChange={(e) =>
                                        setFormData({ ...formData, shift_end: e.target.value })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Duration</Label>
                                <div className="h-10 flex items-center px-3 border rounded-md bg-gray-50">
                                    <span className="text-sm font-medium">{getDurationDisplay()}</span>
                                </div>
                            </div>
                        </div>

                        {/* Location */}
                        <div className="space-y-2">
                            <Label htmlFor="location">Location</Label>
                            <Input
                                placeholder="e.g., Production Floor, Maintenance Shop"
                                value={formData.location || ''}
                                onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                            />
                        </div>

                        {/* Overtime and Notes */}
                        <div className="space-y-3">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_overtime"
                                    checked={formData.is_overtime || false}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, is_overtime: checked as boolean })
                                    }
                                />
                                <Label htmlFor="is_overtime" className="font-normal cursor-pointer">
                                    <span className="flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        Mark as Overtime
                                    </span>
                                </Label>
                            </div>

                            {formData.is_overtime && (
                                <input
                                    type="hidden"
                                    name="overtime_hours"
                                    value={formData.is_overtime ? Math.max(0, calculateShiftDuration(formData.shift_start || '06:00:00', formData.shift_end || '14:00:00') - 8) : 0}
                                />
                            )}
                        </div>

                        {/* Notes */}
                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                placeholder="Add any special notes or instructions for this shift..."
                                value={formData.notes || ''}
                                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                className="resize-none"
                                rows={3}
                            />
                        </div>
                    </div>

                    {/* Conflict Detection Component */}
                    {selectedEmployee && selectedSchedule && formData.date && (
                        <div className="space-y-4">
                            <AssignmentConflictIndicator
                                conflict={
                                    hasConflict
                                        ? {
                                              type: 'overlap',
                                              severity: 'warning',
                                              message: 'Scheduling Conflict Detected',
                                              details: conflictMessage,
                                              resolution:
                                                  'Click "Confirm" to override and assign this shift anyway.',
                                          }
                                        : assignment?.has_conflict
                                        ? {
                                              type: 'overlap',
                                              severity: 'warning',
                                              message: 'Existing Scheduling Conflict',
                                              details: assignment.conflict_reason || 'This assignment has a recorded conflict.',
                                              resolution:
                                                  'This conflict was detected when the assignment was created.',
                                          }
                                        : {
                                              type: 'none',
                                              severity: 'none',
                                              message: 'No conflicts',
                                          }
                                }
                                canOverride={true}
                            />

                            {/* Display conflicting shift details if available */}
                            {(hasConflict || assignment?.has_conflict) && assignment?.conflicting_employee_name && (
                                <Card className="border-red-200 bg-red-50">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-sm text-red-900">
                                            Conflicting Shift Details
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="bg-white rounded p-3 space-y-2 text-red-700">
                                            <div className="flex justify-between">
                                                <span className="font-semibold">Employee:</span>
                                                <span>{assignment.conflicting_employee_name}</span>
                                            </div>
                                            {assignment.conflicting_shift_date && (
                                                <div className="flex justify-between">
                                                    <span className="font-semibold">Date:</span>
                                                    <span>{assignment.conflicting_shift_date}</span>
                                                </div>
                                            )}
                                            {assignment.conflicting_shift_start && assignment.conflicting_shift_end && (
                                                <div className="flex justify-between">
                                                    <span className="font-semibold">Time:</span>
                                                    <span>
                                                        {formatTime(assignment.conflicting_shift_start)} - {formatTime(assignment.conflicting_shift_end)}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}

                    {/* Preview Card */}
                    {selectedEmployee && selectedSchedule && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm flex items-center gap-2">
                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                    Assignment Preview
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Employee:</span>
                                        <span className="font-medium">{selectedEmployee.full_name}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Schedule:</span>
                                        <span className="font-medium">{selectedSchedule.name}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Date:</span>
                                        <span className="font-medium">
                                            {new Date(formData.date || '').toLocaleDateString()}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Shift Time:</span>
                                        <span className="font-medium">
                                            {formatTime(formData.shift_start || '06:00:00')} -{' '}
                                            {formatTime(formData.shift_end || '14:00:00')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Duration:</span>
                                        <span className="font-medium">{getDurationDisplay()}</span>
                                    </div>
                                    {formData.is_overtime && (
                                        <div className="flex justify-between pt-2 border-t">
                                            <span className="text-gray-600 flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                Overtime:
                                            </span>
                                            <Badge className="bg-orange-100 text-orange-800">
                                                {Math.max(0, calculateShiftDuration(formData.shift_start || '06:00:00', formData.shift_end || '14:00:00') - 8)}h
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={!formData.employee_id || !formData.schedule_id}>
                        {assignment ? 'Update Assignment' : 'Create Assignment'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
