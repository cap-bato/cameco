import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
    TrendingDown,
    ArrowRight,
    LayoutGrid,
    Map as MapIcon
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { EventType } from '@/types/timekeeping-pages';
import { DeviceDetailModal } from './device-detail-modal';
import { DeviceMapView } from './device-map-view';
import { useState } from 'react';

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

interface DeviceStatusDashboardProps {
    devices?: Device[];
    onViewDeviceLog?: (deviceId: string) => void;
    className?: string;
}

/**
 * Mock Device Data
 */
const mockDevices: Device[] = [
    {
        id: "GATE-01",
        location: "Gate 1 - Main Entrance",
        status: "online",
        lastScanAgo: "5 seconds ago",
        lastScanTimestamp: "2026-01-29T08:15:23",
        scansToday: 245,
        uptime: 99.8,
        errorRate: 0.2,
        recentScans: [
            {
                employeeName: "Juan Dela Cruz",
                eventType: "time_in",
                timestamp: "2026-01-29T08:15:23"
            },
            {
                employeeName: "Maria Santos",
                eventType: "time_in",
                timestamp: "2026-01-29T08:14:45"
            },
            {
                employeeName: "Pedro Reyes",
                eventType: "time_in",
                timestamp: "2026-01-29T08:13:30"
            },
            {
                employeeName: "Ana Lopez",
                eventType: "time_out",
                timestamp: "2026-01-29T08:12:15"
            },
            {
                employeeName: "Carlos Garcia",
                eventType: "time_in",
                timestamp: "2026-01-29T08:11:50"
            }
        ]
    },
    {
        id: "GATE-02",
        location: "Gate 2 - Loading Dock",
        status: "idle",
        lastScanAgo: "25 minutes ago",
        lastScanTimestamp: "2026-01-29T07:50:00",
        scansToday: 87,
        uptime: 98.5,
        errorRate: 1.5,
        recentScans: [
            {
                employeeName: "Roberto Diaz",
                eventType: "time_in",
                timestamp: "2026-01-29T07:50:00"
            },
            {
                employeeName: "Linda Fernandez",
                eventType: "time_in",
                timestamp: "2026-01-29T07:45:20"
            },
            {
                employeeName: "Miguel Torres",
                eventType: "time_out",
                timestamp: "2026-01-29T07:40:15"
            },
            {
                employeeName: "Sofia Morales",
                eventType: "time_in",
                timestamp: "2026-01-29T07:35:30"
            },
            {
                employeeName: "Diego Ramirez",
                eventType: "break_start",
                timestamp: "2026-01-29T07:30:45"
            }
        ]
    },
    {
        id: "CAFETERIA-01",
        location: "Cafeteria - Break Scanner",
        status: "offline",
        lastScanAgo: "2 hours ago",
        lastScanTimestamp: "2026-01-29T06:15:00",
        scansToday: 156,
        uptime: 85.2,
        errorRate: 14.8,
        recentScans: []
    },
    {
        id: "OFFICE-01",
        location: "Office Building Entrance",
        status: "online",
        lastScanAgo: "1 minute ago",
        lastScanTimestamp: "2026-01-29T08:14:00",
        scansToday: 198,
        uptime: 99.5,
        errorRate: 0.5,
        recentScans: [
            {
                employeeName: "Patricia Gonzalez",
                eventType: "time_in",
                timestamp: "2026-01-29T08:14:00"
            },
            {
                employeeName: "Ricardo Mendez",
                eventType: "time_in",
                timestamp: "2026-01-29T08:12:30"
            },
            {
                employeeName: "Carmen Ruiz",
                eventType: "time_in",
                timestamp: "2026-01-29T08:10:15"
            },
            {
                employeeName: "Fernando Castro",
                eventType: "time_in",
                timestamp: "2026-01-29T08:08:45"
            },
            {
                employeeName: "Isabel Navarro",
                eventType: "time_in",
                timestamp: "2026-01-29T08:07:20"
            }
        ]
    },
    {
        id: "WAREHOUSE-01",
        location: "Warehouse Entry",
        status: "maintenance",
        lastScanAgo: "3 hours ago",
        lastScanTimestamp: "2026-01-29T05:15:00",
        scansToday: 42,
        uptime: 75.0,
        errorRate: 25.0,
        recentScans: [
            {
                employeeName: "Antonio Silva",
                eventType: "time_in",
                timestamp: "2026-01-29T05:15:00"
            },
            {
                employeeName: "Beatriz Ortiz",
                eventType: "time_in",
                timestamp: "2026-01-29T05:10:30"
            }
        ]
    },
    {
        id: "PRODUCTION-01",
        location: "Production Floor - North",
        status: "online",
        lastScanAgo: "30 seconds ago",
        lastScanTimestamp: "2026-01-29T08:14:45",
        scansToday: 312,
        uptime: 99.9,
        errorRate: 0.1,
        recentScans: [
            {
                employeeName: "Gabriel Herrera",
                eventType: "break_end",
                timestamp: "2026-01-29T08:14:45"
            },
            {
                employeeName: "Elena Vargas",
                eventType: "break_end",
                timestamp: "2026-01-29T08:14:20"
            },
            {
                employeeName: "Oscar Jimenez",
                eventType: "break_start",
                timestamp: "2026-01-29T08:00:15"
            },
            {
                employeeName: "Laura Pena",
                eventType: "break_start",
                timestamp: "2026-01-29T08:00:00"
            },
            {
                employeeName: "Manuel Cruz",
                eventType: "time_in",
                timestamp: "2026-01-29T07:55:30"
            }
        ]
    }
];

