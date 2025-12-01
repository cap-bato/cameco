import React, { useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { AlertTriangle, Clock } from 'lucide-react';
import { Department, EmployeeReference } from '@/types/workforce-pages';
import {
    formatTime,
    calculateShiftDuration,
    detectConflicts,
} from '@/lib/workforce-utils';

interface BulkAssignmentModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: (assignments: BulkAssignmentPayload[]) => Promise<void>;
    employees: EmployeeReference[];
    departments: Department[];
    schedules: Array<{ id: number; name: string; shift_start: string; shift_end: string }>;
}

interface BulkAssignmentPayload {
    employee_id: number;
    date: string;
    shift_start: string;
    shift_end: string;
    shift_type: string;
    location?: string;
    is_overtime: boolean;
    department_id: number;
}

interface BulkAssignmentFormData {
    employees: number[];
    date_from: string;
    date_to: string;
    schedule_id: string;
    shift_template?: string;
    location: string;
    is_overtime: boolean;
    department_id: string;
}

/**
 * BulkAssignmentModal Component
 *
 * Reusable modal for bulk assigning shifts to multiple employees across a date range.
 * Features:
 * - Multi-select employees with search
 * - Date range picker for assignment dates
 * - Shift template selector
 * - Real-time preview of assignments
 * - Conflict detection across all assignments
 * - Progress tracking during bulk operation
 * - Success/error summary
 *
 * @example
 * ```tsx
 * <BulkAssignmentModal
 *   isOpen={isOpen}
 *   onClose={() => setIsOpen(false)}
 *   onConfirm={handleBulkAssign}
 *   employees={employees}
 *   departments={departments}
 *   schedules={schedules}
 * />
 * ```
 */
