import { useForm } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AlertCircle, Loader2, Clock, Calendar } from 'lucide-react';
import { format } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface ReportAttendanceIssueModalProps {
    isOpen: boolean;
    onClose: () => void;
    selectedDate?: string;
}

// Issue type options
const issueTypes = [
    { value: 'missing_punch', label: 'Missing Punch (Forgot to clock in/out)' },
    { value: 'wrong_time', label: 'Wrong Time Recorded (System error)' },
    { value: 'other', label: 'Other Issue' },
];

// ============================================================================
// Main Component
// ============================================================================

export function ReportAttendanceIssueModal({ 
    isOpen, 
    onClose, 
    selectedDate 
}: ReportAttendanceIssueModalProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        attendance_date: selectedDate || format(new Date(), 'yyyy-MM-dd'),
        issue_type: '',
        actual_time_in: '',
        actual_time_out: '',
        reason: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post('/employee/attendance/report-issue', {
            onSuccess: () => {
                onClose();
                reset();
            },
        });
    };

    const handleClose = () => {
        if (!processing) {
            reset();
            onClose();
        }
    };

    // Show time fields only for missing_punch and wrong_time
    const showTimeFields = data.issue_type === 'missing_punch' || data.issue_type === 'wrong_time';

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <AlertCircle className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                        Report Attendance Issue
                    </DialogTitle>
                    <DialogDescription>
                        Submit a correction request for your attendance record. HR Staff will review and verify before making any corrections.
                    </DialogDescription>
                </DialogHeader>

                {/* Info Banner */}
                <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                    <div className="flex gap-3">
                        <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                HR Verification Required
                            </p>
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                All attendance corrections require HR Staff verification before being applied. 
                                You will be notified once your request is processed. You can only report issues within the last 3 months.
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Attendance Date */}
                    <div className="space-y-2">
                        <Label htmlFor="attendance_date" className="flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            Attendance Date
                        </Label>
                        <Input
                            id="attendance_date"
                            type="date"
                            value={data.attendance_date}
                            onChange={(e) => setData('attendance_date', e.target.value)}
                            max={format(new Date(), 'yyyy-MM-dd')}
                            min={format(new Date(Date.now() - 90 * 24 * 60 * 60 * 1000), 'yyyy-MM-dd')}
                            disabled={processing}
                            required
                        />
                        {errors.attendance_date && (
                            <p className="text-sm text-destructive">{errors.attendance_date}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            You can only report issues for dates within the last 3 months
                        </p>
                    </div>

                    {/* Issue Type */}
                    <div className="space-y-2">
                        <Label htmlFor="issue_type">Issue Type</Label>
                        <Select
                            value={data.issue_type}
                            onValueChange={(value) => setData('issue_type', value)}
                            disabled={processing}
                        >
                            <SelectTrigger id="issue_type">
                                <SelectValue placeholder="Select the type of issue" />
                            </SelectTrigger>
                            <SelectContent>
                                {issueTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.issue_type && (
                            <p className="text-sm text-destructive">{errors.issue_type}</p>
                        )}
                    </div>

                    {/* Time Fields (Conditional) */}
                    {showTimeFields && (
                        <div className="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <h4 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                                Actual Time (What should have been recorded)
                            </h4>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="actual_time_in">Actual Time In</Label>
                                    <Input
                                        id="actual_time_in"
                                        type="time"
                                        value={data.actual_time_in}
                                        onChange={(e) => setData('actual_time_in', e.target.value)}
                                        disabled={processing}
                                        required={showTimeFields}
                                    />
                                    {errors.actual_time_in && (
                                        <p className="text-sm text-destructive">{errors.actual_time_in}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Example: 08:00 (24-hour format)
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="actual_time_out">Actual Time Out</Label>
                                    <Input
                                        id="actual_time_out"
                                        type="time"
                                        value={data.actual_time_out}
                                        onChange={(e) => setData('actual_time_out', e.target.value)}
                                        disabled={processing}
                                        required={showTimeFields}
                                    />
                                    {errors.actual_time_out && (
                                        <p className="text-sm text-destructive">{errors.actual_time_out}</p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Example: 17:00 (24-hour format)
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Reason */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason for Correction <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="reason"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Please provide a detailed explanation of the attendance issue. Be specific about what happened and why a correction is needed. (Minimum 10 characters)"
                            rows={5}
                            disabled={processing}
                            required
                            minLength={10}
                            maxLength={1000}
                        />
                        {errors.reason && (
                            <p className="text-sm text-destructive">{errors.reason}</p>
                        )}
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Minimum 10 characters required</span>
                            <span>{data.reason.length}/1000</span>
                        </div>
                    </div>

                    {/* Examples Section */}
                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">
                            Example Reasons:
                        </h4>
                        <ul className="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                            <li>• <strong>Missing Punch:</strong> "I forgot to punch out at 5:00 PM on Friday. I was in a rush to catch the bus and forgot to tap my card at the RFID device."</li>
                            <li>• <strong>Wrong Time:</strong> "The RFID device recorded my time in as 9:15 AM, but I actually clocked in at 8:00 AM. There may have been a system glitch."</li>
                            <li>• <strong>Other:</strong> "I clocked in at the wrong location by mistake and need to have my attendance record transferred to the correct department."</li>
                        </ul>
                    </div>

                    {/* Warning */}
                    <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900 dark:bg-amber-900/10">
                        <p className="text-sm text-amber-800 dark:text-amber-200">
                            <strong>Note:</strong> Providing false information in attendance correction requests may result in disciplinary action. 
                            Please ensure all information is accurate and truthful.
                        </p>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {processing ? 'Submitting...' : 'Submit Correction Request'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
