import React from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ShiftAssignment } from '@/types/workforce-pages';
import {
    formatTime,
    calculateShiftDuration,
    getShiftTypeColorClasses,
    getStatusColorClasses,
} from '@/lib/workforce-utils';
import {
    AlertTriangle,
    Clock,
    Calendar,
    MapPin,
    User,
    Building2,
    FileText,
} from 'lucide-react';

/**
 * Props for the AssignmentDetailModal component
 */
interface AssignmentDetailModalProps {
    /** Whether the modal is open */
    isOpen: boolean;
    
    /** Callback to close the modal */
    onClose: () => void;
    
    /** The assignment to display */
    assignment: ShiftAssignment | null;
}

/**
 * AssignmentDetailModal Component
 * 
 * Displays comprehensive information about a shift assignment in a modal dialog.
 * Shows all assignment details organized in logical sections:
 * - Employee Information
 * - Assignment Details (Date, Times, Duration)
 * - Location & Type Information
 * - Status & Conflict Information
 * - Notes
 * 
 * Features:
 * - Conflict indicator with severity color
 * - Overtime badge
 * - Status badge with color coding
 * - Shift type badge
 * - Duration calculation
 * - Responsive layout
 * - Icon-based visual hierarchy
 * 
 * @param props - Component props
 * @returns The rendered detail modal
 */
export default function AssignmentDetailModal({
    isOpen,
    onClose,
    assignment,
}: AssignmentDetailModalProps) {
    if (!assignment) return null;

    const shiftDuration = calculateShiftDuration(
        assignment.shift_start,
        assignment.shift_end
    );

    const assignmentDate = new Date(assignment.date);
    const dateString = assignmentDate.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    const statusClasses = getStatusColorClasses(assignment.status);
    const shiftTypeClasses = getShiftTypeColorClasses(assignment.shift_type || 'custom');

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-3">
                        <User className="h-5 w-5 text-blue-600" />
                        {assignment.employee_name}
                    </DialogTitle>
                    <DialogDescription>
                        Shift Assignment Details - {assignment.employee_number}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Status and Badges Row */}
                    <div className="flex flex-wrap gap-2">
                        <Badge className={statusClasses}>
                            {assignment.status}
                        </Badge>
                        {assignment.is_overtime && (
                            <Badge variant="secondary" className="gap-1">
                                <Clock className="h-3 w-3" />
                                Overtime
                            </Badge>
                        )}
                        {assignment.shift_type && (
                            <Badge className={`${shiftTypeClasses.bg} ${shiftTypeClasses.text}`}>
                                {assignment.shift_type}
                            </Badge>
                        )}
                        {assignment.has_conflict && (
                            <Badge variant="destructive" className="gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                Conflict
                            </Badge>
                        )}
                    </div>

                    {/* Conflict Information (if applicable) */}
                    {assignment.has_conflict && (
                        <Card className="border-red-200 bg-red-50">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm flex items-center gap-2 text-red-900">
                                    <AlertTriangle className="h-4 w-4" />
                                    Scheduling Conflict
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm text-red-800">
                                <div>
                                    <p className="font-semibold mb-1">Issue:</p>
                                    <p>{assignment.conflict_reason || 'This assignment overlaps with an existing shift.'}</p>
                                </div>
                                
                                {assignment.conflicting_employee_name && (
                                    <div className="border-t border-red-200 pt-4">
                                        <p className="font-semibold mb-3">Conflicting Shift Details:</p>
                                        <div className="space-y-2 text-red-700 bg-white rounded p-3">
                                            <div className="flex justify-between">
                                                <span>Employee:</span>
                                                <span className="font-medium">{assignment.conflicting_employee_name}</span>
                                            </div>
                                            {assignment.conflicting_shift_date && (
                                                <div className="flex justify-between">
                                                    <span>Date:</span>
                                                    <span className="font-medium">{assignment.conflicting_shift_date}</span>
                                                </div>
                                            )}
                                            {assignment.conflicting_shift_start && assignment.conflicting_shift_end && (
                                                <div className="flex justify-between">
                                                    <span>Time:</span>
                                                    <span className="font-medium">
                                                        {formatTime(assignment.conflicting_shift_start)} - {formatTime(assignment.conflicting_shift_end)}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Assignment Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                Assignment Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Date */}
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase">
                                        Date
                                    </p>
                                    <p className="text-sm font-medium mt-1">{dateString}</p>
                                </div>

                                {/* Shift Times */}
                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase">
                                        Start Time
                                    </p>
                                    <p className="text-sm font-medium mt-1">
                                        {formatTime(assignment.shift_start)}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase">
                                        End Time
                                    </p>
                                    <p className="text-sm font-medium mt-1">
                                        {formatTime(assignment.shift_end)}
                                    </p>
                                </div>
                            </div>

                            {/* Duration and Schedule */}
                            <div className="border-t pt-4 grid grid-cols-3 gap-4">
                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase">
                                        Duration
                                    </p>
                                    <p className="text-sm font-medium mt-1">
                                        {shiftDuration} hours
                                    </p>
                                </div>

                                {assignment.schedule_name && (
                                    <div>
                                        <p className="text-xs font-semibold text-gray-500 uppercase">
                                            Schedule
                                        </p>
                                        <p className="text-sm font-medium mt-1">
                                            {assignment.schedule_name}
                                        </p>
                                    </div>
                                )}

                                {assignment.overtime_hours && assignment.overtime_hours > 0 && (
                                    <div>
                                        <p className="text-xs font-semibold text-gray-500 uppercase">
                                            Overtime Hours
                                        </p>
                                        <p className="text-sm font-medium mt-1 text-orange-600">
                                            {assignment.overtime_hours}h
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Location and Department */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <MapPin className="h-4 w-4" />
                                Location & Department
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {assignment.location && (
                                <div>
                                    <p className="text-xs font-semibold text-gray-500 uppercase">
                                        Location
                                    </p>
                                    <p className="text-sm font-medium mt-1">
                                        {assignment.location}
                                    </p>
                                </div>
                            )}

                            {assignment.department_name && (
                                <div className={assignment.location ? 'border-t pt-4' : ''}>
                                    <p className="text-xs font-semibold text-gray-500 uppercase flex items-center gap-2">
                                        <Building2 className="h-3 w-3" />
                                        Department
                                    </p>
                                    <p className="text-sm font-medium mt-1">
                                        {assignment.department_name}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Notes */}
                    {assignment.notes && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <FileText className="h-4 w-4" />
                                    Notes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">
                                    {assignment.notes}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Metadata */}
                    <Card className="bg-gray-50">
                        <CardHeader>
                            <CardTitle className="text-xs uppercase text-gray-600">
                                Metadata
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-xs text-gray-600">
                            {assignment.created_by_name && (
                                <p>
                                    <span className="font-semibold">Created by:</span>{' '}
                                    {assignment.created_by_name}
                                </p>
                            )}
                            <p>
                                <span className="font-semibold">Created:</span>{' '}
                                {new Date(assignment.created_at).toLocaleString()}
                            </p>
                            <p>
                                <span className="font-semibold">Updated:</span>{' '}
                                {new Date(assignment.updated_at).toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </DialogContent>
        </Dialog>
    );
}
