import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { AttendanceCalendar } from '@/components/employee/attendance-calendar';
import { ReportAttendanceIssueModal } from '@/components/employee/report-attendance-issue-modal';
import { 
    Clock,
    Calendar,
    AlertCircle,
    CheckCircle2,
    FileText,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { useState } from 'react';
import { format, parseISO } from 'date-fns';

// ============================================================================
// Type Definitions
// ============================================================================

interface EmployeeInfo {
    id: number;
    employee_number: string;
    full_name: string;
    department: string;
}

interface AttendanceRecord {
    date: string;
    status: 'present' | 'late' | 'absent' | 'on_leave' | 'rest_day';
    time_in?: string;
    time_out?: string;
    hours_worked?: number;
    late_minutes?: number;
    remarks?: string;
}

interface AttendanceSummary {
    days_present: number;
    days_late: number;
    days_absent: number;
    days_on_leave: number;
    total_hours_worked: number;
    average_hours_per_day: number;
}

interface RFIDPunch {
    id: number;
    timestamp: string;
    type: 'IN' | 'OUT' | 'BREAK_IN' | 'BREAK_OUT';
    device_name: string;
    location: string;
}

interface AttendanceIndexProps {
    employee: EmployeeInfo;
    attendanceRecords: AttendanceRecord[];
    attendanceSummary: AttendanceSummary;
    rfidPunchHistory: RFIDPunch[];
    filters: {
        view: string;
        start_date: string;
        end_date: string;
    };
    error?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/employee/dashboard',
    },
    {
        title: 'Attendance',
        href: '/employee/attendance',
    },
];

// Status badge configuration
const statusConfig = {
    present: {
        label: 'Present',
        color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        icon: CheckCircle2,
    },
    late: {
        label: 'Late',
        color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        icon: Clock,
    },
    absent: {
        label: 'Absent',
        color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: AlertCircle,
    },
    on_leave: {
        label: 'On Leave',
        color: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        icon: Calendar,
    },
    rest_day: {
        label: 'Rest Day',
        color: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        icon: Calendar,
    },
};

// RFID punch type configuration
const punchTypeConfig = {
    IN: { label: 'Time In', color: 'text-green-600 dark:text-green-400' },
    OUT: { label: 'Time Out', color: 'text-red-600 dark:text-red-400' },
    BREAK_IN: { label: 'Break In', color: 'text-orange-600 dark:text-orange-400' },
    BREAK_OUT: { label: 'Break Out', color: 'text-blue-600 dark:text-blue-400' },
};

// ============================================================================
// Main Component
// ============================================================================

