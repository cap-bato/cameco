import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { 
    ArrowLeft, 
    ChevronLeft, 
    ChevronRight, 
    Clock, 
    MapPin, 
    User, 
    CreditCard,
    Shield,
    Activity,
    Hash,
    CheckCircle,
    XCircle
} from 'lucide-react';

interface AttendanceEvent {
    id: number;
    sequence_id: number;
    employee_id: string;
    employee_name: string;
    event_type: 'time_in' | 'time_out' | 'break_start' | 'break_end';
    timestamp: string;
    device_id: string;
    device_location: string;
    verified: boolean;
    rfid_card: string;
    hash_chain?: string;
    latency_ms?: number;
    source: string;
}

interface RelatedEvents {
    previous?: AttendanceEvent;
    next?: AttendanceEvent;
    employee_today?: AttendanceEvent[];
}

interface EventDetailProps {
    event: AttendanceEvent;
    relatedEvents: RelatedEvents;
}

export default function EventDetail({ event, relatedEvents }: EventDetailProps) {
    const formatTimestamp = (timestamp: string) => {
        return new Date(timestamp).toLocaleString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        });
    };

    const getEventTypeLabel = (type: string) => {
        const labels: Record<string, string> = {
            time_in: 'Time In',
            time_out: 'Time Out',
            break_start: 'Break Start',
            break_end: 'Break End',
        };
        return labels[type] || type;
    };

    const getEventTypeBadgeVariant = (type: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            time_in: 'default',
            time_out: 'secondary',
            break_start: 'outline',
            break_end: 'outline',
        };
        return variants[type] || 'default';
    };

    const route = (name: string, params?: Record<string, string | number> | number | string): string => {
        // This would typically come from window.route if using Laravel Inertia
        // For now, return a basic implementation
        if (!params) {
            return `/app/${name.replace(/\./g, '/')}`;
        }
        
        const paramStr = typeof params === 'object' 
            ? `/${Object.values(params).join('/')}`
            : `/${params}`;
        
        return `/app/${name.replace(/\./g, '/')}${paramStr}`;
    };

    return (
        <AppLayout>
            <Head title={`Event #${event.sequence_id} - Ledger Detail`} />
            
            <div className="container mx-auto py-6 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('hr.timekeeping.ledger.index')}>
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Ledger
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Event Detail</h1>
                            <p className="text-muted-foreground">
                                Sequence ID: #{event.sequence_id}
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        {relatedEvents.previous && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('hr.timekeeping.ledger.show', relatedEvents.previous.sequence_id)}>
                                    <ChevronLeft className="h-4 w-4 mr-1" />
                                    Previous
                                </Link>
                            </Button>
                        )}
                        {relatedEvents.next && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('hr.timekeeping.ledger.show', relatedEvents.next.sequence_id)}>
                                    Next
                                    <ChevronRight className="h-4 w-4 ml-1" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Event Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Activity className="h-5 w-5" />
                                Event Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Event Type</span>
                                    <Badge variant={getEventTypeBadgeVariant(event.event_type)}>
                                        {getEventTypeLabel(event.event_type)}
                                    </Badge>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-start justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Timestamp</span>
                                    <div className="text-right">
                                        <div className="flex items-center gap-2 text-sm font-medium">
                                            <Clock className="h-4 w-4" />
                                            {formatTimestamp(event.timestamp)}
                                        </div>
                                    </div>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Verification Status</span>
                                    <div className="flex items-center gap-2">
                                        {event.verified ? (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-500" />
                                                <span className="text-sm font-medium text-green-600">Verified</span>
                                            </>
                                        ) : (
                                            <>
                                                <XCircle className="h-4 w-4 text-red-500" />
                                                <span className="text-sm font-medium text-red-600">Unverified</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Source</span>
                                    <Badge variant="outline">{event.source}</Badge>
                                </div>
                                
                                {event.latency_ms !== undefined && (
                                    <>
                                        <Separator />
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-muted-foreground">Processing Latency</span>
                                            <span className="text-sm font-medium">{event.latency_ms}ms</span>
                                        </div>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Employee & Device Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Employee & Device
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Employee ID</span>
                                    <span className="text-sm font-medium">{event.employee_id}</span>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Employee Name</span>
                                    <span className="text-sm font-medium">{event.employee_name}</span>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-start justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">RFID Card</span>
                                    <div className="flex items-center gap-2 text-sm font-medium">
                                        <CreditCard className="h-4 w-4" />
                                        {event.rfid_card}
                                    </div>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Device ID</span>
                                    <span className="text-sm font-medium">{event.device_id}</span>
                                </div>
                                
                                <Separator />
                                
                                <div className="flex items-start justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">Device Location</span>
                                    <div className="flex items-center gap-2 text-sm font-medium text-right">
                                        <MapPin className="h-4 w-4" />
                                        {event.device_location}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Cryptographic Information */}
                    {event.hash_chain && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Cryptographic Verification
                                </CardTitle>
                                <CardDescription>
                                    Hash chain for tamper-evident ledger integrity
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <Hash className="h-5 w-5 text-muted-foreground mt-0.5" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-muted-foreground mb-1">Hash Chain</p>
                                            <code className="text-xs bg-muted px-2 py-1 rounded break-all block">
                                                {event.hash_chain}
                                            </code>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Related Events - Employee Today */}
                    {relatedEvents.employee_today && relatedEvents.employee_today.length > 0 && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle>Employee Events Today</CardTitle>
                                <CardDescription>
                                    All events for {event.employee_name} on {new Date(event.timestamp).toLocaleDateString('en-PH')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {relatedEvents.employee_today.map((relatedEvent) => (
                                        <div
                                            key={relatedEvent.sequence_id}
                                            className={`flex items-center justify-between p-3 rounded-lg border ${
                                                relatedEvent.sequence_id === event.sequence_id
                                                    ? 'bg-primary/5 border-primary'
                                                    : 'bg-muted/50'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Badge variant={getEventTypeBadgeVariant(relatedEvent.event_type)}>
                                                    {getEventTypeLabel(relatedEvent.event_type)}
                                                </Badge>
                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Clock className="h-4 w-4" />
                                                    {new Date(relatedEvent.timestamp).toLocaleTimeString('en-PH')}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <MapPin className="h-4 w-4" />
                                                    {relatedEvent.device_location}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-muted-foreground">
                                                    Seq #{relatedEvent.sequence_id}
                                                </span>
                                                {relatedEvent.sequence_id !== event.sequence_id && (
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={route('hr.timekeeping.ledger.show', relatedEvent.sequence_id)}>
                                                            View
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
