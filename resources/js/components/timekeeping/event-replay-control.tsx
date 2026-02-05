import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Slider } from '@/components/ui/slider';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { 
    Play, 
    Pause, 
    SkipBack, 
    SkipForward, 
    Rewind,
    FastForward,
    Calendar,
    Clock,
    AlertTriangle,
    Download
} from 'lucide-react';
import { useState, useEffect, useMemo } from 'react';
import { cn } from '@/lib/utils';
import { format, parseISO, isAfter, isBefore, isEqual } from 'date-fns';

/**
 * Replay Control Component
 * Allows replaying past RFID events with timeline scrubbing and speed control
 * 
 * Implements Task 1.8.1, 1.8.2, 1.8.3, 1.8.4, 1.8.5, and 1.8.6:
 * - Timeline slider with drag functionality
 * - Play/Pause controls
 * - Speed control (1x, 2x, 5x, 10x)
 * - Jump to next/previous event
 * - Display current replay period
 * - Animate event stream to show events appearing in sequence (1.8.3)
 * - Jump to Violation button (1.8.4)
 * - Export replay report (1.8.5)
 * - Smooth transitions between events (1.8.6)
 */

interface ReplayEvent {
    id: number;
    sequenceId: number;
    employeeId: string;
    employeeName: string;
    eventType: 'time_in' | 'time_out' | 'break_start' | 'break_end';
    timestamp: string;
    deviceId: string;
    deviceLocation: string;
}

interface EventReplayControlProps {
    events?: ReplayEvent[];
    onReplayEvent?: (event: ReplayEvent) => void;
    onReplayComplete?: () => void;
    onVisibleEventsChange?: (events: ReplayEvent[]) => void; // For animating event stream (1.8.3)
    className?: string;
}

type PlaybackSpeed = 1 | 2 | 5 | 10;

/**
 * Mock events for replay demonstration
 * Simulates a full workday of RFID events (08:00 - 18:00)
 */