export default function AttendanceIndex({ 
    employee, 
    attendanceRecords, 
    attendanceSummary,
    rfidPunchHistory,
    filters,
    error 
}: AttendanceIndexProps) {
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [currentMonth, setCurrentMonth] = useState(new Date(filters.start_date));
    const [isReportModalOpen, setIsReportModalOpen] = useState(false);

    // Get today's attendance record
    const todayDate = format(new Date(), 'yyyy-MM-dd');
    const todayAttendance = attendanceRecords.find(record => record.date === todayDate);

    // Get selected date attendance record
    const selectedAttendance = selectedDate 
        ? attendanceRecords.find(record => record.date === selectedDate)
        : null;

    // Get RFID punches for selected date
    const selectedDatePunches = selectedDate
        ? rfidPunchHistory.filter(punch => 
            format(parseISO(punch.timestamp), 'yyyy-MM-dd') === selectedDate
          )
        : [];

    // Handle month navigation
    const handlePreviousMonth = () => {
        const newDate = new Date(currentMonth);
        newDate.setMonth(newDate.getMonth() - 1);
        setCurrentMonth(newDate);
        // TODO: Trigger Inertia visit to load data for previous month
    };

    const handleNextMonth = () => {
        const newDate = new Date(currentMonth);
        newDate.setMonth(newDate.getMonth() + 1);
        setCurrentMonth(newDate);
        // TODO: Trigger Inertia visit to load data for next month
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />

            <div className="space-y-6 p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Attendance & Time Logs
                    </h1>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        View your attendance records and RFID punch history
                    </p>
                </div>
                {/* Error Message */}
                {error && (
                    <Card className="border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/10">
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2 text-red-800 dark:text-red-200">
                                <AlertCircle className="h-5 w-5" />
                                <p className="text-sm font-medium">{error}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Employee Info Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                                <Clock className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {employee.full_name}
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {employee.employee_number} • {employee.department}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Today's Attendance Card */}
                {todayAttendance && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Today's Attendance
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                    <div className="mt-1 flex items-center gap-2">
                                        {(() => {
                                            const StatusIcon = statusConfig[todayAttendance.status].icon;
                                            return (
                                                <>
                                                    <StatusIcon className="h-4 w-4" />
                                                    <Badge className={statusConfig[todayAttendance.status].color}>
                                                        {statusConfig[todayAttendance.status].label}
                                                    </Badge>
                                                </>
                                            );
                                        })()}
                                    </div>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Time In</p>
                                    <p className="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                        {todayAttendance.time_in || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Time Out</p>
                                    <p className="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                        {todayAttendance.time_out || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Hours Worked</p>
                                    <p className="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                        {todayAttendance.hours_worked ? `${todayAttendance.hours_worked.toFixed(1)}h` : 'N/A'}
                                    </p>
                                </div>
                            </div>
                            {todayAttendance.late_minutes && todayAttendance.late_minutes > 0 && (
                                <div className="mt-4 flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900 dark:bg-yellow-900/10">
                                    <AlertCircle className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                    <p className="text-sm text-yellow-800 dark:text-yellow-200">
                                        Late by {todayAttendance.late_minutes} minutes
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Attendance Summary Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Monthly Summary
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={handlePreviousMonth}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">
                                    {format(currentMonth, 'MMMM yyyy')}
                                </span>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={handleNextMonth}
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/10">
                                <p className="text-sm text-green-600 dark:text-green-400">Days Present</p>
                                <p className="mt-1 text-2xl font-bold text-green-800 dark:text-green-200">
                                    {attendanceSummary.days_present}
                                </p>
                            </div>
                            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-900/10">
                                <p className="text-sm text-yellow-600 dark:text-yellow-400">Days Late</p>
                                <p className="mt-1 text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                                    {attendanceSummary.days_late}
                                </p>
                            </div>
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/10">
                                <p className="text-sm text-red-600 dark:text-red-400">Days Absent</p>
                                <p className="mt-1 text-2xl font-bold text-red-800 dark:text-red-200">
                                    {attendanceSummary.days_absent}
                                </p>
                            </div>
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/10">
                                <p className="text-sm text-blue-600 dark:text-blue-400">On Leave</p>
                                <p className="mt-1 text-2xl font-bold text-blue-800 dark:text-blue-200">
                                    {attendanceSummary.days_on_leave}
                                </p>
                            </div>
                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-900/10">
                                <p className="text-sm text-purple-600 dark:text-purple-400">Total Hours</p>
                                <p className="mt-1 text-2xl font-bold text-purple-800 dark:text-purple-200">
                                    {attendanceSummary.total_hours_worked.toFixed(1)}
                                </p>
                            </div>
                            <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-900/10">
                                <p className="text-sm text-indigo-600 dark:text-indigo-400">Avg Hours/Day</p>
                                <p className="mt-1 text-2xl font-bold text-indigo-800 dark:text-indigo-200">
                                    {attendanceSummary.average_hours_per_day.toFixed(1)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Attendance Calendar and Details Grid */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Attendance Calendar */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    Attendance Calendar
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AttendanceCalendar
                                    attendanceRecords={attendanceRecords}
                                    selectedDate={selectedDate}
                                    onSelectDate={setSelectedDate}
                                    currentMonth={currentMonth}
                                />
                                
                                {/* Legend */}
                                <div className="mt-4 flex flex-wrap gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                                    {Object.entries(statusConfig).map(([key, config]) => {
                                        const Icon = config.icon;
                                        return (
                                            <div key={key} className="flex items-center gap-2">
                                                <Icon className="h-4 w-4" />
                                                <Badge className={config.color}>
                                                    {config.label}
                                                </Badge>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Selected Date Details */}
                    <div className="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    {selectedDate ? format(parseISO(selectedDate), 'MMM dd, yyyy') : 'Select a Date'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {selectedAttendance ? (
                                    <div className="space-y-4">
                                        {/* Status */}
                                        <div>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                            <div className="mt-1 flex items-center gap-2">
                                                {(() => {
                                                    const StatusIcon = statusConfig[selectedAttendance.status].icon;
                                                    return (
                                                        <>
                                                            <StatusIcon className="h-4 w-4" />
                                                            <Badge className={statusConfig[selectedAttendance.status].color}>
                                                                {statusConfig[selectedAttendance.status].label}
                                                            </Badge>
                                                        </>
                                                    );
                                                })()}
                                            </div>
                                        </div>

                                        {/* Time Details */}
                                        {selectedAttendance.status !== 'rest_day' && (
                                            <>
                                                <div>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">Time In</p>
                                                    <p className="mt-1 font-semibold text-gray-900 dark:text-white">
                                                        {selectedAttendance.time_in || 'N/A'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">Time Out</p>
                                                    <p className="mt-1 font-semibold text-gray-900 dark:text-white">
                                                        {selectedAttendance.time_out || 'N/A'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">Hours Worked</p>
                                                    <p className="mt-1 font-semibold text-gray-900 dark:text-white">
                                                        {selectedAttendance.hours_worked ? `${selectedAttendance.hours_worked.toFixed(1)}h` : 'N/A'}
                                                    </p>
                                                </div>
                                            </>
                                        )}

                                        {/* RFID Punch History */}
                                        {selectedDatePunches.length > 0 && (
                                            <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
                                                <p className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">
                                                    RFID Punch History
                                                </p>
                                                <div className="space-y-2">
                                                    {selectedDatePunches.map((punch) => (
                                                        <div 
                                                            key={punch.id}
                                                            className="flex items-center justify-between rounded-lg border border-gray-200 p-2 dark:border-gray-700"
                                                        >
                                                            <div>
                                                                <p className={`text-sm font-semibold ${punchTypeConfig[punch.type].color}`}>
                                                                    {punchTypeConfig[punch.type].label}
                                                                </p>
                                                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                                                    {punch.device_name}
                                                                </p>
                                                            </div>
                                                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                                {format(parseISO(punch.timestamp), 'HH:mm:ss')}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Remarks */}
                                        {selectedAttendance.remarks && (
                                            <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
                                                <p className="text-sm text-gray-600 dark:text-gray-400">Remarks</p>
                                                <p className="mt-1 text-sm text-gray-900 dark:text-white">
                                                    {selectedAttendance.remarks}
                                                </p>
                                            </div>
                                        )}

                                        {/* Report Issue Button */}
                                        <Button 
                                            variant="outline" 
                                            className="w-full"
                                            onClick={() => setIsReportModalOpen(true)}
                                        >
                                            <AlertCircle className="mr-2 h-4 w-4" />
                                            Report Issue
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="flex h-64 items-center justify-center text-center">
                                        <div>
                                            <Calendar className="mx-auto h-12 w-12 text-gray-400" />
                                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                                Select a date from the calendar to view attendance details
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Report Attendance Issue Modal */}
            <ReportAttendanceIssueModal
                isOpen={isReportModalOpen}
                onClose={() => setIsReportModalOpen(false)}
                selectedDate={selectedDate || undefined}
            />
        </AppLayout>
    );
}
