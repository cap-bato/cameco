import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import { Clock, Coffee, LogIn, LogOut, AlertTriangle, XCircle, Users, Filter } from 'lucide-react';
import { EventType } from '@/types/timekeeping-pages';

/**
 * Timeline Event Interface
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
    variance?: number;
    violationType?: 'late_arrival' | 'early_departure' | 'missing_punch' | 'extended_break';
}

/**
 * Employee Data for Comparison
 */
interface EmployeeTimelineData {
    employeeId: string;
    employeeName: string;
    employeePhoto?: string;
    department: string;
    position: string;
    events: TimelineEvent[];
    totalWork: string;
    totalBreak: string;
    violations: number;
}

interface EmployeeTimelineComparisonProps {
    employees?: EmployeeTimelineData[];
    date?: string;
    className?: string;
}

/**
 * Mock employee data for comparison
 */
const mockEmployeeData: EmployeeTimelineData[] = [
    {
        employeeId: 'EMP-2024-001',
        employeeName: 'Juan Dela Cruz',
        department: 'Production',
        position: 'Machine Operator',
        totalWork: '8h 45m',
        totalBreak: '1h 15m',
        violations: 2,
        events: [
            {
                id: 1,
                sequenceId: 12345,
                employeeId: 'EMP-2024-001',
                employeeName: 'Juan Dela Cruz',
                eventType: 'time_in',
                timestamp: '2026-01-29T08:05:00',
                deviceLocation: 'Gate 1',
                verified: true,
                scheduledTime: '2026-01-29T08:00:00',
                variance: 5,
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
                verified: true
            },
            {
                id: 4,
                sequenceId: 12348,
                employeeId: 'EMP-2024-001',
                employeeName: 'Juan Dela Cruz',
                eventType: 'break_start',
                timestamp: '2026-01-29T15:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 5,
                sequenceId: 12349,
                employeeId: 'EMP-2024-001',
                employeeName: 'Juan Dela Cruz',
                eventType: 'break_end',
                timestamp: '2026-01-29T15:15:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 6,
                sequenceId: 12350,
                employeeId: 'EMP-2024-001',
                employeeName: 'Juan Dela Cruz',
                eventType: 'time_out',
                timestamp: '2026-01-29T16:45:00',
                deviceLocation: 'Gate 2',
                verified: true,
                scheduledTime: '2026-01-29T17:00:00',
                variance: -15,
                violationType: 'early_departure'
            }
        ]
    },
    {
        employeeId: 'EMP-2024-015',
        employeeName: 'Maria Santos',
        department: 'Production',
        position: 'Quality Inspector',
        totalWork: '9h 00m',
        totalBreak: '1h 00m',
        violations: 0,
        events: [
            {
                id: 7,
                sequenceId: 12351,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'time_in',
                timestamp: '2026-01-29T07:58:00',
                deviceLocation: 'Gate 1',
                verified: true,
                scheduledTime: '2026-01-29T08:00:00',
                variance: -2
            },
            {
                id: 8,
                sequenceId: 12352,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'break_start',
                timestamp: '2026-01-29T12:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 9,
                sequenceId: 12353,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'break_end',
                timestamp: '2026-01-29T12:30:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 10,
                sequenceId: 12354,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'break_start',
                timestamp: '2026-01-29T15:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 11,
                sequenceId: 12355,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'break_end',
                timestamp: '2026-01-29T15:15:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 12,
                sequenceId: 12356,
                employeeId: 'EMP-2024-015',
                employeeName: 'Maria Santos',
                eventType: 'time_out',
                timestamp: '2026-01-29T17:00:00',
                deviceLocation: 'Gate 1',
                verified: true,
                scheduledTime: '2026-01-29T17:00:00',
                variance: 0
            }
        ]
    },
    {
        employeeId: 'EMP-2024-032',
        employeeName: 'Pedro Reyes',
        department: 'Production',
        position: 'Welder',
        totalWork: '8h 15m',
        totalBreak: '1h 45m',
        violations: 1,
        events: [
            {
                id: 13,
                sequenceId: 12357,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'time_in',
                timestamp: '2026-01-29T08:00:00',
                deviceLocation: 'Gate 1',
                verified: true,
                scheduledTime: '2026-01-29T08:00:00',
                variance: 0
            },
            {
                id: 14,
                sequenceId: 12358,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'break_start',
                timestamp: '2026-01-29T12:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 15,
                sequenceId: 12359,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'break_end',
                timestamp: '2026-01-29T12:45:00',
                deviceLocation: 'Cafeteria',
                verified: true,
                scheduledTime: '2026-01-29T12:30:00',
                variance: 15,
                violationType: 'extended_break'
            },
            {
                id: 16,
                sequenceId: 12360,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'break_start',
                timestamp: '2026-01-29T15:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 17,
                sequenceId: 12361,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'break_end',
                timestamp: '2026-01-29T15:15:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 18,
                sequenceId: 12362,
                employeeId: 'EMP-2024-032',
                employeeName: 'Pedro Reyes',
                eventType: 'time_out',
                timestamp: '2026-01-29T17:00:00',
                deviceLocation: 'Gate 2',
                verified: true,
                scheduledTime: '2026-01-29T17:00:00',
                variance: 0
            }
        ]
    },
    {
        employeeId: 'EMP-2024-048',
        employeeName: 'Ana Mercado',
        department: 'Production',
        position: 'Assembly Line Worker',
        totalWork: '9h 30m',
        totalBreak: '1h 00m',
        violations: 0,
        events: [
            {
                id: 19,
                sequenceId: 12363,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'time_in',
                timestamp: '2026-01-29T07:55:00',
                deviceLocation: 'Gate 1',
                verified: true,
                scheduledTime: '2026-01-29T08:00:00',
                variance: -5
            },
            {
                id: 20,
                sequenceId: 12364,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'break_start',
                timestamp: '2026-01-29T12:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 21,
                sequenceId: 12365,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'break_end',
                timestamp: '2026-01-29T12:30:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 22,
                sequenceId: 12366,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'break_start',
                timestamp: '2026-01-29T15:00:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 23,
                sequenceId: 12367,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'break_end',
                timestamp: '2026-01-29T15:15:00',
                deviceLocation: 'Cafeteria',
                verified: true
            },
            {
                id: 24,
                sequenceId: 12368,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'overtime_start',
                timestamp: '2026-01-29T17:00:00',
                deviceLocation: 'Production Floor',
                verified: true
            },
            {
                id: 25,
                sequenceId: 12369,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'overtime_end',
                timestamp: '2026-01-29T18:30:00',
                deviceLocation: 'Production Floor',
                verified: true
            },
            {
                id: 26,
                sequenceId: 12370,
                employeeId: 'EMP-2024-048',
                employeeName: 'Ana Mercado',
                eventType: 'time_out',
                timestamp: '2026-01-29T18:30:00',
                deviceLocation: 'Gate 1',
                verified: true
            }
        ]
    }
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
    return <IconComponent className="h-3 w-3" />;
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
 * Calculate position percentage on timeline (8 AM = 0%, 6 PM = 100%)
 */
const calculatePosition = (timestamp: string): number => {
    const date = new Date(timestamp);
    const hours = date.getHours();
    const minutes = date.getMinutes();
    
    const startHour = 8;
    const endHour = 18;
    const totalMinutes = (endHour - startHour) * 60;
    
    const eventMinutes = (hours - startHour) * 60 + minutes;
    const percentage = (eventMinutes / totalMinutes) * 100;
    
    return Math.max(0, Math.min(100, percentage));
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
            borderColor: 'border-red-300'
        },
        early_departure: { 
            icon: AlertTriangle, 
            color: 'text-orange-500',
            bgColor: 'bg-orange-50',
            borderColor: 'border-orange-300'
        },
        missing_punch: { 
            icon: XCircle, 
            color: 'text-red-600',
            bgColor: 'bg-red-100',
            borderColor: 'border-red-400'
        },
        extended_break: { 
            icon: Clock, 
            color: 'text-yellow-600',
            bgColor: 'bg-yellow-50',
            borderColor: 'border-yellow-300'
        }
    };
    return violationType ? violations[violationType as keyof typeof violations] : null;
};