/**
 * Calculate device health score based on uptime and error rate
 * Returns: 0-100 score and health grade (excellent/good/fair/poor)
 */
const calculateHealthScore = (uptime: number, errorRate: number): { score: number; grade: string; trend: 'up' | 'down' | 'stable' } => {
    // Weighted score: uptime 70%, error rate 30%
    const uptimeScore = uptime;
    const errorScore = Math.max(0, 100 - (errorRate * 10)); // 10% error = 0 score
    const score = Math.round((uptimeScore * 0.7) + (errorScore * 0.3));
    
    let grade: string;
    if (score >= 95) grade = 'Excellent';
    else if (score >= 85) grade = 'Good';
    else if (score >= 70) grade = 'Fair';
    else grade = 'Poor';
    
    // Simple trend based on current score
    let trend: 'up' | 'down' | 'stable';
    if (score >= 95) trend = 'up';
    else if (score < 70) trend = 'down';
    else trend = 'stable';
    
    return { score, grade, trend };
};

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
                dotColor: 'bg-green-500',
                emoji: '🟢'
            };
        case 'idle':
            return {
                icon: Clock3,
                label: 'Idle',
                color: 'text-yellow-600',
                bgColor: 'bg-yellow-100',
                borderColor: 'border-yellow-200',
                dotColor: 'bg-yellow-500',
                emoji: '🟡'
            };
        case 'offline':
            return {
                icon: AlertCircle,
                label: 'Offline',
                color: 'text-red-600',
                bgColor: 'bg-red-100',
                borderColor: 'border-red-200',
                dotColor: 'bg-red-500',
                emoji: '🔴'
            };
        case 'maintenance':
            return {
                icon: Wrench,
                label: 'Maintenance',
                color: 'text-blue-600',
                bgColor: 'bg-blue-100',
                borderColor: 'border-blue-200',
                dotColor: 'bg-blue-500',
                emoji: '🔧'
            };
        default:
            return {
                icon: Server,
                label: 'Unknown',
                color: 'text-gray-600',
                bgColor: 'bg-gray-100',
                borderColor: 'border-gray-200',
                dotColor: 'bg-gray-500',
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
            return { emoji: '🟢', label: 'Time In', color: 'text-green-700' };
        case 'time_out':
            return { emoji: '🔴', label: 'Time Out', color: 'text-red-700' };
        case 'break_start':
            return { emoji: '☕', label: 'Break Start', color: 'text-orange-700' };
        case 'break_end':
            return { emoji: '▶️', label: 'Break End', color: 'text-blue-700' };
        case 'overtime_start':
            return { emoji: '⏰', label: 'OT Start', color: 'text-purple-700' };
        case 'overtime_end':
            return { emoji: '✅', label: 'OT End', color: 'text-indigo-700' };
        default:
            return { emoji: '⚪', label: eventType, color: 'text-gray-700' };
    }
};

