import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Clock, Coffee, LogIn, LogOut, AlertTriangle, XCircle } from 'lucide-react';
import { EventType } from '@/types/timekeeping-pages';

/**
 * Timeline Event Interface
 * Represents a single RFID tap event on the timeline
 */
interface TimelineEvent {
    id: number;
    sequenceId: number;
    employeeId: string;
    employeeName: string;
    eventType: EventType;
    timestamp: string;
    deviceLocation: string;
    verified: boolean;
    scheduledTime?: string;
    variance?: number; // Minutes difference from scheduled (+ = late, - = early)
    violationType?: 'late_arrival' | 'early_departure' | 'missing_punch' | 'extended_break';
}

/**
 * Timeline Segment Interface
 * Represents the period between two events
 */
interface TimelineSegment {
    start: Date;
    end: Date;
    type: 'working' | 'break' | 'off-duty' | 'overtime';
    duration: number; // Minutes
}

interface EmployeeTimelineViewProps {
    employeeId?: string;
    employeeName?: string;
    employeePhoto?: string;
    date?: string;
    events?: TimelineEvent[];
    className?: string;
}

/**
 * Mock timeline events for demonstration
 */
const mockTimelineEvents: TimelineEvent[] = [
    {
        id: 1,
        sequenceId: 12345,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'time_in',
        timestamp: '2026-01-29T08:05:00',
        deviceLocation: 'Gate 1 - Main Entrance',
        verified: true,
        scheduledTime: '2026-01-29T08:00:00',
        variance: 5, // 5 minutes late
        violationType: 'late_arrival'
    },
    {
        id: 2,
        sequenceId: 12346,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'break_start',
        timestamp: '2026-01-29T12:00:00',
        deviceLocation: 'Cafeteria',
        verified: true,
        scheduledTime: '2026-01-29T12:00:00',
        variance: 0
    },
    {
        id: 3,
        sequenceId: 12347,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'break_end',
        timestamp: '2026-01-29T12:30:00',
        deviceLocation: 'Cafeteria',
        verified: true,
        scheduledTime: '2026-01-29T12:30:00',
        variance: 0
    },
    {
        id: 4,
        sequenceId: 12348,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'break_start',
        timestamp: '2026-01-29T15:00:00',
        deviceLocation: 'Cafeteria',
        verified: true,
        scheduledTime: '2026-01-29T15:00:00',
        variance: 0
    },
    {
        id: 5,
        sequenceId: 12349,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'break_end',
        timestamp: '2026-01-29T15:15:00',
        deviceLocation: 'Cafeteria',
        verified: true,
        scheduledTime: '2026-01-29T15:15:00',
        variance: 0
    },
    {
        id: 6,
        sequenceId: 12350,
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        eventType: 'time_out',
        timestamp: '2026-01-29T16:45:00',
        deviceLocation: 'Gate 2 - Loading Dock',
        verified: true,
        scheduledTime: '2026-01-29T17:00:00',
        variance: -15, // 15 minutes early (early departure violation)
        violationType: 'early_departure'
    }
];

/**
 * Mock scheduled times for ghost outline visualization
 * Represents the expected schedule for the employee
 */
const mockScheduledTimes = [
    { eventType: 'time_in' as EventType, scheduledTime: '2026-01-29T08:00:00' },
    { eventType: 'break_start' as EventType, scheduledTime: '2026-01-29T12:00:00' },
    { eventType: 'break_end' as EventType, scheduledTime: '2026-01-29T12:30:00' },
    { eventType: 'break_start' as EventType, scheduledTime: '2026-01-29T15:00:00' },
    { eventType: 'break_end' as EventType, scheduledTime: '2026-01-29T15:15:00' },
    { eventType: 'time_out' as EventType, scheduledTime: '2026-01-29T17:00:00' }
];

/**
 * Get icon for event type
 */
