import React, { useState, useMemo } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Search, Clock, MapPin, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Department, EmployeeReference } from '@/types/workforce-pages';

interface BulkAssignProps {
    departments: Department[];
    employees: EmployeeReference[];
    schedules: Array<{ id: number; name: string }>;
}

interface BulkAssignFormData {
    employee_ids: number[];
    schedule_id: string;
    shift_start: string;
    shift_end: string;
    date_from: string;
    date_to: string;
    shift_type: string;
    location: string;
    is_overtime: boolean;
    department_id: string;
    allow_conflicts?: boolean;
}

interface ConflictInfo {
    employee_id: number;
    employee_name: string;
    conflicting_dates: string[];
    conflict_count: number;
}

export default function BulkAssignPage() {
    const { employees: initialEmployees, schedules } = usePage().props as unknown as BulkAssignProps;

    const [formData, setFormData] = useState<BulkAssignFormData>({
        employee_ids: [],
        schedule_id: '',
        shift_start: '09:00',
        shift_end: '17:00',
        date_from: '',
        date_to: '',
        shift_type: 'standard',
        location: '',
        is_overtime: false,
        department_id: 'all',
        allow_conflicts: false,
    });

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [searchText, setSearchText] = useState('');
    const [selectAll, setSelectAll] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [detectedConflicts, setDetectedConflicts] = useState<ConflictInfo[]>([]);
    const [hasCheckedConflicts, setHasCheckedConflicts] = useState(false);

    const breadcrumb = [
        { title: 'HR', href: '/hr' },
        { title: 'Workforce', href: '/hr/workforce' },
        { title: 'Shift Assignments', href: '/hr/workforce/assignments' },
        { title: 'Bulk Assign', href: '/hr/workforce/assignments/bulk-assign' },
    ];

    // Get unique departments from employees
    const employeeDepartments = Array.from(
        new Set(initialEmployees.map((e) => e.department_name))
    ).sort();

    // Filter employees based on search and department
    const filteredEmployees = useMemo(() => {
        return (initialEmployees || []).filter((emp) => {
            const matchesSearch =
                !searchText ||
                emp.full_name.toLowerCase().includes(searchText.toLowerCase()) ||
                emp.employee_number.toLowerCase().includes(searchText.toLowerCase());

            const matchesDepartment =
                formData.department_id === 'all' || emp.department_name === formData.department_id;

            return matchesSearch && matchesDepartment;
        });
    }, [initialEmployees, searchText, formData.department_id]);

    // Get selected schedule details
    const selectedSchedule = useMemo(() => {
        if (!formData.schedule_id) return null;
        return schedules.find((s) => s.id === parseInt(formData.schedule_id));
    }, [schedules, formData.schedule_id]);

    // Calculate total assignments that will be created
    const totalAssignments = useMemo(() => {
        if (!formData.date_from || !formData.date_to || formData.employee_ids.length === 0) {
            return 0;
        }

        const start = new Date(formData.date_from);
        const end = new Date(formData.date_to);
        const daysInRange = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;

        return formData.employee_ids.length * daysInRange;
    }, [formData.date_from, formData.date_to, formData.employee_ids.length]);

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (formData.employee_ids.length === 0) {
            newErrors.employee_ids = 'Please select at least one employee';
        }

        if (!formData.schedule_id) {
            newErrors.schedule_id = 'Please select a shift schedule';
        }

        if (!formData.date_from) {
            newErrors.date_from = 'Start date is required';
        }

        if (!formData.date_to) {
            newErrors.date_to = 'End date is required';
        }

        if (formData.date_from && formData.date_to && formData.date_to < formData.date_from) {
            newErrors.date_to = 'End date must be after start date';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    // Check for conflicts before submission
    const checkForConflicts = async () => {
        if (!validateForm()) {
            return;
        }

        setIsLoading(true);
        setSubmitError(null);

        try {
            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!csrfToken) {
                throw new Error('CSRF token not found in page');
            }

            const response = await fetch('/hr/workforce/assignments/check-bulk-conflicts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    employee_ids: formData.employee_ids,
                    date_from: formData.date_from,
                    date_to: formData.date_to,
                    shift_start: formData.shift_start,
                    shift_end: formData.shift_end,
                }),
            });

            if (!response.ok) {
                const errorData = await response.text();
                console.error('Conflict check error:', {
                    status: response.status,
                    body: errorData,
                });
                throw new Error(`HTTP ${response.status}: Unable to check conflicts`);
            }

            const data = await response.json();
            
            // Ensure conflicts data is properly structured
            const conflicts = Array.isArray(data.conflicts)
                ? data.conflicts.map((conflict) => ({
                    ...conflict,
                    conflicting_dates: Array.isArray(conflict.conflicting_dates)
                        ? conflict.conflicting_dates
                        : [],
                }))
                : [];

            setDetectedConflicts(conflicts);
            setHasCheckedConflicts(true);

            if (conflicts.length > 0) {
                setFormData((prev) => ({ ...prev, allow_conflicts: false }));
            }
        } catch (error) {
            setSubmitError(error instanceof Error ? error.message : 'An error occurred while checking conflicts');
        } finally {
            setIsLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitError(null);

        if (!validateForm()) {
            return;
        }

        // If conflicts were detected and user hasn't confirmed override, check first
        if (detectedConflicts.length > 0 && !formData.allow_conflicts) {
            setSubmitError('Please review conflicts and confirm to proceed');
            return;
        }

        setIsLoading(true);

        try {
            // Convert time format HH:mm to HH:mm:ss for database
            const shiftStart = formData.shift_start.length === 5 ? `${formData.shift_start}:00` : formData.shift_start;
            const shiftEnd = formData.shift_end.length === 5 ? `${formData.shift_end}:00` : formData.shift_end;

            router.post(
                '/hr/workforce/assignments/bulk',
                {
                    employee_ids: formData.employee_ids,
                    schedule_id: parseInt(formData.schedule_id),
                    date_from: formData.date_from,
                    date_to: formData.date_to,
                    shift_start: shiftStart,
                    shift_end: shiftEnd,
                    shift_type: formData.shift_type,
                    location: formData.location || null,
                    is_overtime: formData.is_overtime,
                    department_id: formData.department_id !== 'all' ? parseInt(formData.department_id) : null,
                    allow_conflicts: formData.allow_conflicts,
                },
                {
                    onError: (errors) => {
                        setSubmitError(
                            typeof errors === 'string'
                                ? errors
                                : Object.values(errors).flat().join(', ')
                        );
                    },
                }
            );
        } catch (error) {
            setSubmitError(error instanceof Error ? error.message : 'An error occurred');
        } finally {
            setIsLoading(false);
        }
    };

    const handleSelectAllChange = (checked: boolean) => {
        setSelectAll(checked);
        if (checked) {
            setFormData((prev) => ({
                ...prev,
                employee_ids: filteredEmployees.map((e) => e.id),
            }));
        } else {
            setFormData((prev) => ({
                ...prev,
                employee_ids: [],
            }));
        }
    };

    const handleEmployeeToggle = (employeeId: number, checked: boolean) => {
        setFormData((prev) => ({
            ...prev,
            employee_ids: checked
                ? [...prev.employee_ids, employeeId]
                : prev.employee_ids.filter((id) => id !== employeeId),
        }));
    };

    const handleCancel = () => {
        router.get('/hr/workforce/assignments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumb}>
            <Head title="Bulk Assign Shifts" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" onClick={handleCancel}>
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Bulk Assign Shifts</h1>
                        <p className="text-gray-600 mt-1">Assign shifts to multiple employees across a date range</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {submitError && (
                        <Alert variant="destructive">
                            <AlertDescription>{submitError}</AlertDescription>
                        </Alert>
                    )}

                    {/* Conflict Detection Section */}
                    {hasCheckedConflicts && detectedConflicts.length > 0 && (
                        <Card className="border-yellow-200 bg-yellow-50">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-yellow-900">
                                    <AlertTriangle className="h-5 w-5" />
                                    Scheduling Conflicts Detected
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-yellow-800">
                                    {detectedConflicts.length} employee(s) have existing assignments that conflict with the requested dates:
                                </p>
                                
                                <div className="space-y-3 max-h-64 overflow-y-auto">
                                    {detectedConflicts.map((conflict) => (
                                        <div key={conflict.employee_id} className="bg-white rounded border border-yellow-200 p-3">
                                            <div className="flex justify-between items-start mb-2">
                                                <div>
                                                    <p className="font-semibold text-yellow-900">{conflict.employee_name}</p>
                                                    <p className="text-xs text-yellow-700">{conflict.conflict_count} conflict(s) found</p>
                                                </div>
                                                <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                                                    {conflict.conflict_count}
                                                </Badge>
                                            </div>
                                            <div className="text-xs text-yellow-700 space-y-1">
                                                <p className="font-semibold mb-1">Conflicting dates:</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {conflict.conflicting_dates.slice(0, 5).map((date) => (
                                                        <span key={date} className="bg-yellow-100 px-2 py-1 rounded">
                                                            {date}
                                                        </span>
                                                    ))}
                                                    {conflict.conflicting_dates.length > 5 && (
                                                        <span className="bg-yellow-100 px-2 py-1 rounded">
                                                            +{conflict.conflicting_dates.length - 5} more
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="border-t border-yellow-200 pt-4">
                                    <label className="flex items-center gap-3 cursor-pointer p-3 rounded hover:bg-yellow-100 transition">
                                        <Checkbox
                                            checked={formData.allow_conflicts}
                                            onCheckedChange={(checked) =>
                                                setFormData((prev) => ({ ...prev, allow_conflicts: !!checked }))
                                            }
                                            disabled={isLoading}
                                        />
                                        <span className="text-sm font-medium text-yellow-900">
                                            I confirm and take responsibility for overriding these conflicts
                                        </span>
                                    </label>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* No Conflicts Section */}
                    {hasCheckedConflicts && detectedConflicts.length === 0 && (
                        <Card className="border-green-200 bg-green-50">
                            <CardContent className="pt-6">
                                <div className="flex items-center gap-3">
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                    <div>
                                        <p className="font-semibold text-green-900">No Conflicts Detected</p>
                                        <p className="text-sm text-green-800">All selected employees are available for the requested dates.</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <div className="grid grid-cols-3 gap-6">
                        {/* Column 1: Employee Selection */}
                        <Card className="col-span-1">
                            <CardHeader>
                                <CardTitle className="text-lg">Select Employees</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="flex items-center gap-2 mb-3">
                                        <Checkbox
                                            checked={selectAll}
                                            onCheckedChange={handleSelectAllChange}
                                            disabled={isLoading}
                                        />
                                        <span className="text-sm font-medium">Select All</span>
                                    </label>
                                </div>

                                {errors.employee_ids && (
                                    <p className="text-sm text-red-600">{errors.employee_ids}</p>
                                )}

                                {/* Department Filter */}
                                <div className="space-y-2">
                                    <Label className="text-sm">Department</Label>
                                    <Select
                                        value={formData.department_id}
                                        onValueChange={(value) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                department_id: value,
                                                employee_ids: [],
                                            }))
                                        }
                                        disabled={isLoading}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Filter by department..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Departments</SelectItem>
                                            {employeeDepartments.filter((d) => d).map((dept) => (
                                                <SelectItem key={dept} value={dept || ''}>
                                                    {dept}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Search */}
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        placeholder="Search employees..."
                                        value={searchText}
                                        onChange={(e) => setSearchText(e.target.value)}
                                        className="pl-10"
                                        disabled={isLoading}
                                    />
                                </div>

                                {/* Employee List */}
                                <div className="border rounded-lg bg-white max-h-[500px] overflow-y-auto">
                                    <div className="space-y-2 p-3">
                                        {filteredEmployees.length === 0 ? (
                                            <p className="text-sm text-gray-500 py-4 text-center">
                                                No employees found
                                            </p>
                                        ) : (
                                            filteredEmployees.map((employee) => (
                                                <label
                                                    key={employee.id}
                                                    className="flex items-start gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer"
                                                >
                                                    <Checkbox
                                                        checked={formData.employee_ids.includes(employee.id)}
                                                        onCheckedChange={(checked) =>
                                                            handleEmployeeToggle(employee.id, !!checked)
                                                        }
                                                        disabled={isLoading}
                                                        className="mt-1"
                                                    />
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-gray-900">
                                                            {employee.full_name}
                                                        </p>
                                                        <p className="text-xs text-gray-500">
                                                            {employee.employee_number}
                                                        </p>
                                                    </div>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                </div>

                                {formData.employee_ids.length > 0 && (
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
                                        <p className="text-sm font-semibold text-blue-800">
                                            ✓ {formData.employee_ids.length} employee(s) selected
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Column 2: Date and Schedule Selection */}
                        <Card className="col-span-1">
                            <CardHeader>
                                <CardTitle className="text-lg">Schedule & Dates</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Schedule Selection */}
                                <div className="space-y-2">
                                    <Label>Shift Schedule *</Label>
                                    <Select
                                        value={formData.schedule_id}
                                        onValueChange={(value) =>
                                            setFormData((prev) => ({ ...prev, schedule_id: value }))
                                        }
                                        disabled={isLoading}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a schedule..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {schedules.map((schedule) => (
                                                <SelectItem key={schedule.id} value={schedule.id.toString()}>
                                                    {schedule.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.schedule_id && (
                                        <p className="text-sm text-red-600">{errors.schedule_id}</p>
                                    )}
                                </div>

                                {/* Shift Times */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Shift Start Time *</Label>
                                        <Input
                                            type="time"
                                            value={formData.shift_start}
                                            onChange={(e) =>
                                                setFormData((prev) => ({ ...prev, shift_start: e.target.value }))
                                            }
                                            disabled={isLoading}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Shift End Time *</Label>
                                        <Input
                                            type="time"
                                            value={formData.shift_end}
                                            onChange={(e) =>
                                                setFormData((prev) => ({ ...prev, shift_end: e.target.value }))
                                            }
                                            disabled={isLoading}
                                        />
                                    </div>
                                </div>

                                {/* Date Range */}
                                <div className="space-y-2">
                                    <Label>Start Date *</Label>
                                    <Input
                                        type="date"
                                        value={formData.date_from}
                                        onChange={(e) =>
                                            setFormData((prev) => ({ ...prev, date_from: e.target.value }))
                                        }
                                        disabled={isLoading}
                                    />
                                    {errors.date_from && (
                                        <p className="text-sm text-red-600">{errors.date_from}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label>End Date *</Label>
                                    <Input
                                        type="date"
                                        value={formData.date_to}
                                        onChange={(e) =>
                                            setFormData((prev) => ({ ...prev, date_to: e.target.value }))
                                        }
                                        disabled={isLoading}
                                    />
                                    {errors.date_to && (
                                        <p className="text-sm text-red-600">{errors.date_to}</p>
                                    )}
                                </div>

                                {/* Shift Type */}
                                <div className="space-y-2">
                                    <Label>Shift Type</Label>
                                    <Select
                                        value={formData.shift_type}
                                        onValueChange={(value) =>
                                            setFormData((prev) => ({ ...prev, shift_type: value }))
                                        }
                                        disabled={isLoading}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="standard">Standard</SelectItem>
                                            <SelectItem value="morning">Morning</SelectItem>
                                            <SelectItem value="afternoon">Afternoon</SelectItem>
                                            <SelectItem value="evening">Evening</SelectItem>
                                            <SelectItem value="night">Night</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Column 3: Additional Options & Summary */}
                        <Card className="col-span-1">
                            <CardHeader>
                                <CardTitle className="text-lg">Options & Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Location */}
                                <div className="space-y-2">
                                    <Label className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4" />
                                        Location
                                    </Label>
                                    <Input
                                        placeholder="e.g., Main Office, Remote"
                                        value={formData.location}
                                        onChange={(e) =>
                                            setFormData((prev) => ({ ...prev, location: e.target.value }))
                                        }
                                        disabled={isLoading}
                                    />
                                </div>

                                {/* Overtime */}
                                <div className="space-y-2">
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <Checkbox
                                            checked={formData.is_overtime}
                                            onCheckedChange={(checked) =>
                                                setFormData((prev) => ({ ...prev, is_overtime: !!checked }))
                                            }
                                            disabled={isLoading}
                                        />
                                        <span className="text-sm font-medium">Mark as Overtime</span>
                                    </label>
                                </div>

                                {/* Summary */}
                                {totalAssignments > 0 && (
                                    <Card className="bg-green-50 border-green-200">
                                        <CardContent className="pt-4">
                                            <p className="text-sm text-gray-600 mb-2">Assignment Summary</p>
                                            <div className="space-y-2">
                                                <div className="flex justify-between text-sm">
                                                    <span>Employees:</span>
                                                    <Badge variant="outline">
                                                        {formData.employee_ids.length}
                                                    </Badge>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span>Days:</span>
                                                    <Badge variant="outline">
                                                        {formData.date_from && formData.date_to
                                                            ? Math.ceil(
                                                                (new Date(formData.date_to).getTime() -
                                                                    new Date(formData.date_from).getTime()) /
                                                                    (1000 * 60 * 60 * 24)
                                                            ) + 1
                                                            : 0}
                                                    </Badge>
                                                </div>
                                                <div className="flex justify-between text-sm font-semibold border-t pt-2">
                                                    <span>Total Assignments:</span>
                                                    <Badge className="bg-green-600 hover:bg-green-700">
                                                        {totalAssignments}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-3 justify-end pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleCancel}
                            disabled={isLoading}
                        >
                            Cancel
                        </Button>
                        {!hasCheckedConflicts ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={checkForConflicts}
                                disabled={isLoading || formData.employee_ids.length === 0 || totalAssignments === 0}
                                className="gap-2"
                            >
                                <AlertTriangle className="h-4 w-4" />
                                Check for Conflicts
                            </Button>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setHasCheckedConflicts(false);
                                        setDetectedConflicts([]);
                                        setFormData((prev) => ({ ...prev, allow_conflicts: false }));
                                    }}
                                    disabled={isLoading}
                                >
                                    Re-check
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        isLoading ||
                                        formData.employee_ids.length === 0 ||
                                        totalAssignments === 0 ||
                                        (detectedConflicts.length > 0 && !formData.allow_conflicts)
                                    }
                                >
                                    {isLoading ? 'Creating...' : `Create ${totalAssignments} Assignment${totalAssignments !== 1 ? 's' : ''}`}
                                </Button>
                            </>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
