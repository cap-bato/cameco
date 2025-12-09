import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
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
import { format, differenceInBusinessDays, parseISO } from 'date-fns';
import axios from 'axios';
import { LeaveCoverageWarning } from '@/components/employee/leave-coverage-warning';

// ============================================================================
// Type Definitions
// ============================================================================

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
    status: 'optimal' | 'acceptable' | 'warning' | 'critical';
    message: string;
    alternative_dates?: Array<{
        start_date: string;
        end_date: string;
        coverage_percentage: number;
        status: 'optimal' | 'acceptable' | 'warning' | 'critical';
    }>;
    team_members_on_leave?: Array<{
        name: string;
        leave_type: string;
        dates: string;
    }>;
}

interface CreateRequestProps {
    leaveTypes: LeaveType[];
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
    employee,
}: CreateRequestProps) {
    // Form state
    const [selectedLeaveType, setSelectedLeaveType] = useState<string>('');
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

    // Selected leave type details
    const selectedLeaveTypeData = leaveTypes?.find(
        (lt) => lt.id.toString() === selectedLeaveType
    );

    // Calculate number of days when dates change
    useEffect(() => {
        if (startDate && endDate) {
            try {
                const start = parseISO(startDate);
                const end = parseISO(endDate);
                const days = differenceInBusinessDays(end, start) + 1;
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
    }, [startDate, endDate, selectedLeaveTypeData]);

    // Debounced coverage calculation
    const calculateCoverage = useCallback(
        async (start: string, end: string) => {
            if (!start || !end) return;

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
                const axiosError = error as { response?: { data?: { error?: string } } };
                setCoverageError(
                    axiosError.response?.data?.error || 'Failed to calculate coverage. Please try again.'
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
            alert('Please select a leave type.');
            return;
        }

        if (!startDate || !endDate) {
            alert('Please select start and end dates.');
            return;
        }

        if (!reason.trim()) {
            alert('Please enter a reason for your leave request.');
            return;
        }

        if (balanceError) {
            alert('Cannot submit: ' + balanceError);
            return;
        }

        if (selectedLeaveTypeData?.requires_document && !uploadedDocument) {
            alert('This leave type requires supporting documentation. Please upload a PDF file.');
            return;
        }

        setSubmitting(true);

        try {
            const formData = new FormData();
            formData.append('leave_type_id', selectedLeaveType);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('reason', reason);
            if (contactDuringLeave.trim()) {
                formData.append('contact_during_leave', contactDuringLeave);
            }
            if (uploadedDocument) {
                formData.append('document', uploadedDocument);
            }

            await axios.post('/employee/leave/request', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            // Redirect to leave history on success
            router.visit('/employee/leave/history', {
                preserveState: false,
            });
        } catch (error: unknown) {
            console.error('Failed to submit leave request', error);
            const axiosError = error as { response?: { data?: { message?: string; error?: string } } };
            const errorMessage =
                axiosError.response?.data?.message ||
                axiosError.response?.data?.error ||
                'Failed to submit leave request. Please try again.';
            alert(errorMessage);
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

                                {/* Date Selection */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="start_date">
                                            Start Date <span className="text-red-500">*</span>
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
                                                <span>Submit requests at least 3 days in advance</span>
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
        </AppLayout>
    );
}