const getEventIcon = (type: EventType) => {
    const icons = {
        time_in: LogIn,
        time_out: LogOut,
        break_start: Coffee,
        break_end: Coffee,
        overtime_start: Clock,
        overtime_end: Clock
    };
    const IconComponent = icons[type] || Clock;
    return <IconComponent className="h-4 w-4" />;
};

/**
 * Get color for event type
 */
const getEventColor = (type: EventType): string => {
    const colors = {
        time_in: 'bg-green-500 border-green-600',
        time_out: 'bg-red-500 border-red-600',
        break_start: 'bg-yellow-500 border-yellow-600',
        break_end: 'bg-blue-500 border-blue-600',
        overtime_start: 'bg-purple-500 border-purple-600',
        overtime_end: 'bg-pink-500 border-pink-600'
    };
    return colors[type] || 'bg-gray-500 border-gray-600';
};

/**
 * Get display name for event type
 */
const getEventTypeName = (type: EventType): string => {
    const names = {
        time_in: 'Time In',
        time_out: 'Time Out',
        break_start: 'Break Start',
        break_end: 'Break End',
        overtime_start: 'Overtime Start',
        overtime_end: 'Overtime End'
    };
    return names[type] || type;
};

/**
 * Format time to 12-hour format
 */
const formatTime = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
};

/**
 * Format duration in hours and minutes
 */
const formatDuration = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours > 0 && mins > 0) {
        return `${hours}h ${mins}m`;
    } else if (hours > 0) {
        return `${hours}h`;
    } else {
        return `${mins}m`;
    }
};

/**
 * Calculate timeline segments between events
 */
const calculateSegments = (events: TimelineEvent[]): TimelineSegment[] => {
    const segments: TimelineSegment[] = [];
    
    for (let i = 0; i < events.length - 1; i++) {
        const current = events[i];
        const next = events[i + 1];
        
        const start = new Date(current.timestamp);
        const end = new Date(next.timestamp);
        const duration = Math.round((end.getTime() - start.getTime()) / 60000); // Minutes
        
        let segmentType: TimelineSegment['type'] = 'off-duty';
        
        if (current.eventType === 'time_in' && next.eventType === 'break_start') {
            segmentType = 'working';
        } else if (current.eventType === 'break_end' && (next.eventType === 'break_start' || next.eventType === 'time_out')) {
            segmentType = 'working';
        } else if (current.eventType === 'break_start' && next.eventType === 'break_end') {
            segmentType = 'break';
        } else if (current.eventType === 'overtime_start' && next.eventType === 'overtime_end') {
            segmentType = 'overtime';
        }
        
        segments.push({
            start,
            end,
            type: segmentType,
            duration
        });
    }
    
    return segments;
};

/**
 * Calculate summary statistics
 */
const calculateSummary = (events: TimelineEvent[]) => {
    const segments = calculateSegments(events);
    
    const totalWork = segments
        .filter(s => s.type === 'working')
        .reduce((sum, s) => sum + s.duration, 0);
    
    const totalBreak = segments
        .filter(s => s.type === 'break')
        .reduce((sum, s) => sum + s.duration, 0);
    
    const totalOvertime = segments
        .filter(s => s.type === 'overtime')
        .reduce((sum, s) => sum + s.duration, 0);
    
    return {
        totalWork: formatDuration(totalWork),
        totalBreak: formatDuration(totalBreak),
        totalOvertime: formatDuration(totalOvertime || 45) // Mock 45m overtime
    };
};

/**
 * Calculate position percentage on timeline (8 AM = 0%, 6 PM = 100%)
 */
const calculatePosition = (timestamp: string): number => {
    const date = new Date(timestamp);
    const hours = date.getHours();
    const minutes = date.getMinutes();
    
    // Timeline: 8:00 AM (0%) to 18:00 (6 PM) (100%)
    const startHour = 8;
    const endHour = 18;
    const totalMinutes = (endHour - startHour) * 60; // 600 minutes
    
    const eventMinutes = (hours - startHour) * 60 + minutes;
    const percentage = (eventMinutes / totalMinutes) * 100;
    
    return Math.max(0, Math.min(100, percentage));
};