/**
 * Device Card Component
 */
function DeviceCard({ device, onViewLog }: { device: Device; onViewLog?: (deviceId: string) => void }) {
    const statusConfig = getStatusConfig(device.status);
    const StatusIcon = statusConfig.icon;

    return (
        <Card className={cn(
            'relative overflow-hidden transition-all hover:shadow-lg',
            device.status === 'offline' && 'border-red-300 bg-red-50/30',
            device.status === 'maintenance' && 'border-blue-300 bg-blue-50/30'
        )}>
            {/* Status Indicator Stripe */}
            <div className={cn('absolute top-0 left-0 right-0 h-1', statusConfig.dotColor)} />

            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-3 flex-1">
                        {/* Device Icon */}
                        <div className={cn(
                            'h-12 w-12 rounded-lg flex items-center justify-center',
                            statusConfig.bgColor
                        )}>
                            <Server className={cn('h-6 w-6', statusConfig.color)} />
                        </div>

                        {/* Device Info */}
                        <div className="flex-1 min-w-0">
                            <CardTitle className="text-lg font-semibold flex items-center gap-2">
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
                            </CardTitle>
                            <CardDescription className="flex items-center gap-1.5 mt-1">
                                <MapPin className="h-3.5 w-3.5" />
                                {device.location}
                            </CardDescription>
                        </div>
                    </div>

                    {/* Status Icon */}
                    <StatusIcon className={cn('h-5 w-5 flex-shrink-0', statusConfig.color)} />
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {/* Metrics Grid */}
                <div className="grid grid-cols-4 gap-3">
                    {/* Scans Today */}
                    <div className="text-center p-2 bg-slate-50 rounded-lg">
                        <div className="text-xs text-muted-foreground mb-1">Scans Today</div>
                        <div className="text-lg font-bold text-slate-900">{device.scansToday}</div>
                    </div>

                    {/* Uptime with Tooltip */}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="text-center p-2 bg-slate-50 rounded-lg cursor-help">
                                    <div className="text-xs text-muted-foreground mb-1">Uptime</div>
                                    <div className={cn(
                                        'text-lg font-bold',
                                        device.uptime >= 99 ? 'text-green-600' :
                                        device.uptime >= 95 ? 'text-yellow-600' :
                                        'text-red-600'
                                    )}>
                                        {device.uptime}%
                                    </div>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-xs">
                                    Device operational time: {device.uptime}%<br />
                                    {device.uptime >= 99 ? '✅ Excellent uptime' :
                                     device.uptime >= 95 ? '⚠️ Good uptime' :
                                     '❌ Poor uptime - needs attention'}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    {/* Error Rate with Tooltip */}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="text-center p-2 bg-slate-50 rounded-lg cursor-help">
                                    <div className="text-xs text-muted-foreground mb-1">Error Rate</div>
                                    <div className={cn(
                                        'text-lg font-bold',
                                        device.errorRate! <= 1 ? 'text-green-600' :
                                        device.errorRate! <= 5 ? 'text-yellow-600' :
                                        'text-red-600'
                                    )}>
                                        {device.errorRate}%
                                    </div>
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p className="text-xs">
                                    Failed scan rate: {device.errorRate}%<br />
                                    {device.errorRate! <= 1 ? '✅ Excellent performance' :
                                     device.errorRate! <= 5 ? '⚠️ Acceptable performance' :
                                     '❌ High error rate - requires maintenance'}
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    {/* Health Score */}
                    {(() => {
                        const health = calculateHealthScore(device.uptime, device.errorRate || 0);
                        return (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <div className="text-center p-2 bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg cursor-help border border-slate-200">
                                            <div className="text-xs text-muted-foreground mb-1 flex items-center justify-center gap-1">
                                                Health
                                                {health.trend === 'up' && <TrendingUp className="h-3 w-3 text-green-600" />}
                                                {health.trend === 'down' && <TrendingDown className="h-3 w-3 text-red-600" />}
                                            </div>
                                            <div className={cn(
                                                'text-lg font-bold',
                                                health.score >= 95 ? 'text-green-600' :
                                                health.score >= 85 ? 'text-blue-600' :
                                                health.score >= 70 ? 'text-yellow-600' :
                                                'text-red-600'
                                            )}>
                                                {health.score}
                                            </div>
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <div className="text-xs space-y-1">
                                            <p className="font-semibold">Health Score: {health.score}/100</p>
                                            <p>Grade: {health.grade}</p>
                                            <p className="text-muted-foreground">
                                                Calculated from:<br />
                                                • Uptime: {device.uptime}% (70% weight)<br />
                                                • Error Rate: {device.errorRate}% (30% weight)
                                            </p>
                                            {health.score >= 95 && <p className="text-green-600">🎉 Excellent device health!</p>}
                                            {health.score < 70 && <p className="text-red-600">⚠️ Device requires attention</p>}
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        );
                    })()}
                </div>

                {/* Last Scan Info */}
                <div className="flex items-center gap-2 text-sm">
                    <Clock className="h-4 w-4 text-muted-foreground" />
                    <span className="text-muted-foreground">Last scan:</span>
                    <span className={cn(
                        'font-medium',
                        device.status === 'offline' && 'text-red-600',
                        device.status === 'idle' && 'text-yellow-600'
                    )}>
                        {device.lastScanAgo}
                    </span>
                </div>

                <Separator />

                {/* Recent Scans */}
                <div>
                    <div className="flex items-center justify-between mb-2">
                        <h4 className="text-sm font-medium flex items-center gap-2">
                            <Activity className="h-4 w-4 text-muted-foreground" />
                            Recent Activity
                        </h4>
                        <Badge variant="secondary" className="text-xs">
                            Last 5 scans
                        </Badge>
                    </div>

                    {device.recentScans.length === 0 ? (
                        <div className="text-center py-6 text-sm text-muted-foreground bg-slate-50 rounded-lg">
                            <AlertCircle className="h-8 w-8 mx-auto mb-2 text-slate-400" />
                            <p>No recent activity</p>
                            {device.status === 'offline' && (
                                <p className="text-xs mt-1 text-red-600 font-medium">Device is offline</p>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {device.recentScans.map((scan, index) => {
                                const eventConfig = getEventTypeConfig(scan.eventType);
                                const scanTime = new Date(scan.timestamp);
                                
                                return (
                                    <div 
                                        key={index}
                                        className="flex items-center gap-3 p-2 rounded-lg bg-slate-50 hover:bg-slate-100 transition-colors"
                                    >
                                        {/* Employee Avatar */}
                                        <Avatar className="h-8 w-8">
                                            <AvatarFallback className="text-xs bg-slate-200">
                                                {scan.employeeName.split(' ').map(n => n[0]).join('').slice(0, 2)}
                                            </AvatarFallback>
                                        </Avatar>

                                        {/* Scan Info */}
                                        <div className="flex-1 min-w-0">
                                            <div className="text-sm font-medium truncate">
                                                {scan.employeeName}
                                            </div>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <span className={eventConfig.color}>
                                                    {eventConfig.emoji} {eventConfig.label}
                                                </span>
                                                <span>•</span>
                                                <span>{scanTime.toLocaleTimeString()}</span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* View Full Log Button */}
                <Button 
                    variant="outline" 
                    className="w-full"
                    onClick={() => onViewLog?.(device.id)}
                >
                    View Full Log
                    <ArrowRight className="h-4 w-4 ml-2" />
                </Button>
            </CardContent>
        </Card>
    );
}

/**
 * Device Status Dashboard Component
 */
export function DeviceStatusDashboard({
    devices = mockDevices,
    onViewDeviceLog,
    className
}: DeviceStatusDashboardProps) {
    const [selectedDevice, setSelectedDevice] = useState<Device | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'map'>('grid');

    // Handle device log view
    const handleViewLog = (deviceId: string) => {
        const device = devices.find(d => d.id === deviceId);
        if (device) {
            setSelectedDevice(device);
            setIsModalOpen(true);
        }
        onViewDeviceLog?.(deviceId);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        // Delay clearing selected device to allow modal to close smoothly
        setTimeout(() => setSelectedDevice(null), 300);
    };

    // Calculate summary statistics
    const totalDevices = devices.length;
    const onlineDevices = devices.filter(d => d.status === 'online').length;
    const idleDevices = devices.filter(d => d.status === 'idle').length;
    const offlineDevices = devices.filter(d => d.status === 'offline').length;
    const maintenanceDevices = devices.filter(d => d.status === 'maintenance').length;
    const totalScansToday = devices.reduce((sum, d) => sum + d.scansToday, 0);
    const avgUptime = (devices.reduce((sum, d) => sum + d.uptime, 0) / totalDevices).toFixed(1);

    return (
        <div className={cn('space-y-6', className)}>
            {/* Summary Header */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="text-2xl">RFID Device Status Dashboard</CardTitle>
                            <CardDescription>Real-time monitoring of all attendance scanning devices</CardDescription>
                        </div>
                        <div className="flex items-center gap-3">
                            {/* View Toggle */}
                            <div className="flex items-center gap-1 bg-slate-100 rounded-lg border border-slate-200 p-1">
                                <Button
                                    variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('grid')}
                                    className="h-8"
                                >
                                    <LayoutGrid className="h-4 w-4 mr-1" />
                                    Grid
                                </Button>
                                <Button
                                    variant={viewMode === 'map' ? 'default' : 'ghost'}
                                    size="sm"
                                    onClick={() => setViewMode('map')}
                                    className="h-8"
                                >
                                    <MapIcon className="h-4 w-4 mr-1" />
                                    Map
                                </Button>
                            </div>
                            <Separator orientation="vertical" className="h-6" />
                            <TrendingUp className="h-5 w-5 text-green-600" />
                            <span className="text-sm font-medium text-muted-foreground">
                                {totalScansToday.toLocaleString()} scans today
                            </span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
                        {/* Total Devices */}
                        <div className="text-center p-3 bg-slate-50 rounded-lg">
                            <div className="text-xs text-muted-foreground mb-1">Total Devices</div>
                            <div className="text-2xl font-bold">{totalDevices}</div>
                        </div>

                        {/* Online */}
                        <div className="text-center p-3 bg-green-50 rounded-lg border border-green-200">
                            <div className="text-xs text-green-700 mb-1 flex items-center justify-center gap-1">
                                🟢 Online
                            </div>
                            <div className="text-2xl font-bold text-green-600">{onlineDevices}</div>
                        </div>

                        {/* Idle */}
                        <div className="text-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div className="text-xs text-yellow-700 mb-1 flex items-center justify-center gap-1">
                                🟡 Idle
                            </div>
                            <div className="text-2xl font-bold text-yellow-600">{idleDevices}</div>
                        </div>

                        {/* Offline */}
                        <div className="text-center p-3 bg-red-50 rounded-lg border border-red-200">
                            <div className="text-xs text-red-700 mb-1 flex items-center justify-center gap-1">
                                🔴 Offline
                            </div>
                            <div className="text-2xl font-bold text-red-600">{offlineDevices}</div>
                        </div>

                        {/* Maintenance */}
                        <div className="text-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <div className="text-xs text-blue-700 mb-1 flex items-center justify-center gap-1">
                                🔧 Maintenance
                            </div>
                            <div className="text-2xl font-bold text-blue-600">{maintenanceDevices}</div>
                        </div>

                        {/* Avg Uptime */}
                        <div className="text-center p-3 bg-slate-50 rounded-lg">
                            <div className="text-xs text-muted-foreground mb-1">Avg Uptime</div>
                            <div className="text-2xl font-bold text-slate-900">{avgUptime}%</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Device Grid or Map View */}
            {viewMode === 'grid' ? (
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {devices.map((device) => (
                        <DeviceCard 
                            key={device.id} 
                            device={device}
                            onViewLog={handleViewLog}
                        />
                    ))}
                </div>
            ) : (
                <DeviceMapView 
                    devices={devices}
                    onDeviceClick={handleViewLog}
                />
            )}

            {/* Device Detail Modal */}
            <DeviceDetailModal
                device={selectedDevice}
                isOpen={isModalOpen}
                onClose={handleCloseModal}
            />
        </div>
    );
}

/**
 * Export mock data for use in other components
 */
export { mockDevices };
export type { Device, DeviceStatus, RecentScan };
