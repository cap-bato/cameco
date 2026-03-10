import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertTriangle } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import type { AttendanceRecord } from '@/types/timekeeping-pages';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

export interface CorrectionFormData {
    corrected_time_in: string;
    corrected_time_out: string;
    corrected_break_start?: string;
    corrected_break_end?: string;
    correction_reason: string;
    justification: string;
}

interface AttendanceCorrectionModalProps {
    isOpen: boolean;
    onClose: () => void;
    record: AttendanceRecord | null;
    onSave?: (data: CorrectionFormData) => void;
    isLoading?: boolean;
}

const correctionReasons = [
    { value: 'wrong_entry', label: 'Wrong Entry' },
    { value: 'machine_error', label: 'Machine Error' },
    { value: 'employee_reported', label: 'Employee Reported' },
    { value: 'manual_adjustment', label: 'Manual Adjustment' },
    { value: 'other', label: 'Other' },
];

export function AttendanceCorrectionModal({
    isOpen,
    onClose,
    record,
    onSave,
    isLoading: externalLoading = false,
}: AttendanceCorrectionModalProps) {
    const [correctedTimeIn, setCorrectedTimeIn] = useState('');
    const [correctedTimeOut, setCorrectedTimeOut] = useState('');
    const [correctedBreakStart, setCorrectedBreakStart] = useState('');
    const [correctedBreakEnd, setCorrectedBreakEnd] = useState('');
    const [reason, setReason] = useState('');
    const [justification, setJustification] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    React.useEffect(() => {
        if (isOpen && record) {
            setCorrectedTimeIn(record.time_in || '');
            setCorrectedTimeOut(record.time_out || '');
            setCorrectedBreakStart(record.break_start || '');
            setCorrectedBreakEnd(record.break_end || '');
            setReason('');
            setJustification('');
            setErrors({});
        }
    }, [isOpen, record]);

    const calculateHours = (timeIn: string, timeOut: string, breakStart?: string, breakEnd?: string) => {
        if (!timeIn || !timeOut) return 0;
        const inDate = new Date(`2000-01-01T${timeIn}`);
        const outDate = new Date(`2000-01-01T${timeOut}`);
        let hours = (outDate.getTime() - inDate.getTime()) / (1000 * 60 * 60);

        if (breakStart && breakEnd) {
            const breakStartDate = new Date(`2000-01-01T${breakStart}`);
            const breakEndDate = new Date(`2000-01-01T${breakEnd}`);
            const breakHours = (breakEndDate.getTime() - breakStartDate.getTime()) / (1000 * 60 * 60);
            hours -= breakHours;
        }

        return Math.max(0, hours);
    };

    const originalHours = record ? calculateHours(record.time_in || '', record.time_out || '') : 0;
    const correctedHours = calculateHours(correctedTimeIn, correctedTimeOut, correctedBreakStart, correctedBreakEnd);
    const hoursDifference = correctedHours - originalHours;

    const validateForm = () => {
        const newErrors: Record<string, string> = {};

        if (!correctedTimeIn) newErrors.timeIn = 'Corrected time in is required';
        if (!correctedTimeOut) newErrors.timeOut = 'Corrected time out is required';
        if (correctedTimeIn && correctedTimeOut && correctedTimeIn >= correctedTimeOut) {
            newErrors.timeRange = 'Corrected time out must be after time in';
        }
        if (!reason) newErrors.reason = 'Reason is required';
        if (!justification || justification.trim().length < 10) {
            newErrors.justification = 'Justification must be at least 10 characters';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async () => {
        if (!validateForm() || !record) return;

        // If custom onSave handler provided, use that instead
        if (onSave) {
            onSave({
                corrected_time_in: correctedTimeIn,
                corrected_time_out: correctedTimeOut,
                corrected_break_start: correctedBreakStart,
                corrected_break_end: correctedBreakEnd,
                correction_reason: reason,
                justification,
            });
            return;
        }

        // Otherwise, submit to real backend API
        setIsSubmitting(true);
        setErrors({});

        try {
            const response = await fetch(
                route('hr.timekeeping.api.attendance.corrections.store'),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        attendance_id: record.id,
                        corrected_time_in: correctedTimeIn,
                        corrected_time_out: correctedTimeOut,
                        corrected_break_start: correctedBreakStart || null,
                        corrected_break_end: correctedBreakEnd || null,
                        correction_reason: reason,
                        justification,
                    }),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    // Laravel validation errors
                    const formErrors: Record<string, string> = {};
                    Object.keys(data.errors).forEach((key) => {
                        formErrors[key] = data.errors[key][0];
                    });
                    setErrors(formErrors);
                    toast.error('Validation failed. Please check the form.');
                } else {
                    toast.error(data.message || 'Failed to submit correction request.');
                }
                return;
            }

            // Success
            toast.success('Correction request submitted successfully!');
            
            // Close modal and refresh page
            onClose();
            router.reload({ only: ['attendance'] });

        } catch (error) {
            console.error('Failed to submit correction:', error);
            toast.error('Network error. Please try again.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const isLoading = externalLoading || isSubmitting;

    if (!record) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Correct Attendance Record</DialogTitle>
                </DialogHeader>

                <Alert className="border-red-200 bg-red-50">
                    <AlertTriangle className="h-4 w-4 text-red-600" />
                    <div className="ml-3 text-sm text-red-800">
                        This action will create a permanent audit trail. All corrections are recorded and visible to administrators.
                    </div>
                </Alert>

                <div className="space-y-6 py-4">
                    <div className="grid grid-cols-2 gap-4">
                        {/* Original Record (Read-only) */}
                        <Card className="border-l-4 border-l-gray-300 bg-gray-50">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Original Record</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <p className="text-xs text-gray-600 font-semibold">Time In</p>
                                    <p className="font-mono mt-1">{record.time_in || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-600 font-semibold">Time Out</p>
                                    <p className="font-mono mt-1">{record.time_out || '-'}</p>
                                </div>
                                <div className="pt-2 border-t">
                                    <p className="text-xs text-gray-600 font-semibold">Total Hours</p>
                                    <p className="text-lg font-bold text-gray-700 mt-1">{originalHours.toFixed(2)}h</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Corrected Record */}
                        <Card className="border-l-4 border-l-green-500">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Corrected Record</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="space-y-2">
                                    <Label htmlFor="correctedTimeIn" className="text-xs">Time In</Label>
                                    <Input
                                        id="correctedTimeIn"
                                        type="time"
                                        value={correctedTimeIn}
                                        onChange={(e) => setCorrectedTimeIn(e.target.value)}
                                    />
                                    {errors.timeIn && <p className="text-xs text-red-500">{errors.timeIn}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="correctedTimeOut" className="text-xs">Time Out</Label>
                                    <Input
                                        id="correctedTimeOut"
                                        type="time"
                                        value={correctedTimeOut}
                                        onChange={(e) => setCorrectedTimeOut(e.target.value)}
                                    />
                                    {errors.timeOut && <p className="text-xs text-red-500">{errors.timeOut}</p>}
                                </div>

                                <div className="pt-2 border-t">
                                    <p className="text-xs text-gray-600 font-semibold">Total Hours</p>
                                    <div className="flex items-center gap-2 mt-1">
                                        <p className={`text-lg font-bold ${hoursDifference >= 0 ? 'text-green-600' : 'text-orange-600'}`}>
                                            {correctedHours.toFixed(2)}h
                                        </p>
                                        <span className={`text-sm px-2 py-1 rounded ${hoursDifference >= 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'}`}>
                                            {hoursDifference >= 0 ? '+' : ''}{hoursDifference.toFixed(2)}h
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Correction Reason */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason for Correction</Label>
                        <Select value={reason} onValueChange={setReason}>
                            <SelectTrigger id="reason">
                                <SelectValue placeholder="Select reason..." />
                            </SelectTrigger>
                            <SelectContent>
                                {correctionReasons.map(r => (
                                    <SelectItem key={r.value} value={r.value}>
                                        {r.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.reason && <p className="text-sm text-red-500">{errors.reason}</p>}
                    </div>

                    {/* Justification */}
                    <div className="space-y-2">
                        <Label htmlFor="justification">
                            Justification (minimum 10 characters)
                            <span className="text-xs text-gray-500 ml-2">({justification.length} characters)</span>
                        </Label>
                        <Textarea
                            id="justification"
                            placeholder="Explain why this correction is necessary..."
                            value={justification}
                            onChange={(e) => setJustification(e.target.value)}
                            className="min-h-32"
                        />
                        {errors.justification && <p className="text-sm text-red-500">{errors.justification}</p>}
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={onClose} disabled={isLoading}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={isLoading}>
                        Apply Correction
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
