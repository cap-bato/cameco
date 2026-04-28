import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import {
    Calendar,
    Upload,
    AlertCircle,
    CheckCircle2,
    FileText,
    X,
    Loader2,
    Send,
} from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { format, differenceInBusinessDays, parseISO, isBefore, isValid } from 'date-fns';
import axios from 'axios';
import { LeaveCoverageWarning } from '@/components/employee/leave-coverage-warning';

// ============================================================================
// Type Definitions
// ============================================================================

interface LeaveVariant {
    code: string;
    label: string;
}

interface LeaveType {
    id: number;
    name: string;
    code: string;
    available_balance: number;
    requires_document: boolean;
    description: string;
}

interface CoverageData {
    coverage_percentage: number;
    status: 'optimal' | 'acceptable' | 'warning' | 'critical' | 'unavailable';
    message: string;
    coverage_available?: boolean;
    alternative_dates?: Array<{
        start_date: string;
        end_date: string;
        coverage_percentage: number;
        status: 'optimal' | 'acceptable' | 'warning' | 'critical' | 'unavailable';
    }>;
    team_members_on_leave?: Array<{
        name: string;
        leave_type: string;
        dates: string;
    }>;
}

type AxiosValidationError = {
    response?: {
        status?: number;
        data?: {
            message?: string;
            error?: string;
            errors?: Record<string, string[]>;
        };
    };
};

interface CreateRequestProps {
    leaveTypes: LeaveType[];
    leaveVariants: LeaveVariant[];
    employee: {
        id: number;
        employee_number: string;
        full_name: string;
        department: string;
    };
    error?: Record<string, string>;
}

// ============================================================================
// Main Component
// ============================================================================