/**
 * Get segment color
 */
const getSegmentColor = (type: TimelineSegment['type']): string => {
    const colors = {
        working: 'bg-green-400',
        break: 'bg-yellow-300',
        'off-duty': 'bg-gray-200',
        overtime: 'bg-purple-400'
    };
    return colors[type];
};

/**
 * Get violation details
 */
const getViolationDetails = (violationType?: string) => {
    const violations = {
        late_arrival: { 
            icon: AlertTriangle, 
            color: 'text-red-500', 
            bgColor: 'bg-red-50',
            borderColor: 'border-red-300',
            label: 'Late Arrival',
            description: 'Employee arrived after scheduled time'
        },
        early_departure: { 
            icon: AlertTriangle, 
            color: 'text-orange-500',
            bgColor: 'bg-orange-50',
            borderColor: 'border-orange-300',
            label: 'Early Departure',
            description: 'Employee left before scheduled time'
        },
        missing_punch: { 
            icon: XCircle, 
            color: 'text-red-600',
            bgColor: 'bg-red-100',
            borderColor: 'border-red-400',
            label: 'Missing Punch',
            description: 'Required time punch is missing'
        },
        extended_break: { 
            icon: Clock, 
            color: 'text-yellow-600',
            bgColor: 'bg-yellow-50',
            borderColor: 'border-yellow-300',
            label: 'Extended Break',
            description: 'Break exceeded scheduled duration'
        }
    };
    return violationType ? violations[violationType as keyof typeof violations] : null;
};

/**
 * Employee Daily Timeline View Component
 * Displays a horizontal timeline of employee's RFID tap events for a single day
 */