const generateMockReplayEvents = (): ReplayEvent[] => {
    const baseDate = '2026-01-28';
    const events: ReplayEvent[] = [];
    let sequenceId = 10000;

    // Helper to create event
    const createEvent = (hour: number, minute: number, employeeName: string, employeeId: string, eventType: ReplayEvent['eventType'], deviceId: string, location: string) => {
        const timestamp = `${baseDate}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`;
        events.push({
            id: sequenceId,
            sequenceId,
            employeeId,
            employeeName,
            eventType,
            timestamp,
            deviceId,
            deviceLocation: location
        });
        sequenceId++;
    };

    // Morning arrivals (8:00 - 8:30)
    createEvent(8, 3, 'Juan Dela Cruz', 'EMP-001', 'time_in', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(8, 5, 'Maria Santos', 'EMP-002', 'time_in', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(8, 8, 'Pedro Reyes', 'EMP-003', 'time_in', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(8, 12, 'Ana Garcia', 'EMP-004', 'time_in', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(8, 15, 'Carlos Mendoza', 'EMP-005', 'time_in', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(8, 18, 'Rosa Villanueva', 'EMP-006', 'time_in', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(8, 22, 'Luis Gonzales', 'EMP-007', 'time_in', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(8, 28, 'Sofia Ramirez', 'EMP-008', 'time_in', 'GATE-02', 'Gate 2 - Loading Dock');

    // Late arrivals (8:30 - 9:00)
    createEvent(8, 35, 'Miguel Torres', 'EMP-009', 'time_in', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(8, 42, 'Elena Cruz', 'EMP-010', 'time_in', 'GATE-02', 'Gate 2 - Loading Dock');

    // Morning break (10:00 - 10:15)
    createEvent(10, 0, 'Juan Dela Cruz', 'EMP-001', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(10, 2, 'Maria Santos', 'EMP-002', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(10, 15, 'Juan Dela Cruz', 'EMP-001', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(10, 17, 'Maria Santos', 'EMP-002', 'break_end', 'CAFETERIA-01', 'Cafeteria');

    // Lunch break (12:00 - 13:00)
    createEvent(12, 0, 'Juan Dela Cruz', 'EMP-001', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(12, 2, 'Maria Santos', 'EMP-002', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(12, 5, 'Pedro Reyes', 'EMP-003', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(12, 7, 'Ana Garcia', 'EMP-004', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(12, 10, 'Carlos Mendoza', 'EMP-005', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(13, 0, 'Juan Dela Cruz', 'EMP-001', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(13, 2, 'Maria Santos', 'EMP-002', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(13, 5, 'Pedro Reyes', 'EMP-003', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(13, 7, 'Ana Garcia', 'EMP-004', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(13, 10, 'Carlos Mendoza', 'EMP-005', 'break_end', 'CAFETERIA-01', 'Cafeteria');

    // Afternoon break (15:00 - 15:15)
    createEvent(15, 0, 'Rosa Villanueva', 'EMP-006', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(15, 3, 'Luis Gonzales', 'EMP-007', 'break_start', 'CAFETERIA-01', 'Cafeteria');
    createEvent(15, 15, 'Rosa Villanueva', 'EMP-006', 'break_end', 'CAFETERIA-01', 'Cafeteria');
    createEvent(15, 18, 'Luis Gonzales', 'EMP-007', 'break_end', 'CAFETERIA-01', 'Cafeteria');

    // End of day departures (17:00 - 18:00)
    createEvent(17, 0, 'Juan Dela Cruz', 'EMP-001', 'time_out', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(17, 3, 'Maria Santos', 'EMP-002', 'time_out', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(17, 8, 'Pedro Reyes', 'EMP-003', 'time_out', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(17, 12, 'Ana Garcia', 'EMP-004', 'time_out', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(17, 15, 'Carlos Mendoza', 'EMP-005', 'time_out', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(17, 20, 'Rosa Villanueva', 'EMP-006', 'time_out', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(17, 25, 'Luis Gonzales', 'EMP-007', 'time_out', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(17, 30, 'Sofia Ramirez', 'EMP-008', 'time_out', 'GATE-02', 'Gate 2 - Loading Dock');
    createEvent(17, 45, 'Miguel Torres', 'EMP-009', 'time_out', 'GATE-01', 'Gate 1 - Main Entrance');
    createEvent(18, 0, 'Elena Cruz', 'EMP-010', 'time_out', 'GATE-02', 'Gate 2 - Loading Dock');

    return events.sort((a, b) => a.timestamp.localeCompare(b.timestamp));
};

export function EventReplayControl({ 
    events: propEvents, 
    onReplayEvent,
    onReplayComplete,
    onVisibleEventsChange,
    className 
}: EventReplayControlProps) {
    const events = propEvents || generateMockReplayEvents();

    // Replay state
    const [isPlaying, setIsPlaying] = useState(false);
    const [playbackSpeed, setPlaybackSpeed] = useState<PlaybackSpeed>(1);
    const [sliderValue, setSliderValue] = useState(0);

    // Calculate time bounds from events
    const { startTime, endTime, totalDuration } = useMemo(() => {
        if (events.length === 0) {
            return { startTime: new Date(), endTime: new Date(), totalDuration: 0 };
        }

        const start = parseISO(events[0].timestamp);
        const end = parseISO(events[events.length - 1].timestamp);
        const duration = end.getTime() - start.getTime();

        return { startTime: start, endTime: end, totalDuration: duration };
    }, [events]);

    // Get current timestamp based on slider position
    const currentTimestamp = useMemo(() => {
        if (events.length === 0) return new Date();
        const progress = sliderValue / 100;
        const currentTime = startTime.getTime() + (totalDuration * progress);
        return new Date(currentTime);
    }, [sliderValue, startTime, totalDuration, events.length]);

    // Get events up to current timestamp (for animated display - Task 1.8.3)
    const visibleEvents = useMemo(() => {
        return events.filter(event => {
            const eventTime = parseISO(event.timestamp);
            return isBefore(eventTime, currentTimestamp) || isEqual(eventTime, currentTimestamp);
        });
    }, [events, currentTimestamp]);

    // Notify parent component when visible events change (Task 1.8.3)
    useEffect(() => {
        onVisibleEventsChange?.(visibleEvents);
    }, [visibleEvents, onVisibleEventsChange]);

    // Get current event index based on slider (computed from visible events)
    const currentEventIndex = useMemo(() => {
        const index = events.findIndex(event => {
            const eventTime = parseISO(event.timestamp);
            return isAfter(eventTime, currentTimestamp);
        });
        return index === -1 ? events.length - 1 : Math.max(0, index - 1);
    }, [currentTimestamp, events]);

    // Detect violations (Task 1.8.4)
    // Late arrival: time_in after 8:05 AM
    // Missing punch: missing time_out for a time_in (simplified detection)
    const violations = useMemo(() => {
        return events.map((event, index) => {
            const eventTime = parseISO(event.timestamp);
            const hour = eventTime.getHours();
            const minute = eventTime.getMinutes();
            
            let isViolation = false;
            let violationType: 'late' | 'missing_punch' | null = null;

            // Late arrival detection
            if (event.eventType === 'time_in') {
                // Consider late if time_in is after 8:05 AM
                if (hour > 8 || (hour === 8 && minute > 5)) {
                    isViolation = true;
                    violationType = 'late';
                }
            }

            // Missing punch detection (simplified: check if time_in without matching time_out)
            if (event.eventType === 'time_in') {
                const hasTimeOut = events.slice(index + 1).some(
                    e => e.employeeId === event.employeeId && e.eventType === 'time_out'
                );
                if (!hasTimeOut && index < events.length - 5) { // Only check if not one of the last events
                    isViolation = true;
                    violationType = 'missing_punch';
                }
            }

            return {
                eventIndex: index,
                event,
                isViolation,
                violationType
            };
        }).filter(v => v.isViolation);
    }, [events]);

    // Find next violation from current position (Task 1.8.4)
    const nextViolationIndex = useMemo(() => {
        const nextViolation = violations.find(v => v.eventIndex > currentEventIndex);
        return nextViolation?.eventIndex ?? -1;
    }, [violations, currentEventIndex]);

    // Playback animation with smooth transitions (Task 1.8.6)
    useEffect(() => {
        if (!isPlaying || events.length === 0) return;

        const interval = setInterval(() => {
            setSliderValue(prev => {
                // Smooth increment based on playback speed (Task 1.8.6)
                // Using smaller increments for smoother animation
                const baseIncrement = 0.3; // Base increment for 1x speed
                const increment = baseIncrement * playbackSpeed;
                const newValue = prev + increment;

                if (newValue >= 100) {
                    setIsPlaying(false);
                    onReplayComplete?.();
                    return 100;
                }

                // Trigger event callback when passing an event
                const newProgress = newValue / 100;
                const newTime = startTime.getTime() + (totalDuration * newProgress);
                const newTimestamp = new Date(newTime);

                const passedEvent = events.find(event => {
                    const eventTime = parseISO(event.timestamp);
                    const oldTime = startTime.getTime() + (totalDuration * (prev / 100));
                    const oldTimestamp = new Date(oldTime);
                    return isAfter(eventTime, oldTimestamp) && 
                           (isBefore(eventTime, newTimestamp) || isEqual(eventTime, newTimestamp));
                });

                if (passedEvent) {
                    onReplayEvent?.(passedEvent);
                }

                return newValue;
            });
        }, 100); // Update every 100ms for smooth animation (Task 1.8.6)

        return () => clearInterval(interval);
    }, [isPlaying, playbackSpeed, events, startTime, totalDuration, onReplayEvent, onReplayComplete]);

    // Control handlers
    const handlePlayPause = () => {
        if (sliderValue >= 100) {
            setSliderValue(0); // Reset if at end
        }
        setIsPlaying(!isPlaying);
    };

    const handleSpeedChange = (speed: PlaybackSpeed) => {
        setPlaybackSpeed(speed);
    };

    const handleSliderChange = (value: number[]) => {
        setSliderValue(value[0]);
        setIsPlaying(false); // Pause when manually scrubbing
    };

    const handlePreviousEvent = () => {
        if (currentEventIndex > 0) {
            const prevEvent = events[currentEventIndex - 1];
            const prevTime = parseISO(prevEvent.timestamp);
            const progress = ((prevTime.getTime() - startTime.getTime()) / totalDuration) * 100;
            setSliderValue(Math.max(0, progress));
            setIsPlaying(false);
        }
    };

    const handleNextEvent = () => {
        if (currentEventIndex < events.length - 1) {
            const nextEvent = events[currentEventIndex + 1];
            const nextTime = parseISO(nextEvent.timestamp);
            const progress = ((nextTime.getTime() - startTime.getTime()) / totalDuration) * 100;
            setSliderValue(Math.min(100, progress));
            setIsPlaying(false);
        }
    };

    const handleReset = () => {
        setSliderValue(0);
        setIsPlaying(false);
    };

    // Jump to next violation handler (Task 1.8.4)
    const handleJumpToViolation = () => {
        if (nextViolationIndex !== -1) {
            const violationEvent = events[nextViolationIndex];
            const violationTime = parseISO(violationEvent.timestamp);
            const progress = ((violationTime.getTime() - startTime.getTime()) / totalDuration) * 100;
            setSliderValue(Math.min(100, progress));
            setIsPlaying(false);
        }
    };

    // Export replay report handler (Task 1.8.5)
    const handleExportReplay = () => {
        if (events.length === 0) return;

        // Generate comprehensive replay report
        const report = {
            reportTitle: 'RFID Event Replay Report',
            generatedAt: new Date().toISOString(),
            replayPeriod: {
                startTime: format(startTime, 'yyyy-MM-dd HH:mm:ss'),
                endTime: format(endTime, 'yyyy-MM-dd HH:mm:ss'),
                duration: `${Math.round(totalDuration / 1000 / 60)} minutes`
            },
            summary: {
                totalEvents: events.length,
                eventsReplayed: visibleEvents.length,
                progress: `${Math.round(sliderValue)}%`,
                violations: violations.length,
                uniqueEmployees: new Set(events.map(e => e.employeeId)).size
            },
            eventBreakdown: {
                time_in: events.filter(e => e.eventType === 'time_in').length,
                time_out: events.filter(e => e.eventType === 'time_out').length,
                break_start: events.filter(e => e.eventType === 'break_start').length,
                break_end: events.filter(e => e.eventType === 'break_end').length
            },
            violations: violations.map(v => ({
                sequenceId: v.event.sequenceId,
                employeeId: v.event.employeeId,
                employeeName: v.event.employeeName,
                eventType: v.event.eventType,
                timestamp: format(parseISO(v.event.timestamp), 'yyyy-MM-dd HH:mm:ss'),
                violationType: v.violationType,
                deviceId: v.event.deviceId,
                deviceLocation: v.event.deviceLocation
            })),
            events: visibleEvents.map(e => ({
                sequenceId: e.sequenceId,
                employeeId: e.employeeId,
                employeeName: e.employeeName,
                eventType: e.eventType,
                timestamp: format(parseISO(e.timestamp), 'yyyy-MM-dd HH:mm:ss'),
                deviceId: e.deviceId,
                deviceLocation: e.deviceLocation
            }))
        };

        // Convert to JSON and trigger download
        const jsonString = JSON.stringify(report, null, 2);
        const blob = new Blob([jsonString], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `rfid-replay-report-${format(new Date(), 'yyyyMMdd-HHmmss')}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    // Format current replay period
    const replayPeriodText = useMemo(() => {
        if (events.length === 0) return 'No events to replay';
        const dateStr = format(startTime, 'MMMM d, yyyy');
        const startTimeStr = format(startTime, 'HH:mm');
        const endTimeStr = format(endTime, 'HH:mm');
        return `Replaying: ${dateStr}  ${startTimeStr} → ${endTimeStr}`;
    }, [events.length, startTime, endTime]);

    const currentTimeStr = format(currentTimestamp, 'HH:mm:ss');

    if (events.length === 0) {
        return (
            <Card className={cn('w-full', className)}>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Event Replay Control
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-center py-8 text-gray-500">
                        <Calendar className="h-12 w-12 mx-auto mb-3 opacity-50" />
                        <p>No events available for replay</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('w-full', className)}>
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Event Replay Control
                    </div>
                    <Badge variant={isPlaying ? 'default' : 'secondary'} className="font-mono">
                        {isPlaying ? '▶ PLAYING' : '⏸ PAUSED'}
                    </Badge>
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                {/* Replay Period Display (Task 1.8.2) */}
                <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                            <Calendar className="h-4 w-4" />
                            <span className="font-medium">{replayPeriodText}</span>
                        </div>
                        <Badge variant="outline" className="font-mono text-lg px-3 py-1">
                            {playbackSpeed}x
                        </Badge>
                    </div>
                </div>

                {/* Timeline Slider with smooth transitions (Task 1.8.1 & 1.8.6) */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 transition-all duration-200">
                        <span className="font-mono">{format(startTime, 'HH:mm')}</span>
                        <div className="flex items-center gap-2 transition-all duration-200">
                            <Clock className="h-4 w-4" />
                            <span className="font-mono font-semibold text-base text-gray-900 dark:text-gray-100">
                                Current: {currentTimeStr}
                            </span>
                        </div>
                        <span className="font-mono">{format(endTime, 'HH:mm')}</span>
                    </div>

                    {/* Slider with smooth animation (Task 1.8.6) */}
                    <div className="relative px-2">
                        <Slider
                            value={[sliderValue]}
                            onValueChange={handleSliderChange}
                            max={100}
                            step={0.1}
                            className="cursor-pointer transition-all duration-75"
                        />
                        
                        {/* Event markers on timeline with smooth transitions (Task 1.8.6) */}
                        <div className="absolute top-1 left-2 right-2 h-2 pointer-events-none">
                            {events.map((event) => {
                                const eventTime = parseISO(event.timestamp);
                                const position = ((eventTime.getTime() - startTime.getTime()) / totalDuration) * 100;
                                const isPassed = position <= sliderValue;
                                
                                // Check if this event is a violation
                                const violation = violations.find(v => v.eventIndex === events.indexOf(event));
                                const isViolation = !!violation;
                                
                                return (
                                    <Tooltip key={event.id}>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={cn(
                                                    'absolute w-1.5 h-1.5 rounded-full transform -translate-x-1/2 transition-all duration-200',
                                                    isViolation 
                                                        ? isPassed 
                                                            ? 'bg-red-500 ring-2 ring-red-300 scale-125' 
                                                            : 'bg-red-300 ring-2 ring-red-200'
                                                        : isPassed 
                                                            ? 'bg-blue-500 scale-110' 
                                                            : 'bg-gray-300 dark:bg-gray-600'
                                                )}
                                                style={{ left: `${position}%`, top: '-2px' }}
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <div className="text-xs">
                                                <div className="font-semibold">{event.employeeName}</div>
                                                <div>{event.eventType.replace('_', ' ').toUpperCase()}</div>
                                                <div className="text-gray-400">{format(eventTime, 'HH:mm:ss')}</div>
                                                {isViolation && (
                                                    <div className="text-red-400 font-semibold mt-1 flex items-center gap-1">
                                                        <AlertTriangle className="h-3 w-3" />
                                                        {violation.violationType === 'late' ? 'Late Arrival' : 'Missing Punch'}
                                                    </div>
                                                )}
                                            </div>
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>
                    </div>

                    {/* Progress info */}
                    <div className="flex items-center justify-between text-xs text-gray-500">
                        <span>Event {currentEventIndex + 1} of {events.length}</span>
                        <span>{visibleEvents.length} events replayed</span>
                        {violations.length > 0 && (
                            <span className="text-red-500 font-semibold flex items-center gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                {violations.length} violation{violations.length !== 1 ? 's' : ''}
                            </span>
                        )}
                        <span>{Math.round(sliderValue)}% complete</span>
                    </div>
                </div>

                {/* Playback Controls (Task 1.8.1 & 1.8.4) */}
                <div className="space-y-4">
                    {/* Jump to Violation Button (Task 1.8.4) */}
                    {violations.length > 0 && (
                        <div className="flex items-center justify-center">
                            <Button
                                variant="destructive"
                                size="default"
                                onClick={handleJumpToViolation}
                                disabled={nextViolationIndex === -1}
                                className="gap-2"
                            >
                                <AlertTriangle className="h-4 w-4" />
                                Jump to Violation
                                {nextViolationIndex !== -1 && (
                                    <Badge variant="secondary" className="ml-2">
                                        {violations.filter(v => v.eventIndex > currentEventIndex).length} remaining
                                    </Badge>
                                )}
                            </Button>
                        </div>
                    )}

                    {/* Main controls */}
                    <div className="flex items-center justify-center gap-2">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={handleReset}
                                    disabled={sliderValue === 0}
                                >
                                    <Rewind className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Reset to start</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={handlePreviousEvent}
                                    disabled={currentEventIndex === 0}
                                >
                                    <SkipBack className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Previous event</TooltipContent>
                        </Tooltip>

                        <Button
                            size="lg"
                            onClick={handlePlayPause}
                            className="w-24"
                        >
                            {isPlaying ? (
                                <>
                                    <Pause className="h-5 w-5 mr-2" />
                                    Pause
                                </>
                            ) : (
                                <>
                                    <Play className="h-5 w-5 mr-2" />
                                    Play
                                </>
                            )}
                        </Button>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={handleNextEvent}
                                    disabled={currentEventIndex === events.length - 1}
                                >
                                    <SkipForward className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Next event</TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={() => setSliderValue(100)}
                                    disabled={sliderValue === 100}
                                >
                                    <FastForward className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Jump to end</TooltipContent>
                        </Tooltip>
                    </div>

                    {/* Speed controls */}
                    <div className="flex items-center justify-center gap-2">
                        <span className="text-sm text-gray-600 dark:text-gray-400 mr-2">Speed:</span>
                        {([1, 2, 5, 10] as PlaybackSpeed[]).map(speed => (
                            <Button
                                key={speed}
                                variant={playbackSpeed === speed ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => handleSpeedChange(speed)}
                                className="w-16 font-mono"
                            >
                                {speed}x
                            </Button>
                        ))}
                    </div>

                    {/* Export Replay Button (Task 1.8.5) */}
                    <div className="flex items-center justify-center">
                        <Button
                            variant="outline"
                            size="default"
                            onClick={handleExportReplay}
                            disabled={visibleEvents.length === 0}
                            className="gap-2"
                        >
                            <Download className="h-4 w-4" />
                            Export Replay Report
                            {visibleEvents.length > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {visibleEvents.length} events
                                </Badge>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Current event info with smooth transition (Task 1.8.6) */}
                {events[currentEventIndex] && (
                    <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border transition-all duration-300 ease-in-out animate-in fade-in slide-in-from-bottom-2">
                        <div className="text-xs text-gray-500 mb-1">Current Event:</div>
                        <div className="flex items-center justify-between">
                            <div className="transition-all duration-200">
                                <div className="font-semibold">{events[currentEventIndex].employeeName}</div>
                                <div className="text-sm text-gray-600 dark:text-gray-400">
                                    {events[currentEventIndex].eventType.replace('_', ' ').toUpperCase()} at {events[currentEventIndex].deviceLocation}
                                </div>
                            </div>
                            <Badge variant="secondary" className="font-mono transition-all duration-200">
                                #{events[currentEventIndex].sequenceId}
                            </Badge>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// Export mock events generator for testing
export { generateMockReplayEvents };
