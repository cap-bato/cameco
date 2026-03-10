import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { EmployeeTimelineView } from '@/components/timekeeping/employee-timeline-view';
import { 
    ArrowLeft, 
    Calendar, 
    Briefcase, 
    Building,
    AlertTriangle,
    CheckCircle2
} from 'lucide-react';

// Declare route as a global function
declare global {
    function route(name: string, params?: Record<string, string | number>): string;
}

interface Employee {
    id: number;
    employee_id: string;
    name: string;
    department: string;
    position: string;
    photo?: string | null;
}

interface TimelineEvent {
    id: number;
    sequenceId: number;
    employeeId: string;
    employeeName: string;
    eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end' | 'overtime_start' | 'overtime_end';
    timestamp: string;
    deviceLocation: string;
    verified: boolean;
    scheduledTime?: string;
    variance?: number;
    violationType?: 'late_arrival' | 'early_departure' | 'missing_punch' | 'extended_break';
}

interface Schedule {
    id: number;
    name: string;
    type: string;
    timeIn: string;
    timeOut: string;
    breakStart: string;
    breakEnd: string;
    afternoonBreakStart?: string;
    afternoonBreakEnd?: string;
    totalHours: number;
}

interface Summary {
    status: 'compliant' | 'with_violations';
    hoursWorked: number;
    expectedHours: number;
    violations: TimelineEvent[];
    violationCount: number;
    timeInVariance: number;
    timeOutVariance: number;
}

interface EmployeeTimelineProps {
    employee: Employee;
    events: TimelineEvent[];
    schedule: Schedule;
    summary: Summary;
    date: string;
}

export default function EmployeeTimeline({ 
    employee, 
    events, 
    schedule, 
    summary, 
    date 
}: EmployeeTimelineProps) {
    const [selectedDate, setSelectedDate] = useState(date);
    
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };
    
    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <AppLayout>
            <Head title={`${employee.name} - Timeline`} />
            
            <div className="container mx-auto py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('hr.timekeeping.overview')}>
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Overview
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Employee Timeline</h1>
                            <p className="text-muted-foreground">
                                Attendance timeline for {formatDate(date)}
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        <Label htmlFor="date" className="sr-only">Select Date</Label>
                        <Input
                            id="date"
                            type="date"
                            value={selectedDate}
                            onChange={(e) => setSelectedDate(e.target.value)}
                            className="w-40"
                        />
                        <Button asChild>
                            <Link 
                                href={route('hr.timekeeping.employee.timeline', { employeeId: employee.id })}
                            >
                                <Calendar className="h-4 w-4 mr-2" />
                                View Date
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Employee Info & Summary */}
                <div className="grid gap-6 md:grid-cols-3">
                    {/* Employee Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Employee Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Avatar className="h-16 w-16">
                                    {employee.photo && <AvatarImage src={employee.photo} />}
                                    <AvatarFallback>{getInitials(employee.name)}</AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-medium">{employee.name}</div>
                                    <div className="text-sm text-muted-foreground">{employee.employee_id}</div>
                                </div>
                            </div>
                            
                            <div className="space-y-2 pt-2 border-t">
                                <div className="flex items-center gap-2 text-sm">
                                    <Building className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Department:</span>
                                    <span className="font-medium">{employee.department}</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <Briefcase className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Position:</span>
                                    <span className="font-medium">{employee.position}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Attendance Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Attendance Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Status</span>
                                <Badge variant={summary.status === 'compliant' ? 'default' : 'destructive'}>
                                    {summary.status === 'compliant' ? (
                                        <>
                                            <CheckCircle2 className="h-3 w-3 mr-1" />
                                            Compliant
                                        </>
                                    ) : (
                                        <>
                                            <AlertTriangle className="h-3 w-3 mr-1" />
                                            With Violations
                                        </>
                                    )}
                                </Badge>
                            </div>
                            
                            <div className="space-y-2 pt-2 border-t">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Hours Worked</span>
                                    <span className="font-medium">{summary.hoursWorked.toFixed(1)}h</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Expected Hours</span>
                                    <span className="font-medium">{summary.expectedHours.toFixed(1)}h</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Time In Variance</span>
                                    <span className={`font-medium ${summary.timeInVariance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                        {summary.timeInVariance > 0 ? '+' : ''}{summary.timeInVariance} min
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Time Out Variance</span>
                                    <span className={`font-medium ${summary.timeOutVariance < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                        {summary.timeOutVariance > 0 ? '+' : ''}{summary.timeOutVariance} min
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Violations */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Violations</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {summary.violationCount === 0 ? (
                                <div className="flex flex-col items-center justify-center py-8 text-center">
                                    <CheckCircle2 className="h-12 w-12 text-green-500 mb-2" />
                                    <p className="text-sm text-muted-foreground">
                                        No violations detected
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {summary.violations.map((violation) => (
                                        <div
                                            key={violation.id}
                                            className="p-3 rounded-lg border bg-red-50 dark:bg-red-950/20"
                                        >
                                            <div className="flex items-center gap-2">
                                                <AlertTriangle className="h-4 w-4 text-red-600" />
                                                <span className="text-sm font-medium">
                                                    {violation.violationType?.replace('_', ' ').toUpperCase()}
                                                </span>
                                            </div>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                {violation.eventType === 'time_in' && 'Late arrival by'} 
                                                {violation.eventType === 'time_out' && 'Early departure by'} 
                                                {' '}
                                                {Math.abs(violation.variance || 0)} minutes
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Timeline Visualization */}
                <EmployeeTimelineView
                    employeeId={employee.employee_id}
                    employeeName={employee.name}
                    employeePhoto={employee.photo ?? undefined}
                    date={date}
                    events={events}
                />

                {/* Schedule Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Work Schedule</CardTitle>
                        <CardDescription>Expected schedule for {formatDate(date)}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Schedule Name</span>
                                    <span className="font-medium">{schedule.name}</span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Type</span>
                                    <Badge variant="outline">{schedule.type}</Badge>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Total Hours</span>
                                    <span className="font-medium">{schedule.totalHours}h</span>
                                </div>
                            </div>
                            
                            <div className="space-y-2">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Time In</span>
                                    <span className="font-medium">
                                        {new Date(schedule.timeIn).toLocaleTimeString('en-PH', { 
                                            hour: '2-digit', 
                                            minute: '2-digit' 
                                        })}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Time Out</span>
                                    <span className="font-medium">
                                        {new Date(schedule.timeOut).toLocaleTimeString('en-PH', { 
                                            hour: '2-digit', 
                                            minute: '2-digit' 
                                        })}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">Lunch Break</span>
                                    <span className="font-medium">
                                        {new Date(schedule.breakStart).toLocaleTimeString('en-PH', { 
                                            hour: '2-digit', 
                                            minute: '2-digit' 
                                        })} - {new Date(schedule.breakEnd).toLocaleTimeString('en-PH', { 
                                            hour: '2-digit', 
                                            minute: '2-digit' 
                                        })}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