/**
 * Employee Timeline Comparison Component
 * Displays multiple employees' timelines side-by-side for comparison
 */
export function EmployeeTimelineComparison({
    employees = mockEmployeeData,
    date = '2026-01-29',
    className
}: EmployeeTimelineComparisonProps) {
    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <Users className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <CardTitle className="text-xl">Employee Timeline Comparison</CardTitle>
                            <CardDescription>
                                {new Date(date).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })} • {employees.length} Employees
                            </CardDescription>
                        </div>
                    </div>
                    
                    <Button variant="outline" size="sm">
                        <Filter className="h-4 w-4 mr-2" />
                        Filter Employees
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                <div className="space-y-6">
                    {/* Synchronized Time Labels */}
                    <div className="flex justify-between text-xs text-muted-foreground font-mono px-32 sticky top-0 bg-white z-10 pb-2 border-b">
                        <span>8:00 AM</span>
                        <span>10:00 AM</span>
                        <span>12:00 PM</span>
                        <span>2:00 PM</span>
                        <span>4:00 PM</span>
                        <span>6:00 PM</span>
                    </div>

                    {/* Employee Timeline Rows */}
                    <div className="space-y-4">
                        {employees.map((employee) => (
                            <div 
                                key={employee.employeeId}
                                className="group hover:bg-slate-50 rounded-lg p-4 transition-colors border border-transparent hover:border-slate-200"
                            >
                                <div className="flex items-center gap-4">
                                    {/* Employee Info (Fixed Width) */}
                                    <div className="w-56 flex-shrink-0">
                                        <div className="flex items-center gap-3">
                                            <Avatar className="h-10 w-10">
                                                <AvatarImage src={employee.employeePhoto} alt={employee.employeeName} />
                                                <AvatarFallback className="text-xs">
                                                    {employee.employeeName.split(' ').map(n => n[0]).join('')}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-sm truncate">{employee.employeeName}</div>
                                                <div className="text-xs text-muted-foreground truncate">{employee.position}</div>
                                            </div>
                                        </div>
                                        
                                        {/* Quick Stats */}
                                        <div className="mt-2 flex gap-2 text-xs">
                                            <span className="text-green-700 font-mono">{employee.totalWork}</span>
                                            <span className="text-muted-foreground">•</span>
                                            <span className="text-yellow-700 font-mono">{employee.totalBreak}</span>
                                            {employee.violations > 0 && (
                                                <>
                                                    <span className="text-muted-foreground">•</span>
                                                    <Badge variant="destructive" className="h-5 text-xs px-1.5">
                                                        {employee.violations} violation{employee.violations > 1 ? 's' : ''}
                                                    </Badge>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {/* Timeline Track */}
                                    <div className="flex-1 relative h-12">
                                        {/* Base Timeline Line */}
                                        <div className="absolute top-1/2 left-0 right-0 h-1.5 bg-gray-200 rounded-full -translate-y-1/2" />

                                        {/* Event Markers */}
                                        {employee.events.map((event) => {
                                            const position = calculatePosition(event.timestamp);
                                            const hasViolation = !!event.violationType;
                                            const violationDetails = getViolationDetails(event.violationType);
                                            const ViolationIcon = violationDetails?.icon;

                                            return (
                                                <Tooltip key={event.id}>
                                                    <TooltipTrigger asChild>
                                                        <div
                                                            className="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 cursor-help"
                                                            style={{ left: `${position}%` }}
                                                        >
                                                            {/* Violation Background */}
                                                            {hasViolation && violationDetails && (
                                                                <div className={`absolute inset-0 -m-1.5 rounded-full ${violationDetails.bgColor} ${violationDetails.borderColor} border-2 animate-pulse`} />
                                                            )}
                                                            
                                                            {/* Event Marker */}
                                                            <div className={`relative w-7 h-7 rounded-full ${getEventColor(event.eventType)} border-2 text-white flex items-center justify-center shadow-sm ${hasViolation ? `ring-2 ${violationDetails?.borderColor.replace('border-', 'ring-')}` : ''}`}>
                                                                {getEventIcon(event.eventType)}
                                                            </div>

                                                            {/* Violation Icon Badge */}
                                                            {hasViolation && ViolationIcon && (
                                                                <div className="absolute -top-5 -right-1 bg-white rounded-full p-0.5 shadow-sm">
                                                                    <ViolationIcon className={`h-3 w-3 ${violationDetails?.color}`} />
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top">
                                                        <div className="text-xs space-y-1.5 min-w-[180px]">
                                                            <div className="font-semibold">{event.eventType.replace('_', ' ').toUpperCase()}</div>
                                                            <div className="flex items-center justify-between gap-3">
                                                                <span className="text-muted-foreground">Time:</span>
                                                                <span className="font-mono">{formatTime(event.timestamp)}</span>
                                                            </div>
                                                            <div className="flex items-center justify-between gap-3">
                                                                <span className="text-muted-foreground">Location:</span>
                                                                <span className="text-xs">{event.deviceLocation}</span>
                                                            </div>
                                                            {event.variance !== undefined && event.variance !== 0 && (
                                                                <div className="flex items-center justify-between gap-3 pt-1 border-t">
                                                                    <span className="text-muted-foreground">Variance:</span>
                                                                    <span className={`font-mono font-semibold ${event.variance > 0 ? 'text-red-500' : 'text-green-500'}`}>
                                                                        {event.variance > 0 ? '+' : ''}{event.variance}m
                                                                    </span>
                                                                </div>
                                                            )}
                                                            {hasViolation && violationDetails && (
                                                                <div className={`mt-2 p-2 rounded ${violationDetails.bgColor} border ${violationDetails.borderColor} flex items-start gap-2`}>
                                                                    {ViolationIcon && <ViolationIcon className={`h-3 w-3 mt-0.5 flex-shrink-0 ${violationDetails.color}`} />}
                                                                    <span className={`text-xs ${violationDetails.color} font-medium`}>
                                                                        {event.violationType?.replace('_', ' ').toUpperCase()}
                                                                    </span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Legend */}
                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4 text-xs">
                                <span className="text-muted-foreground font-medium">Legend:</span>
                                <div className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-green-500 border border-green-600" />
                                    <span>Time In</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-red-500 border border-red-600" />
                                    <span>Time Out</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-yellow-500 border border-yellow-600" />
                                    <span>Break Start</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-blue-500 border border-blue-600" />
                                    <span>Break End</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-purple-500 border border-purple-600" />
                                    <span>Overtime</span>
                                </div>
                            </div>
                            
                            <div className="text-xs text-muted-foreground">
                                Hover over markers for details
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
