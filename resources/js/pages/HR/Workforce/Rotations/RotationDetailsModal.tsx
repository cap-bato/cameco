import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { EmployeeRotation } from '@/types/workforce-pages';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Calendar, Users, FileText, Copy, Trash2, Plus } from 'lucide-react';

interface RotationDetailsModalProps {
    isOpen: boolean;
    onClose: () => void;
    rotation: EmployeeRotation | null;
    onEdit?: (rotation: EmployeeRotation) => void;
    onDelete?: (id: number) => void;
    onDuplicate?: (rotation: EmployeeRotation) => void;
    onAssignEmployees?: (rotation: EmployeeRotation) => void;
}

interface AssignedEmployee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    department_name?: string;
    effective_date?: string;
}

export function RotationDetailsModal({
    isOpen,
    onClose,
    rotation,
    onEdit,
    onDelete,
    onDuplicate,
    onAssignEmployees,
}: RotationDetailsModalProps) {
    const [assignedEmployees, setAssignedEmployees] = useState<AssignedEmployee[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Use useCallback to memoize the function and include it in the dependency array
    const loadAssignedEmployees = useCallback(async () => {
        if (!rotation) return;
        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/hr/workforce/rotations/${rotation.id}/api/assigned-employees`);
            if (!response.ok) {
                throw new Error('Failed to load assigned employees');
            }
            const data = await response.json();
            setAssignedEmployees(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
        } finally {
            setIsLoading(false);
        }
    }, [rotation]);

    useEffect(() => {
        if (isOpen && rotation) {
            loadAssignedEmployees();
        }
    }, [isOpen, rotation, loadAssignedEmployees]);

    if (!rotation) return null;

    const getPatternDisplay = () => {
        const patternData = typeof rotation.pattern_json === 'string'
            ? JSON.parse(rotation.pattern_json)
            : rotation.pattern_json;

        const work_days = patternData?.work_days || patternData?.workDays || 0;
        const rest_days = patternData?.rest_days || patternData?.restDays || 0;

        if (rotation.pattern_type === 'custom' && (work_days > 0 || rest_days > 0)) {
            return `${work_days}w / ${rest_days}r`;
        }
        return rotation.pattern_type?.toUpperCase() || 'UNKNOWN';
    };

    const getStatusColor = () => {
        return rotation.is_active
            ? 'bg-green-100 text-green-800 border-green-300'
            : 'bg-gray-100 text-gray-800 border-gray-300';
    };

    const getPatternVisualization = () => {
        const patternData = typeof rotation.pattern_json === 'string'
            ? JSON.parse(rotation.pattern_json)
            : rotation.pattern_json;

        if (!patternData?.pattern || !Array.isArray(patternData.pattern)) {
            return null;
        }

        return patternData.pattern.slice(0, 14).map((day: number, index: number) => (
            <div
                key={index}
                className={`h-8 w-full rounded text-xs font-bold flex items-center justify-center text-white ${
                    day === 1 ? 'bg-blue-500' : 'bg-gray-300'
                }`}
                title={day === 1 ? 'Work' : 'Rest'}
            >
                {day === 1 ? 'W' : 'R'}
            </div>
        ));
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center justify-between">
                        <span>{rotation.name}</span>
                        <Badge className={`${getStatusColor()} border`}>
                            {rotation.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </DialogTitle>
                    <DialogDescription>
                        {rotation.department_name && `Department: ${rotation.department_name}`}
                    </DialogDescription>
                </DialogHeader>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="space-y-6">
                    {/* Rotation Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Rotation Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Pattern Info */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-gray-600 font-medium">Pattern Type</p>
                                    <p className="text-lg font-semibold mt-1">{getPatternDisplay()}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 font-medium">Status</p>
                                    <p className="text-lg font-semibold mt-1">
                                        {rotation.is_active ? '🟢 Active' : '⚪ Inactive'}
                                    </p>
                                </div>
                            </div>

                            {/* Pattern Details */}
                            {(() => {
                                const patternData = typeof rotation.pattern_json === 'string'
                                    ? JSON.parse(rotation.pattern_json)
                                    : rotation.pattern_json;
                                const work_days = patternData?.work_days || patternData?.workDays || 0;
                                const rest_days = patternData?.rest_days || patternData?.restDays || 0;
                                const cycle_length = patternData?.cycle_length || patternData?.pattern?.length || 0;

                                return (
                                    <div className="space-y-2">
                                        <p className="text-sm text-gray-600 font-medium">Work / Rest Schedule</p>
                                        <div className="bg-gray-50 p-3 rounded border">
                                            <p className="text-center font-semibold">{work_days} work / {rest_days} rest days</p>
                                            <p className="text-center text-sm text-gray-600">Cycle: {cycle_length} days</p>
                                        </div>
                                    </div>
                                );
                            })()}

                            {/* Description */}
                            {rotation.description && (
                                <div className="space-y-2">
                                    <p className="text-sm text-gray-600 font-medium flex items-center gap-2">
                                        <FileText className="h-4 w-4" />
                                        Description
                                    </p>
                                    <p className="text-sm bg-gray-50 p-3 rounded">{rotation.description}</p>
                                </div>
                            )}

                            {/* Pattern Visualization */}
                            {getPatternVisualization() && (
                                <div className="space-y-2">
                                    <p className="text-sm text-gray-600 font-medium">Pattern Visualization (First 14 Days)</p>
                                    <div className="grid grid-cols-7 gap-1 bg-gray-50 p-3 rounded">
                                        {getPatternVisualization()}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Assigned Employees */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Assigned Employees
                            </CardTitle>
                            <Badge variant="secondary">
                                {assignedEmployees.length}
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            {isLoading ? (
                                <div className="text-center py-6 text-gray-500">Loading assigned employees...</div>
                            ) : assignedEmployees.length > 0 ? (
                                <div className="space-y-2 max-h-64 overflow-y-auto">
                                    {assignedEmployees.map((employee, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between p-3 bg-gray-50 rounded border hover:bg-gray-100 transition"
                                        >
                                            <div className="flex-1">
                                                <p className="font-medium text-sm">
                                                    {employee.first_name} {employee.last_name}
                                                </p>
                                                <p className="text-xs text-gray-600">
                                                    {employee.employee_number}
                                                    {employee.department_name && ` • ${employee.department_name}`}
                                                </p>
                                            </div>
                                            {employee.effective_date && (
                                                <div className="text-right">
                                                    <p className="text-xs text-gray-600 flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {new Date(employee.effective_date).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-6 text-gray-500">
                                    <p className="text-sm">No employees assigned to this rotation</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Metadata */}
                    {rotation.created_at && (
                        <div className="text-xs text-gray-600 space-y-1 p-3 bg-gray-50 rounded">
                            <p>Created: {new Date(rotation.created_at).toLocaleString()}</p>
                            {rotation.updated_at && (
                                <p>Last Updated: {new Date(rotation.updated_at).toLocaleString()}</p>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter className="flex gap-2 pt-4 border-t">
                    <Button
                        variant="outline"
                        onClick={() => {
                            if (onDuplicate) onDuplicate(rotation);
                            onClose();
                        }}
                        className="gap-2"
                    >
                        <Copy className="h-4 w-4" />
                        Duplicate
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => {
                            if (onDelete) onDelete(rotation.id);
                            onClose();
                        }}
                        className="gap-2 text-red-600 hover:text-red-700"
                    >
                        <Trash2 className="h-4 w-4" />
                        Delete
                    </Button>
                    <Button
                        onClick={() => {
                            if (onEdit) onEdit(rotation);
                            onClose();
                        }}
                    >
                        Edit
                    </Button>
                    <Button
                        onClick={() => {
                            if (onAssignEmployees) onAssignEmployees(rotation);
                            onClose();
                        }}
                        className="gap-2 bg-blue-600 hover:bg-blue-700"
                    >
                        <Plus className="h-4 w-4" />
                        Assign Employees
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