export default function BulkAssignmentModal({
    isOpen,
    onClose,
    onConfirm,
    employees,
    departments,
    schedules,
}: BulkAssignmentModalProps) {
    const [formData, setFormData] = useState<BulkAssignmentFormData>({
        employees: [],
        date_from: '',
        date_to: '',
        schedule_id: '',
        location: '',
        is_overtime: false,
        department_id: 'all',
    });

    const [isLoading, setIsLoading] = useState(false);
    const [previewConflicts, setPreviewConflicts] = useState<Record<number, boolean>>({});
    const [operationStep, setOperationStep] = useState<'form' | 'preview' | 'processing'>('form');
    const [employeeSearchTerm, setEmployeeSearchTerm] = useState('');

    // Filter employees by department and search term
    const filteredEmployees = useMemo(() => {
        if (!employees || !Array.isArray(employees)) {
            return [];
        }
        
        let filtered = employees;
        
        // Filter by department
        if (formData.department_id !== 'all') {
            filtered = filtered.filter(
                (e) => e.department_id === parseInt(formData.department_id)
            );
        }
        
        // Filter by search term (search in name and employee number)
        if (employeeSearchTerm.trim()) {
            const searchLower = employeeSearchTerm.toLowerCase();
            filtered = filtered.filter(
                (e) =>
                    e.full_name?.toLowerCase().includes(searchLower) ||
                    e.employee_number?.toLowerCase().includes(searchLower) ||
                    (e.position_name?.toLowerCase().includes(searchLower) || false)
            );
        }
        
        return filtered;
    }, [employees, formData.department_id, employeeSearchTerm]);

    // Get selected schedule details
    const selectedSchedule = useMemo(() => {
        if (!schedules || !Array.isArray(schedules) || !formData.schedule_id) {
            return undefined;
        }
        return schedules.find((s) => s.id === parseInt(formData.schedule_id));
    }, [schedules, formData.schedule_id]);

    // Generate dates in range
    const dateRange = useMemo(() => {
        if (!formData.date_from || !formData.date_to) return [];

        const dates: string[] = [];
        const start = new Date(formData.date_from);
        const end = new Date(formData.date_to);

        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            dates.push(d.toISOString().split('T')[0]);
        }

        return dates;
    }, [formData.date_from, formData.date_to]);

    // Generate preview assignments
    const previewAssignments = useMemo(() => {
        if (
            !formData.employees.length ||
            !selectedSchedule ||
            !dateRange.length
        ) {
            return [];
        }

        const assignments: BulkAssignmentPayload[] = [];

        for (const employeeId of formData.employees) {
            const employee = employees.find((e) => e.id === employeeId);
            if (!employee) continue;

            for (const date of dateRange) {
                assignments.push({
                    employee_id: employeeId,
                    date,
                    shift_start: selectedSchedule.shift_start,
                    shift_end: selectedSchedule.shift_end,
                    shift_type: 'standard',
                    location: formData.location || undefined,
                    is_overtime: formData.is_overtime,
                    department_id: employee.department_id || parseInt(formData.department_id) || 0,
                });
            }
        }

        return assignments;
    }, [formData.employees, selectedSchedule, dateRange, employees, formData.location, formData.is_overtime, formData.department_id]);

    // Check conflicts for preview
    React.useEffect(() => {
        const checkConflicts = async () => {
            const conflicts: Record<number, boolean> = {};

            for (const assignment of previewAssignments) {
                try {
                    const result = await detectConflicts(
                        assignment.employee_id,
                        assignment.date,
                        assignment.shift_start,
                        assignment.shift_end
                    );

                    if (result.hasConflict) {
                        conflicts[assignment.employee_id] = true;
                    }
                } catch (err) {
                    console.error('Conflict detection error:', err);
                }
            }

            setPreviewConflicts(conflicts);
        };

        if (previewAssignments.length > 0) {
            checkConflicts();
        }
    }, [previewAssignments]);

    const handleEmployeeToggle = (employeeId: number) => {
        setFormData((prev) => ({
            ...prev,
            employees: prev.employees.includes(employeeId)
                ? prev.employees.filter((id) => id !== employeeId)
                : [...prev.employees, employeeId],
        }));
    };

    const handleSelectAll = () => {
        if (formData.employees.length === filteredEmployees.length) {
            setFormData((prev) => ({ ...prev, employees: [] }));
        } else {
            setFormData((prev) => ({
                ...prev,
                employees: filteredEmployees.map((e) => e.id),
            }));
        }
    };

    const handlePreview = () => {
        if (!formData.employees.length || !formData.date_from || !formData.date_to || !formData.schedule_id) {
            return;
        }
        setOperationStep('preview');
    };

    const handleConfirm = async () => {
        setOperationStep('processing');
        setIsLoading(true);

        try {
            await onConfirm(previewAssignments);
            // Reset form after successful submission
            setFormData({
                employees: [],
                date_from: '',
                date_to: '',
                schedule_id: '',
                location: '',
                is_overtime: false,
                department_id: 'all',
            });
            setOperationStep('form');
            onClose();
        } catch {
            // Conflict detected or other error, stay in preview mode
            setOperationStep('preview');
        } finally {
            setIsLoading(false);
        }
    };

    const handleReset = () => {
        setFormData({
            employees: [],
            date_from: '',
            date_to: '',
            schedule_id: '',
            location: '',
            is_overtime: false,
            department_id: 'all',
        });
        setOperationStep('form');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Bulk Assign Shifts</DialogTitle>
                    <DialogDescription>
                        {operationStep === 'form'
                            ? 'Select employees, dates, and shift template to bulk assign shifts'
                            : operationStep === 'preview'
                              ? 'Review the assignments that will be created'
                              : 'Processing bulk assignments...'}
                    </DialogDescription>
                </DialogHeader>

                {/* Form Step */}
                {operationStep === 'form' && (
                    <div className="space-y-6">
                        {/* Department Filter */}
                        <div>
                            <label className="block text-sm font-medium mb-2">
                                Department (Optional)
                            </label>
                            <Select
                                value={formData.department_id}
                                onValueChange={(value) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        department_id: value,
                                        employees: [], // Reset employee selection when department changes
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {departments.map((dept) => (
                                        <SelectItem key={dept.id} value={dept.id.toString()}>
                                            {dept.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Employee Selection */}
                        <div>
                            <div className="flex justify-between items-center mb-2">
                                <label className="block text-sm font-medium">
                                    Select Employees ({formData.employees.length})
                                </label>
                                {filteredEmployees.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleSelectAll}
                                    >
                                        {formData.employees.length === filteredEmployees.length
                                            ? 'Deselect All'
                                            : 'Select All'}
                                    </Button>
                                )}
                            </div>
                            
                            {/* Employee Search Input */}
                            <Input
                                placeholder="Search by name, employee ID, or position..."
                                value={employeeSearchTerm}
                                onChange={(e) => setEmployeeSearchTerm(e.target.value)}
                                className="mb-3"
                            />
                            
                            {/* Selected Employees Display */}
                            {formData.employees.length > 0 && (
                                <div className="mb-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <p className="text-xs font-medium text-blue-900 mb-2">Selected Employees:</p>
                                    <div className="flex flex-wrap gap-2">
                                        {employees
                                            .filter((e) => formData.employees.includes(e.id))
                                            .map((employee) => (
                                                <Badge key={employee.id} variant="secondary" className="whitespace-nowrap">
                                                    {employee.full_name} ({employee.employee_number})
                                                </Badge>
                                            ))}
                                    </div>
                                </div>
                            )}
                            
                            <Card className="max-h-40 overflow-y-auto">
                                <CardContent className="pt-4">
                                    {filteredEmployees.length > 0 ? (
                                        <div className="space-y-2">
                                            {filteredEmployees.map((employee) => (
                                                <div
                                                    key={employee.id}
                                                    className="flex items-center gap-2 p-2 rounded hover:bg-gray-50"
                                                >
                                                    <Checkbox
                                                        checked={formData.employees.includes(
                                                            employee.id
                                                        )}
                                                        onCheckedChange={() =>
                                                            handleEmployeeToggle(employee.id)
                                                        }
                                                    />
                                                    <span className="text-sm">
                                                        {employee.full_name} ({employee.employee_number})
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">
                                            No employees in selected department
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Date Range */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Start Date
                                </label>
                                <Input
                                    type="date"
                                    value={formData.date_from}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            date_from: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    End Date
                                </label>
                                <Input
                                    type="date"
                                    value={formData.date_to}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            date_to: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>

                        {/* Shift Template Selector */}
                        <div>
                            <label className="block text-sm font-medium mb-2">
                                Shift Template
                            </label>
                            <Select
                                value={formData.schedule_id}
                                onValueChange={(value) =>
                                    setFormData((prev) => ({
                                        ...prev,
                                        schedule_id: value,
                                    }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a shift template..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {schedules.map((schedule) => (
                                        <SelectItem
                                            key={schedule.id}
                                            value={schedule.id.toString()}
                                        >
                                            {schedule.name} ({formatTime(schedule.shift_start)} -{' '}
                                            {formatTime(schedule.shift_end)})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Additional Options */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Location (Optional)
                                </label>
                                <Input
                                    type="text"
                                    placeholder="e.g., Building A, Floor 2"
                                    value={formData.location}
                                    onChange={(e) =>
                                        setFormData((prev) => ({
                                            ...prev,
                                            location: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="flex items-end">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <Checkbox
                                        checked={formData.is_overtime}
                                        onCheckedChange={(checked) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                is_overtime: checked as boolean,
                                            }))
                                        }
                                    />
                                    <span className="text-sm font-medium">Mark as Overtime</span>
                                </label>
                            </div>
                        </div>

                        {/* Assignment Summary */}
                        {previewAssignments.length > 0 && (
                            <Card className="bg-blue-50 border-blue-200">
                                <CardContent className="pt-4">
                                    <p className="text-sm">
                                        <strong>Summary:</strong> {formData.employees.length} employee
                                        {formData.employees.length !== 1 ? 's' : ''} ×{' '}
                                        {dateRange.length} date
                                        {dateRange.length !== 1 ? 's' : ''} ={' '}
                                        {previewAssignments.length} assignments
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}

                {/* Preview Step */}
                {operationStep === 'preview' && (
                    <div className="space-y-4">
                        {/* Conflict Summary */}
                        {Object.keys(previewConflicts).length > 0 && (
                            <Card className="bg-yellow-50 border-yellow-200">
                                <CardContent className="pt-4 flex items-start gap-2">
                                    <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                    <div>
                                        <p className="text-sm font-semibold text-yellow-900">
                                            {Object.keys(previewConflicts).length} employee
                                            {Object.keys(previewConflicts).length !== 1
                                                ? 's have'
                                                : ' has'}{' '}
                                            scheduling conflicts
                                        </p>
                                        <p className="text-xs text-yellow-800 mt-1">
                                            Review conflicts before proceeding
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Preview Table */}
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Shift Time</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Location</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {previewAssignments.length > 0 ? (
                                        previewAssignments.slice(0, 10).map((assignment, idx) => {
                                            const employee = employees.find(
                                                (e) => e.id === assignment.employee_id
                                            );
                                            const hasConflict =
                                                previewConflicts[assignment.employee_id] || false;

                                            return (
                                                <TableRow
                                                    key={idx}
                                                    className={
                                                        hasConflict
                                                            ? 'bg-yellow-50'
                                                            : ''
                                                    }
                                                >
                                                    <TableCell className="font-medium">
                                                        <div className="flex flex-col">
                                                            <span>{employee?.full_name}</span>
                                                            <span className="text-xs text-gray-500">
                                                                ID: {employee?.employee_number}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {new Date(
                                                            assignment.date
                                                        ).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell>
                                                        {formatTime(assignment.shift_start)} -{' '}
                                                        {formatTime(assignment.shift_end)}
                                                    </TableCell>
                                                    <TableCell>
                                                        {calculateShiftDuration(
                                                            assignment.shift_start,
                                                            assignment.shift_end
                                                        )}
                                                        h
                                                    </TableCell>
                                                    <TableCell>
                                                        {assignment.location || '-'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {hasConflict ? (
                                                            <Badge variant="destructive">
                                                                <AlertTriangle className="h-3 w-3 mr-1" />
                                                                Conflict
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline">Ready</Badge>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-4">
                                                No assignments to preview
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {previewAssignments.length > 10 && (
                            <p className="text-xs text-gray-500 text-center">
                                Showing 10 of {previewAssignments.length} assignments
                            </p>
                        )}
                    </div>
                )}

                {/* Processing Step */}
                {operationStep === 'processing' && (
                    <div className="flex flex-col items-center justify-center py-8 space-y-4">
                        <div className="animate-spin">
                            <Clock className="h-8 w-8 text-blue-600" />
                        </div>
                        <p className="text-sm font-medium">
                            Creating {previewAssignments.length} assignments...
                        </p>
                        <p className="text-xs text-gray-500">
                            This may take a few moments
                        </p>
                    </div>
                )}

                <DialogFooter className="gap-2">
                    {operationStep === 'form' && (
                        <>
                            <Button variant="outline" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handlePreview}
                                disabled={
                                    !formData.employees.length ||
                                    !formData.date_from ||
                                    !formData.date_to ||
                                    !formData.schedule_id
                                }
                            >
                                Preview Assignments
                            </Button>
                        </>
                    )}

                    {operationStep === 'preview' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setOperationStep('form')}
                                disabled={isLoading}
                            >
                                Back
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleReset}
                                disabled={isLoading}
                            >
                                Clear
                            </Button>
                            <Button
                                onClick={handleConfirm}
                                disabled={isLoading}
                            >
                                {isLoading ? 'Creating...' : 'Create Assignments'}
                            </Button>
                        </>
                    )}

                    {operationStep === 'processing' && (
                        <Button disabled>
                            <Clock className="h-4 w-4 mr-2 animate-spin" />
                            Processing...
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
