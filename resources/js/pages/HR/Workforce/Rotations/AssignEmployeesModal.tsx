import React, { useState, useEffect, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { EmployeeRotation } from '@/types/workforce-pages';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Search } from 'lucide-react';
import { router } from '@inertiajs/react';

interface AssignEmployeesModalProps {
    isOpen: boolean;
    onClose: () => void;
    rotation: EmployeeRotation | null;
}

interface Employee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    department_id: number;
    department_name?: string;
}

export function AssignEmployeesModal({ isOpen, onClose, rotation }: AssignEmployeesModalProps) {
    const [employees, setEmployees] = useState<Employee[]>([]);
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [departments, setDepartments] = useState<Record<number, string>>({});
    const [effectiveDate, setEffectiveDate] = useState(new Date().toISOString().split('T')[0]);

    // Load available employees when modal opens
    useEffect(() => {
        if (isOpen && rotation) {
            loadEmployees();
        }
    }, [isOpen, rotation]);

    const loadEmployees = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await fetch('/hr/workforce/rotations/available-employees');
            if (!response.ok) {
                throw new Error('Failed to load employees');
            }
            const data = await response.json();
            
            // Build department mapping
            const deptMap: Record<number, string> = {};
            data.forEach((emp: Employee) => {
                if (emp.department_id && !deptMap[emp.department_id]) {
                    deptMap[emp.department_id] = emp.department_name || `Department ${emp.department_id}`;
                }
            });
            
            setDepartments(deptMap);
            setEmployees(data);
            setSelectedEmployees([]);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred while loading employees');
        } finally {
            setIsLoading(false);
        }
    };

    // Filter employees based on search and department
    const filteredEmployees = useMemo(() => {
        return employees.filter((emp) => {
            const matchesSearch =
                !searchTerm ||
                emp.employee_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
                `${emp.first_name} ${emp.last_name}`.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesDept =
                departmentFilter === 'all' || emp.department_id.toString() === departmentFilter;

            return matchesSearch && matchesDept;
        });
    }, [employees, searchTerm, departmentFilter]);

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            setSelectedEmployees(filteredEmployees.map((e) => e.id));
        } else {
            setSelectedEmployees([]);
        }
    };

    const handleSelectEmployee = (employeeId: number, checked: boolean) => {
        if (checked) {
            setSelectedEmployees([...selectedEmployees, employeeId]);
        } else {
            setSelectedEmployees(selectedEmployees.filter((id) => id !== employeeId));
        }
    };

    const handleAssign = () => {
        if (!rotation || selectedEmployees.length === 0) return;

        setIsLoading(true);
        setError(null);

        // Use Inertia router.post directly for proper SPA handling
        router.post(
            `/hr/workforce/rotations/${rotation.id}/assign-employees`,
            {
                employee_ids: selectedEmployees,
                effective_date: effectiveDate,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    setIsLoading(false);
                },
                onError: (errors) => {
                    const errorMessages = Object.values(errors)
                        .flat()
                        .join(', ');
                    setError(errorMessages || 'Failed to assign employees');
                    setIsLoading(false);
                },
                onFinish: () => {
                    setIsLoading(false);
                },
            }
        );
    };

    const allSelected = filteredEmployees.length > 0 && selectedEmployees.length === filteredEmployees.length;
    const someSelected = selectedEmployees.length > 0 && !allSelected;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Assign Employees to Rotation</DialogTitle>
                    <DialogDescription>
                        {rotation ? `Assigning to: ${rotation.name}` : 'Select employees to assign to this rotation'}
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="space-y-4">
                    {/* Effective Date */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Effective Date</label>
                        <Input
                            type="date"
                            value={effectiveDate}
                            onChange={(e) => setEffectiveDate(e.target.value)}
                            disabled={isLoading}
                        />
                    </div>

                    {/* Search and Filter */}
                    <div className="space-y-3">
                        <div className="flex gap-2">
                            <div className="flex-1 relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search by employee number or name..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    disabled={isLoading}
                                    className="pl-10"
                                />
                            </div>
                            <Select value={departmentFilter} onValueChange={setDepartmentFilter} disabled={isLoading}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Departments</SelectItem>
                                    {Object.entries(departments).map(([id, name]) => (
                                        <SelectItem key={id} value={id}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Select All Checkbox */}
                        <div className="flex items-center gap-2 p-2 bg-gray-50 rounded border">
                            <Checkbox
                                checked={allSelected || someSelected}
                                onCheckedChange={handleSelectAll}
                                disabled={isLoading || filteredEmployees.length === 0}
                                id="select-all"
                            />
                            <label htmlFor="select-all" className="text-sm font-medium cursor-pointer flex-1">
                                Select All ({selectedEmployees.length} of {filteredEmployees.length} selected)
                            </label>
                        </div>
                    </div>

                    {/* Employee List */}
                    <div className="border rounded-lg max-h-96 overflow-y-auto">
                        {isLoading ? (
                            <div className="p-4 text-center text-gray-500">Loading employees...</div>
                        ) : filteredEmployees.length > 0 ? (
                            <div className="space-y-1">
                                {filteredEmployees.map((employee) => (
                                    <div
                                        key={employee.id}
                                        className="flex items-center gap-3 p-3 hover:bg-gray-50 border-b last:border-b-0"
                                    >
                                        <Checkbox
                                            checked={selectedEmployees.includes(employee.id)}
                                            onCheckedChange={(checked) =>
                                                handleSelectEmployee(employee.id, !!checked)
                                            }
                                            disabled={isLoading}
                                            id={`employee-${employee.id}`}
                                        />
                                        <label htmlFor={`employee-${employee.id}`} className="flex-1 cursor-pointer">
                                            <div className="font-medium text-sm">
                                                {employee.first_name} {employee.last_name}
                                            </div>
                                            <div className="text-xs text-gray-600">
                                                {employee.employee_number} • {departments[employee.department_id] || 'Unknown'}
                                            </div>
                                        </label>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="p-4 text-center text-gray-500">No employees found</div>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={isLoading}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleAssign}
                        disabled={isLoading || selectedEmployees.length === 0}
                    >
                        {isLoading ? 'Assigning...' : `Assign ${selectedEmployees.length} Employee${selectedEmployees.length !== 1 ? 's' : ''}`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