export default function CreateRequest({
    leaveTypes,
    leaveVariants,
    employee,
}: CreateRequestProps) {
    // Form state
    const [selectedLeaveType, setSelectedLeaveType] = useState<string>('');
    const [selectedVariant, setSelectedVariant] = useState<string | null>(null);
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [reason, setReason] = useState<string>('');
    const [contactDuringLeave, setContactDuringLeave] = useState<string>('');
    const [uploadedDocument, setUploadedDocument] = useState<File | null>(null);
    const [documentError, setDocumentError] = useState<string>('');
    const [uploadProgress, setUploadProgress] = useState<number>(0);

    // Coverage state
    const [coverageData, setCoverageData] = useState<CoverageData | null>(null);
    const [loadingCoverage, setLoadingCoverage] = useState<boolean>(false);
    const [coverageError, setCoverageError] = useState<string>('');

    // Form validation state
    const [numberOfDays, setNumberOfDays] = useState<number>(0);
    const [balanceError, setBalanceError] = useState<string>('');
    const [submitting, setSubmitting] = useState<boolean>(false);
    const [submitError, setSubmitError] = useState<string>('');

    // Success dialog state
    const [showSuccessDialog, setShowSuccessDialog] = useState<boolean>(false);
    const [successDetails, setSuccessDetails] = useState<{
        leaveType: string;
        startDate: string;
        endDate: string;
        daysRequested: number;
    } | null>(null);

    // Validation error modal state
    const [showValidationError, setShowValidationError] = useState<boolean>(false);
    const [validationErrorMessage, setValidationErrorMessage] = useState<string>('');

    // Selected leave type details
    const selectedLeaveTypeData = leaveTypes?.find(
        (lt) => lt.id.toString() === selectedLeaveType
    );

    // Calculate number of days when dates change
    useEffect(() => {
        if (startDate && endDate) {
            try {
                // Check for variant (new approach: variant on SL)
                let days: number;
                if (selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant)) {
                    days = 0.5;
                }
                // Legacy: check for deprecated HAM/HPM policies
                else if (selectedLeaveTypeData?.code === 'HAM' || selectedLeaveTypeData?.code === 'HPM') {
                    days = 0.5;
                } else {
                    const start = parseISO(startDate);
                    const end = parseISO(endDate);
                    days = differenceInBusinessDays(end, start) + 1;
                }
                setNumberOfDays(days > 0 ? days : 0);

                // Check balance
                if (selectedLeaveTypeData && days > selectedLeaveTypeData.available_balance) {
                    setBalanceError(
                        `Insufficient balance. You have ${selectedLeaveTypeData.available_balance} days available.`
                    );
                } else {
                    setBalanceError('');
                }
            } catch {
                setNumberOfDays(0);
            }
        } else {
            setNumberOfDays(0);
            setBalanceError('');
        }
    }, [startDate, endDate, selectedLeaveTypeData, selectedVariant]);

    // Auto-set end date to match start date when half-day variant is selected
    useEffect(() => {
        if (selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant)) {
            if (startDate && endDate !== startDate) {
                setEndDate(startDate);
            }
        }
    }, [selectedVariant, startDate]);

    // Debounced coverage calculation
    const calculateCoverage = useCallback(
        async (start: string, end: string) => {
            if (!start || !end) return;

            const startParsed = parseISO(start);
            const endParsed = parseISO(end);
            if (!isValid(startParsed) || !isValid(endParsed) || isBefore(endParsed, startParsed)) {
                setCoverageData(null);
                setCoverageError('');
                return;
            }

            setLoadingCoverage(true);
            setCoverageError('');

            try {
                const response = await axios.post('/employee/leave/request/calculate-coverage', {
                    start_date: start,
                    end_date: end,
                });

                setCoverageData(response.data);
            } catch (error: unknown) {
                console.error('Failed to calculate coverage', error);
                const axiosError = error as AxiosValidationError;
                if (axiosError.response?.status === 422) {
                    // Validation can briefly fail while user is editing dates; avoid noisy error state.
                    setCoverageData(null);
                    setCoverageError('');
                    return;
                }
                setCoverageError(
                    axiosError.response?.data?.error || axiosError.response?.data?.message || 'Failed to calculate coverage. Please try again.'
                );
                setCoverageData(null);
            } finally {
                setLoadingCoverage(false);
            }
        },
        []
    );

    // Trigger coverage calculation with debounce
    useEffect(() => {
        if (startDate && endDate) {
            const timer = setTimeout(() => {
                calculateCoverage(startDate, endDate);
            }, 500); // 500ms debounce

            return () => clearTimeout(timer);
        } else {
            setCoverageData(null);
        }
    }, [startDate, endDate, calculateCoverage]);

    // Handle document upload
    const handleDocumentChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        setDocumentError('');

        if (!file) {
            setUploadedDocument(null);
            return;
        }

        // Validate file type (PDF only)
        if (file.type !== 'application/pdf') {
            setDocumentError('Only PDF files are allowed.');
            setUploadedDocument(null);
            return;
        }

        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            setDocumentError('File size must not exceed 5MB.');
            setUploadedDocument(null);
            return;
        }

        setUploadedDocument(file);
        // Simulate upload progress
        setUploadProgress(0);
        const interval = setInterval(() => {
            setUploadProgress((prev) => {
                if (prev >= 100) {
                    clearInterval(interval);
                    return 100;
                }
                return prev + 10;
            });
        }, 50);
    };

    // Handle alternative date selection
    const handleSelectAlternativeDate = (start: string, end: string) => {
        setStartDate(start);
        setEndDate(end);
    };

    // Handle form submission
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Validation
        if (!selectedLeaveType) {
            setValidationErrorMessage('Please select a leave type.');
            setShowValidationError(true);
            return;
        }

        if (!startDate || !endDate) {
            setValidationErrorMessage('Please select start and end dates.');
            setShowValidationError(true);
            return;
        }

        if (!reason.trim()) {
            setValidationErrorMessage('Please enter a reason for your leave request.');
            setShowValidationError(true);
            return;
        }

        if (reason.trim().length < 10) {
            setValidationErrorMessage('Reason must be at least 10 characters long. Please provide a more detailed explanation.');
            setShowValidationError(true);
            return;
        }

        if (balanceError) {
            setValidationErrorMessage('Cannot submit: ' + balanceError);
            setShowValidationError(true);
            return;
        }

        if (selectedLeaveTypeData?.requires_document && !uploadedDocument) {
            setValidationErrorMessage('This leave type requires supporting documentation. Please upload a PDF file.');
            setShowValidationError(true);
            return;
        }

        setSubmitError('');
        setSubmitting(true);

        try {
            const formData = new FormData();
            formData.append('leave_policy_id', selectedLeaveType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('reason', reason);
            if (selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant)) {
                formData.append('leave_type_variant', selectedVariant);
            }
            if (contactDuringLeave.trim()) {
                formData.append('contact_during_leave', contactDuringLeave);
            }
            if (uploadedDocument) {
                formData.append('document', uploadedDocument);
            }

            console.log('Submitting leave request with data:', {
                leave_policy_id: selectedLeaveType,
                start_date: startDate,
                end_date: endDate,
                reason: reason.substring(0, 50) + '...',
                variant: selectedVariant || 'none',
                has_document: !!uploadedDocument,
                document_name: uploadedDocument?.name,
                document_size: uploadedDocument?.size,
            });

            await axios.post('/employee/leave/request', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            // Show success dialog with details
            const leaveTypeData = leaveTypes.find(lt => lt.id.toString() === selectedLeaveType);
            setSuccessDetails({
                leaveType: leaveTypeData?.name || 'Leave',
                startDate,
                endDate,
                daysRequested: numberOfDays,
            });
            setShowSuccessDialog(true);
        } catch (error: unknown) {
            console.error('Failed to submit leave request', error);
            const axiosError = error as AxiosValidationError;
            
            console.log('Error response:', {
                status: axiosError.response?.status,
                data: axiosError.response?.data,
            });
            
            // Handle validation errors from backend
            const validationErrors = axiosError.response?.data?.errors;
            if (validationErrors && typeof validationErrors === 'object') {
                // Build error message from all validation errors
                const errorMessages = Object.entries(validationErrors)
                    .map(([field, messages]) => {
                        const msgArray = Array.isArray(messages) ? messages : [messages];
                        return msgArray.join(', ');
                    })
                    .join('\n');
                console.log('Validation errors:', errorMessages);
                setSubmitError(errorMessages || 'Validation failed. Please check your entries.');
                setSubmitting(false);
                return;
            }
            
            const errorMessage =
                axiosError.response?.data?.message ||
                axiosError.response?.data?.error ||
                `Request failed with status ${axiosError.response?.status || 'unknown'}. Please check the console for details and try again.`;
            console.log('Final error message:', errorMessage);
            setSubmitError(errorMessage);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Apply for Leave" />

            <div className="space-y-6 p-6">
            {/* Page Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    Apply for Leave
                </h1>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Submit a new leave request for approval
                </p>
            </div>

            {/* Employee Info Card */}
            <Card className="mb-6">
                <CardContent className="pt-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Employee Number</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.employee_number}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Full Name</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.full_name}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">Department</div>
                            <div className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {employee.department}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Leave Request Form */}
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5" />
                                Leave Request Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Leave Type Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="leave_type">
                                        Leave Type <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={selectedLeaveType}
                                        onValueChange={setSelectedLeaveType}
                                    >
                                        <SelectTrigger id="leave_type">
                                            <SelectValue placeholder="Select leave type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {leaveTypes?.map((lt) => (
                                                <SelectItem key={lt.id} value={lt.id.toString()}>
                                                    {lt.name} ({lt.available_balance} days available)
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedLeaveTypeData && (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            {selectedLeaveTypeData.description}
                                        </div>
                                    )}
                                </div>

                                {/* Leave Duration (Variant Selector) - Only for Sick Leave */}
                                {selectedLeaveTypeData?.code === 'SL' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="leave_variant">
                                            Leave Duration <span className="text-red-500">*</span>
                                        </Label>
                                        <Select
                                            value={selectedVariant || 'full_day'}
                                            onValueChange={(val) => setSelectedVariant(val === 'full_day' ? null : val)}
                                        >
                                            <SelectTrigger id="leave_variant">
                                                <SelectValue placeholder="Select duration" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="full_day">Full Day (1.0 days)</SelectItem>
                                                <SelectItem value="half_am">Half Day AM (0.5 days)</SelectItem>
                                                <SelectItem value="half_pm">Half Day PM (0.5 days)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            Select whether you are taking leave for the full day or just the morning/afternoon. Half day leave counts as 0.5 days against your balance.
                                        </div>
                                    </div>
                                )}

                                {/* Date Selection */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="start_date">
                                            {selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant) ? 'Leave Date' : 'Start Date'} <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={startDate}
                                            onChange={(e) => setStartDate(e.target.value)}
                                            min={format(new Date(), 'yyyy-MM-dd')}
                                            required
                                        />
                                    </div>
                                    {!selectedVariant || !['half_am', 'half_pm'].includes(selectedVariant) ? (
                                        <div className="space-y-2">
                                            <Label htmlFor="end_date">
                                                End Date <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="end_date"
                                                type="date"
                                                value={endDate}
                                            onChange={(e) => setEndDate(e.target.value)}
                                            min={startDate || format(new Date(), 'yyyy-MM-dd')}
                                            required
                                        />
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <Label className="text-blue-600">Duration</Label>
                                            <div className="p-3 rounded-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 flex items-center h-[40px]">
                                                <span className="text-sm font-medium text-blue-800 dark:text-blue-200">
                                                    {selectedVariant === 'half_am' ? 'Half Day AM (0.5 days)' : 'Half Day PM (0.5 days)'}
                                                </span>
                                            </div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Half-day leave is for a single day only</p>
                                        </div>
                                    )}
                                </div>

                                {/* Number of Days Display */}
                                {numberOfDays > 0 && (
                                    <div className="rounded-md bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                                Total Business Days: {numberOfDays} days
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* Balance Error */}
                                {balanceError && (
                                    <div className="rounded-md bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
                                        <div className="flex items-center gap-2">
                                            <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                                            <span className="text-sm font-medium text-red-700 dark:text-red-300">
                                                {balanceError}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* Submission Error */}
                                {submitError && (
                                    <div className="rounded-md bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800">
                                        <div className="flex items-center gap-2">
                                            <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                                            <span className="text-sm font-medium text-red-700 dark:text-red-300">
                                                {submitError}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* Reason */}
                                <div className="space-y-2">
                                    <Label htmlFor="reason">
                                        Reason for Leave <span className="text-red-500">*</span>
                                    </Label>
                                    <Textarea
                                        id="reason"
                                        placeholder="Please provide a brief explanation for your leave request..."
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        rows={4}
                                        required
                                    />
                                </div>

                                {/* Contact During Leave */}
                                <div className="space-y-2">
                                    <Label htmlFor="contact">Contact During Leave (Optional)</Label>
                                    <Input
                                        id="contact"
                                        type="text"
                                        placeholder="e.g., +63 917 123 4567 or backup@email.com"
                                        value={contactDuringLeave}
                                        onChange={(e) => setContactDuringLeave(e.target.value)}
                                    />
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        Provide contact information in case of emergencies.
                                    </div>
                                </div>

                                {/* Document Upload */}
                                <div className="space-y-2">
                                    <Label htmlFor="document">
                                        Supporting Document (PDF only, max 5MB)
                                        {selectedLeaveTypeData?.requires_document && (
                                            <span className="text-red-500"> *</span>
                                        )}
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            id="document"
                                            type="file"
                                            accept="application/pdf"
                                            onChange={handleDocumentChange}
                                            className="hidden"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.document.getElementById('document')?.click()}
                                        >
                                            <Upload className="h-4 w-4 mr-2" />
                                            {uploadedDocument ? 'Change File' : 'Upload PDF'}
                                        </Button>
                                        {uploadedDocument && (
                                            <div className="flex items-center gap-2 flex-1">
                                                <FileText className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                <span className="text-sm text-gray-700 dark:text-gray-300 truncate">
                                                    {uploadedDocument.name}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setUploadedDocument(null);
                                                        setUploadProgress(0);
                                                    }}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                    {uploadedDocument && uploadProgress < 100 && (
                                        <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div
                                                className="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all"
                                                style={{ width: `${uploadProgress}%` }}
                                            />
                                        </div>
                                    )}
                                    {documentError && (
                                        <div className="text-sm text-red-600 dark:text-red-400">
                                            {documentError}
                                        </div>
                                    )}
                                    {selectedLeaveTypeData?.requires_document && (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            This leave type requires supporting documentation.
                                        </div>
                                    )}
                                </div>

                                {/* Submit Button */}
                                <div className="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <Button
                                        type="submit"
                                        disabled={
                                            submitting ||
                                            !selectedLeaveType ||
                                            !startDate ||
                                            !endDate ||
                                            !reason.trim() ||
                                            !!balanceError ||
                                            (selectedLeaveTypeData?.requires_document && !uploadedDocument)
                                        }
                                    >
                                        {submitting ? (
                                            <>
                                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                Submitting...
                                            </>
                                        ) : (
                                            <>
                                                <Send className="h-4 w-4 mr-2" />
                                                Submit Leave Request
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                {/* Coverage Preview Sidebar */}
                <div className="lg:col-span-1">
                    <div className="space-y-4 sticky top-6">
                        {/* Coverage Loading State */}
                        {loadingCoverage && (
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-center py-8">
                                        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                                    </div>
                                    <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                                        Calculating workforce coverage...
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {/* Coverage Data Display */}
                        {!loadingCoverage && coverageData && (
                            <LeaveCoverageWarning
                                coveragePercentage={coverageData.coverage_percentage}
                                status={coverageData.status}
                                message={coverageData.message}
                                alternativeDates={coverageData.alternative_dates}
                                teamMembersOnLeave={coverageData.team_members_on_leave}
                                onSelectAlternativeDate={handleSelectAlternativeDate}
                            />
                        )}

                        {/* Coverage Error */}
                        {coverageError && (
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                        <AlertCircle className="h-5 w-5" />
                                        <span className="text-sm">{coverageError}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Help Card */}
                        {!coverageData && !loadingCoverage && (
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            <AlertCircle className="h-4 w-4" />
                                            <span>Leave Request Guidelines</span>
                                        </div>
                                        <ul className="space-y-2 text-xs text-gray-600 dark:text-gray-400">
                                            <li className="flex items-start gap-2">
                                                <span className="text-blue-600 dark:text-blue-400">•</span>
                                                <span>Standard leave is 3 days advance; sick and emergency leave may be filed for immediate dates</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-blue-600 dark:text-blue-400">•</span>
                                                <span>Sick leave (3+ days) requires medical certificate</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-blue-600 dark:text-blue-400">•</span>
                                                <span>Emergency leaves are processed urgently</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-blue-600 dark:text-blue-400">•</span>
                                                <span>You'll receive notification within 24 hours</span>
                                            </li>
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </div>

        {/* Success Dialog */}
        <Dialog open={showSuccessDialog} onOpenChange={setShowSuccessDialog}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-3 mb-2">
                        <CheckCircle2 className="h-6 w-6 text-green-600 dark:text-green-400" />
                        <DialogTitle>Leave Request Submitted Successfully</DialogTitle>
                    </div>
                    <DialogDescription>
                        Your leave request has been submitted for approval. You will receive a notification once it has been reviewed.
                    </DialogDescription>
                </DialogHeader>

                {successDetails && (
                    <div className="space-y-4 py-4">
                        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 space-y-3">
                            <div className="flex justify-between items-start">
                                <div className="text-sm text-gray-600 dark:text-gray-400">Leave Type</div>
                                <div className="font-medium text-gray-900 dark:text-gray-100">{successDetails.leaveType}</div>
                            </div>
                            <div className="flex justify-between items-start">
                                <div className="text-sm text-gray-600 dark:text-gray-400">Start Date</div>
                                <div className="font-medium text-gray-900 dark:text-gray-100">{format(parseISO(successDetails.startDate), 'MMM dd, yyyy')}</div>
                            </div>
                            <div className="flex justify-between items-start">
                                <div className="text-sm text-gray-600 dark:text-gray-400">End Date</div>
                                <div className="font-medium text-gray-900 dark:text-gray-100">{format(parseISO(successDetails.endDate), 'MMM dd, yyyy')}</div>
                            </div>
                            <div className="flex justify-between items-start border-t border-green-200 dark:border-green-800 pt-3">
                                <div className="text-sm text-gray-600 dark:text-gray-400">Days Requested</div>
                                <div className="font-semibold text-green-700 dark:text-green-300 text-lg">{successDetails.daysRequested} day(s)</div>
                            </div>
                        </div>

                        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                            <p className="text-xs text-blue-800 dark:text-blue-200">
                                <span className="font-semibold">Next Steps:</span> Your request will be reviewed by your supervisor and HR manager. Check your leave history to track the status.
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex gap-3 justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                    <Button
                        variant="outline"
                        onClick={() => setShowSuccessDialog(false)}
                    >
                        Continue Editing
                    </Button>
                    <Button
                        onClick={() => {
                            setShowSuccessDialog(false);
                            router.visit('/employee/leave/history', {
                                preserveState: false,
                            });
                        }}
                        className="bg-green-600 hover:bg-green-700 text-white"
                    >
                        View Leave History
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        {/* Validation Error Modal */}
        <Dialog open={showValidationError} onOpenChange={setShowValidationError}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <div className="flex items-center gap-3 mb-4">
                        <div className="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                            <AlertCircle className="h-6 w-6 text-red-600 dark:text-red-400" />
                        </div>
                        <DialogTitle className="text-lg">Unable to Submit</DialogTitle>
                    </div>
                </DialogHeader>
                <DialogDescription className="text-base text-gray-700 dark:text-gray-300 leading-relaxed">
                    {validationErrorMessage}
                </DialogDescription>
                <div className="flex justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <Button
                        onClick={() => setShowValidationError(false)}
                        className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 text-white"
                    >
                        Understood
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
        </AppLayout>
    );
}
