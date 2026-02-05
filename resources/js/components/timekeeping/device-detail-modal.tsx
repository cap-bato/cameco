import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Server,
    MapPin,
    Clock,
    Activity,
    AlertCircle,
    CheckCircle2,
    Clock3,
    Wrench,
    TrendingUp,
    Calendar,
    Download,
    RefreshCw,
    Hash,
    Zap
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { EventType } from '@/types/timekeeping-pages';

/**
 * Device Status Types
 */
type DeviceStatus = 'online' | 'idle' | 'offline' | 'maintenance';

/**
 * Recent Scan Entry Interface
 */
interface RecentScan {
    employeeName: string;
    eventType: EventType;
    timestamp: string;
}

/**
 * Device Interface
 */
interface Device {
    id: string;
    location: string;
    status: DeviceStatus;
    lastScanAgo: string;
    lastScanTimestamp: string;
    scansToday: number;
    uptime: number;
    errorRate?: number;
    recentScans: RecentScan[];
}

interface DeviceDetailModalProps {
    device: Device | null;
    isOpen: boolean;
    onClose: () => void;
}

/**
 * Get status badge configuration
 */
const getStatusConfig = (status: DeviceStatus) => {
    switch (status) {
        case 'online':
            return {
                icon: CheckCircle2,
                label: 'Online',
                color: 'text-green-600',
                bgColor: 'bg-green-100',
                borderColor: 'border-green-200',
                emoji: '🟢'
            };
        case 'idle':
            return {
                icon: Clock3,
                label: 'Idle',
                color: 'text-yellow-600',
                bgColor: 'bg-yellow-100',
                borderColor: 'border-yellow-200',
                emoji: '🟡'
            };
        case 'offline':
            return {
                icon: AlertCircle,
                label: 'Offline',
                color: 'text-red-600',
                bgColor: 'bg-red-100',
                borderColor: 'border-red-200',
                emoji: '🔴'
            };
        case 'maintenance':
            return {
                icon: Wrench,
                label: 'Maintenance',
                color: 'text-blue-600',
                bgColor: 'bg-blue-100',
                borderColor: 'border-blue-200',
                emoji: '🔧'
            };
        default:
            return {
                icon: Server,
                label: 'Unknown',
                color: 'text-gray-600',
                bgColor: 'bg-gray-100',
                borderColor: 'border-gray-200',
                emoji: '⚪'
            };
    }
};

/**
 * Get event type display configuration
 */
const getEventTypeConfig = (eventType: EventType) => {
    switch (eventType) {
        case 'time_in':
            return { emoji: '🟢', label: 'Time In', color: 'text-green-700', bgColor: 'bg-green-50' };
        case 'time_out':
            return { emoji: '🔴', label: 'Time Out', color: 'text-red-700', bgColor: 'bg-red-50' };
        case 'break_start':
            return { emoji: '☕', label: 'Break Start', color: 'text-orange-700', bgColor: 'bg-orange-50' };
        case 'break_end':
            return { emoji: '▶️', label: 'Break End', color: 'text-blue-700', bgColor: 'bg-blue-50' };
        case 'overtime_start':
            return { emoji: '⏰', label: 'OT Start', color: 'text-purple-700', bgColor: 'bg-purple-50' };
        case 'overtime_end':
            return { emoji: '✅', label: 'OT End', color: 'text-indigo-700', bgColor: 'bg-indigo-50' };
        default:
            return { emoji: '⚪', label: eventType, color: 'text-gray-700', bgColor: 'bg-gray-50' };
    }
};

/**
 * Generate extended event log for demo (50+ events)
 */
