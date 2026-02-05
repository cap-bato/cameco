import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { 
    User, 
    Calendar, 
    MapPin, 
    Hash, 
    Clock, 
    Activity, 
    CheckCircle2, 
    AlertTriangle,
    Server,
    Code,
    ChevronDown,
    ChevronUp,
    Download,
    AlertCircle,
    ChevronLeft,
    ChevronRight
} from 'lucide-react';
import { EventType } from '@/types/timekeeping-pages';
import { useState } from 'react';

/**
 * Extended Event Data Interface
 * Includes all ledger, device, and processing metadata
 */
export interface EventDetailData {
    // Basic Event Info
    id: number;
    sequenceId: number;
    employeeId: string;
    employeeName: string;
    employeePhoto?: string;
    employeeDepartment?: string;
    employeePosition?: string;
    rfidCard: string;
    eventType: EventType;
    timestamp: string;
    
    // Device Info
    deviceId: string;
    deviceLocation: string;
    deviceStatus?: 'online' | 'offline' | 'maintenance';
    deviceLastMaintenance?: string;
    
    // Ledger Info
    verified: boolean;
    hashChain?: string;
    signature?: string;
    verificationStatus?: 'verified' | 'pending' | 'failed';
    
    // Processing Info
    processedAt?: string;
    latencyMs?: number;
    summaryImpact?: string;
    
    // Duration (if paired event)
    duration?: string;
    pairedWithSequence?: number;
}

interface EventDetailModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    event: EventDetailData | null;
    allLogs?: EventDetailData[];
    onNavigate?: (event: EventDetailData) => void;
}

/**
 * Get display name for event type
 */
