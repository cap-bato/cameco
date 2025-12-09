import { 
    startOfMonth, 
    endOfMonth, 
    eachDayOfInterval, 
    format, 
    isSameDay, 
    parseISO,
    startOfWeek,
    endOfWeek,
    isSameMonth,
} from 'date-fns';
import { cn } from '@/lib/utils';

// ============================================================================
// Type Definitions
// ============================================================================

interface AttendanceRecord {
    date: string;
    status: 'present' | 'late' | 'absent' | 'on_leave' | 'rest_day';
    time_in?: string;
    time_out?: string;
    hours_worked?: number;
    late_minutes?: number;
    remarks?: string;
}

interface AttendanceCalendarProps {
    attendanceRecords: AttendanceRecord[];
    selectedDate: string | null;
    onSelectDate: (date: string) => void;
    currentMonth: Date;
}

// Status color configuration for calendar cells
const statusColors = {
    present: 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50 border-green-300 dark:border-green-700',
    late: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:hover:bg-yellow-900/50 border-yellow-300 dark:border-yellow-700',
    absent: 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 border-red-300 dark:border-red-700',
    on_leave: 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 border-blue-300 dark:border-blue-700',
    rest_day: 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-900/30 dark:text-gray-400 dark:hover:bg-gray-900/50 border-gray-300 dark:border-gray-700',
};

// ============================================================================
// Main Component
// ============================================================================

export function AttendanceCalendar({ 
    attendanceRecords, 
    selectedDate, 
    onSelectDate,
    currentMonth 
}: AttendanceCalendarProps) {
    // Get calendar grid (including previous/next month overflow days)
    const monthStart = startOfMonth(currentMonth);
    const monthEnd = endOfMonth(currentMonth);
    const calendarStart = startOfWeek(monthStart, { weekStartsOn: 0 }); // Sunday
    const calendarEnd = endOfWeek(monthEnd, { weekStartsOn: 0 });
    
    const calendarDays = eachDayOfInterval({ start: calendarStart, end: calendarEnd });

    // Group days into weeks
    const weeks: Date[][] = [];
    let currentWeek: Date[] = [];
    
    calendarDays.forEach((day, index) => {
        currentWeek.push(day);
        
        if ((index + 1) % 7 === 0) {
            weeks.push(currentWeek);
            currentWeek = [];
        }
    });

    // If there's a remaining partial week (shouldn't happen with startOfWeek/endOfWeek, but just in case)
    if (currentWeek.length > 0) {
        weeks.push(currentWeek);
    }

    // Get attendance record for a specific date
    const getAttendanceForDate = (date: Date): AttendanceRecord | undefined => {
        const dateString = format(date, 'yyyy-MM-dd');
        return attendanceRecords.find(record => record.date === dateString);
    };

    // Handle date click
    const handleDateClick = (date: Date) => {
        const dateString = format(date, 'yyyy-MM-dd');
        onSelectDate(dateString);
    };

    // Check if date is selected
    const isSelected = (date: Date): boolean => {
        if (!selectedDate) return false;
        return isSameDay(date, parseISO(selectedDate));
    };

    // Weekday labels
    const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    return (
        <div className="w-full">
            {/* Weekday Headers */}
            <div className="mb-2 grid grid-cols-7 gap-1">
                {weekDays.map((day) => (
                    <div 
                        key={day}
                        className="py-2 text-center text-sm font-semibold text-gray-700 dark:text-gray-300"
                    >
                        {day}
                    </div>
                ))}
            </div>

            {/* Calendar Grid */}
            <div className="space-y-1">
                {weeks.map((week, weekIndex) => (
                    <div key={weekIndex} className="grid grid-cols-7 gap-1">
                        {week.map((day, dayIndex) => {
                            const attendance = getAttendanceForDate(day);
                            const isCurrentMonth = isSameMonth(day, currentMonth);
                            const isToday = isSameDay(day, new Date());
                            const selected = isSelected(day);
                            
                            // Determine cell styling based on attendance status
                            let cellClasses = cn(
                                'relative aspect-square rounded-lg border-2 p-1 transition-all cursor-pointer',
                                'flex flex-col items-center justify-center',
                                !isCurrentMonth && 'opacity-30',
                                selected && 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-gray-900',
                                isToday && !selected && 'ring-1 ring-gray-400 dark:ring-gray-600'
                            );

                            if (attendance && isCurrentMonth) {
                                cellClasses = cn(cellClasses, statusColors[attendance.status]);
                            } else if (isCurrentMonth) {
                                cellClasses = cn(
                                    cellClasses,
                                    'bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700',
                                    'border-gray-200 dark:border-gray-700'
                                );
                            } else {
                                cellClasses = cn(
                                    cellClasses,
                                    'bg-gray-50 dark:bg-gray-900',
                                    'border-gray-200 dark:border-gray-800'
                                );
                            }

                            return (
                                <button
                                    key={dayIndex}
                                    onClick={() => handleDateClick(day)}
                                    className={cellClasses}
                                    title={attendance ? `${format(day, 'MMM dd')} - ${attendance.status.replace('_', ' ').toUpperCase()}` : format(day, 'MMM dd')}
                                >
                                    {/* Date Number */}
                                    <span className={cn(
                                        'text-sm font-semibold',
                                        !isCurrentMonth && 'text-gray-400 dark:text-gray-600',
                                        isToday && 'font-bold'
                                    )}>
                                        {format(day, 'd')}
                                    </span>

                                    {/* Status Indicator Dot */}
                                    {attendance && isCurrentMonth && (
                                        <div className={cn(
                                            'mt-0.5 h-1.5 w-1.5 rounded-full',
                                            attendance.status === 'present' && 'bg-green-600 dark:bg-green-400',
                                            attendance.status === 'late' && 'bg-yellow-600 dark:bg-yellow-400',
                                            attendance.status === 'absent' && 'bg-red-600 dark:bg-red-400',
                                            attendance.status === 'on_leave' && 'bg-blue-600 dark:bg-blue-400',
                                            attendance.status === 'rest_day' && 'bg-gray-600 dark:bg-gray-400'
                                        )} />
                                    )}

                                    {/* Late Indicator */}
                                    {attendance?.late_minutes && attendance.late_minutes > 0 && isCurrentMonth && (
                                        <span className="absolute right-1 top-1 text-[10px] font-bold text-yellow-700 dark:text-yellow-300">
                                            L
                                        </span>
                                    )}

                                    {/* Today Indicator */}
                                    {isToday && (
                                        <span className="absolute bottom-1 left-1 text-[10px] font-bold text-blue-600 dark:text-blue-400">
                                            •
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                ))}
            </div>

            {/* Calendar Info */}
            <div className="mt-4 space-y-2 text-xs text-gray-600 dark:text-gray-400">
                <p className="flex items-center gap-2">
                    <span className="inline-block h-2 w-2 rounded-full bg-blue-600"></span>
                    • = Today
                </p>
                <p className="flex items-center gap-2">
                    <span className="inline-block rounded border-2 border-blue-500 px-1">00</span>
                    = Selected date (blue ring)
                </p>
                <p>
                    <strong>L</strong> = Late arrival
                </p>
                <p className="text-gray-500 dark:text-gray-500">
                    Click any date to view detailed attendance information
                </p>
            </div>
        </div>
    );
}
