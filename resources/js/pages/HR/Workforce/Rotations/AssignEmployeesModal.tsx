import React, { useState, useEffect, useMemo, useCallback } from 'react';
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
import { EmployeeRotation, WorkSchedule as WorkScheduleType } from '@/types/workforce-pages';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Search, AlertTriangle, CheckCircle } from 'lucide-react';
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

interface Conflict {
    date: string;
    type: string;
    severity: 'warning' | 'error';
    message: string;
    rotation_says: string;
    schedule_says: string;
}

interface EmployeeConflictResult {
    employee_id: number;
    name: string;
    rotation_name: string | null;
    has_rotation: boolean;
    conflict_count: number;
    error_count: number;
    warning_count: number;
    conflicts: Conflict[];
}

interface ConflictCheckResponse {
    success: boolean;
    has_errors: boolean;
    has_warnings: boolean;
    total_conflicts: number;
    error_count: number;
    warning_count: number;
    employees: EmployeeConflictResult[];
}

// Step enum for modal navigation
enum Step {
    SELECT_EMPLOYEES = 1,
    SELECT_SCHEDULE = 2,
    REVIEW_CONFLICTS = 3,
}

export function AssignEmployeesModal({ isOpen, onClose, rotation }: AssignEmployeesModalProps) {
    const [employees, setEmployees] = useState<Employee[]>([]);
    const [schedules, setSchedules] = useState<WorkScheduleType[]>([]);
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [selectedScheduleId, setSelectedScheduleId] = useState<number | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [departments, setDepartments] = useState<Record<number, string>>({});
    const [effectiveDate, setEffectiveDate] = useState(new Date().toISOString().split('T')[0]);
    const [durationDays, setDurationDays] = useState(60);
    
    // Phase 3: Conflict detection state
    const [currentStep, setCurrentStep] = useState<Step>(Step.SELECT_EMPLOYEES);
    const [conflictData, setConflictData] = useState<ConflictCheckResponse | null>(null);
    const [expandedEmployee, setExpandedEmployee] = useState<number | null>(null);

    const loadEmployees = useCallback(async () => {
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
    }, []);

    const loadSchedules = useCallback(async () => {
        try {
            const params = new URLSearchParams({ active_only: '1', include_templates: '0' });
            if (rotation?.department_id) {
                params.append('department_id', rotation.department_id.toString());
            }

            const response = await fetch(`/hr/workforce/schedules/api/list?${params.toString()}`);
            if (!response.ok) {
                throw new Error('Failed to load schedules');
            }
            const data: WorkScheduleType[] = await response.json();

            const uniqueSchedules = new Map<string, WorkScheduleType>();
            data.forEach((schedule) => {
                const key = `${schedule.name.toLowerCase()}-${schedule.department_id ?? 'none'}`;
                if (!uniqueSchedules.has(key)) {
                    uniqueSchedules.set(key, schedule);
                }
            });

            setSchedules(Array.from(uniqueSchedules.values()).sort((a, b) => a.name.localeCompare(b.name)));
        } catch (err) {
            console.error('Error loading schedules:', err);
            setSchedules([]);
        }
    }, [rotation]);

    // Load employees and schedules when modal opens
    useEffect(() => {
        if (isOpen) {
            loadEmployees();
            loadSchedules();
        }
    }, [isOpen, loadEmployees, loadSchedules]);

    // Check for conflicts when schedule is selected
    const checkConflicts = async () => {
        if (!rotation || selectedEmployees.length === 0 || !selectedScheduleId) {
            setError('Please select employees and a schedule');
            return;
        }

        setIsLoading(true);
        setError(null);
        
        try {
            // Use window.Laravel if available, otherwise fallback to meta tag
            let csrfToken = (window as any).Laravel?.csrfToken;
            
            if (!csrfToken) {
                const csrfTokenMeta = document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
                csrfToken = csrfTokenMeta?.content;
            }
            
            if (!csrfToken) {
                throw new Error('CSRF token not found. Please refresh the page.');
            }

            const response = await fetch(`/hr/workforce/rotations/${rotation.id}/check-conflicts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include', // Changed from 'same-origin' to 'include'
                body: JSON.stringify({
                    employee_ids: selectedEmployees,
                    work_schedule_id: selectedScheduleId,
                    effective_date: effectiveDate,
                    duration_days: durationDays,
                }),
            });

            if (!response.ok) {
                if (response.status === 419) {
                    throw new Error('Your session has expired. Please refresh the page and try again.');
                }
                
                let errorMessage = 'Failed to check conflicts';
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorData.error || errorMessage;
                    if (errorData.errors) {
                        errorMessage = Object.values(errorData.errors).flat().join(', ');
                    }
                } catch {
                    errorMessage = `Server error: ${response.status} ${response.statusText}`;
                }
                throw new Error(errorMessage);
            }

            const data: ConflictCheckResponse = await response.json();
            setConflictData(data);
            setCurrentStep(Step.REVIEW_CONFLICTS);
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to check conflicts';
            setError(errorMessage);
            console.error('Conflict check error:', err);
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
                    // Reset modal state
                    setCurrentStep(Step.SELECT_EMPLOYEES);
                    setSelectedEmployees([]);
                    setSelectedScheduleId(null);
                    setConflictData(null);
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

    const handleBackToScheduleSelection = () => {
        setCurrentStep(Step.SELECT_SCHEDULE);
        setConflictData(null);
    };

    const handleBackToEmployeeSelection = () => {
        setCurrentStep(Step.SELECT_EMPLOYEES);
    };

    const allSelected = filteredEmployees.length > 0 && selectedEmployees.length === filteredEmployees.length;
    const someSelected = selectedEmployees.length > 0 && !allSelected;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {currentStep === Step.SELECT_EMPLOYEES && 'Select Employees to Assign'}
                        {currentStep === Step.SELECT_SCHEDULE && 'Select Schedule & Duration'}
                        {currentStep === Step.REVIEW_CONFLICTS && 'Review Rotation-Schedule Conflicts'}
                    </DialogTitle>
                    <DialogDescription>
                        {currentStep === Step.SELECT_EMPLOYEES && (rotation ? `Assigning to: ${rotation.name}` : 'Select employees to assign to this rotation')}
                        {currentStep === Step.SELECT_SCHEDULE && `Schedule for ${selectedEmployees.length} employee${selectedEmployees.length !== 1 ? 's' : ''}`}
                        {currentStep === Step.REVIEW_CONFLICTS && conflictData && `Showing conflicts for ${selectedEmployees.length} employee${selectedEmployees.length !== 1 ? 's' : ''}`}
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="space-y-4">
                    {/* STEP 1: SELECT EMPLOYEES */}
                    {currentStep === Step.SELECT_EMPLOYEES && (
                        <>
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
                        </>
                    )}

                    {/* STEP 2: SELECT SCHEDULE */}
                    {currentStep === Step.SELECT_SCHEDULE && (
                        <>
                            {/* Schedule Selection */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Work Schedule</label>
                                <Select
                                    value={selectedScheduleId?.toString() ?? ''}
                                    onValueChange={(value) => {
                                        const parsed = Number(value);
                                        setSelectedScheduleId(Number.isNaN(parsed) ? null : parsed);
                                    }}
                                    disabled={isLoading}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a work schedule..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {schedules.map((schedule) => (
                                            <SelectItem key={schedule.id} value={schedule.id.toString()}>
                                                {schedule.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Duration Days */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Check Duration (days)</label>
                                <Input
                                    type="number"
                                    value={durationDays}
                                    onChange={(e) => setDurationDays(parseInt(e.target.value) || 60)}
                                    min="1"
                                    max="365"
                                    disabled={isLoading}
                                />
                                <p className="text-xs text-gray-500">Recommended: 60 days to cover rotation cycles</p>
                            </div>

                            {/* Info Alert */}
                            <Alert className="bg-blue-50 border-blue-200">
                                <AlertCircle className="h-4 w-4 text-blue-600" />
                                <AlertDescription className="text-blue-800">
                                    The next step will check for conflicts between the selected rotation patterns and this schedule.
                                    We'll identify any incompatibilities so you can review them before assignment.
                                </AlertDescription>
                            </Alert>
                        </>
                    )}

                    {/* STEP 3: REVIEW CONFLICTS */}
                    {currentStep === Step.REVIEW_CONFLICTS && conflictData && (
                        <>
                            {/* Summary Banner */}
                            {conflictData.has_errors && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        ⚠️ <strong>{conflictData.error_count} error conflict{conflictData.error_count !== 1 ? 's' : ''}:</strong> Rotation expects rest days but schedule expects work. These may cause scheduling issues.
                                    </AlertDescription>
                                </Alert>
                            )}
                            {conflictData.has_warnings && !conflictData.has_errors && (
                                <Alert className="bg-yellow-50 border-yellow-200">
                                    <AlertCircle className="h-4 w-4 text-yellow-600" />
                                    <AlertDescription className="text-yellow-800">
                                        ℹ️ <strong>{conflictData.warning_count} warning conflict{conflictData.warning_count !== 1 ? 's' : ''}:</strong> Rotation expects work days but schedule has no shift. Employees won't work those days.
                                    </AlertDescription>
                                </Alert>
                            )}
                            {!conflictData.has_errors && !conflictData.has_warnings && (
                                <Alert className="bg-green-50 border-green-200">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <AlertDescription className="text-green-800">
                                        ✅ No conflicts detected! This schedule is compatible with the selected rotations.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Conflict Details */}
                            <div className="space-y-2 max-h-96 overflow-y-auto">
                                {conflictData.employees.map((emp) => (
                                    <div key={emp.employee_id} className="border rounded-lg p-3 bg-gray-50">
                                        <button
                                            onClick={() => setExpandedEmployee(expandedEmployee === emp.employee_id ? null : emp.employee_id)}
                                            className="w-full text-left flex items-center justify-between hover:bg-gray-100 p-2 rounded"
                                        >
                                            <div>
                                                <div className="font-medium text-sm">{emp.name}</div>
                                                <div className="text-xs text-gray-600">
                                                    {emp.rotation_name || 'No rotation assigned'}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {emp.error_count > 0 && (
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        {emp.error_count} error
                                                    </span>
                                                )}
                                                {emp.warning_count > 0 && (
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        {emp.warning_count} warning
                                                    </span>
                                                )}
                                                {emp.conflict_count === 0 && (
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        ✓ OK
                                                    </span>
                                                )}
                                            </div>
                                        </button>

                                        {/* Expanded Details */}
                                        {expandedEmployee === emp.employee_id && emp.conflicts.length > 0 && (
                                            <div className="mt-2 pt-2 border-t space-y-1">
                                                {emp.conflicts.map((conflict, idx) => (
                                                    <div
                                                        key={idx}
                                                        className={`text-xs p-2 rounded ${
                                                            conflict.severity === 'error'
                                                                ? 'bg-red-50 text-red-800 border-l-2 border-red-400'
                                                                : 'bg-yellow-50 text-yellow-800 border-l-2 border-yellow-400'
                                                        }`}
                                                    >
                                                        <strong>{conflict.date}:</strong> {conflict.message}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {/* Continue Button Notice */}
                            {conflictData.has_errors && (
                                <Alert className="bg-orange-50 border-orange-200">
                                    <AlertTriangle className="h-4 w-4 text-orange-600" />
                                    <AlertDescription className="text-orange-800">
                                        You can continue despite errors, but review them carefully. Errors indicate potential scheduling problems.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </>
                    )}
                </div>

                <DialogFooter>
                    {currentStep === Step.SELECT_EMPLOYEES && (
                        <>
                            <Button variant="outline" onClick={onClose} disabled={isLoading}>
                                Cancel
                            </Button>
                            <Button
                                onClick={() => setCurrentStep(Step.SELECT_SCHEDULE)}
                                disabled={isLoading || selectedEmployees.length === 0}
                            >
                                Next: Select Schedule
                            </Button>
                        </>
                    )}

                    {currentStep === Step.SELECT_SCHEDULE && (
                        <>
                            <Button variant="outline" onClick={handleBackToEmployeeSelection} disabled={isLoading}>
                                Back
                            </Button>
                            <Button
                                onClick={checkConflicts}
                                disabled={isLoading || !selectedScheduleId}
                            >
                                {isLoading ? 'Checking...' : 'Check for Conflicts'}
                            </Button>
                        </>
                    )}

                    {currentStep === Step.REVIEW_CONFLICTS && (
                        <>
                            <Button variant="outline" onClick={handleBackToScheduleSelection} disabled={isLoading}>
                                Back
                            </Button>
                            <Button
                                onClick={handleAssign}
                                disabled={isLoading}
                                variant={conflictData?.has_errors ? 'destructive' : 'default'}
                            >
                                {isLoading ? 'Assigning...' : `Assign ${selectedEmployees.length} Employee${selectedEmployees.length !== 1 ? 's' : ''}`}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
