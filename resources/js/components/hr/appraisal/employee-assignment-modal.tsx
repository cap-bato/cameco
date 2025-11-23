import React, { useState, useMemo, useCallback } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Search, Users } from 'lucide-react';
import { EmployeeAssignmentFormData } from '@/types/appraisal-pages';

interface Employee {
    id: number;
    name: string;
    employee_number: string;
    department: string;
    position: string;
}

interface EmployeeAssignmentModalProps {
    isOpen: boolean;
    onClose: () => void;
    cycleId: number;
    employees: Employee[];
}

export function EmployeeAssignmentModal({
    isOpen,
    onClose,
    cycleId,
    employees,
}: EmployeeAssignmentModalProps) {
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<Set<number>>(new Set());
    const [searchQuery, setSearchQuery] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState<string>('all');
    const [dueDate, setDueDate] = useState('');
    const [notes, setNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Get unique departments from employees
    const departments = useMemo(() => {
        const depts = Array.from(new Set(employees.map((e) => e.department))).sort();
        return depts;
    }, [employees]);

    // Filter employees based on search and department
    const filteredEmployees = useMemo(() => {
        return employees.filter((employee) => {
            const matchesSearch =
                employee.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                employee.employee_number.toLowerCase().includes(searchQuery.toLowerCase());

            const matchesDepartment =
                departmentFilter === 'all' || employee.department === departmentFilter;

            return matchesSearch && matchesDepartment;
        });
    }, [employees, searchQuery, departmentFilter]);

    // Check if all filtered employees are selected
    const allSelectedInView =
        filteredEmployees.length > 0 &&
        filteredEmployees.every((emp) => selectedEmployeeIds.has(emp.id));

    // Check if some filtered employees are selected
    const someSelectedInView =
        filteredEmployees.length > 0 &&
        filteredEmployees.some((emp) => selectedEmployeeIds.has(emp.id)) &&
        !allSelectedInView;

    const handleToggleEmployee = useCallback((employeeId: number) => {
        setSelectedEmployeeIds((prev) => {
            const newSelected = new Set(prev);
            if (newSelected.has(employeeId)) {
                newSelected.delete(employeeId);
            } else {
                newSelected.add(employeeId);
            }
            return newSelected;
        });
    }, []);

    const handleSelectAll = useCallback(() => {
        setSelectedEmployeeIds((prev) => {
            const allSelected =
                filteredEmployees.length > 0 &&
                filteredEmployees.every((emp) => prev.has(emp.id));

            const newSelected = new Set(prev);
            if (allSelected) {
                // Deselect all in view
                filteredEmployees.forEach((emp) => newSelected.delete(emp.id));
            } else {
                // Select all in view
                filteredEmployees.forEach((emp) => newSelected.add(emp.id));
            }
            return newSelected;
        });
    }, [filteredEmployees]);

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (selectedEmployeeIds.size === 0) {
            newErrors.employees = 'At least one employee must be selected';
        }

        if (!dueDate) {
            newErrors.due_date = 'Due date is required';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSearchChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        setSearchQuery(e.target.value);
    }, []);

    const handleDepartmentChange = useCallback((value: string) => {
        setDepartmentFilter(value);
    }, []);

    const handleDueDateChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        setDueDate(e.target.value);
    }, []);

    const handleNotesChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
        setNotes(e.target.value);
    }, []);

    const handleSubmit = useCallback((e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        const formData: EmployeeAssignmentFormData = {
            cycle_id: cycleId,
            employee_ids: Array.from(selectedEmployeeIds),
            due_date: dueDate,
            notes: notes || undefined,
        };

        // @ts-expect-error - Type assertion for Inertia router compatibility
        router.post(`/hr/appraisals/cycles/${cycleId}/assign`, formData, {
            onSuccess: () => {
                onClose();
                setIsSubmitting(false);
                setSelectedEmployeeIds(new Set());
                setSearchQuery('');
                setDepartmentFilter('all');
                setDueDate('');
                setNotes('');
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    }, [cycleId, selectedEmployeeIds, dueDate, notes, onClose]);

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[900px] max-h-[90vh] overflow-y-auto">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Assign Employees to Cycle</DialogTitle>
                        <DialogDescription>
                            Select employees and set assignment details for this appraisal cycle
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6 py-6">
                        {/* Filters */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Search & Filter</h3>
                            <div className="grid grid-cols-2 gap-4">
                                {/* Search */}
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        placeholder="Search by name or employee #"
                                        value={searchQuery}
                                        onChange={handleSearchChange}
                                        className="pl-10"
                                    />
                                </div>

                                {/* Department Filter */}
                                <Select value={departmentFilter} onValueChange={handleDepartmentChange}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept} value={dept}>
                                                {dept}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Error Message */}
                        {errors.employees && (
                            <div className="bg-red-50 border border-red-200 rounded p-3 text-sm text-red-600">
                                {errors.employees}
                            </div>
                        )}

                        {/* Employee Selection Table */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold">
                                    Employees ({filteredEmployees.length})
                                </h3>
                                <div className="text-xs text-gray-600">
                                    Selected: {selectedEmployeeIds.size}
                                </div>
                            </div>

                            <Card className="border">
                                <CardHeader className="pb-3">
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            checked={allSelectedInView || someSelectedInView}
                                            onCheckedChange={handleSelectAll}
                                            id="select-all"
                                        />
                                        <Label
                                            htmlFor="select-all"
                                            className="text-sm font-medium cursor-pointer flex-1"
                                        >
                                            {allSelectedInView
                                                ? 'Deselect all in view'
                                                : 'Select all in view'}
                                        </Label>
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-2 max-h-[300px] overflow-y-auto">
                                    {filteredEmployees.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            <Users className="h-12 w-12 mx-auto mb-2 text-gray-300" />
                                            <p>No employees match your search</p>
                                        </div>
                                    ) : (
                                        filteredEmployees.map((employee) => (
                                            <div
                                                key={employee.id}
                                                className="flex items-center gap-3 p-3 hover:bg-gray-50 rounded"
                                            >
                                                <Checkbox
                                                    checked={selectedEmployeeIds.has(
                                                        employee.id
                                                    )}
                                                    onCheckedChange={() =>
                                                        handleToggleEmployee(employee.id)
                                                    }
                                                />
                                                <div className="flex-1">
                                                    <div className="text-sm font-medium">
                                                        {employee.name}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {employee.employee_number} •{' '}
                                                        {employee.position}
                                                    </div>
                                                </div>
                                                <div className="text-xs text-gray-400">
                                                    {employee.department}
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Assignment Details */}
                        <div className="space-y-4">
                            <h3 className="text-sm font-semibold">Assignment Details</h3>

                            {/* Due Date */}
                            <div className="space-y-2">
                                <Label htmlFor="due_date">Due Date *</Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={dueDate}
                                    onChange={handleDueDateChange}
                                    className={errors.due_date ? 'border-red-500' : ''}
                                />
                                {errors.due_date && (
                                    <p className="text-sm text-red-500">{errors.due_date}</p>
                                )}
                            </div>

                            {/* Notes */}
                            <div className="space-y-2">
                                <Label htmlFor="notes">Assignment Notes</Label>
                                <Textarea
                                    id="notes"
                                    placeholder="Add any notes or instructions for the appraisers..."
                                    value={notes}
                                    onChange={handleNotesChange}
                                    rows={4}
                                    maxLength={500}
                                />
                                <div className="text-xs text-gray-500">
                                    {notes.length}/500 characters
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting || selectedEmployeeIds.size === 0}>
                            {isSubmitting
                                ? 'Assigning...'
                                : `Assign ${selectedEmployeeIds.size} Employee${selectedEmployeeIds.size !== 1 ? 's' : ''}`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