const generateExtendedEventLog = (device: Device): RecentScan[] => {
    if (device.status === 'offline' && device.recentScans.length === 0) {
        return [];
    }

    const employees = [
        'Juan Dela Cruz', 'Maria Santos', 'Pedro Reyes', 'Ana Lopez', 'Carlos Garcia',
        'Roberto Diaz', 'Linda Fernandez', 'Miguel Torres', 'Sofia Morales', 'Diego Ramirez',
        'Patricia Gonzalez', 'Ricardo Mendez', 'Carmen Ruiz', 'Fernando Castro', 'Isabel Navarro',
        'Antonio Silva', 'Beatriz Ortiz', 'Gabriel Herrera', 'Elena Vargas', 'Oscar Jimenez',
        'Laura Pena', 'Manuel Cruz', 'Rosa Martinez', 'Luis Gomez', 'Teresa Lopez'
    ];

    const eventTypes: EventType[] = ['time_in', 'time_out', 'break_start', 'break_end', 'overtime_start', 'overtime_end'];
    
    const extendedLog: RecentScan[] = [];
    const now = new Date('2026-01-29T08:15:00');
    
    // Generate 50 events going back in time
    for (let i = 0; i < 50; i++) {
        const minutesAgo = i * 3; // Every 3 minutes
        const timestamp = new Date(now.getTime() - minutesAgo * 60000);
        
        // Weight event types based on time of day
        const hour = timestamp.getHours();
        let eventType: EventType;
        
        if (hour >= 7 && hour < 9) {
            eventType = Math.random() > 0.3 ? 'time_in' : 'break_start';
        } else if (hour >= 12 && hour < 13) {
            eventType = Math.random() > 0.5 ? 'break_start' : 'break_end';
        } else if (hour >= 17 && hour < 19) {
            eventType = Math.random() > 0.3 ? 'time_out' : 'overtime_start';
        } else {
            eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)];
        }
        
        extendedLog.push({
            employeeName: employees[i % employees.length],
            eventType,
            timestamp: timestamp.toISOString()
        });
    }
    
    return extendedLog;
};

/**
 * Device Detail Modal Component
 */