const getEventTypeName = (type: EventType): string => {
    const names: Record<EventType, string> = {
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
 * Get color for event type
 */
const getEventTypeColor = (type: EventType): string => {
    const colors: Record<EventType, string> = {
        time_in: 'bg-green-100 text-green-700 border-green-200',
        time_out: 'bg-red-100 text-red-700 border-red-200',
        break_start: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        break_end: 'bg-blue-100 text-blue-700 border-blue-200',
        overtime_start: 'bg-purple-100 text-purple-700 border-purple-200',
        overtime_end: 'bg-pink-100 text-pink-700 border-pink-200'
    };
    return colors[type] || 'bg-gray-100 text-gray-700 border-gray-200';
};

/**
 * Format timestamp to readable format
 */
const formatTimestamp = (timestamp: string): string => {
    const date = new Date(timestamp);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
};

/**
 * Event Detail Modal Component
 * Displays comprehensive information about a single RFID attendance event
 */
export function EventDetailModal({ open, onOpenChange, event, allLogs = [], onNavigate }: EventDetailModalProps) {
    const [isRawDataOpen, setIsRawDataOpen] = useState(false);
    const [isReportIssueOpen, setIsReportIssueOpen] = useState(false);
    const [issueDescription, setIssueDescription] = useState('');

    if (!event) return null;

    // Find previous and next events in sequence
    const currentIndex = allLogs.findIndex(log => log.sequenceId === event.sequenceId);
    const previousEvent = currentIndex > 0 ? allLogs[currentIndex - 1] : null;
    const nextEvent = currentIndex < allLogs.length - 1 ? allLogs[currentIndex + 1] : null;

    /**
     * Navigate to previous or next event
     */
    const handleNavigate = (targetEvent: EventDetailData | null) => {
        if (targetEvent && onNavigate) {
            onNavigate(targetEvent);
        }
    };

    /**
     * Export event data as JSON file
     */
    const handleExportEvent = () => {
        const dataStr = JSON.stringify(rawLedgerData, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `event-${event.sequenceId}-${event.employeeId}-${new Date(event.timestamp).toISOString().split('T')[0]}.json`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    /**
     * Handle report issue submission
     */
    const handleReportIssue = () => {
        // In production, this would send to backend API
        console.log('Report Issue:', {
            eventId: event.id,
            sequenceId: event.sequenceId,
            employeeId: event.employeeId,
            timestamp: event.timestamp,
            description: issueDescription
        });
        
        // Close dialog and reset
        setIsReportIssueOpen(false);
        setIssueDescription('');
        
        // Show success message (in production, use toast notification)
        alert('Issue reported successfully. HR will review and respond within 24 hours.');
    };

    // Mock timeline data - in production, this would come from the API
    const mockTimelineEvents = [
        { time: '08:05 AM', type: 'time_in' as EventType, location: 'Gate 1', current: event.eventType === 'time_in' },
        { time: '10:15 AM', type: 'break_start' as EventType, location: 'Cafeteria', current: event.eventType === 'break_start' },
        { time: '10:30 AM', type: 'break_end' as EventType, location: 'Cafeteria', current: event.eventType === 'break_end' },
        { time: '12:00 PM', type: 'break_start' as EventType, location: 'Cafeteria', current: false },
        { time: '01:00 PM', type: 'break_end' as EventType, location: 'Cafeteria', current: false },
        { time: '05:30 PM', type: 'time_out' as EventType, location: 'Gate 2', current: false },
    ];

    // Generate raw ledger data as JSON
    const rawLedgerData = {
        id: event.id,
        sequence_id: event.sequenceId,
        employee: {
            id: event.employeeId,
            name: event.employeeName,
            rfid_card: event.rfidCard,
            department: event.employeeDepartment,
            position: event.employeePosition
        },
        event: {
            type: event.eventType,
            timestamp: event.timestamp,
            duration: event.duration,
            paired_sequence: event.pairedWithSequence
        },
        device: {
            id: event.deviceId,
            location: event.deviceLocation,
            status: event.deviceStatus,
            last_maintenance: event.deviceLastMaintenance
        },
        ledger: {
            verified: event.verified,
            hash_chain: event.hashChain,
            signature: event.signature,
            verification_status: event.verificationStatus
        },
        processing: {
            processed_at: event.processedAt,
            latency_ms: event.latencyMs,
            summary_impact: event.summaryImpact
        },
        metadata: {
            created_at: event.timestamp,
            updated_at: event.processedAt,
            version: '1.0.0'
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-2xl">Event Details</DialogTitle>
                    <DialogDescription>
                        Comprehensive information about this attendance event
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 mt-4">
                    {/* Employee Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <User className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Employee Information</h3>
                        </div>
                        <div className="flex items-start gap-4 p-4 bg-muted/50 rounded-lg">
                            <Avatar className="h-16 w-16">
                                <AvatarImage src={event.employeePhoto} alt={event.employeeName} />
                                <AvatarFallback className="text-lg">
                                    {event.employeeName.split(' ').map(n => n[0]).join('')}
                                </AvatarFallback>
                            </Avatar>
                            <div className="flex-1 space-y-2">
                                <div>
                                    <p className="text-lg font-semibold">{event.employeeName}</p>
                                    <p className="text-sm text-muted-foreground">Employee ID: {event.employeeId}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Department:</span>
                                        <p className="font-medium">{event.employeeDepartment || 'Manufacturing'}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Position:</span>
                                        <p className="font-medium">{event.employeePosition || 'Production Staff'}</p>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-sm">RFID Card:</span>
                                    <p className="font-mono text-sm">{event.rfidCard}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <Separator />

                    {/* Event Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <Calendar className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Event Information</h3>
                        </div>
                        <div className="space-y-3 p-4 bg-muted/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Event Type:</span>
                                <Badge className={getEventTypeColor(event.eventType)}>
                                    {getEventTypeName(event.eventType)}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Timestamp:</span>
                                <p className="font-mono text-sm font-medium">{formatTimestamp(event.timestamp)}</p>
                            </div>
                            {event.duration && (
                                <>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Duration:</span>
                                        <p className="text-sm font-medium">{event.duration}</p>
                                    </div>
                                    {event.pairedWithSequence && (
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Paired with Sequence:</span>
                                            <p className="font-mono text-sm">#{event.pairedWithSequence}</p>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </section>

                    <Separator />

                    {/* Device Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <MapPin className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Device Information</h3>
                        </div>
                        <div className="space-y-3 p-4 bg-muted/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Device ID:</span>
                                <p className="font-mono text-sm font-medium">{event.deviceId}</p>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Location:</span>
                                <p className="text-sm font-medium">{event.deviceLocation}</p>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Status:</span>
                                <Badge variant={event.deviceStatus === 'online' ? 'default' : 'secondary'}>
                                    <Activity className="h-3 w-3 mr-1" />
                                    {event.deviceStatus || 'online'}
                                </Badge>
                            </div>
                            {event.deviceLastMaintenance && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Last Maintenance:</span>
                                    <p className="text-sm">{formatTimestamp(event.deviceLastMaintenance)}</p>
                                </div>
                            )}
                        </div>
                    </section>

                    <Separator />

                    {/* Ledger Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <Hash className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Ledger & Verification</h3>
                        </div>
                        <div className="space-y-3 p-4 bg-muted/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Sequence ID:</span>
                                <p className="font-mono text-sm font-medium">#{event.sequenceId}</p>
                            </div>
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-sm text-muted-foreground">Hash Chain:</span>
                                <p className="font-mono text-xs break-all text-right max-w-md">
                                    {event.hashChain || 'N/A'}
                                </p>
                            </div>
                            {event.signature && (
                                <div className="flex items-start justify-between gap-2">
                                    <span className="text-sm text-muted-foreground">Signature:</span>
                                    <p className="font-mono text-xs break-all text-right max-w-md">
                                        {event.signature}
                                    </p>
                                </div>
                            )}
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Verification Status:</span>
                                <Badge variant={event.verified ? 'default' : 'destructive'}>
                                    {event.verified ? (
                                        <>
                                            <CheckCircle2 className="h-3 w-3 mr-1" />
                                            Verified
                                        </>
                                    ) : (
                                        <>
                                            <AlertTriangle className="h-3 w-3 mr-1" />
                                            {event.verificationStatus || 'Pending'}
                                        </>
                                    )}
                                </Badge>
                            </div>
                        </div>
                    </section>

                    <Separator />

                    {/* Processing Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <Server className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Processing Information</h3>
                        </div>
                        <div className="space-y-3 p-4 bg-muted/50 rounded-lg">
                            {event.processedAt && (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Processed At:</span>
                                    <p className="font-mono text-sm">{formatTimestamp(event.processedAt)}</p>
                                </div>
                            )}
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Processing Latency:</span>
                                <Badge variant="outline">
                                    <Clock className="h-3 w-3 mr-1" />
                                    {event.latencyMs || 0} ms
                                </Badge>
                            </div>
                            {event.summaryImpact && (
                                <div className="flex items-start justify-between gap-2">
                                    <span className="text-sm text-muted-foreground">Summary Impact:</span>
                                    <p className="text-sm text-right">{event.summaryImpact}</p>
                                </div>
                            )}
                        </div>
                    </section>

                    <Separator />

                    {/* Event Timeline Section */}
                    <section>
                        <div className="flex items-center gap-2 mb-4">
                            <Clock className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-lg font-semibold">Event Timeline (Today)</h3>
                        </div>

                        <div className="relative px-4 py-6 bg-muted/30 rounded-lg">
                            {/* Timeline Line */}
                            <div className="absolute top-1/2 left-8 right-8 h-0.5 bg-border -translate-y-1/2" />
                            
                            {/* Timeline Events */}
                            <div className="relative flex justify-between items-center">
                                {mockTimelineEvents.map((timelineEvent, index) => (
                                    <div key={index} className="flex flex-col items-center gap-2 z-10">
                                        {/* Event Marker */}
                                        <div 
                                            className={`w-4 h-4 rounded-full border-2 transition-all ${
                                                timelineEvent.current 
                                                    ? 'bg-blue-500 border-blue-600 ring-4 ring-blue-200 scale-125'
                                                    : 'bg-background border-muted-foreground'
                                            }`}
                                        />
                                        
                                        {/* Event Badge */}
                                        <Badge 
                                            variant="outline" 
                                            className={`text-xs whitespace-nowrap ${
                                                timelineEvent.current 
                                                    ? 'border-blue-500 text-blue-700 bg-blue-50 font-semibold'
                                                    : 'border-border'
                                            }`}
                                        >
                                            {getEventTypeName(timelineEvent.type)}
                                        </Badge>
                                        
                                        {/* Time & Location */}
                                        <div className="text-center">
                                            <div className={`text-xs font-mono ${
                                                timelineEvent.current ? 'text-blue-700 font-semibold' : 'text-muted-foreground'
                                            }`}>
                                                {timelineEvent.time}
                                            </div>
                                            <div className="text-xs text-muted-foreground">{timelineEvent.location}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <p className="text-xs text-muted-foreground text-center mt-3">
                            Showing all RFID events for {event.employeeName} today
                        </p>
                    </section>

                    <Separator />

                    {/* Raw Ledger Data Section */}
                    <section>
                        <Collapsible open={isRawDataOpen} onOpenChange={setIsRawDataOpen}>
                            <CollapsibleTrigger asChild>
                                <Button 
                                    variant="outline" 
                                    className="w-full flex items-center justify-between hover:bg-muted/50"
                                >
                                    <div className="flex items-center gap-2">
                                        <Code className="h-4 w-4" />
                                        <span className="text-sm font-medium">Raw Ledger Data</span>
                                    </div>
                                    {isRawDataOpen ? (
                                        <ChevronUp className="h-4 w-4" />
                                    ) : (
                                        <ChevronDown className="h-4 w-4" />
                                    )}
                                </Button>
                            </CollapsibleTrigger>
                            
                            <CollapsibleContent className="mt-4">
                                <div className="bg-slate-900 rounded-lg p-4 overflow-x-auto">
                                    <pre className="text-xs text-slate-100 font-mono leading-relaxed">
                                        {JSON.stringify(rawLedgerData, null, 2)}
                                    </pre>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    This is the raw cryptographically-signed event data from the PostgreSQL ledger
                                </p>
                            </CollapsibleContent>
                        </Collapsible>
                    </section>

                    <Separator />

                    {/* Related Events Section */}
                    <section>
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-2">
                                <Activity className="h-5 w-5 text-muted-foreground" />
                                <h3 className="text-lg font-semibold">Related Events</h3>
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Event {currentIndex + 1} of {allLogs.length}
                            </div>
                        </div>

                        <div className="flex gap-3">
                            {/* Previous Event */}
                            <div className="flex-1">
                                <Button
                                    variant="outline"
                                    className="w-full h-auto p-4 flex items-start gap-3 text-left"
                                    onClick={() => handleNavigate(previousEvent)}
                                    disabled={!previousEvent}
                                >
                                    <ChevronLeft className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                    <div className="flex-1 min-w-0">
                                        <div className="text-xs text-muted-foreground mb-1">Previous Event</div>
                                        {previousEvent ? (
                                            <>
                                                <div className="font-medium truncate">{previousEvent.employeeName}</div>
                                                <Badge 
                                                    variant="outline" 
                                                    className={`mt-1 text-xs ${getEventTypeColor(previousEvent.eventType)}`}
                                                >
                                                    {getEventTypeName(previousEvent.eventType)}
                                                </Badge>
                                                <div className="text-xs text-muted-foreground mt-1">
                                                    Seq #{previousEvent.sequenceId}
                                                </div>
                                            </>
                                        ) : (
                                            <div className="text-sm text-muted-foreground">No previous event</div>
                                        )}
                                    </div>
                                </Button>
                            </div>

                            {/* Next Event */}
                            <div className="flex-1">
                                <Button
                                    variant="outline"
                                    className="w-full h-auto p-4 flex items-start gap-3 text-left"
                                    onClick={() => handleNavigate(nextEvent)}
                                    disabled={!nextEvent}
                                >
                                    <div className="flex-1 min-w-0">
                                        <div className="text-xs text-muted-foreground mb-1">Next Event</div>
                                        {nextEvent ? (
                                            <>
                                                <div className="font-medium truncate">{nextEvent.employeeName}</div>
                                                <Badge 
                                                    variant="outline" 
                                                    className={`mt-1 text-xs ${getEventTypeColor(nextEvent.eventType)}`}
                                                >
                                                    {getEventTypeName(nextEvent.eventType)}
                                                </Badge>
                                                <div className="text-xs text-muted-foreground mt-1">
                                                    Seq #{nextEvent.sequenceId}
                                                </div>
                                            </>
                                        ) : (
                                            <div className="text-sm text-muted-foreground">No next event</div>
                                        )}
                                    </div>
                                    <ChevronRight className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                </Button>
                            </div>
                        </div>

                        <p className="mt-3 text-xs text-muted-foreground text-center">
                            Navigate through events in ledger sequence order
                        </p>
                    </section>

                    <Separator />

                    {/* Action Buttons */}
                    <section>
                        <div className="flex items-center gap-3">
                            <Button 
                                variant="default" 
                                className="flex-1 flex items-center justify-center gap-2"
                                onClick={handleExportEvent}
                            >
                                <Download className="h-4 w-4" />
                                <span>Export Event</span>
                            </Button>
                            
                            <Button 
                                variant="outline" 
                                className="flex-1 flex items-center justify-center gap-2 border-orange-300 text-orange-700 hover:bg-orange-50"
                                onClick={() => setIsReportIssueOpen(true)}
                            >
                                <AlertCircle className="h-4 w-4" />
                                <span>Report Issue</span>
                            </Button>
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground text-center">
                            Export for records or report disputed timestamps to HR
                        </p>
                    </section>
                </div>
            </DialogContent>

            {/* Report Issue Dialog */}
            <AlertDialog open={isReportIssueOpen} onOpenChange={setIsReportIssueOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertCircle className="h-5 w-5 text-orange-500" />
                            Report Issue with Event #{event.sequenceId}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Describe the issue with this attendance event. HR will review and respond within 24 hours.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    
                    <div className="space-y-4 py-4">
                        {/* Event Summary */}
                        <div className="p-3 bg-muted rounded-lg space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Employee:</span>
                                <span className="font-medium">{event.employeeName}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Event Type:</span>
                                <Badge variant="outline" className={getEventTypeColor(event.eventType)}>
                                    {getEventTypeName(event.eventType)}
                                </Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Timestamp:</span>
                                <span className="font-mono text-xs">{formatTimestamp(event.timestamp)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Location:</span>
                                <span>{event.deviceLocation}</span>
                            </div>
                        </div>

                        {/* Issue Description */}
                        <div className="space-y-2">
                            <label htmlFor="issue-description" className="text-sm font-medium">
                                Issue Description <span className="text-red-500">*</span>
                            </label>
                            <Textarea
                                id="issue-description"
                                placeholder="Example: The timestamp is incorrect. I actually tapped in at 8:00 AM, not 8:15 AM. The device may have been offline."
                                value={issueDescription}
                                onChange={(e) => setIssueDescription(e.target.value)}
                                rows={4}
                                className="resize-none"
                            />
                            <p className="text-xs text-muted-foreground">
                                Please provide specific details about the discrepancy
                            </p>
                        </div>
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => {
                            setIsReportIssueOpen(false);
                            setIssueDescription('');
                        }}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction 
                            onClick={handleReportIssue}
                            disabled={!issueDescription.trim()}
                            className="bg-orange-600 hover:bg-orange-700"
                        >
                            Submit Report
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Dialog>
    );
}