export function EmployeeTimelineView({
    employeeId = 'EMP-2024-001',
    employeeName = 'Juan Dela Cruz',
    employeePhoto,
    date = '2026-01-29',
    events = mockTimelineEvents,
    className
}: EmployeeTimelineViewProps) {
    const summary = calculateSummary(events);
    const segments = calculateSegments(events);

    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Avatar className="h-12 w-12">
                            <AvatarImage src={employeePhoto} alt={employeeName} />
                            <AvatarFallback>
                                {employeeName.split(' ').map(n => n[0]).join('')}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <CardTitle className="text-xl">{employeeName}</CardTitle>
                            <CardDescription>
                                {new Date(date).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}
                            </CardDescription>
                        </div>
                    </div>
                    
                    {/* Summary Stats */}
                    <div className="flex gap-4 text-sm">
                        <div className="text-center">
                            <div className="text-xs text-muted-foreground">Total Work</div>
                            <div className="font-semibold text-green-700">{summary.totalWork}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-xs text-muted-foreground">Break</div>
                            <div className="font-semibold text-yellow-700">{summary.totalBreak}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-xs text-muted-foreground">Overtime</div>
                            <div className="font-semibold text-purple-700">{summary.totalOvertime}</div>
                        </div>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                {/* Timeline Container */}
                <div className="space-y-6">
                    {/* Time Labels */}
                    <div className="flex justify-between text-xs text-muted-foreground font-mono px-4">
                        <span>8:00 AM</span>
                        <span>10:00 AM</span>
                        <span>12:00 PM</span>
                        <span>2:00 PM</span>
                        <span>4:00 PM</span>
                        <span>6:00 PM</span>
                    </div>

                    {/* Timeline Track */}
                    <div className="relative px-4">
                        {/* Base Timeline Line */}
                        <div className="absolute top-1/2 left-4 right-4 h-2 bg-gray-200 rounded-full -translate-y-1/2" />

                        {/* Scheduled Time Ghost Markers */}
                        {mockScheduledTimes.map((scheduled, idx) => {
                            const position = calculatePosition(scheduled.scheduledTime);
                            return (
                                <Tooltip key={`scheduled-${idx}`}>
                                    <TooltipTrigger asChild>
                                        <div
                                            className="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 z-5 cursor-help"
                                            style={{ left: `${position}%` }}
                                        >
                                            <div className="w-8 h-8 rounded-full border-2 border-dashed border-gray-400 bg-white/50 backdrop-blur-sm flex items-center justify-center opacity-60">
                                                <div className="w-2 h-2 rounded-full bg-gray-400" />
                                            </div>
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <div className="text-xs space-y-1">
                                            <div className="font-semibold">Scheduled Time</div>
                                            <div className="font-mono">{formatTime(scheduled.scheduledTime)}</div>
                                            <div className="text-muted-foreground">{getEventTypeName(scheduled.eventType)}</div>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}

                        {/* Colored Segments */}
                        {segments.map((segment, idx) => {
                            const startPos = calculatePosition(segment.start.toISOString());
                            const endPos = calculatePosition(segment.end.toISOString());
                            const width = endPos - startPos;
                            
                            return (
                                <div
                                    key={idx}
                                    className={`absolute top-1/2 h-2 rounded ${getSegmentColor(segment.type)} -translate-y-1/2`}
                                    style={{
                                        left: `${startPos}%`,
                                        width: `${width}%`
                                    }}
                                />
                            );
                        })}

                        {/* Event Markers */}
                        {events.map((event) => {
                            const position = calculatePosition(event.timestamp);
                            const hasViolation = event.violationType !== undefined;
                            const violationDetails = getViolationDetails(event.violationType);
                            const ViolationIcon = violationDetails?.icon;
                            
                            return (
                                <Tooltip key={event.id}>
                                    <TooltipTrigger asChild>
                                        <div
                                            className="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 cursor-pointer"
                                            style={{ left: `${position}%` }}
                                        >
                                            {/* Violation Background Highlight */}
                                            {hasViolation && violationDetails && (
                                                <div className={`absolute inset-0 -m-2 rounded-full ${violationDetails.bgColor} ${violationDetails.borderColor} border-2 animate-pulse`} />
                                            )}
                                            
                                            {/* Event Marker */}
                                            <div
                                                className={`relative w-10 h-10 rounded-full border-4 flex items-center justify-center text-white shadow-lg transition-transform hover:scale-125 ${getEventColor(
                                                    event.eventType
                                                )} ${hasViolation && violationDetails ? `ring-4 ${violationDetails.borderColor.replace('border-', 'ring-')}` : ''}`}
                                            >
                                                {getEventIcon(event.eventType)}
                                            </div>
                                            
                                            {/* Violation Warning Icon */}
                                            {hasViolation && ViolationIcon && (
                                                <div className="absolute -top-7 left-1/2 -translate-x-1/2">
                                                    <div className={`p-1 rounded-full bg-white shadow-md ${violationDetails!.borderColor} border-2`}>
                                                        <ViolationIcon className={`h-4 w-4 ${violationDetails!.color}`} />
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent className="w-64 p-4">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between gap-2 flex-wrap">
                                                <Badge variant="outline" className={getEventColor(event.eventType).replace('bg-', 'border-').replace('border-', 'text-')}>
                                                    {getEventTypeName(event.eventType)}
                                                </Badge>
                                                {event.verified && (
                                                    <Badge variant="outline" className="text-xs">
                                                        Verified
                                                    </Badge>
                                                )}
                                                {hasViolation && violationDetails && (
                                                    <Badge variant="destructive" className="text-xs">
                                                        {violationDetails.label}
                                                    </Badge>
                                                )}
                                            </div>
                                            
                                            {/* Violation Alert */}
                                            {hasViolation && violationDetails && (
                                                <div className={`p-2 rounded ${violationDetails.bgColor} ${violationDetails.borderColor} border text-xs`}>
                                                    <div className="flex items-start gap-2">
                                                        {ViolationIcon && <ViolationIcon className={`h-4 w-4 ${violationDetails.color} flex-shrink-0 mt-0.5`} />}
                                                        <div>
                                                            <div className="font-semibold mb-1">{violationDetails.label}</div>
                                                            <div className="text-muted-foreground">{violationDetails.description}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                            
                                            <div className="space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Actual Time:</span>
                                                    <span className="font-mono font-semibold">{formatTime(event.timestamp)}</span>
                                                </div>
                                                
                                                {event.scheduledTime && (
                                                    <div className="flex justify-between">
                                                        <span className="text-muted-foreground">Scheduled:</span>
                                                        <span className="font-mono text-xs">{formatTime(event.scheduledTime)}</span>
                                                    </div>
                                                )}
                                                
                                                {event.variance !== undefined && event.variance !== 0 && (
                                                    <div className={`flex justify-between ${event.variance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                        <span className="text-muted-foreground">Variance:</span>
                                                        <span className="font-semibold">
                                                            {event.variance > 0 ? '+' : ''}{event.variance} min
                                                            {event.variance > 0 && ' (Late)'}
                                                        </span>
                                                    </div>
                                                )}
                                                
                                                <div className="pt-2 border-t">
                                                    <div className="text-xs text-muted-foreground">Location:</div>
                                                    <div className="text-xs">{event.deviceLocation}</div>
                                                </div>
                                                
                                                <div className="text-xs text-muted-foreground">
                                                    Sequence #{event.sequenceId}
                                                </div>
                                            </div>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}
                    </div>

                    {/* Event Labels Below Timeline */}
                    <div className="relative px-4 mt-12">
                        {events.map((event) => {
                            const position = calculatePosition(event.timestamp);
                            const violationDetails = getViolationDetails(event.violationType);
                            
                            return (
                                <div
                                    key={event.id}
                                    className="absolute -translate-x-1/2"
                                    style={{ left: `${position}%` }}
                                >
                                    <div className="flex flex-col items-center gap-1 text-xs">
                                        <div className="font-mono font-semibold">{formatTime(event.timestamp)}</div>
                                        <div className="text-muted-foreground whitespace-nowrap">
                                            {getEventTypeName(event.eventType)}
                                        </div>
                                        {event.violationType && violationDetails && (
                                            <div className={`${violationDetails.color.replace('text-', 'text-')} font-semibold px-2 py-0.5 rounded ${violationDetails.bgColor}`}>
                                                {event.variance !== undefined && event.variance !== 0 && (
                                                    <span>
                                                        ({event.variance > 0 ? '+' : ''}{event.variance}m)
                                                    </span>
                                                )}
                                                {!event.variance && violationDetails.label}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Legend */}
                <div className="mt-8 pt-4 border-t">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                        <div className="flex items-center gap-2">
                            <div className="w-4 h-2 bg-green-400 rounded" />
                            <span className="text-muted-foreground">Working</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="w-4 h-2 bg-yellow-300 rounded" />
                            <span className="text-muted-foreground">Break</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="w-4 h-2 bg-purple-400 rounded" />
                            <span className="text-muted-foreground">Overtime</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="w-3 h-3 rounded-full border-2 border-dashed border-gray-400 bg-white/50" />
                            <span className="text-muted-foreground">Scheduled</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-4 w-4 text-red-500" />
                            <span className="text-muted-foreground">Late Arrival</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-4 w-4 text-orange-500" />
                            <span className="text-muted-foreground">Early Departure</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <XCircle className="h-4 w-4 text-red-600" />
                            <span className="text-muted-foreground">Missing Punch</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Clock className="h-4 w-4 text-yellow-600" />
                            <span className="text-muted-foreground">Extended Break</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

// Export mock data for use in other components
export { mockTimelineEvents };