export function DeviceDetailModal({ device, isOpen, onClose }: DeviceDetailModalProps) {
    // Use lazy state initialization to generate random values only once on mount
    const [randomFirmwareVersion] = useState(() => Math.floor(Math.random() * 10));
    const [randomIpLastOctet] = useState(() => Math.floor(Math.random() * 254) + 1);

    if (!device) return null;

    const statusConfig = getStatusConfig(device.status);
    const StatusIcon = statusConfig.icon;
    const extendedLog = generateExtendedEventLog(device);

    // Calculate hourly scan distribution
    const hourlyDistribution = extendedLog.reduce((acc, scan) => {
        const hour = new Date(scan.timestamp).getHours();
        acc[hour] = (acc[hour] || 0) + 1;
        return acc;
    }, {} as Record<number, number>);

    const peakHour = Object.entries(hourlyDistribution)
        .sort(([, a], [, b]) => b - a)[0];

    // Calculate event type distribution
    const eventTypeDistribution = extendedLog.reduce((acc, scan) => {
        acc[scan.eventType] = (acc[scan.eventType] || 0) + 1;
        return acc;
    }, {} as Record<EventType, number>);

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <div className="flex items-start justify-between">
                        <div className="flex items-start gap-3">
                            <div className={cn(
                                'h-12 w-12 rounded-lg flex items-center justify-center flex-shrink-0',
                                statusConfig.bgColor
                            )}>
                                <Server className={cn('h-6 w-6', statusConfig.color)} />
                            </div>
                            <div>
                                <DialogTitle className="text-2xl flex items-center gap-2">
                                    {device.id}
                                    <Badge variant="outline" className={cn(
                                        'text-xs font-normal',
                                        statusConfig.bgColor,
                                        statusConfig.color,
                                        statusConfig.borderColor
                                    )}>
                                        <span className="mr-1">{statusConfig.emoji}</span>
                                        {statusConfig.label}
                                    </Badge>
                                </DialogTitle>
                                <DialogDescription className="flex items-center gap-1.5 mt-1">
                                    <MapPin className="h-4 w-4" />
                                    {device.location}
                                </DialogDescription>
                            </div>
                        </div>
                        <StatusIcon className={cn('h-6 w-6', statusConfig.color)} />
                    </div>
                </DialogHeader>

                {/* Metrics Overview */}
                <div className="grid grid-cols-4 gap-3 mb-4">
                    <Card>
                        <CardContent className="pt-4 pb-3">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-slate-900">{device.scansToday}</div>
                                <div className="text-xs text-muted-foreground mt-1">Scans Today</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4 pb-3">
                            <div className="text-center">
                                <div className={cn(
                                    'text-2xl font-bold',
                                    device.uptime >= 99 ? 'text-green-600' :
                                    device.uptime >= 95 ? 'text-yellow-600' :
                                    'text-red-600'
                                )}>
                                    {device.uptime}%
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">Uptime</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4 pb-3">
                            <div className="text-center">
                                <div className={cn(
                                    'text-2xl font-bold',
                                    device.errorRate! <= 1 ? 'text-green-600' :
                                    device.errorRate! <= 5 ? 'text-yellow-600' :
                                    'text-red-600'
                                )}>
                                    {device.errorRate}%
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">Error Rate</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-4 pb-3">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-slate-900">{extendedLog.length}</div>
                                <div className="text-xs text-muted-foreground mt-1">Total Events</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="events" className="flex-1 flex flex-col overflow-hidden">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger value="events">
                            <Activity className="h-4 w-4 mr-2" />
                            Event Log
                        </TabsTrigger>
                        <TabsTrigger value="analytics">
                            <TrendingUp className="h-4 w-4 mr-2" />
                            Analytics
                        </TabsTrigger>
                        <TabsTrigger value="info">
                            <Server className="h-4 w-4 mr-2" />
                            Device Info
                        </TabsTrigger>
                    </TabsList>

                    {/* Event Log Tab */}
                    <TabsContent value="events" className="flex-1 overflow-hidden mt-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="text-sm font-medium">
                                Complete Event History ({extendedLog.length} events)
                            </h3>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm">
                                    <RefreshCw className="h-3 w-3 mr-2" />
                                    Refresh
                                </Button>
                                <Button variant="outline" size="sm">
                                    <Download className="h-3 w-3 mr-2" />
                                    Export
                                </Button>
                            </div>
                        </div>
                        
                        <ScrollArea className="h-[400px] rounded-lg border">
                            <div className="p-4 space-y-2">
                                {extendedLog.length === 0 ? (
                                    <div className="text-center py-12">
                                        <AlertCircle className="h-12 w-12 mx-auto mb-3 text-slate-400" />
                                        <p className="text-sm text-muted-foreground">No events recorded</p>
                                        {device.status === 'offline' && (
                                            <p className="text-xs mt-1 text-red-600 font-medium">Device is offline</p>
                                        )}
                                    </div>
                                ) : (
                                    extendedLog.map((scan, index) => {
                                        const eventConfig = getEventTypeConfig(scan.eventType);
                                        const scanTime = new Date(scan.timestamp);
                                        
                                        return (
                                            <div 
                                                key={index}
                                                className={cn(
                                                    'flex items-center gap-3 p-3 rounded-lg transition-colors hover:bg-slate-50',
                                                    eventConfig.bgColor
                                                )}
                                            >
                                                {/* Index */}
                                                <div className="flex-shrink-0 w-12 text-center">
                                                    <div className="text-xs font-mono text-muted-foreground">
                                                        #{extendedLog.length - index}
                                                    </div>
                                                </div>

                                                {/* Employee Avatar */}
                                                <Avatar className="h-9 w-9 flex-shrink-0">
                                                    <AvatarFallback className="text-xs bg-slate-200">
                                                        {scan.employeeName.split(' ').map(n => n[0]).join('').slice(0, 2)}
                                                    </AvatarFallback>
                                                </Avatar>

                                                {/* Event Info */}
                                                <div className="flex-1 min-w-0">
                                                    <div className="text-sm font-medium truncate">
                                                        {scan.employeeName}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <span className={eventConfig.color}>
                                                            {eventConfig.emoji} {eventConfig.label}
                                                        </span>
                                                    </div>
                                                </div>

                                                {/* Timestamp */}
                                                <div className="flex-shrink-0 text-right">
                                                    <div className="text-sm font-medium">
                                                        {scanTime.toLocaleTimeString()}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {scanTime.toLocaleDateString()}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </ScrollArea>
                    </TabsContent>

                    {/* Analytics Tab */}
                    <TabsContent value="analytics" className="flex-1 overflow-hidden mt-4">
                        <ScrollArea className="h-[400px]">
                            <div className="space-y-6 pr-4">
                                {/* Event Type Distribution */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Event Type Distribution</h3>
                                    <div className="space-y-2">
                                        {Object.entries(eventTypeDistribution).map(([type, count]) => {
                                            const eventConfig = getEventTypeConfig(type as EventType);
                                            const percentage = ((count / extendedLog.length) * 100).toFixed(1);
                                            
                                            return (
                                                <div key={type} className="space-y-1">
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className={eventConfig.color}>
                                                            {eventConfig.emoji} {eventConfig.label}
                                                        </span>
                                                        <span className="font-medium">{count} ({percentage}%)</span>
                                                    </div>
                                                    <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div 
                                                            className={cn('h-full transition-all', eventConfig.bgColor)}
                                                            style={{ width: `${percentage}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                <Separator />

                                {/* Peak Activity */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Peak Activity Hours</h3>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Card>
                                            <CardContent className="pt-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <Clock className="h-5 w-5 text-blue-600" />
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">Peak Hour</div>
                                                        <div className="text-lg font-bold">
                                                            {peakHour ? `${peakHour[0]}:00` : 'N/A'}
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardContent className="pt-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                                                        <Zap className="h-5 w-5 text-purple-600" />
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">Peak Volume</div>
                                                        <div className="text-lg font-bold">
                                                            {peakHour ? peakHour[1] : 0} scans
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>

                                <Separator />

                                {/* Performance Metrics */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Performance Metrics</h3>
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm">Average Response Time</span>
                                            <span className="font-medium text-green-600">127ms</span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm">Success Rate</span>
                                            <span className="font-medium text-green-600">
                                                {(100 - device.errorRate!).toFixed(1)}%
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm">Failed Scans Today</span>
                                            <span className="font-medium text-red-600">
                                                {Math.round(device.scansToday * device.errorRate! / 100)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </TabsContent>

                    {/* Device Info Tab */}
                    <TabsContent value="info" className="flex-1 overflow-hidden mt-4">
                        <ScrollArea className="h-[400px]">
                            <div className="space-y-6 pr-4">
                                {/* Device Details */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Device Details</h3>
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Device ID</span>
                                            <span className="font-mono text-sm font-medium">{device.id}</span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Location</span>
                                            <span className="text-sm font-medium">{device.location}</span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Status</span>
                                            <Badge variant="outline" className={cn(
                                                'text-xs',
                                                statusConfig.bgColor,
                                                statusConfig.color
                                            )}>
                                                {statusConfig.emoji} {statusConfig.label}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Last Scan</span>
                                            <span className="text-sm font-medium">{device.lastScanAgo}</span>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Technical Info */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Technical Information</h3>
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">IP Address</span>
                                            <span className="font-mono text-sm">192.168.1.{randomIpLastOctet}</span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Firmware Version</span>
                                            <span className="font-mono text-sm">v2.4.{randomFirmwareVersion}</span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Last Sync</span>
                                            <span className="text-sm">
                                                {new Date(device.lastScanTimestamp).toLocaleString()}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <span className="text-sm text-muted-foreground">Connection Type</span>
                                            <span className="text-sm">Ethernet (Wired)</span>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                {/* Maintenance Schedule */}
                                <div>
                                    <h3 className="text-sm font-medium mb-3">Maintenance Schedule</h3>
                                    <div className="space-y-2">
                                        <div className="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                            <Calendar className="h-4 w-4 text-muted-foreground mt-0.5" />
                                            <div className="flex-1">
                                                <div className="text-sm font-medium">Next Maintenance</div>
                                                <div className="text-xs text-muted-foreground mt-1">
                                                    February 15, 2026 • 2:00 PM
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                            <Hash className="h-4 w-4 text-muted-foreground mt-0.5" />
                                            <div className="flex-1">
                                                <div className="text-sm font-medium">Last Maintenance</div>
                                                <div className="text-xs text-muted-foreground mt-1">
                                                    January 15, 2026 • 3:30 PM
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
